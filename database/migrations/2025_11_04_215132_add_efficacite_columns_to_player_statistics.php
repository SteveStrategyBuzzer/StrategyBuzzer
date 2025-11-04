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
            $table->decimal('efficacite_partie', 5, 2)->nullable()->after('efficacite_joueur');
            $table->decimal('efficacite_manche', 5, 2)->nullable()->after('efficacite_partie');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_statistics', function (Blueprint $table) {
            $table->dropColumn(['efficacite_partie', 'efficacite_manche']);
        });
    }
};
