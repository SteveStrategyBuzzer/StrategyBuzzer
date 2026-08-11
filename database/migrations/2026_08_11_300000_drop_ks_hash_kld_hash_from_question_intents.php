<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * #142 — Suppression des vestiges KLD / KEY_STRUCTURE
 *
 * ks_hash  et kld_hash ont été ajoutés pour KEY_STRUCTURE et KLD.
 * Ces deux modules sont SUPERSEDED (2026-08-11 — canonical-kernel-flow.md).
 *
 * Conditions de suppression vérifiées avant migration :
 *   - ks_hash  non-null rows : 0
 *   - kld_hash non-null rows : 0
 *   - question_intents total  : 0
 *   - Writer officiel         : AUCUN
 *   - Reader officiel         : AUCUN
 *
 * Ce qui est CONSERVÉ (frame_en gelé, BLOCKER PHASES) :
 *   - KernelFrameBuilder section C : ks_hash (null), ks_result, kld_result
 *   - Ces clés appartiennent au FORMAT du ticket frame, pas aux colonnes DB.
 *
 * Rollback : recrée les colonnes vides (nullable, aucune donnée perdue).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Garde anti-données : refuser si une ligne non-null existe
        $ksCount  = DB::table('question_intents')->whereNotNull('ks_hash')->count();
        $kldCount = DB::table('question_intents')->whereNotNull('kld_hash')->count();

        if ($ksCount > 0 || $kldCount > 0) {
            throw new \RuntimeException(
                "#142 BLOCKER : données non-null détectées avant suppression "
                . "(ks_hash={$ksCount}, kld_hash={$kldCount}). Migration annulée."
            );
        }

        Schema::table('question_intents', function (Blueprint $table) {
            $table->dropColumn(['ks_hash', 'kld_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('question_intents', function (Blueprint $table) {
            $table->string('ks_hash', 64)->nullable()->after('kernel_code');
            $table->string('kld_hash', 64)->nullable()->after('ks_hash');
        });
    }
};
