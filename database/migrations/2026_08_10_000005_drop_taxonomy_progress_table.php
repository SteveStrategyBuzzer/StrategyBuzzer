<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Supprime taxonomy_progress.
 *
 * Cette table était gérée par TaxonomyProgressManager (supprimé).
 * Elle est remplacée par les 4 tables dynamiques :
 *   - taxonomy_subdomain_bank
 *   - taxonomy_subject_bank
 *   - taxonomy_dominant_idea_bank
 *   - taxonomy_generation_memory
 *
 * Toutes existantes après les migrations 000001–000004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('taxonomy_progress');
    }

    public function down(): void
    {
        // Reconstruction minimale pour permettre le rollback (sans données)
        // La restauration complète nécessiterait de remettre TaxonomyProgressManager.
        \Illuminate\Support\Facades\Schema::create('taxonomy_progress', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('subdomain_name', 256)->nullable();
            $table->string('subject_name', 256)->nullable();
            $table->unsignedTinyInteger('dominant_idea_index')->default(0);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
            $table->unique(['depth', 'domain_code'], 'tp_depth_domain_unique');
        });
    }
};
