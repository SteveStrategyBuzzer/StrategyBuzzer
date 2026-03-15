<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_qualification_events', function (Blueprint $table) {
            $table->dropUnique('bqe_user_event_ref_unique');
        });

        DB::statement("
            CREATE UNIQUE INDEX bqe_solo_level_unique
            ON bot_qualification_events (user_id, reference_id)
            WHERE event_type = 'solo_level'
        ");

        DB::statement("ALTER TABLE bot_qualification_events ADD CONSTRAINT bqe_event_type_check CHECK (event_type IN ('solo_level', 'duo_match', 'league_individual_match'))");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS bqe_solo_level_unique");
        DB::statement("ALTER TABLE bot_qualification_events DROP CONSTRAINT IF EXISTS bqe_event_type_check");

        Schema::table('bot_qualification_events', function (Blueprint $table) {
            $table->unique(['user_id', 'event_type', 'reference_id'], 'bqe_user_event_ref_unique');
        });
    }
};
