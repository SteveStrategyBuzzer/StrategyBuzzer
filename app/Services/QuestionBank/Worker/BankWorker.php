<?php

namespace App\Services\QuestionBank\Worker;

use App\Services\QuestionBank\QuestionBankRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * The continuous bank refill loop.
 *
 * Cycle (no traffic-driven trigger anywhere):
 *   1. Recompute deficits via BankNeedsCalculator
 *   2. Take the highest-deficit segment
 *   3. Acquire a rate-limit token (sleeps if window full)
 *   4. Call BankAIGenerator
 *   5. Hand the candidate to QualityGuards
 *   6. On success → addToBank(); on rejection → log + back-off
 *   7. Apply exponential back-off on upstream errors
 *   8. When nothing is missing → idle sleep
 *
 * A Redis semaphore prevents concurrent runs; SIGTERM stops the loop
 * cleanly between segments.
 */
class BankWorker
{
    private bool $stopRequested = false;
    private int $backoffSeconds;

    public function __construct(
        private readonly BankNeedsCalculator $needs,
        private readonly BankAIGenerator $generator,
        private readonly QualityGuards $guards,
        private readonly QuestionBankRepository $repo,
        private readonly WorkerRateLimiter $rateLimiter
    ) {
        $this->backoffSeconds = (int) config('question_bank_profiles.worker.backoff_initial_seconds', 5);
    }

    /**
     * Main loop. Returns the number of cycles executed (useful for the
     * --once flag in tests / smoke runs).
     */
    public function run(?int $maxCycles = null, bool $dryRun = false, ?\Closure $onCycle = null): int
    {
        $config = config('question_bank_profiles.worker');
        $semaphoreKey = $config['redis_keys']['semaphore'];

        // Atomic semaphore: SET key value NX EX 300 in one call so we can't
        // crash between SETNX and EXPIRE. We also store a unique owner token
        // (host + pid + random) so the unlock path only deletes the lock if
        // we still own it (avoids "old worker deletes new worker's lock"
        // after TTL expiry).
        $ownerToken = (gethostname() ?: 'worker') . ':' . getmypid() . ':' . bin2hex(random_bytes(6));
        $acquired = Redis::set($semaphoreKey, $ownerToken, 'EX', 300, 'NX');
        // predis returns the string 'OK' on success and null on collision;
        // phpredis returns true / false. Normalise.
        $acquired = $acquired === true || $acquired === 'OK' || $acquired === 1;
        if (!$acquired) {
            Log::warning('[BankWorker] another instance holds the semaphore — exiting', [
                'key' => $semaphoreKey,
            ]);
            return 0;
        }

        $this->installSignalHandlers();

        $cycles = 0;
        try {
            while (!$this->stopRequested) {
                if ($maxCycles !== null && $cycles >= $maxCycles) {
                    break;
                }
                $cycles++;

                Redis::expire($semaphoreKey, 300); // renew

                $deficits = $this->needs->computeDeficits(limit: 1);
                if (empty($deficits)) {
                    Log::info('[BankWorker] bank fully covered — idle sleep', [
                        'sleep' => (int) $config['idle_sleep_seconds'],
                    ]);
                    if ($onCycle) {
                        $onCycle(['action' => 'idle', 'cycles' => $cycles]);
                    }
                    $this->sleepInterruptible((int) $config['idle_sleep_seconds']);
                    continue;
                }

                $segment = $deficits[0];

                if ($dryRun) {
                    Log::info('[BankWorker] dry-run, would generate segment', $segment);
                    if ($onCycle) {
                        $onCycle(['action' => 'dry_run', 'segment' => $segment, 'cycles' => $cycles]);
                    }
                    break;
                }

                $this->rateLimiter->acquireOrSleep();

                $result = $this->generator->generateForSegment($segment);
                $minuteKey = (int) floor(time() / 60);

                if (!$result['ok']) {
                    Redis::incr(sprintf($config['redis_keys']['gen_counter_err'], $minuteKey));
                    Redis::expire(sprintf($config['redis_keys']['gen_counter_err'], $minuteKey), 7200);
                    $this->recordReject('generator_error', $result['error'] ?? 'unknown', $segment);
                    Log::warning('[BankWorker] generator error → backoff', [
                        'segment' => $segment,
                        'error' => $result['error'] ?? null,
                        'http_status' => $result['http_status'] ?? null,
                        'backoff' => $this->backoffSeconds,
                    ]);
                    if ($onCycle) {
                        $onCycle(['action' => 'gen_error', 'segment' => $segment, 'error' => $result['error'] ?? null, 'cycles' => $cycles]);
                    }
                    $this->sleepInterruptible($this->backoffSeconds);
                    $this->bumpBackoff();
                    continue;
                }

                $eval = $this->guards->evaluate($result['payload']);
                if (!$eval['ok']) {
                    $this->recordReject($eval['code'] ?? 'guard', $eval['detail'] ?? '', $segment);
                    Log::info('[BankWorker] candidate rejected by guards', [
                        'code' => $eval['code'] ?? null,
                        'detail' => $eval['detail'] ?? null,
                        'segment' => $segment,
                    ]);
                    if ($onCycle) {
                        $onCycle(['action' => 'guard_rejected', 'segment' => $segment, 'code' => $eval['code'] ?? null, 'cycles' => $cycles]);
                    }
                    continue; // try next cycle without back-off (different segment likely)
                }

                $group = $this->repo->addToBank($result['payload'], updateExisting: false);
                if ($group === null) {
                    $this->recordReject('insert_skipped', 'duplicate concept_id', $segment);
                    if ($onCycle) {
                        $onCycle(['action' => 'insert_skipped', 'segment' => $segment, 'cycles' => $cycles]);
                    }
                    continue;
                }

                Redis::incr(sprintf($config['redis_keys']['gen_counter_ok'], $minuteKey));
                Redis::expire(sprintf($config['redis_keys']['gen_counter_ok'], $minuteKey), 7200);
                Redis::set(
                    $config['redis_keys']['last_success'],
                    json_encode([
                        'ts' => time(),
                        'group_id' => $group->id,
                        'segment' => $segment,
                    ]),
                    'EX',
                    86400
                );
                $this->resetBackoff();

                Log::info('[BankWorker] inserted group', [
                    'group_id' => $group->id,
                    'segment' => $segment,
                    'validated' => $group->validated,
                ]);

                if ($onCycle) {
                    $onCycle(['action' => 'inserted', 'group_id' => $group->id, 'segment' => $segment, 'cycles' => $cycles]);
                }
            }
        } finally {
            // Owner-checked unlock. Lua script ensures we only delete the
            // lock if it still holds OUR token (the same value we wrote at
            // acquisition).
            try {
                $script = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";
                Redis::eval($script, 1, $semaphoreKey, $ownerToken);
            } catch (\Throwable $e) {
                Log::warning('[BankWorker] unlock script failed', ['error' => $e->getMessage()]);
            }
        }

        return $cycles;
    }

    private function recordReject(string $code, string $detail, array $segment): void
    {
        $key = config('question_bank_profiles.worker.redis_keys.last_rejects');
        $entry = json_encode([
            'ts' => time(),
            'code' => $code,
            'detail' => mb_substr($detail, 0, 200),
            'segment' => [
                'mode' => $segment['mode'] ?? null,
                'sub_domain' => $segment['sub_domain'] ?? null,
                'cognitive_type' => $segment['cognitive_type'] ?? null,
                'language' => $segment['language'] ?? null,
            ],
        ]);
        Redis::lpush($key, $entry);
        Redis::ltrim($key, 0, 24);
        Redis::expire($key, 86400);
    }

    private function bumpBackoff(): void
    {
        $cap = (int) config('question_bank_profiles.worker.backoff_max_seconds', 300);
        $this->backoffSeconds = min($cap, $this->backoffSeconds * 2);
    }

    private function resetBackoff(): void
    {
        $this->backoffSeconds = (int) config('question_bank_profiles.worker.backoff_initial_seconds', 5);
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->stopRequested = true);
        pcntl_signal(SIGINT, fn () => $this->stopRequested = true);
    }

    private function sleepInterruptible(int $seconds): void
    {
        $end = time() + $seconds;
        while (time() < $end && !$this->stopRequested) {
            sleep(1);
        }
    }
}
