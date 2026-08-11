<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée taxonomy_subdomain_bank.
 *
 * Remplace taxonomy_progress comme banque de sous-domaines dynamiques.
 * Les sous-domaines sont générés par Gemini (DepthContract + Domaine)
 * et non plus lus depuis taxonomy.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_subdomain_bank', function (Blueprint $table) {
            $table->id();

            // Depth du bassin (2,4,6,7,8,9,10)
            $table->unsignedTinyInteger('depth');

            // Code DomainCycle lowercase ASCII (ex: 'histoire', 'science')
            $table->string('domain_code', 32);

            // Nom du Sous-domaine tel que généré par Gemini
            $table->string('subdomain_name', 256);

            // Statut du sous-domaine : ACTIVE | EXHAUSTED
            $table->string('status', 16)->default('ACTIVE');

            // True quand Gemini ne peut plus générer de nouveaux Sujets pour ce Sous-domaine
            $table->boolean('generation_exhausted')->default(false);

            // Nombre d'appels Gemini effectués pour générer des Sujets
            $table->unsignedTinyInteger('subject_attempt_count')->default(0);

            $table->timestamps();

            // Unicité : un seul sous-domaine par nom pour ce bassin
            $table->unique(['depth', 'domain_code', 'subdomain_name'], 'tsb_depth_domain_name_unique');

            // Index de recherche
            $table->index(['depth', 'domain_code', 'generation_exhausted'], 'tsb_depth_domain_exhausted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_subdomain_bank');
    }
};
