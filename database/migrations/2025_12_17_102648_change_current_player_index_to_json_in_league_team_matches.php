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
            $table->dropColumn('current_player_index');
        });
        
        Schema::table('league_team_matches', function (Blueprint $table) {
            $table->json('relay_indices')->nullable()->after('duel_pairings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_team_matches', function (Blueprint $table) {
            $table->dropColumn('relay_indices');
        });
        
        Schema::table('league_team_matches', function (Blueprint $table) {
            $table->integer('current_player_index')->default(0)->after('duel_pairings');
        });
    }
};
