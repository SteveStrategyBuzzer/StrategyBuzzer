<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_games', function (Blueprint $table) {
            if (!Schema::hasColumn('master_games', 'room_id')) {
                $table->string('room_id')->nullable()->after('firebase_id');
                $table->index('room_id');
            }
            if (!Schema::hasColumn('master_games', 'lobby_code')) {
                $table->string('lobby_code')->nullable()->after('room_id');
            }
        });

        Schema::table('league_individual_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('league_individual_matches', 'room_id')) {
                $table->string('room_id')->nullable()->after('status');
                $table->index('room_id');
            }
            if (!Schema::hasColumn('league_individual_matches', 'lobby_code')) {
                $table->string('lobby_code')->nullable()->after('room_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_games', function (Blueprint $table) {
            if (Schema::hasColumn('master_games', 'lobby_code')) {
                $table->dropColumn('lobby_code');
            }
            if (Schema::hasColumn('master_games', 'room_id')) {
                $table->dropIndex(['room_id']);
                $table->dropColumn('room_id');
            }
        });

        Schema::table('league_individual_matches', function (Blueprint $table) {
            if (Schema::hasColumn('league_individual_matches', 'lobby_code')) {
                $table->dropColumn('lobby_code');
            }
            if (Schema::hasColumn('league_individual_matches', 'room_id')) {
                $table->dropIndex(['room_id']);
                $table->dropColumn('room_id');
            }
        });
    }
};
