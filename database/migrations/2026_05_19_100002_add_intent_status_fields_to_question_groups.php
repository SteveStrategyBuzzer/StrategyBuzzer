<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->string('post_review_status', 32)->nullable()->after('source');
            $table->unsignedBigInteger('question_intent_id')->nullable()->after('post_review_status');
            $table->string('question_intent_key', 255)->nullable()->after('question_intent_id');
            $table->string('subject', 255)->nullable()->after('question_intent_key');
            $table->string('angle_large', 255)->nullable()->after('subject');
            $table->string('micro_angle', 255)->nullable()->after('angle_large');
            $table->string('readable_code', 32)->nullable()->after('micro_angle');
            $table->text('correction_notes')->nullable()->after('readable_code');

            $table->index('post_review_status', 'qg_post_review_status_idx');
            $table->index('question_intent_key', 'qg_intent_key_idx');
            $table->index('readable_code', 'qg_readable_code_idx');

            $table->foreign('question_intent_id')
                ->references('id')->on('question_intents')
                ->nullOnDelete();
        });

        DB::statement("ALTER TABLE question_groups
            ADD CONSTRAINT qg_post_review_status_check
            CHECK (post_review_status IS NULL OR post_review_status IN (
                'review_bank','correction_needed','ready_bank',
                'blocked_critical','duplicate_blocked'
            ))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE question_groups DROP CONSTRAINT IF EXISTS qg_post_review_status_check');

        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropForeign(['question_intent_id']);
            $table->dropIndex('qg_post_review_status_idx');
            $table->dropIndex('qg_intent_key_idx');
            $table->dropIndex('qg_readable_code_idx');
            $table->dropColumn([
                'post_review_status',
                'question_intent_id',
                'question_intent_key',
                'subject',
                'angle_large',
                'micro_angle',
                'readable_code',
                'correction_notes',
            ]);
        });
    }
};
