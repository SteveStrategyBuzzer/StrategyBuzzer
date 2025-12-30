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
        Schema::table('master_games', function (Blueprint $table) {
            $table->string('structure_type', 50)->default('free_for_all');
            $table->integer('team_count')->nullable();
            $table->integer('team_size_cap')->default(20);
            $table->string('skill_policy', 50)->default('all_players');
            $table->string('buzz_rule', 50)->default('first_buzz_locks');
        });

        Schema::create('master_game_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_game_id')->constrained('master_games')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('color', 20)->nullable();
            $table->integer('team_order')->default(0);
            $table->integer('max_players')->default(10);
            $table->timestamps();
        });

        Schema::table('master_game_players', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained('master_game_teams')->onDelete('set null');
            $table->integer('seat_index')->nullable();
            $table->boolean('is_captain')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_game_players', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn(['team_id', 'seat_index', 'is_captain']);
        });

        Schema::dropIfExists('master_game_teams');

        Schema::table('master_games', function (Blueprint $table) {
            $table->dropColumn(['structure_type', 'team_count', 'team_size_cap', 'skill_policy', 'buzz_rule']);
        });
    }
};
