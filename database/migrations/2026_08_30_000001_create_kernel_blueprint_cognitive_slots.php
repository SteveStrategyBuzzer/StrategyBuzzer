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
            $table->jsonb('source')->nullable();
            $table->jsonb('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
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
             CHECK (source IS NULL OR jsonb_typeof(source) = 'object')"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_translations_object_check
             CHECK (jsonb_typeof(translations) = 'object')"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_creation_source_check
             CHECK (
                 (creation_status = 'CREATED' AND source IS NOT NULL)
                 OR
                 (creation_status IN ('EMPTY', 'CREATION_FAILED') AND source IS NULL)
             )"
        );

        DB::statement(
            "ALTER TABLE kernel_blueprint_cognitive_slots
             ADD CONSTRAINT kbcs_validation_requires_source_check
             CHECK (
                 creation_status = 'CREATED'
                 OR validation_status = 'NOT_VALIDATED'
             )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
    }
};