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
        // Drop ancienne table user_quest_progress d'abord (foreign key), puis quests
        Schema::dropIfExists('user_quest_progress');
        Schema::dropIfExists('quests');
        
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nom de la quête
            $table->string('category'); // Catégorie (⚔️ Jeu, 🧠 Intellectuelle, etc.)
            $table->text('condition'); // Description de la condition
            $table->integer('reward_coins')->default(0); // Récompense en pièces (coins, pas intelligence_pieces)
            $table->string('rarity')->default('Standard'); // Rareté (Standard, Rare, Épique, Légendaire, Maître, Quotidiennes)
            $table->string('badge_emoji'); // Emoji du badge
            $table->string('badge_description'); // Description du badge (ex: "Cœur vert")
            $table->string('detection_code')->unique(); // Code unique pour la détection (ex: 'first_match', 'perfect_score')
            $table->json('detection_params')->nullable(); // Paramètres supplémentaires pour la détection
            $table->boolean('auto_complete')->default(true); // Attribution automatique (true) ou manuelle (false)
            $table->timestamps();
        });
        
        // Recréer user_quest_progress avec le bon schéma
        Schema::create('user_quest_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quest_id')->constrained()->onDelete('cascade');
            $table->timestamp('completed_at')->nullable(); // Date de complétion
            $table->json('progress')->nullable(); // Progression actuelle (ex: {"buzzes": 5, "target": 10})
            $table->boolean('rewarded')->default(false); // Si la récompense a été donnée
            $table->timestamps();
            
            // Un utilisateur ne peut avoir qu'une seule entrée par quête
            $table->unique(['user_id', 'quest_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_quest_progress');
        Schema::dropIfExists('quests');
    }
};
