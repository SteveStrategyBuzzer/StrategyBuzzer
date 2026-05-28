<?php

namespace App\Services\QuestionBank;

use App\Mail\KernelLoopAlertMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * KernelQuarantineManager
 *
 * Handles the terminal quarantine state for a question kernel that could not
 * be auto-corrected after Phase 1 full generation + Phase 1 targeted retry.
 *
 * Output: a 7-file audit package written to storage/app/quarantine/kernel_{id}_{ts}/
 *
 *   01_original_generation.json  — 7 variants + Phase 2 initial scores + latency + provider
 *   02_phase2_analysis.json      — variant_scores + subscores + structured_issues + policy
 *   03_retry_guidance.json       — failed_variants + avoid + retry_goal + attempt metadata
 *   04_retry_generation.json     — variants BEFORE and AFTER retry + latency + provider
 *   05_phase2_retry_analysis.json — new scores + subscores + new policy
 *   06_diff_report.json          — per-variant before/after + score_delta + policy_improved
 *   07_final_quarantine.json     — final state + retry history + human workflow instructions
 *
 * Invariants:
 *   - Package directory is uniquely timestamped — never overwritten
 *   - Each file is written independently — a single file failure is non-fatal
 *   - Never propagates exceptions — quarantine must never block the pipeline
 *
 * Alert:
 *   Always logs Log::critical.
 *   Emails QB_KERNEL_ALERT_EMAIL when configured + MAIL_MAILER != log.
 */
final class KernelQuarantineManager
{
    private const QUARANTINE_BASE = 'quarantine';

    // Grade → numeric rank for policy_improved comparison (higher = better).
    private const GRADE_RANK = ['D' => 1, 'C' => 2, 'B' => 3, 'A' => 4];

    // Cognitive type label per variant key — shown explicitly in 08_human_review.md.
    private const VARIANT_COGNITIVE_TYPE = [
        'qcm_recognition'      => 'recognition — QCM (master)',
        'qcm_reasoning'        => 'causal reasoning — QCM',
        'qcm_deceptive_trap'   => 'deceptive trap — QCM',
        'tf_recognition_true'  => 'recognition — T/F true statement',
        'tf_recognition_false' => 'recognition — T/F false plausible',
        'tf_reasoning_true'    => 'reasoning — T/F true inference',
        'tf_reasoning_false'   => 'reasoning — T/F false inference',
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Write the 7-file quarantine audit package.
     *
     * @param  int         $intentId
     * @param  array       $snapshotOriginal  Data captured after attempt 1:
     *                                        { frame, phase2, sources, latency_master_ms,
     *                                          latency_derived_ms, at }
     * @param  array       $snapshotRetry     Data captured after attempt 2:
     *                                        { variants_before, variants_after, fixed_keys,
     *                                          phase2_before, phase2_after, latency_ms, source, at }
     * @param  array|null  $retryGuidance     Guidance that was passed to attempt 2
     * @param  array       $finalFrame        The actual final frame_en after all attempts (post-retry).
     *                                        Used for 07_final_quarantine.json to ensure correct final state.
     * @return string                         Absolute path of the package directory ('' on failure)
     */
    public function writeQuarantinePackage(
        int    $intentId,
        array  $snapshotOriginal,
        array  $snapshotRetry,
        ?array $retryGuidance,
        array  $finalFrame = []
    ): string {
        try {
            $timestamp = now()->format('Ymd_His');
            $dir       = storage_path(
                'app' . DIRECTORY_SEPARATOR . self::QUARANTINE_BASE .
                DIRECTORY_SEPARATOR . "kernel_{$intentId}_{$timestamp}"
            );

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $phase2Original = $snapshotOriginal['phase2']    ?? [];
            // Final Phase 2 result: prefer retry analysis, fall back to original if retry did not run
            $phase2Retry    = ! empty($snapshotRetry['phase2_after'])
                ? $snapshotRetry['phase2_after']
                : $phase2Original;
            $fixedKeys      = $snapshotRetry['fixed_keys']   ?? [];

            $this->writeFile($dir, '01_original_generation.json',
                $this->build01OriginalGeneration($snapshotOriginal));

            $this->writeFile($dir, '02_phase2_analysis.json',
                $this->build02Phase2Analysis($phase2Original, $snapshotOriginal['at'] ?? null));

            $this->writeFile($dir, '03_retry_guidance.json',
                $this->build03RetryGuidance($retryGuidance, $fixedKeys, $snapshotRetry['at'] ?? null));

            $this->writeFile($dir, '04_retry_generation.json',
                $this->build04RetryGeneration($snapshotRetry));

            $this->writeFile($dir, '05_phase2_retry_analysis.json',
                $this->build05Phase2RetryAnalysis($phase2Retry, $snapshotRetry['at'] ?? null));

            $this->writeFile($dir, '06_diff_report.json',
                $this->build06DiffReport($snapshotRetry, $phase2Original, $phase2Retry));

            // Use the explicitly-passed final frame (post-retry). If not provided (e.g. caller
            // is from a code path where retry did not run), fall back to snapshotOriginal frame.
            $frameForFinal = ! empty($finalFrame) ? $finalFrame : ($snapshotOriginal['frame'] ?? []);

            $this->writeFile($dir, '07_final_quarantine.json',
                $this->build07FinalQuarantine(
                    $intentId,
                    $frameForFinal,
                    $phase2Retry,
                    $phase2Original,
                    $fixedKeys,
                    $timestamp
                ));

            $this->writeRawFile($dir, '08_human_review.md',
                $this->build08HumanReview(
                    $intentId,
                    $frameForFinal,
                    $phase2Original,
                    $phase2Retry,
                    $snapshotOriginal,
                    $snapshotRetry,
                    $fixedKeys
                ));

            Log::info('[KernelQuarantineManager] audit package written', [
                'intent_id' => $intentId,
                'dir'       => $dir,
                'files'     => 8,
            ]);

            return $dir;
        } catch (Throwable $e) {
            Log::warning('[KernelQuarantineManager] writeQuarantinePackage failed (non-fatal)', [
                'intent_id' => $intentId,
                'error'     => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Send the quarantine alert.
     * Always logs Log::critical.
     * Sends email only when QB_KERNEL_ALERT_EMAIL configured AND MAIL_MAILER != log.
     *
     * @param  int    $intentId
     * @param  string $quarantineDir   Directory path returned by writeQuarantinePackage()
     * @param  array  $phase2Result    Final Phase 2 result (after retry)
     * @param  array  $kernelCore      For subject/domain context in the email
     */
    public function sendAlert(
        int    $intentId,
        string $quarantineDir,
        array  $phase2Result,
        array  $kernelCore = []
    ): void {
        try {
            $issues     = $phase2Result['structured_issues'] ?? [];
            $driftTypes = array_values(array_unique(array_filter(array_column($issues, 'drift_type'))));
            $variants   = array_values(array_unique(array_filter(array_column($issues, 'variant_key'))));

            $payload = [
                'intent_id'          => $intentId,
                'semantic_key'       => $kernelCore['semantic_key'] ?? '?',
                'domain'             => $kernelCore['domain']       ?? '?',
                'sub_domain'         => $kernelCore['sub_domain']   ?? '?',
                'subject'            => $kernelCore['subject']      ?? '?',
                'policy'             => $phase2Result['policy']     ?? '?',
                'drift_types'        => $driftTypes,
                'variants_affected'  => $variants,
                'fill_attempt_count' => 2,
                'trigger_reason'     => 'quarantine_after_inline_retry',
                'action_recommended' => 'Consulter le package d\'audit et corriger les variants marqués.',
                'quarantine_dir'     => $quarantineDir,
                'at'                 => now()->toIso8601String(),
                'environment'        => (string) config('app.env', 'unknown'),
            ];

            Log::critical('[KernelQuarantineManager] kernel quarantined — manual review required', $payload);

            $recipient = (string) config('question_bank_profiles.kernel_alert.email_recipient', '');
            $mailer    = (string) config('mail.default', 'log');

            if ($recipient !== '' && $mailer !== 'log') {
                try {
                    Mail::to($recipient)->send(new KernelLoopAlertMail($payload));
                    Log::info('[KernelQuarantineManager] quarantine alert email sent', [
                        'intent_id' => $intentId,
                        'to'        => $recipient,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('[KernelQuarantineManager] email failed (non-fatal)', [
                        'intent_id' => $intentId,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('[KernelQuarantineManager] sendAlert failed (non-fatal)', [
                'intent_id' => $intentId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Compatibility alias — kept so existing callers don't break.
     * Calls writeQuarantinePackage() with minimal snapshots built from the provided data.
     * Returns the package directory path (not a single file path as in the original).
     *
     * @deprecated  Use writeQuarantinePackage() directly for full audit packages.
     */
    public function writeQuarantineFile(int $intentId, array $frame, array $phase2Result): string
    {
        $snapshotOriginal = [
            'frame'  => $frame,
            'phase2' => $phase2Result,
            'at'     => now()->toIso8601String(),
        ];
        return $this->writeQuarantinePackage($intentId, $snapshotOriginal, [], null, $frame);
    }

    // =========================================================================
    // File builders — one method per snapshot file
    // =========================================================================

    private function build01OriginalGeneration(array $snap): array
    {
        $frame = $snap['frame'] ?? [];

        return [
            'generated_at' => $snap['at'] ?? now()->toIso8601String(),
            'kernel_core'  => $frame['kernel_core'] ?? [],
            'variants'     => $frame['variants']    ?? [],
            'phase2'       => [
                'policy'            => $snap['phase2']['policy']  ?? '?',
                'summary'           => $snap['phase2']['summary'] ?? [],
                'variant_scores'    => $snap['phase2']['variant_scores'] ?? [],
                'structured_issues' => $snap['phase2']['structured_issues'] ?? [],
            ],
            'latency' => [
                'master_ms'  => $snap['latency_master_ms']  ?? 0,
                'derived_ms' => $snap['latency_derived_ms'] ?? 0,
            ],
            'sources' => $snap['sources'] ?? [],
        ];
    }

    private function build02Phase2Analysis(?array $phase2, ?string $at): array
    {
        return [
            'analyzed_at'       => $at ?? now()->toIso8601String(),
            'policy'            => $phase2['policy']            ?? '?',
            'ok'                => $phase2['ok']                ?? false,
            'summary'           => $phase2['summary']           ?? [],
            'variant_scores'    => $phase2['variant_scores']    ?? [],
            'structured_issues' => $phase2['structured_issues'] ?? [],
            'recommendation'    => $phase2['recommendation']    ?? '',
        ];
    }

    private function build03RetryGuidance(?array $guidance, array $fixedKeys, ?string $at): array
    {
        return [
            'guidance_built_at' => $at ?? now()->toIso8601String(),
            'retry_attempt'     => 2,
            'variants_targeted' => $fixedKeys,
            'policy'            => $guidance['policy']          ?? '?',
            'failed_variants'   => $guidance['failed_variants'] ?? [],
            'avoid'             => $guidance['avoid']           ?? [],
            'retry_goal'        => $guidance['retry_goal']      ?? [],
        ];
    }

    private function build04RetryGeneration(array $snap): array
    {
        $fixedKeys      = $snap['fixed_keys']      ?? [];
        $variantsBefore = $snap['variants_before'] ?? [];
        $variantsAfter  = $snap['variants_after']  ?? [];

        $before = [];
        $after  = [];
        foreach ($fixedKeys as $key) {
            $before[$key] = $this->extractVariantContent($variantsBefore[$key] ?? []);
            $after[$key]  = $this->extractVariantContent($variantsAfter[$key]  ?? []);
        }

        return [
            'generated_at'       => $snap['at'] ?? now()->toIso8601String(),
            'fixed_variant_keys' => $fixedKeys,
            'variants_before'    => $before,
            'variants_after'     => $after,
            'latency_ms'         => $snap['latency_ms'] ?? 0,
            'source'             => $snap['source']     ?? '?',
        ];
    }

    private function build05Phase2RetryAnalysis(?array $phase2, ?string $at): array
    {
        return [
            'analyzed_at'       => $at ?? now()->toIso8601String(),
            'policy'            => $phase2['policy']            ?? '?',
            'ok'                => $phase2['ok']                ?? false,
            'summary'           => $phase2['summary']           ?? [],
            'variant_scores'    => $phase2['variant_scores']    ?? [],
            'structured_issues' => $phase2['structured_issues'] ?? [],
            'recommendation'    => $phase2['recommendation']    ?? '',
        ];
    }

    private function build06DiffReport(array $snap, ?array $phase2Before, ?array $phase2After): array
    {
        $fixedKeys       = $snap['fixed_keys']      ?? [];
        $variantsBefore  = $snap['variants_before'] ?? [];
        $variantsAfter   = $snap['variants_after']  ?? [];
        $scoresBefore    = $phase2Before['variant_scores'] ?? [];
        $scoresAfter     = $phase2After['variant_scores']  ?? [];

        $diffs = [];

        foreach ($fixedKeys as $key) {
            $sb = $scoresBefore[$key] ?? [];
            $sa = $scoresAfter[$key]  ?? [];

            $gradeBefore = $sb['grade'] ?? 'D';
            $gradeAfter  = $sa['grade'] ?? 'D';
            $scoreBefore = (float) ($sb['score'] ?? 0.0);
            $scoreAfter  = (float) ($sa['score'] ?? 0.0);

            $subsBefore = $sb['subscores'] ?? [];
            $subsAfter  = $sa['subscores'] ?? [];

            $semanticBefore   = (float) ($subsBefore['semantic_chain_alignment'] ?? 0.0);
            $semanticAfter    = (float) ($subsAfter['semantic_chain_alignment']  ?? 0.0);
            $cognitiveBefore  = (float) ($subsBefore['cognitive_integrity']      ?? 0.0);
            $cognitiveAfter   = (float) ($subsAfter['cognitive_integrity']       ?? 0.0);

            $rankBefore     = self::GRADE_RANK[$gradeBefore] ?? 1;
            $rankAfter      = self::GRADE_RANK[$gradeAfter]  ?? 1;
            $policyImproved = $rankAfter > $rankBefore;

            $vb = $variantsBefore[$key] ?? [];
            $va = $variantsAfter[$key]  ?? [];

            $diffs[] = [
                'variant_key' => $key,
                'before' => [
                    'question_text'     => $vb['question_text'] ?? '',
                    'answers'           => $this->extractAnswers($vb),
                    'correct_answer_key'=> $vb['correct_answer_key'] ?? '',
                    'grade'             => $gradeBefore,
                    'score'             => round($scoreBefore, 3),
                    'semantic_chain'    => round($semanticBefore, 3),
                    'cognitive_integrity'=> round($cognitiveBefore, 3),
                ],
                'after' => [
                    'question_text'     => $va['question_text'] ?? '',
                    'answers'           => $this->extractAnswers($va),
                    'correct_answer_key'=> $va['correct_answer_key'] ?? '',
                    'grade'             => $gradeAfter,
                    'score'             => round($scoreAfter, 3),
                    'semantic_chain'    => round($semanticAfter, 3),
                    'cognitive_integrity'=> round($cognitiveAfter, 3),
                ],
                'evolution' => [
                    'score_delta'      => round($scoreAfter - $scoreBefore, 3),
                    'semantic_delta'   => round($semanticAfter - $semanticBefore, 3),
                    'cognitive_delta'  => round($cognitiveAfter - $cognitiveBefore, 3),
                    'grade_before'     => $gradeBefore,
                    'grade_after'      => $gradeAfter,
                    'policy_improved'  => $policyImproved,
                ],
            ];
        }

        return [
            'generated_at'    => now()->toIso8601String(),
            'variants_diffed' => count($diffs),
            'diffs'           => $diffs,
        ];
    }

    private function build07FinalQuarantine(
        int    $intentId,
        array  $frame,
        ?array $phase2Retry,
        ?array $phase2Original,
        array  $fixedKeys,
        string $timestamp
    ): array {
        $issues         = $phase2Retry['structured_issues'] ?? [];
        $stillProblematic = array_values(array_filter(
            $issues,
            static fn(array $si) => in_array($si['grade'] ?? '', ['C', 'D'], true)
        ));

        $annotatedVariants = $this->annotateVariantsWithIssues(
            $frame['variants'] ?? [],
            $issues
        );

        return [
            'quarantine_at' => now()->toIso8601String(),
            'intent_id'     => $intentId,
            'kernel_core'   => $frame['kernel_core'] ?? [],
            'variants'      => $annotatedVariants,
            'phase2_result' => $phase2Retry ?? [],
            'retry_history' => [
                'attempt_1' => [
                    'policy'       => $phase2Original['policy'] ?? '?',
                    'issues_count' => count($phase2Original['structured_issues'] ?? []),
                    'summary'      => $phase2Original['summary'] ?? [],
                ],
                'attempt_2' => [
                    'policy'       => $phase2Retry['policy'] ?? '?',
                    'issues_count' => count($issues),
                    'fixed_keys'   => $fixedKeys,
                    'improvement'  => ($phase2Retry['policy'] ?? 'D') !== ($phase2Original['policy'] ?? 'D'),
                    'summary'      => $phase2Retry['summary'] ?? [],
                ],
            ],
            'still_problematic' => $stillProblematic,
            '_edit_instructions' => [
                'purpose'    => 'This kernel was quarantined after 2 generation attempts (full generation + targeted retry). Edit the variants marked with _phase2_issue.',
                'audit_files' => [
                    '01_original_generation.json'  => 'Complete generation attempt 1 — all 7 variants + Phase 2 scores',
                    '02_phase2_analysis.json'       => 'Phase 2 analysis after attempt 1 — identifies the failing variants',
                    '03_retry_guidance.json'        => 'Instructions given to the AI for targeted retry (attempt 2)',
                    '04_retry_generation.json'      => 'Side-by-side before/after for each variant that was retried',
                    '05_phase2_retry_analysis.json' => 'Phase 2 analysis after attempt 2 — still failing → quarantine',
                    '06_diff_report.json'           => 'Score evolution per retried variant (deltas + policy_improved)',
                    '07_final_quarantine.json'      => 'This file — final state for human correction',
                    '08_human_review.md'            => 'START HERE — human-readable summary: before/after content, score table, verdict per variant',
                ],
                'workflow' => [
                    '1. Read 06_diff_report.json to understand what the retry changed (or failed to change).',
                    '2. Read 02_phase2_analysis.json to understand the original drift_type for each failing variant.',
                    '3. Edit the variants marked _phase2_issue != null in this file (question_text, answers, explanation, saviez_vous).',
                    '4. Do NOT change kernel_core — it is the immutable subject anchor.',
                    '5. Do NOT change correct_answer_key unless the answer is factually wrong.',
                    '6. Save this file (07_final_quarantine.json).',
                    '7. Run: php artisan questions:kernel:import-quarantine ' . "<path_to_this_dir>",
                ],
                'grade_guide' => [
                    'A' => 'OK — no action needed',
                    'B' => 'Warn — minor subject-touch issue, tolerable',
                    'C' => 'Needs correction — subject drift or weak reasoning',
                    'D' => 'Critical — must be rewritten',
                ],
            ],
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function writeFile(string $dir, string $filename, array $content): void
    {
        try {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents(
                $path,
                json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            Log::warning('[KernelQuarantineManager] failed to write audit file (non-fatal)', [
                'file'  => $filename,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function writeRawFile(string $dir, string $filename, string $content): void
    {
        try {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($path, $content);
        } catch (Throwable $e) {
            Log::warning('[KernelQuarantineManager] failed to write audit file (non-fatal)', [
                'file'  => $filename,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the human-readable markdown review file (08_human_review.md).
     *
     * Per variant, displays:
     *   - Explicit cognitive type (A)
     *   - BEFORE/AFTER content blocks with scores (retried variants)
     *   - WHY FAILED section with specific sub-reasons from subscores + drift_type (B)
     *   - Gameplay Notes with proximity/difficulty judgments (C)
     *   - Verdict with correction instructions
     */
    private function build08HumanReview(
        int    $intentId,
        array  $frameForFinal,
        array  $phase2Original,
        array  $phase2Retry,
        array  $snapshotOriginal,
        array  $snapshotRetry,
        array  $fixedKeys
    ): string {
        $kc             = $frameForFinal['kernel_core']     ?? [];
        $finalVariants  = $frameForFinal['variants']        ?? [];
        $variantsBefore = $snapshotRetry['variants_before'] ?? [];
        $variantsAfter  = $snapshotRetry['variants_after']  ?? [];
        $scoresBefore   = $phase2Original['variant_scores'] ?? [];
        $scoresAfter    = $phase2Retry['variant_scores']    ?? [];
        $fixedKeySet    = array_flip($fixedKeys);

        $issuesBefore = [];
        foreach ($phase2Original['structured_issues'] ?? [] as $si) {
            $issuesBefore[$si['variant_key'] ?? ''] = $si;
        }
        $issuesAfter = [];
        foreach ($phase2Retry['structured_issues'] ?? [] as $si) {
            $issuesAfter[$si['variant_key'] ?? ''] = $si;
        }

        $stillBadKeys = array_keys(array_filter(
            $issuesAfter,
            static fn(array $si) => in_array($si['grade'] ?? '', ['C', 'D'], true)
        ));

        $lines = [];

        // ── Header ───────────────────────────────────────────────────────────
        $lines[] = "# 🔍 Quarantine Review — Kernel #{$intentId}";
        $lines[] = "";
        $lines[] = "**Generated:** " . now()->toIso8601String();
        $lines[] = "";
        $lines[] = "| Field | Value |";
        $lines[] = "|---|---|";
        $lines[] = "| Subject | " . ($kc['subject'] ?? '?') . " |";
        $lines[] = "| Answer target | " . ($kc['answer_target'] ?? '?') . " |";
        $lines[] = "| Potential trap | " . ($kc['potential_trap'] ?? '—') . " |";
        $lines[] = "| Domain | " . ($kc['domain'] ?? '?') . " / " . ($kc['sub_domain'] ?? '?') . " |";
        $lines[] = "| Semantic key | " . ($kc['semantic_key'] ?? '?') . " |";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        // ── Summary table ─────────────────────────────────────────────────────
        $s1 = $phase2Original['summary'] ?? [];
        $s2 = $phase2Retry['summary']    ?? [];
        $p1 = $phase2Original['policy']  ?? '?';
        $p2 = $phase2Retry['policy']     ?? '?';

        $lines[] = "## Summary";
        $lines[] = "";
        $lines[] = "| Metric | Attempt 1 | Attempt 2 (retry) |";
        $lines[] = "|---|---|---|";
        $lines[] = "| **Policy** | {$p1} | **{$p2}** |";
        $lines[] = "| OK (A) | " . ($s1['ok_count'] ?? 0) . " | " . ($s2['ok_count'] ?? 0) . " |";
        $lines[] = "| Warn (B) | " . ($s1['warn_count'] ?? 0) . " | " . ($s2['warn_count'] ?? 0) . " |";
        $lines[] = "| Partial (C) | " . ($s1['partial_count'] ?? 0) . " | " . ($s2['partial_count'] ?? 0) . " |";
        $lines[] = "| Review (D) | " . ($s1['review_count'] ?? 0) . " | " . ($s2['review_count'] ?? 0) . " |";
        $lines[] = "";

        $retriedList  = count($fixedKeys) > 0 ? implode(', ', $fixedKeys) : '(none)';
        $stillBadList = count($stillBadKeys) > 0 ? implode(', ', $stillBadKeys) : '(none)';

        $lines[] = "**Variants retried:** {$retriedList}  ";
        $lines[] = "**Still problematic after retry:** {$stillBadList}";
        $lines[] = "";
        $lines[] = "> 📋 **Action needed:** Edit variants marked **NEEDS CORRECTION** or **CRITICAL** below,  ";
        $lines[] = "> then run: `php artisan questions:kernel:import-quarantine <path_to_this_dir>`";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        // ── Per-variant sections ──────────────────────────────────────────────
        $variantOrder = [
            'qcm_recognition'      => '1. QCM Recognition (Master)',
            'qcm_reasoning'        => '2. QCM Reasoning',
            'qcm_deceptive_trap'   => '3. QCM Deceptive Trap',
            'tf_recognition_true'  => '4. V/F Recognition — TRUE',
            'tf_recognition_false' => '5. V/F Recognition — FALSE',
            'tf_reasoning_true'    => '6. V/F Reasoning — TRUE',
            'tf_reasoning_false'   => '7. V/F Reasoning — FALSE',
        ];

        foreach ($variantOrder as $key => $label) {
            $isRetried   = isset($fixedKeySet[$key]);
            $isStillBad  = in_array($key, $stillBadKeys, true);
            $gradeBefore = $scoresBefore[$key]['grade'] ?? 'A';
            $gradeAfter  = $scoresAfter[$key]['grade']  ?? ($isRetried ? $gradeBefore : ($scoresBefore[$key]['grade'] ?? 'A'));
            $scoreBefore = (float) ($scoresBefore[$key]['score'] ?? 0.0);
            $scoreAfter  = (float) ($scoresAfter[$key]['score']  ?? $scoreBefore);

            // Final subscores (for WHY FAILED + gameplay — available for all paths)
            $finalSubs  = $scoresAfter[$key]['subscores'] ?? $scoresBefore[$key]['subscores'] ?? [];
            $finalDrift = $issuesAfter[$key]['drift_type'] ?? '';

            // ── Header ───────────────────────────────────────────────────────
            if ($isStillBad) {
                $badge = $gradeAfter === 'D' ? '❌ CRITICAL — must be rewritten' : '⚠️ NEEDS CORRECTION';
            } elseif ($isRetried) {
                $badge = '✅ FIXED BY RETRY';
            } else {
                $badge = $gradeAfter === 'A' ? '✅ OK' : "⚠️ Grade {$gradeAfter} — monitor";
            }

            $retriedTag = $isRetried ? ' `[RETRIED]`' : ' `[NOT RETRIED]`';
            $lines[] = "## {$label}{$retriedTag}";
            $lines[] = "";
            $lines[] = "**Status:** {$badge}";

            if ($isRetried) {
                $rankBefore = self::GRADE_RANK[$gradeBefore] ?? 1;
                $rankAfter  = self::GRADE_RANK[$gradeAfter]  ?? 1;
                $improved   = $rankAfter > $rankBefore;
                $arrow      = $improved ? '→ ✅ improved' : '→ ❌ no improvement';
                $lines[] = "**Grade:** {$gradeBefore} → {$gradeAfter} {$arrow}";
            } else {
                $lines[] = "**Grade:** {$gradeAfter}";
            }

            // A — Cognitive type explicit
            $cogType = self::VARIANT_COGNITIVE_TYPE[$key] ?? $key;
            $lines[] = "**Cognitive type:** {$cogType}";
            $lines[] = "";

            if ($isRetried) {
                // ── BEFORE block ─────────────────────────────────────────────
                $vb = $variantsBefore[$key] ?? ($snapshotOriginal['frame']['variants'][$key] ?? []);
                $lines[] = "### BEFORE (attempt 1)";
                $lines[] = "";
                $this->appendVariantContent($lines, $vb);

                $driftBefore = $issuesBefore[$key]['drift_type'] ?? null;
                if ($driftBefore) {
                    $lines[] = "**Phase 2 drift:** `{$driftBefore}`  ";
                    $msgBefore = $issuesBefore[$key]['message_humain'] ?? '';
                    if ($msgBefore) {
                        $lines[] = "**Phase 2 message:** {$msgBefore}";
                    }
                    $lines[] = "";
                }

                // ── AFTER block ──────────────────────────────────────────────
                $va = $variantsAfter[$key] ?? $finalVariants[$key] ?? [];
                $lines[] = "### AFTER (attempt 2)";
                $lines[] = "";
                $this->appendVariantContent($lines, $va);

                $driftAfter = $issuesAfter[$key]['drift_type'] ?? null;
                if ($driftAfter) {
                    $lines[] = "**Phase 2 drift:** `{$driftAfter}`  ";
                    $msgAfter = $issuesAfter[$key]['message_humain'] ?? '';
                    if ($msgAfter) {
                        $lines[] = "**Phase 2 message:** {$msgAfter}";
                    }
                    $lines[] = "";
                }

                // ── Score evolution table ─────────────────────────────────────
                $sbSubs = $scoresBefore[$key]['subscores'] ?? [];
                $saSubs = $scoresAfter[$key]['subscores']  ?? [];

                $semBefore = (float) ($sbSubs['semantic_chain_alignment'] ?? 0.0);
                $semAfter  = (float) ($saSubs['semantic_chain_alignment'] ?? 0.0);
                $cogBefore = (float) ($sbSubs['cognitive_integrity']      ?? 0.0);
                $cogAfter  = (float) ($saSubs['cognitive_integrity']      ?? 0.0);

                $lines[] = "### Score Evolution";
                $lines[] = "";
                $lines[] = "| Metric | Before | After | Δ |";
                $lines[] = "|---|---|---|---|";
                $lines[] = "| subject_touch | " . number_format($scoreBefore, 3) . " | " . number_format($scoreAfter, 3) . " | " . $this->fmtDelta($scoreAfter - $scoreBefore) . " |";
                $lines[] = "| semantic_chain | " . number_format($semBefore, 3) . " | " . number_format($semAfter, 3) . " | " . $this->fmtDelta($semAfter - $semBefore) . " |";
                $lines[] = "| cognitive_integrity | " . number_format($cogBefore, 3) . " | " . number_format($cogAfter, 3) . " | " . $this->fmtDelta($cogAfter - $cogBefore) . " |";
                $lines[] = "";
            } else {
                // ── Final state only (not retried) ────────────────────────────
                $vf = $finalVariants[$key] ?? [];
                $this->appendVariantContent($lines, $vf);

                $driftAfter = $issuesAfter[$key]['drift_type'] ?? null;
                if ($driftAfter) {
                    $lines[] = "**Phase 2 drift:** `{$driftAfter}`  ";
                    $msgAfter = $issuesAfter[$key]['message_humain'] ?? '';
                    if ($msgAfter) {
                        $lines[] = "**Phase 2 message:** {$msgAfter}";
                    }
                    $lines[] = "";
                }
            }

            // B — WHY FAILED (for any C/D grade variant)
            $needsWhyFailed = $isStillBad || in_array($gradeAfter, ['C', 'D'], true);
            if ($needsWhyFailed && $finalDrift !== '') {
                $whyReasons = $this->deriveFailureReasons($key, $gradeAfter, $scoreAfter, $finalSubs, $finalDrift, $isRetried, $isStillBad);
                if (! empty($whyReasons)) {
                    $lines[] = "### ❌ WHY FAILED";
                    $lines[] = "";
                    foreach ($whyReasons as $reason) {
                        $lines[] = "- {$reason}";
                    }
                    $lines[] = "";
                }
            }

            // C — Gameplay Notes (any variant — even passing ones can have warnings)
            $gameplayWarnings = $this->deriveGameplayWarnings($key, $scoreAfter, $finalSubs, $gradeAfter, $isStillBad);
            if (! empty($gameplayWarnings)) {
                $lines[] = "### 🎮 Gameplay Notes";
                $lines[] = "";
                foreach ($gameplayWarnings as $w) {
                    $lines[] = "- {$w}";
                }
                $lines[] = "";
            }

            // ── Verdict ───────────────────────────────────────────────────────
            if ($isStillBad) {
                $lines[] = "> ✏️ **Manual correction required.** Edit `question_text`, answers, `explanation`, `saviez_vous` until the subject is clearly anchored to: *" . ($kc['subject'] ?? '?') . "*";
            } elseif ($gradeAfter === 'B') {
                $lines[] = "> ⚠️ Grade B — tolerable, monitor at next generation cycle.";
            }

            $lines[] = "";
            $lines[] = "---";
            $lines[] = "";
        }

        // ── Grade guide footer ────────────────────────────────────────────────
        $lines[] = "## Grade Guide";
        $lines[] = "";
        $lines[] = "| Grade | Meaning |";
        $lines[] = "|---|---|";
        $lines[] = "| A ✅ | OK — subject well anchored |";
        $lines[] = "| B ⚠️ | Warn — slight drift, tolerable |";
        $lines[] = "| C 🔶 | Needs correction — subject drift or weak reasoning |";
        $lines[] = "| D ❌ | Critical — must be fully rewritten |";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "*Generated by KernelQuarantineManager — StrategyBuzzer question bank audit package*";

        return implode("\n", $lines);
    }

    /**
     * Derive specific WHY FAILED reasons for a C/D grade variant.
     *
     * Produces human-readable strings from the combination of:
     *   - subscores (subject_touch, semantic_chain, cognitive_integrity)
     *   - drift_type from Phase 2 structured_issues
     *   - variant key (to apply type-specific logic)
     *   - retry outcome (wasRetried + isStillBad)
     */
    private function deriveFailureReasons(
        string $variantKey,
        string $grade,
        float  $score,
        array  $subscores,
        string $driftType,
        bool   $wasRetried,
        bool   $isStillBad
    ): array {
        $reasons  = [];
        $semantic  = (float) ($subscores['semantic_chain_alignment'] ?? 0.0);
        $cognitive = (float) ($subscores['cognitive_integrity']      ?? 0.0);

        // ── Subject anchor (lexical touch) ────────────────────────────────────
        if ($score < 0.10) {
            $reasons[] = 'Subject anchor completely absent — the question doesn\'t reference the topic at all';
        } elseif ($score < 0.15) {
            $reasons[] = 'Subject touch critically low (' . number_format($score, 3) . ') — topic mentioned at most once across question + answer + explanation';
        } elseif ($score < 0.22) {
            $reasons[] = 'Subject touch too low (' . number_format($score, 3) . ') — topic is barely present; increase explicit references to the answer_target and anchor terms';
        }

        // ── Semantic chain ─────────────────────────────────────────────────────
        $isReasoningVariant = in_array($variantKey, ['qcm_reasoning', 'tf_reasoning_true', 'tf_reasoning_false'], true);
        if ($semantic < 0.15) {
            $reasons[] = 'Semantic chain broken (chain=' . number_format($semantic, 3) . ') — no logical link between this variant and the master concept';
        } elseif ($semantic < 0.25 && $isReasoningVariant) {
            $reasons[] = 'Causal chain too short (chain=' . number_format($semantic, 3) . ') — reasoning doesn\'t follow from the master concept; add intermediate inferential steps';
        } elseif ($semantic < 0.30) {
            $reasons[] = 'Weak semantic chain (chain=' . number_format($semantic, 3) . ') — connection to master concept is indirect or superficial';
        }

        // ── Cognitive integrity ────────────────────────────────────────────────
        $isCognitivelySensitive = in_array($variantKey, ['qcm_reasoning', 'tf_reasoning_true', 'tf_reasoning_false', 'qcm_deceptive_trap'], true);
        if ($cognitive < 0.35) {
            $reasons[] = 'Cognitive structure very weak (integrity=' . number_format($cognitive, 3) . ') — variant doesn\'t behave like its declared type';
        } elseif ($cognitive < 0.50 && $isCognitivelySensitive) {
            $reasons[] = 'Cognitive integrity insufficient for a ' . (self::VARIANT_COGNITIVE_TYPE[$variantKey] ?? $variantKey) . ' (integrity=' . number_format($cognitive, 3) . ') — logical structure must be reinforced';
        }

        // ── Drift-type specific diagnosis ─────────────────────────────────────
        switch ($driftType) {
            case 'weak_reasoning':
                if ($variantKey === 'tf_reasoning_false') {
                    $reasons[] = 'False statement unconvincing — trivial inversion or negation that doesn\'t require subject knowledge to identify as false';
                } elseif ($variantKey === 'tf_reasoning_true') {
                    $reasons[] = 'True reasoning statement too direct — paraphrases the definition instead of requiring logical inference';
                } else {
                    $reasons[] = 'Reasoning chain doesn\'t require subject knowledge to answer — question works without knowing the topic';
                }
                break;
            case 'weak_deceptive_trap':
                $reasons[] = 'Trap distractor is generic, not anchored to the subject — the cognitive contract must be specifically designed around this topic\'s potential_trap';
                break;
            case 'false_not_plausible':
                $reasons[] = 'False statement not convincing enough — it doesn\'t appear true about this subject, reducing the cognitive challenge';
                break;
            case 'subject_escape':
                $reasons[] = 'Subject escape — the variant has drifted to a completely different topic';
                break;
            case 'subject_touch_low':
                $reasons[] = 'Topic keyword present but the question\'s core focus is off the subject';
                break;
            case 'kernel_collapse':
                $reasons[] = 'Kernel collapse — content is absent, empty, or completely off-topic';
                break;
        }

        // ── Retry outcome ─────────────────────────────────────────────────────
        if ($wasRetried && $isStillBad) {
            $reasons[] = 'AI retry did not resolve the core issue — structural rewrite required, not minor rephrasing';
        }

        return $reasons;
    }

    /**
     * Derive gameplay proximity and difficulty warnings for a variant.
     *
     * These are UX/gameplay judgments independent of Phase 2 quality scores:
     *   - Wording too close to master (player who saw master gets free answer)
     *   - Trap too obvious (experienced player identifies distractor immediately)
     *   - TF false too easy to detect without subject knowledge
     *   - Reasoning chain too weak to challenge a knowledgeable player
     */
    private function deriveGameplayWarnings(
        string $variantKey,
        float  $score,
        array  $subscores,
        string $grade,
        bool   $isStillBad
    ): array {
        $warnings  = [];
        $semantic  = (float) ($subscores['semantic_chain_alignment'] ?? 0.0);
        $cognitive = (float) ($subscores['cognitive_integrity']      ?? 0.0);

        // Wording too close to master — free answer for player who read master
        if ($variantKey !== 'qcm_recognition') {
            if ($semantic > 0.88) {
                $warnings[] = 'Wording too close to master (semantic chain=' . number_format($semantic, 3) . ') — a player who just read the master can answer without thinking';
            } elseif ($semantic > 0.78 && in_array($variantKey, ['tf_recognition_true', 'tf_recognition_false'], true)) {
                $warnings[] = 'Phrasing similar to master (chain=' . number_format($semantic, 3) . ') — this recognition T/F risks being a near-paraphrase; differentiate more from master wording';
            }
        }

        // Deceptive trap — obvious or not anchored
        if ($variantKey === 'qcm_deceptive_trap') {
            if ($cognitive < 0.45) {
                $warnings[] = 'Trap too obvious (integrity=' . number_format($cognitive, 3) . ') — experienced players will identify the distractor immediately; cognitive contract must be tighter';
            }
            if ($isStillBad) {
                $warnings[] = 'Deceptive trap not functioning — the distractor must be specifically designed around the subject\'s potential_trap, not a generic implausible option';
            }
        }

        // TF false — detectable without subject knowledge
        if ($variantKey === 'tf_recognition_false' && $score < 0.30) {
            $warnings[] = 'False statement too easy to detect — player can reject it without knowing the subject, reducing strategic value of the T/F format';
        }
        if ($variantKey === 'tf_reasoning_false' && $cognitive < 0.45) {
            $warnings[] = 'Reasoning false statement won\'t challenge a knowledgeable player — logical structure too weak to create meaningful doubt';
        }

        // QCM reasoning — generic, not topic-specific
        if ($variantKey === 'qcm_reasoning' && $score < 0.25 && $grade !== 'A') {
            $warnings[] = 'Generic reasoning question — could apply to many topics, not specifically tied to this subject; the causal chain must require knowledge of the answer_target';
        }

        // TF reasoning true — trivially obvious from master
        if ($variantKey === 'tf_reasoning_true' && $semantic > 0.82) {
            $warnings[] = 'True reasoning statement may be trivially obvious (master overlap=' . number_format($semantic, 3) . ') — reduce direct overlap with QCM master wording to force genuine inference';
        }

        return $warnings;
    }

    /**
     * Append a human-readable variant content block (question / answers / explanation / saviez_vous)
     * to the lines array. Marks the correct answer with ✅.
     */
    private function appendVariantContent(array &$lines, array $v): void
    {
        $qt  = $v['question_text'] ?? '_(no content generated)_';
        $ck  = strtolower($v['correct_answer_key'] ?? '');

        $lines[] = "**Question:** {$qt}";
        $lines[] = "";
        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $ans  = $v["answer_{$letter}"] ?? '—';
            $mark = ($ck === $letter) ? ' ✅' : '';
            $lines[] = "- **" . strtoupper($letter) . ":** {$ans}{$mark}";
        }
        $lines[] = "";
        $lines[] = "**Explanation:** " . ($v['explanation'] ?? '—');
        $lines[] = "";
        $lines[] = "**Saviez-vous:** " . ($v['saviez_vous'] ?? '—');
        $lines[] = "";
    }

    /**
     * Format a numeric delta with explicit sign for the score evolution table.
     */
    private function fmtDelta(float $delta): string
    {
        $sign = $delta >= 0.0 ? '+' : '';
        return $sign . number_format($delta, 3);
    }

    /**
     * Extract only the content fields from a variant (no translation_slots, no internal keys).
     */
    private function extractVariantContent(array $variant): array
    {
        $fields = ['question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
                   'correct_answer_key', 'explanation', 'saviez_vous'];
        $out = [];
        foreach ($fields as $f) {
            $out[$f] = $variant[$f] ?? null;
        }
        if (isset($variant['cognitive_contract'])) {
            $out['cognitive_contract'] = $variant['cognitive_contract'];
        }
        return $out;
    }

    /**
     * Extract answers a/b/c/d as a compact object for the diff report.
     */
    private function extractAnswers(array $variant): array
    {
        return [
            'a' => $variant['answer_a'] ?? null,
            'b' => $variant['answer_b'] ?? null,
            'c' => $variant['answer_c'] ?? null,
            'd' => $variant['answer_d'] ?? null,
        ];
    }

    /**
     * Annotate each variant with its Phase 2 issue for human review.
     * Variants with no issue get '_phase2_issue' => null (grade A — OK).
     */
    private function annotateVariantsWithIssues(array $variants, array $structuredIssues): array
    {
        $issuesByKey = [];
        foreach ($structuredIssues as $si) {
            $k = $si['variant_key'] ?? null;
            if ($k) {
                $issuesByKey[$k] = [
                    'grade'           => $si['grade']            ?? '?',
                    'score'           => $si['score']            ?? null,
                    'drift_type'      => $si['drift_type']       ?? '?',
                    'action_required' => $si['action_required']  ?? '?',
                    'message'         => $si['message_humain']   ?? '',
                ];
            }
        }

        $annotated = [];
        foreach ($variants as $key => $variant) {
            $annotated[$key] = array_merge(
                ['_phase2_issue' => $issuesByKey[$key] ?? null],
                $variant
            );
        }

        return $annotated;
    }
}
