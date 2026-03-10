<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coin_ledger', function (Blueprint $table) {
            $table->string('coin_type')->default('intelligence')->after('delta');
            $table->index('coin_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin_ledger', function (Blueprint $table) {
            $table->dropIndex(['coin_type']);
            $table->dropColumn('coin_type');
        });
    }
};
