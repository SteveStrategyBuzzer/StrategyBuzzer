<?php

namespace Tests\Unit\QuestionBank;

use App\Services\QuestionBank\BankDryDetector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BankDryDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->flushDryKeys();
    }

    protected function tearDown(): void
    {
        $this->flushDryKeys();
        parent::tearDown();
    }

    public function test_record_fallback_used_increments_minute_counter_and_writes_last_event(): void
    {
        Log::spy();

        (new BankDryDetector())->recordFallbackUsed(
            'histoire', 21, 'fr', false, 'solo',
            BankDryDetector::CACHE_STATUS_MISS
        );

        $minute = (int) floor(time() / 60);
        $key = sprintf(
            config('question_bank_profiles.worker.redis_keys.dry_fallback_counter'),
            $minute
        );
        $this->assertSame(1, (int) Redis::get($key));

        $rawLast = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
        $event = json_decode($rawLast, true);
        $this->assertSame(BankDryDetector::SEVERITY_DEGRADED, $event['severity']);
        $this->assertSame('histoire', $event['segment']['theme']);
        $this->assertSame(21, $event['segment']['niveau']);
        $this->assertSame('fr', $event['segment']['language']);
        $this->assertFalse($event['segment']['is_boss']);
        $this->assertSame('solo', $event['segment']['context']);
        $this->assertSame(BankDryDetector::CACHE_STATUS_MISS, $event['segment']['cache_status']);
        $this->assertArrayHasKey('at', $event);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'DRY DEGRADED'))
            ->once();
    }

    public function test_record_fallback_used_preserves_each_cache_status_value(): void
    {
        $detector = new BankDryDetector();

        $cases = [
            BankDryDetector::CACHE_STATUS_MISS,
            BankDryDetector::CACHE_STATUS_SKIPPED_BOSS,
            BankDryDetector::CACHE_STATUS_SKIPPED_EXPLICIT,
            BankDryDetector::CACHE_STATUS_UNKNOWN,
        ];

        foreach ($cases as $status) {
            $detector->recordFallbackUsed('art', 41, 'es', false, 'solo', $status);
            $rawLast = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
            $event = json_decode($rawLast, true);
            $this->assertSame($status, $event['segment']['cache_status']);
        }
    }

    public function test_record_fallback_used_defaults_cache_status_to_unknown_when_omitted(): void
    {
        (new BankDryDetector())->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');

        $rawLast = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
        $event = json_decode($rawLast, true);
        $this->assertSame(BankDryDetector::CACHE_STATUS_UNKNOWN, $event['segment']['cache_status']);
    }

    public function test_record_total_dry_increments_total_counter_and_writes_critical_event(): void
    {
        Log::spy();

        (new BankDryDetector())->recordTotalDry('sport', 50, 'en', true, 'duo');

        $minute = (int) floor(time() / 60);
        $key = sprintf(
            config('question_bank_profiles.worker.redis_keys.dry_total_counter'),
            $minute
        );
        $this->assertSame(1, (int) Redis::get($key));

        $rawLast = Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
        $event = json_decode($rawLast, true);
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $event['severity']);
        $this->assertSame('sport', $event['segment']['theme']);
        $this->assertSame(50, $event['segment']['niveau']);
        $this->assertSame('en', $event['segment']['language']);
        $this->assertTrue($event['segment']['is_boss']);
        $this->assertSame('duo', $event['segment']['context']);

        Log::shouldHaveReceived('critical')
            ->withArgs(fn ($msg) => str_contains($msg, 'DRY CRITICAL'))
            ->once();
    }

    public function test_snapshot_with_empty_redis_returns_zero_and_ok_severity(): void
    {
        $snapshot = (new BankDryDetector())->snapshot();

        $this->assertSame(0, $snapshot['fallback_used_1h']);
        $this->assertSame(0, $snapshot['total_dry_1h']);
        $this->assertSame(0, $snapshot['dry_segment_count_1h']);
        $this->assertSame(0, $snapshot['fallback_segment_count_1h']);
        $this->assertSame([], $snapshot['segments_top_critical']);
        $this->assertSame([], $snapshot['segments_top_degraded']);
        $this->assertNull($snapshot['last_event']);
        $this->assertNull($snapshot['last_dry_at']);
        $this->assertSame(BankDryDetector::SEVERITY_OK, $snapshot['severity']);
    }

    public function test_snapshot_after_fallback_event_reports_degraded(): void
    {
        $detector = new BankDryDetector();
        $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');

        $snapshot = $detector->snapshot();

        $this->assertSame(1, $snapshot['fallback_used_1h']);
        $this->assertSame(0, $snapshot['total_dry_1h']);
        $this->assertNotNull($snapshot['last_event']);
        $this->assertSame(BankDryDetector::SEVERITY_DEGRADED, $snapshot['severity']);
    }

    public function test_snapshot_after_total_dry_event_reports_critical_even_if_fallback_also_present(): void
    {
        $detector = new BankDryDetector();
        $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');
        $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');
        $detector->recordTotalDry('sport', 100, 'es', true, 'master');

        $snapshot = $detector->snapshot();

        $this->assertSame(2, $snapshot['fallback_used_1h']);
        $this->assertSame(1, $snapshot['total_dry_1h']);
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $snapshot['severity']);
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $snapshot['last_event']['severity']);
    }

    public function test_snapshot_reports_distinct_critical_segment_count_not_just_aggregate(): void
    {
        $detector = new BankDryDetector();
        // Same segment hit 3 times → still 1 distinct segment.
        for ($i = 0; $i < 3; $i++) {
            $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');
        }
        // Two more distinct segments.
        $detector->recordTotalDry('sport', 50, 'en', true, 'duo');
        $detector->recordTotalDry('art', 41, 'es', false, 'solo');

        $snapshot = $detector->snapshot();

        $this->assertSame(5, $snapshot['total_dry_1h']);
        $this->assertSame(3, $snapshot['dry_segment_count_1h']);

        $top = $snapshot['segments_top_critical'];
        $this->assertCount(3, $top);
        $this->assertSame(BankDryDetector::segmentLabel('histoire', 21, 'fr', false), $top[0]['label']);
        $this->assertSame(3, $top[0]['count']);
        $this->assertNotNull($top[0]['last_at']);
    }

    public function test_snapshot_reports_distinct_fallback_segment_count(): void
    {
        $detector = new BankDryDetector();
        $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');
        $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');
        $detector->recordFallbackUsed('sport', 50, 'en', true, 'duo');

        $snapshot = $detector->snapshot();

        $this->assertSame(3, $snapshot['fallback_used_1h']);
        $this->assertSame(2, $snapshot['fallback_segment_count_1h']);
        $this->assertCount(2, $snapshot['segments_top_degraded']);
    }

    public function test_snapshot_last_dry_at_reflects_last_critical_event(): void
    {
        $detector = new BankDryDetector();
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');

        $snapshot = $detector->snapshot();

        $this->assertNotNull($snapshot['last_dry_at']);
        $this->assertNotNull($snapshot['last_critical_event']);
        $this->assertSame($snapshot['last_critical_event']['at'], $snapshot['last_dry_at']);
        $this->assertSame('histoire', $snapshot['last_critical_event']['segment']['theme']);
    }

    public function test_last_dry_at_is_critical_only_and_not_overwritten_by_later_degraded_event(): void
    {
        $detector = new BankDryDetector();
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');
        $criticalSnapshot = $detector->snapshot();
        $criticalAt = $criticalSnapshot['last_dry_at'];
        $this->assertNotNull($criticalAt);

        // Wait a moment so a later event has a strictly later timestamp.
        sleep(1);
        $detector->recordFallbackUsed('art', 41, 'es', false, 'solo');

        $snapshot = $detector->snapshot();
        // last_event reflects the most recent event of any severity…
        $this->assertSame(BankDryDetector::SEVERITY_DEGRADED, $snapshot['last_event']['severity']);
        // …but last_dry_at must still point to the earlier CRITICAL event.
        $this->assertSame($criticalAt, $snapshot['last_dry_at']);
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $snapshot['last_critical_event']['severity']);
    }

    public function test_per_segment_count_is_rolling_window_only_excludes_stale_minute_buckets(): void
    {
        // Pre-seed the per-minute hash for a label as if the segment had
        // 7 critical events 90 minutes ago — outside the 60-minute rolling
        // window. Then trigger 2 fresh events for the same label inside
        // the window. The displayed count must be 2 (rolling), not 9
        // (cumulative).
        $label = BankDryDetector::segmentLabel('histoire', 21, 'fr', false);
        $minute = (int) floor(time() / 60);
        $countsPattern = config('question_bank_profiles.worker.redis_keys.dry_total_segment_counts');
        $seenKey = config('question_bank_profiles.worker.redis_keys.dry_total_segment_seen');

        // Stale bucket: 90 minutes ago, count=7.
        Redis::hincrby(sprintf($countsPattern, $minute - 90), $label, 7);
        // Last-seen ZSET in the window so the label is considered.
        Redis::zadd($seenKey, time() - 30, $label);

        // Two fresh events in the rolling window.
        $detector = new BankDryDetector();
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');

        $snapshot = $detector->snapshot();
        $this->assertNotEmpty($snapshot['segments_top_critical']);
        $top = $snapshot['segments_top_critical'][0];
        $this->assertSame($label, $top['label']);
        $this->assertSame(2, $top['count'], 'rolling 1h count must exclude stale buckets older than the window');
    }

    public function test_segment_label_distinguishes_boss_from_standard_for_same_level(): void
    {
        $std = BankDryDetector::segmentLabel('histoire', 50, 'fr', false);
        $boss = BankDryDetector::segmentLabel('histoire', 50, 'fr', true);
        $this->assertNotSame($std, $boss);
    }

    public function test_redis_failure_is_swallowed_so_gameplay_never_breaks(): void
    {
        Log::spy();
        Redis::shouldReceive('incr')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('expire')->andReturnTrue();
        Redis::shouldReceive('set')->andReturnTrue();
        Redis::shouldReceive('hincrby')->andReturnTrue();
        Redis::shouldReceive('zadd')->andReturnTrue();
        Redis::shouldReceive('get')->andReturn(null);

        $detector = new BankDryDetector();

        $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');
        $detector->recordTotalDry('sport', 100, 'es', true, 'master');

        Log::shouldHaveReceived('warning')->atLeast()->times(2);
    }

    public function test_detector_class_makes_no_http_calls_and_imports_no_ai_provider(): void
    {
        // Source-level guard: BankDryDetector must remain a metric service
        // and never reintroduce the live-AI path that #88 removed.
        $source = file_get_contents(app_path('Services/QuestionBank/BankDryDetector.php'));
        $this->assertStringNotContainsString('Http::', $source);
        $this->assertStringNotContainsString('GuzzleHttp', $source);
        $this->assertStringNotContainsString('generate-bank-question', $source);
        $this->assertStringNotContainsString('generate-question', $source);
        $this->assertStringNotContainsString('BankWorker', $source);
        $this->assertStringNotContainsString('triggerRefill', $source);
    }

    private function flushDryKeys(): void
    {
        try {
            $minute = (int) floor(time() / 60);
            $patterns = [
                config('question_bank_profiles.worker.redis_keys.dry_fallback_counter'),
                config('question_bank_profiles.worker.redis_keys.dry_total_counter'),
                config('question_bank_profiles.worker.redis_keys.dry_total_segment_counts'),
                config('question_bank_profiles.worker.redis_keys.dry_fallback_segment_counts'),
            ];
            for ($i = -2; $i < 130; $i++) {
                foreach ($patterns as $pat) {
                    Redis::del(sprintf($pat, $minute - $i));
                }
            }
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_critical_event'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_total_segment_seen'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_fallback_segment_seen'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_alert'));
        } catch (\Throwable $e) {
            // intentionally ignored
        }
    }
}
