<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Kernel loop / drift alert email — plain text.
 *
 * Fired when a question kernel enters a correction loop:
 * repeated drift_type, kernel_collapse, reject_kernel,
 * max fill attempts reached, or Policy D.
 *
 * Pattern mirrors BankDryAlertMail — plain text only,
 * no HTML, ops-focused content.
 */
class KernelLoopAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function build(): self
    {
        $p = $this->payload;

        $driftTypes = implode(', ', (array) ($p['drift_types'] ?? []));
        $variants   = implode(', ', (array) ($p['variants_affected'] ?? []));

        $body = sprintf(
            "Bonjour Steve,\n\nUn noyau de questions semble entrer dans une boucle de corrections ou présente une dérive récurrente.\n\nDétails :\n- intent_id         : %s\n- semantic_key      : %s\n- domaine           : %s\n- sous-domaine      : %s\n- sujet             : %s\n- policy            : %s\n- drift_type        : %s\n- variants concernés: %s\n- nombre de tentatives : %d\n- déclencheur       : %s\n- action recommandée: %s\n\nMerci de vérifier ce noyau avant nouvelle génération.\n\nSignalé à     : %s\nEnvironnement : %s\n",
            $p['intent_id']        ?? '?',
            $p['semantic_key']     ?? '?',
            $p['domain']           ?? '?',
            $p['sub_domain']       ?? '?',
            $p['subject']          ?? '?',
            $p['policy']           ?? '?',
            $driftTypes ?: '—',
            $variants   ?: '—',
            (int) ($p['fill_attempt_count'] ?? 0),
            $p['trigger_reason']   ?? '?',
            $p['action_recommended'] ?? '?',
            $p['at']               ?? now()->toIso8601String(),
            $p['environment']      ?? 'unknown'
        );

        $subject = sprintf(
            '[StrategyBuzzer] Noyau IA à vérifier — intent #%s (%s)',
            $p['intent_id']     ?? '?',
            $p['trigger_reason'] ?? '?'
        );

        return $this->subject($subject)->text('mail.kernel-loop-alert-plain', ['body' => $body]);
    }
}
