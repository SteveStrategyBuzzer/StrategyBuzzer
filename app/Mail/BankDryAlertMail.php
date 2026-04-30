<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Bank-dry critical-threshold alert email. Plain text — Ops cares
 * about the segment + counts, not formatting.
 */
class BankDryAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function build(): self
    {
        $segment = $this->payload['segment'] ?? [];
        $body = sprintf(
            "Question bank DRY alert\n\nEnvironment: %s\nCRITICAL events: %d in last %d minutes (threshold: %d)\n\nLast segment:\n  theme: %s\n  niveau: %s\n  language: %s\n  is_boss: %s\n  context: %s\n  cache_status: %s\n\nReported at: %s\n",
            $this->payload['environment'] ?? '?',
            (int) ($this->payload['count'] ?? 0),
            (int) ($this->payload['window_minutes'] ?? 0),
            (int) ($this->payload['threshold'] ?? 0),
            $segment['theme'] ?? '?',
            $segment['niveau'] ?? '?',
            $segment['language'] ?? '?',
            isset($segment['is_boss']) && $segment['is_boss'] ? 'true' : 'false',
            $segment['context'] ?? '?',
            $segment['cache_status'] ?? '?',
            $this->payload['at'] ?? now()->toIso8601String()
        );

        $subject = sprintf(
            '[%s] Question bank DRY — %d critical events',
            strtoupper((string) ($this->payload['environment'] ?? 'unknown')),
            (int) ($this->payload['count'] ?? 0)
        );

        return $this->subject($subject)->text('mail.bank-dry-alert-plain', ['body' => $body]);
    }
}
