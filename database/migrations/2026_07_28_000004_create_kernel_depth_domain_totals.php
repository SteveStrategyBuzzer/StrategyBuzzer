<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-04 — kernel_depth_domain_totals
 *
 * 56 lignes initiales (7 Depths × 8 Domaines), alimentées par M-08.
 * Porte kernel_received_total par couple Depth × Domaine (DEC-060).
 * Additive — aucune table existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_depth_domain_totals', function (Blueprint $table) {
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->bigInteger('kernel_received_total')->default(0);
            $table->timestamps();

            $table->primary(['depth', 'domain_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_depth_domain_totals');
    }
};
