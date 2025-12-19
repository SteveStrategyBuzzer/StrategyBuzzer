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
            $table->string('match_division')->default('bronze')->after('game_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_team_matches', function (Blueprint $table) {
            $table->dropColumn('match_division');
        });
    }
};
