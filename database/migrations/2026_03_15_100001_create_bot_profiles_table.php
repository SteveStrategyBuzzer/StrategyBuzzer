<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->boolean('is_active')->default(false);
            $table->string('bot_avatar_slug', 64)->nullable();
            $table->boolean('stake_enabled')->default(false);
            $table->unsignedInteger('max_stake_per_match')->default(0);
            $table->unsignedInteger('times_used_as_bot')->default(0);
            $table->unsignedInteger('bot_wins')->default(0);
            $table->unsignedInteger('bot_losses')->default(0);
            $table->unsignedInteger('coins_earned_for_owner')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_profiles');
    }
};
