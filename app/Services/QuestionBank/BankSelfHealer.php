<?php

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Auto-remediation hook fired on bank-dry CRITICAL threshold breaches.
 *
 * The healer runs three mechanical actions whose effect is bounded by a
 * TTL so a misfire cannot make things worse than a manual nudge:
 *   1. Bumps the worker rate-limit override (Redis JSON, TTL = boost
 *      window). The WorkerRateLimiter reads it and uses
 *      max(default_rate, override_rate) while it is active.
 *   2. Force-flushes the current minute's rate bucket so the next
 *      generation attempt is not blocked by the previous window.
 *   3. Pins a "priority segment" key (Redis JSON, TTL = boost window).
 *      BankNeedsCalculator prepends matching deficit rows so the worker
 *      attacks the affected segment first.
 *
 * All side-effects are wrapped in try/catch — a Redis blip must never
 * propagate to gameplay. The full action descriptor (segment, actions,
 * errors, outcome, expiry) is persisted under
 * `qb:dry:last_self_heal` so the health endpoint can surface "what we
 * did + when". Subsequent invocations within an active boost window are
 * short-circuited (`outcome=skipped_active_boost`) so a sustained
 * outage does not produce a flood of redundant nudges.
 *
 * Gated behind `QB_DRY_AUTOREMEDIATE_ENABLED` (default off) so dry-runs
 * are safe.
 */
class BankSelfHealer
{
    public function __construct(
        private readonly ?bool $enabledOverride = null,
        private readonly ?int $boostMinutesOverride = null,
        private readonly ?int $boostRatePerMinuteOverride = null,
    ) {
    }

    /**
     * Attempt to self-heal. Returns the action descriptor (always an
     * array when the hook ran, even on partial / skipped outcomes), or
     * null when auto-remediation is disabled by configuration.
     *
     * @param  array  $segment  same shape produced by BankDryDetector::segment()
     */
    public function attempt(array $segment): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        $boostMinutes = max(1, $this->boostMinutes());
        $boostRate = max(1, $this->boostRatePerMinute());
        $boostSeconds = $boostMinutes * 60;
        $until = time() + $boostSeconds;

        // Idempotency: if a previous self-heal is still in effect we
        // skip the side-effects (the override TTL is already protecting
        // throughput) and return a descriptor with outcome=skipped so
        // the alert payload still mentions what is currently active.
        $activeBoost = $this->readRateOverride();
        if ($activeBoost !== null && (int) ($activeBoost['until_ts'] ?? 0) > time()) {
            $descriptor = [
                'enabled' => true,
                'at' => now()->toIso8601String(),
                'ts' => time(),
                'segment' => $segment,
                'actions' => [],
                'errors' => [],
                'outcome' => 'skipped_active_boost',
                'active_boost' => $activeBoost,
                'boost_minutes' => $boostMinutes,
                'boost_rate_per_minute' => $boostRate,
            ];
            $this->persistLastAction($descriptor);
            Log::info('[BankSelfHealer] skipped — active boost still in effect', [
                'segment' => $segment,
                'active_until' => $activeBoost['until_ts'] ?? null,
            ]);
            return $descriptor;
        }

        $actions = [];
        $errors = [];

        // 1. Rate-limit override (TTL == boost window).
        try {
            $key = (string) config('question_bank_profiles.worker.redis_keys.rate_override');
            $body = json_encode([
                'rate_per_minute' => $boostRate,
                'until_ts' => $until,
                'set_at_ts' => time(),
                'reason' => 'auto_remediate:critical_dry',
            ], JSON_UNESCAPED_UNICODE);
            Redis::set($key, $body, 'EX', $boostSeconds);
            $actions[] = [
                'type' => 'rate_boost',
                'rate_per_minute' => $boostRate,
                'duration_minutes' => $boostMinutes,
                'until_ts' => $until,
            ];
        } catch (Throwable $e) {
            $errors[] = ['type' => 'rate_boost', 'error' => $e->getMessage()];
            Log::warning('[BankSelfHealer] rate boost failed', ['error' => $e->getMessage()]);
        }

        // 2. Flush the current minute bucket so the next acquire is not
        //    artificially blocked by the previous (saturated) window.
        try {
            $minute = (int) floor(time() / 60);
            $bucketPattern = (string) config('question_bank_profiles.worker.redis_keys.rate_bucket');
            $bucketKey = sprintf($bucketPattern, $minute);
            Redis::del($bucketKey);
            $actions[] = [
                'type' => 'rate_bucket_flushed',
                'bucket' => $bucketKey,
            ];
        } catch (Throwable $e) {
            $errors[] = ['type' => 'rate_bucket_flush', 'error' => $e->getMessage()];
            Log::warning('[BankSelfHealer] rate bucket flush failed', ['error' => $e->getMessage()]);
        }

        // 3. Priority segment hint (TTL == boost window). The needs
        //    calculator prepends matching deficit rows so the worker
        //    attacks this segment first.
        try {
            $priorityKey = (string) config('question_bank_profiles.worker.redis_keys.priority_segment');
            $priorityBody = json_encode([
                'segment' => $segment,
                'set_at_ts' => time(),
                'until_ts' => $until,
            ], JSON_UNESCAPED_UNICODE);
            Redis::set($priorityKey, $priorityBody, 'EX', $boostSeconds);
            $actions[] = [
                'type' => 'priority_segment_enqueued',
                'segment_label' => $segment['label'] ?? null,
                'until_ts' => $until,
            ];
        } catch (Throwable $e) {
            $errors[] = ['type' => 'priority_segment', 'error' => $e->getMessage()];
            Log::warning('[BankSelfHealer] priority segment enqueue failed', ['error' => $e->getMessage()]);
        }

        $outcome = empty($errors)
            ? (empty($actions) ? 'noop' : 'success')
            : (empty($actions) ? 'failed' : 'partial');

        $descriptor = [
            'enabled' => true,
            'at' => now()->toIso8601String(),
            'ts' => time(),
            'segment' => $segment,
            'actions' => $actions,
            'errors' => $errors,
            'outcome' => $outcome,
            'boost_minutes' => $boostMinutes,
            'boost_rate_per_minute' => $boostRate,
            'until_ts' => $until,
        ];

        $this->persistLastAction($descriptor);

        Log::info('[BankSelfHealer] auto-remediation applied', [
            'segment' => $segment,
            'outcome' => $outcome,
            'actions' => array_column($actions, 'type'),
        ]);

        return $descriptor;
    }

    /**
     * Read the most recent self-heal action descriptor (or null).
     *
     * @return array|null
     */
    public function lastActionSnapshot(): ?array
    {
        try {
            $raw = Redis::get((string) config('question_bank_profiles.worker.redis_keys.dry_last_self_heal'));
            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            Log::warning('[BankSelfHealer] last action snapshot read failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @return array{
     *   enabled:bool, boost_minutes:int, boost_rate_per_minute:int,
     *   active_boost:?array,
     * }
     */
    public function configSnapshot(): array
    {
        return [
            'enabled' => $this->enabled(),
            'boost_minutes' => $this->boostMinutes(),
            'boost_rate_per_minute' => $this->boostRatePerMinute(),
            'active_boost' => $this->readRateOverride(),
        ];
    }

    private function readRateOverride(): ?array
    {
        try {
            $raw = Redis::get((string) config('question_bank_profiles.worker.redis_keys.rate_override'));
            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function persistLastAction(array $descriptor): void
    {
        try {
            $key = (string) config('question_bank_profiles.worker.redis_keys.dry_last_self_heal');
            Redis::set($key, json_encode($descriptor, JSON_UNESCAPED_UNICODE));
            Redis::expire($key, 86400);
        } catch (Throwable $e) {
            Log::warning('[BankSelfHealer] persist last action failed', ['error' => $e->getMessage()]);
        }
    }

    private function enabled(): bool
    {
        if ($this->enabledOverride !== null) {
            return $this->enabledOverride;
        }
        return (bool) config('question_bank_profiles.worker.dry_autoremediate.enabled', false);
    }

    private function boostMinutes(): int
    {
        return $this->boostMinutesOverride ?? (int) config('question_bank_profiles.worker.dry_autoremediate.boost_minutes', 10);
    }

    private function boostRatePerMinute(): int
    {
        return $this->boostRatePerMinuteOverride ?? (int) config('question_bank_profiles.worker.dry_autoremediate.boost_rate_per_minute', 30);
    }
}
