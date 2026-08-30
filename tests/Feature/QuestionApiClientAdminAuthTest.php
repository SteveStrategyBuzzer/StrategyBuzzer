<?php

namespace Tests\Feature;

use App\Models\AdminQuestionAuditLog;
use App\Services\QuestionApi\QuestionApiClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task #94 — Locks down the admin AI composition path.
 *
 * Proves that QuestionApiClient:
 *   1. Mints a short-lived (<= 60s) HS256 JWT with the contract claims
 *      the question-api expects (aud, purpose, endpoint, payload_hash, sub,
 *      jti, iat, exp).
 *   2. Sends it as `Authorization: Bearer <jwt>` (NOT as the legacy
 *      X-Admin-Token shared secret) and reuses the JSON body bytes that
 *      were hashed into the token.
 *   3. Writes one audit row per call capturing caller user id, endpoint,
 *      payload hash, http status, accepted flag.
 *   4. Refuses to run if the secret is missing/weak (fail-closed).
 */
class QuestionApiClientAdminAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('admin_question_audit_log', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('jti', 64)->unique();
            $table->unsignedBigInteger('caller_user_id')->nullable();
            $table->string('endpoint', 64);
            $table->char('payload_hash', 64);
            $table->string('source', 64)->nullable();
            $table->boolean('accepted')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('responded_at')->nullable();
        });
        // Laravel's env() only reads from $_ENV / $_SERVER (the PutenvAdapter
        // is disabled in Laravel 9+ for performance), so set both.
        $secret = 'test-secret-that-is-definitely-long-enough';
        $_ENV['QUESTION_API_JWT_SECRET'] = $secret;
        $_SERVER['QUESTION_API_JWT_SECRET'] = $secret;
        $_ENV['QUESTION_API_URL'] = 'http://qapi.test';
        $_SERVER['QUESTION_API_URL'] = 'http://qapi.test';
    }

    protected function tearDown(): void
    {
        unset($_ENV['QUESTION_API_JWT_SECRET'], $_SERVER['QUESTION_API_JWT_SECRET']);
        unset($_ENV['QUESTION_API_URL'], $_SERVER['QUESTION_API_URL']);
        Schema::dropIfExists('admin_question_audit_log');
        parent::tearDown();
    }

    public function test_post_admin_mints_short_lived_jwt_and_writes_audit_row(): void
    {
        Http::fake([
            'qapi.test/generate-master-question' => Http::response([
                'success' => true,
                'question' => ['text' => 'Q?', 'answers' => ['a', 'b', 'c', 'd'], 'correct_index' => 0],
            ], 200),
        ]);

        $client = new QuestionApiClient();
        $payload = ['theme' => 'Histoire', 'language' => 'fr', 'questionNumber' => 1];

        $beforeCount = AdminQuestionAuditLog::count();
        $response = $client->postAdmin(
            QuestionApiClient::ENDPOINT_MASTER_QUESTION,
            $payload,
            ['caller_user_id' => 4242, 'source' => 'phpunit']
        );

        $this->assertTrue($response->successful());
        $this->assertSame(200, $response->status());
        $this->assertSame($beforeCount + 1, AdminQuestionAuditLog::count());

        $row = AdminQuestionAuditLog::latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(4242, (int) $row->caller_user_id);
        $this->assertSame(QuestionApiClient::ENDPOINT_MASTER_QUESTION, $row->endpoint);
        $this->assertSame('phpunit', $row->source);
        $this->assertTrue((bool) $row->accepted);
        $this->assertSame(200, (int) $row->http_status);
        $this->assertNotNull($row->responded_at);
        $this->assertNotEmpty($row->jti);
        $this->assertSame(64, strlen($row->payload_hash));

        // The audit row's payload_hash must be sha256(rawBody) sent on the wire.
        $expectedHash = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $this->assertSame($expectedHash, $row->payload_hash);

        // The request must carry an Authorization: Bearer header — never the
        // legacy X-Admin-Token shared secret.
        Http::assertSent(function ($request) use ($expectedHash) {
            $auth = $request->header('Authorization')[0] ?? '';
            $legacy = $request->header('X-Admin-Token');

            if ($legacy && count($legacy) > 0) {
                return false;
            }
            if (!str_starts_with($auth, 'Bearer ')) {
                return false;
            }

            $jwt = substr($auth, strlen('Bearer '));
            $claims = (array) JWT::decode(
                $jwt,
                new Key('test-secret-that-is-definitely-long-enough', 'HS256')
            );

            return $claims['aud'] === 'question-api'
                && $claims['purpose'] === 'qapi_admin'
                && $claims['endpoint'] === '/generate-master-question'
                && $claims['payload_hash'] === $expectedHash
                && $claims['sub'] === '4242'
                && !empty($claims['jti'])
                && $claims['exp'] - $claims['iat'] <= 60
                && $claims['exp'] - $claims['iat'] > 0;
        });
    }

    public function test_post_admin_records_failed_call_in_audit_row(): void
    {
        Http::fake([
            'qapi.test/generate-master-question' => Http::response(['error' => 'nope'], 503),
        ]);

        $client = new QuestionApiClient();

        $response = $client->postAdmin(
            QuestionApiClient::ENDPOINT_MASTER_QUESTION,
            ['theme' => 'X', 'language' => 'fr'],
            ['caller_user_id' => 7, 'source' => 'phpunit-fail']
        );

        $this->assertFalse($response->successful());

        $row = AdminQuestionAuditLog::latest('id')->first();
        $this->assertSame(7, (int) $row->caller_user_id);
        $this->assertFalse((bool) $row->accepted);
        $this->assertSame(503, (int) $row->http_status);
        $this->assertNotNull($row->error);
    }

    public function test_phase1_endpoint_is_allowed_and_bound_into_jwt_claim(): void
    {
        Http::fake([
            'qapi.test/generate-kernel-phase1-source' => Http::response([
                'ok' => true,
                'result' => ['slots' => []],
            ], 200),
        ]);

        $client = new QuestionApiClient();
        $client->postAdmin(
            QuestionApiClient::ENDPOINT_KERNEL_PHASE1_SOURCE,
            ['blueprint_id' => 'bp-phase1'],
            ['caller_user_id' => 1, 'source' => 'phase1-test']
        );

        Http::assertSent(function ($request): bool {
            $auth = $request->header('Authorization')[0] ?? '';
            if (! str_starts_with($auth, 'Bearer ')) {
                return false;
            }

            $claims = (array) JWT::decode(
                substr($auth, strlen('Bearer ')),
                new Key('test-secret-that-is-definitely-long-enough', 'HS256')
            );

            return $claims['endpoint'] === QuestionApiClient::ENDPOINT_KERNEL_PHASE1_SOURCE;
        });
    }

    public function test_post_admin_rejects_unknown_endpoint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QuestionApiClient())->postAdmin('/some-other-endpoint', ['x' => 1]);
    }

    public function test_constructor_fails_fast_when_secret_is_missing_or_weak(): void
    {
        unset($_ENV['QUESTION_API_JWT_SECRET'], $_SERVER['QUESTION_API_JWT_SECRET']);
        // Also blank the fallback explicitly so the test is hermetic.
        config(['app.game_server_jwt_secret' => '']);

        $this->expectException(\RuntimeException::class);
        new QuestionApiClient();
    }

    public function test_each_call_uses_a_fresh_jti(): void
    {
        Http::fake([
            'qapi.test/generate-master-question' => Http::response(['success' => true], 200),
        ]);

        $client = new QuestionApiClient();
        $client->postAdmin(QuestionApiClient::ENDPOINT_MASTER_QUESTION, ['n' => 1], ['caller_user_id' => 1]);
        $client->postAdmin(QuestionApiClient::ENDPOINT_MASTER_QUESTION, ['n' => 2], ['caller_user_id' => 1]);

        $jtis = AdminQuestionAuditLog::orderBy('id', 'desc')->limit(2)->pluck('jti')->all();
        $this->assertCount(2, $jtis);
        $this->assertNotSame($jtis[0], $jtis[1]);
    }
}
