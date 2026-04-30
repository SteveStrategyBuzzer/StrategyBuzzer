<?php

namespace Tests\Unit\QuestionBank;

use App\Services\QuestionBank\BankDryAlerter;
use App\Services\QuestionBank\BankDryDetector;
use App\Services\QuestionBank\BankSelfHealer;
use App\Services\QuestionBank\Worker\WorkerRateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BankSelfHealerTest extends TestCase
{
    private const SLACK_URL = 'https://hooks.slack.test/services/test/dry-alert-99';

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushKeys();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->flushKeys();
        parent::tearDown();
    }

    public function test_attempt_returns_null_when_disabled(): void
    {
        $healer = new BankSelfHealer(enabledOverride: false);

        $result = $healer->attempt($this->fakeSegment());

        $this->assertNull($result);
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.rate_override')));
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.priority_segment')));
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_self_heal')));
    }

    public function test_attempt_writes_rate_override_priority_segment_and_descriptor_when_enabled(): void
    {
        $healer = new BankSelfHealer(
            enabledOverride: true,
            boostMinutesOverride: 7,
            boostRatePerMinuteOverride: 42
        );

        $result = $healer->attempt($this->fakeSegment());

        $this->assertIsArray($result);
        $this->assertTrue($result['enabled']);
        $this->assertSame('success', $result['outcome']);
        $this->assertSame(7, $result['boost_minutes']);
        $this->assertSame(42, $result['boost_rate_per_minute']);
        $this->assertGreaterThan(time(), $result['until_ts']);

        $actionTypes = array_column($result['actions'], 'type');
        $this->assertContains('rate_boost', $actionTypes);
        $this->assertContains('rate_bucket_flushed', $actionTypes);
        $this->assertContains('priority_segment_enqueued', $actionTypes);

        $rateOverride = json_decode(Redis::get(config('question_bank_profiles.worker.redis_keys.rate_override')), true);
        $this->assertIsArray($rateOverride);
        $this->assertSame(42, $rateOverride['rate_per_minute']);
        $this->assertGreaterThan(time(), $rateOverride['until_ts']);

        $priority = json_decode(Redis::get(config('question_bank_profiles.worker.redis_keys.priority_segment')), true);
        $this->assertIsArray($priority);
        $this->assertSame('histoire', $priority['segment']['theme']);
        $this->assertSame(21, $priority['segment']['niveau']);

        $last = $healer->lastActionSnapshot();
        $this->assertIsArray($last);
        $this->assertSame('success', $last['outcome']);
        $this->assertSame('histoire', $last['segment']['theme']);
    }

    public function test_attempt_skips_when_active_boost_still_in_effect(): void
    {
        $healer = new BankSelfHealer(
            enabledOverride: true,
            boostMinutesOverride: 5,
            boostRatePerMinuteOverride: 30
        );

        // First call lays down the boost.
        $first = $healer->attempt($this->fakeSegment());
        $this->assertSame('success', $first['outcome']);

        // Second call — same window — must be a no-op for side-effects
        // and report skipped_active_boost.
        $second = $healer->attempt($this->fakeSegment(theme: 'sport', niveau: 80));
        $this->assertSame('skipped_active_boost', $second['outcome']);
        $this->assertEmpty($second['actions']);

        // The original rate-override JSON must still be the one written
        // by the first call.
        $override = json_decode(Redis::get(config('question_bank_profiles.worker.redis_keys.rate_override')), true);
        $this->assertSame(30, $override['rate_per_minute']);
    }

    public function test_worker_rate_limiter_uses_override_when_active(): void
    {
        $base = 2;
        config(['question_bank_profiles.worker.redis_keys.rate_override' => 'qb:worker:rate_override']);
        $limiter = new WorkerRateLimiter($base, 'qb:test:rate:%s');

        $this->assertSame($base, $limiter->effectiveRatePerMinute());

        Redis::set('qb:worker:rate_override', json_encode([
            'rate_per_minute' => 40,
            'until_ts' => time() + 600,
            'set_at_ts' => time(),
            'reason' => 'auto_remediate:critical_dry',
        ]));

        $this->assertSame(40, $limiter->effectiveRatePerMinute());
    }

    public function test_worker_rate_limiter_ignores_expired_override(): void
    {
        $base = 5;
        $limiter = new WorkerRateLimiter($base, 'qb:test:rate:%s');

        Redis::set('qb:worker:rate_override', json_encode([
            'rate_per_minute' => 99,
            'until_ts' => time() - 10,
            'set_at_ts' => time() - 600,
            'reason' => 'auto_remediate:critical_dry',
        ]));

        $this->assertSame($base, $limiter->effectiveRatePerMinute());
    }

    public function test_worker_rate_limiter_keeps_base_when_override_below_base(): void
    {
        $base = 50;
        $limiter = new WorkerRateLimiter($base, 'qb:test:rate:%s');

        Redis::set('qb:worker:rate_override', json_encode([
            'rate_per_minute' => 10,
            'until_ts' => time() + 600,
            'set_at_ts' => time(),
        ]));

        // max(base, override) — never lower the worker's normal rate.
        $this->assertSame($base, $limiter->effectiveRatePerMinute());
    }

    public function test_alerter_includes_self_heal_descriptor_in_payload_and_message(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, autoremediate: true);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertSent(function ($request) {
            $text = (string) $request['text'];
            return str_contains($text, 'Self-heal: success')
                && str_contains($text, 'rate_boost')
                && str_contains($text, 'priority_segment_enqueued');
        });

        // The healer should have left its descriptor key behind.
        $last = (new BankSelfHealer())->lastActionSnapshot();
        $this->assertIsArray($last);
        $this->assertSame('success', $last['outcome']);
    }

    public function test_alerter_does_not_invoke_self_heal_when_autoremediate_disabled(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, autoremediate: false);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        // Slack still fired (alerter behaviour unchanged) but the
        // self-heal Redis keys must not have been written.
        Http::assertSentCount(1);
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.rate_override')));
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.priority_segment')));
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_self_heal')));
    }

    public function test_detector_triggers_self_heal_on_total_dry_when_threshold_breached(): void
    {
        $this->configureAlert(threshold: 1, slack: self::SLACK_URL, cooldownMinutes: 0, autoremediate: true);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);

        $detector = new BankDryDetector();
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');

        $rateOverride = json_decode(Redis::get(config('question_bank_profiles.worker.redis_keys.rate_override')), true);
        $this->assertIsArray($rateOverride);
        $this->assertGreaterThan(0, $rateOverride['rate_per_minute']);

        $priority = json_decode(Redis::get(config('question_bank_profiles.worker.redis_keys.priority_segment')), true);
        $this->assertIsArray($priority);
        $this->assertSame('histoire', $priority['segment']['theme']);
    }

    public function test_config_snapshot_reports_enabled_and_boost_window(): void
    {
        config([
            'question_bank_profiles.worker.dry_autoremediate.enabled' => true,
            'question_bank_profiles.worker.dry_autoremediate.boost_minutes' => 12,
            'question_bank_profiles.worker.dry_autoremediate.boost_rate_per_minute' => 25,
        ]);

        $snapshot = (new BankSelfHealer())->configSnapshot();
        $this->assertTrue($snapshot['enabled']);
        $this->assertSame(12, $snapshot['boost_minutes']);
        $this->assertSame(25, $snapshot['boost_rate_per_minute']);
        $this->assertNull($snapshot['active_boost']);

        Redis::set('qb:worker:rate_override', json_encode([
            'rate_per_minute' => 25,
            'until_ts' => time() + 600,
            'set_at_ts' => time(),
        ]));
        $snapshot2 = (new BankSelfHealer())->configSnapshot();
        $this->assertIsArray($snapshot2['active_boost']);
        $this->assertSame(25, $snapshot2['active_boost']['rate_per_minute']);
    }

    private function configureAlert(
        int $threshold = 5,
        int $windowMinutes = 10,
        int $cooldownMinutes = 30,
        string $slack = '',
        string $email = '',
        bool $autoremediate = false
    ): void {
        config([
            'question_bank_profiles.worker.dry_alert.threshold' => $threshold,
            'question_bank_profiles.worker.dry_alert.window_minutes' => $windowMinutes,
            'question_bank_profiles.worker.dry_alert.cooldown_minutes' => $cooldownMinutes,
            'question_bank_profiles.worker.dry_alert.slack_webhook_url' => $slack,
            'question_bank_profiles.worker.dry_alert.email_recipient' => $email,
            'question_bank_profiles.worker.dry_alert.environment_label' => 'testing',
            'question_bank_profiles.worker.dry_autoremediate.enabled' => $autoremediate,
            'question_bank_profiles.worker.dry_autoremediate.boost_minutes' => 10,
            'question_bank_profiles.worker.dry_autoremediate.boost_rate_per_minute' => 30,
        ]);
    }

    private function fakeSegment(string $theme = 'histoire', int $niveau = 21): array
    {
        return [
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => 'fr',
            'is_boss' => false,
            'context' => 'solo',
            'cache_status' => BankDryDetector::CACHE_STATUS_MISS,
            'label' => BankDryDetector::segmentLabel($theme, $niveau, 'fr', false),
        ];
    }

    private function primeCriticalCount(int $count): void
    {
        $minute = (int) floor(time() / 60);
        $pattern = config('question_bank_profiles.worker.redis_keys.dry_total_counter');
        for ($i = 0; $i < $count; $i++) {
            Redis::incr(sprintf($pattern, $minute));
        }
    }

    private function flushKeys(): void
    {
        try {
            $minute = (int) floor(time() / 60);
            $patterns = [
                config('question_bank_profiles.worker.redis_keys.dry_total_counter'),
                config('question_bank_profiles.worker.redis_keys.dry_fallback_counter'),
                config('question_bank_profiles.worker.redis_keys.dry_total_segment_counts'),
                config('question_bank_profiles.worker.redis_keys.dry_fallback_segment_counts'),
            ];
            for ($i = -2; $i < 130; $i++) {
                foreach ($patterns as $pat) {
                    Redis::del(sprintf($pat, $minute - $i));
                }
            }
            $singletons = [
                'dry_last_event', 'dry_last_critical_event', 'dry_last_alert',
                'dry_total_segment_seen', 'dry_fallback_segment_seen',
                'rate_override', 'priority_segment', 'dry_last_self_heal',
            ];
            foreach ($singletons as $name) {
                Redis::del(config('question_bank_profiles.worker.redis_keys.' . $name));
            }
            Redis::del('qb:worker:rate_override');
        } catch (\Throwable $e) {
            // intentionally ignored
        }
    }
}
