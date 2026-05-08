<?php

namespace App\Services\QuestionBank\Worker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bank-side LLM caller — two-step master/translate pipeline.
 *
 * Architecture (Option B, locked in after audit):
 *
 *   STEP 1  POST /generate-bank-question  languages=["fr"]
 *           → one validated master question in French only.
 *
 *   STEP 2  POST /translate-bank-question  master=<fr payload>  target_languages=[en,es,…]
 *           → exact translations of the master. The Node endpoint enforces:
 *               • same correct_answer_key in every language
 *               • same answer positions (A→A, B→B, …)
 *               • same answer count as master
 *               • saviez_vous + explanation non-empty per language
 *
 *   MERGE   masterPayload['translations'] receives the 9 translated languages.
 *           shapeIntoPayload() then produces the canonical addToBank() payload
 *           (1 question_group + up to 10 question_translations).
 *
 * Failure handling:
 *   • STEP 1 fails → generateForSegment() returns ok:false → BankWorker backoff.
 *   • STEP 2 fails → warning logged, FR-only stored (validated=false).
 *     validated=false groups must NOT be selected for multilingual/world modes.
 *     The next worker cycle will generate a completely new concept.
 *
 * AI credentials live exclusively on the Node Question API service.
 * The worker remains the only PHP caller of the router endpoints
 * (architectural constraint locked in by #88).
 */
class BankAIGenerator
{
    private const REQUEST_TIMEOUT_SECONDS = 90;

    /**
     * Orchestrates the generate → translate → merge pipeline for one segment.
     *
     * @param  array  $segment  one row from BankNeedsCalculator output
     * @return array{ok:bool, payload?:array, error?:string, http_status?:int}
     */
    public function generateForSegment(array $segment): array
    {
        // ── STEP 1 : master question in French ───────────────────────────────
        $masterResult = $this->generateMaster($segment);
        if (!$masterResult['ok']) {
            return $masterResult;
        }
        $masterPayload = $masterResult['payload'];

        // ── STEP 2 : exact translations → 9 other languages ─────────────────
        $allLanguages    = config('question_bank_profiles.worker.preferred_languages', ['fr']);
        $targetLanguages = array_values(array_filter($allLanguages, fn ($l) => $l !== 'fr'));

        if (!empty($targetLanguages)) {
            $translateResult = $this->translateMaster($masterPayload, $targetLanguages);

            if ($translateResult['ok']) {
                // Merge: the 9 translations are added beside the existing fr
                // translation that generateMaster() already put in translations[].
                foreach ($translateResult['translations'] as $lang => $tr) {
                    $masterPayload['translations'][$lang] = $tr;
                }
            } else {
                // Translation failed. Store FR-only (validated=false) so the
                // question_group is created but excluded from multilingual modes
                // until the bank is refilled with a fully translated group.
                Log::warning('[BankAIGenerator] translation step failed — storing FR only (validated=false)', [
                    'error'      => $translateResult['error'] ?? 'unknown',
                    'segment'    => [
                        'domain'         => $segment['domain'] ?? null,
                        'sub_domain'     => $segment['sub_domain'] ?? null,
                        'cognitive_type' => $segment['cognitive_type'] ?? null,
                    ],
                ]);
            }
        }

        // ── STEP 3 : shape into addToBank() canonical payload ────────────────
        $payload = $this->shapeIntoPayload($masterPayload, $segment);
        if ($payload === null) {
            return ['ok' => false, 'error' => 'shape mismatch after merge'];
        }

        return ['ok' => true, 'payload' => $payload];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * STEP 1 — Call POST /generate-bank-question with languages=["fr"].
     * Returns the raw router payload (not yet shaped for addToBank).
     *
     * @return array{ok:bool, payload?:array, error?:string, http_status?:int}
     */
    private function generateMaster(array $segment): array
    {
        $endpoint = rtrim(env('QUESTION_API_URL', 'http://localhost:3000'), '/')
            . '/generate-bank-question';

        $body = [
            'domain'           => (string) $segment['domain'],
            'sub_domain'       => (string) $segment['sub_domain'],
            'cognitive_type'   => (string) $segment['cognitive_type'],
            'question_type'    => (string) ($segment['question_type'] ?? 'qcm'),
            'difficulty_depth' => (int) $segment['depth_range'][1],
            'languages'        => ['fr'],   // master in French only
        ];

        // Segment-context XOR (#91 contract): body MUST carry exactly one of
        // difficulty_level (Solo) or boss_level (Boss). DB CHECK enforces the
        // same XOR at the storage layer.
        $target = $segment['mode_target'];
        if (($target['type'] ?? null) === 'boss') {
            $body['boss_level'] = (int) $target['level'];
        } else {
            // Pin to LOW end of the band so the row is reusable across the
            // entire band; the planner only filters by depth_range.
            $body['difficulty_level'] = (int) $target['levels'][0];
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)->post($endpoint, $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'transport (master): ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            $detail = $this->extractRouterError($response);
            return [
                'ok'          => false,
                'error'       => 'router http error (master)' . ($detail ? ' (' . $detail . ')' : ''),
                'http_status' => $response->status(),
            ];
        }

        $json = $response->json();
        if (!is_array($json) || ($json['ok'] ?? false) !== true || !is_array($json['payload'] ?? null)) {
            return ['ok' => false, 'error' => 'router returned invalid envelope (master)'];
        }

        return ['ok' => true, 'payload' => $json['payload']];
    }

    /**
     * STEP 2 — Call POST /translate-bank-question with the FR master payload
     * and the list of target languages.
     *
     * The Node endpoint enforces strict translation rules:
     *   - same correct_answer_key as master in every language
     *   - same answer count and positions (A→A, B→B, …)
     *   - no reformulation, no cultural substitution
     *
     * The "master" block sent to the endpoint is built from
     * translations['fr'] (primary) then falls back to the top-level
     * payload fields emitted by /generate-bank-question.
     *
     * @param  array    $masterPayload   raw payload from generateMaster()
     * @param  string[] $targetLanguages e.g. ['en','es','it','de','pt','ru','zh','ar','el']
     * @return array{ok:bool, translations?:array, error?:string}
     */
    private function translateMaster(array $masterPayload, array $targetLanguages): array
    {
        $endpoint = rtrim(env('QUESTION_API_URL', 'http://localhost:3000'), '/')
            . '/translate-bank-question';

        // Prefer translations['fr'] as source; fall back to top-level fields
        // (the generate endpoint puts top-level + translations['fr'] for fr).
        $fr = $masterPayload['translations']['fr'] ?? [];

        $master = [
            'question_text'     => (string) ($fr['question_text']      ?? $masterPayload['question_text']      ?? ''),
            'answer_a'          => (string) ($fr['answer_a']           ?? $masterPayload['answer_a']           ?? ''),
            'answer_b'          => (string) ($fr['answer_b']           ?? $masterPayload['answer_b']           ?? ''),
            'answer_c'          => (string) ($fr['answer_c']           ?? $masterPayload['answer_c']           ?? ''),
            'answer_d'          => (string) ($fr['answer_d']           ?? $masterPayload['answer_d']           ?? ''),
            'correct_answer_key'=> strtoupper((string) ($fr['correct_answer_key'] ?? $masterPayload['correct_answer_key'] ?? 'A')),
            'explanation'       => (string) ($fr['explanation']        ?? $masterPayload['explanation']        ?? ''),
            'saviez_vous'       => (string) ($fr['saviez_vous']        ?? $masterPayload['saviez_vous']        ?? ''),
            'question_type'     => (string) ($masterPayload['question_type'] ?? 'qcm'),
        ];

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)->post($endpoint, [
                'master'           => $master,
                'source_language'  => 'fr',
                'target_languages' => array_values($targetLanguages),
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'transport (translate): ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            $detail = $this->extractRouterError($response);
            return [
                'ok'    => false,
                'error' => 'router http error (translate)' . ($detail ? ' (' . $detail . ')' : ''),
            ];
        }

        $json = $response->json();
        if (!is_array($json) || ($json['ok'] ?? false) !== true || !is_array($json['translations'] ?? null)) {
            return ['ok' => false, 'error' => 'translate endpoint returned invalid envelope'];
        }

        return ['ok' => true, 'translations' => $json['translations']];
    }

    /**
     * Reshape the merged payload (fr master + N translations) into the
     * canonical addToBank() payload. Classification fields come from the
     * segment (not trusted from the LLM). Provider source is stamped for
     * bank-report visibility.
     *
     * Unchanged from pre-Option-B: shapeIntoPayload receives whatever is
     * in translations[] and cleans/normalises each entry.
     */
    private function shapeIntoPayload(array $routed, array $segment): ?array
    {
        $translations = $routed['translations'] ?? null;
        if (!is_array($translations) || empty($translations)) {
            return null;
        }

        $cleanTranslations = [];
        foreach ($translations as $lang => $tr) {
            if (!is_array($tr)) {
                continue;
            }
            $cleanTranslations[$lang] = [
                'question_text'     => (string) ($tr['question_text']      ?? ''),
                'answer_a'          => (string) ($tr['answer_a']           ?? ''),
                'answer_b'          => (string) ($tr['answer_b']           ?? ''),
                'answer_c'          => (string) ($tr['answer_c']           ?? ''),
                'answer_d'          => (string) ($tr['answer_d']           ?? ''),
                'correct_answer_key'=> strtoupper((string) ($tr['correct_answer_key'] ?? 'A')),
                'explanation'       => (string) ($tr['explanation']        ?? ''),
                'saviez_vous'       => (string) ($tr['saviez_vous']        ?? ''),
            ];
        }
        if (empty($cleanTranslations)) {
            return null;
        }

        // The router stamps `source` with the provider that answered STEP 1
        // (gemini|openai|…). STEP 2 may use a different provider but we track
        // the master generator as the canonical source for bank reports.
        $source = (string) ($routed['source'] ?? 'router');

        $payload = [
            'difficulty_depth' => (int) $segment['depth_range'][1],
            'domain'           => (string) $segment['domain'],
            'sub_domain'       => (string) $segment['sub_domain'],
            'question_type'    => (string) ($segment['question_type'] ?? 'qcm'),
            'cognitive_type'   => (string) $segment['cognitive_type'],
            'concept_id'       => (string) ($routed['concept_id']    ?? Str::random(20)),
            'concept_family'   => (string) ($routed['concept_family'] ?? $segment['sub_domain']),
            'source'           => $source,
            'validated'        => $this->hasAllPreferred($cleanTranslations),
            'translations'     => $cleanTranslations,
        ];

        // XOR enforced by DB CHECK: exactly one of difficulty_level / boss_level.
        $target = $segment['mode_target'];
        if ($target['type'] === 'boss') {
            $payload['boss_level'] = (int) $target['level'];
        } else {
            $payload['difficulty_level'] = (int) $target['levels'][0];
        }

        return $payload;
    }

    /**
     * Returns true only when every language in preferred_languages has a
     * non-empty question_text. validated=false groups are excluded from
     * multilingual / world-mode question selection.
     */
    private function hasAllPreferred(array $translations): bool
    {
        $preferred = config('question_bank_profiles.worker.preferred_languages', []);
        foreach ($preferred as $lang) {
            if (empty($translations[$lang]['question_text'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Extract the structured error detail from a failed Node router response.
     * The router returns JSON with `error` and optional `detail` fields on
     * 502/503 responses.
     */
    private function extractRouterError(\Illuminate\Http\Client\Response $response): ?string
    {
        try {
            $json = $response->json();
            if (is_array($json)) {
                $detail = $json['error'] ?? null;
                if (!empty($json['detail'])) {
                    $detail = $detail ? $detail . ': ' . $json['detail'] : $json['detail'];
                }
                return $detail;
            }
        } catch (\Throwable $_) {
            // Body non-JSON — ignoré.
        }
        return null;
    }
}
