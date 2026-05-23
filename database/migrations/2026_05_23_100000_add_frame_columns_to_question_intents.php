<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_intents', function (Blueprint $table) {
            $table->jsonb('frame_en')->nullable()->after('answer_target');
            $table->string('frame_status', 32)->nullable()->after('frame_en');
            $table->timestamp('frame_validated_at')->nullable()->after('frame_status');
        });

        DB::statement("ALTER TABLE question_intents
            ADD CONSTRAINT qi_frame_status_check
            CHECK (frame_status IS NULL OR frame_status IN (
                'draft',
                'awaiting_content',
                'content_ready',
                'content_validated',
                'rejected'
            ))");

        DB::statement('CREATE INDEX qi_frame_status_idx ON question_intents (frame_status) WHERE frame_status IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS qi_frame_status_idx');
        DB::statement('ALTER TABLE question_intents DROP CONSTRAINT IF EXISTS qi_frame_status_check');

        Schema::table('question_intents', function (Blueprint $table) {
            $table->dropColumn(['frame_en', 'frame_status', 'frame_validated_at']);
        });
    }
};
