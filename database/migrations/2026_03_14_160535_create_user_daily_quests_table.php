<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quest_id')->constrained()->onDelete('cascade');
            $table->date('quest_date');
            $table->json('progress')->default('{}');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('rewarded')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'quest_id', 'quest_date']);
            $table->index(['user_id', 'quest_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_quests');
    }
};
