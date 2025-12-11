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
        Schema::table('league_individual_matches', function (Blueprint $table) {
            $table->integer('player1_coins_earned')->default(0);
            $table->integer('player2_coins_earned')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('league_individual_matches', function (Blueprint $table) {
            $table->dropColumn(['player1_coins_earned', 'player2_coins_earned']);
        });
    }
};
