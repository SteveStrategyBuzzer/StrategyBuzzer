<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** Ajouts à la table users */
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'coins')) {
                $table->unsignedInteger('coins')->default(0); // Pièces d’intelligence
            }
            if (!Schema::hasColumn('users', 'lives')) {
                $table->unsignedInteger('lives')->default(5); // Vies de base
            }
            if (!Schema::hasColumn('users', 'infinite_lives_until')) {
                $table->timestamp('infinite_lives_until')->nullable();
            }
            if (!Schema::hasColumn('users', 'rank')) {
                $table->string('rank')->default('Rookie');
            }
            if (!Schema::hasColumn('users', 'profile_settings')) {
                $table->json('profile_settings')->nullable();
            }
        });

        /** Table purchases (si absente) */
        if (!Schema::hasTable('purchases')) {
            Schema::create('purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('item_name');
                $table->string('item_type'); // avatar, abonnement, crédits, bonus
                $table->decimal('price', 8, 2)->nullable();
                $table->string('currency')->default('USD');
                $table->string('payment_method')->nullable();
                $table->string('status')->default('pending');
                $table->string('transaction_id')->nullable();
                $table->timestamps();
            });
        }

        /** Table user_preferences (si absente) */
        if (!Schema::hasTable('user_preferences')) {
            Schema::create('user_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('avatar_joueur')->nullable();
                $table->string('avatar_strategique')->nullable();
                $table->string('strategie_preferee')->nullable();
                $table->string('langue')->default('fr');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['coins', 'lives', 'infinite_lives_until', 'rank', 'profile_settings']);
        });

        Schema::dropIfExists('purchases');
        Schema::dropIfExists('user_preferences');
    }
};
