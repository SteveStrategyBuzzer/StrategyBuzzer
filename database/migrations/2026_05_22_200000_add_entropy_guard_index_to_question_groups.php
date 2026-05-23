<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a composite index on (sub_domain, concept_family, cognitive_type) to
     * support the E1 entropy guard in QualityGuards::check().
     *
     * E1 counts existing questions per cognitive path
     * (answer_text × concept_family × cognitive_type) and rejects when ≥ 2.
     * Without this index the query does a partial seq-scan; with it the planner
     * can satisfy the filter with an index-only scan on the ~3 000-row table.
     */
    public function up(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->index(
                ['sub_domain', 'concept_family', 'cognitive_type'],
                'qg_entropy_e1_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropIndex('qg_entropy_e1_idx');
        });
    }
};
