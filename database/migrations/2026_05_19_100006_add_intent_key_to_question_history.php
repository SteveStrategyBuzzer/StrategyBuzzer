<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_history', function (Blueprint $table) {
            $table->string('question_intent_key', 255)->nullable()->after('niveau');
            $table->unsignedBigInteger('question_group_id')->nullable()->after('question_intent_key');

            $table->index(['user_id', 'question_intent_key'], 'qh_user_intent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('question_history', function (Blueprint $table) {
            $table->dropIndex('qh_user_intent_idx');
            $table->dropColumn(['question_intent_key', 'question_group_id']);
        });
    }
};
