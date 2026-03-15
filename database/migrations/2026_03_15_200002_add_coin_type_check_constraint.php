<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE quests ADD CONSTRAINT quests_coin_type_check CHECK (coin_type IN ('competence', 'intelligence'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE quests DROP CONSTRAINT IF EXISTS quests_coin_type_check");
    }
};
