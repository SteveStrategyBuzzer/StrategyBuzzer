<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tighten the question_groups level/boss check from inclusive-OR to exclusive-OR.
 *
 * Initial migration allowed both columns to be non-null, which produced ambiguous
 * targeting (a row could match both `whereNotNull('difficulty_level')` for Solo and
 * `whereNotNull('boss_level')` for Boss). The canonical contract is "exactly one of
 * difficulty_level or boss_level is set"; this migration encodes that.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE question_groups DROP CONSTRAINT IF EXISTS qg_level_or_boss_check');
        DB::statement("
            ALTER TABLE question_groups
            ADD CONSTRAINT qg_level_xor_boss_check
            CHECK (
                (difficulty_level IS NOT NULL AND boss_level IS NULL)
                OR
                (difficulty_level IS NULL AND boss_level IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE question_groups DROP CONSTRAINT IF EXISTS qg_level_xor_boss_check');
        DB::statement("
            ALTER TABLE question_groups
            ADD CONSTRAINT qg_level_or_boss_check
            CHECK (difficulty_level IS NOT NULL OR boss_level IS NOT NULL)
        ");
    }
};
