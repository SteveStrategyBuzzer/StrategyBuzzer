<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOT B — Migration B-b (02_KernelRotationPlanner v3.2).
 *
 * Ajoute pending_depth_exhausted_depth INT NULLABLE dans kernel_rotation_state_v2.
 *
 * Sémantique (DEC-089) :
 *   NULL   → aucune transition en attente.
 *   non-NULL → Depth dont tous les domaines sont DOMAIN_EXHAUSTED ;
 *              la transition vers le Depth suivant sera appliquée au prochain CKR.
 *
 * Producteur : KernelRotationPlanner::receiveDepthExhausted() (signal Taxonomy).
 * Consommateur : KernelRotationPlanner::receiveKernelReceivedV2() (DEC-093).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->integer('pending_depth_exhausted_depth')->nullable()->default(null)->after('domain_states');
        });
    }

    public function down(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->dropColumn('pending_depth_exhausted_depth');
        });
    }
};
