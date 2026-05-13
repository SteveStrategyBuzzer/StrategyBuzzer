<?php

namespace App\Services\QuestionBank\Worker;

use App\Models\QuestionGroup;
use App\Services\QuestionBank\QuestionBankRepository;
use Illuminate\Support\Facades\DB;

/**
 * Reject-before-insert guards for generated bank candidates.
 *
 * A generation is rejected (and re-queued with back-off) when ANY of the
 * following holds. Each rejection returns a stable code that the worker
 * logs into Redis (qb:worker:last_rejects) and surfaces in the health
 * endpoint for triage:
 *
 *   - dup_concept_id        : concept_id already in same segment
 *   - concept_family_share  : concept_family above per-segment cap
 *   - text_similarity       : Jaccard token-shingle similarity > threshold
 *   - missing_saviez_vous   : at least one required language has no/weak saviez_vous
 *   - cognitive_mismatch    : heuristic flags wrong cognitive_type
 *   - depth_incoherent      : heuristic flags wrong depth band
 *   - answer_key_misaligned : letter A-D doesn't refer to same logical answer in all langs
 *   - missing_translations  : worker config requires more languages than provided
 */
class QualityGuards
{
    public function __construct(
        private readonly QuestionBankRepository $repo
    ) {}

    /**
     * Run every guard against a candidate payload.
     *
     * @param  array  $payload  same shape as QuestionBankRepository::addToBank()
     * @return array{ok:bool, code?:string, detail?:string}
     */
    public function evaluate(array $payload): array
    {
        $config = config('question_bank_profiles');
        $worker = $config['worker'];
        $guards = $worker['guards'];

        // 1. Required languages present.
        $translations = $payload['translations'] ?? [];
        foreach ($worker['min_required_languages'] as $req) {
            if (empty($translations[$req]['question_text'])) {
                return ['ok' => false, 'code' => 'missing_translations', 'detail' => "missing language: {$req}"];
            }
        }

        // 2. Saviez_vous strength on every supplied translation.
        // CJK/Arabic scripts are semantically denser per character, so they
        // use a lower per-language threshold defined in guards config.
        $minSvDefault  = (int) $guards['saviez_vous_min_length'];
        $minSvByLang   = (array) ($guards['saviez_vous_min_length_by_lang'] ?? []);
        foreach ($translations as $lang => $tr) {
            $minSv = isset($minSvByLang[$lang]) ? (int) $minSvByLang[$lang] : $minSvDefault;
            $sv = trim((string) ($tr['saviez_vous'] ?? ''));
            if (mb_strlen($sv) < $minSv) {
                return ['ok' => false, 'code' => 'missing_saviez_vous', 'detail' => "{$lang} too short ({$lang} = ".mb_strlen($sv)." < {$minSv})"];
            }
            // Trivial / tautological detector.
            $svLower = mb_strtolower($sv);
            if (str_contains($svLower, 'cette question') || str_contains($svLower, 'this question')) {
                return ['ok' => false, 'code' => 'missing_saviez_vous', 'detail' => "{$lang} tautological"];
            }
        }

        // 3. Answer-key alignment: the correct letter must point to the
        // same logical answer position in every translation.
        $expectedKey = strtoupper((string) ($translations[array_key_first($translations)]['correct_answer_key'] ?? ''));
        foreach ($translations as $lang => $tr) {
            $k = strtoupper((string) ($tr['correct_answer_key'] ?? ''));
            if ($k !== $expectedKey) {
                return ['ok' => false, 'code' => 'answer_key_misaligned', 'detail' => "{$lang} key={$k} expected={$expectedKey}"];
            }
        }

        // 4. Concept_id collision in same segment.
        $conceptId = $payload['concept_id'] ?? null;
        if ($conceptId) {
            $exists = QuestionGroup::query()
                ->where('concept_id', $conceptId)
                ->where('domain', $payload['domain'])
                ->where('sub_domain', $payload['sub_domain'])
                ->where('difficulty_depth', $payload['difficulty_depth'])
                ->where('cognitive_type', $payload['cognitive_type'])
                ->exists();
            if ($exists) {
                return ['ok' => false, 'code' => 'dup_concept_id', 'detail' => $conceptId];
            }
        }

        // 5. Concept_family share inside the segment.
        $family = $payload['concept_family'] ?? null;
        if ($family) {
            $segmentBase = QuestionGroup::query()
                ->where('domain', $payload['domain'])
                ->where('sub_domain', $payload['sub_domain'])
                ->where('cognitive_type', $payload['cognitive_type']);

            if (isset($payload['difficulty_level'])) {
                $segmentBase->where('difficulty_level', $payload['difficulty_level']);
            } elseif (isset($payload['boss_level'])) {
                $segmentBase->where('boss_level', $payload['boss_level']);
            }

            $segmentTotal = (clone $segmentBase)->count();
            $familyCount = (clone $segmentBase)->where('concept_family', $family)->count();

            if ($segmentTotal > 0 && $familyCount > 0) {
                $share = ($familyCount + 1) / ($segmentTotal + 1);
                if ($share > $guards['concept_family_segment_max_share']) {
                    return ['ok' => false, 'code' => 'concept_family_share', 'detail' => sprintf('%.2f > %.2f', $share, $guards['concept_family_segment_max_share'])];
                }
            }
        }

        // 6. Text similarity vs existing same-segment FR text (cheap & deterministic).
        $candidateText = (string) ($translations['fr']['question_text'] ?? $translations[array_key_first($translations)]['question_text'] ?? '');
        if ($candidateText !== '') {
            $cap = (float) $guards['text_similarity_max'];
            $segmentTexts = DB::table('question_translations')
                ->join('question_groups', 'question_groups.id', '=', 'question_translations.question_group_id')
                ->where('question_groups.domain', $payload['domain'])
                ->where('question_groups.sub_domain', $payload['sub_domain'])
                ->where('question_groups.cognitive_type', $payload['cognitive_type'])
                ->where('question_translations.language', 'fr')
                ->limit(200)
                ->pluck('question_translations.question_text');

            foreach ($segmentTexts as $existing) {
                if ($this->jaccardShingle($candidateText, (string) $existing) >= $cap) {
                    return ['ok' => false, 'code' => 'text_similarity', 'detail' => 'too close to existing'];
                }
            }
        }

        // 7. Cognitive mismatch heuristic. A "recognition" question shouldn't
        // require multi-step reasoning; we flag obvious chained reasoning words.
        if (($payload['cognitive_type'] ?? null) === 'recognition') {
            $reasoningMarkers = ['parmi', 'sachant que', 'donc', 'en déduire', 'calculer', 'résoudre'];
            $low = mb_strtolower($candidateText);
            $hits = 0;
            foreach ($reasoningMarkers as $m) {
                if (str_contains($low, $m)) {
                    $hits++;
                }
            }
            if ($hits >= 2) {
                return ['ok' => false, 'code' => 'cognitive_mismatch', 'detail' => "recognition flagged with {$hits} reasoning markers"];
            }
        }

        // 8. Depth incoherence heuristic. A depth ≥ 9 question should not be
        // a one-word fact lookup. Length is a crude proxy but cheap.
        $depth = (int) $payload['difficulty_depth'];
        if ($depth >= 9 && mb_strlen(trim($candidateText)) < 40) {
            return ['ok' => false, 'code' => 'depth_incoherent', 'detail' => "depth={$depth} text too short"];
        }

        return ['ok' => true];
    }

    /**
     * Jaccard similarity over 3-token shingles. Tolerant to small wording
     * changes, harsh on real reformulations.
     */
    private function jaccardShingle(string $a, string $b): float
    {
        $sA = $this->shingles($a);
        $sB = $this->shingles($b);
        if (empty($sA) && empty($sB)) {
            return 0.0;
        }
        $inter = count(array_intersect($sA, $sB));
        $union = count(array_unique(array_merge($sA, $sB)));
        return $union === 0 ? 0.0 : $inter / $union;
    }

    private function shingles(string $text, int $k = 3): array
    {
        $norm = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower($text));
        $tokens = preg_split('/\s+/u', trim((string) $norm)) ?: [];
        if (count($tokens) < $k) {
            return $tokens;
        }
        $out = [];
        for ($i = 0, $n = count($tokens) - $k + 1; $i < $n; $i++) {
            $out[] = implode(' ', array_slice($tokens, $i, $k));
        }
        return $out;
    }
}
