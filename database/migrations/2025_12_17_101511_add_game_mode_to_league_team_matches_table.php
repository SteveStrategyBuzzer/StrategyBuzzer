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
        Schema::table('league_team_matches', function (Blueprint $table) {
            $table->string('game_mode')->default('classique')->after('status');
            $table->json('player_order')->nullable()->after('game_mode');
            $table->json('duel_pairings')->nullable()->after('player_order');
            $table->integer('current_player_index')->default(0)->after('duel_pairings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_team_matches', function (Blueprint $table) {
            $table->dropColumn(['game_mode', 'player_order', 'duel_pairings', 'current_player_index']);
        });
    }
};
