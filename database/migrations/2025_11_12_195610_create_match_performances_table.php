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
        Schema::create('match_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('game_mode'); // solo, duo, league
            $table->string('game_id'); // référence au game_id dans player_statistics
            $table->decimal('performance', 5, 2); // 0-100%
            $table->integer('rounds_played')->nullable(); // 2 ou 3 manches (null pour modes sans rounds)
            $table->boolean('is_victory');
            $table->timestamp('played_at');
            $table->timestamps();
            
            // Index pour requêtes efficaces
            $table->index(['user_id', 'game_mode', 'played_at']);
            $table->index(['user_id', 'played_at']); // Pour récupérer les 10 derniers matchs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_performances');
    }
};
