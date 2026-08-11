<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_generation_memory', function (Blueprint $table) {
            $table->id();

            // Type de contexte : SUBDOMAIN | SUBJECT | IDEA
            $table->string('context_type', 16);

            // Clé unique du contexte (hash métier depth+domain+subdomain+subject)
            $table->string('context_key', 512);

            // Numéro de l'appel Gemini (1, 2, 3…)
            $table->unsignedSmallInteger('attempt_number');

            // Candidats proposés par Gemini
            $table->json('candidates')->nullable();

            // Valeurs ayant obtenu le statut PASS après validation
            $table->json('pass_items')->nullable();

            // Détails des FAIL [{value, reason, conflict_with}]
            $table->json('fail_items')->nullable();

            // Directions pédagogiques maintenant couvertes
            $table->json('covered_directions')->nullable();

            // True quand Gemini a signalé qu'il ne peut plus générer pour ce contexte
            $table->boolean('generation_exhausted')->default(false);

            $table->timestamps();

            // Unicité : un seul enregistrement par contexte × numéro d'appel
            $table->unique(['context_type', 'context_key', 'attempt_number'], 'tgm_context_attempt_unique');

            // Index de recherche
            $table->index(['context_type', 'context_key'], 'tgm_context_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_generation_memory');
    }
};
