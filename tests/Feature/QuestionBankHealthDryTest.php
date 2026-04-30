<?php

namespace Tests\Feature;

use App\Services\QuestionBank\BankDryDetector;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * #92 — GET /api/admin/questions/health must expose a `dry` section so
 * Ops can detect when live matches are degraded (served from seed pool)
 * or critical (bank+cache+seed all empty).
 *
 * The endpoint stays gated by QB_HEALTH_TOKEN (deny-by-default if unset).
 */
class QuestionBankHealthDryTest extends TestCase
{
    private const TEST_TOKEN = 'test-qb-health-token-92';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'testing']);
        // Set the env var the controller reads via env() for the duration of the test.
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
            'dry' => ['fallback_used_1h', 'total_dry_1h', 'last_event', 'severity'],
        ]);
        $response->assertJson([
            'dry' => [
                'fallback_used_1h' => 0,
                'total_dry_1h' => 0,
                'last_event' => null,
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
        $this->assertSame(BankDryDetector::SEVERITY_CRITICAL, $body['severity']);
        $this->assertSame('sport', $body['last_event']['segment']['theme']);
        $this->assertTrue($body['last_event']['segment']['is_boss']);
    }

    public function test_health_endpoint_remains_gated_dry_section_not_leaked_without_token(): void
    {
        (new BankDryDetector())->recordTotalDry('sport', 100, 'es', true, 'master');

        // No Authorization header → 403, no dry section in body.
        $response = $this->getJson('/api/admin/questions/health');
        $response->assertStatus(403);
        $response->assertJsonMissingPath('dry');

        // Wrong token → still 403.
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
            ];
            for ($i = -2; $i < 65; $i++) {
                foreach ($patterns as $pat) {
                    Redis::del(sprintf($pat, $minute - $i));
                }
            }
            Redis::del(config('question_bank_profiles.worker.redis_keys.dry_last_event'));
        } catch (\Throwable $e) {
            // intentionally ignored
        }
    }
}
