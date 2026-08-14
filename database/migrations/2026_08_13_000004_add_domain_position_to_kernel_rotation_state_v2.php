<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOT B — Migration B-c (02_KernelRotationPlanner v3.2).
 *
 * Ajoute domain_position SMALLINT NULLABLE dans kernel_rotation_state_v2.
 *
 * Sémantique (DEC-091) :
 *   NULL   → aucune rotation effectuée pour ce Depth (premier Blueprint).
 *   0..7   → index du DERNIER domaine sélectionné dans DepthTourState::DOMAIN_CYCLE.
 *
 * Règle selectNextDomain :
 *   position = domain_position ?? -1
 *   offset   = 1
 *   index    = (position + offset) % 8
 *   → domain_position = null ⟺ first = DOMAIN_CYCLE[0] = 'geographie'
 *
 * Écrit par : KernelRotationPlanner::applyRotation().
 * Réinitialisé : à NULL lors de la transition de Depth (receiveKernelReceivedV2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->smallInteger('domain_position')->nullable()->default(null)->after('pending_depth_exhausted_depth');
        });
    }

    public function down(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->dropColumn('domain_position');
        });
    }
};
