<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_quarantine_requests', function (Blueprint $table): void {
            $table->string('request_id', 36)->primary();
            $table->string('idempotency_key', 128)->unique();
            $table->string('blueprint_id', 36);
            $table->string('origin_phase', 64);
            $table->string('cause_code', 96);
            $table->jsonb('cause_payload')->nullable();
            $table->string('copy_id', 36)->nullable()->unique();
            $table->string('state', 16)->default('OPEN');
            $table->timestamps();
            $table->foreign('blueprint_id', 'kqr_blueprint_fk')
                ->references('blueprint_id')->on('kernel_blueprint_runs')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::table('kernel_quarantine_work_copies', function (Blueprint $table): void {
            $table->string('request_id', 36)->nullable()->unique();
            $table->string('intake_idempotency_key', 128)->nullable()->unique();
            $table->string('cause_code', 96)->nullable();
            $table->jsonb('cause_payload')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->index(['state', 'claim_expires_at'], 'kqwc_claim_expiry_idx');
            $table->foreign('request_id', 'kqwc_request_fk')
                ->references('request_id')->on('kernel_quarantine_requests')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('kernel_quarantine_transition_history', function (Blueprint $table): void {
            $table->string('transition_id', 36)->primary();
            $table->string('request_id', 36)->nullable();
            $table->string('copy_id', 36);
            $table->string('from_state', 16)->nullable();
            $table->string('to_state', 16);
            $table->unsignedBigInteger('copy_version');
            $table->string('reason_code', 96);
            $table->jsonb('payload')->nullable();
            $table->timestamp('created_at');
            $table->foreign('request_id', 'kqth_request_fk')
                ->references('request_id')->on('kernel_quarantine_requests')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('copy_id', 'kqth_copy_fk')
                ->references('copy_id')->on('kernel_quarantine_work_copies')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['copy_id', 'created_at'], 'kqth_copy_time_idx');
        });

        Schema::create('kernel_quarantine_resume_intents', function (Blueprint $table): void {
            $table->string('intent_id', 36)->primary();
            $table->string('idempotency_key', 128)->unique();
            $table->string('copy_id', 36);
            $table->unsignedBigInteger('copy_version');
            $table->string('owner_phase', 64);
            $table->string('operation', 64);
            $table->string('cognitive_type', 64)->nullable();
            $table->string('language_code', 2)->nullable();
            $table->string('state', 16)->default('OPEN');
            $table->jsonb('payload')->nullable();
            $table->timestamps();
            $table->foreign('copy_id', 'kqri_copy_fk')
                ->references('copy_id')->on('kernel_quarantine_work_copies')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['copy_id', 'copy_version'], 'kqri_copy_version_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE kernel_quarantine_work_copies DROP CONSTRAINT IF EXISTS kqwc_state_check");
            DB::statement("ALTER TABLE kernel_quarantine_work_copies
                ADD CONSTRAINT kqwc_state_check
                CHECK (state IN ('EDITABLE','READY','IN_FLIGHT'))");
            DB::statement('ALTER TABLE kernel_current_kernel_dispatches DROP CONSTRAINT IF EXISTS kckd_direction_check');
            DB::statement("ALTER TABLE kernel_current_kernel_dispatches
                ADD CONSTRAINT kckd_direction_check
                CHECK (direction IN ('QUARANTINE','KBP','BLOCKED'))");
            DB::statement("CREATE OR REPLACE FUNCTION kqth_immutable() RETURNS trigger
                LANGUAGE plpgsql AS \$\$
                BEGIN RAISE EXCEPTION 'Quarantine transition history is immutable'; END;
                \$\$");
            DB::statement("CREATE TRIGGER kqth_no_mutation
                BEFORE UPDATE OR DELETE ON kernel_quarantine_transition_history
                FOR EACH ROW EXECUTE FUNCTION kqth_immutable()");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS kqth_no_mutation ON kernel_quarantine_transition_history');
            DB::statement('DROP FUNCTION IF EXISTS kqth_immutable()');
            DB::statement('ALTER TABLE kernel_quarantine_work_copies DROP CONSTRAINT IF EXISTS kqwc_state_check');
            DB::statement('ALTER TABLE kernel_current_kernel_dispatches DROP CONSTRAINT IF EXISTS kckd_direction_check');
            DB::statement("ALTER TABLE kernel_current_kernel_dispatches
                ADD CONSTRAINT kckd_direction_check
                CHECK (direction IN ('QUARANTINE','KBP'))");
        }
        Schema::dropIfExists('kernel_quarantine_resume_intents');
        Schema::dropIfExists('kernel_quarantine_transition_history');
        Schema::table('kernel_quarantine_work_copies', function (Blueprint $table): void {
            $table->dropForeign('kqwc_request_fk');
            $table->dropUnique('kernel_quarantine_work_copies_request_id_unique');
            $table->dropUnique('kernel_quarantine_work_copies_intake_idempotency_key_unique');
            $table->dropIndex('kqwc_claim_expiry_idx');
            $table->dropColumn([
                'request_id', 'intake_idempotency_key', 'cause_code',
                'cause_payload', 'claim_expires_at',
            ]);
        });
        Schema::dropIfExists('kernel_quarantine_requests');
    }
};