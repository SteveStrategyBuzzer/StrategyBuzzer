<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->foreignId('related_match_id')->nullable()->constrained('duo_matches')->onDelete('set null');
            $table->timestamps();

            $table->index('sender_id');
            $table->index('receiver_id');
            $table->index(['receiver_id', 'is_read']);
            $table->index('related_match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_messages');
    }
};
