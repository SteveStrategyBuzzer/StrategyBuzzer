<?php

namespace App\Services\QuestionBank;

use App\Mail\BankDryAlertMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Proactive ops alerter for bank-dry CRITICAL events. Posts to Slack
 * and/or email once the rolling CRITICAL count over a configurable
 * window strictly exceeds the configured threshold, then enters a
 * cooldown so a sustained outage produces one alert per cooldown
 * window. Never propagates exceptions — gameplay is the priority.
 */
class BankDryAlerter
{
    public function __construct(
        private readonly ?int $thresholdOverride = null,
        private readonly ?int $windowMinutesOverride = null,
        private readonly ?int $cooldownMinutesOverride = null,
    ) {
    }

    public function maybeAlert(array $segment): void
    {
        try {
            $threshold = $this->threshold();
            $windowMinutes = $this->windowMinutes();
            $cooldownSeconds = $this->cooldownMinutes() * 60;

            if ($threshold <= 0 || $windowMinutes <= 0) {
                return;
            }

            $count = $this->rollingCriticalCount($windowMinutes);
            if ($count <= $threshold) {
                return;
            }

            if ($this->isInCooldown($cooldownSeconds)) {
                return;
            }

            $slackUrl = (string) config('question_bank_profiles.worker.dry_alert.slack_webhook_url', '');
            $email = (string) config('question_bank_profiles.worker.dry_alert.email_recipient', '');

            // No destination wired: log a critical line so the signal
            // still reaches the log aggregator, and do NOT bump the
            // cooldown — the next CRITICAL event after Ops configures
            // a destination must be able to fire immediately.
            if ($slackUrl === '' && $email === '') {
                Log::critical('[BankDryAlerter] Threshold breached but no destination configured', [
                    'count' => $count,
                    'window_minutes' => $windowMinutes,
                    'threshold' => $threshold,
                    'segment' => $segment,
                ]);
                return;
            }

            $payload = [
                'count' => $count,
                'window_minutes' => $windowMinutes,
                'threshold' => $threshold,
                'segment' => $segment,
                'environment' => (string) config('question_bank_profiles.worker.dry_alert.environment_label', 'unknown'),
                'at' => now()->toIso8601String(),
            ];

            $delivered = false;
            if ($slackUrl !== '') {
                $delivered = $this->sendSlack($slackUrl, $payload) || $delivered;
            }
            if ($email !== '') {
                $delivered = $this->sendEmail($email, $payload) || $delivered;
            }

            if ($delivered) {
                $this->markAlertSent($cooldownSeconds, $payload);
            }
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] maybeAlert failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *   threshold:int, window_minutes:int, cooldown_minutes:int,
     *   slack_configured:bool, email_configured:bool,
     *   in_cooldown:bool, last_alert_at:?string,
     * }
     */
    public function configSnapshot(): array
    {
        $cooldownSeconds = $this->cooldownMinutes() * 60;
        $lastAlertAt = null;
        $inCooldown = false;
        try {
            $raw = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_alert'));
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $lastAlertAt = $decoded['at'] ?? null;
                    if (isset($decoded['ts']) && (time() - (int) $decoded['ts']) < $cooldownSeconds) {
                        $inCooldown = true;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] configSnapshot read failed', ['error' => $e->getMessage()]);
        }

        return [
            'threshold' => $this->threshold(),
            'window_minutes' => $this->windowMinutes(),
            'cooldown_minutes' => $this->cooldownMinutes(),
            'slack_configured' => ((string) config('question_bank_profiles.worker.dry_alert.slack_webhook_url', '')) !== '',
            'email_configured' => ((string) config('question_bank_profiles.worker.dry_alert.email_recipient', '')) !== '',
            'in_cooldown' => $inCooldown,
            'last_alert_at' => $lastAlertAt,
        ];
    }

    private function rollingCriticalCount(int $windowMinutes): int
    {
        $sum = 0;
        try {
            $minute = (int) floor(time() / 60);
            $pattern = config('question_bank_profiles.worker.redis_keys.dry_total_counter');
            for ($i = 0; $i < $windowMinutes; $i++) {
                $sum += (int) Redis::get(sprintf($pattern, $minute - $i));
            }
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] rolling count read failed', ['error' => $e->getMessage()]);
        }
        return $sum;
    }

    private function isInCooldown(int $cooldownSeconds): bool
    {
        if ($cooldownSeconds <= 0) {
            return false;
        }
        try {
            $raw = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_alert'));
            if (!is_string($raw) || $raw === '') {
                return false;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !isset($decoded['ts'])) {
                return false;
            }
            return (time() - (int) $decoded['ts']) < $cooldownSeconds;
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] cooldown read failed', ['error' => $e->getMessage()]);
            // Fail open: a missed alert is worse than a duplicate one.
            return false;
        }
    }

    private function markAlertSent(int $cooldownSeconds, array $payload): void
    {
        try {
            $key = config('question_bank_profiles.worker.redis_keys.dry_last_alert');
            $body = json_encode([
                'ts' => time(),
                'at' => $payload['at'] ?? now()->toIso8601String(),
                'count' => $payload['count'] ?? null,
                'window_minutes' => $payload['window_minutes'] ?? null,
                'threshold' => $payload['threshold'] ?? null,
                'segment' => $payload['segment'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
            Redis::set($key, $body);
            Redis::expire($key, max($cooldownSeconds * 2, 3600));
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] markAlertSent failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendSlack(string $url, array $payload): bool
    {
        try {
            $segment = $payload['segment'];
            $segmentLabel = sprintf(
                'theme=%s niveau=%s lang=%s%s',
                $segment['theme'] ?? '?',
                $segment['niveau'] ?? '?',
                $segment['language'] ?? '?',
                isset($segment['is_boss']) && $segment['is_boss'] ? ' (BOSS)' : ''
            );
            $text = sprintf(
                ":rotating_light: *Question bank DRY* — %d CRITICAL events in last %d min (threshold=%d) on `%s`.\nLast segment: `%s`",
                $payload['count'],
                $payload['window_minutes'],
                $payload['threshold'],
                $payload['environment'],
                $segmentLabel
            );
            $response = Http::timeout(5)->post($url, ['text' => $text]);
            if ($response->successful()) {
                return true;
            }
            Log::warning('[BankDryAlerter] Slack webhook returned non-2xx', [
                'status' => $response->status(),
                'body_preview' => substr((string) $response->body(), 0, 200),
            ]);
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] Slack webhook POST failed', [
                'error' => $e->getMessage(),
            ]);
        }
        return false;
    }

    private function sendEmail(string $recipient, array $payload): bool
    {
        try {
            Mail::to($recipient)->send(new BankDryAlertMail($payload));
            return true;
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] email send failed', [
                'error' => $e->getMessage(),
            ]);
        }
        return false;
    }

    private function threshold(): int
    {
        return $this->thresholdOverride ?? (int) config('question_bank_profiles.worker.dry_alert.threshold', 5);
    }

    private function windowMinutes(): int
    {
        return $this->windowMinutesOverride ?? (int) config('question_bank_profiles.worker.dry_alert.window_minutes', 10);
    }

    private function cooldownMinutes(): int
    {
        return $this->cooldownMinutesOverride ?? (int) config('question_bank_profiles.worker.dry_alert.cooldown_minutes', 30);
    }
}
