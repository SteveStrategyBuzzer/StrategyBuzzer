<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_question_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('question_group_id')->nullable();
            $table->string('question_intent_key', 255)->nullable();
            $table->string('mode', 32)->nullable();
            $table->timestamp('played_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            $table->foreign('question_group_id')
                ->references('id')->on('question_groups')
                ->nullOnDelete();

            $table->index(['user_id', 'question_intent_key'], 'pqh_user_intent_idx');
            $table->index(['user_id', 'played_at'], 'pqh_user_played_idx');
            $table->index('question_group_id', 'pqh_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_question_history');
    }
};
