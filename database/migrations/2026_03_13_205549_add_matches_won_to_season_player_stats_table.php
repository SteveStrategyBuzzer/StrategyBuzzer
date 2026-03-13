<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_player_stats', function (Blueprint $table) {
            $table->unsignedInteger('matches_won')->default(0)->after('matches_played');
            $table->unsignedInteger('prize_rank')->nullable()->after('coins_awarded');
        });
    }

    public function down(): void
    {
        Schema::table('season_player_stats', function (Blueprint $table) {
            $table->dropColumn(['matches_won', 'prize_rank']);
        });
    }
};
