<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('game_mode'); // 'solo', 'duo', 'league_individual', 'league_team'
            $table->string('scope'); // 'round', 'match', 'global'
            $table->string('game_id')->nullable(); // Firebase game ID pour round/match
            $table->integer('round_number')->nullable(); // Numéro de la manche (1, 2, 3)
            
            // Données brutes
            $table->integer('total_questions')->default(0);
            $table->integer('questions_buzzed')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('wrong_answers')->default(0);
            $table->integer('points_earned')->default(0); // Points totaux obtenus
            $table->integer('points_possible')->default(0); // Points maximum possibles
            
            // Métriques calculées (stockées pour historique)
            $table->decimal('efficacite_brute', 5, 2)->default(0); // Points / Questions (%)
            $table->decimal('taux_participation', 5, 2)->default(0); // Buzzed / Total (%)
            $table->decimal('taux_precision', 5, 2)->default(0); // Correct / Buzzed (%)
            $table->decimal('ratio_performance', 5, 2)->default(0); // Earned / Possible (%)
            
            // Métadonnées
            $table->json('details')->nullable(); // Pour données additionnelles
            $table->timestamps();
            
            // Index pour requêtes rapides
            $table->index(['user_id', 'game_mode', 'scope']);
            $table->index(['user_id', 'game_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_statistics');
    }
};
