<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Currency System Switch Migration
     * 
     * This migration:
     * 1. Swaps existing user balances (coins ↔ competence_coins)
     * 2. Updates default values for new players: 25 Intelligence + 250 Compétence
     * 
     * New Logic:
     * - Intelligence coins (coins): Earned in Multiplayer modes (Duo, League, Master)
     * - Compétence coins (competence_coins): Earned in Solo + Quests, used for ALL boutique purchases
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::statement('
                UPDATE users 
                SET 
                    coins = COALESCE(competence_coins, 0),
                    competence_coins = COALESCE(coins, 0)
            ');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('coins')->default(25)->change();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN competence_coins SET DEFAULT 250');
    }

    /**
     * Reverse the migrations (swap back)
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::statement('
                UPDATE users 
                SET 
                    coins = COALESCE(competence_coins, 0),
                    competence_coins = COALESCE(coins, 0)
            ');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('coins')->default(0)->change();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN competence_coins SET DEFAULT 0');
    }
};
