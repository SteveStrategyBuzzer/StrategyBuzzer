<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class KernelBlueprintCognitiveSlotRepositoryTest extends TestCase
{
    private KernelBlueprintCognitiveSlotRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
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

        DB::table('kernel_blueprint_runs')->insert(['blueprint_id' => 'bp-slots']);
        $this->repository = new KernelBlueprintCognitiveSlotRepository();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_initialization_creates_exactly_the_seven_empty_types(): void
    {
        $slots = $this->repository->initializeEmptySlots('bp-slots');

        $this->assertCount(7, $slots);
        $this->assertEqualsCanonicalizing(KernelBlueprint::COGNITIVE_TYPES, array_keys($slots));
        foreach ($slots as $slot) {
            $this->assertSame('EMPTY', $slot['creation_status']);
            $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
            $this->assertNull($slot['source']);
            $this->assertNull($slot['creation_failure']);
            $this->assertSame([], $slot['translations']);
            $this->assertSame([], $slot['validation_findings']);
        }
    }

    public function test_duplicate_initialization_is_rejected_by_composite_key(): void
    {
        $this->repository->initializeEmptySlots('bp-slots');

        $this->expectException(QueryException::class);
        $this->repository->initializeEmptySlots('bp-slots');
    }

    public function test_repository_rejects_an_eighth_type(): void
    {
        $this->repository->initializeEmptySlots('bp-slots');

        $this->expectException(LogicException::class);
        $this->repository->writeCreated('bp-slots', 'EIGHTH_TYPE', ['question' => 'Interdit']);
    }

    public function test_created_transition_is_atomic_and_idempotent(): void
    {
        $this->repository->initializeEmptySlots('bp-slots');
        $source = ['question' => 'Quelle ville est la capitale de la France?'];

        $this->repository->writeCreated('bp-slots', 'QCM_RECOGNITION', $source);
        $this->repository->writeCreated('bp-slots', 'QCM_RECOGNITION', $source);

        $slot = $this->repository->find('bp-slots', 'QCM_RECOGNITION');
        $this->assertSame('CREATED', $slot['creation_status']);
        $this->assertSame($source, $slot['source']);
        $this->assertNull($slot['creation_failure']);
        $this->assertSame([], $slot['translations']);
    }

    public function test_creation_failed_transition_keeps_other_created_slots(): void
    {
        $this->repository->initializeEmptySlots('bp-slots');
        $this->repository->writeCreated(
            'bp-slots',
            'QCM_RECOGNITION',
            ['question' => 'Question valide']
        );
        $failure = [
            'reason_code' => 'PHASE1_TECHNICAL_FAILURE',
            'attempt_count' => 3,
            'last_failure_type' => 'INVALID_SCHEMA',
            'occurred_at' => '2026-08-30T00:00:00Z',
        ];
        $this->repository->writeCreationFailure(
            'bp-slots',
            'QCM_REASONING',
            $failure
        );

        $slots = $this->repository->allForBlueprint('bp-slots');
        $this->assertSame('CREATED', $slots['QCM_RECOGNITION']['creation_status']);
        $this->assertSame('CREATION_FAILED', $slots['QCM_REASONING']['creation_status']);
        $this->assertSame($failure, $slots['QCM_REASONING']['creation_failure']);
        $this->assertNull($slots['QCM_REASONING']['source']);
    }
}