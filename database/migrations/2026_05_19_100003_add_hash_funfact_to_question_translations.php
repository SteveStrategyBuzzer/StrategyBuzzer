<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_translations', function (Blueprint $table) {
            $table->char('hash_question', 64)->nullable()->after('saviez_vous');
            $table->char('hash_answer', 64)->nullable()->after('hash_question');
            $table->unsignedTinyInteger('funfact_score')->nullable()->after('hash_answer');

            $table->index(['language', 'hash_question'], 'qt_lang_hash_q_idx');
            $table->index(['language', 'hash_answer'], 'qt_lang_hash_a_idx');
        });

        DB::statement("ALTER TABLE question_translations
            ADD CONSTRAINT qt_funfact_score_check
            CHECK (funfact_score IS NULL OR funfact_score BETWEEN 1 AND 4)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE question_translations DROP CONSTRAINT IF EXISTS qt_funfact_score_check');

        Schema::table('question_translations', function (Blueprint $table) {
            $table->dropIndex('qt_lang_hash_q_idx');
            $table->dropIndex('qt_lang_hash_a_idx');
            $table->dropColumn(['hash_question', 'hash_answer', 'funfact_score']);
        });
    }
};
