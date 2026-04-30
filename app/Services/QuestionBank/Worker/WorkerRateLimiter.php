<?php

namespace App\Services\QuestionBank\Worker;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Tiny token-bucket rate limiter backed by Redis. One bucket per
 * minute window — INCR + EXPIRE — so the global rate stays steady
 * even across multiple instances of the worker.
 *
 * The worker calls `acquireOrSleep()` before every IA call. If the
 * current window is full, the call blocks until the next minute, so
 * the rhythm stays constant by design.
 *
 * Auto-remediation (#99): when the BankSelfHealer writes a
 * `qb:worker:rate_override` JSON blob (TTL = boost window), the limiter
 * uses `max(default_rate, override_rate)` for the duration so a
 * temporary throughput surge can drain a dry segment quickly. The
 * override is read on every acquire so it both kicks in and decays
 * automatically without restarting the worker.
 */
class WorkerRateLimiter
{
    public function __construct(
        private readonly int $ratePerMinute,
        private readonly string $bucketKeyPattern = 'qb:worker:rate:%s'
    ) {}

    /**
     * Atomically reserves a slot in the current window. If the window is
     * full, sleeps until the next window and retries (bounded once).
     */
    public function acquireOrSleep(): void
    {
        $effectiveRate = $this->effectiveRatePerMinute();
        if ($effectiveRate <= 0) {
            // Pause mode — sleep one minute then retry.
            sleep(60);
            $this->acquireOrSleep();
            return;
        }

        $tries = 0;
        while ($tries++ < 3) {
            $effectiveRate = $this->effectiveRatePerMinute();
            $minute = (int) floor(time() / 60);
            $key = sprintf($this->bucketKeyPattern, $minute);

            $count = (int) Redis::incr($key);
            if ($count === 1) {
                // First incr in this window — set TTL to ensure cleanup.
                Redis::expire($key, 65);
            }
            if ($count <= $effectiveRate) {
                return; // slot acquired
            }

            // Sleep until next minute boundary.
            $now = time();
            $sleep = 60 - ($now % 60);
            sleep(max(1, $sleep));
        }
    }

    /**
     * Returns the active per-minute budget. Reads the optional
     * `rate_override` key written by BankSelfHealer and uses
     * max(default, override) while the override is in its TTL window.
     */
    public function effectiveRatePerMinute(): int
    {
        $base = $this->ratePerMinute;
        try {
            $overrideKey = (string) config('question_bank_profiles.worker.redis_keys.rate_override');
            if ($overrideKey === '') {
                return $base;
            }
            $raw = Redis::get($overrideKey);
            if (!is_string($raw) || $raw === '') {
                return $base;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return $base;
            }
            $until = (int) ($decoded['until_ts'] ?? 0);
            if ($until <= time()) {
                return $base;
            }
            $override = (int) ($decoded['rate_per_minute'] ?? 0);
            return $override > $base ? $override : $base;
        } catch (Throwable $e) {
            Log::warning('[WorkerRateLimiter] override read failed (non-fatal)', ['error' => $e->getMessage()]);
            return $base;
        }
    }
}
