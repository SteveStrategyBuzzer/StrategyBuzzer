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
        Schema::create('league_team_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team1_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('team2_id')->constrained('teams')->onDelete('cascade');
            $table->integer('team1_level');
            $table->integer('team2_level');
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('status')->default('waiting');
            $table->json('game_state')->nullable();
            $table->integer('team1_points_earned')->default(0);
            $table->integer('team2_points_earned')->default(0);
            $table->timestamps();
            
            $table->index(['team1_id', 'status']);
            $table->index(['team2_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_team_matches');
    }
};
