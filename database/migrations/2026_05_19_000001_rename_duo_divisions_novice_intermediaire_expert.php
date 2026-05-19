<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE player_divisions
            SET division = CASE division
                WHEN 'bronze'   THEN 'novice'
                WHEN 'argent'   THEN 'intermediaire'
                WHEN 'or'       THEN 'expert'
                WHEN 'platine'  THEN 'expert'
                WHEN 'diamant'  THEN 'expert'
                WHEN 'legende'  THEN 'expert'
                ELSE division
            END
            WHERE mode = 'duo'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE player_divisions
            SET division = CASE division
                WHEN 'novice'        THEN 'bronze'
                WHEN 'intermediaire' THEN 'argent'
                WHEN 'expert'        THEN 'or'
                ELSE division
            END
            WHERE mode = 'duo'
        ");
    }
};
