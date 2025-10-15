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
        Schema::create('master_game_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_game_id')->constrained('master_games')->onDelete('cascade');
            
            $table->string('code', 10)->unique(); // Code unique pour rejoindre (ex: ABCD1234)
            $table->enum('role', ['player', 'host'])->default('player');
            $table->string('side')->nullable(); // A, B, Groupe1, Groupe2, etc.
            $table->integer('capacity')->default(1); // Nombre max de joueurs pour ce code
            $table->integer('used')->default(0); // Nombre de joueurs ayant utilisé ce code
            $table->enum('state', ['active', 'revoked'])->default('active');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_game_codes');
    }
};
