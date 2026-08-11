<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * #147 — Suppression du vestige kernel_code de question_intents
 *
 * question_intents.kernel_code a été ajouté par 2026_07_03_100000 dans le cadre
 * du Kernel Blueprint Frame legacy (même migration que ks_hash/kld_hash, déjà supprimés).
 *
 * Audit pré-suppression (#147) :
 *   - Colonne présente          : OUI (VARCHAR 32, nullable)
 *   - Index qi_kernel_code_idx  : OUI (UNIQUE WHERE kernel_code IS NOT NULL)
 *   - kernel_code non-null rows : 0
 *   - question_intents total    : 0
 *   - Writer OFFICIAL           : AUCUN
 *   - Reader OFFICIAL           : AUCUN
 *   - Responsabilité actuelle   : AUCUNE
 *
 * Stockage canonique du kernel_code = kernel_blueprint_runs.kernel_code (KernelCodeEngine)
 * question_intents n'est PAS le stockage canonique de 05_QuestionIntent.
 *
 * Rollback : recrée la colonne vide + l'index UNIQUE PARTIEL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Garde anti-données
        $count = DB::table('question_intents')->whereNotNull('kernel_code')->count();
        if ($count > 0) {
            throw new \RuntimeException(
                "#147 BLOCKER : {$count} lignes kernel_code non-null détectées. Migration annulée."
            );
        }

        // Supprimer l'index UNIQUE PARTIEL avant de dropper la colonne
        DB::statement('DROP INDEX IF EXISTS qi_kernel_code_idx');

        Schema::table('question_intents', function (Blueprint $table) {
            $table->dropColumn('kernel_code');
        });
    }

    public function down(): void
    {
        Schema::table('question_intents', function (Blueprint $table) {
            $table->string('kernel_code', 32)->nullable()->after('frame_validated_at');
        });

        DB::statement(
            'CREATE UNIQUE INDEX qi_kernel_code_idx ON question_intents (kernel_code) WHERE kernel_code IS NOT NULL'
        );
    }
};
