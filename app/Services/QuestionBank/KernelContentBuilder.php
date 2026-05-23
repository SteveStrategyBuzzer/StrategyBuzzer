<?php

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KernelContentBuilder
 *
 * PHASE 1 — Étape 3 : fills English content into the 5 variants of frame_en.
 *
 * Calls POST /generate-kernel-variants on the Node Question API.
 * Merges the response into the frame array.
 * Never writes to the database.
 *
 * For qcm_deceptive_trap the Node endpoint returns a filled cognitive_contract
 * (trap_type, intuitive_wrong_answer, intuitive_answer_presence,
 *  recadrage_expected, fairness_reason, alignment_with_kernel_core).
 * These fields are merged into the existing skeleton cognitive_contract
 * (which already has the Phase 1 structure keys with null values).
 *
 * Returns: ['ok' => bool, 'frame' => array|null, 'error' => string|null]
 */
class KernelContentBuilder
{
    private const REQUEST_TIMEOUT = 120;

    private const VARIANT_KEYS = [
        'qcm_recognition',
        'qcm_reasoning',
        'qcm_deceptive_trap',
        'true_false_recognition',
        'true_false_reasoning',
    ];

    private const EN_CONTENT_FIELDS = [
        'question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
        'correct_answer_key', 'explanation', 'saviez_vous',
    ];

    private const DECEPTIVE_CONTRACT_FILL_KEYS = [
        'trap_type', 'intuitive_wrong_answer', 'intuitive_answer_presence',
        'recadrage_expected', 'fairness_reason', 'alignment_with_kernel_core',
    ];

    /**
     * Generate English content for all 5 variants.
     *
     * @param  array  $frame  The validated Phase 1 frame_en skeleton.
     * @return array{ok:bool, frame?:array, error?:string, source?:string, latency_ms?:int}
     */
    public function buildEnglishContent(array $frame): array
    {
        $kernelCore = $frame['kernel_core'] ?? [];

        if (empty($kernelCore)) {
            return ['ok' => false, 'error' => 'frame missing kernel_core'];
        }

        // ── Call Node API ─────────────────────────────────────────────────────
        $endpoint = rtrim(env('QUESTION_API_URL', 'http://localhost:3000'), '/')
            . '/generate-kernel-variants';

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)->post($endpoint, [
                'kernel_core' => $kernelCore,
            ]);
        } catch (\Throwable $e) {
            Log::error('[KernelContentBuilder] transport error', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'transport: ' . $e->getMessage()];
        }

        if (! $response->successful()) {
            $body  = $response->json();
            $detail = is_array($body) ? ($body['error'] ?? $body['detail'] ?? 'http error') : 'http error';
            Log::error('[KernelContentBuilder] API error', [
                'status' => $response->status(),
                'detail' => $detail,
            ]);
            return ['ok' => false, 'error' => "API {$response->status()}: {$detail}"];
        }

        $json = $response->json();

        if (! is_array($json) || ($json['ok'] ?? false) !== true || ! is_array($json['variants'] ?? null)) {
            return ['ok' => false, 'error' => 'invalid response envelope from Node API'];
        }

        $variants = $json['variants'];

        // ── Merge into frame ──────────────────────────────────────────────────
        $updatedFrame = $frame;

        foreach (self::VARIANT_KEYS as $variantKey) {
            if (! isset($variants[$variantKey]) || ! is_array($variants[$variantKey])) {
                return ['ok' => false, 'error' => "API response missing variant: {$variantKey}"];
            }

            $source = $variants[$variantKey];

            // Fill EN content fields
            foreach (self::EN_CONTENT_FIELDS as $field) {
                $updatedFrame['variants'][$variantKey][$field] = $source[$field] ?? null;
            }

            // For deceptive_trap: merge cognitive_contract fill fields
            if ($variantKey === 'qcm_deceptive_trap' && isset($source['cognitive_contract'])) {
                $cc = $source['cognitive_contract'];
                foreach (self::DECEPTIVE_CONTRACT_FILL_KEYS as $key) {
                    if (isset($cc[$key])) {
                        $updatedFrame['variants'][$variantKey]['cognitive_contract'][$key] = $cc[$key];
                    }
                }
            }
        }

        return [
            'ok'         => true,
            'frame'      => $updatedFrame,
            'source'     => $json['source'] ?? 'unknown',
            'latency_ms' => $json['latency_ms'] ?? 0,
        ];
    }
}
