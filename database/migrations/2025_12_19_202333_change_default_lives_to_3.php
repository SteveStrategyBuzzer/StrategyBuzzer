<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change the default value for lives column from 5 to 3
        DB::statement('ALTER TABLE users ALTER COLUMN lives SET DEFAULT 3');
        
        // Fix existing users who have more than 3 lives (cap at 3)
        DB::table('users')->where('lives', '>', 3)->update(['lives' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN lives SET DEFAULT 5');
    }
};
