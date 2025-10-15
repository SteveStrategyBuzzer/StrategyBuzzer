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
        Schema::create('master_games', function (Blueprint $table) {
            $table->id();
            $table->string('game_code', 10)->unique(); // Code unique pour rejoindre
            $table->string('firebase_id')->nullable()->unique(); // ID Firestore pour temps réel
            $table->foreignId('host_user_id')->constrained('users')->onDelete('cascade');
            
            // Paramètres de la partie
            $table->string('name'); // Nom de la partie
            $table->json('languages')->default('["FR"]'); // FR, EN
            $table->integer('participants_expected')->default(10); // 3-40
            $table->enum('mode', ['face_to_face', 'one_vs_all', 'podium', 'groups'])->default('podium');
            
            // Questions
            $table->integer('total_questions')->default(20); // 10, 20, 30, 40
            $table->json('question_types')->default('["multiple_choice"]'); // true_false, multiple_choice, image, random
            
            // Domaine
            $table->string('domain_type')->default('theme'); // theme, scolaire, personnalisé
            $table->string('theme')->nullable(); // Si domaine = theme
            $table->string('school_country')->nullable();
            $table->string('school_level')->nullable();
            $table->string('school_subject')->nullable();
            
            // État
            $table->enum('status', ['draft', 'lobby', 'running', 'ended'])->default('draft');
            $table->integer('current_question')->default(0);
            $table->boolean('quiz_validated')->default(false);
            
            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_games');
    }
};
