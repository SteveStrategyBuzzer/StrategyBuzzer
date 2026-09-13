<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Quarantine;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Phase1\KernelQuarantinePhase1Provider;
use App\Services\QuestionBank\Phase1\KernelQuarantinePhase1ResumptionOrchestrator;
use App\Services\QuestionBank\Phase1\KernelQuarantinePhase1EntryBoundary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class KernelQuarantinePhase1EntryBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('kernel_code', 23)->nullable();
        });
        Schema::create('kernel_quarantine_work_copies', function (Blueprint $table): void {
            $table->string('copy_id', 36)->primary();
            $table->string('blueprint_id', 36);
            $table->string('kernel_code', 23);
            $table->unsignedBigInteger('copy_version')->default(1);
            $table->string('claim_token', 128)->nullable();
            $table->string('state', 16)->default('IN_FLIGHT');
        });
        Schema::create('kernel_quarantine_work_copy_slots', function (Blueprint $table): void {
            $table->string('copy_id', 36);
            $table->string('cognitive_type', 64);
            $table->json('source')->default('{}');
            $table->json('creation_failure')->nullable();
            $table->string('creation_status')->default('CREATED');
            $table->string('validation_status')->default('PASS');
            $table->json('validation_findings')->default('[]');
            $table->string('color')->default('GREEN');
            $table->unsignedBigInteger('slot_revision')->default(1);
            $table->unsignedBigInteger('manual_revision')->default(0);
            $table->timestamps();
            $table->primary(['copy_id', 'cognitive_type']);
        });
        Schema::create('kernel_quarantine_slot_resumptions', function (Blueprint $table): void {
            $table->string('copy_id', 36);
            $table->string('cognitive_type', 64);
            $table->unsignedBigInteger('resumption_number')->default(1);
            $table->unsignedBigInteger('copy_version');
            $table->unsignedBigInteger('manual_revision')->default(0);
            $table->boolean('phase1_remaining')->default(true);
            $table->boolean('phase1_creation_required')->default(false);
            $table->boolean('validation_phase1_remaining')->default(true);
            $table->boolean('phase2_remaining')->default(false);
            $table->boolean('validation_phase2_remaining')->default(false);
            $table->string('current_stage')->default('PHASE1');
            $table->string('state', 16)->default('ACTIVE');
            $table->timestamps();
        });
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => 'bp-q', 'kernel_code' => '06-SCI-SUB-SUJ-IDE-0000',
        ]);
        DB::table('kernel_quarantine_work_copies')->insert([
            'copy_id' => 'copy-q', 'blueprint_id' => 'bp-q',
            'kernel_code' => '06-SCI-SUB-SUJ-IDE-0000', 'copy_version' => 3,
            'claim_token' => 'claim-q', 'state' => 'IN_FLIGHT',
        ]);
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            DB::table('kernel_quarantine_work_copy_slots')->insert([
                'copy_id' => 'copy-q', 'cognitive_type' => $type,
            ]);
        }
        DB::table('kernel_quarantine_slot_resumptions')->insert([
            'copy_id' => 'copy-q', 'cognitive_type' => 'QCM_RECOGNITION',
            'copy_version' => 3, 'manual_revision' => 0,
            'resumption_number' => 1, 'phase1_remaining' => true,
            'validation_phase1_remaining' => true, 'phase2_remaining' => false,
            'validation_phase2_remaining' => false, 'current_stage' => 'PHASE1',
            'state' => 'ACTIVE',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_quarantine_slot_resumptions');
        Schema::dropIfExists('kernel_quarantine_work_copy_slots');
        Schema::dropIfExists('kernel_quarantine_work_copies');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_boundary_reloads_all_seven_slots_without_slots_argument(): void
    {
        self::assertSame(
            'bp-q',
            (new KernelQuarantinePhase1EntryBoundary())->receive('bp-q', 'copy-q', 3, 'claim-q')
        );
        self::assertFalse((bool) DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', 'copy-q')->value('phase1_remaining'));
        self::assertSame('VALIDATION_PHASE1', DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', 'copy-q')->value('current_stage'));
    }

    public function test_wrong_blueprint_version_or_token_is_stale(): void
    {
        $boundary = new KernelQuarantinePhase1EntryBoundary();
        foreach ([['other', 'copy-q', 3, 'claim-q'], ['bp-q', 'copy-q', 2, 'claim-q'], ['bp-q', 'copy-q', 3, 'wrong']] as $args) {
            try {
                $boundary->receive(...$args);
                self::fail('stale quarantine reference must be rejected');
            } catch (LogicException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_empty_slot_calls_provider_persists_copy_source_then_progresses(): void
    {
        DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', 'copy-q')->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['creation_status' => 'EMPTY', 'color' => 'RED']);
        DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', 'copy-q')->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['phase1_creation_required' => true]);

        $provider = new class implements KernelQuarantinePhase1Provider {
            public int $calls = 0;
            public function create(object $copy, array $slots): array
            {
                $this->calls++;
                return ['QCM_RECOGNITION' => ['question' => 'created-in-copy']];
            }
        };
        $boundary = new KernelQuarantinePhase1EntryBoundary(
            new KernelQuarantinePhase1ResumptionOrchestrator($provider),
        );
        $boundary->receive('bp-q', 'copy-q', 3, 'claim-q');

        self::assertSame(1, $provider->calls);
        self::assertSame('CREATED', DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', 'copy-q')->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('creation_status'));
        self::assertSame('created-in-copy', DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', 'copy-q')->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('source->question'));
        self::assertFalse((bool) DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', 'copy-q')->value('phase1_remaining'));
    }

    public function test_provider_failure_keeps_empty_copy_and_phase1_pending(): void
    {
        DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', 'copy-q')->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['creation_status' => 'EMPTY', 'color' => 'RED']);
        DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', 'copy-q')->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['phase1_creation_required' => true]);
        $provider = new class implements KernelQuarantinePhase1Provider {
            public function create(object $copy, array $slots): array
            {
                throw new LogicException('provider failure');
            }
        };
        $boundary = new KernelQuarantinePhase1EntryBoundary(
            new KernelQuarantinePhase1ResumptionOrchestrator($provider),
        );
        $this->expectException(LogicException::class);
        try {
            $boundary->receive('bp-q', 'copy-q', 3, 'claim-q');
        } finally {
            self::assertSame('EMPTY', DB::table('kernel_quarantine_work_copy_slots')
                ->where('copy_id', 'copy-q')->where('cognitive_type', 'QCM_RECOGNITION')
                ->value('creation_status'));
            self::assertTrue((bool) DB::table('kernel_quarantine_slot_resumptions')
                ->where('copy_id', 'copy-q')->value('phase1_remaining'));
        }
    }
}