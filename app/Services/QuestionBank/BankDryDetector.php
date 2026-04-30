<?php

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Records bank-dry incidents emitted by QuestionService and exposes a
 * read-side snapshot for the admin health endpoint.
 *
 * Pure metric service: no AI calls, no worker triggers, no question
 * mutation. All Redis writes are wrapped so a Redis blip cannot break
 * gameplay.
 */
class BankDryDetector
{
    public const SEVERITY_OK = 'ok';
    public const SEVERITY_DEGRADED = 'degraded';
    public const SEVERITY_CRITICAL = 'critical';

    public const CACHE_STATUS_MISS = 'miss';
    public const CACHE_STATUS_SKIPPED_BOSS = 'skipped:boss';
    public const CACHE_STATUS_SKIPPED_EXPLICIT = 'skipped:explicit';
    public const CACHE_STATUS_UNKNOWN = 'unknown';

    private const KEY_TTL_SECONDS = 7200;
    private const ROLLING_WINDOW_MINUTES = 60;
    private const LAST_EVENT_TTL_SECONDS = 86400;
    private const SEGMENT_TOP_N = 10;

    private BankDryAlerter $alerter;

    public function __construct(?BankDryAlerter $alerter = null)
    {
        $this->alerter = $alerter ?? new BankDryAlerter();
    }

    /**
     * DEGRADED — bank+cache empty, served from the seed pool. Match continues.
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
            $this->incrementMinuteCounter('dry_fallback_counter');
            $this->touchSegment('dry_fallback_segment_counts', 'dry_fallback_segment_seen', $segment);
            $this->writeLastEvent(self::SEVERITY_DEGRADED, $segment);
        });
    }

    /**
     * CRITICAL — bank+cache+seed all empty for this segment. Match cannot
     * start; QuestionService throws right after this call. Triggers the
     * proactive ops alert.
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
            $this->incrementMinuteCounter('dry_total_counter');
            $this->touchSegment('dry_total_segment_counts', 'dry_total_segment_seen', $segment);
            $this->writeLastEvent(self::SEVERITY_CRITICAL, $segment);
            // Critical-only timestamp so a later degraded fallback can't
            // shift the "last critical dry" marker on the dashboard.
            $this->writeLastCriticalEvent($segment);
        });

        $this->alerter->maybeAlert($segment);
    }

    /**
     * Read-side snapshot for GET /api/admin/questions/health.
     *
     * @return array{
     *   fallback_used_1h:int, total_dry_1h:int,
     *   dry_segment_count_1h:int, fallback_segment_count_1h:int,
     *   segments_top_critical:array<int,array{label:string,count:int,last_at:?string}>,
     *   segments_top_degraded:array<int,array{label:string,count:int,last_at:?string}>,
     *   last_event:?array, last_dry_at:?string,
     *   severity:string, alert:array
     * }
     */
    public function snapshot(): array
    {
        $fallback1h = 0;
        $total1h = 0;
        $lastEvent = null;
        $criticalSegments = [];
        $degradedSegments = [];

        $lastCriticalEvent = null;

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

            $rawLastCritical = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_critical_event'));
            if (is_string($rawLastCritical) && $rawLastCritical !== '') {
                $decoded = json_decode($rawLastCritical, true);
                if (is_array($decoded)) {
                    $lastCriticalEvent = $decoded;
                }
            }

            $criticalSegments = $this->readSegments(
                'dry_total_segment_counts',
                'dry_total_segment_seen',
                self::ROLLING_WINDOW_MINUTES * 60
            );
            $degradedSegments = $this->readSegments(
                'dry_fallback_segment_counts',
                'dry_fallback_segment_seen',
                self::ROLLING_WINDOW_MINUTES * 60
            );
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
            'dry_segment_count_1h' => count($criticalSegments),
            'fallback_segment_count_1h' => count($degradedSegments),
            'segments_top_critical' => array_slice($criticalSegments, 0, self::SEGMENT_TOP_N),
            'segments_top_degraded' => array_slice($degradedSegments, 0, self::SEGMENT_TOP_N),
            'last_event' => $lastEvent,
            // Critical-only timestamp; never overwritten by degraded events.
            'last_dry_at' => $lastCriticalEvent['at'] ?? null,
            'last_critical_event' => $lastCriticalEvent,
            'severity' => $severity,
            'alert' => $this->alerter->configSnapshot(),
        ];
    }

    /**
     * Per-segment label used as Redis hash field. Stable string so Ops
     * can grep historical logs / metrics for the same segment over time.
     */
    public static function segmentLabel(string $theme, int $niveau, string $language, bool $isBoss): string
    {
        return sprintf('%s|%d|%s|%s', $theme, $niveau, $language, $isBoss ? 'boss' : 'std');
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
            'label' => self::segmentLabel($theme, $niveau, $language, $isBoss),
        ];
    }

    private function incrementMinuteCounter(string $configKey): void
    {
        $minute = (int) floor(time() / 60);
        $key = sprintf(
            config('question_bank_profiles.worker.redis_keys.' . $configKey),
            $minute
        );
        Redis::incr($key);
        Redis::expire($key, self::KEY_TTL_SECONDS);
    }

    private function touchSegment(string $countsConfigKey, string $seenConfigKey, array $segment): void
    {
        $label = $segment['label'];
        $minute = (int) floor(time() / 60);
        $countsKey = sprintf(
            config('question_bank_profiles.worker.redis_keys.' . $countsConfigKey),
            $minute
        );
        $seenKey = config('question_bank_profiles.worker.redis_keys.' . $seenConfigKey);

        Redis::hincrby($countsKey, $label, 1);
        Redis::expire($countsKey, self::KEY_TTL_SECONDS);

        Redis::zadd($seenKey, time(), $label);
        Redis::expire($seenKey, self::KEY_TTL_SECONDS);
    }

    private function writeLastEvent(string $severity, array $segment): void
    {
        $key = config('question_bank_profiles.worker.redis_keys.dry_last_event');
        Redis::set($key, json_encode([
            'severity' => $severity,
            'segment' => $segment,
            'at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE));
        Redis::expire($key, self::LAST_EVENT_TTL_SECONDS);
    }

    private function writeLastCriticalEvent(array $segment): void
    {
        $key = config('question_bank_profiles.worker.redis_keys.dry_last_critical_event');
        Redis::set($key, json_encode([
            'severity' => self::SEVERITY_CRITICAL,
            'segment' => $segment,
            'at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE));
        Redis::expire($key, self::LAST_EVENT_TTL_SECONDS);
    }

    /**
     * Read the per-segment counts and "last seen" timestamps for events
     * that occurred within the rolling window. Returns the segments
     * sorted by count desc (largest offender first) so the health
     * payload can show a useful "top N" without further sorting.
     *
     * @return array<int,array{label:string,count:int,last_at:?string}>
     */
    private function readSegments(string $countsConfigKey, string $seenConfigKey, int $windowSeconds): array
    {
        $countsPattern = config('question_bank_profiles.worker.redis_keys.' . $countsConfigKey);
        $seenKey = config('question_bank_profiles.worker.redis_keys.' . $seenConfigKey);

        $now = time();
        $minScore = $now - $windowSeconds;
        $recentRaw = Redis::zrangebyscore($seenKey, $minScore, '+inf', ['withscores' => true]);
        if (empty($recentRaw)) {
            return [];
        }

        $recent = $this->normalizeZrangeWithScores($recentRaw);
        if (empty($recent)) {
            return [];
        }

        $labels = array_keys($recent);
        $totals = array_fill_keys($labels, 0);

        // Sum each per-minute hash for the rolling window so the displayed
        // count reflects only events in the last self::ROLLING_WINDOW_MINUTES
        // — never older buckets that still happen to be alive in TTL.
        $minute = (int) floor($now / 60);
        for ($i = 0; $i < self::ROLLING_WINDOW_MINUTES; $i++) {
            $bucketKey = sprintf($countsPattern, $minute - $i);
            $rawCounts = Redis::hmget($bucketKey, $labels);
            foreach ($labels as $idx => $label) {
                $totals[$label] += (int) ($rawCounts[$idx] ?? 0);
            }
        }

        $segments = [];
        foreach ($labels as $label) {
            if ($totals[$label] <= 0) {
                continue;
            }
            $segments[] = [
                'label' => (string) $label,
                'count' => $totals[$label],
                'last_at' => date('c', (int) $recent[$label]),
            ];
        }

        usort($segments, fn ($a, $b) => $b['count'] <=> $a['count']);
        return $segments;
    }

    /**
     * Normalize the various shapes of `Redis::zrangebyscore(..., withscores)`
     * (alternating list vs. associative array) so callers can rely on
     * `[label => score]`.
     *
     * @param  mixed  $raw
     * @return array<string,float>
     */
    private function normalizeZrangeWithScores($raw): array
    {
        if (!is_array($raw) || empty($raw)) {
            return [];
        }
        $first = array_key_first($raw);
        if (is_string($first)) {
            return array_map(fn ($v) => (float) $v, $raw);
        }

        $out = [];
        $count = count($raw);
        for ($i = 0; $i + 1 < $count; $i += 2) {
            $out[(string) $raw[$i]] = (float) $raw[$i + 1];
        }
        return $out;
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
