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
 *   - missing_translations         : worker config requires more languages than provided
 *   - saviez_vous_contradicts_answer : saviez_vous cites a distractor but not the correct answer
 *   - saviez_vous_tautological       : saviez_vous is a near-reformulation of the question text
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

        // 9. Correct-answer text frequency cap.
        //
        // When the same text ("Chine", "Picasso", "Australie"…) is already the
        // correct answer for too many questions in the same sub_domain, adding
        // one more makes the correct answer predictable by pattern-matching.
        // We count existing FR rows where the keyed answer column matches.
        //
        // The check is skipped for true/false questions (Vrai/Faux are always
        // the only two options and frequency is irrelevant).
        if (($payload['question_type'] ?? 'qcm') === 'qcm') {
            $frTr       = $translations['fr'] ?? $translations[array_key_first($translations)];
            $correctKey = strtoupper((string) ($frTr['correct_answer_key'] ?? 'A'));
            $columnMap  = ['A' => 'answer_a', 'B' => 'answer_b', 'C' => 'answer_c', 'D' => 'answer_d'];
            $answerCol  = $columnMap[$correctKey] ?? null;
            $answerText = $answerCol ? trim((string) ($frTr[$answerCol] ?? '')) : '';

            if ($answerText !== '' && $answerCol !== null) {
                $maxFreq = (int) ($guards['correct_answer_text_max_freq'] ?? 12);

                $freq = DB::table('question_translations as qt')
                    ->join('question_groups as qg', 'qg.id', '=', 'qt.question_group_id')
                    ->where('qg.sub_domain', $payload['sub_domain'])
                    ->where('qt.language', 'fr')
                    ->where('qt.correct_answer_key', $correctKey)
                    ->where("qt.{$answerCol}", $answerText)
                    ->count();

                if ($freq >= $maxFreq) {
                    return [
                        'ok'     => false,
                        'code'   => 'correct_answer_overused',
                        'detail' => "correct answer '{$answerText}' already appears {$freq}× in sub_domain '{$payload['sub_domain']}' (max {$maxFreq})",
                    ];
                }
            }
        }

        // 10. Saviez_vous topic relevance — French only (cheap sanity check).
        //
        // The saviez_vous must be about the same fact as the question itself.
        // We enforce a minimum Jaccard overlap between the saviez_vous tokens
        // and the union of (question_text + answer texts). A saviez_vous that
        // shares no tokens with the question is almost certainly cross-contaminated
        // (fact from a different question injected by the LLM).
        //
        // Threshold 0.04 (≥1 shared 3-shingle in a typical 60-word text) is
        // intentionally low to avoid false positives on correct paraphrases.
        $frTr      = $translations['fr'] ?? null;
        if ($frTr !== null) {
            $sv = trim((string) ($frTr['saviez_vous'] ?? ''));
            if ($sv !== '') {
                $questionContext = implode(' ', array_filter([
                    $frTr['question_text'] ?? '',
                    $frTr['answer_a'] ?? '',
                    $frTr['answer_b'] ?? '',
                    $frTr['answer_c'] ?? '',
                    $frTr['answer_d'] ?? '',
                ]));
                $overlap = $this->jaccardShingle($sv, $questionContext);
                if ($overlap < 0.04 && mb_strlen($questionContext) > 20) {
                    // Fallback: keyword overlap.
                    // A good saviez_vous enriches the topic with new vocabulary
                    // (e.g. "Folie de Seward" for an Alaska question). The 3-shingle
                    // metric misses these because no 3-consecutive-token run matches.
                    // If at least 1 significant word (>3 chars, normalised) is shared
                    // between saviez_vous and question context, the SV is on-topic.
                    if (!$this->hasKeywordOverlap($sv, $questionContext)) {
                        return [
                            'ok'     => false,
                            'code'   => 'saviez_vous_off_topic',
                            'detail' => sprintf(
                                'saviez_vous Jaccard overlap=%.3f with question context (< 0.04) — likely cross-contaminated',
                                $overlap
                            ),
                        ];
                    }
                }
            }
        }

        // ── PATCH GROUP QUALITÉ CONTENU ────────────────────────────────────

        // 11. question_text length cap — gameplay readability guard.
        //
        // Players have ≤30 s per question on mobile; a 150-char question
        // already consumes ~10 s of reading time at 15 chars/s. We check
        // every supplied translation against its per-language cap (CJK/Arabic
        // are denser per character so they get a lower absolute limit).
        $qMaxDefault      = (int) ($guards['question_text_max_length']         ?? 150);
        $qMaxByLang       = (array) ($guards['question_text_max_length_by_lang'] ?? []);
        foreach ($translations as $lang => $tr) {
            $qMax  = isset($qMaxByLang[$lang]) ? (int) $qMaxByLang[$lang] : $qMaxDefault;
            $qText = mb_strlen(trim((string) ($tr['question_text'] ?? '')));
            if ($qText > $qMax) {
                return [
                    'ok'     => false,
                    'code'   => 'question_too_long',
                    'detail' => "{$lang} question_text={$qText} > max={$qMax}",
                ];
            }
        }

        // 12. answer choice length cap — button readability guard.
        //
        // Answer buttons are ~180 px wide on a 375 px screen. More than 80
        // chars wraps over 3 lines and makes fast scanning impossible.
        $aMaxDefault = (int) ($guards['answer_max_length']         ?? 80);
        $aMaxByLang  = (array) ($guards['answer_max_length_by_lang'] ?? []);
        foreach ($translations as $lang => $tr) {
            $aMax    = isset($aMaxByLang[$lang]) ? (int) $aMaxByLang[$lang] : $aMaxDefault;
            $answers = [
                'answer_a' => (string) ($tr['answer_a'] ?? ''),
                'answer_b' => (string) ($tr['answer_b'] ?? ''),
                'answer_c' => (string) ($tr['answer_c'] ?? ''),
                'answer_d' => (string) ($tr['answer_d'] ?? ''),
            ];
            foreach ($answers as $field => $text) {
                $text = trim($text);
                if ($text === '' || $text === 'null') {
                    continue; // true/false questions have null answer_d
                }
                $len = mb_strlen($text);
                if ($len > $aMax) {
                    return [
                        'ok'     => false,
                        'code'   => 'answer_too_long',
                        'detail' => "{$lang}.{$field} len={$len} > max={$aMax}",
                    ];
                }
            }
        }

        // 13. saviez_vous length cap — RESULT screen readability guard.
        //
        // The RESULT screen auto-advances after ~4 s. 220 chars is the hard
        // ceiling (~1 500 ms reading at average speed). CJK/Arabic get tighter
        // caps because their scripts are denser per character.
        $svMaxDefault = (int) ($guards['saviez_vous_max_length']         ?? 220);
        $svMaxByLang  = (array) ($guards['saviez_vous_max_length_by_lang'] ?? []);
        foreach ($translations as $lang => $tr) {
            $svMax = isset($svMaxByLang[$lang]) ? (int) $svMaxByLang[$lang] : $svMaxDefault;
            $sv    = mb_strlen(trim((string) ($tr['saviez_vous'] ?? '')));
            if ($sv > $svMax) {
                return [
                    'ok'     => false,
                    'code'   => 'saviez_vous_too_long',
                    'detail' => "{$lang} saviez_vous={$sv} > max={$svMax}",
                ];
            }
        }

        // 15. Semantic consistency — saviez_vous must not endorse a distractor.
        //
        // If the saviez_vous explicitly contains the text of a WRONG answer
        // (a distractor) while NOT containing the text of the CORRECT answer,
        // the AI almost certainly mixed up the answer key — the archetypal
        // failure is "La bataille de la Boyne a eu lieu en 1690" when the
        // correct_answer_key points to "1688".
        //
        // Guard logic (French only, skipped for true_false):
        //   1. Resolve the correct answer text from correct_answer_key.
        //   2. Build the list of distractor texts (other answer slots).
        //   3. If any distractor (≥ 4 chars) appears in saviez_vous AND the
        //      correct answer text does NOT → reject saviez_vous_contradicts_answer.
        //
        // Short answers (< 4 chars: "Oui", "Non", "Vrai", "Faux", numbers < 4
        // digits) are excluded from distractor matching to avoid false positives.
        if (($payload['question_type'] ?? '') !== 'true_false' && $frTr !== null) {
            $svForConsistency  = trim((string) ($frTr['saviez_vous'] ?? ''));
            $correctKeyForSV   = strtoupper((string) ($frTr['correct_answer_key'] ?? ''));
            $slotToAnswer      = [
                'A' => trim((string) ($frTr['answer_a'] ?? '')),
                'B' => trim((string) ($frTr['answer_b'] ?? '')),
                'C' => trim((string) ($frTr['answer_c'] ?? '')),
                'D' => trim((string) ($frTr['answer_d'] ?? '')),
            ];
            $correctAnswerText = $slotToAnswer[$correctKeyForSV] ?? '';

            if ($svForConsistency !== '' && mb_strlen($correctAnswerText) >= 4) {
                $correctPresentInSv   = mb_stripos($svForConsistency, $correctAnswerText) !== false;
                $distractorInSv       = false;
                $matchedDistractorVal = '';

                foreach ($slotToAnswer as $slot => $answerText) {
                    if ($slot === $correctKeyForSV || mb_strlen($answerText) < 4) {
                        continue;
                    }
                    if (mb_stripos($svForConsistency, $answerText) !== false) {
                        $distractorInSv       = true;
                        $matchedDistractorVal = $answerText;
                        break;
                    }
                }

                if ($distractorInSv && !$correctPresentInSv) {
                    return [
                        'ok'     => false,
                        'code'   => 'saviez_vous_contradicts_answer',
                        'detail' => sprintf(
                            'saviez_vous mentions distractor "%s" but not correct answer "%s" (key=%s) — likely answer_key mismatch',
                            $matchedDistractorVal,
                            $correctAnswerText,
                            $correctKeyForSV
                        ),
                    ];
                }
            }
        }

        // 16. Anti-tautology — saviez_vous must add new information.
        //
        // A saviez_vous that merely restates the question with the answer
        // inserted provides zero cognitive value (e.g., "La bataille de la
        // Boyne a eu lieu en 1690" for the question "En quelle année s'est
        // déroulée la bataille de la Boyne ?"). We detect this with 3-shingle
        // Jaccard overlap between the saviez_vous and the question_text alone
        // (answers are intentionally excluded so that a saviez_vous that adds
        // context around the correct answer is not penalised).
        //
        // Threshold 0.20: calibrated to catch clear question→answer phrasings
        // while allowing legitimately related content (same topic, different fact).
        if ($frTr !== null) {
            $svForTauto = trim((string) ($frTr['saviez_vous']    ?? ''));
            $qtForTauto = trim((string) ($frTr['question_text']  ?? ''));
            if ($svForTauto !== '' && $qtForTauto !== '') {
                $tautoOverlap = $this->jaccardShingle($svForTauto, $qtForTauto);
                if ($tautoOverlap > 0.20) {
                    return [
                        'ok'     => false,
                        'code'   => 'saviez_vous_tautological',
                        'detail' => sprintf(
                            'saviez_vous Jaccard overlap=%.3f with question_text (> 0.20) — likely reformulation, must add new information',
                            $tautoOverlap
                        ),
                    ];
                }
            }
        }

        // 14. Negative / ambiguous question framing — gameplay clarity guard.
        //
        // "Lequel n'est PAS …", "Sauf …", "Aucun de ces …" framings are
        // cognitively expensive under time pressure and systematically produce
        // ambiguous correct answers (player must rule out 3 facts instead of
        // recognising 1). Check runs on French text only (cheap string scan).
        $frQuestion = mb_strtolower(trim((string) ($translations['fr']['question_text'] ?? '')));
        if ($frQuestion !== '') {
            $negKeywords = (array) ($guards['negative_framing_keywords'] ?? []);
            foreach ($negKeywords as $kw) {
                if (str_contains($frQuestion, mb_strtolower((string) $kw))) {
                    return [
                        'ok'     => false,
                        'code'   => 'negative_framing',
                        'detail' => "question contains forbidden framing: \"{$kw}\"",
                    ];
                }
            }
        }

        // ── END PATCH GROUP QUALITÉ CONTENU ────────────────────────────────

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

    /**
     * Keyword overlap fallback for saviez_vous topic relevance.
     *
     * Returns true if at least one significant word (>3 chars, normalised)
     * appears in both texts. Used when the 3-shingle Jaccard score is below
     * threshold but the saviez_vous may still be on-topic — e.g. "Folie de
     * Seward" enriches an Alaska question without repeating "vendu en 1867".
     *
     * Two matching strategies (OR):
     *   1. Exact token match (>3 chars)
     *   2. Prefix match: if both tokens are ≥5 chars and one starts with the
     *      other, count as a match. Handles French morphology (éruption /
     *      éruptions, volcan / volcanique, glacial / glacier …) without a
     *      full stemmer.
     *
     * Rules (per user spec):
     *   - lowercase normalisation
     *   - punctuation stripped
     *   - only tokens with mb_strlen > 3 qualify as significant
     *   - intersection ≥ 1 → on-topic
     */
    private function hasKeywordOverlap(string $sv, string $context): bool
    {
        $normalize = static function (string $text): array {
            $norm   = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower($text));
            $tokens = preg_split('/\s+/u', trim((string) $norm)) ?: [];
            return array_values(
                array_filter($tokens, static fn (string $t) => mb_strlen($t) > 3)
            );
        };

        $svTokens  = $normalize($sv);
        $ctxTokens = $normalize($context);

        // Strategy 1: exact match
        $svKeys = array_flip($svTokens);
        foreach ($ctxTokens as $ct) {
            if (isset($svKeys[$ct])) {
                return true;
            }
        }

        // Strategy 2: prefix match (≥5 chars each side — avoids short noise)
        foreach ($ctxTokens as $ct) {
            if (mb_strlen($ct) < 5) {
                continue;
            }
            foreach ($svTokens as $st) {
                if (mb_strlen($st) < 5) {
                    continue;
                }
                // One is a prefix of the other → same lexical root
                if (str_starts_with($ct, $st) || str_starts_with($st, $ct)) {
                    return true;
                }
            }
        }

        return false;
    }
}
