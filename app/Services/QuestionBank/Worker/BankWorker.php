<?php

namespace App\Services\QuestionBank\Worker;

use App\Services\QuestionBank\QuestionBankRepository;
use Illuminate\Support\Facades\DB;
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
        $rawAcquire = Redis::set($semaphoreKey, $ownerToken, 'EX', 300, 'NX');
        // Normalise the assorted return shapes:
        //   - phpredis  : true / false
        //   - predis    : Predis\Response\Status object whose __toString() == "OK", or null
        //   - raw redis : "OK" / null / 1 / 0
        $acquired = $rawAcquire === true
            || $rawAcquire === 1
            || (is_string($rawAcquire) && strcasecmp($rawAcquire, 'OK') === 0)
            || (is_object($rawAcquire) && method_exists($rawAcquire, '__toString')
                && strcasecmp((string) $rawAcquire, 'OK') === 0);
        if (!$acquired) {
            Log::warning('[BankWorker] another instance holds the semaphore — exiting', [
                'key' => $semaphoreKey,
            ]);
            return 0;
        }

        $this->installSignalHandlers();

        // Reset per-session guard stats so the dashboard shows only this run.
        try {
            $sessionKeys = Redis::keys('qb:worker:guard_stats:session:*');
            if (!empty($sessionKeys)) {
                Redis::del(...$sessionKeys);
            }
            Redis::set('qb:worker:guard_stats:session_ts', time());
            Redis::expire('qb:worker:guard_stats:session_ts', 86400 * 7);
        } catch (\Throwable) {}

        $cycles = 0;
        try {
            while (!$this->stopRequested) {
                if ($maxCycles !== null && $cycles >= $maxCycles) {
                    break;
                }
                $cycles++;

                Redis::expire($semaphoreKey, 300); // renew

                // Heartbeat: proves the worker is alive and processing cycles.
                // TTL = 90s — if missing on the monitor, the worker has been stuck
                // for >90s (blocked on a long HTTP call or crashed post-SIGKILL).
                Redis::set('qb:worker:heartbeat', time(), 'EX', 90);

                $deficits = $this->needs->computeDeficits(limit: 20);
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

                // Select the first non-cooled-down segment from the top-20 list.
                $segment = null;
                foreach ($deficits as $candidate) {
                    if (!$this->isSegmentCoolingDown($candidate)) {
                        $segment = $candidate;
                        // P4 — worker-safe noyau lock.
                        // Enrich the segment with a concept_hint when a QuestionIntent
                        // with noyau metadata (subject + angle_large) exists for this
                        // sub_domain. Fail-open: returns '' when no enriched intent is
                        // found so the worker behaves exactly as before for unlocked slots.
                        $segment['concept_hint'] = $this->resolveConceptHint($segment);
                        break;
                    }
                    Log::info('[BankWorker] segment skipped — cooldown active', [
                        'sub_domain'     => $candidate['sub_domain'] ?? null,
                        'cognitive_type' => $candidate['cognitive_type'] ?? null,
                        'language'       => $candidate['language'] ?? null,
                    ]);
                }

                if ($segment === null) {
                    Log::info('[BankWorker] all top-20 segments in cooldown — idle sleep', [
                        'sleep' => (int) $config['idle_sleep_seconds'],
                    ]);
                    if ($onCycle) {
                        $onCycle(['action' => 'idle_all_cooled', 'cycles' => $cycles]);
                    }
                    $this->sleepInterruptible((int) $config['idle_sleep_seconds']);
                    continue;
                }

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
                    $this->recordSegmentReject($segment);
                    continue; // cooldown will engage if threshold reached
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
                $this->resetSegmentRejectCount($segment);

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

        // Per-guard cumulative counters for KPI-5 dashboard.
        // Key pattern: qb:worker:guard_stats:{code}
        // Session key (reset each worker start via qb:worker:guard_stats:session_ts):
        //   qb:worker:guard_stats:session:{code}
        $allTimeKey  = 'qb:worker:guard_stats:' . $code;
        $sessionKey  = 'qb:worker:guard_stats:session:' . $code;
        Redis::incr($allTimeKey);
        Redis::expire($allTimeKey, 86400 * 30);
        Redis::incr($sessionKey);
        Redis::expire($sessionKey, 86400 * 7);

        // Total reject counter (all codes combined)
        Redis::incr('qb:worker:guard_stats:_total');
        Redis::expire('qb:worker:guard_stats:_total', 86400 * 30);
        Redis::incr('qb:worker:guard_stats:session:_total');
        Redis::expire('qb:worker:guard_stats:session:_total', 86400 * 7);
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

    // ── Segment cooldown helpers ───────────────────────────────────────────────

    /**
     * Stable Redis key fragment for a segment — deterministic md5 over
     * the 5 fields that uniquely identify a segment for the worker.
     */
    private function segmentKey(array $segment): string
    {
        return md5(sprintf(
            '%s|%s|%s|%d|%s',
            $segment['mode']            ?? '',
            $segment['sub_domain']      ?? '',
            $segment['cognitive_type']  ?? '',
            (int) ($segment['depth_range'][1] ?? 0),
            $segment['language']        ?? ''
        ));
    }

    /**
     * Returns true when a cooldown key exists in Redis for this segment.
     * Fail-open: if Redis is unavailable the worker continues normally.
     */
    private function isSegmentCoolingDown(array $segment): bool
    {
        try {
            $pattern = (string) config('question_bank_profiles.worker.redis_keys.seg_cooldown');
            $key     = sprintf($pattern, $this->segmentKey($segment));
            return (bool) Redis::exists($key);
        } catch (\Throwable $e) {
            Log::warning('[BankWorker] cooldown check failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
            return false; // fail-open — never block the worker on a Redis error
        }
    }

    /**
     * Increments the per-segment consecutive-reject counter.
     * When the counter reaches seg_reject_threshold, sets the cooldown key
     * (TTL = seg_cooldown_seconds) so the segment is skipped for 30 minutes.
     * Guards are NOT modified — the rejection still happened; we just stop
     * hammering the same segment until the LLM can produce something new.
     */
    private function recordSegmentReject(array $segment): void
    {
        $config    = config('question_bank_profiles.worker');
        $threshold = (int) ($config['seg_reject_threshold'] ?? 10);
        $cooldown  = (int) ($config['seg_cooldown_seconds'] ?? 1800);
        $hash      = $this->segmentKey($segment);

        try {
            $countKey    = sprintf((string) $config['redis_keys']['seg_reject_count'], $hash);
            $cooldownKey = sprintf((string) $config['redis_keys']['seg_cooldown'],     $hash);

            $count = (int) Redis::incr($countKey);
            Redis::expire($countKey, $cooldown + 60); // auto-clean after cooldown ends

            Log::info('[BankWorker] segment reject count', [
                'sub_domain'     => $segment['sub_domain']     ?? null,
                'cognitive_type' => $segment['cognitive_type'] ?? null,
                'language'       => $segment['language']       ?? null,
                'count'          => $count,
                'threshold'      => $threshold,
            ]);

            if ($count >= $threshold) {
                Redis::set($cooldownKey, time(), 'EX', $cooldown);
                Log::warning('[BankWorker] segment cooldown started', [
                    'sub_domain'       => $segment['sub_domain']     ?? null,
                    'cognitive_type'   => $segment['cognitive_type'] ?? null,
                    'language'         => $segment['language']       ?? null,
                    'cooldown_seconds' => $cooldown,
                    'reject_count'     => $count,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[BankWorker] recordSegmentReject failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * P4 — Worker-safe noyau lock.
     *
     * Attempts to find a QuestionIntent with full noyau metadata
     * (subject + angle_large at minimum) matching this segment's sub_domain,
     * and builds the same concept_hint string that the dialyse command would
     * produce. Injected into the segment before it reaches BankAIGenerator so
     * the AI is steered toward a specific semantic nucleus rather than being
     * free to pick any fact in the broad sub_domain.
     *
     * Selection strategy:
     *   1. Must match sub_domain and have subject + angle_large populated.
     *   2. Prefer intents whose difficulty_depth matches the segment's depth
     *      (depth_range[1]); fall back to any matching intent if none is found.
     *   3. Pick randomly among candidates so the worker rotates across noyaux
     *      rather than hammering the same one every cycle.
     *
     * Fail-open: returns '' on any exception or when no enriched intent exists.
     * A '' concept_hint leaves the prompt identical to the pre-P4 behaviour
     * ("Choisis un fait précis et vérifiable") — no cycle is ever blocked.
     *
     * Coverage today: ~10 intents carry noyau data (the 10 dialyse noyaux).
     * As the question_intents table is enriched with subject/angle metadata for
     * more noyaux, the worker automatically benefits without further code changes.
     */
    private function resolveConceptHint(array $segment): string
    {
        try {
            $subDomain = (string) ($segment['sub_domain'] ?? '');
            $depth     = (int)   ($segment['depth_range'][1] ?? 0);

            if ($subDomain === '') {
                return '';
            }

            // Try depth-matched intent first, then any enriched intent.
            $base = DB::table('question_intents')
                ->where('sub_domain', $subDomain)
                ->whereNotNull('subject')
                ->where('subject', '!=', '')
                ->whereNotNull('angle_large')
                ->where('angle_large', '!=', '');

            $intent = (clone $base)
                ->where('difficulty_depth', $depth)
                ->inRandomOrder()
                ->first(['subject', 'angle_large', 'micro_angle', 'answer_target', 'semantic_key']);

            if ($intent === null) {
                $intent = $base
                    ->inRandomOrder()
                    ->first(['subject', 'angle_large', 'micro_angle', 'answer_target', 'semantic_key']);
            }

            if ($intent === null) {
                return '';
            }

            $parts = array_filter([
                $intent->subject       !== '' ? 'Sujet: '       . trim((string) $intent->subject)       : null,
                $intent->angle_large   !== '' ? 'Angle: '       . trim((string) $intent->angle_large)   : null,
                ($intent->micro_angle  ?? '') !== '' ? 'Micro-angle: ' . trim((string) $intent->micro_angle)   : null,
                ($intent->answer_target ?? '') !== '' ? 'Cible: '       . trim((string) $intent->answer_target) : null,
            ]);

            if (empty($parts)) {
                return '';
            }

            $hint = implode('. ', $parts) . '.';

            $sk = trim((string) ($intent->semantic_key ?? ''));
            if ($sk !== '') {
                $hint .= " Reste STRICTEMENT dans ce noyau ({$sk}) — toute dérive vers un autre sous-thème est interdite.";
            }

            return $hint;

        } catch (\Throwable $e) {
            Log::warning('[BankWorker] resolveConceptHint failed (non-fatal)', [
                'sub_domain' => $segment['sub_domain'] ?? null,
                'error'      => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Clears the reject counter and cooldown key after a successful insertion.
     * The segment is immediately eligible again after it produces a good question.
     */
    private function resetSegmentRejectCount(array $segment): void
    {
        $config = config('question_bank_profiles.worker');
        $hash   = $this->segmentKey($segment);

        try {
            $countKey    = sprintf((string) $config['redis_keys']['seg_reject_count'], $hash);
            $cooldownKey = sprintf((string) $config['redis_keys']['seg_cooldown'],     $hash);
            Redis::del($countKey, $cooldownKey);
        } catch (\Throwable $e) {
            Log::warning('[BankWorker] resetSegmentRejectCount failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
