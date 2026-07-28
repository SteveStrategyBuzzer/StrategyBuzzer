<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-01 — kernel_blueprint_runs
 *
 * Cycle de vie d'exécution de chaque Blueprint (DEC-067).
 * Additive — aucune table existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            // Valeurs : CREATED_UNENGAGED | ENGAGED_IN_PIPELINE
            //           | READY_BANK_RECEIVED | NOT_ENGAGED_PRODUCTION_ON_HOLD
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->timestamp('engaged_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('execution_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_blueprint_runs');
    }
};
