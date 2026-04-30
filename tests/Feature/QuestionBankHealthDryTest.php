<?php

namespace Tests\Feature;

use App\Services\QuestionBank\BankDryDetector;
use App\Services\QuestionBank\BankSelfHealer;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class QuestionBankHealthDryTest extends TestCase
{
    private const TEST_TOKEN = 'test-qb-health-token-92';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'testing']);
        putenv('QB_HEALTH_TOKEN='.self::TEST_TOKEN);
        $this->flushDryKeys();
    }

    protected function tearDown(): void
    {
        $this->flushDryKeys();
        putenv('QB_HEALTH_TOKEN');
        parent::tearDown();
    }

    public function test_health_endpoint_includes_dry_section_with_ok_severity_when_no_events(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TEST_TOKEN,
        ])->getJson('/api/admin/questions/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'dry' => [
                'fallback_used_1h',
                'total_dry_1h',
                'dry_segment_count_1h',
                'fallback_segment_count_1h',
                'segments_top_critical',
                'segments_top_degraded',
                'last_event',
                'last_dry_at',
                'severity',
                'alert',
            ],
        ]);
        $response->assertJson([
            'dry' => [
                'fallback_used_1h' => 0,
                'total_dry_1h' => 0,
                'dry_segment_count_1h' => 0,
                'fallback_segment_count_1h' => 0,
                'last_event' => null,
                'last_dry_at' => null,
                'severity' => BankDryDetector::SEVERITY_OK,
            ],
        ]);
    }

    public function test_health_endpoint_reports_degraded_after_fallback_event(): void
    {
        (new BankDryDetector())->recordFallbackUsed('histoire', 21, 'fr', false, 'solo');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TEST_TOKEN,
        ])->getJson('/api/admin/questions/health');

        $response->assertOk();
        $body = $response->json('dry');
        $this->assertSame(1, $body['fallback_used_1h']);
        $this->assertSame(0, $body['total_dry_1h']);
        $this->assertSame(1, $body['fallback_segment_count_1h']);
        $this->assertSame(BankDryDetector::SEVERITY_DEGRADED, $body['severity']);
        $this->assertSame('histoire', $body['last_event']['segment']['theme']);
        $this->assertSame('fr', $body['last_event']['segment']['language']);
    }

    public function test_health_endpoint_reports_critical_after_total_dry_event(): void
    {
        (new BankDryDetector())->recordTotalDry('sport', 100, 'es', true, 'master');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TEST_TOKEN,
        ])->getJson('/api/admin/questions/health');

        $response->assertOk();
        $body = $response->json('dry');
        $this->assertSame(0, $body['fallback_used_1h']);
        $this->assertSame(1, $body['total_dry_1h']);
        $this->assertSame(1, $body['dry_segment_count_1h']);
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $body['severity']);
        $this->assertSame('sport', $body['last_event']['segment']['theme']);
        $this->assertTrue($body['last_event']['segment']['is_boss']);
    }

    public function test_health_endpoint_reports_distinct_segments_and_top_offender(): void
    {
        $detector = new BankDryDetector();
        // Same segment 4×, plus 2 other distinct segments.
        for ($i = 0; $i < 4; $i++) {
            $detector->recordTotalDry('histoire', 21, 'fr', false, 'solo');
        }
        $detector->recordTotalDry('sport', 50, 'en', true, 'duo');
        $detector->recordTotalDry('art', 41, 'es', false, 'solo');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TEST_TOKEN,
        ])->getJson('/api/admin/questions/health');

        $response->assertOk();
        $body = $response->json('dry');
        $this->assertSame(6, $body['total_dry_1h']);
        $this->assertSame(3, $body['dry_segment_count_1h']);
        $this->assertCount(3, $body['segments_top_critical']);
        $this->assertSame(
            BankDryDetector::segmentLabel('histoire', 21, 'fr', false),
            $body['segments_top_critical'][0]['label']
        );
        $this->assertSame(4, $body['segments_top_critical'][0]['count']);
    }

    public function test_health_endpoint_exposes_alert_configuration_under_dry_section(): void
    {
        config([
            'question_bank_profiles.worker.dry_alert.threshold' => 5,
            'question_bank_profiles.worker.dry_alert.window_minutes' => 10,
            'question_bank_profiles.worker.dry_alert.cooldown_minutes' => 30,
            'question_bank_profiles.worker.dry_alert.slack_webhook_url' => 'https://hooks.slack.test/x',
            'question_bank_profiles.worker.dry_alert.email_recipient' => '',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TEST_TOKEN,
        ])->getJson('/api/admin/questions/health');

        $response->assertOk();
        $alert = $response->json('dry.alert');
        $this->assertSame(5, $alert['threshold']);
        $this->assertSame(10, $alert['window_minutes']);
        $this->assertSame(30, $alert['cooldown_minutes']);
        $this->assertTrue($alert['slack_configured']);
        $this->assertFalse($alert['email_configured']);
        $this->assertFalse($alert['in_cooldown']);
        $this->assertNull($alert['last_alert_at']);
    }

    public function test_health_endpoint_reports_self_heal_section_with_disabled_default(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TEST_TOKEN,
        ])->getJson('/api/admin/questions/health');

        $response->assertOk();
        $selfHeal = $response->json('dry.self_heal');
        $this->assertIsArray($selfHeal);
        $this->assertArrayHasKey('config', $selfHeal);
        $this->assertArrayHasKey('last_action', $selfHeal);
        $this->assertFalse($selfHeal['config']['enabled']);
        $this->assertNull($selfHeal['last_action']);
        $this->assertNull($selfHeal['config']['active_boost']);
    }

    public function test_health_endpoint_surfaces_last_self_heal_action_after_critical(): void
    {
        config([
            'question_bank_profiles.worker.dry_alert.threshold' => 1,
            'question_bank_profiles.worker.dry_alert.window_minutes' => 10,
            'question_bank_profiles.worker.dry_alert.cooldown_minutes' => 0,
            'question_bank_profiles.worker.dry_alert.slack_webhook_url' => '',
            'question_bank_profiles.worker.dry_alert.email_recipient' => '',
            'question_bank_profiles.worker.dry_autoremediate.enabled' => true,
            'question_bank_profiles.worker.dry_autoremediate.boost_minutes' => 8,
            'question_bank_profiles.worker.dry_autoremediate.boost_rate_per_minute' => 22,
        ]);

        // Two CRITICAL events to push past threshold=1.
        $detector = new BankDryDetector();
        $detector->recordTotalDry('art', 41, 'fr', false, 'solo');
        $detector->recordTotalDry('art', 41, 'fr', false, 'solo');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TEST_TOKEN,
        ])->getJson('/api/admin/questions/health');

        $response->assertOk();
        $selfHeal = $response->json('dry.self_heal');
        $this->assertTrue($selfHeal['config']['enabled']);
        $this->assertSame(8, $selfHeal['config']['boost_minutes']);
        $this->assertSame(22, $selfHeal['config']['boost_rate_per_minute']);
        $this->assertIsArray($selfHeal['last_action']);
        $this->assertSame('art', $selfHeal['last_action']['segment']['theme']);
        $this->assertSame('success', $selfHeal['last_action']['outcome']);
        $this->assertGreaterThan(0, $selfHeal['last_action']['ts']);
        $actionTypes = array_column($selfHeal['last_action']['actions'], 'type');
        $this->assertContains('rate_boost', $actionTypes);
        $this->assertContains('priority_segment_enqueued', $actionTypes);
        $this->assertIsArray($selfHeal['config']['active_boost']);
    }

    public function test_health_endpoint_remains_gated_dry_section_not_leaked_without_token(): void
    {
        (new BankDryDetector())->recordTotalDry('sport', 100, 'es', true, 'master');

        $response = $this->getJson('/api/admin/questions/health');
        $response->assertStatus(403);
        $response->assertJsonMissingPath('dry');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer wrong-token',
        ])->getJson('/api/admin/questions/health');
        $response->assertStatus(403);
        $response->assertJsonMissingPath('dry');
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
            Redis::del(config('question_bank_profiles.worker.redis_keys.rate_override'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.priority_segment'));
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_self_heal'));
        } catch (\Throwable $e) {
            // intentionally ignored
        }
    }
}
