<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quests')
            ->whereIn('id', [70, 71, 72])
            ->update(['auto_complete' => true]);
    }

    public function down(): void
    {
        DB::table('quests')
            ->whereIn('id', [70, 71, 72])
            ->update(['auto_complete' => false]);
    }
};
