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
        Schema::table('player_divisions', function (Blueprint $table) {
            $table->decimal('initial_efficiency', 5, 2)->default(0)->after('level');
            $table->integer('matches_won')->default(0)->after('initial_efficiency');
            $table->integer('matches_lost')->default(0)->after('matches_won');
        });
    }

    public function down(): void
    {
        Schema::table('player_divisions', function (Blueprint $table) {
            $table->dropColumn(['initial_efficiency', 'matches_won', 'matches_lost']);
        });
    }
};
