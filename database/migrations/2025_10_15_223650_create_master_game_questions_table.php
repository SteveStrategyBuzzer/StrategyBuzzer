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
        Schema::create('master_game_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_game_id')->constrained('master_games')->onDelete('cascade');
            
            $table->integer('question_number'); // Position dans le quiz (1, 2, 3...)
            $table->enum('type', ['true_false', 'multiple_choice', 'image'])->default('multiple_choice');
            $table->text('text'); // Énoncé de la question
            $table->json('choices')->nullable(); // Choix A, B, C, D pour QCM
            $table->json('correct_indexes'); // Indices des bonnes réponses [0] ou [0,2] pour multi
            $table->string('media_url')->nullable(); // URL de l'image si type = image
            
            $table->timestamps();
            
            $table->unique(['master_game_id', 'question_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_game_questions');
    }
};
