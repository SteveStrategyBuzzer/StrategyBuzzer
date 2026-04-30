<?php

namespace Tests\Unit\QuestionBank\Worker;

use App\Models\QuestionGroup;
use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankAIGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BankAIGeneratorRouterTest extends TestCase
{
    use RefreshDatabase;

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

        // Any unmatched request fails the test instead of leaking real HTTP.
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
        ]);

        (new BankAIGenerator())->generateForSegment($segment);

        Http::assertSent(function (Request $req) {
            $body = $req->data();

            $this->assertArrayHasKey('domain', $body);
            $this->assertArrayHasKey('sub_domain', $body);
            $this->assertArrayHasKey('cognitive_type', $body);
            $this->assertArrayHasKey('question_type', $body);
            $this->assertArrayHasKey('difficulty_depth', $body);
            $this->assertArrayHasKey('languages', $body);

            $this->assertSame('Histoire', $body['domain']);
            $this->assertSame('rome-antique', $body['sub_domain']);
            $this->assertSame('reasoning', $body['cognitive_type']);
            $this->assertSame('qcm', $body['question_type']);
            $this->assertSame(5, $body['difficulty_depth']);

            $this->assertIsArray($body['languages']);
            $this->assertSame(['fr', 'en', 'es'], $body['languages']);

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
     * Plumbing assertion: the source the router reports must reach the
     * persisted row. Pipes the generator's payload into the real
     * QuestionBankRepository (sqlite in-memory) and reads the row back.
     */
    public function test_router_payload_source_reaches_persisted_row_via_addToBank(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question'
                => Http::response($this->okEnvelope(['source' => 'openai', 'concept_id' => 'plumbing-test-1']), 200),
        ]);

        $result = (new BankAIGenerator())->generateForSegment($this->soloSegment());
        $this->assertTrue($result['ok']);

        $group = (new QuestionBankRepository())->addToBank($result['payload']);

        $this->assertNotNull($group, 'addToBank should persist the generator payload.');
        $persisted = QuestionGroup::find($group->id);
        $this->assertNotNull($persisted);
        $this->assertSame('openai', $persisted->source, 'The router-reported source must be persisted, not a hardcoded label.');
        $this->assertSame('plumbing-test-1', $persisted->concept_id);
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
     * Contract-ambiguity guard: the strict reading of #91 req.5 says the
     * Solo router request body MUST include `difficulty_level`. The
     * production contract today (#87 implementation + the user's explicit
     * 6-field body list) sends only `difficulty_depth`. Captured as a
     * visible regression signal via markTestIncomplete — see follow-up
     * task for the product decision (send level metadata to the router or
     * formalise that bank metadata stays worker-side).
     */
    public function test_solo_segment_request_body_includes_difficulty_level(): void
    {
        $this->markTestIncomplete(
            'Production sends only difficulty_depth to /generate-bank-question. '
            .'Awaiting product decision: include level metadata in the router '
            .'body, or formalise level fields as worker-only metadata. See the '
            .'follow-up task filed alongside #91.'
        );
    }

    public function test_boss_segment_request_body_includes_boss_level(): void
    {
        $this->markTestIncomplete(
            'Production sends only difficulty_depth to /generate-bank-question. '
            .'Awaiting product decision: include level metadata in the router '
            .'body, or formalise level fields as worker-only metadata. See the '
            .'follow-up task filed alongside #91.'
        );
    }

    /**
     * Solo segment must not leak boss_level into the router request body
     * (regression: a future change sending boss_level for a Solo segment).
     * The output payload then carries the correct level field for addToBank.
     */
    public function test_solo_segment_does_not_leak_boss_level_into_request_body(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $segment = $this->soloSegment([
            'mode_target' => ['type' => 'solo_range', 'levels' => [11, 19]],
        ]);

        $result = (new BankAIGenerator())->generateForSegment($segment);

        Http::assertSent(function (Request $req) {
            $body = $req->data();
            $this->assertArrayNotHasKey(
                'boss_level',
                $body,
                'Solo segment must NEVER send boss_level to the router.'
            );
            return true;
        });

        // And the resulting payload (bound for addToBank) carries the
        // correct level field — the actual production XOR.
        $payload = $result['payload'];
        $this->assertArrayHasKey('difficulty_level', $payload);
        $this->assertSame(11, $payload['difficulty_level']);
        $this->assertArrayNotHasKey('boss_level', $payload);
    }

    /**
     * Boss segment must not leak difficulty_level into the router request
     * body (regression: a future change sending difficulty_level for a
     * Boss segment). The output payload then carries the correct level
     * field for addToBank.
     */
    public function test_boss_segment_does_not_leak_difficulty_level_into_request_body(): void
    {
        Http::fake([
            self::ROUTER_BASE.'/generate-bank-question' => Http::response($this->okEnvelope(), 200),
        ]);

        $segment = $this->soloSegment([
            'mode_target' => ['type' => 'boss', 'level' => 30],
        ]);

        $result = (new BankAIGenerator())->generateForSegment($segment);

        Http::assertSent(function (Request $req) {
            $body = $req->data();
            $this->assertArrayNotHasKey(
                'difficulty_level',
                $body,
                'Boss segment must NEVER send difficulty_level to the router.'
            );
            return true;
        });

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
