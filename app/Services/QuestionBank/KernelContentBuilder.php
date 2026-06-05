<?php

namespace App\Services\QuestionBank;

use App\Services\QuestionBank\KernelTextHelpers;
use App\Services\QuestionBank\ReadingBandConfig;
use App\Services\QuestionBank\VariantAlignmentChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KernelContentBuilder
 *
 * PHASE 1 — Étape 3 : fills English content into the 7 variants of frame_en.
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
 *        qcm_reasoning / qcm_deceptive_trap /
 *        tf_recognition_true / tf_recognition_false /
 *        tf_reasoning_true   / tf_reasoning_false
 *        — all derived FROM the master, anchored to the same sujet touché.
 *
 * The master question is the coherence anchor of the kernel.
 * All variants share the same SUJET TOUCHÉ — correct answer may differ for
 * reasoning variants if the cognitive angle is causal/contextual.
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
        'tf_recognition_true',
        'tf_recognition_false',
        'tf_reasoning_true',
        'tf_reasoning_false',
    ];

    private const DECEPTIVE_CONTRACT_FILL_KEYS = [
        'trap_carriers',                          // Fix 2: array — which mechanics carry the trap
        'natural_hypothesis_triggered',           // Fix 3: bool — step 1
        'hypothesis_overturned_after_full_read',  // Fix 3: bool — step 2
        'implicit_hypothesis', 'hypothesis_invalidated_by', 'reconstruction_required',
        'intuitive_wrong_answer', 'intuitive_answer_presence',
        'fairness_reason', 'alignment_with_kernel_core',
    ];

    // Mirrors KernelLoopAlerter::MAX_FILL_ATTEMPTS — kept in sync manually.
    public const MAX_FILL_ATTEMPTS = 3;

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
    public function buildEnglishContent(array $frame, ?array $retryGuidance = null): array
    {
        $kernelCore = $frame['kernel_core'] ?? [];

        if (empty($kernelCore)) {
            return ['ok' => false, 'error' => 'frame missing kernel_core', 'step' => '3-A'];
        }

        $startMs = (int) round(microtime(true) * 1000);
        $sources  = [];

        // ── 3-A : generate master question (qcm_recognition) ─────────────────
        $aStart       = (int) round(microtime(true) * 1000);
        $masterResult = $this->generateMaster($kernelCore, $retryGuidance);
        if (! $masterResult['ok']) {
            return ['ok' => false, 'error' => $masterResult['error'], 'step' => '3-A'];
        }
        $latencyMasterMs   = (int) round(microtime(true) * 1000) - $aStart;
        $master            = $masterResult['master'];
        $sources['master'] = $masterResult['source'] ?? 'unknown';

        // ── 3-B : validate master ──────────────────────────────────────────────
        $bStart           = (int) round(microtime(true) * 1000);
        $validationResult = $this->validateMaster($master, $kernelCore);
        $latencyValidationMs = (int) round(microtime(true) * 1000) - $bStart;
        if (! $validationResult['ok']) {
            return ['ok' => false, 'error' => $validationResult['error'], 'step' => '3-B'];
        }

        // ── 3-C : generate derived variants from master ────────────────────────
        $cStart        = (int) round(microtime(true) * 1000);
        $derivedResult = $this->generateDerivedVariants($kernelCore, $master, $retryGuidance);
        if (! $derivedResult['ok']) {
            return ['ok' => false, 'error' => $derivedResult['error'], 'step' => '3-C'];
        }
        $latencyDerivedMs   = (int) round(microtime(true) * 1000) - $cStart;
        $derived            = $derivedResult['variants'];
        $sources['derived'] = $derivedResult['source'] ?? 'unknown';

        // ── Merge all into frame ───────────────────────────────────────────────
        $updatedFrame = $frame;

        // Master → qcm_recognition
        foreach (self::EN_CONTENT_FIELDS as $field) {
            $updatedFrame['variants']['qcm_recognition'][$field] = $master[$field] ?? null;
        }

        // ── Extract EN anchor terms from master for Phase 2 scoring ───────────
        // Significant English tokens from question + correct answer + explanation + saviez_vous.
        // Stored in kernel_core.en_anchor_terms so VariantAlignmentChecker can score
        // all 7 variants in English without needing French-to-English token matching.
        $masterCkField = 'answer_' . strtolower((string) ($master['correct_answer_key'] ?? 'a'));
        $masterHaystack = implode(' ', array_filter([
            $master['question_text'] ?? '',
            $master[$masterCkField]  ?? '',
            $master['explanation']   ?? '',
            $master['saviez_vous']   ?? '',
        ]));
        $masterTokens = KernelTextHelpers::significantTokens($masterHaystack);
        $updatedFrame['kernel_core']['en_anchor_terms'] = array_slice(
            array_values(array_unique($masterTokens)),
            0, 12
        );

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

        // ── Phase 2 : subject-touch alignment check (non-blocking) ───────────
        $phase2Alignment = null;
        try {
            $phase2Alignment = (new VariantAlignmentChecker())->check($updatedFrame);
        } catch (\Throwable $e) {
            Log::warning('[KernelContentBuilder] Phase 2 alignment check failed (non-blocking)', [
                'error' => $e->getMessage(),
            ]);
        }

        // Persist Phase 2 result inside frame_en so the DB write below
        // captures it and future commands (fill-content --force, validate-content)
        // can read the last known alignment scores without re-running Phase 2.
        $updatedFrame['phase2_result'] = $phase2Alignment;

        $endMs        = (int) round(microtime(true) * 1000);
        $latencyTotal = $endMs - $startMs;

        return [
            'ok'                       => true,
            'frame'                    => $updatedFrame,
            'master'                   => $master,
            'sources'                  => $sources,
            'latency_total_ms'         => $latencyTotal,
            'latency_master_ms'        => $latencyMasterMs,
            'latency_validation_ms'    => $latencyValidationMs,
            'latency_derived_ms'       => $latencyDerivedMs,
            'phase2_alignment'         => $phase2Alignment,
        ];
    }

    // =========================================================================
    // 3-A  Generate master question
    // =========================================================================

    /**
     * @return array{ok:bool, master?:array, source?:string, error?:string}
     */
    private function generateMaster(array $kernelCore, ?array $retryGuidance = null): array
    {
        $endpoint = $this->apiUrl('/generate-kernel-master');

        $body = ['kernel_core' => $kernelCore];
        if ($retryGuidance !== null) {
            $body['retry_guidance'] = $retryGuidance;
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)->post($endpoint, $body);
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

        // question_text length — band-aware tri-state (OK / WARNING / REVIEW_NEEDED)
        $qLen  = mb_strlen($master['question_text']);
        $band  = $kernelCore['default_reading_band'] ?? ReadingBandConfig::DEFAULT_BAND;

        if ($qLen < 10) {
            return ['ok' => false, 'error' => "3-B: master.question_text too short ({$qLen})"];
        }
        $readingAssess = ReadingBandConfig::assess($qLen, $band, 'en');
        if ($readingAssess['status'] === 'REVIEW_NEEDED') {
            return ['ok' => false, 'error' => "3-B: master.question_text REVIEW_NEEDED ({$readingAssess['detail']})"];
        }
        // WARNING is logged but not rejected

        // saviez_vous length
        $svLen = mb_strlen($master['saviez_vous']);
        if ($svLen < 20) {
            return ['ok' => false, 'error' => "3-B: master.saviez_vous too short ({$svLen})"];
        }
        if ($svLen > 225) {
            $warnings[] = "master.saviez_vous may be too long ({$svLen})";
        }

        // saviez_vous cognitive check — master is always qcm_recognition.
        // The SV must explain the main fact, not a generic description.
        // Non-blocking: logged as a warning, does not reject the master.
        $svCheck = mb_strtolower(trim($master['saviez_vous']));
        $genericFillersSV = [
            'is known for its', 'is famous for', 'is often associated',
            'plays an important role', 'is widely recognized', 'is widely known',
            'has been growing', 'is considered one of',
            'est connu pour', 'est célèbre pour', 'est souvent associé',
            'joue un rôle important', 'est largement reconnu',
        ];
        foreach ($genericFillersSV as $filler) {
            if (str_contains($svCheck, $filler)) {
                $warnings[] = "3-B: master.saviez_vous (recognition) contains generic filler \"{$filler}\" — must explain the main fact with specific context";
                break;
            }
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
    private function generateDerivedVariants(array $kernelCore, array $master, ?array $retryGuidance = null): array
    {
        $endpoint = $this->apiUrl('/generate-kernel-derived-variants');

        $body = ['kernel_core' => $kernelCore, 'master' => $master];
        if ($retryGuidance !== null) {
            $body['retry_guidance'] = $retryGuidance;
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)->post($endpoint, $body);
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

    // =========================================================================
    // Feed-forward : Phase 2 → Phase 1 retry guidance
    // =========================================================================

    /**
     * Build a compact diagnostic retry guidance array from the previous
     * phase2_result. Returns null when no guidance is needed (first run,
     * or previous policy was A — all variants aligned).
     *
     * Output contains ONLY structural direction — no question_text,
     * no answer content, no creative reformulations.
     *
     * @param  array|null  $phase2Result  frame_en['phase2_result'] from previous run.
     * @return array|null
     */
    public function buildRetryGuidance(?array $phase2Result): ?array
    {
        if ($phase2Result === null) {
            return null;
        }

        $policy = $phase2Result['policy'] ?? 'A';
        if ($policy === 'A') {
            return null;
        }

        $issues = $phase2Result['structured_issues'] ?? [];
        if (empty($issues)) {
            return null;
        }

        $failedVariants = [];
        $avoidSet       = [];
        $retryGoals     = [];

        foreach ($issues as $si) {
            $variantKey = $si['variant_key'] ?? null;
            $driftType  = $si['drift_type']  ?? null;
            $action     = $si['action_required'] ?? 'none';

            if ($variantKey && $driftType && $action !== 'none') {
                $failedVariants[$variantKey] = $driftType;

                foreach ($this->avoidItemsForDrift($driftType) as $item) {
                    $avoidSet[$item] = true;
                }

                $goal = $this->retryGoalForDrift($driftType);
                if ($goal !== '' && !in_array($goal, $retryGoals, true)) {
                    $retryGoals[] = $goal;
                }
            }
        }

        if (empty($failedVariants)) {
            return null;
        }

        return [
            'policy'          => $policy,
            'failed_variants' => $failedVariants,
            'avoid'           => array_keys($avoidSet),
            'retry_goal'      => $retryGoals,
        ];
    }

    // =========================================================================
    // Targeted retry — Phase 1 corrects ONLY flagged variants
    // =========================================================================

    /**
     * Re-generate only the variants flagged by Phase 2 (no master re-generation).
     *
     * Called from FillContentCommand after the initial Phase 2 detected issues.
     * The master (qcm_recognition) is never regenerated in this path — it is
     * already validated and anchored.
     *
     * Flow:
     *   1. Extract derived keys to fix (filter out qcm_recognition)
     *   2. Call generateDerivedVariants() as usual — all 6 are regenerated by the AI
     *      with retry_guidance focused on the failing variants
     *   3. Merge ONLY the $failedVariantKeys back into the frame — good variants
     *      already in the frame are preserved unchanged
     *   4. Run Phase 2 again on the merged frame
     *
     * The selective merge (step 3) means previously-validated variants are never
     * accidentally degraded by this retry.
     *
     * @param  array      $frame             frame_en after initial generation (has all 7)
     * @param  string[]   $failedVariantKeys Variant keys that Phase 2 flagged (grade C/D)
     * @param  array|null $retryGuidance     Guidance built from Phase 2 structured_issues
     * @return array{ok:bool, frame?:array, fixed_variants?:string[],
     *               latency_retry_ms?:int, phase2_alignment?:array,
     *               skipped?:bool, error?:string, step?:string}
     */
    public function retryFlaggedVariants(array $frame, array $failedVariantKeys, ?array $retryGuidance = null): array
    {
        $kernelCore = $frame['kernel_core'] ?? [];
        $master     = $frame['variants']['qcm_recognition'] ?? [];

        // Master is never re-generated in targeted retry
        $keysToFix = array_values(array_filter(
            $failedVariantKeys,
            static fn(string $k) => $k !== 'qcm_recognition'
                                 && in_array($k, self::DERIVED_VARIANT_KEYS, true)
        ));

        if (empty($keysToFix)) {
            Log::info('[KernelContentBuilder] retryFlaggedVariants: no derived variants to fix — skipping');
            return ['ok' => true, 'frame' => $frame, 'skipped' => true];
        }

        Log::info('[KernelContentBuilder] retryFlaggedVariants: targeting ' . implode(', ', $keysToFix));

        $cStart        = (int) round(microtime(true) * 1000);
        $derivedResult = $this->generateDerivedVariants($kernelCore, $master, $retryGuidance);
        $latencyMs     = (int) round(microtime(true) * 1000) - $cStart;

        if (! $derivedResult['ok']) {
            return ['ok' => false, 'error' => $derivedResult['error'], 'step' => '3-C-retry'];
        }

        $freshDerived = $derivedResult['variants'];
        $updatedFrame = $frame;

        // Selective merge: only apply newly-generated content for the flagged keys
        $actuallyFixed = [];
        foreach ($keysToFix as $variantKey) {
            if (! isset($freshDerived[$variantKey]) || ! is_array($freshDerived[$variantKey])) {
                Log::warning('[KernelContentBuilder] retryFlaggedVariants: key absent from AI response — keeping original', [
                    'key' => $variantKey,
                ]);
                continue; // Keep existing — non-fatal
            }

            $src = $freshDerived[$variantKey];
            foreach (self::EN_CONTENT_FIELDS as $field) {
                $updatedFrame['variants'][$variantKey][$field] = $src[$field] ?? null;
            }

            if ($variantKey === 'qcm_deceptive_trap' && isset($src['cognitive_contract'])) {
                $cc = $src['cognitive_contract'];
                foreach (self::DECEPTIVE_CONTRACT_FILL_KEYS as $key) {
                    if (isset($cc[$key])) {
                        $updatedFrame['variants'][$variantKey]['cognitive_contract'][$key] = $cc[$key];
                    }
                }
            }

            $actuallyFixed[] = $variantKey;
        }

        // Phase 2 re-check on the merged frame
        $phase2Alignment = null;
        try {
            $phase2Alignment = (new VariantAlignmentChecker())->check($updatedFrame);
        } catch (\Throwable $e) {
            Log::warning('[KernelContentBuilder] Phase 2 re-check failed (non-blocking)', [
                'error' => $e->getMessage(),
            ]);
        }

        $updatedFrame['phase2_result'] = $phase2Alignment;

        return [
            'ok'               => true,
            'frame'            => $updatedFrame,
            'fixed_variants'   => $actuallyFixed,
            'latency_retry_ms' => $latencyMs,
            'phase2_alignment' => $phase2Alignment,
        ];
    }

    /**
     * Detect whether the same drift_type(s) appear in both the previous
     * and the current phase2_result structured_issues.
     * Returns true if repeated drift detected.
     */
    public function detectRepeatedDrift(?array $previousPhase2, array $currentPhase2): bool
    {
        if ($previousPhase2 === null) {
            return false;
        }
        $prevDrifts    = array_column($previousPhase2['structured_issues'] ?? [], 'drift_type');
        $currentDrifts = array_column($currentPhase2['structured_issues']  ?? [], 'drift_type');
        $shared = array_intersect(array_filter($prevDrifts), array_filter($currentDrifts));
        return !empty($shared);
    }

    /**
     * Map drift_type → list of avoid patterns.
     * Closed vocabulary — no creative content, no question text.
     */
    private function avoidItemsForDrift(string $driftType): array
    {
        return match ($driftType) {
            'subject_touch_low'      => ['broad topic drift', 'tangential subject reference'],
            'weak_reasoning'         => ['simple reformulation', 'weak inference chain'],
            'weak_deceptive_trap'    => ['generic distractors not tied to answer_target'],
            'false_not_plausible'    => ['implausible false statement', 'false claim unrelated to subject'],
            'subject_escape'         => ['question about a different subject than answer_target'],
            'kernel_collapse'        => [],
            'proximity_expected'     => [],   // tf_recognition_true: expected behavior, nothing to avoid
            default                  => ['broad topic drift'],
        };
    }

    /**
     * Map drift_type → structural retry goal (direction only, no content).
     */
    private function retryGoalForDrift(string $driftType): string
    {
        return match ($driftType) {
            'subject_touch_low'      => 'tighter subject_touch alignment with answer_target',
            'weak_reasoning'         => 'stronger causal or comparative reasoning chain',
            'weak_deceptive_trap'    => 'distractor strategy anchored to answer_target confusion',
            'false_not_plausible'    => 'plausible false claim directly about the subject',
            'subject_escape'         => 'keep question anchored to the exact answer_target',
            'kernel_collapse'        => 'regenerate variant from kernel_core',
            'proximity_expected'     => '',   // tf_recognition_true: no retry goal — expected behavior
            default                  => 'tighter subject_touch alignment',
        };
    }

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
