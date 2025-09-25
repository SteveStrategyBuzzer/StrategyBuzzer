<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appliquer la migration.
     * On rend la colonne password nullable pour accepter les connexions via Google/Facebook sans mot de passe initial.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();

            // 👇 Si tu souhaites à l'avenir, d'autres colonnes peuvent être modifiées ici :
            // $table->string('avatar_url')->nullable()->after('email');
            // $table->boolean('is_verified')->default(false)->after('email_verified_at');
        });
    }

    /**
     * Annuler la migration.
     * On remet la colonne password comme NOT NULL (comme dans la migration d’origine).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};

