<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_games', function (Blueprint $table) {
            $table->string('tiebreaker_mode')->default('bonus');
        });
    }

    public function down(): void
    {
        Schema::table('master_games', function (Blueprint $table) {
            $table->dropColumn('tiebreaker_mode');
        });
    }
};
