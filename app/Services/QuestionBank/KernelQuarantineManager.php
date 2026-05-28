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
     * @return string                         Absolute path of the package directory ('' on failure)
     */
    public function writeQuarantinePackage(
        int    $intentId,
        array  $snapshotOriginal,
        array  $snapshotRetry,
        ?array $retryGuidance
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

            $phase2Original = $snapshotOriginal['phase2']      ?? [];
            $phase2Retry    = $snapshotRetry['phase2_after']   ?? [];
            $fixedKeys      = $snapshotRetry['fixed_keys']     ?? [];

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

            $this->writeFile($dir, '07_final_quarantine.json',
                $this->build07FinalQuarantine(
                    $intentId,
                    $snapshotOriginal['frame'] ?? [],
                    $phase2Retry,
                    $phase2Original,
                    $fixedKeys,
                    $timestamp
                ));

            Log::info('[KernelQuarantineManager] audit package written', [
                'intent_id' => $intentId,
                'dir'       => $dir,
                'files'     => 7,
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
