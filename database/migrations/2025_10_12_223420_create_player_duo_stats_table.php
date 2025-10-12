<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_duo_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->integer('total_matches')->default(0);
            $table->integer('victories')->default(0);
            $table->integer('defeats')->default(0);
            $table->integer('level')->default(0); // Niveau = nombre de victoires
            $table->decimal('win_rate', 5, 2)->default(0); // Pourcentage de victoires
            $table->integer('current_streak')->default(0); // Série actuelle (+ si victoires, - si défaites)
            $table->integer('best_win_streak')->default(0);
            $table->integer('best_lose_streak')->default(0);
            $table->timestamps();
            
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_duo_stats');
    }
};
