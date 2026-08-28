<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KRP v4 boundary: terminal facts received from the future Taxonomy v1.1 flow.
 *
 * This is owned by KRP. Taxonomy will only call the KRP entry with fact_id,
 * depth and domain; it does not own DOMAIN_EXHAUSTED, DEPTH_EXHAUSTED, rotation
 * or the lifecycle state stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_taxonomy_terminal_facts', function (Blueprint $table) {
            $table->id();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->uuid('tour_id');
            $table->timestampTz('received_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['depth', 'tour_id', 'consumed_at', 'received_at'], 'kttf_pending_tour_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_taxonomy_terminal_facts');
    }
};