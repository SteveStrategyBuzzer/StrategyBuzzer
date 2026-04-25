<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_team_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('league_team_matches', 'room_id')) {
                $table->string('room_id')->nullable()->after('status');
                $table->index('room_id');
            }
            if (!Schema::hasColumn('league_team_matches', 'lobby_code')) {
                $table->string('lobby_code')->nullable()->after('room_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('league_team_matches', function (Blueprint $table) {
            if (Schema::hasColumn('league_team_matches', 'lobby_code')) {
                $table->dropColumn('lobby_code');
            }
            if (Schema::hasColumn('league_team_matches', 'room_id')) {
                $table->dropIndex(['room_id']);
                $table->dropColumn('room_id');
            }
        });
    }
};
