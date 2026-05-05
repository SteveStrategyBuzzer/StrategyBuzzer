<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duo_matches', function (Blueprint $table) {
            $table->string('lobby_code', 20)->nullable()->after('room_id');
            $table->index('lobby_code');
        });
    }

    public function down(): void
    {
        Schema::table('duo_matches', function (Blueprint $table) {
            $table->dropIndex(['lobby_code']);
            $table->dropColumn('lobby_code');
        });
    }
};
