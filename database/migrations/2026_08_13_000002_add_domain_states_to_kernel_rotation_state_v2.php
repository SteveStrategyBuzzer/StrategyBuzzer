<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOT B — Migration B-a (02_KernelRotationPlanner v3.2).
 *
 * Ajoute domain_states JSONB dans kernel_rotation_state_v2.
 *
 * Structure :
 *   { "2": { "geographie": "ACTIF", "histoire": "ACTIF", ... 8 domaines },
 *     "4": { ... }, "6": { ... }, "7": { ... }, "8": { ... }, "9": { ... }, "10": { ... } }
 *
 *   7 Depths × 8 Domaines = 56 paires (ACTIF | DOMAIN_EXHAUSTED).
 *
 * Stratégie INIT_PROPRE (0 lignes) : backfill = no-op.
 * Nouvelles lignes initialisées par registerActiveBlueprintIdentity() dans KernelRotationPlanner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->jsonb('domain_states')->nullable()->default(null)->after('depth_state');
        });
    }

    public function down(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->dropColumn('domain_states');
        });
    }
};
