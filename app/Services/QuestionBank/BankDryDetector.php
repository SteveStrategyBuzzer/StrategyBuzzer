<?php

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * #92 — Bank-dry detector.
 *
 * After #88 the live-match path is bank → cache → seed. There is no AI
 * fallback any more. When the bank AND the cache are both empty for a
 * segment, gameplay falls back to the embedded seed pool, and when the
 * seed pool is itself dry the run-time throws. Both situations are
 * silent today: no metric, no alert, no visibility for Ops.
 *
 * This service plugs ONLY a metric + a structured log into those two
 * code paths. It NEVER triggers the worker, NEVER calls an AI provider,
 * NEVER mutates question state. All Redis writes are best-effort and
 * exception-swallowing so a Redis blip can never break gameplay.
 *
 *   recordFallbackUsed() → DEGRADED   (Log::warning)
 *   recordTotalDry()      → CRITICAL  (Log::critical)
 *
 * snapshot() returns the rolling 1h figures + last_event for the
 * health endpoint at GET /api/admin/questions/health.
 */
class BankDryDetector
{
    public const SEVERITY_OK = 'ok';
    public const SEVERITY_DEGRADED = 'degraded';
    public const SEVERITY_CRITICAL = 'critical';

    // cache_status values reported in the segment payload so Ops can tell
    // a true cache exhaustion apart from a deliberately-bypassed cache.
    public const CACHE_STATUS_MISS = 'miss';            // Cache consulted, returned nothing.
    public const CACHE_STATUS_SKIPPED_BOSS = 'skipped:boss';     // Boss levels never consult cache by design.
    public const CACHE_STATUS_SKIPPED_EXPLICIT = 'skipped:explicit'; // Caller passed skipCache=true.
    public const CACHE_STATUS_UNKNOWN = 'unknown';      // Detector called from a path that didn't track cache state.

    private const KEY_TTL_SECONDS = 7200;
    private const ROLLING_WINDOW_MINUTES = 60;
    // last_event auto-expires after 24h so a stale incident can't keep
    // showing on the dashboard once severity has returned to OK.
    private const LAST_EVENT_TTL_SECONDS = 86400;

    /**
     * Bank + cache returned nothing for this segment; gameplay served a
     * question from the embedded seed pool. The match continues, but the
     * worker is behind on this profile and Ops should know.
     */
    public function recordFallbackUsed(
        string $theme,
        int $niveau,
        string $language,
        bool $isBoss,
        string $context = 'solo',
        string $cacheStatus = self::CACHE_STATUS_UNKNOWN
    ): void {
        $segment = $this->segment($theme, $niveau, $language, $isBoss, $context, $cacheStatus);

        Log::warning('[QuestionBank] DRY DEGRADED — bank empty, served from seed pool', $segment);

        $this->safeRedis(function () use ($segment) {
            $minute = (int) floor(time() / 60);
            $key = sprintf(
                config('question_bank_profiles.worker.redis_keys.dry_fallback_counter'),
                $minute
            );
            Redis::incr($key);
            Redis::expire($key, self::KEY_TTL_SECONDS);
            $lastEventKey = config('question_bank_profiles.worker.redis_keys.dry_last_event');
            Redis::set(
                $lastEventKey,
                json_encode([
                    'severity' => self::SEVERITY_DEGRADED,
                    'segment' => $segment,
                    'at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE)
            );
            Redis::expire($lastEventKey, self::LAST_EVENT_TTL_SECONDS);
        });
    }

    /**
     * Bank + cache + seed pool ALL returned nothing for this segment. The
     * match cannot start. This is a CRITICAL condition: the worker is
     * either down or has never produced anything for this language.
     */
    public function recordTotalDry(
        string $theme,
        int $niveau,
        string $language,
        bool $isBoss,
        string $context = 'solo',
        string $cacheStatus = self::CACHE_STATUS_UNKNOWN
    ): void {
        $segment = $this->segment($theme, $niveau, $language, $isBoss, $context, $cacheStatus);

        Log::critical('[QuestionBank] DRY CRITICAL — bank+cache+seed all empty', $segment);

        $this->safeRedis(function () use ($segment) {
            $minute = (int) floor(time() / 60);
            $key = sprintf(
                config('question_bank_profiles.worker.redis_keys.dry_total_counter'),
                $minute
            );
            Redis::incr($key);
            Redis::expire($key, self::KEY_TTL_SECONDS);
            $lastEventKey = config('question_bank_profiles.worker.redis_keys.dry_last_event');
            Redis::set(
                $lastEventKey,
                json_encode([
                    'severity' => self::SEVERITY_CRITICAL,
                    'segment' => $segment,
                    'at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE)
            );
            Redis::expire($lastEventKey, self::LAST_EVENT_TTL_SECONDS);
        });
    }

    /**
     * Read-side snapshot for the health endpoint. Sums the last 60 1-minute
     * counters into a rolling 1h figure, decodes the last_event blob, and
     * derives a single severity from the numbers.
     *
     * @return array{
     *   fallback_used_1h: int,
     *   total_dry_1h: int,
     *   last_event: ?array,
     *   severity: string,
     * }
     */
    public function snapshot(): array
    {
        $fallback1h = 0;
        $total1h = 0;
        $lastEvent = null;

        try {
            $minute = (int) floor(time() / 60);
            $fallbackPattern = config('question_bank_profiles.worker.redis_keys.dry_fallback_counter');
            $totalPattern = config('question_bank_profiles.worker.redis_keys.dry_total_counter');
            for ($i = 0; $i < self::ROLLING_WINDOW_MINUTES; $i++) {
                $fallback1h += (int) Redis::get(sprintf($fallbackPattern, $minute - $i));
                $total1h += (int) Redis::get(sprintf($totalPattern, $minute - $i));
            }
            $rawLast = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
            if (is_string($rawLast) && $rawLast !== '') {
                $decoded = json_decode($rawLast, true);
                if (is_array($decoded)) {
                    $lastEvent = $decoded;
                }
            }
        } catch (Throwable $e) {
            Log::warning('[BankDryDetector] snapshot read failed', ['error' => $e->getMessage()]);
        }

        if ($total1h > 0) {
            $severity = self::SEVERITY_CRITICAL;
        } elseif ($fallback1h > 0) {
            $severity = self::SEVERITY_DEGRADED;
        } else {
            $severity = self::SEVERITY_OK;
        }

        return [
            'fallback_used_1h' => $fallback1h,
            'total_dry_1h' => $total1h,
            'last_event' => $lastEvent,
            'severity' => $severity,
        ];
    }

    private function segment(string $theme, int $niveau, string $language, bool $isBoss, string $context, string $cacheStatus): array
    {
        return [
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => $language,
            'is_boss' => $isBoss,
            'context' => $context,
            'cache_status' => $cacheStatus,
        ];
    }

    private function safeRedis(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Log::warning('[BankDryDetector] redis write failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
