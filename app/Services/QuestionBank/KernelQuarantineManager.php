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
 * Responsibilities:
 *   1. Write a human-editable JSON file to storage/app/quarantine/
 *      The file contains the full kernel (kernel_core + all 7 variants) with
 *      Phase 2 structured_issues annotated so the reviewer knows what to fix.
 *   2. Send an alert email to QB_KERNEL_ALERT_EMAIL (if configured and
 *      MAIL_MAILER != log). Always falls back to Log::critical.
 *
 * Usage (called from FillContentCommand after inline retry fails):
 *   $manager = new KernelQuarantineManager();
 *   $path    = $manager->writeQuarantineFile($intentId, $frame, $phase2Result);
 *   $manager->sendAlert($intentId, $path, $phase2Result);
 *
 * Re-entry (future — not implemented here):
 *   php artisan questions:kernel:import-quarantine {file}
 *   Reads the edited file, validates, imports back into frame_en,
 *   sets frame_status=awaiting_content, triggers translation.
 *
 * Never propagates exceptions — quarantine must never block the pipeline.
 */
final class KernelQuarantineManager
{
    private const QUARANTINE_DIR = 'quarantine';

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Write the full kernel to a human-editable JSON quarantine file.
     *
     * The file includes:
     *   - quarantine_at       : ISO 8601 timestamp
     *   - intent_id           : DB id of the QuestionIntent
     *   - kernel_core         : the kernel_core object (subject, anchor terms, etc.)
     *   - variants            : all 7 variant objects with current EN content
     *   - phase2_result       : Phase 2 structured_issues explaining what to fix
     *   - _edit_instructions  : human-readable guide for the reviewer
     *
     * @param  int    $intentId
     * @param  array  $frame         Full frame_en array
     * @param  array  $phase2Result  phase2_result from the last Phase 2 run
     * @return string                Absolute path of the written file (empty on failure)
     */
    public function writeQuarantineFile(int $intentId, array $frame, array $phase2Result): string
    {
        try {
            $dir = storage_path('app/' . self::QUARANTINE_DIR);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $timestamp = now()->format('Ymd_His');
            $filename  = "kernel_{$intentId}_{$timestamp}.json";
            $path      = $dir . DIRECTORY_SEPARATOR . $filename;

            // Annotate each variant with its Phase 2 issue (if any) for the reviewer
            $annotatedVariants = $this->annotateVariantsWithIssues(
                $frame['variants'] ?? [],
                $phase2Result['structured_issues'] ?? []
            );

            $content = [
                'quarantine_at'      => now()->toIso8601String(),
                'intent_id'          => $intentId,
                'kernel_core'        => $frame['kernel_core'] ?? [],
                'variants'           => $annotatedVariants,
                'phase2_result'      => $phase2Result,
                '_edit_instructions' => [
                    'purpose'  => 'This kernel was quarantined after 2 generation attempts. Edit the variants marked with _phase2_issue.',
                    'workflow' => [
                        '1. Review each variant marked with _phase2_issue.',
                        '2. Edit question_text, answer_*, explanation, saviez_vous as needed.',
                        '3. Do NOT change kernel_core — it is the immutable subject anchor.',
                        '4. Do NOT change the correct_answer_key unless the answer is factually wrong.',
                        '5. Save this file.',
                        '6. Run: php artisan questions:kernel:import-quarantine <path_to_this_file>',
                        '7. The import command re-validates and re-triggers translation.',
                    ],
                    'grade_guide' => [
                        'A' => 'OK — no action needed',
                        'B' => 'Warn — minor subject-touch issue, tolerable',
                        'C' => 'Needs correction — subject drift or weak reasoning',
                        'D' => 'Critical — must be rewritten or regenerated',
                    ],
                ],
            ];

            file_put_contents(
                $path,
                json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            Log::info('[KernelQuarantineManager] quarantine file written', [
                'intent_id' => $intentId,
                'file'      => $path,
            ]);

            return $path;
        } catch (Throwable $e) {
            Log::warning('[KernelQuarantineManager] writeQuarantineFile failed (non-fatal)', [
                'intent_id' => $intentId,
                'error'     => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Send the quarantine alert.
     * Always logs Log::critical.
     * Sends email only when QB_KERNEL_ALERT_EMAIL is configured
     * AND MAIL_MAILER is not 'log'.
     *
     * @param  int         $intentId
     * @param  string      $quarantineFilePath  Path returned by writeQuarantineFile()
     * @param  array       $phase2Result
     * @param  array       $kernelCore          For subject/domain context in the email
     */
    public function sendAlert(
        int    $intentId,
        string $quarantineFilePath,
        array  $phase2Result,
        array  $kernelCore = []
    ): void {
        try {
            $issues    = $phase2Result['structured_issues'] ?? [];
            $driftTypes = array_values(array_unique(array_filter(array_column($issues, 'drift_type'))));
            $variants   = array_values(array_unique(array_filter(array_column($issues, 'variant_key'))));

            $payload = [
                'intent_id'          => $intentId,
                'semantic_key'       => $kernelCore['semantic_key']   ?? '?',
                'domain'             => $kernelCore['domain']         ?? '?',
                'sub_domain'         => $kernelCore['sub_domain']     ?? '?',
                'subject'            => $kernelCore['subject']        ?? '?',
                'policy'             => $phase2Result['policy']       ?? '?',
                'drift_types'        => $driftTypes,
                'variants_affected'  => $variants,
                'fill_attempt_count' => 2,
                'trigger_reason'     => 'quarantine_after_inline_retry',
                'action_recommended' => 'Éditer le fichier de quarantaine et relancer l\'import.',
                'quarantine_file'    => $quarantineFilePath,
                'at'                 => now()->toIso8601String(),
                'environment'        => (string) config('app.env', 'unknown'),
            ];

            // Always log — works even without SMTP
            Log::critical('[KernelQuarantineManager] kernel quarantined — manual review required', $payload);

            // Email: only when recipient + non-log mailer
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
    // Private helpers
    // =========================================================================

    /**
     * Merge Phase 2 structured_issues into each variant as a '_phase2_issue' annotation.
     * Variants with no issue get '_phase2_issue' => null (grade A — OK).
     */
    private function annotateVariantsWithIssues(array $variants, array $structuredIssues): array
    {
        $issuesByKey = [];
        foreach ($structuredIssues as $si) {
            $k = $si['variant_key'] ?? null;
            if ($k) {
                $issuesByKey[$k] = [
                    'grade'          => $si['grade']           ?? '?',
                    'score'          => $si['score']           ?? null,
                    'drift_type'     => $si['drift_type']      ?? '?',
                    'action_required'=> $si['action_required'] ?? '?',
                    'message'        => $si['message_humain']  ?? '',
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
