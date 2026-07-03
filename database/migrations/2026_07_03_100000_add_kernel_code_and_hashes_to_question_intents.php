<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Kernel Blueprint Frame — colonnes d'identité noyau
 *
 * Purement additive — aucun drop, aucune modification existante.
 *
 * kernel_code  : code structurel yy-xx-xxx-xxx-xxx-zz généré par KEY_STRUCTURE + KLD
 * ks_hash      : signature KEY_STRUCTURE (détecte recollision structurelle)
 * kld_hash     : signature KLD (détecte doublon directionnel Sujet/Idée)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_intents', function (Blueprint $table) {
            $table->string('kernel_code', 32)->nullable()->after('frame_validated_at');
            $table->string('ks_hash', 64)->nullable()->after('kernel_code');
            $table->string('kld_hash', 64)->nullable()->after('ks_hash');
        });

        DB::statement(
            'CREATE UNIQUE INDEX qi_kernel_code_idx ON question_intents (kernel_code) WHERE kernel_code IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS qi_kernel_code_idx');

        Schema::table('question_intents', function (Blueprint $table) {
            $table->dropColumn(['kernel_code', 'ks_hash', 'kld_hash']);
        });
    }
};
