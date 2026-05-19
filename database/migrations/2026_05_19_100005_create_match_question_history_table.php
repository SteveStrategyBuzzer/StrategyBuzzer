<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_question_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('match_ref', 64);
            $table->unsignedBigInteger('question_group_id')->nullable();
            $table->string('question_intent_key', 255)->nullable();
            $table->unsignedTinyInteger('round')->nullable();
            $table->unsignedTinyInteger('question_number')->nullable();
            $table->timestamp('served_at')->useCurrent();

            $table->foreign('question_group_id')
                ->references('id')->on('question_groups')
                ->nullOnDelete();

            $table->index('match_ref', 'mqh_match_ref_idx');
            $table->index(['match_ref', 'question_intent_key'], 'mqh_match_intent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_question_history');
    }
};
