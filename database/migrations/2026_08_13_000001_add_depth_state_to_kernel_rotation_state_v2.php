<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LOT A — Migration A-4 (02_KernelRotationPlanner v3.2).
 *
 * Ajoute depth_state (remplace rotation_status) dans kernel_rotation_state_v2.
 *
 * Stratégie INIT_PROPRE (0 lignes en production) :
 *   - ADD COLUMN depth_state VARCHAR(64) DEFAULT 'ROTATION_ACTIVE'
 *   - Backfill : aucune ligne → no-op
 *
 * M-cleanup (ticket séparé, après validation zéro appelant rotation_status) :
 *   DROP COLUMN rotation_status, DROP COLUMN active_tour_id
 *
 * Valeurs :
 *   ROTATION_ACTIVE  — production active (ex-TOUR_IN_PROGRESS)
 *   PRODUCTION_ON_HOLD — aucun besoin actif (ex-NOT_ENGAGED_PRODUCTION_ON_HOLD)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->string('depth_state', 64)->default('ROTATION_ACTIVE')->after('active_depth');
        });

        // Backfill : convertir les lignes existantes (INIT_PROPRE → no-op)
        DB::statement("
            UPDATE kernel_rotation_state_v2
            SET depth_state = CASE
                WHEN rotation_status = 'NOT_ENGAGED_PRODUCTION_ON_HOLD' THEN 'PRODUCTION_ON_HOLD'
                ELSE 'ROTATION_ACTIVE'
            END
            WHERE rotation_status IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->dropColumn('depth_state');
        });
    }
};
