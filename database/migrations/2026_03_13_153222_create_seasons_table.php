<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mode')->default('all');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['upcoming', 'active', 'ended'])->default('upcoming');
            $table->timestamp('rewards_distributed_at')->nullable();
            $table->timestamps();

            $table->index(['mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
