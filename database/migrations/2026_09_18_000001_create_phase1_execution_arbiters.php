<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Phase 1 execution arbitration.
 *
 * This table is deliberately separate from CognitiveSlot canonical_revision:
 * it arbitrates an execution lease, not slot content revisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_phase1_executions', function (Blueprint $table): void {
            $table->uuid('execution_id')->primary();
            $table->string('blueprint_id', 36);
            $table->char('identity_revision', 64);
            $table->string('state', 16)->default('ACTIVE');
            $table->uuid('lease_token');
            $table->jsonb('result')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('blueprint_id', 'kp1e_blueprint_id_fk')
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->restrictOnDelete();
            $table->unique(
                ['blueprint_id', 'identity_revision'],
                'kp1e_blueprint_identity_revision_unique'
            );
            $table->index(['blueprint_id', 'state'], 'kp1e_blueprint_state_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE kernel_phase1_executions
                 ADD CONSTRAINT kp1e_state_check
                 CHECK (state IN ('ACTIVE', 'COMPLETED'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_phase1_executions');
    }
};