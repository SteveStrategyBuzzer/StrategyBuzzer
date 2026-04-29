<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_groups', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedSmallInteger('difficulty_level')->nullable();
            $table->unsignedSmallInteger('boss_level')->nullable();
            $table->unsignedTinyInteger('difficulty_depth');

            $table->string('domain', 64);
            $table->string('sub_domain', 64);

            $table->string('question_type', 16);
            $table->string('cognitive_type', 32);

            $table->string('concept_id', 191)->nullable();
            $table->string('concept_family', 191)->nullable();

            $table->string('source', 32)->default('seed');
            $table->boolean('validated')->default(false);

            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['difficulty_level', 'domain', 'sub_domain', 'cognitive_type', 'difficulty_depth'], 'qg_solo_lookup_idx');
            $table->index(['boss_level', 'domain', 'sub_domain', 'cognitive_type'], 'qg_boss_lookup_idx');
            $table->index(['concept_family', 'domain', 'sub_domain'], 'qg_family_idx');
            $table->index(['validated', 'usage_count'], 'qg_pick_priority_idx');
        });

        DB::statement("ALTER TABLE question_groups
            ADD CONSTRAINT qg_cognitive_type_check
            CHECK (cognitive_type IN ('recognition', 'reasoning', 'deceptive_trap'))");

        DB::statement("ALTER TABLE question_groups
            ADD CONSTRAINT qg_question_type_check
            CHECK (question_type IN ('qcm', 'true_false'))");

        DB::statement("ALTER TABLE question_groups
            ADD CONSTRAINT qg_depth_check
            CHECK (difficulty_depth BETWEEN 1 AND 10)");

        DB::statement("ALTER TABLE question_groups
            ADD CONSTRAINT qg_level_or_boss_check
            CHECK (difficulty_level IS NOT NULL OR boss_level IS NOT NULL)");

        DB::statement("CREATE UNIQUE INDEX qg_unique_concept_id
            ON question_groups (concept_id)
            WHERE concept_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('question_groups');
    }
};
