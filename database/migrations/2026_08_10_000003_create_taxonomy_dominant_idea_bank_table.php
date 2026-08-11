<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_dominant_idea_bank', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subject_id')
                  ->constrained('taxonomy_subject_bank')
                  ->cascadeOnDelete();

            // Valeur de l'Idée Dominante telle que générée par Gemini
            $table->string('idea_value', 512);

            // Résultat de la validation : PASS | FAIL
            $table->string('validation_status', 8);

            // Code de raison en cas de FAIL (nullable si PASS)
            $table->string('fail_reason', 64)->nullable();

            // Valeur en conflit ayant causé le FAIL (nullable)
            $table->string('fail_conflict_with', 512)->nullable();

            // Statut de disponibilité pour les PASS : AVAILABLE | CONSUMED | FAIL
            $table->string('status', 16);

            $table->timestamps();

            // Index de recherche : clé principale de peekNext()
            $table->index(
                ['subject_id', 'validation_status', 'status'],
                'tdib_subject_validation_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_dominant_idea_bank');
    }
};
