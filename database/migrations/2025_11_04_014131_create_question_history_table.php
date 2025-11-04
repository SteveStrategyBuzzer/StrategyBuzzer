<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('question_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('question_id', 255)->index(); // ID unique de la question (ex: "Général_ai_abc123")
            $table->string('question_hash', 32)->index(); // MD5 hash du texte de la question pour détecter duplicates
            $table->string('correct_answer', 500); // La réponse correcte (pour éviter doublons de réponses)
            $table->string('theme', 100)->nullable(); // Thème de la question
            $table->integer('niveau')->nullable(); // Niveau de difficulté
            $table->timestamps();
            
            // Index composite pour recherches rapides
            $table->index(['user_id', 'question_hash']);
            $table->index(['user_id', 'correct_answer']);
            
            // Foreign key vers users
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_history');
    }
};
