<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxonomy v1.1 — occurrence, reprise, sélection exacte et livraison terminale.
 *
 * Ces tables sont propres à Taxonomy. Elles ne modifient aucune table KRP et ne
 * transportent aucune décision de rotation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_v11_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->unsignedInteger('ordinal');
            $table->string('status', 16)->default('PREPARING');
            $table->unsignedTinyInteger('consecutive_technical_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('exhausted_at')->nullable();
            $table->timestamps();

            $table->unique(['depth', 'domain_code', 'ordinal'], 'tv11_occurrence_ordinal_unique');
            $table->index(['depth', 'domain_code', 'status'], 'tv11_occurrence_lookup_idx');
        });

        Schema::create('taxonomy_v11_subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')
                ->unique('tv11_subdomain_occurrence_unique')
                ->constrained('taxonomy_v11_occurrences')
                ->cascadeOnDelete();
            $table->string('subdomain_name', 256);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('taxonomy_v11_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdomain_id')
                ->constrained('taxonomy_v11_subdomains')
                ->cascadeOnDelete();
            $table->string('subject_name', 256);
            $table->string('status', 24)->default('AVAILABLE');
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);
            $table->boolean('idea_generation_exhausted')->default(false);
            $table->timestamps();

            $table->unique(['subdomain_id', 'subject_name'], 'tv11_subject_name_unique');
            $table->index(['subdomain_id', 'status'], 'tv11_subject_status_idx');
        });

        Schema::create('taxonomy_v11_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')
                ->constrained('taxonomy_v11_subjects')
                ->cascadeOnDelete();
            $table->string('idea_value', 512);
            $table->string('validation_status', 8);
            $table->string('fail_reason', 64)->nullable();
            $table->string('fail_conflict_with', 512)->nullable();
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['subject_id', 'idea_value'], 'tv11_idea_value_unique');
            $table->index(['subject_id', 'validation_status', 'status'], 'tv11_idea_selection_idx');
        });

        Schema::create('taxonomy_v11_generation_memory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')
                ->constrained('taxonomy_v11_occurrences')
                ->cascadeOnDelete();
            $table->string('context_type', 16);
            $table->string('context_key', 512);
            $table->unsignedSmallInteger('attempt_number');
            $table->json('candidates')->nullable();
            $table->json('pass_items')->nullable();
            $table->json('fail_items')->nullable();
            $table->json('covered_directions')->nullable();
            $table->boolean('generation_exhausted')->default(false);
            $table->timestamps();

            $table->unique(
                ['occurrence_id', 'context_type', 'context_key', 'attempt_number'],
                'tv11_memory_context_attempt_unique'
            );
            $table->index(['occurrence_id', 'context_type'], 'tv11_memory_context_idx');
        });

        Schema::create('taxonomy_v11_terminal_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')
                ->unique('tv11_terminal_occurrence_unique')
                ->constrained('taxonomy_v11_occurrences')
                ->cascadeOnDelete();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('status', 16)->default('PENDING');
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'tv11_terminal_pending_idx');
        });

        Schema::create('taxonomy_v11_blueprint_assignments', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->foreignId('occurrence_id')
                ->constrained('taxonomy_v11_occurrences')
                ->restrictOnDelete();
            $table->foreignId('subdomain_id')
                ->constrained('taxonomy_v11_subdomains')
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained('taxonomy_v11_subjects')
                ->restrictOnDelete();
            $table->foreignId('idea_id')
                ->constrained('taxonomy_v11_ideas')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('subdomain_active', 256);
            $table->string('subject_active', 256);
            $table->string('dominant_idea_active', 512);
            $table->timestamps();

            $table->index(['occurrence_id', 'created_at'], 'tv11_assignment_occurrence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_v11_blueprint_assignments');
        Schema::dropIfExists('taxonomy_v11_terminal_facts');
        Schema::dropIfExists('taxonomy_v11_generation_memory');
        Schema::dropIfExists('taxonomy_v11_ideas');
        Schema::dropIfExists('taxonomy_v11_subjects');
        Schema::dropIfExists('taxonomy_v11_subdomains');
        Schema::dropIfExists('taxonomy_v11_occurrences');
    }
};