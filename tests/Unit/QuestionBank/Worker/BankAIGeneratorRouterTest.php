<?php

namespace Tests\Unit\QuestionBank\Worker;

use App\Services\QuestionBank\Worker\BankAIGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression net for #87 — locks the bank-refill router contract.
 *
 * This file MUST stay green for the architectural promise of #87 to hold:
 *   - BankAIGenerator only ever talks to the Node router endpoint
 *     (POST {QUESTION_API_URL}/generate-bank-question), never to Gemini
 *     or any other provider directly.
 *   - The request body matches the documented contract field-for-field.
 *   - The router-reported `payload.source` (e.g. "openai" after a
 *     Gemini failover) is honoured all the way to the addToBank()
 *     payload — no hardcoded provider label.
 *   - Router 5xx surfaces structurally as ['ok' => false, …,
 *     'http_status' => 5xx], no exception leak.
 *   - Solo vs Boss segments produce the right (level XOR) field on the
 *     output payload that the worker then writes to the bank.
 *   - The class file itself contains zero direct-Gemini residues.
 *
 * No real outbound HTTP. No real provider keys. Sqlite in-memory only
 * (forced by phpunit.xml — never touch the live Postgres bank).
 */
class BankAIGeneratorRouterTest extends TestCase
{
    private const ROUTER_BASE = 'http://test-router.local';

    private ?string $previousQuestionApiUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin a known router base + a known preferred-language list so
        // assertions on the request body are deterministic regardless of
        // the local .env or the production config defaults.
        $this->previousQuestionApiUrl = getenv('QUESTION_API_URL') ?: null;
        putenv('QUESTION_API_URL='.self::ROUTER_BASE);
        $_ENV['QUESTION_API_URL'] = self::ROUTER_BASE;
        $_SERVER['QUESTION_API_URL'] = self::ROUTER_BASE;

        config()->set('question_bank_profiles.worker.preferred_languages', ['fr', 'en', 'es']);

        // HARD GUARD: any unmatched outbound request — including a
        // future regression that bypasses Http::fake() and tries to
        // hit Gemini/OpenAI directly — fails the test instead of
        // silently leaking real network traffic. This is the core
        // architectural promise of #87.
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        // Restore the environment so we don't leak QUESTION_API_URL
        // into other tests sharing this PHP process.
        if ($this->previousQuestionApiUrl === null) {
            putenv('QUESTION_API_URL');
            unset($_ENV['QUESTION_API_URL'], $_SERVER['QUESTION_API_URL']);
        } else {
            putenv('QUESTION_API_URL='.$this->previousQuestionApiUrl);
            $_ENV['QUESTION_API_URL'] = $this->previousQuestionApiUrl;
            $_SERVER['QUESTION_API_URL'] = $this->previousQuestionApiUrl;
        }

        parent::tearDown();
    }

    /**
     * REQUIREMENT 1 — endpoint targeted.
     */
    public function test_calls_exactly_the_bank_refill_router_endpoint(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());

        $this->assertTrue($result['ok'], 'router fake should yield ok=true');
        Http::assertSent(function (Request $req) {
            return $req->method() === 'POST'
                && $req->url() === self::ROUTER_BASE.'/generate-bank-question';
        });
        Http::assertSentCount(1);
    }

    /**
     * REQUIREMENT 2 — request body matches the documented contract,
     * field-for-field, with values plumbed from the segment + worker
     * config (languages = full preferred list, never a single string).
     */
    public function test_request_body_contains_full_contract_fields(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $segment = $this->soloSegment([
            'domain' => 'Histoire',
            'sub_domain' => 'rome-antique',
            'cognitive_type' => 'reasoning',
            'question_type' => 'qcm',
            'depth_range' => [3, 5],
        ]);

        (new BankAIGenerator())->generateForSegment($segment);

        Http::assertSent(function (Request $req) {
            $body = $req->data();

            // Presence
            $this->assertArrayHasKey('domain', $body);
            $this->assertArrayHasKey('sub_domain', $body);
            $this->assertArrayHasKey('cognitive_type', $body);
            $this->assertArrayHasKey('question_type', $body);
            $this->assertArrayHasKey('difficulty_depth', $body);
            $this->assertArrayHasKey('languages', $body);

            // Values
            $this->assertSame('Histoire', $body['domain']);
            $this->assertSame('rome-antique', $body['sub_domain']);
            $this->assertSame('reasoning', $body['cognitive_type']);
            $this->assertSame('qcm', $body['question_type']);
            // difficulty_depth is the HIGH end of the segment depth_range
            $this->assertSame(5, $body['difficulty_depth']);

            // languages MUST be an array carrying the full preferred list,
            // not a scalar — the router needs the full list to satisfy the
            // segment's deficit in one call.
            $this->assertIsArray($body['languages']);
            $this->assertSame(['fr', 'en', 'es'], $body['languages']);

            return true;
        });
    }

    /**
     * REQUIREMENT 3 — the router-reported `source` MUST flow through to
     * the output payload. No hardcoded provider label may overwrite it.
     */
    public function test_router_payload_source_is_honoured_in_output_payload(): void
    {
        // Simulate a Gemini→OpenAI failover: router answers with source=openai.
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question'
                => Http::response($this->okEnvelope(['source' => 'openai']), 200),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('payload', $result);
        $this->assertSame(
            'openai',
            $result['payload']['source'],
            'BankAIGenerator must persist the provider that actually answered (router payload.source), not a hardcoded label.'
        );
    }

    /**
     * REQUIREMENT 4 — a router 503 surfaces structurally as ok=false +
     * http_status=503. No exception leak. No internal retry (backoff is
     * the worker's job).
     */
    public function test_router_503_returns_structured_failure(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response(
                ['error' => 'all_providers_exhausted'],
                503
            ),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());

        $this->assertSame(false, $result['ok']);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
        $this->assertArrayHasKey('http_status', $result);
        $this->assertSame(503, $result['http_status']);

        // Backoff is the worker's responsibility — the generator must NOT
        // retry. Exactly one outbound call.
        Http::assertSentCount(1);
    }

    /**
     * REQUIREMENT 5a — Solo segment yields difficulty_level (XOR boss_level)
     * on the output payload that addToBank() will persist.
     */
    public function test_solo_segment_output_payload_carries_difficulty_level_only(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $segment = $this->soloSegment([
            'mode_target' => ['type' => 'solo_range', 'levels' => [11, 19]],
        ]);

        $result = (new BankAIGenerator())->generateForSegment($segment);
        $payload = $result['payload'];

        $this->assertArrayHasKey('difficulty_level', $payload, 'Solo segment must produce difficulty_level.');
        $this->assertSame(11, $payload['difficulty_level'], 'Solo difficulty_level must pin to the LOW end of the band.');
        $this->assertArrayNotHasKey(
            'boss_level',
            $payload,
            'Solo segment MUST NOT carry boss_level (DB CHECK enforces XOR).'
        );

        // The request body itself never carries level fields — only the
        // depth and segment classification. Lock that, too, so a future
        // refactor can't quietly leak boss_level into the prompt.
        Http::assertSent(function (Request $req) {
            $body = $req->data();
            $this->assertArrayNotHasKey('difficulty_level', $body);
            $this->assertArrayNotHasKey('boss_level', $body);
            return true;
        });
    }

    /**
     * REQUIREMENT 5b — Boss segment yields boss_level (XOR difficulty_level)
     * on the output payload that addToBank() will persist.
     */
    public function test_boss_segment_output_payload_carries_boss_level_only(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $segment = $this->soloSegment([
            'mode_target' => ['type' => 'boss', 'level' => 30],
        ]);

        $result = (new BankAIGenerator())->generateForSegment($segment);
        $payload = $result['payload'];

        $this->assertArrayHasKey('boss_level', $payload, 'Boss segment must produce boss_level.');
        $this->assertSame(30, $payload['boss_level']);
        $this->assertArrayNotHasKey(
            'difficulty_level',
            $payload,
            'Boss segment MUST NOT carry difficulty_level (DB CHECK enforces XOR).'
        );

        Http::assertSent(function (Request $req) {
            $body = $req->data();
            $this->assertArrayNotHasKey('difficulty_level', $body);
            $this->assertArrayNotHasKey('boss_level', $body);
            return true;
        });
    }

    /**
     * REQUIREMENT 6 — structural anti-regression net. The class file
     * itself MUST contain zero direct-Gemini residues. This is the
     * cheapest guard and the one that protects #87's architectural
     * promise even against refactors that bypass the behavioural tests.
     */
    public function test_class_file_contains_no_direct_gemini_call(): void
    {
        $reflector = new \ReflectionClass(BankAIGenerator::class);
        $source = file_get_contents($reflector->getFileName());

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            'generativelanguage.googleapis.com',
            $source,
            'BankAIGenerator must NEVER hit the Gemini REST endpoint directly. All AI traffic goes through the Node router (#87 / #88).'
        );
        $this->assertStringNotContainsString(
            'GEMINI_API_KEY',
            $source,
            'BankAIGenerator must NEVER read GEMINI_API_KEY. Provider credentials live exclusively on the Node router.'
        );
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Minimal valid segment shaped like one row from BankNeedsCalculator.
     */
    private function soloSegment(array $over = []): array
    {
        return array_replace([
            'domain' => 'Sciences',
            'sub_domain' => 'physique',
            'cognitive_type' => 'recognition',
            'question_type' => 'qcm',
            'depth_range' => [1, 3],
            'mode_target' => ['type' => 'solo_range', 'levels' => [1, 9]],
        ], $over);
    }

    /**
     * Minimal valid router success envelope. Contract-shaped so
     * shapeIntoPayload() is exercised end-to-end.
     */
    private function okEnvelope(array $over = []): array
    {
        $payload = [
            'concept_id' => 'concept-test-123',
            'concept_family' => 'physique-mecanique',
            'source' => 'gemini',
            'translations' => [
                'fr' => [
                    'question_text' => 'Quelle est la première loi de Newton ?',
                    'answer_a' => 'Inertie',
                    'answer_b' => 'F = ma',
                    'answer_c' => 'Action-réaction',
                    'answer_d' => 'Gravitation',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Loi de l’inertie.',
                    'saviez_vous' => 'Énoncée par Newton dans les Principia (1687).',
                ],
                'en' => [
                    'question_text' => "What is Newton's first law?",
                    'answer_a' => 'Inertia',
                    'answer_b' => 'F = ma',
                    'answer_c' => 'Action-reaction',
                    'answer_d' => 'Gravitation',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Law of inertia.',
                    'saviez_vous' => 'Stated by Newton in Principia (1687).',
                ],
                'es' => [
                    'question_text' => '¿Cuál es la primera ley de Newton?',
                    'answer_a' => 'Inercia',
                    'answer_b' => 'F = ma',
                    'answer_c' => 'Acción-reacción',
                    'answer_d' => 'Gravitación',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Ley de la inercia.',
                    'saviez_vous' => 'Enunciada por Newton en los Principia (1687).',
                ],
            ],
        ];

        // Shallow override on the payload (e.g. switch source to "openai").
        $payload = array_replace($payload, $over);

        return ['ok' => true, 'payload' => $payload];
    }
}
