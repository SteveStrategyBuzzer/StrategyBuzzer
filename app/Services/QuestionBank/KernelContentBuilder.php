<?php

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KernelContentBuilder
 *
 * PHASE 1 — Étape 3 : fills English content into the 5 variants of frame_en.
 *
 * Implements the master-first flow (3 steps):
 *
 *   3-A. Generate master question  → POST /generate-kernel-master
 *        qcm_recognition only — direct factual recall, anchored to kernel_core.
 *
 *   3-B. Validate master           → PHP-side lightweight checks
 *        Coherence with kernel_core.answer_target, readability, distractors.
 *
 *   3-C. Generate derived variants → POST /generate-kernel-derived-variants
 *        qcm_reasoning / qcm_deceptive_trap / true_false_recognition /
 *        true_false_reasoning — all derived FROM the master, never free-form.
 *
 * The master question is the coherence anchor of the kernel.
 * Derived variants share the same correct answer as the master.
 *
 * Never writes to the database.
 */
class KernelContentBuilder
{
    private const REQUEST_TIMEOUT = 120;

    private const EN_CONTENT_FIELDS = [
        'question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
        'correct_answer_key', 'explanation', 'saviez_vous',
    ];

    private const DERIVED_VARIANT_KEYS = [
        'qcm_reasoning',
        'qcm_deceptive_trap',
        'true_false_recognition',
        'true_false_reasoning',
    ];

    private const DECEPTIVE_CONTRACT_FILL_KEYS = [
        'trap_type', 'intuitive_wrong_answer', 'intuitive_answer_presence',
        'recadrage_expected', 'fairness_reason', 'alignment_with_kernel_core',
    ];

    // =========================================================================
    // Public entry point
    // =========================================================================

    /**
     * Run the full 3-step master-first flow.
     *
     * @param  array  $frame  The validated Phase 1 frame_en skeleton.
     * @return array{ok:bool, frame?:array, error?:string, step?:string,
     *              master?:array, sources?:array, latency_total_ms?:int}
     */
    public function buildEnglishContent(array $frame): array
    {
        $kernelCore = $frame['kernel_core'] ?? [];

        if (empty($kernelCore)) {
            return ['ok' => false, 'error' => 'frame missing kernel_core', 'step' => '3-A'];
        }

        $startMs = (int) round(microtime(true) * 1000);
        $sources  = [];

        // ── 3-A : generate master question (qcm_recognition) ─────────────────
        $masterResult = $this->generateMaster($kernelCore);
        if (! $masterResult['ok']) {
            return ['ok' => false, 'error' => $masterResult['error'], 'step' => '3-A'];
        }
        $master = $masterResult['master'];
        $sources['master'] = $masterResult['source'] ?? 'unknown';

        // ── 3-B : validate master ──────────────────────────────────────────────
        $validationResult = $this->validateMaster($master, $kernelCore);
        if (! $validationResult['ok']) {
            return ['ok' => false, 'error' => $validationResult['error'], 'step' => '3-B'];
        }

        // ── 3-C : generate derived variants from master ────────────────────────
        $derivedResult = $this->generateDerivedVariants($kernelCore, $master);
        if (! $derivedResult['ok']) {
            return ['ok' => false, 'error' => $derivedResult['error'], 'step' => '3-C'];
        }
        $derived = $derivedResult['variants'];
        $sources['derived'] = $derivedResult['source'] ?? 'unknown';

        // ── Merge all into frame ───────────────────────────────────────────────
        $updatedFrame = $frame;

        // Master → qcm_recognition
        foreach (self::EN_CONTENT_FIELDS as $field) {
            $updatedFrame['variants']['qcm_recognition'][$field] = $master[$field] ?? null;
        }

        // Derived variants → 4 remaining keys
        foreach (self::DERIVED_VARIANT_KEYS as $variantKey) {
            if (! isset($derived[$variantKey]) || ! is_array($derived[$variantKey])) {
                return ['ok' => false, 'error' => "derived variants missing key: {$variantKey}", 'step' => '3-C'];
            }
            $src = $derived[$variantKey];

            foreach (self::EN_CONTENT_FIELDS as $field) {
                $updatedFrame['variants'][$variantKey][$field] = $src[$field] ?? null;
            }

            // deceptive_trap: merge cognitive_contract fill fields
            if ($variantKey === 'qcm_deceptive_trap' && isset($src['cognitive_contract'])) {
                $cc = $src['cognitive_contract'];
                foreach (self::DECEPTIVE_CONTRACT_FILL_KEYS as $key) {
                    if (isset($cc[$key])) {
                        $updatedFrame['variants'][$variantKey]['cognitive_contract'][$key] = $cc[$key];
                    }
                }
            }
        }

        $endMs        = (int) round(microtime(true) * 1000);
        $latencyTotal = $endMs - $startMs;

        return [
            'ok'               => true,
            'frame'            => $updatedFrame,
            'master'           => $master,
            'sources'          => $sources,
            'latency_total_ms' => $latencyTotal,
        ];
    }

    // =========================================================================
    // 3-A  Generate master question
    // =========================================================================

    /**
     * @return array{ok:bool, master?:array, source?:string, error?:string}
     */
    private function generateMaster(array $kernelCore): array
    {
        $endpoint = $this->apiUrl('/generate-kernel-master');

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)->post($endpoint, [
                'kernel_core' => $kernelCore,
            ]);
        } catch (\Throwable $e) {
            Log::error('[KernelContentBuilder] 3-A transport error', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => '3-A transport: ' . $e->getMessage()];
        }

        if (! $response->successful()) {
            $detail = $this->extractDetail($response);
            Log::error('[KernelContentBuilder] 3-A API error', ['status' => $response->status(), 'detail' => $detail]);
            return ['ok' => false, 'error' => "3-A API {$response->status()}: {$detail}"];
        }

        $json = $response->json();
        if (! is_array($json) || ($json['ok'] ?? false) !== true || ! is_array($json['master'] ?? null)) {
            return ['ok' => false, 'error' => '3-A: invalid response envelope from Node API'];
        }

        return ['ok' => true, 'master' => $json['master'], 'source' => $json['source'] ?? 'unknown'];
    }

    // =========================================================================
    // 3-B  Validate master (PHP-side, lightweight)
    // =========================================================================

    /**
     * @return array{ok:bool, error?:string, warnings?:string[]}
     */
    private function validateMaster(array $master, array $kernelCore): array
    {
        $warnings = [];

        // Required fields present
        foreach (['question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d', 'correct_answer_key', 'explanation', 'saviez_vous'] as $f) {
            if (empty($master[$f]) && $master[$f] !== '0') {
                return ['ok' => false, 'error' => "3-B: master missing field '{$f}'"];
            }
        }

        // correct_answer_key must be A/B/C/D
        if (! in_array(strtoupper($master['correct_answer_key']), ['A', 'B', 'C', 'D'], true)) {
            return ['ok' => false, 'error' => "3-B: master.correct_answer_key invalid: {$master['correct_answer_key']}"];
        }

        // question_text length
        $qLen = mb_strlen($master['question_text']);
        if ($qLen > 115) {
            return ['ok' => false, 'error' => "3-B: master.question_text too long ({$qLen} > 115)"];
        }
        if ($qLen < 10) {
            return ['ok' => false, 'error' => "3-B: master.question_text too short ({$qLen})"];
        }

        // saviez_vous length
        $svLen = mb_strlen($master['saviez_vous']);
        if ($svLen < 20) {
            return ['ok' => false, 'error' => "3-B: master.saviez_vous too short ({$svLen})"];
        }
        if ($svLen > 225) {
            $warnings[] = "master.saviez_vous may be too long ({$svLen})";
        }

        // All 4 answer options must be distinct
        $answers = array_map('strtolower', array_map('trim', [
            $master['answer_a'], $master['answer_b'], $master['answer_c'], $master['answer_d'],
        ]));
        if (count(array_unique($answers)) < 4) {
            return ['ok' => false, 'error' => '3-B: master has duplicate answer options'];
        }

        // Correct answer option must not be empty
        $ckField = 'answer_' . strtolower($master['correct_answer_key']);
        if (empty($master[$ckField])) {
            return ['ok' => false, 'error' => "3-B: master.{$ckField} (correct answer) is empty"];
        }

        // Keyword coherence: answer_target words in correct answer or question
        $target   = strtolower($kernelCore['answer_target'] ?? '');
        $haystack = strtolower($master['question_text'] . ' ' . ($master[$ckField] ?? '') . ' ' . ($master['explanation'] ?? ''));
        if ($target && mb_strpos($haystack, substr($target, 0, 4)) === false) {
            $warnings[] = "answer_target '{$target}' not found in question/answer/explanation — verify coherence";
        }

        return ['ok' => true, 'warnings' => $warnings];
    }

    // =========================================================================
    // 3-C  Generate derived variants
    // =========================================================================

    /**
     * @return array{ok:bool, variants?:array, source?:string, error?:string}
     */
    private function generateDerivedVariants(array $kernelCore, array $master): array
    {
        $endpoint = $this->apiUrl('/generate-kernel-derived-variants');

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)->post($endpoint, [
                'kernel_core' => $kernelCore,
                'master'      => $master,
            ]);
        } catch (\Throwable $e) {
            Log::error('[KernelContentBuilder] 3-C transport error', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => '3-C transport: ' . $e->getMessage()];
        }

        if (! $response->successful()) {
            $detail = $this->extractDetail($response);
            Log::error('[KernelContentBuilder] 3-C API error', ['status' => $response->status(), 'detail' => $detail]);
            return ['ok' => false, 'error' => "3-C API {$response->status()}: {$detail}"];
        }

        $json = $response->json();
        if (! is_array($json) || ($json['ok'] ?? false) !== true || ! is_array($json['variants'] ?? null)) {
            return ['ok' => false, 'error' => '3-C: invalid response envelope from Node API'];
        }

        return ['ok' => true, 'variants' => $json['variants'], 'source' => $json['source'] ?? 'unknown'];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function apiUrl(string $path): string
    {
        return rtrim(env('QUESTION_API_URL', 'http://localhost:3000'), '/') . $path;
    }

    private function extractDetail($response): string
    {
        $body = $response->json();
        return is_array($body) ? ($body['error'] ?? $body['detail'] ?? 'http error') : 'http error';
    }
}
