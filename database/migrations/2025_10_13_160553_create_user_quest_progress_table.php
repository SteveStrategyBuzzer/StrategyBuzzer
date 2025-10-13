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
        Schema::create('user_quest_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quest_id')->constrained('quests')->onDelete('cascade');
            $table->integer('current_value')->default(0);
            $table->boolean('completed')->default(false);
            $table->boolean('claimed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'quest_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_quest_progress');
    }
};
