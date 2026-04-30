<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_snapshots', function (Blueprint $table) {
            $table->uuid('match_id')->primary();
            $table->string('mode', 32)->default('DUO');
            $table->unsignedSmallInteger('round_number')->default(0);
            $table->jsonb('player_scores')->default('{}');
            $table->jsonb('rounds_won')->default('{}');
            $table->jsonb('player_stats')->default('{}');
            $table->timestamp('snapshotted_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_snapshots');
    }
};
