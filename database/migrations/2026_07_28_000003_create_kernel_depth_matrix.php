<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-03 — kernel_depth_matrix
 *
 * Une ligne par Depth du DepthCycle (7 lignes, alimentées par M-07).
 * Porte cycle_target (dénormalisé), cycle_completed, et la progression
 * du Tour actif (DEC-060, DEC-062).
 * Additive — aucune table existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_depth_matrix', function (Blueprint $table) {
            $table->smallInteger('depth')->primary();
            $table->integer('cycle_target')->default(0);
            $table->integer('cycle_completed')->default(0);
            $table->smallInteger('empty_progress_current_tour')->default(0);
            $table->string('current_tour_id', 36)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_depth_matrix');
    }
};
