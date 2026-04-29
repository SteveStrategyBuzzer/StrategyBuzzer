<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_question_plans', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('plan_uid', 64)->unique();
            $table->string('mode', 32);
            $table->string('division', 32)->nullable();
            $table->unsignedSmallInteger('difficulty_level')->nullable();
            $table->unsignedSmallInteger('boss_level')->nullable();
            $table->string('domain', 64);
            $table->string('language', 8);

            $table->unsignedSmallInteger('total_questions');
            $table->unsignedTinyInteger('rounds_count');

            $table->json('global_composition');
            $table->json('per_round_composition');
            $table->json('group_ids');
            $table->json('issues')->nullable();

            $table->timestamps();

            $table->index(['mode', 'division'], 'mqp_mode_division_idx');
            $table->index('created_at', 'mqp_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_question_plans');
    }
};
