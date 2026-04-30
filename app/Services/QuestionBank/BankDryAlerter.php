<?php

namespace App\Services\QuestionBank;

use App\Mail\BankDryAlertMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Proactive ops alerter for bank-dry CRITICAL events. Posts to Slack,
 * email and/or PagerDuty once the rolling CRITICAL count over a
 * configurable window strictly exceeds the configured threshold, then
 * enters a cooldown so a sustained outage produces one alert per
 * cooldown window.
 *
 * PagerDuty integration uses Events API v2: the alerter opens an
 * incident with a stable dedup_key on threshold breach and resolves it
 * once the rolling CRITICAL count over the same window falls back to 0
 * (auto-recovery, no manual ack needed).
 *
 * Never propagates exceptions — gameplay is the priority.
 */
class BankDryAlerter
{
    private const PAGERDUTY_DEFAULT_ENDPOINT = 'https://events.pagerduty.com/v2/enqueue';
    private const PAGERDUTY_DEDUP_PREFIX = 'qb-bank-dry';
    private const PAGERDUTY_OPEN_TTL_SECONDS = 86400 * 7;

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
            $pagerDutyKey = $this->pagerDutyRoutingKey();

            // No destination wired: log a critical line so the signal
            // still reaches the log aggregator, and do NOT bump the
            // cooldown — the next CRITICAL event after Ops configures
            // a destination must be able to fire immediately.
            if ($slackUrl === '' && $email === '' && $pagerDutyKey === '') {
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
            if ($pagerDutyKey !== '') {
                if ($this->sendPagerDutyTrigger($pagerDutyKey, $payload)) {
                    $this->markPagerDutyOpen($this->pagerDutyDedupKey());
                    $delivered = true;
                }
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
     * Auto-resolve an open PagerDuty incident once the rolling CRITICAL
     * count over the alert window has returned to 0. Safe to call on
     * every health snapshot — it is a cheap no-op when PagerDuty is
     * not configured or no incident is open.
     */
    public function maybeResolve(): void
    {
        try {
            $routingKey = $this->pagerDutyRoutingKey();
            if ($routingKey === '') {
                return;
            }
            if (!$this->isPagerDutyOpen()) {
                return;
            }

            $windowMinutes = $this->windowMinutes();
            if ($windowMinutes <= 0) {
                return;
            }

            $count = $this->rollingCriticalCount($windowMinutes);
            if ($count > 0) {
                return;
            }

            if ($this->sendPagerDutyResolve($routingKey)) {
                $this->clearPagerDutyOpen();
            }
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] maybeResolve failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *   threshold:int, window_minutes:int, cooldown_minutes:int,
     *   slack_configured:bool, email_configured:bool, pagerduty_configured:bool,
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
            'pagerduty_configured' => $this->pagerDutyRoutingKey() !== '',
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

    private function sendPagerDutyTrigger(string $routingKey, array $payload): bool
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
            $body = [
                'routing_key' => $routingKey,
                'event_action' => 'trigger',
                'dedup_key' => $this->pagerDutyDedupKey(),
                'payload' => [
                    'summary' => sprintf(
                        'Question bank DRY — %d CRITICAL events in last %d min (threshold=%d) on %s',
                        $payload['count'],
                        $payload['window_minutes'],
                        $payload['threshold'],
                        $payload['environment']
                    ),
                    'source' => 'question-bank',
                    'severity' => 'critical',
                    'component' => 'question-bank',
                    'group' => (string) $payload['environment'],
                    'class' => 'bank-dry',
                    'custom_details' => [
                        'segment_label' => $segmentLabel,
                        'segment' => $segment,
                        'count' => $payload['count'],
                        'window_minutes' => $payload['window_minutes'],
                        'threshold' => $payload['threshold'],
                        'environment' => $payload['environment'],
                        'at' => $payload['at'] ?? null,
                    ],
                ],
            ];
            $response = Http::timeout(5)->post($this->pagerDutyEndpoint(), $body);
            if ($response->successful()) {
                return true;
            }
            Log::warning('[BankDryAlerter] PagerDuty trigger returned non-2xx', [
                'status' => $response->status(),
                'body_preview' => substr((string) $response->body(), 0, 200),
            ]);
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] PagerDuty trigger POST failed', [
                'error' => $e->getMessage(),
            ]);
        }
        return false;
    }

    private function sendPagerDutyResolve(string $routingKey): bool
    {
        try {
            $body = [
                'routing_key' => $routingKey,
                'event_action' => 'resolve',
                'dedup_key' => $this->pagerDutyDedupKey(),
            ];
            $response = Http::timeout(5)->post($this->pagerDutyEndpoint(), $body);
            if ($response->successful()) {
                return true;
            }
            Log::warning('[BankDryAlerter] PagerDuty resolve returned non-2xx', [
                'status' => $response->status(),
                'body_preview' => substr((string) $response->body(), 0, 200),
            ]);
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] PagerDuty resolve POST failed', [
                'error' => $e->getMessage(),
            ]);
        }
        return false;
    }

    private function isPagerDutyOpen(): bool
    {
        try {
            $key = config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open');
            $raw = Redis::get($key);
            return is_string($raw) && $raw !== '';
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] pagerduty open read failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function markPagerDutyOpen(string $dedupKey): void
    {
        try {
            $key = config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open');
            $body = json_encode([
                'dedup_key' => $dedupKey,
                'ts' => time(),
                'at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE);
            Redis::set($key, $body);
            Redis::expire($key, self::PAGERDUTY_OPEN_TTL_SECONDS);
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] markPagerDutyOpen failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function clearPagerDutyOpen(): void
    {
        try {
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open'));
        } catch (Throwable $e) {
            Log::warning('[BankDryAlerter] clearPagerDutyOpen failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function pagerDutyRoutingKey(): string
    {
        return (string) config('question_bank_profiles.worker.dry_alert.pagerduty_routing_key', '');
    }

    private function pagerDutyEndpoint(): string
    {
        $endpoint = (string) config('question_bank_profiles.worker.dry_alert.pagerduty_endpoint', '');
        return $endpoint !== '' ? $endpoint : self::PAGERDUTY_DEFAULT_ENDPOINT;
    }

    private function pagerDutyDedupKey(): string
    {
        $env = (string) config('question_bank_profiles.worker.dry_alert.environment_label', 'unknown');
        return self::PAGERDUTY_DEDUP_PREFIX . ':' . ($env !== '' ? $env : 'unknown');
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
