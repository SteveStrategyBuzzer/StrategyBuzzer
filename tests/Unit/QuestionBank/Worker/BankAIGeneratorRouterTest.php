<?php

namespace Tests\Unit\QuestionBank\Worker;

use App\Services\QuestionBank\Worker\BankAIGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BankAIGeneratorRouterTest extends TestCase
{
    private const ROUTER_BASE = 'http://test-router.local';

    private ?string $previousQuestionApiUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousQuestionApiUrl = getenv('QUESTION_API_URL') ?: null;
        putenv('QUESTION_API_URL='.self::ROUTER_BASE);
        $_ENV['QUESTION_API_URL'] = self::ROUTER_BASE;
        $_SERVER['QUESTION_API_URL'] = self::ROUTER_BASE;

        config()->set('question_bank_profiles.worker.preferred_languages', ['fr', 'en', 'es']);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
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

    public function test_calls_exactly_the_bank_refill_router_endpoint(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());

        $this->assertTrue($result['ok']);
        Http::assertSent(fn (Request $req) =>
            $req->method() === 'POST'
            && $req->url() === self::ROUTER_BASE.'/generate-bank-question'
        );
        Http::assertSentCount(1);
    }

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
            'mode_target' => ['type' => 'solo_range', 'levels' => [21, 39]],
        ]);

        (new BankAIGenerator())->generateForSegment($segment);

        Http::assertSent(function (Request $req) {
            $body = $req->data();

            // The 6 always-present fields
            $this->assertArrayHasKey('domain', $body);
            $this->assertArrayHasKey('sub_domain', $body);
            $this->assertArrayHasKey('cognitive_type', $body);
            $this->assertArrayHasKey('question_type', $body);
            $this->assertArrayHasKey('difficulty_depth', $body);
            $this->assertArrayHasKey('languages', $body);
            // #91 contract: body MUST include exactly one of difficulty_level
            // or boss_level (segment-context XOR).
            $this->assertArrayHasKey('difficulty_level', $body);

            $this->assertSame('Histoire', $body['domain']);
            $this->assertSame('rome-antique', $body['sub_domain']);
            $this->assertSame('reasoning', $body['cognitive_type']);
            $this->assertSame('qcm', $body['question_type']);
            $this->assertSame(5, $body['difficulty_depth']);
            $this->assertSame(21, $body['difficulty_level']);

            $this->assertIsArray($body['languages']);
            $this->assertSame(['fr', 'en', 'es'], $body['languages']);

            // 7th field — the level-context XOR. This Solo segment must
            // carry difficulty_level pinned to the low end of the band.
            $this->assertArrayHasKey('difficulty_level', $body);
            $this->assertSame(21, $body['difficulty_level']);
            $this->assertArrayNotHasKey('boss_level', $body);

            return true;
        });
    }

    public function test_router_payload_source_is_returned_unchanged(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question'
                => Http::response($this->okEnvelope(['source' => 'openai']), 200),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());

        $this->assertTrue($result['ok']);
        $this->assertSame('openai', $result['payload']['source']);
    }

    /**
     * Plumbing assertion (DB-free): the payload returned by generateForSegment()
     * must carry the exact shape that QuestionBankRepository::addToBank()
     * consumes — including the router-reported `source`. This locks the
     * contract between generator and writer without touching the DB.
     */
    public function test_router_payload_shape_matches_addToBank_input_contract(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question'
                => Http::response($this->okEnvelope([
                    'source' => 'openai',
                    'concept_id' => 'plumbing-test-1',
                    'concept_family' => 'physique-mecanique',
                ]), 200),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());
        $this->assertTrue($result['ok']);

        $payload = $result['payload'];

        // Every key addToBank() reads must be present on the payload, with
        // the router-reported source preserved verbatim.
        foreach ([
            'difficulty_depth', 'domain', 'sub_domain', 'question_type',
            'cognitive_type', 'concept_id', 'concept_family', 'source',
            'translations',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "addToBank input must contain `{$key}`.");
        }
        $this->assertSame('openai', $payload['source']);
        $this->assertSame('plumbing-test-1', $payload['concept_id']);
        $this->assertSame('physique-mecanique', $payload['concept_family']);
        $this->assertIsArray($payload['translations']);
        $this->assertNotEmpty($payload['translations']);
    }

    public function test_router_503_returns_structured_failure(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response(
                ['error' => 'all_providers_exhausted'],
                503
            ),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
        $this->assertSame(503, $result['http_status']);

        // Backoff is the worker's job — no internal retry.
        Http::assertSentCount(1);
    }

    /**
     * Solo segment XOR (#91 official contract):
     *  - body MUST contain difficulty_level (anchored on the LOW end of the band)
     *  - body MUST NOT contain boss_level
     * Output payload carries the same XOR for addToBank().
     */
    public function test_solo_segment_request_body_includes_difficulty_level_and_excludes_boss_level(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $segment = $this->soloSegment([
            'mode_target' => ['type' => 'solo_range', 'levels' => [11, 19]],
        ]);

        $result = (new BankAIGenerator())->generateForSegment($segment);

        // Request body: difficulty_level present, boss_level absent.
        Http::assertSent(function (Request $req) {
            $body = $req->data();
            $this->assertArrayHasKey(
                'difficulty_level',
                $body,
                'Solo segment MUST send difficulty_level in the router body.'
            );
            $this->assertSame(11, $body['difficulty_level']);
            $this->assertArrayNotHasKey(
                'boss_level',
                $body,
                'Solo segment MUST NOT send boss_level in the router body.'
            );
            return true;
        });

        // Output payload (bound for addToBank) mirrors the same XOR.
        $payload = $result['payload'];
        $this->assertArrayHasKey('difficulty_level', $payload);
        $this->assertSame(11, $payload['difficulty_level']);
        $this->assertArrayNotHasKey('boss_level', $payload);
    }

    /**
     * Boss segment XOR (#91 official contract):
     *  - body MUST contain boss_level
     *  - body MUST NOT contain difficulty_level
     * Output payload carries the same XOR for addToBank().
     */
    public function test_boss_segment_request_body_includes_boss_level_and_excludes_difficulty_level(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $segment = $this->soloSegment([
            'mode_target' => ['type' => 'boss', 'level' => 30],
        ]);

        $result = (new BankAIGenerator())->generateForSegment($segment);

        // Request body: boss_level present, difficulty_level absent.
        Http::assertSent(function (Request $req) {
            $body = $req->data();
            $this->assertArrayHasKey(
                'boss_level',
                $body,
                'Boss segment MUST send boss_level in the router body.'
            );
            $this->assertSame(30, $body['boss_level']);
            $this->assertArrayNotHasKey(
                'difficulty_level',
                $body,
                'Boss segment MUST NOT send difficulty_level in the router body.'
            );
            return true;
        });

        // Persisted payload (bound for addToBank): same XOR.
        $payload = $result['payload'];
        $this->assertArrayHasKey('boss_level', $payload);
        $this->assertSame(30, $payload['boss_level']);
        $this->assertArrayNotHasKey('difficulty_level', $payload);
    }

    /**
     * Structural guard: the class file must contain no direct-Gemini
     * residue. Cheapest net against a refactor that bypasses the
     * behavioural tests above.
     */
    public function test_class_file_contains_no_direct_gemini_call(): void
    {
        $source = file_get_contents((new \ReflectionClass(BankAIGenerator::class))->getFileName());

        $this->assertIsString($source);
        $this->assertStringNotContainsString('generativelanguage.googleapis.com', $source);
        $this->assertStringNotContainsString('GEMINI_API_KEY', $source);
    }

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

    private function okEnvelope(array $over = []): array
    {
        $payload = array_replace([
            'concept_id' => 'concept-test-123',
            'concept_family' => 'physique-mecanique',
            'source' => 'gemini',
            'translations' => [
                'fr' => [
                    'question_text' => 'Quelle est la première loi de Newton ?',
                    'answer_a' => 'Inertie', 'answer_b' => 'F = ma',
                    'answer_c' => 'Action-réaction', 'answer_d' => 'Gravitation',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Loi de l’inertie.',
                    'saviez_vous' => 'Énoncée par Newton dans les Principia (1687).',
                ],
                'en' => [
                    'question_text' => "What is Newton's first law?",
                    'answer_a' => 'Inertia', 'answer_b' => 'F = ma',
                    'answer_c' => 'Action-reaction', 'answer_d' => 'Gravitation',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Law of inertia.',
                    'saviez_vous' => 'Stated by Newton in Principia (1687).',
                ],
                'es' => [
                    'question_text' => '¿Cuál es la primera ley de Newton?',
                    'answer_a' => 'Inercia', 'answer_b' => 'F = ma',
                    'answer_c' => 'Acción-reacción', 'answer_d' => 'Gravitación',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Ley de la inercia.',
                    'saviez_vous' => 'Enunciada por Newton en los Principia (1687).',
                ],
            ],
        ], $over);

        return ['ok' => true, 'payload' => $payload];
    }
}
