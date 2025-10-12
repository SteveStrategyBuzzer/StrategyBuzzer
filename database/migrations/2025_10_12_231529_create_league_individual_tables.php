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
        // Statistiques Ligue Individuel (carrière 1v1 permanente)
        Schema::create('league_individual_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('level')->default(1); // Niveau initial basé sur Duo
            $table->integer('matches_played')->default(0);
            $table->integer('matches_won')->default(0);
            $table->integer('matches_lost')->default(0);
            $table->integer('total_points')->default(0); // Points cumulés
            $table->boolean('initialized')->default(false); // Initialisé ou non
            $table->timestamps();

            $table->unique('user_id');
        });

        // Historique des matchs Ligue Individuel
        Schema::create('league_individual_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('player2_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status')->default('waiting'); // waiting, playing, finished, cancelled
            $table->integer('player1_level')->default(1);
            $table->integer('player2_level')->default(1);
            $table->json('game_state')->nullable(); // État du jeu (best of 3)
            $table->integer('player1_points_earned')->default(0); // Points gagnés/perdus
            $table->integer('player2_points_earned')->default(0);
            $table->timestamps();

            $table->index(['player1_id', 'status']);
            $table->index(['player2_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_individual_matches');
        Schema::dropIfExists('league_individual_stats');
    }
};
