<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lobby_game_starts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('lobby_code', 10)->index();
            $table->integer('bet_amount')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['lobby_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lobby_game_starts');
    }
};
