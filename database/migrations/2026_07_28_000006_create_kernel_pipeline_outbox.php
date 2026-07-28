<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-06 — kernel_pipeline_outbox
 *
 * Outbox transactionnel pour les événements KRP (DEC-063).
 * Le listener ApplyCurrentKernelReceivedToRotation consomme ces événements.
 * Additive — aucune table existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_pipeline_outbox', function (Blueprint $table) {
            $table->string('event_id', 36)->primary();
            $table->string('event_type', 128);
            $table->integer('schema_version')->default(1);
            $table->text('payload'); // JSON
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_pipeline_outbox');
    }
};
