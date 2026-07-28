<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-05 — kernel_current_kernel_receipts
 *
 * Idempotence de CURRENT_KERNEL_RECEIVED (DEC-063).
 * PK sur blueprint_id : chaque Blueprint ne peut être comptabilisé qu'une fois.
 * Additive — aucune table existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_current_kernel_receipts', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('event_id', 36)->unique();
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_current_kernel_receipts');
    }
};
