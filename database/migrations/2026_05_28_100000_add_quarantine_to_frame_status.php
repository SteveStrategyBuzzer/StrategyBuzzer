<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extends question_intents.frame_status CHECK constraint to include 'quarantine'.
 *
 * 'quarantine' is set when Phase 2 still detects major anomalies (policy C/D)
 * after the inline targeted-retry loop — the kernel requires human editorial
 * review before it can proceed to translation.
 *
 * All prior values are preserved unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
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
                'rejected',
                'human_review',
                'quarantine'
            ))
        ");
    }

    public function down(): void
    {
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
                'rejected',
                'human_review'
            ))
        ");
    }
};
