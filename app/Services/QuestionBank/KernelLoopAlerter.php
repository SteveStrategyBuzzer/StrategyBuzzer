<?php

namespace App\Services\QuestionBank;

use App\Mail\KernelLoopAlertMail;
use App\Models\QuestionIntent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * KernelLoopAlerter
 *
 * Detects when a question kernel enters a correction loop or presents
 * a recurring drift, then fires a single alert (email + Log::critical).
 *
 * Triggers evaluated by evaluate():
 *   same_drift_repeat     — identical drift_type in previous + current phase2_result
 *   max_attempts_reached  — _fill_attempt_count >= MAX_FILL_ATTEMPTS
 *   policy_d              — Phase 2 returned Policy D
 *   kernel_collapse       — any structured_issue has drift_type=kernel_collapse
 *   reject_kernel         — any structured_issue has action_required=reject_kernel
 *   regen_required_multi  — 2+ structured_issues with action_required=regen_required
 *   retry_variant_cascade — 3+ structured_issues with action_required=retry_variant
 *
 * Dedup: _alert_sent_at stored in frame_en prevents repeat emails
 * for the same kernel within the configured dedup window (default 24h).
 *
 * Never propagates exceptions — alerting must never block generation.
 */
final class KernelLoopAlerter
{
    public const MAX_FILL_ATTEMPTS = 3;

    private const BLOCKING_TRIGGERS = [
        'same_drift_repeat',
        'max_attempts_reached',
        'kernel_collapse',
        'reject_kernel',
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Evaluate whether an alert should be triggered.
     *
     * @param  array               $frame         Current frame_en (after generation, before alert).
     * @param  QuestionIntent      $intent        The intent being processed.
     * @param  array|null          $previousPhase2 phase2_result from BEFORE this generation run.
     * @param  array               $phase2Result  phase2_result from AFTER this generation run.
     * @return string|null         Trigger reason string, or null if no alert needed.
     */
    public function evaluate(
        array          $frame,
        QuestionIntent $intent,
        ?array         $previousPhase2,
        array          $phase2Result
    ): ?string {
        $fillAttemptCount = (int) ($frame['_fill_attempt_count'] ?? 0);
        $issues           = $phase2Result['structured_issues'] ?? [];

        // Trigger 2 — max attempts reached (evaluated first — hardest stop)
        if ($fillAttemptCount >= self::MAX_FILL_ATTEMPTS) {
            return 'max_attempts_reached';
        }

        // Trigger 4 — kernel_collapse present in current issues
        foreach ($issues as $si) {
            if (($si['drift_type'] ?? '') === 'kernel_collapse') {
                return 'kernel_collapse';
            }
        }

        // Trigger 5 — reject_kernel present in current issues
        foreach ($issues as $si) {
            if (($si['action_required'] ?? '') === 'reject_kernel') {
                return 'reject_kernel';
            }
        }

        // Trigger 1 — same drift_type repeated from previous run
        if ($previousPhase2 !== null) {
            $prevDrifts    = array_column($previousPhase2['structured_issues'] ?? [], 'drift_type');
            $currentDrifts = array_column($issues, 'drift_type');
            $sharedDrifts  = array_intersect(array_filter($prevDrifts), array_filter($currentDrifts));
            if (!empty($sharedDrifts)) {
                return 'same_drift_repeat';
            }
        }

        // Trigger 3 — policy D
        if (($phase2Result['policy'] ?? '') === 'D') {
            return 'policy_d';
        }

        // Trigger 6 — regen_required on 2+ variants
        $regenCount = count(array_filter($issues, fn ($si) => ($si['action_required'] ?? '') === 'regen_required'));
        if ($regenCount >= 2) {
            return 'regen_required_multi';
        }

        // Trigger 7 — retry_variant on 3+ variants
        $retryCount = count(array_filter($issues, fn ($si) => ($si['action_required'] ?? '') === 'retry_variant'));
        if ($retryCount >= 3) {
            return 'retry_variant_cascade';
        }

        return null;
    }

    /**
     * Fire alert for a triggered kernel loop.
     * Checks dedup window before sending email.
     * Always writes Log::critical regardless of email config.
     * Writes _alert_sent_at + _alert_trigger into $frame (passed by reference).
     *
     * @param  array          $frame          Modified in place: _alert_sent_at + _alert_trigger added.
     * @param  QuestionIntent $intent
     * @param  string         $triggerReason
     * @param  array          $phase2Result
     */
    public function maybeAlert(
        array          &$frame,
        QuestionIntent $intent,
        string         $triggerReason,
        array          $phase2Result
    ): void {
        try {
            $dedupHours  = (int) config('question_bank_profiles.kernel_alert.dedup_hours', 24);
            $alertSentAt = $frame['_alert_sent_at'] ?? null;

            if ($alertSentAt !== null) {
                try {
                    $sentAt = Carbon::parse($alertSentAt);
                    if (now()->diffInHours($sentAt, true) < $dedupHours) {
                        Log::debug('[KernelLoopAlerter] alert deduped — sent < ' . $dedupHours . 'h ago', [
                            'intent_id'     => $intent->id,
                            'trigger'       => $triggerReason,
                            'alert_sent_at' => $alertSentAt,
                        ]);
                        return;
                    }
                } catch (Throwable) {
                }
            }

            $issues      = $phase2Result['structured_issues'] ?? [];
            $driftTypes  = array_values(array_unique(array_filter(array_column($issues, 'drift_type'))));
            $variants    = array_values(array_unique(array_filter(array_column($issues, 'variant_key'))));
            $actionRec   = $this->resolveActionRecommendation($triggerReason);

            $payload = [
                'intent_id'          => $intent->id,
                'semantic_key'       => $intent->semantic_key ?? '?',
                'domain'             => $intent->domain ?? '?',
                'sub_domain'         => $intent->sub_domain ?? '?',
                'subject'            => $intent->subject ?? '?',
                'policy'             => $phase2Result['policy'] ?? '?',
                'drift_types'        => $driftTypes,
                'variants_affected'  => $variants,
                'fill_attempt_count' => (int) ($frame['_fill_attempt_count'] ?? 0),
                'trigger_reason'     => $triggerReason,
                'action_recommended' => $actionRec,
                'at'                 => now()->toIso8601String(),
                'environment'        => (string) config('app.env', 'unknown'),
            ];

            // Always log — fallback even without SMTP
            Log::critical('[KernelLoopAlerter] kernel loop alert', $payload);

            // Email: only if recipient configured and mailer is not the log driver
            $recipient = (string) config('question_bank_profiles.kernel_alert.email_recipient', '');
            $mailer    = (string) config('mail.default', 'log');

            if ($recipient !== '' && $mailer !== 'log') {
                try {
                    Mail::to($recipient)->send(new KernelLoopAlertMail($payload));
                    Log::info('[KernelLoopAlerter] alert email sent', [
                        'intent_id' => $intent->id,
                        'to'        => $recipient,
                        'trigger'   => $triggerReason,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('[KernelLoopAlerter] email send failed (non-fatal)', [
                        'error'     => $e->getMessage(),
                        'intent_id' => $intent->id,
                    ]);
                }
            }

            // Write dedup marker into frame (caller must persist frame_en)
            $frame['_alert_sent_at'] = now()->toIso8601String();
            $frame['_alert_trigger'] = $triggerReason;
        } catch (Throwable $e) {
            Log::warning('[KernelLoopAlerter] maybeAlert failed (non-fatal)', [
                'error'     => $e->getMessage(),
                'intent_id' => $intent->id ?? null,
            ]);
        }
    }

    /**
     * Whether a trigger requires blocking frame_status change.
     */
    public function isBlocking(string $triggerReason): bool
    {
        return in_array($triggerReason, self::BLOCKING_TRIGGERS, true);
    }

    /**
     * Resolve frame_status for a blocking trigger.
     */
    public function resolveBlockingStatus(string $triggerReason): string
    {
        return $triggerReason === 'max_attempts_reached' ? 'rejected' : 'human_review';
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function resolveActionRecommendation(string $triggerReason): string
    {
        return match ($triggerReason) {
            'same_drift_repeat'    => 'Vérifier kernel_core + relancer questions:kernel:skeleton avant nouvelle génération.',
            'max_attempts_reached' => 'Noyau bloqué (rejected) — revoir kernel_core manuellement.',
            'policy_d'             => 'Régénérer les variantes faibles ou revoir le kernel_core.',
            'kernel_collapse'      => 'Contenu vide ou absent — relancer questions:kernel:fill-content après correction du kernel_core.',
            'reject_kernel'        => 'Noyau entier à régénérer — 3+ variants grade D.',
            'regen_required_multi' => '2+ variantes nécessitent une régénération complète.',
            'retry_variant_cascade'=> '3+ variantes marquées retry_variant — risque de boucle.',
            default                => 'Vérifier manuellement ce noyau.',
        };
    }
}
