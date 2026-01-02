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
        Schema::table('duo_matches', function (Blueprint $table) {
            $table->string('room_id')->nullable()->after('lobby_code');
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('duo_matches', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
            $table->dropColumn('room_id');
        });
    }
};
