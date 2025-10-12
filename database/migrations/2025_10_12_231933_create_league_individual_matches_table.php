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
        Schema::create('league_individual_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('player2_id')->constrained('users')->onDelete('cascade');
            $table->integer('player1_level');
            $table->integer('player2_level');
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status')->default('waiting');
            $table->json('game_state')->nullable();
            $table->integer('player1_points_earned')->default(0);
            $table->integer('player2_points_earned')->default(0);
            $table->timestamps();
            
            $table->index(['player1_id', 'status']);
            $table->index(['player2_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_individual_matches');
    }
};
