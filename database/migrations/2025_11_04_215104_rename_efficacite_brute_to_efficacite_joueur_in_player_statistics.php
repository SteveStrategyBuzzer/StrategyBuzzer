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
        Schema::table('player_statistics', function (Blueprint $table) {
            $table->renameColumn('efficacite_brute', 'efficacite_joueur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_statistics', function (Blueprint $table) {
            $table->renameColumn('efficacite_joueur', 'efficacite_brute');
        });
    }
};
