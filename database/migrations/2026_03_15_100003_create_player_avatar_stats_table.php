<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_avatar_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('avatar_slug', 64);
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('avg_buzz_ms')->default(3000);
            $table->float('accuracy_rate')->default(0.5);
            $table->timestamps();

            $table->unique(['user_id', 'avatar_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_avatar_stats');
    }
};
