<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KRP v4 keeps the active tour and its last persisted closure in the KRP state.
 *
 * The closure markers make the DOMAIN_EXHAUSTED -> DEPTH_EXHAUSTED boundary
 * observable and idempotent without delegating any lifecycle state to Taxonomy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->uuid('active_tour_id')->nullable()->after('active_depth');
            $table->string('tour_state', 16)->default('OPEN')->after('active_tour_id');
            $table->uuid('last_closed_tour_id')->nullable()->after('tour_state');
            $table->unsignedTinyInteger('last_closed_depth')->nullable()->after('last_closed_tour_id');
        });
    }

    public function down(): void
    {
        Schema::table('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->dropColumn([
                'active_tour_id',
                'tour_state',
                'last_closed_tour_id',
                'last_closed_depth',
            ]);
        });
    }
};