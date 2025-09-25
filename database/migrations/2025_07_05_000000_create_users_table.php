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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // ✅ Ajouts pour le jeu
            $table->unsignedInteger('coins')->default(0); // Pièces d’intelligence
            $table->unsignedInteger('lives')->default(5); // Vies de base
            $table->timestamp('infinite_lives_until')->nullable(); // Pack vies infinies
            $table->string('rank')->default('Rookie'); // Rang ou grade du joueur

            // ✅ Paramètres JSON (avatars, sons, langue, pays, etc.)
            $table->json('profile_settings')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
