<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #158 — Cleanup colonnes legacy KRP.
 *
 * Retire uniquement :
 *   - rotation_status  (remplacée par depth_state depuis 2026_08_13_000001)
 *   - active_tour_id   (non utilisée par KRP v3.2)
 *
 * Audit préalable 2026-08-14 :
 *   readers runtime = 0, writers runtime = 0, CLI = 0,
 *   listeners = 0, jobs/workers = 0, tests actifs = 0,
 *   seeders = 0, factories = 0, repositories = 0, commands = 0.
 *
 * RIEN D'AUTRE n'est modifié.
 * Toute autre suppression nécessite son propre audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->dropColumn(['rotation_status', 'active_tour_id']);
        });
    }

    public function down(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->string('active_tour_id', 36)->nullable()->after('active_depth');
            $table->string('rotation_status', 64)->default('TOUR_IN_PROGRESS')->after('active_tour_id');
        });
    }
};
