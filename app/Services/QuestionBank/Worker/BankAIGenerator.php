<?php

namespace App\Services\QuestionBank\Worker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bank-side LLM caller. Talks to the Node Question API's bank-refill
 * endpoint (`POST /generate-bank-question`), which is itself wired
 * through the multi-provider AI router (multi-key, quarantine,
 * failover) shipped in #83.
 *
 * Before #87 this class hit a single provider directly, so an outage on
 * that provider would freeze the bank refill pipeline entirely. Now the
 * worker sends the segment to the router endpoint, which:
 *   - rotates keys per provider, fails over across providers on outage,
 *   - validates the rich JSON contract INSIDE its retry loop,
 *   - returns the parsed payload enriched with `source` (provider name),
 *     `provider_key_index` and `latency_ms`.
 *
 * AI credentials live exclusively on the Node service. The worker
 * remains the only PHP caller of the router endpoint (architectural
 * constraint locked in by #88).
 *
 * Per-language loop (#patch-worker): instead of one call with all 10
 * languages at once (which causes intermittent Gemini JSON truncation at
 * ~5 KB response size), we now generate the master language (fr) first,
 * then call once per remaining language. Each payload is ~1.2 KB —
 * well within Gemini's reliable output window. A per-language failure
 * does not cancel the whole group: it is logged and the language is
 * omitted from the insertion, leaving `validated=false` so the picker
 * skips it for that language until a future worker cycle fills it in.
 */
class BankAIGenerator
{
    private const REQUEST_TIMEOUT_SECONDS = 90;

    /**
     * Generate one question group for the given segment, gathering
     * translations language by language.
     *
     * @param  array  $segment  one row from BankNeedsCalculator output
     * @return array{ok:bool, payload?:array, error?:string, http_status?:int}
     */
    public function generateForSegment(array $segment): array
    {
        $languages  = config('question_bank_profiles.worker.preferred_languages', ['fr']);
        $masterLang = 'fr';
        $otherLangs = array_values(array_filter($languages, fn (string $l) => $l !== $masterLang));

        // ── 1. Master language (fr) ───────────────────────────────────────────
        // The master call provides concept_id, concept_family and all group
        // metadata — subsequent per-language calls only contribute translations.
        $masterCall = $this->callApiForLanguages($segment, [$masterLang]);
        if (!$masterCall['ok']) {
            return $masterCall;
        }

        $masterPayload = $this->shapeIntoPayload($masterCall['routed'], $segment);
        if ($masterPayload === null) {
            return ['ok' => false, 'error' => 'shape mismatch on master language'];
        }

        $mergedTranslations = $masterPayload['translations']; // ['fr' => [...]]
        $langErrors = [];

        // ── 2. Remaining languages — one API call each ────────────────────────
        foreach ($otherLangs as $lang) {
            $call = $this->callApiForLanguages($segment, [$lang]);
            if (!$call['ok']) {
                $langErrors[$lang] = $call['error'] ?? 'unknown';
                Log::warning('[BankAIGenerator] per-lang call failed — skipping', [
                    'lang'  => $lang,
                    'error' => $call['error'] ?? null,
                ]);
                continue;
            }

            $tr = $call['routed']['translations'][$lang] ?? null;
            if (!is_array($tr)) {
                $langErrors[$lang] = 'no translation in response';
                Log::warning('[BankAIGenerator] per-lang response missing translation field', [
                    'lang' => $lang,
                ]);
                continue;
            }

            $mergedTranslations[$lang] = [
                'question_text'     => (string) ($tr['question_text']     ?? ''),
                'answer_a'          => (string) ($tr['answer_a']          ?? ''),
                'answer_b'          => (string) ($tr['answer_b']          ?? ''),
                'answer_c'          => (string) ($tr['answer_c']          ?? ''),
                'answer_d'          => (string) ($tr['answer_d']          ?? ''),
                'correct_answer_key' => strtoupper((string) ($tr['correct_answer_key'] ?? 'A')),
                'explanation'       => (string) ($tr['explanation']       ?? ''),
                'saviez_vous'       => (string) ($tr['saviez_vous']       ?? ''),
            ];
        }

        if (!empty($langErrors)) {
            Log::info('[BankAIGenerator] group inserted with partial translations', [
                'missing_langs' => array_keys($langErrors),
                'present_langs' => array_keys($mergedTranslations),
            ]);
        }

        // ── 3. Merge translations back into the master payload ────────────────
        $masterPayload['translations'] = $mergedTranslations;
        $masterPayload['validated']    = $this->hasAllPreferred($mergedTranslations);

        return ['ok' => true, 'payload' => $masterPayload];
    }

    /**
     * Single HTTP call to the Node AI router for a given set of languages.
     * Extracted from the old generateForSegment() so the orchestrator above
     * can call it once per language without duplicating HTTP/error-handling.
     *
     * @param  array    $segment    one row from BankNeedsCalculator output
     * @param  array    $languages  e.g. ['fr'] or ['en']
     * @return array{ok:bool, routed?:array, error?:string, http_status?:int}
     */
    private function callApiForLanguages(array $segment, array $languages): array
    {
        $endpoint = rtrim(env('QUESTION_API_URL', 'http://localhost:3000'), '/').'/generate-bank-question';

        $body = [
            'domain'           => (string) $segment['domain'],
            'sub_domain'       => (string) $segment['sub_domain'],
            'cognitive_type'   => (string) $segment['cognitive_type'],
            'question_type'    => (string) ($segment['question_type'] ?? 'qcm'),
            'difficulty_depth' => (int) $segment['depth_range'][1],
            'languages'        => array_values($languages),
        ];

        // Segment-context XOR (#91 contract): the body MUST carry exactly one
        // of difficulty_level (Solo) or boss_level (Boss). The router uses
        // it to anchor the prompt's narrative target; the worker also
        // re-stamps the same field on the output payload (DB CHECK enforces
        // the XOR at the storage layer too).
        $target = $segment['mode_target'];
        if (($target['type'] ?? null) === 'boss') {
            $body['boss_level'] = (int) $target['level'];
        } else {
            // For solo_range we pin to the LOW end of the band so the
            // generated row is reusable across the entire band — same rule
            // shapeIntoPayload() applies to the output payload.
            $body['difficulty_level'] = (int) $target['levels'][0];
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)->post($endpoint, $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'transport: '.$e->getMessage()];
        }

        if (!$response->successful()) {
            // The router returns structured 503/502 payloads (no_providers_configured,
            // all_providers_exhausted, router_error); surface the detail so the
            // worker's existing exponential backoff kicks in just like before.
            $detail = null;
            try {
                $json = $response->json();
                if (is_array($json)) {
                    $detail = $json['error'] ?? null;
                    if (!empty($json['detail'])) {
                        $detail = $detail ? $detail.': '.$json['detail'] : $json['detail'];
                    }
                }
            } catch (\Throwable $_) {
                // Body wasn't JSON; ignore and fall back to status only.
            }

            return [
                'ok'          => false,
                'error'       => 'router http error'.($detail ? ' ('.$detail.')' : ''),
                'http_status' => $response->status(),
            ];
        }

        $parsed = $response->json();
        if (!is_array($parsed) || ($parsed['ok'] ?? false) !== true || !is_array($parsed['payload'] ?? null)) {
            return ['ok' => false, 'error' => 'router returned invalid envelope'];
        }

        return ['ok' => true, 'routed' => $parsed['payload']];
    }

    /**
     * Reshape the router's validated payload into the canonical addToBank()
     * payload, carrying every fixed field from the segment so the writer
     * doesn't trust the LLM on classification, and stamping the worker's
     * own `source` (the provider that produced the question, as reported
     * by the router — surfaces real failover events in the bank report).
     *
     * Called on the MASTER (fr) response only; subsequent per-language
     * payloads are merged directly into $mergedTranslations in the
     * orchestrator above without going through this method.
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
                'question_text'     => (string) ($tr['question_text']     ?? ''),
                'answer_a'          => (string) ($tr['answer_a']          ?? ''),
                'answer_b'          => (string) ($tr['answer_b']          ?? ''),
                'answer_c'          => (string) ($tr['answer_c']          ?? ''),
                'answer_d'          => (string) ($tr['answer_d']          ?? ''),
                'correct_answer_key' => strtoupper((string) ($tr['correct_answer_key'] ?? 'A')),
                'explanation'       => (string) ($tr['explanation']       ?? ''),
                'saviez_vous'       => (string) ($tr['saviez_vous']       ?? ''),
            ];
        }
        if (empty($cleanTranslations)) {
            return null;
        }

        // The router stamps `source` with the provider that actually answered
        // (gemini|openai|...). Persist it so `php artisan questions:bank:report`
        // can show real failover events. Fall back to a safe label if missing.
        $source = (string) ($routed['source'] ?? 'router');

        $payload = [
            'difficulty_depth' => (int) $segment['depth_range'][1],
            'domain'           => (string) $segment['domain'],
            'sub_domain'       => (string) $segment['sub_domain'],
            'question_type'    => (string) ($segment['question_type'] ?? 'qcm'),
            'cognitive_type'   => (string) $segment['cognitive_type'],
            'concept_id'       => (string) ($routed['concept_id']     ?? Str::random(20)),
            'concept_family'   => (string) ($routed['concept_family'] ?? $segment['sub_domain']),
            'source'           => $source,
            'validated'        => $this->hasAllPreferred($cleanTranslations),
            'translations'     => $cleanTranslations,
        ];

        // Set the level/boss exclusively (XOR enforced by DB CHECK).
        $target = $segment['mode_target'];
        if ($target['type'] === 'boss') {
            $payload['boss_level'] = (int) $target['level'];
        } else {
            // For solo_range we pin to the LOW end of the band so the row is
            // re-usable across the entire band; the planner only filters by
            // depth_range and band membership.
            $payload['difficulty_level'] = (int) $target['levels'][0];
        }

        return $payload;
    }

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
}
