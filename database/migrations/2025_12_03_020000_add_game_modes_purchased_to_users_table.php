<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('duo_purchased')->default(false)->after('master_purchased');
            $table->boolean('league_purchased')->default(false)->after('duo_purchased');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['duo_purchased', 'league_purchased']);
        });
    }
};
