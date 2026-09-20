<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 owns translation creation state separately from the Phase 1 slots.
 * The source slots remain read-only to this phase; ValidationPhase2 is a later
 * owner and is deliberately not represented here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_phase2_translation_units', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->string('language_code', 2);
            $table->char('source_revision', 64);
            $table->unsignedBigInteger('translation_revision')->nullable();
            $table->string('state', 32)->default('PENDING');
            $table->string('creation_status', 16)->default('PENDING');
            $table->string('validation_status', 16)->default('NOT_VALIDATED');
            $table->unsignedInteger('retry_cycle')->default(0);
            $table->unsignedInteger('attempt_number')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('claim_expires_at')->nullable();
            $table->char('source_payload_hash', 64);
            $table->jsonb('translation')->nullable();
            $table->char('translation_hash', 64)->nullable();
            $table->unsignedBigInteger('yellow_revision')->nullable();
            $table->string('last_technical_reason_code', 96)->nullable();
            $table->timestampTz('permanent_failed_at')->nullable();
            $table->jsonb('provider_metadata')->nullable();
            $table->timestampsTz();

            $table->primary(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision'],
                'kp2tu_identity_pk'
            );
            $table->foreign('blueprint_id', 'kp2tu_blueprint_fk')
                ->references('blueprint_id')->on('kernel_blueprint_runs')->restrictOnDelete();
            $table->index(['state', 'next_attempt_at'], 'kp2tu_due_idx');
            $table->index(['blueprint_id', 'state'], 'kp2tu_blueprint_state_idx');
        });

        Schema::create('kernel_phase2_translation_attempts', function (Blueprint $table): void {
            $table->uuid('operation_id')->primary();
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->string('language_code', 2);
            $table->char('source_revision', 64);
            $table->unsignedBigInteger('expected_translation_revision')->nullable();
            $table->unsignedInteger('retry_cycle');
            $table->unsignedInteger('attempt_number');
            $table->uuid('claim_token');
            $table->uuid('provider_request_reference');
            $table->uuid('external_idempotency_key');
            $table->jsonb('internal_envelope');
            $table->string('outcome', 40)->default('OPEN');
            $table->timestampTz('applied_at')->nullable();
            $table->string('provider_request_id', 255)->nullable();
            $table->char('response_hash', 64)->nullable();
            $table->timestampsTz();
            $table->unique('external_idempotency_key', 'kp2ta_external_key_unique');
            $table->unique('provider_request_reference', 'kp2ta_provider_ref_unique');
            $table->index(['blueprint_id', 'cognitive_type', 'language_code'], 'kp2ta_unit_idx');
            $table->foreign(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision'],
                'kp2ta_unit_fk'
            )->references(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision']
            )->on('kernel_phase2_translation_units')->restrictOnDelete();
        });

        Schema::create('kernel_phase2_operation_signals', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->string('language_code', 2);
            $table->char('source_revision', 64);
            $table->unsignedBigInteger('translation_revision')->nullable();
            $table->unsignedInteger('retry_cycle');
            $table->string('technical_reason_code', 96);
            $table->string('owner_phase', 16)->default('PHASE2');
            $table->timestampTz('blocked_at');
            $table->timestampsTz();
            $table->index(['blueprint_id', 'language_code', 'source_revision'], 'kp2os_unit_idx');
            $table->foreign(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision'],
                'kp2os_unit_fk'
            )->references(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision']
            )->on('kernel_phase2_translation_units')->restrictOnDelete();
        });

        Schema::create('kernel_phase2_resolution_events', function (Blueprint $table): void {
            $table->uuid('resolution_event_id')->primary();
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->string('language_code', 2);
            $table->char('source_revision', 64);
            $table->string('authorized_by', 32);
            $table->string('authorization_reason_code', 96);
            $table->unsignedInteger('retry_cycle');
            $table->timestampTz('authorized_at');
            $table->unique(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision', 'resolution_event_id'],
                'kp2re_event_identity_unique'
            );
            $table->foreign(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision'],
                'kp2re_unit_fk'
            )->references(
                ['blueprint_id', 'cognitive_type', 'language_code', 'source_revision']
            )->on('kernel_phase2_translation_units')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_type_check CHECK (cognitive_type IN ('QCM_RECOGNITION','QCM_REASONING','QCM_TRAP','TRUE_FALSE_RECOGNITION_TRUE','TRUE_FALSE_RECOGNITION_FALSE','TRUE_FALSE_REASONING_TRUE','TRUE_FALSE_REASONING_FALSE'))");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_language_check CHECK (language_code IN ('fr','es','de','it','pt','ru','zh','ar','el'))");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_state_check CHECK (state IN ('PENDING','IN_PROGRESS','CREATED','RETRYABLE_FAILURE','PERMANENT_FAILURE'))");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_creation_status_check CHECK (creation_status IN ('PENDING','CREATED'))");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_validation_status_check CHECK (validation_status = 'NOT_VALIDATED')");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_status_consistency_check CHECK ((state <> 'CREATED' OR creation_status = 'CREATED') AND (creation_status = 'PENDING' OR (translation IS NOT NULL AND translation_revision IS NOT NULL)))");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_attempt_check CHECK (attempt_number BETWEEN 0 AND 4)");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_revision_check CHECK (translation_revision IS NULL OR translation_revision >= 1)");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_created_check CHECK (state <> 'CREATED' OR (translation IS NOT NULL AND translation_revision IS NOT NULL))");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_translation_object_check CHECK (translation IS NULL OR jsonb_typeof(translation) = 'object')");
            DB::statement("ALTER TABLE kernel_phase2_translation_units ADD CONSTRAINT kp2tu_claim_check CHECK ((state = 'IN_PROGRESS') = (claim_token IS NOT NULL AND claimed_at IS NOT NULL AND claim_expires_at IS NOT NULL))");
            DB::statement("ALTER TABLE kernel_phase2_translation_attempts ADD CONSTRAINT kp2ta_outcome_check CHECK (outcome IN ('OPEN','APPLIED','NO_OP','RETRYABLE_TECHNICAL_FAILURE','NON_RETRYABLE_TECHNICAL_FAILURE','STALE_RESULT'))");
            DB::statement("ALTER TABLE kernel_phase2_operation_signals ADD CONSTRAINT kp2os_owner_phase_check CHECK (owner_phase = 'PHASE2')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_phase2_resolution_events');
        Schema::dropIfExists('kernel_phase2_operation_signals');
        Schema::dropIfExists('kernel_phase2_translation_attempts');
        Schema::dropIfExists('kernel_phase2_translation_units');
    }
};