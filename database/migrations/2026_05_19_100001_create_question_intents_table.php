<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_intents', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('intent_key', 255)->unique();
            $table->char('language_source', 2)->default('en');

            $table->string('domain', 64);
            $table->string('sub_domain', 64)->nullable();
            $table->unsignedTinyInteger('difficulty_depth');

            $table->string('subject', 255)->nullable();
            $table->string('angle_large', 255)->nullable();
            $table->string('micro_angle', 255)->nullable();

            $table->text('answer_target')->nullable();
            $table->text('potential_trap')->nullable();

            $table->string('concept_family', 191)->nullable();
            $table->string('source', 32)->nullable();

            $table->timestamps();

            $table->index(['domain', 'sub_domain', 'difficulty_depth'], 'qi_domain_depth_idx');
            $table->index('concept_family', 'qi_concept_family_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_intents');
    }
};
