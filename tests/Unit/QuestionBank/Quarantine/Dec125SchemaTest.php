<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Quarantine;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class Dec125SchemaTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('kernel_code', 23)->nullable();
        });
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->json('source');
            $table->json('creation_failure')->nullable();
            $table->json('translations')->default('{}');
            $table->string('creation_status')->default('EMPTY');
            $table->string('validation_status')->default('NOT_VALIDATED');
            $table->json('validation_findings')->default('[]');
            $table->timestamps();
            $table->primary(['blueprint_id', 'cognitive_type']);
            $table->foreign('blueprint_id', 'kbcs_blueprint_id_fk')
                ->references('blueprint_id')->on('kernel_blueprint_runs')->cascadeOnDelete();
        });
        $this->migration = require base_path('database/migrations/2026_09_10_000001_create_dec125_quarantine_foundation.php');
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_additive_schema_has_revision_one_and_singleton_gate(): void
    {
        DB::table('kernel_blueprint_runs')->insert(['blueprint_id' => 'bp-schema']);
        DB::table('kernel_blueprint_cognitive_slots')->insert([
            'blueprint_id' => 'bp-schema', 'cognitive_type' => 'QCM_RECOGNITION',
            'source' => '{}',
        ]);
        self::assertSame(1, (int) DB::table('kernel_blueprint_cognitive_slots')->value('canonical_revision'));
        self::assertTrue(Schema::hasColumn('kernel_quarantine_work_copies', 'ready_request_id'));
        self::assertTrue(Schema::hasColumn('kernel_quarantine_work_copies', 'claimed_version'));
        self::assertTrue(Schema::hasColumn('kernel_quarantine_work_copy_slots', 'slot_revision'));
        self::assertTrue(Schema::hasColumn('kernel_quarantine_slot_resumptions', 'phase1_remaining'));
        self::assertTrue(Schema::hasColumn('kernel_current_kernel_route_gate', 'gate_id'));
        self::assertSame([], DB::table('kernel_current_kernel_dispatches')->get()->all());
    }
}