<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend question_intents.frame_status check constraint
 * to allow 'human_review'.
 *
 * human_review is set by KernelLoopAlerter when a kernel enters
 * a correction loop (repeated drift, kernel_collapse, reject_kernel,
 * same_drift_repeat). It blocks further auto-generation until a human
 * reviews and resets the kernel manually.
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
            DROP CONSTRAINT IF EXISTS qi_frame_status_check
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
                'rejected'
            ))
        ");
    }
};
