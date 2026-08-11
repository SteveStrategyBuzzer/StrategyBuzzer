<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes additives : capacité de lien Blueprint → question_intents.
 *
 * ⏸ AUDIT RÈGLE DU VIDE (2026-08-11) : l'encodage QuestionIntent lui-même est un
 * BLOCKER ARCHITECTURAL ouvert (contrat officiel manquant). AUCUN code n'écrit
 * ces colonnes aujourd'hui — elles sont INERTES (0 ligne concernée).
 *
 * - blueprint_id     : identité du KernelBlueprint (kernel_blueprint_runs.blueprint_id).
 *                      UNIQUE : un Blueprint ne pourra produire qu'UN QuestionIntent.
 * - dominant_idea    : idée dominante VALIDÉE fournie par Taxonomy ↕ ValidationDominantIdeas
 *                      (largeur alignée sur taxonomy_dominant_idea_bank.idea_value = 512).
 * - advance_attempts : INERTE — le mécanisme qui l'utilisait a été retiré (audit
 *                      2026-08-11) ; suppression = migration destructive, uniquement
 *                      sur ordre explicite de l'utilisateur.
 *
 * Élargissements PG (labels Taxonomy plus larges que les colonnes historiques) :
 *   sub_domain 64→256, subject 255→256, concept_family 191→256 (subdomain_name = 256),
 *   angle_large / micro_angle 255→512 (idea_value = 512).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_intents', function (Blueprint $table) {
            $table->char('blueprint_id', 36)->nullable()->unique('qi_blueprint_id_unique');
            $table->string('dominant_idea', 512)->nullable();
            $table->unsignedTinyInteger('advance_attempts')->default(0);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE question_intents ALTER COLUMN sub_domain TYPE varchar(256)');
            DB::statement('ALTER TABLE question_intents ALTER COLUMN subject TYPE varchar(256)');
            DB::statement('ALTER TABLE question_intents ALTER COLUMN concept_family TYPE varchar(256)');
            DB::statement('ALTER TABLE question_intents ALTER COLUMN angle_large TYPE varchar(512)');
            DB::statement('ALTER TABLE question_intents ALTER COLUMN micro_angle TYPE varchar(512)');
        }
    }

    public function down(): void
    {
        Schema::table('question_intents', function (Blueprint $table) {
            $table->dropUnique('qi_blueprint_id_unique');
            $table->dropColumn(['blueprint_id', 'dominant_idea', 'advance_attempts']);
        });
        // Les élargissements de colonnes ne sont pas rétrécis (non destructif).
    }
};
