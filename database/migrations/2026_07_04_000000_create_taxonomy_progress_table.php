<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive — création de la table taxonomy_progress.
 *
 * Rôle : curseur de progression interne de TaxonomyProgressManager.
 * Une ligne par couple (depth, domain_code).
 * Taxonomy.json reste la source de vérité des sujets et idées.
 * Cette table ne stocke que l'état du curseur, pas les paires elles-mêmes.
 *
 * Aucune table existante n'est modifiée.
 * Aucun drop, truncate, delete ou seed dans ce patch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_progress', function (Blueprint $table) {
            $table->id();

            // Depth du bassin (2–10)
            $table->unsignedTinyInteger('depth');

            // Code DomainCycle lowercase ASCII (ex: 'science', 'histoire')
            $table->string('domain_code', 32);

            // Sous-domaine en cours de remplissage (null = bassin non démarré ou épuisé)
            $table->string('active_sub_domain', 128)->nullable();

            // Sujet actif dans le sous-domaine en cours (null = épuisé)
            $table->string('active_subject', 128)->nullable();

            // Index 0-based dans le tableau idees_dominantes du sujet actif
            $table->unsignedTinyInteger('dominant_idea_index')->default(0);

            // Tableau JSON des sous-domaines entièrement épuisés pour ce bassin
            // ex: ["Sciences", "Technologies"]
            $table->json('used_sub_domains')->default('[]');

            // Statut du bassin : 'active' | 'exhausted'
            $table->string('status', 16)->default('active');

            $table->timestamps();

            // Un seul curseur par couple (depth, domain_code)
            $table->unique(['depth', 'domain_code'], 'tp_depth_domain_unique');

            // Filtrage rapide par statut
            $table->index(['depth', 'domain_code', 'status'], 'tp_depth_domain_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_progress');
    }
};
