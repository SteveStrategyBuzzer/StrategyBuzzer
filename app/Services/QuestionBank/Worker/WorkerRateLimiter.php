<?php

namespace App\Services\QuestionBank\Worker;

use Illuminate\Support\Facades\Redis;

/**
 * Tiny token-bucket rate limiter backed by Redis. One bucket per
 * minute window — INCR + EXPIRE — so the global rate stays steady
 * even across multiple instances of the worker.
 *
 * The worker calls `acquireOrSleep()` before every IA call. If the
 * current window is full, the call blocks until the next minute, so
 * the rhythm stays constant by design.
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
        if ($this->ratePerMinute <= 0) {
            // Pause mode — sleep one minute then retry.
            sleep(60);
            $this->acquireOrSleep();
            return;
        }

        $tries = 0;
        while ($tries++ < 3) {
            $minute = (int) floor(time() / 60);
            $key = sprintf($this->bucketKeyPattern, $minute);

            $count = (int) Redis::incr($key);
            if ($count === 1) {
                // First incr in this window — set TTL to ensure cleanup.
                Redis::expire($key, 65);
            }
            if ($count <= $this->ratePerMinute) {
                return; // slot acquired
            }

            // Sleep until next minute boundary.
            $now = time();
            $sleep = 60 - ($now % 60);
            sleep(max(1, $sleep));
        }
    }
}
