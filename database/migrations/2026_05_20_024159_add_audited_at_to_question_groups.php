<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->timestamp('audited_at')->nullable()->after('correction_notes');
            $table->index('audited_at', 'qg_audited_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropIndex('qg_audited_at_idx');
            $table->dropColumn('audited_at');
        });
    }
};
