<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-02 — kernel_rotation_state_v2
 *
 * Ligne unique de l'état de rotation V2 (DEC-064).
 * Coexiste avec la table legacy kernel_rotation_state (DEPRECATED).
 * Additive — aucune table existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('active_depth')->nullable();
            $table->string('active_tour_id', 36)->nullable();
            $table->string('rotation_status', 64)->default('TOUR_IN_PROGRESS');
            // Valeurs : TOUR_IN_PROGRESS | NOT_ENGAGED_PRODUCTION_ON_HOLD
            $table->text('tour_domain_states')->nullable();
            // JSON : {"states":{"geographie":"ON",...},"empty_progress":0}
            $table->string('active_blueprint_identity', 36)->nullable();
            $table->string('last_counted_blueprint_identity', 36)->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_rotation_state_v2');
    }
};
