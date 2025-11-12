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
        Schema::create('profile_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            
            // Stats Solo
            $table->integer('solo_matchs_joues')->default(0);
            $table->integer('solo_victoires')->default(0);
            $table->integer('solo_defaites')->default(0);
            $table->decimal('solo_ratio_victoire', 5, 2)->default(0);
            $table->integer('solo_matchs_3_manches')->default(0);
            $table->integer('solo_victoires_3_manches')->default(0);
            $table->decimal('solo_performance_moyenne', 5, 2)->default(0);
            
            // Stats Duo
            $table->integer('duo_matchs_joues')->default(0);
            $table->integer('duo_victoires')->default(0);
            $table->integer('duo_defaites')->default(0);
            $table->decimal('duo_ratio_victoire', 5, 2)->default(0);
            $table->decimal('duo_performance_moyenne', 5, 2)->default(0);
            
            // Stats Ligue
            $table->integer('league_matchs_joues')->default(0);
            $table->integer('league_victoires')->default(0);
            $table->integer('league_defaites')->default(0);
            $table->decimal('league_ratio_victoire', 5, 2)->default(0);
            $table->decimal('league_performance_moyenne', 5, 2)->default(0);
            
            $table->timestamps();
            
            // Index pour requêtes rapides
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_stats');
    }
};
