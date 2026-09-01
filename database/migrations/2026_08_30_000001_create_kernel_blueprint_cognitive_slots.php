<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase1 v1.0 — seven canonical CognitiveSlots per KernelBlueprint.
 *
 * Additive only:
 * - kernel_blueprint_runs remains the immutable Section 1 store;
 * - each slot is independently persisted under (blueprint_id, cognitive_type);
 * - question_intents.frame_en remains untouched legacy storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table) {
            // Exact type of kernel_blueprint_runs.blueprint_id: VARCHAR(36).
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            // EMPTY and CREATION_FAILED still carry the canonical source
            // skeleton; Phase 1 only replaces its nullable content fields.
            $table->jsonb('source');
            $table->jsonb('creation_failure')->nullable();
            $table->jsonb('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
            $table->jsonb('validation_findings')->default('[]');
            $table->timestamps();

            $table->primary(
                ['blueprint_id', 'cognitive_type'],
                'kbcs_blueprint_cognitive_type_pk'
            );

            $table->foreign('blueprint_id', 'kbcs_blueprint_id_fk')
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_cognitive_type_check
             CHECK (cognitive_type IN (
                 'QCM_RECOGNITION',
                 'QCM_REASONING',
                 'QCM_TRAP',
                 'TRUE_FALSE_RECOGNITION_TRUE',
                 'TRUE_FALSE_RECOGNITION_FALSE',
                 'TRUE_FALSE_REASONING_TRUE',
                 'TRUE_FALSE_REASONING_FALSE'
             ))"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_creation_status_check
             CHECK (creation_status IN (
                 'EMPTY',
                 'CREATED',
                 'CREATION_FAILED'
             ))"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_validation_status_check
             CHECK (validation_status IN (
                 'NOT_VALIDATED',
                 'PASS',
                 'SUSPICION'
             ))"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_source_object_check
             CHECK (jsonb_typeof(source) = 'object')"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_creation_failure_object_check
             CHECK (
                 creation_failure IS NULL
                 OR jsonb_typeof(creation_failure) = 'object'
             )"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_validation_findings_array_check
             CHECK (jsonb_typeof(validation_findings) = 'array')"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_translations_object_check
             CHECK (jsonb_typeof(translations) = 'object')"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_creation_state_check
             CHECK (
                 (
                     creation_status = 'EMPTY'
                     AND source IS NOT NULL
                     AND creation_failure IS NULL
                     AND validation_status = 'NOT_VALIDATED'
                     AND jsonb_array_length(validation_findings) = 0
                 )
                 OR
                 (
                     creation_status = 'CREATED'
                     AND source IS NOT NULL
                     AND creation_failure IS NULL
                 )
                 OR
                 (
                     creation_status = 'CREATION_FAILED'
                     AND source IS NOT NULL
                     AND creation_failure IS NOT NULL
                     AND validation_status = 'NOT_VALIDATED'
                     AND jsonb_array_length(validation_findings) = 0
                 )
             )"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_validation_state_check
             CHECK (
                 (
                     validation_status = 'NOT_VALIDATED'
                     AND jsonb_array_length(validation_findings) = 0
                 )
                 OR
                 (
                     validation_status = 'PASS'
                     AND creation_status = 'CREATED'
                     AND source IS NOT NULL
                     AND jsonb_array_length(validation_findings) = 0
                 )
                 OR
                 (
                     validation_status = 'SUSPICION'
                     AND creation_status = 'CREATED'
                     AND source IS NOT NULL
                     AND jsonb_array_length(validation_findings) > 0
                 )
             )"
        );

    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
    }
};