<?php

namespace Tests\Unit\QuestionBank;

use App\Services\QuestionBank\BankDryDetector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * #92 — BankDryDetector unit tests.
 *
 * Verifies that:
 *   - dry events increment the correct Redis counters and write last_event
 *   - severity is derived correctly from the rolling 1h sums
 *   - Redis failures are swallowed (gameplay must never break on a metric)
 *   - the detector NEVER triggers the worker or any AI provider (no HTTP)
 */
class BankDryDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Wipe any previous-run Redis state so counters start at 0.
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
        $this->assertIsString($rawLast);
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

    /**
     * #92 — Ops must be able to filter degraded events by cache_status to
     * tell a true cache exhaustion (`miss`) apart from a deliberately
     * bypassed cache (`skipped:boss` / `skipped:explicit`).
     */
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
            $this->assertSame(
                $status,
                $event['segment']['cache_status'],
                "cache_status `{$status}` must round-trip through last_event verbatim."
            );
        }
    }

    public function test_record_fallback_used_defaults_cache_status_to_unknown_when_omitted(): void
    {
        // Backward-compat: callers that haven't been updated must still work
        // and get the explicit `unknown` marker rather than a missing key.
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
        $this->assertNull($snapshot['last_event']);
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
        // Critical wins over degraded — Ops must see the worst signal.
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $snapshot['severity']);
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $snapshot['last_event']['severity']);
    }

    public function test_redis_failure_is_swallowed_so_gameplay_never_breaks(): void
    {
        Log::spy();
        // Force every Redis write through the facade to throw. The detector
        // must NOT propagate the exception — the metric is best-effort.
        Redis::shouldReceive('incr')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('expire')->andReturnTrue();
        Redis::shouldReceive('set')->andReturnTrue();

        $detector = new BankDryDetector();

        // Either of these would propagate if the detector forgot the safety net.
        $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');
        $detector->recordTotalDry('sport', 100, 'es', true, 'master');

        // Original DEGRADED/CRITICAL warnings + the swallow-warning for each call.
        Log::shouldHaveReceived('warning')->atLeast()->times(2);
    }

    public function test_detector_class_makes_no_http_calls_and_imports_no_ai_provider(): void
    {
        // Source-level guard: this is a metric service, not a generation
        // service. It must never reintroduce the live-AI path that #88
        // explicitly removed.
        $source = file_get_contents(app_path('Services/QuestionBank/BankDryDetector.php'));
        $this->assertIsString($source);
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
            ];
            // Window is 60 minutes; clear a bit wider to be safe across runs.
            for ($i = -2; $i < 65; $i++) {
                foreach ($patterns as $pat) {
                    Redis::del(sprintf($pat, $minute - $i));
                }
            }
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
        } catch (\Throwable $e) {
            // Tests still fail loudly on assertions if Redis isn't available;
            // this cleanup helper just shouldn't itself crash.
        }
    }
}
