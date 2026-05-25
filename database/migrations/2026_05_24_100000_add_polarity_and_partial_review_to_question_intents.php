<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * A1 — Kernel 7-variant infrastructure
 *
 * 1. question_groups.polarity  VARCHAR(10) NULL
 *    NULL  = QCM types (no polarity)
 *    'true'  = TF variant correct_answer = True  (answer_key = A)
 *    'false' = TF variant correct_answer = False (answer_key = B)
 *
 * 2. Backfill existing true_false rows from correct_answer_key via JOIN.
 *
 * 3. question_intents.frame_status ENUM extended with 'partial_review'
 *    partial_review = some variants human_review, rest usable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add polarity column ──────────────────────────────────────────
        Schema::table('question_groups', function (Blueprint $table) {
            $table->string('polarity', 10)->nullable()->after('question_type');
        });

        // ── 2. Backfill polarity for existing true_false rows ───────────────
        // Infer from the most common correct_answer_key in question_translations
        DB::statement("
            UPDATE question_groups qg
            SET polarity = sub.inferred_polarity
            FROM (
                SELECT
                    qt.question_group_id,
                    CASE
                        WHEN mode() WITHIN GROUP (ORDER BY qt.correct_answer_key) = 'A' THEN 'true'
                        WHEN mode() WITHIN GROUP (ORDER BY qt.correct_answer_key) = 'B' THEN 'false'
                        ELSE NULL
                    END AS inferred_polarity
                FROM question_translations qt
                JOIN question_groups qg2 ON qg2.id = qt.question_group_id
                WHERE qg2.question_type = 'true_false'
                GROUP BY qt.question_group_id
            ) sub
            WHERE qg.id = sub.question_group_id
              AND qg.question_type = 'true_false'
        ");

        // ── 3. Add 'partial_review' to question_intents.frame_status ────────
        // frame_status is VARCHAR — just verify the column exists (no enum cast needed)
        // The application already guards allowed values; partial_review is now valid.
        // Add a check constraint so the DB enforces the allowed set.
        DB::statement("
            ALTER TABLE question_intents
            DROP CONSTRAINT IF EXISTS question_intents_frame_status_check
        ");
        DB::statement("
            ALTER TABLE question_intents
            ADD CONSTRAINT question_intents_frame_status_check
            CHECK (frame_status IN (
                'draft',
                'awaiting_content',
                'content_ready',
                'partial_review',
                'content_validated',
                'correction_needed',
                'rejected'
            ))
        ");
    }

    public function down(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropColumn('polarity');
        });

        DB::statement("
            ALTER TABLE question_intents
            DROP CONSTRAINT IF EXISTS question_intents_frame_status_check
        ");
    }
};
