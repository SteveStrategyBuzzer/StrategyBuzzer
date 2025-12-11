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
        Schema::table('users', function (Blueprint $table) {
            $table->string('temp_access_division')->nullable();
            $table->timestamp('temp_access_expires_at')->nullable();
            $table->string('current_match_id')->nullable();
            $table->timestamp('match_started_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['temp_access_division', 'temp_access_expires_at', 'current_match_id', 'match_started_at']);
        });
    }
};
