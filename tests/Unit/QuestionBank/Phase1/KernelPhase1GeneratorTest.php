<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Phase1;

use App\Services\QuestionApi\QuestionApiClient;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use App\Services\QuestionBank\Phase1\KernelPhase1Generator;
use App\Services\QuestionBank\Phase1\KernelPhase1SourceValidator;
use App\Services\QuestionBank\Phase1\Phase1TechnicalException;
use App\Services\QuestionBank\Phase1\ValidationPhase1EntryBoundary;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class KernelPhase1GeneratorTest extends TestCase
{
    private KernelBlueprintCognitiveSlotRepository $repository;
    private KernelBlueprint $blueprint;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64);
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('subdomain_active')->nullable();
            $table->string('subject_active')->nullable();
            $table->text('dominant_idea_active')->nullable();
            $table->string('kernel_code_dd', 2)->nullable();
            $table->string('kernel_code_do', 3)->nullable();
            $table->string('kernel_code_sub', 3)->nullable();
            $table->string('kernel_code_suj', 3)->nullable();
            $table->string('kernel_code_ide', 3)->nullable();
            $table->string('kernel_code_vvvv', 4)->nullable();
            $table->string('kernel_code', 23)->nullable();
        });
        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table): void {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);
        });
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->json('source')->nullable();
            $table->json('creation_failure')->nullable();
            $table->json('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
            $table->json('validation_findings')->default('[]');
            $table->timestamps();
            $table->primary(['blueprint_id', 'cognitive_type']);
        });
        Schema::create('question_intents', function (Blueprint $table): void {
            $table->id();
            $table->json('frame_en')->nullable();
        });

        $this->blueprint = new KernelBlueprint();
        $this->blueprint->initializeBlueprintId('bp-phase1');
        $this->blueprint->fillRotation(4, 'science');
        $this->blueprint->fillTaxonomy('Physique', 'Lumière', 'Réfraction');
        $this->blueprint->fillVvvv('0000');

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $this->blueprint->blueprint_id,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth' => $this->blueprint->depth,
            'domain_code' => $this->blueprint->domain,
            'subdomain_active' => $this->blueprint->subdomain_active,
            'subject_active' => $this->blueprint->subject_active,
            'dominant_idea_active' => $this->blueprint->dominant_idea_active,
            'kernel_code_dd' => $this->blueprint->kernel_code_dd,
            'kernel_code_do' => $this->blueprint->kernel_code_do,
            'kernel_code_sub' => $this->blueprint->kernel_code_sub,
            'kernel_code_suj' => $this->blueprint->kernel_code_suj,
            'kernel_code_ide' => $this->blueprint->kernel_code_ide,
            'kernel_code_vvvv' => $this->blueprint->kernel_code_vvvv,
            'kernel_code' => $this->blueprint->kernel_code,
        ]);
        DB::table('kernel_blueprint_request_refs')->insert([
            'request_reference' => 'phase1:test',
            'blueprint_id' => $this->blueprint->blueprint_id,
        ]);
        DB::table('question_intents')->insert(['id' => 1, 'frame_en' => '{"legacy":true}']);

        $this->repository = new KernelBlueprintCognitiveSlotRepository();
        $slots = $this->repository->initializeEmptySlots((string) $this->blueprint->blueprint_id);
        $this->blueprint->initializeCognitiveSlots($slots);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('question_intents');
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
        Schema::dropIfExists('kernel_blueprint_request_refs');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_one_api_call_creates_seven_slots_without_mutating_section_one_or_frame_en(): void
    {
        $client = new Phase1FakeQuestionApiClient([$this->successResponse($this->payload())]);
        $before = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-phase1')->first();

        $result = $this->generator($client)->generate('bp-phase1');

        $this->assertSame('bp-phase1', $result);
        $this->assertSame(1, $client->calls);
        $this->assertSame(QuestionApiClient::ENDPOINT_KERNEL_PHASE1_SOURCE, $client->endpoints[0]);
        $slots = $this->repository->allForBlueprint('bp-phase1');
        $this->assertCount(7, $slots);
        foreach ($slots as $slot) {
            $this->assertSame('CREATED', $slot['creation_status']);
            $this->assertSame([], $slot['translations']);
            $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
        }
        $validationBlueprint = (new ValidationPhase1EntryBoundary())->receive($result);
        $this->assertSame('bp-phase1', $validationBlueprint->blueprint_id);
        $this->assertCount(7, $validationBlueprint->cognitive_slots);
        $this->assertEquals(
            $before,
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-phase1')->first()
        );
        $this->assertSame(
            '{"legacy":true}',
            DB::table('question_intents')->where('id', 1)->value('frame_en')
        );
    }

    public function test_three_transport_attempts_maximum_mark_all_slots_failed(): void
    {
        $client = new Phase1FakeQuestionApiClient([
            new RuntimeException('timeout 1'),
            new RuntimeException('timeout 2'),
            new RuntimeException('timeout 3'),
        ]);

        try {
            $this->generator($client)->generate('bp-phase1');
            $this->fail('Phase 1 ne doit pas transmettre blueprint_id après un échec total.');
        } catch (Phase1TechnicalException $exception) {
            $this->assertSame('CREATION_FAILED', $exception->failureType);
        }
        $this->assertSame(3, $client->calls);
        $slots = $this->repository->allForBlueprint('bp-phase1');
        $this->assertCount(7, $slots);
        foreach ($slots as $slot) {
            $this->assertSame(3, $slot['creation_failure']['attempt_count']);
            $this->assertSame('TRANSPORT', $slot['creation_failure']['last_failure_type']);
            $this->assertSame(
                KernelBlueprint::emptyCognitiveSlotSource($slot['cognitive_type']),
                $slot['source']
            );
        }
        $this->assertValidationPhaseOneRejects('bp-phase1');
    }

    public function test_validation_phase_one_rejects_blueprint_before_phase_one_finishes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CognitiveSlot non créé');

        (new ValidationPhase1EntryBoundary())->receive('bp-phase1');
    }

    public function test_idempotent_replay_does_not_call_api_again(): void
    {
        $client = new Phase1FakeQuestionApiClient([$this->successResponse($this->payload())]);
        $generator = $this->generator($client);
        $generator->generate('bp-phase1');

        $result = $generator->generate('bp-phase1');

        $this->assertSame('bp-phase1', $result);
        $this->assertSame(1, $client->calls);
    }

    public function test_valid_slots_are_preserved_when_one_slot_exhausts_schema_retries(): void
    {
        $payload = $this->payload();
        $payload['slots'][1]['question'] = implode(' ', array_fill(0, 21, 'mot'));
        $response = $this->successResponse($payload);
        $client = new Phase1FakeQuestionApiClient([$response, $response, $response]);

        try {
            $this->generator($client)->generate('bp-phase1');
            $this->fail('Phase 1 ne doit pas transmettre blueprint_id après un échec partiel.');
        } catch (Phase1TechnicalException $exception) {
            $this->assertSame('CREATION_FAILED', $exception->failureType);
        }
        $this->assertSame(3, $client->calls);
        $slots = $this->repository->allForBlueprint('bp-phase1');
        $this->assertSame(
            6,
            count(array_filter($slots, static fn (array $slot): bool => $slot['creation_status'] === 'CREATED'))
        );
        $this->assertSame(
            'CREATED',
            $slots['QCM_RECOGNITION']['creation_status']
        );
        $this->assertSame(
            'CREATION_FAILED',
            $slots['QCM_REASONING']['creation_status']
        );
        $this->assertValidationPhaseOneRejects('bp-phase1');
    }

    private function generator(QuestionApiClient $client): KernelPhase1Generator
    {
        return new KernelPhase1Generator(
            $client,
            $this->repository,
            new KernelPhase1SourceValidator()
        );
    }

    private function assertValidationPhaseOneRejects(string $blueprintId): void
    {
        try {
            (new ValidationPhase1EntryBoundary())->receive($blueprintId);
            $this->fail('Validation Phase 1 devait refuser un slot non CREATED.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('CognitiveSlot non créé', $exception->getMessage());
        }
    }

    private function successResponse(array $payload): Response
    {
        return new Response(new PsrResponse(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['ok' => true, 'result' => $payload], JSON_THROW_ON_ERROR)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $slots = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $index => $type) {
            $isQcm = str_starts_with($type, 'QCM_');
            $slots[] = [
                'cognitive_type' => $type,
                'question' => "Question cognitive distincte numéro {$index}?",
                'choices' => $isQcm
                    ? [
                        ['key' => 'a', 'text' => 'Paris'],
                        ['key' => 'b', 'text' => 'Rome'],
                        ['key' => 'c', 'text' => 'Madrid'],
                        ['key' => 'd', 'text' => 'Berlin'],
                    ]
                    : [
                        ['key' => 'a', 'text' => 'VRAI'],
                        ['key' => 'b', 'text' => 'FAUX'],
                    ],
                'correct_answer_key' => $isQcm || str_ends_with($type, '_TRUE') ? 'a' : 'b',
                'sv' => 'Cette explication courte relie clairement la réponse au contexte scientifique.',
                'creation_evidence' => [
                    'cognitive_operation' => 'Opération distincte',
                    'cognitive_justification' => 'Justification déterministe',
                    'difference_from_other_slots' => 'Proposition intellectuelle indépendante',
                    'truth_basis' => 'Fondement factuel vérifiable',
                    'trap_basis' => $type === 'QCM_TRAP' ? 'Confusion plausible documentée' : null,
                    'self_checks' => [
                        'question_readable_under_8_seconds' => true,
                        'sv_readable_under_30_seconds' => true,
                        'correct_answer_explained_by_sv' => true,
                        'cognitive_type_respected' => true,
                        'one_correct_answer_only' => true,
                        'choices_are_plausible' => true,
                        'distinct_from_other_slots' => true,
                        'same_subject_and_dominant_idea' => true,
                        'question_answer_choices_sv_coherent_with_subdomain' => true,
                    ],
                ],
            ];
        }

        return [
            'schema_version' => 'phase1.source.v1',
            'blueprint_id' => $this->blueprint->blueprint_id,
            'kernel_code' => $this->blueprint->kernel_code,
            'source_language' => 'fr',
            'slots' => $slots,
        ];
    }
}

class Phase1FakeQuestionApiClient extends QuestionApiClient
{
    public int $calls = 0;
    /** @var string[] */
    public array $endpoints = [];

    /** @param array<int, Response|\Throwable> $queue */
    public function __construct(private array $queue) {}

    public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
    {
        $this->calls++;
        $this->endpoints[] = $endpoint;
        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }
        if (! $next instanceof Response) {
            throw new RuntimeException('Fake response queue exhausted.');
        }

        return $next;
    }
}