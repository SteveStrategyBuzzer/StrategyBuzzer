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
        Schema::table('kernel_quarantine_requests', function (Blueprint $table): void {
            $table->char('request_hash', 64)->after('idempotency_key');
        });
        Schema::table('kernel_quarantine_resume_intents', function (Blueprint $table): void {
            $table->char('intent_hash', 64)->after('idempotency_key');
            $table->string('expected_source_revision', 64)->nullable();
            $table->unsignedBigInteger('expected_translation_revision')->nullable();
            $table->unsignedBigInteger('expected_manual_revision')->nullable();
            $table->unsignedBigInteger('expected_yellow_revision')->nullable();
        });
        Schema::table('kernel_quarantine_work_copy_slots', function (Blueprint $table): void {
            $table->char('source_revision', 64)->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE kernel_quarantine_requests ADD CONSTRAINT kqr_cause_check
                CHECK (cause_code IN ('SUSPICION','CONTENT_UNTRANSLATABLE','MANUAL_CORRECTION'))");
            DB::statement("ALTER TABLE kernel_quarantine_resume_intents ADD CONSTRAINT kqri_owner_phase_check
                CHECK (owner_phase IN ('PHASE1','VALIDATION_PHASE1','PHASE2','VALIDATION_PHASE2'))");
            DB::statement("ALTER TABLE kernel_quarantine_resume_intents ADD CONSTRAINT kqri_operation_check
                CHECK (operation IN ('RESUME_PHASE1','RESUME_VALIDATION_PHASE1','RESUME_PHASE2','RESUME_VALIDATION_PHASE2','REGENERATE_SOURCE','REGENERATE_TRANSLATION'))");
            DB::statement("CREATE OR REPLACE FUNCTION kqwc_transition_guard() RETURNS trigger
                LANGUAGE plpgsql AS \$\$
                BEGIN
                    IF NOT (
                        (OLD.state = 'EDITABLE' AND NEW.state IN ('EDITABLE','READY')) OR
                        (OLD.state = 'READY' AND NEW.state IN ('READY','EDITABLE','IN_FLIGHT')) OR
                        (OLD.state = 'IN_FLIGHT' AND NEW.state IN ('IN_FLIGHT','READY'))
                    ) THEN
                        RAISE EXCEPTION 'Invalid Quarantine state transition % -> %', OLD.state, NEW.state;
                    END IF;
                    RETURN NEW;
                END;
                \$\$");
            DB::statement("CREATE TRIGGER kqwc_transition_guard BEFORE UPDATE OF state ON kernel_quarantine_work_copies
                FOR EACH ROW EXECUTE FUNCTION kqwc_transition_guard()");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE kernel_quarantine_resume_intents DROP CONSTRAINT IF EXISTS kqri_operation_check');
            DB::statement('ALTER TABLE kernel_quarantine_resume_intents DROP CONSTRAINT IF EXISTS kqri_owner_phase_check');
            DB::statement('ALTER TABLE kernel_quarantine_requests DROP CONSTRAINT IF EXISTS kqr_cause_check');
            DB::statement('DROP TRIGGER IF EXISTS kqwc_transition_guard ON kernel_quarantine_work_copies');
            DB::statement('DROP FUNCTION IF EXISTS kqwc_transition_guard()');
        }
        Schema::table('kernel_quarantine_work_copy_slots', fn (Blueprint $t) => $t->dropColumn('source_revision'));
        Schema::table('kernel_quarantine_resume_intents', function (Blueprint $t): void {
            $t->dropColumn(['intent_hash','expected_source_revision','expected_translation_revision','expected_manual_revision','expected_yellow_revision']);
        });
        Schema::table('kernel_quarantine_requests', fn (Blueprint $t) => $t->dropColumn('request_hash'));
    }
};