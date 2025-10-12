<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duo_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('player2_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('waiting'); // waiting, playing, finished, cancelled
            $table->string('match_type')->default('random'); // random, invitation
            $table->integer('player1_score')->default(0);
            $table->integer('player2_score')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('theme')->nullable();
            $table->integer('player1_level')->default(1);
            $table->integer('player2_level')->default(1);
            $table->integer('player1_points_earned')->default(0); // +1/+2/+5
            $table->integer('player2_points_earned')->default(0);
            $table->json('game_state')->nullable(); // État complet du jeu
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            
            $table->index(['player1_id', 'status']);
            $table->index(['player2_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duo_matches');
    }
};
