<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kernel_validation_phase2_runs', function (Blueprint $table): void {
            $table->uuid('validation_run_id')->primary();
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->string('language_code', 2);
            $table->char('source_revision', 64);
            $table->unsignedBigInteger('translation_revision');
            $table->unsignedInteger('retry_cycle')->default(0);
            $table->unsignedInteger('attempt_number')->default(0);
            $table->unsignedBigInteger('yellow_revision')->nullable();
            $table->string('status', 32)->default('NOT_VALIDATED');
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('claim_expires_at')->nullable();
            $table->uuid('validation_request_reference')->unique();
            $table->uuid('external_idempotency_key')->unique();
            $table->uuid('current_attempt_id')->nullable();
            $table->string('validator_request_id', 255)->nullable();
            $table->char('response_hash', 64)->nullable();
            $table->string('technical_reason_code', 96)->nullable();
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->unique(['blueprint_id','cognitive_type','language_code','source_revision','translation_revision'], 'kv2_current_identity');
            $table->index(['blueprint_id','status'], 'kv2_blueprint_status');
        });
        Schema::create('kernel_validation_phase2_attempts', function (Blueprint $table): void {
            $table->uuid('attempt_id')->primary();
            $table->uuid('validation_run_id');
            $table->unsignedInteger('retry_cycle');
            $table->unsignedInteger('attempt_number');
            $table->uuid('claim_token');
            $table->uuid('validation_request_reference')->unique();
            $table->uuid('external_idempotency_key')->unique();
            $table->char('source_payload_hash', 64);
            $table->char('target_payload_hash', 64);
            $table->unsignedBigInteger('expected_translation_revision');
            $table->unsignedBigInteger('yellow_revision')->nullable();
            $table->string('outcome', 48)->default('OPEN');
            $table->char('response_hash', 64)->nullable();
            $table->string('validator_request_id', 255)->nullable();
            $table->jsonb('internal_envelope');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->foreign('validation_run_id')->references('validation_run_id')->on('kernel_validation_phase2_runs')->restrictOnDelete();
            $table->unique(['validation_run_id','retry_cycle','attempt_number'], 'kv2_attempt_identity');
        });
        Schema::create('kernel_validation_phase2_findings', function (Blueprint $table): void {
            $table->uuid('finding_id')->primary();
            $table->uuid('validation_run_id');
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->string('language_code', 2);
            $table->char('source_revision', 64);
            $table->unsignedBigInteger('translation_revision');
            $table->string('field_path', 96);
            $table->string('rule_code', 96);
            $table->string('severity', 16);
            $table->jsonb('evidence');
            $table->timestampTz('created_at');
            $table->foreign('validation_run_id')->references('validation_run_id')->on('kernel_validation_phase2_runs')->restrictOnDelete();
        });
        Schema::create('kernel_validation_phase2_resolution_events', function (Blueprint $table): void {
            $table->uuid('resolution_event_id')->primary();
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->string('language_code', 2);
            $table->char('source_revision', 64);
            $table->unsignedInteger('retry_cycle');
            $table->unsignedBigInteger('translation_revision');
            $table->unsignedBigInteger('yellow_revision')->nullable();
            $table->string('authorized_by', 32);
            $table->string('reason_code', 96);
            $table->timestampTz('created_at');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE kernel_validation_phase2_runs ADD CONSTRAINT kv2_status_check CHECK (status IN ('NOT_VALIDATED','IN_PROGRESS','PASS','SUSPICION','RETRYABLE_FAILURE','PERMANENT_FAILURE'))");
            DB::statement("ALTER TABLE kernel_validation_phase2_runs ADD CONSTRAINT kv2_attempt_check CHECK (attempt_number BETWEEN 0 AND 4)");
            DB::statement("ALTER TABLE kernel_validation_phase2_runs ADD CONSTRAINT kv2_state_shape_check CHECK ((status = 'IN_PROGRESS') = (claim_token IS NOT NULL AND claim_expires_at IS NOT NULL) AND (status <> 'RETRYABLE_FAILURE' OR next_attempt_at IS NOT NULL) AND (status <> 'PERMANENT_FAILURE' OR next_attempt_at IS NULL))");
            DB::statement("ALTER TABLE kernel_validation_phase2_findings ADD CONSTRAINT kv2_severity_check CHECK (severity = 'BLOCKING' AND evidence <> '{}'::jsonb)");
            DB::statement("ALTER TABLE kernel_validation_phase2_attempts ADD CONSTRAINT kv2_attempt_outcome_check CHECK (outcome IN ('OPEN','APPLIED','NO_OP','RETRYABLE_TECHNICAL_FAILURE','NON_RETRYABLE_TECHNICAL_FAILURE','STALE_RESULT'))");
            DB::statement("ALTER TABLE kernel_validation_phase2_attempts ADD CONSTRAINT kv2_attempt_number_check CHECK (attempt_number BETWEEN 1 AND 4)");
            DB::statement("CREATE OR REPLACE FUNCTION kv2_findings_immutable() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'ValidationPhase2 findings are immutable'; END; \$\$");
            DB::statement("CREATE TRIGGER kv2_findings_no_update_delete BEFORE UPDATE OR DELETE ON kernel_validation_phase2_findings FOR EACH ROW EXECUTE FUNCTION kv2_findings_immutable()");
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('kernel_validation_phase2_resolution_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS kv2_findings_no_update_delete ON kernel_validation_phase2_findings');
            DB::statement('DROP FUNCTION IF EXISTS kv2_findings_immutable()');
        }
        Schema::dropIfExists('kernel_validation_phase2_findings');
        Schema::dropIfExists('kernel_validation_phase2_attempts');
        Schema::dropIfExists('kernel_validation_phase2_runs');
    }
};