<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX coin_ledger_idempotency_unique
            ON coin_ledger (user_id, ref_type, ref_id, reason, coin_type)
            WHERE ref_type IS NOT NULL AND ref_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS coin_ledger_idempotency_unique');
    }
};
