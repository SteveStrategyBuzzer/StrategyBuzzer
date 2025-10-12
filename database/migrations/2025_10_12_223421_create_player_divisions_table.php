<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('mode'); // duo, league_individual, league_team
            $table->string('division')->default('bronze'); // bronze, argent, or, platine, diamant, legende
            $table->integer('points')->default(0); // Points totaux (0-99 Bronze, 100-199 Argent, etc.)
            $table->integer('level')->default(1); // Niveau dans le mode
            $table->integer('rank')->nullable(); // Position dans le classement de la division
            $table->timestamps();
            
            $table->unique(['user_id', 'mode']);
            $table->index(['mode', 'division', 'points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_divisions');
    }
};
