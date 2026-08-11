<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 05_QuestionIntent (VERROUILLÉ).
 *
 * Opérations :
 *   1. Ajoute kernel_code VARCHAR(22) NULL à kernel_blueprint_runs
 *      + index UNIQUE PARTIEL (WHERE NOT NULL).
 *   2. Crée kernel_code_sequences — source de vérité des compteurs
 *      par bassin (depth, domain_code).
 *
 * Garde : kernel_blueprint_runs.kernel_code est la source canonique de
 * l'identité du noyau (DEC-069). kernel_code_sequences ne contient PAS
 * l'identité métier — uniquement l'état interne du compteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. kernel_blueprint_runs.kernel_code ─────────────────────────────
        Schema::table('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('kernel_code', 22)->nullable()->after('domain_code');
        });

        // Index UNIQUE PARTIEL (pgsql) : NULL autorisé sur plusieurs lignes,
        // unicité appliquée uniquement pour les valeurs non-NULL.
        DB::statement(
            'CREATE UNIQUE INDEX kernel_blueprint_runs_kernel_code_unique '
            . 'ON kernel_blueprint_runs (kernel_code) '
            . 'WHERE kernel_code IS NOT NULL'
        );

        // ── 2. kernel_code_sequences ─────────────────────────────────────────
        Schema::create('kernel_code_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('depth');
            $table->char('domain_code', 2);
            // next_value : entier base10, plage 0..1 679 615 (36^4 - 1)
            $table->unsignedInteger('next_value')->default(0);
            $table->timestamps();

            // Clé primaire composite = verrou de bassin
            $table->primary(['depth', 'domain_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_code_sequences');

        DB::statement(
            'DROP INDEX IF EXISTS kernel_blueprint_runs_kernel_code_unique'
        );

        Schema::table('kernel_blueprint_runs', function (Blueprint $table) {
            $table->dropColumn('kernel_code');
        });
    }
};
