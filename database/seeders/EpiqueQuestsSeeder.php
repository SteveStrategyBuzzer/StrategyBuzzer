<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EpiqueQuestsSeeder extends Seeder
{
    public function run(): void
    {
        $epiqueQuests = [
            [
                'name' => 'Perfectionniste ultime',
                'category' => 'Performance',
                'condition' => 'Obtenir 100% de réponses correctes sur 50 questions',
                'reward_coins' => 150,
                'rarity' => 'Épique',
                'badge_emoji' => '💯',
                'badge_description' => 'Score parfait',
                'detection_code' => 'perfect_accuracy_epique',
                'detection_params' => json_encode(['count' => 50, 'accuracy' => 100]),
                'auto_complete' => false
            ],
            [
                'name' => 'Héros du buzzer',
                'category' => 'Buzz',
                'condition' => 'Être le premier à buzzer 50 fois dans la journée',
                'reward_coins' => 140,
                'rarity' => 'Épique',
                'badge_emoji' => '🦸',
                'badge_description' => 'Super-héros',
                'detection_code' => 'daily_first_buzz_50',
                'detection_params' => json_encode(['count' => 50]),
                'auto_complete' => false
            ],
            [
                'name' => 'Encyclopédie vivante',
                'category' => 'Connaissance',
                'condition' => 'Jouer dans tous les thèmes disponibles',
                'reward_coins' => 160,
                'rarity' => 'Épique',
                'badge_emoji' => '📖',
                'badge_description' => 'Livre encyclopédie',
                'detection_code' => 'all_themes_played',
                'detection_params' => json_encode(['all_themes' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Légende du duo',
                'category' => 'Duo',
                'condition' => 'Atteindre le rang Diamant en mode Duo',
                'reward_coins' => 170,
                'rarity' => 'Épique',
                'badge_emoji' => '💎',
                'badge_description' => 'Diamant brillant',
                'detection_code' => 'duo_rank_diamond',
                'detection_params' => json_encode(['rank' => 'Diamant']),
                'auto_complete' => false
            ],
            [
                'name' => 'Boss slayer',
                'category' => 'Boss',
                'condition' => 'Vaincre 5 boss différents',
                'reward_coins' => 180,
                'rarity' => 'Épique',
                'badge_emoji' => '🏆',
                'badge_description' => 'Trophée champion',
                'detection_code' => 'boss_defeats',
                'detection_params' => json_encode(['count' => 5, 'unique' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Marathon nocturne',
                'category' => 'Endurance',
                'condition' => 'Jouer 50 matchs entre 22h et 6h',
                'reward_coins' => 145,
                'rarity' => 'Épique',
                'badge_emoji' => '🦉',
                'badge_description' => 'Hibou noctambule',
                'detection_code' => 'night_marathon',
                'detection_params' => json_encode(['count' => 50, 'start_hour' => 22, 'end_hour' => 6]),
                'auto_complete' => false
            ],
            [
                'name' => 'Collectionneur fou',
                'category' => 'Collection',
                'condition' => 'Déverrouiller 30 avatars différents',
                'reward_coins' => 155,
                'rarity' => 'Épique',
                'badge_emoji' => '🎨',
                'badge_description' => 'Palette artistique',
                'detection_code' => 'avatar_collection_epique',
                'detection_params' => json_encode(['count' => 30]),
                'auto_complete' => false
            ],
            [
                'name' => 'Speed demon',
                'category' => 'Vitesse',
                'condition' => 'Répondre à 20 questions en moins d\'1 seconde chacune',
                'reward_coins' => 165,
                'rarity' => 'Épique',
                'badge_emoji' => '⚡',
                'badge_description' => 'Éclair rapide',
                'detection_code' => 'ultra_fast_answers_epique',
                'detection_params' => json_encode(['count' => 20, 'max_time' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Indestructible',
                'category' => 'Série',
                'condition' => 'Gagner 25 matchs d\'affilée',
                'reward_coins' => 175,
                'rarity' => 'Épique',
                'badge_emoji' => '🔱',
                'badge_description' => 'Trident puissant',
                'detection_code' => 'win_streak_epique',
                'detection_params' => json_encode(['count' => 25]),
                'auto_complete' => false
            ],
            [
                'name' => 'Magnat de la boutique',
                'category' => 'Boutique',
                'condition' => 'Acheter 20 objets dans la boutique',
                'reward_coins' => 150,
                'rarity' => 'Épique',
                'badge_emoji' => '💰',
                'badge_description' => 'Sac d\'argent',
                'detection_code' => 'shop_purchases_epique',
                'detection_params' => json_encode(['count' => 20]),
                'auto_complete' => false
            ],
            [
                'name' => 'Génie stratégique',
                'category' => 'Stratégie',
                'condition' => 'Utiliser 10 compétences d\'avatar différentes',
                'reward_coins' => 160,
                'rarity' => 'Épique',
                'badge_emoji' => '🎯',
                'badge_description' => 'Cible stratégique',
                'detection_code' => 'unique_skills_used',
                'detection_params' => json_encode(['count' => 10, 'unique' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Ascension fulgurante',
                'category' => 'Progression',
                'condition' => 'Atteindre le niveau 50',
                'reward_coins' => 170,
                'rarity' => 'Épique',
                'badge_emoji' => '🚀',
                'badge_description' => 'Fusée décollage',
                'detection_code' => 'level_reached_epique',
                'detection_params' => json_encode(['level' => 50]),
                'auto_complete' => false
            ],
            [
                'name' => 'Champion international',
                'category' => 'Ligue',
                'condition' => 'Gagner 50 matchs en mode Ligue',
                'reward_coins' => 165,
                'rarity' => 'Épique',
                'badge_emoji' => '🌍',
                'badge_description' => 'Globe terrestre',
                'detection_code' => 'league_wins',
                'detection_params' => json_encode(['count' => 50]),
                'auto_complete' => false
            ],
            [
                'name' => 'Maître des thèmes',
                'category' => 'Thème',
                'condition' => 'Obtenir 50 victoires dans 5 thèmes différents',
                'reward_coins' => 180,
                'rarity' => 'Épique',
                'badge_emoji' => '🎪',
                'badge_description' => 'Chapiteau cirque',
                'detection_code' => 'multi_theme_mastery',
                'detection_params' => json_encode(['wins_per_theme' => 50, 'themes' => 5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Influenceur StrategyBuzzer',
                'category' => 'Sociale',
                'condition' => 'Inviter 20 nouveaux joueurs',
                'reward_coins' => 190,
                'rarity' => 'Épique',
                'badge_emoji' => '📱',
                'badge_description' => 'Téléphone social',
                'detection_code' => 'referrals_epique',
                'detection_params' => json_encode(['count' => 20]),
                'auto_complete' => false
            ],
            [
                'name' => 'Millionnaire',
                'category' => 'Richesse',
                'condition' => 'Accumuler 1 000 000 coins',
                'reward_coins' => 200,
                'rarity' => 'Épique',
                'badge_emoji' => '💵',
                'badge_description' => 'Billet de banque',
                'detection_code' => 'coins_accumulated',
                'detection_params' => json_encode(['amount' => 1000000]),
                'auto_complete' => false
            ],
            [
                'name' => 'Vétéran aguerri',
                'category' => 'Expérience',
                'condition' => 'Jouer 500 matchs au total',
                'reward_coins' => 175,
                'rarity' => 'Épique',
                'badge_emoji' => '⚓',
                'badge_description' => 'Ancre marine',
                'detection_code' => 'total_matches_epique',
                'detection_params' => json_encode(['count' => 500]),
                'auto_complete' => false
            ],
            [
                'name' => 'Roi du comeback',
                'category' => 'Résilience',
                'condition' => 'Gagner 10 matchs après avoir perdu les 2 premières manches',
                'reward_coins' => 185,
                'rarity' => 'Épique',
                'badge_emoji' => '🔄',
                'badge_description' => 'Flèches circulaires',
                'detection_code' => 'comeback_victories_epique',
                'detection_params' => json_encode(['count' => 10, 'rounds_lost_first' => 2]),
                'auto_complete' => false
            ],
            [
                'name' => 'Maître du jeu ultime',
                'category' => 'Master',
                'condition' => 'Organiser 50 quiz en mode Maître du jeu',
                'reward_coins' => 170,
                'rarity' => 'Épique',
                'badge_emoji' => '🎬',
                'badge_description' => 'Clap de cinéma',
                'detection_code' => 'master_games_hosted_epique',
                'detection_params' => json_encode(['count' => 50]),
                'auto_complete' => false
            ],
            [
                'name' => 'Conquérant de divisions',
                'category' => 'Progression',
                'condition' => 'Atteindre la division Légende',
                'reward_coins' => 195,
                'rarity' => 'Épique',
                'badge_emoji' => '👑',
                'badge_description' => 'Couronne légende',
                'detection_code' => 'division_reached',
                'detection_params' => json_encode(['division' => 'Légende']),
                'auto_complete' => false
            ]
        ];

        DB::table('quests')->insert($epiqueQuests);
    }
}
