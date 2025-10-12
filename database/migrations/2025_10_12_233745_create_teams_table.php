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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tag', 10)->unique();
            $table->foreignId('captain_id')->constrained('users')->onDelete('cascade');
            $table->string('division')->default('bronze');
            $table->integer('points')->default(0);
            $table->integer('level')->default(1);
            $table->integer('matches_played')->default(0);
            $table->integer('matches_won')->default(0);
            $table->integer('matches_lost')->default(0);
            $table->boolean('is_recruiting')->default(true);
            $table->timestamps();
            
            $table->index(['division', 'points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
