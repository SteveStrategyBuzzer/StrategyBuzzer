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
        Schema::create('master_game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_game_id')->constrained('master_games')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('master_game_code_id')->nullable()->constrained('master_game_codes')->onDelete('set null');
            
            $table->string('side')->nullable(); // A, B, Groupe1, etc.
            $table->integer('score')->default(0);
            $table->json('answered')->default('{}'); // {1: {choice: 'A', timeMs: 1500, correct: true}, ...}
            $table->enum('status', ['waiting', 'playing', 'disconnected'])->default('waiting');
            
            $table->timestamps();
            
            $table->unique(['master_game_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_game_players');
    }
};
