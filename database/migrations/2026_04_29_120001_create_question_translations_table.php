<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_translations', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('question_group_id');
            $table->string('language', 8);

            $table->text('question_text');
            $table->text('answer_a');
            $table->text('answer_b');
            $table->text('answer_c')->nullable();
            $table->text('answer_d')->nullable();
            $table->char('correct_answer_key', 1);

            $table->text('explanation')->nullable();
            $table->text('saviez_vous')->nullable();

            $table->timestamps();

            $table->foreign('question_group_id')
                ->references('id')->on('question_groups')
                ->onDelete('cascade');

            $table->unique(['question_group_id', 'language'], 'qt_group_lang_unique');
            $table->index(['language', 'question_group_id'], 'qt_lang_group_idx');
        });

        DB::statement("ALTER TABLE question_translations
            ADD CONSTRAINT qt_correct_answer_key_check
            CHECK (correct_answer_key IN ('A', 'B', 'C', 'D'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('question_translations');
    }
};
