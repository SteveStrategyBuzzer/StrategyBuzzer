<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('master_game_questions', function (Blueprint $table) {
            $table->boolean('is_tiebreaker')->default(false)->after('media_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_game_questions', function (Blueprint $table) {
            $table->dropColumn('is_tiebreaker');
        });
    }
};
