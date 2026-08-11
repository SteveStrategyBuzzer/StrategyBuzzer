<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_subject_bank', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subdomain_id')
                  ->constrained('taxonomy_subdomain_bank')
                  ->cascadeOnDelete();

            // Nom du Sujet tel que généré par Gemini
            $table->string('subject_name', 256);

            // Statut : AVAILABLE | CONSUMED
            $table->string('status', 16)->default('AVAILABLE');

            // Nombre d'appels Gemini effectués pour générer des Idées
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);

            // True quand Gemini ne peut plus générer de nouvelles Idées pour ce Sujet
            $table->boolean('idea_generation_exhausted')->default(false);

            $table->timestamps();

            // Unicité : un seul sujet par nom dans un sous-domaine
            $table->unique(['subdomain_id', 'subject_name'], 'tsub_subdomain_name_unique');

            // Index de recherche
            $table->index(['subdomain_id', 'idea_generation_exhausted', 'status'], 'tsub_subdomain_exhausted_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_subject_bank');
    }
};
