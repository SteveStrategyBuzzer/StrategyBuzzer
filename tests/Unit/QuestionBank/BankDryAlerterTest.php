<?php

namespace Tests\Unit\QuestionBank;

use App\Services\QuestionBank\BankDryAlerter;
use App\Services\QuestionBank\BankDryDetector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BankDryAlerterTest extends TestCase
{
    private const SLACK_URL = 'https://hooks.slack.test/services/test/dry-alert';
    private const EMAIL = 'ops@test.local';
    private const PAGERDUTY_KEY = 'pd-test-routing-key-1234567890';
    private const PAGERDUTY_URL = 'https://events.pagerduty.test/v2/enqueue';

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

    public function test_alerter_stays_silent_below_threshold(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL);
        Http::fake();
        $this->primeCriticalCount(3);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertNothingSent();
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_alert')));
    }

    public function test_alerter_does_not_fire_exactly_at_threshold_only_above_it(): void
    {
        // Spec wording is ">5 in 10 min" — strictly greater than.
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL);
        Http::fake();
        $this->primeCriticalCount(5);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertNothingSent();
    }

    public function test_alerter_fires_slack_when_threshold_exceeded(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertSent(function ($request) {
            return $request->url() === self::SLACK_URL
                && str_contains((string) $request['text'], 'Question bank DRY')
                && str_contains((string) $request['text'], 'theme=histoire');
        });
        $this->assertNotNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_alert')));
    }

    public function test_alerter_does_not_re_fire_during_cooldown(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, cooldownMinutes: 30);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);
        $this->primeCriticalCount(6);

        $alerter = new BankDryAlerter();
        $alerter->maybeAlert($this->fakeSegment());
        $alerter->maybeAlert($this->fakeSegment());
        $alerter->maybeAlert($this->fakeSegment());

        Http::assertSentCount(1);
    }

    public function test_alerter_re_fires_after_cooldown_expires(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, cooldownMinutes: 0);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);
        $this->primeCriticalCount(6);

        $alerter = new BankDryAlerter();
        $alerter->maybeAlert($this->fakeSegment());
        $alerter->maybeAlert($this->fakeSegment());

        Http::assertSentCount(2);
    }

    public function test_alerter_sends_email_when_email_recipient_configured(): void
    {
        $this->configureAlert(threshold: 5, email: self::EMAIL);
        Http::fake();
        $this->primeCriticalCount(7);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Mail::assertSent(\App\Mail\BankDryAlertMail::class);
        Http::assertNothingSent();
    }

    public function test_alerter_sends_to_both_slack_and_email_when_both_configured(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, email: self::EMAIL);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertSentCount(1);
        Mail::assertSent(\App\Mail\BankDryAlertMail::class);
    }

    public function test_alerter_is_noop_when_no_destination_configured_and_does_not_set_cooldown(): void
    {
        $this->configureAlert(threshold: 5, slack: '', email: '');
        Http::fake();
        $this->primeCriticalCount(99);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertNothingSent();
        Mail::assertNothingSent();
        // Cooldown stays unset so a config fix can fire immediately.
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_alert')));
    }

    public function test_alerter_swallows_slack_http_failure_and_does_not_bump_cooldown(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL);
        Http::fake([self::SLACK_URL => Http::response('boom', 500)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_alert')));
    }

    public function test_alerter_uses_window_minutes_to_aggregate_only_recent_events(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, windowMinutes: 10);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);

        $this->primeCriticalCount(4, withinMinutes: 10);
        $this->primeCriticalCount(4, withinMinutes: 60, offsetFromMinutesAgo: 20);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertNothingSent();
    }

    public function test_config_snapshot_reports_destination_state_and_cooldown(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, email: '', cooldownMinutes: 30);

        $snapshot = (new BankDryAlerter())->configSnapshot();

        $this->assertSame(5, $snapshot['threshold']);
        $this->assertSame(10, $snapshot['window_minutes']);
        $this->assertSame(30, $snapshot['cooldown_minutes']);
        $this->assertTrue($snapshot['slack_configured']);
        $this->assertFalse($snapshot['email_configured']);
        $this->assertFalse($snapshot['pagerduty_configured']);
        $this->assertFalse($snapshot['in_cooldown']);
        $this->assertNull($snapshot['last_alert_at']);
    }

    public function test_config_snapshot_reports_pagerduty_configured_when_routing_key_set(): void
    {
        $this->configureAlert(threshold: 5, pagerduty: self::PAGERDUTY_KEY);

        $snapshot = (new BankDryAlerter())->configSnapshot();

        $this->assertFalse($snapshot['slack_configured']);
        $this->assertFalse($snapshot['email_configured']);
        $this->assertTrue($snapshot['pagerduty_configured']);
    }

    public function test_config_snapshot_reports_in_cooldown_after_alert(): void
    {
        $this->configureAlert(threshold: 5, slack: self::SLACK_URL, cooldownMinutes: 30);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        $snapshot = (new BankDryAlerter())->configSnapshot();
        $this->assertTrue($snapshot['in_cooldown']);
        $this->assertNotNull($snapshot['last_alert_at']);
    }

    public function test_detector_invokes_alerter_on_total_dry(): void
    {
        $this->configureAlert(threshold: 1, slack: self::SLACK_URL, cooldownMinutes: 0);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);

        $detector = new BankDryDetector();
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');
        $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');

        Http::assertSentCount(1);
    }

    public function test_detector_does_not_alert_on_fallback_used_only(): void
    {
        // Per spec, the alert is for the dry counter (CRITICAL) only.
        $this->configureAlert(threshold: 1, slack: self::SLACK_URL, cooldownMinutes: 0);
        Http::fake([self::SLACK_URL => Http::response('ok', 200)]);

        $detector = new BankDryDetector();
        for ($i = 0; $i < 50; $i++) {
            $detector->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');
        }

        Http::assertNothingSent();
    }

    public function test_alerter_triggers_pagerduty_incident_when_threshold_exceeded(): void
    {
        $this->configureAlert(threshold: 5, pagerduty: self::PAGERDUTY_KEY);
        Http::fake([self::PAGERDUTY_URL => Http::response(['status' => 'success'], 202)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertSent(function ($request) {
            return $request->url() === self::PAGERDUTY_URL
                && $request['routing_key'] === self::PAGERDUTY_KEY
                && $request['event_action'] === 'trigger'
                && is_string($request['dedup_key']) && $request['dedup_key'] !== ''
                && str_contains((string) ($request['payload']['summary'] ?? ''), 'Question bank DRY')
                && ($request['payload']['severity'] ?? null) === 'critical'
                && ($request['payload']['source'] ?? null) === 'question-bank'
                && ($request['payload']['custom_details']['segment']['theme'] ?? null) === 'histoire';
        });
        $this->assertNotNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open')));
    }

    public function test_alerter_pagerduty_uses_stable_dedup_key_per_environment(): void
    {
        // Two consecutive triggers in the same environment must reuse the
        // same dedup_key so PagerDuty deduplicates them as one incident.
        $this->configureAlert(threshold: 5, pagerduty: self::PAGERDUTY_KEY, cooldownMinutes: 0);
        Http::fake([self::PAGERDUTY_URL => Http::response(['status' => 'success'], 202)]);
        $this->primeCriticalCount(6);

        $alerter = new BankDryAlerter();
        $alerter->maybeAlert($this->fakeSegment());
        $alerter->maybeAlert($this->fakeSegment());

        $sentDedupKeys = [];
        Http::assertSent(function ($request) use (&$sentDedupKeys) {
            $sentDedupKeys[] = $request['dedup_key'] ?? null;
            return true;
        });
        $this->assertCount(2, $sentDedupKeys);
        $this->assertSame($sentDedupKeys[0], $sentDedupKeys[1]);
        $this->assertStringContainsString('testing', (string) $sentDedupKeys[0]);
    }

    public function test_alerter_resolves_pagerduty_incident_after_dry_counter_clears(): void
    {
        $this->configureAlert(threshold: 5, pagerduty: self::PAGERDUTY_KEY);
        Http::fake([self::PAGERDUTY_URL => Http::response(['status' => 'success'], 202)]);
        $this->primeCriticalCount(6);

        $alerter = new BankDryAlerter();
        $alerter->maybeAlert($this->fakeSegment()); // trigger
        $this->assertNotNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open')));

        // Simulate the dry counter falling back to 0 across the window.
        $this->clearCriticalCounters();

        $alerter->maybeResolve();

        Http::assertSent(function ($request) {
            return $request->url() === self::PAGERDUTY_URL
                && ($request['event_action'] ?? null) === 'resolve'
                && ($request['routing_key'] ?? null) === self::PAGERDUTY_KEY
                && is_string($request['dedup_key'] ?? null);
        });
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open')));
    }

    public function test_alerter_does_not_resolve_when_no_incident_open(): void
    {
        $this->configureAlert(threshold: 5, pagerduty: self::PAGERDUTY_KEY);
        Http::fake([self::PAGERDUTY_URL => Http::response('', 202)]);

        (new BankDryAlerter())->maybeResolve();

        Http::assertNothingSent();
    }

    public function test_alerter_does_not_resolve_while_dry_counter_still_above_zero(): void
    {
        $this->configureAlert(threshold: 5, pagerduty: self::PAGERDUTY_KEY);
        Http::fake([self::PAGERDUTY_URL => Http::response(['status' => 'success'], 202)]);
        $this->primeCriticalCount(6);

        $alerter = new BankDryAlerter();
        $alerter->maybeAlert($this->fakeSegment()); // trigger only
        Http::assertSentCount(1);

        // Counter still well above 0 — resolve must not fire.
        $alerter->maybeResolve();
        Http::assertSentCount(1);
        $this->assertNotNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open')));
    }

    public function test_alerter_resolve_is_noop_when_pagerduty_not_configured(): void
    {
        $this->configureAlert(threshold: 5, pagerduty: '');
        Http::fake();

        // Even if a stale open marker existed somehow, no PagerDuty key
        // means we cannot (and must not) call the API.
        Redis::set(
            config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open'),
            json_encode(['dedup_key' => 'qb-bank-dry:testing', 'ts' => time()])
        );

        (new BankDryAlerter())->maybeResolve();

        Http::assertNothingSent();
    }

    public function test_alerter_pagerduty_failure_does_not_mark_incident_open_or_bump_cooldown(): void
    {
        $this->configureAlert(threshold: 5, pagerduty: self::PAGERDUTY_KEY);
        Http::fake([self::PAGERDUTY_URL => Http::response('boom', 500)]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open')));
        $this->assertNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_last_alert')));
    }

    public function test_alerter_fires_all_three_channels_when_all_configured(): void
    {
        $this->configureAlert(
            threshold: 5,
            slack: self::SLACK_URL,
            email: self::EMAIL,
            pagerduty: self::PAGERDUTY_KEY,
        );
        Http::fake([
            self::SLACK_URL => Http::response('ok', 200),
            self::PAGERDUTY_URL => Http::response(['status' => 'success'], 202),
        ]);
        $this->primeCriticalCount(6);

        (new BankDryAlerter())->maybeAlert($this->fakeSegment());

        Http::assertSent(fn ($r) => $r->url() === self::SLACK_URL);
        Http::assertSent(fn ($r) => $r->url() === self::PAGERDUTY_URL);
        Mail::assertSent(\App\Mail\BankDryAlertMail::class);
        $this->assertNotNull(Redis::get(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open')));
    }

    private function clearCriticalCounters(): void
    {
        $minute = (int) floor(time() / 60);
        $pattern = config('question_bank_profiles.worker.redis_keys.dry_total_counter');
        for ($i = -2; $i < 130; $i++) {
            Redis::del(sprintf($pattern, $minute - $i));
        }
    }

    private function configureAlert(
        int $threshold = 5,
        int $windowMinutes = 10,
        int $cooldownMinutes = 30,
        string $slack = '',
        string $email = '',
        string $pagerduty = '',
        string $pagerdutyEndpoint = self::PAGERDUTY_URL
    ): void {
        config([
            'question_bank_profiles.worker.dry_alert.threshold' => $threshold,
            'question_bank_profiles.worker.dry_alert.window_minutes' => $windowMinutes,
            'question_bank_profiles.worker.dry_alert.cooldown_minutes' => $cooldownMinutes,
            'question_bank_profiles.worker.dry_alert.slack_webhook_url' => $slack,
            'question_bank_profiles.worker.dry_alert.email_recipient' => $email,
            'question_bank_profiles.worker.dry_alert.pagerduty_routing_key' => $pagerduty,
            'question_bank_profiles.worker.dry_alert.pagerduty_endpoint' => $pagerdutyEndpoint,
            'question_bank_profiles.worker.dry_alert.environment_label' => 'testing',
        ]);
    }

    private function fakeSegment(): array
    {
        return [
            'theme' => 'histoire',
            'niveau' => 21,
            'language' => 'fr',
            'is_boss' => false,
            'context' => 'solo',
            'cache_status' => BankDryDetector::CACHE_STATUS_MISS,
        ];
    }

    private function primeCriticalCount(int $count, int $withinMinutes = 10, int $offsetFromMinutesAgo = 0): void
    {
        $minute = (int) floor(time() / 60) - $offsetFromMinutesAgo;
        $pattern = config('question_bank_profiles.worker.redis_keys.dry_total_counter');
        for ($i = 0; $i < $count; $i++) {
            $bucket = $minute - ($i % max($withinMinutes, 1));
            Redis::incr(sprintf($pattern, $bucket));
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
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_critical_event'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_alert'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_pagerduty_open'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_total_segment_seen'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_fallback_segment_seen'));
        } catch (\Throwable $e) {
            // intentionally ignored
        }
    }
}
