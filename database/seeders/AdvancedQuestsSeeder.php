<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdvancedQuestsSeeder extends Seeder
{
    public function run(): void
    {
        $quests = [
            // ============================
            // RARE (8 quêtes fonctionnelles) - 75-150 pièces
            // ============================
            [
                'name' => 'Marathonien',
                'category' => '⚔️ Jeu',
                'condition' => 'Jouez 50 parties',
                'reward_coins' => 75,
                'rarity' => 'Rare',
                'badge_emoji' => '🏃',
                'badge_description' => 'Coureur',
                'detection_code' => 'play_50_matches',
                'detection_params' => json_encode(['matches' => 50]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Parfait x3',
                'category' => '🧠 Intellectuelle',
                'condition' => 'Obtenez 3 scores parfaits (10/10)',
                'reward_coins' => 150,
                'rarity' => 'Rare',
                'badge_emoji' => '💎',
                'badge_description' => 'Diamant',
                'detection_code' => 'perfect_score_3',
                'detection_params' => json_encode(['count' => 3]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Polyvalent',
                'category' => '🎭 Thématique',
                'condition' => 'Jouez dans 5 thèmes différents',
                'reward_coins' => 100,
                'rarity' => 'Rare',
                'badge_emoji' => '🎭',
                'badge_description' => 'Masques de théâtre',
                'detection_code' => 'themes_5',
                'detection_params' => json_encode(['themes' => 5]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Duo Élite',
                'category' => '👥 Multijoueur',
                'condition' => 'Gagnez 10 parties en mode Duo',
                'reward_coins' => 100,
                'rarity' => 'Rare',
                'badge_emoji' => '👥',
                'badge_description' => 'Silhouettes',
                'detection_code' => 'duo_wins_10',
                'detection_params' => json_encode(['wins' => 10]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Collectionneur',
                'category' => '🎨 Collection',
                'condition' => 'Déverrouillez 10 avatars différents',
                'reward_coins' => 100,
                'rarity' => 'Rare',
                'badge_emoji' => '🎨',
                'badge_description' => 'Palette',
                'detection_code' => 'avatars_unlocked_10',
                'detection_params' => json_encode(['count' => 10]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Niveau 25',
                'category' => '📊 Progression',
                'condition' => 'Atteignez le niveau 25',
                'reward_coins' => 100,
                'rarity' => 'Rare',
                'badge_emoji' => '🎖️',
                'badge_description' => 'Médaille militaire',
                'detection_code' => 'level_25',
                'detection_params' => json_encode(['level' => 25]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Richesse',
                'category' => '💰 Économie',
                'condition' => 'Accumulez 1000 pièces',
                'reward_coins' => 100,
                'rarity' => 'Rare',
                'badge_emoji' => '💰',
                'badge_description' => 'Sac d\'argent',
                'detection_code' => 'coins_1000',
                'detection_params' => json_encode(['coins' => 1000]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Boss Hunter',
                'category' => '👹 Combat',
                'condition' => 'Battez 5 boss différents en mode Solo',
                'reward_coins' => 125,
                'rarity' => 'Rare',
                'badge_emoji' => '👹',
                'badge_description' => 'Ogre japonais',
                'detection_code' => 'boss_defeats_5',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => true,
            ],

            // ============================
            // ÉPIQUE (7 quêtes fonctionnelles) - 200-400 pièces
            // ============================
            [
                'name' => 'Centurion',
                'category' => '⚔️ Jeu',
                'condition' => 'Jouez 100 parties',
                'reward_coins' => 200,
                'rarity' => 'Épique',
                'badge_emoji' => '💪',
                'badge_description' => 'Biceps',
                'detection_code' => 'play_100_matches',
                'detection_params' => json_encode(['matches' => 100]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Parfait x10',
                'category' => '🧠 Intellectuelle',
                'condition' => 'Obtenez 10 scores parfaits (10/10)',
                'reward_coins' => 300,
                'rarity' => 'Épique',
                'badge_emoji' => '💠',
                'badge_description' => 'Diamant avec point',
                'detection_code' => 'perfect_score_10',
                'detection_params' => json_encode(['count' => 10]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Encyclopédie',
                'category' => '🎭 Thématique',
                'condition' => 'Jouez dans 10 thèmes différents',
                'reward_coins' => 250,
                'rarity' => 'Épique',
                'badge_emoji' => '📚',
                'badge_description' => 'Livres',
                'detection_code' => 'themes_10',
                'detection_params' => json_encode(['themes' => 10]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Niveau 50',
                'category' => '📊 Progression',
                'condition' => 'Atteignez le niveau 50',
                'reward_coins' => 300,
                'rarity' => 'Épique',
                'badge_emoji' => '🏆',
                'badge_description' => 'Trophée',
                'detection_code' => 'level_50',
                'detection_params' => json_encode(['level' => 50]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Millionnaire',
                'category' => '💰 Économie',
                'condition' => 'Accumulez 5000 pièces',
                'reward_coins' => 250,
                'rarity' => 'Épique',
                'badge_emoji' => '💎',
                'badge_description' => 'Gemme',
                'detection_code' => 'coins_5000',
                'detection_params' => json_encode(['coins' => 5000]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Maître des Avatars',
                'category' => '🎨 Collection',
                'condition' => 'Déverrouillez 25 avatars différents',
                'reward_coins' => 300,
                'rarity' => 'Épique',
                'badge_emoji' => '🎭',
                'badge_description' => 'Masques multiples',
                'detection_code' => 'avatars_unlocked_25',
                'detection_params' => json_encode(['count' => 25]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Division Argent',
                'category' => '🏅 Compétitif',
                'condition' => 'Atteignez la division Argent en Duo/Ligue',
                'reward_coins' => 250,
                'rarity' => 'Épique',
                'badge_emoji' => '🥈',
                'badge_description' => 'Médaille d\'argent',
                'detection_code' => 'division_silver',
                'detection_params' => null,
                'auto_complete' => true,
            ],

            // ============================
            // LÉGENDAIRE (4 quêtes fonctionnelles) - 500-1000 pièces
            // ============================
            [
                'name' => 'Vétéran',
                'category' => '⚔️ Jeu',
                'condition' => 'Jouez 250 parties',
                'reward_coins' => 500,
                'rarity' => 'Légendaire',
                'badge_emoji' => '⭐',
                'badge_description' => 'Étoile brillante',
                'detection_code' => 'play_250_matches',
                'detection_params' => json_encode(['matches' => 250]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Niveau 75',
                'category' => '📊 Progression',
                'condition' => 'Atteignez le niveau 75',
                'reward_coins' => 600,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🎖️',
                'badge_description' => 'Médaille d\'honneur',
                'detection_code' => 'level_75',
                'detection_params' => json_encode(['level' => 75]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Division Or',
                'category' => '🏅 Compétitif',
                'condition' => 'Atteignez la division Or en Duo/Ligue',
                'reward_coins' => 750,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🥇',
                'badge_description' => 'Médaille d\'or',
                'detection_code' => 'division_gold',
                'detection_params' => null,
                'auto_complete' => true,
            ],
            [
                'name' => 'Parfait x25',
                'category' => '🧠 Intellectuelle',
                'condition' => 'Obtenez 25 scores parfaits (10/10)',
                'reward_coins' => 1000,
                'rarity' => 'Légendaire',
                'badge_emoji' => '💠',
                'badge_description' => 'Diamant parfait',
                'detection_code' => 'perfect_score_25',
                'detection_params' => json_encode(['count' => 25]),
                'auto_complete' => true,
            ],

            // ============================
            // MAÎTRE (3 quêtes fonctionnelles) - 1500-3000 pièces
            // ============================
            [
                'name' => 'Maître Absolu',
                'category' => '⚔️ Jeu',
                'condition' => 'Jouez 500 parties',
                'reward_coins' => 2000,
                'rarity' => 'Maître',
                'badge_emoji' => '👑',
                'badge_description' => 'Couronne royale',
                'detection_code' => 'play_500_matches',
                'detection_params' => json_encode(['matches' => 500]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Niveau 100',
                'category' => '📊 Progression',
                'condition' => 'Atteignez le niveau maximum 100',
                'reward_coins' => 3000,
                'rarity' => 'Maître',
                'badge_emoji' => '💯',
                'badge_description' => 'Cent points',
                'detection_code' => 'level_100',
                'detection_params' => json_encode(['level' => 100]),
                'auto_complete' => true,
            ],
            [
                'name' => 'Division Légende',
                'category' => '🏅 Compétitif',
                'condition' => 'Atteignez la division Légende en Duo/Ligue',
                'reward_coins' => 1500,
                'rarity' => 'Maître',
                'badge_emoji' => '🌠',
                'badge_description' => 'Étoile filante',
                'detection_code' => 'division_legend',
                'detection_params' => null,
                'auto_complete' => true,
            ],
        ];

        foreach ($quests as $quest) {
            DB::table('quests')->updateOrInsert(
                ['detection_code' => $quest['detection_code']],
                $quest
            );
        }

        $this->command->info('✅ Quêtes avancées (Rare, Épique, Légendaire, Maître) ajoutées avec succès !');
    }
}
