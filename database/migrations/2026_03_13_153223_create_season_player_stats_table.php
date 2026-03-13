<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('mode');
            $table->string('division_at_start')->default('bronze');
            $table->string('division_at_end')->nullable();
            $table->integer('season_points')->default(0);
            $table->integer('matches_played')->default(0);
            $table->boolean('reward_coins_distributed')->default(false);
            $table->integer('coins_awarded')->default(0);
            $table->boolean('promoted')->default(false);
            $table->boolean('exclusive_frame_awarded')->default(false);
            $table->timestamps();

            $table->unique(['season_id', 'user_id', 'mode']);
            $table->index(['season_id', 'mode', 'season_points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_player_stats');
    }
};
