<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RareQuestsSeeder extends Seeder
{
    public function run(): void
    {
        $rareQuests = [
            [
                'name' => 'Série d\'excellence',
                'category' => 'Série',
                'condition' => 'Gagner 3 manches consécutives',
                'reward_coins' => 80,
                'rarity' => 'Rare',
                'badge_emoji' => '🔥',
                'badge_description' => 'Flamme triple',
                'detection_code' => 'consecutive_wins',
                'detection_params' => json_encode(['count' => 3]),
                'auto_complete' => false
            ],
            [
                'name' => 'Roi du temps',
                'category' => 'Performance',
                'condition' => 'Répondre à 5 questions en moins de 2 secondes chacune',
                'reward_coins' => 85,
                'rarity' => 'Rare',
                'badge_emoji' => '⏱️',
                'badge_description' => 'Chronomètre précis',
                'detection_code' => 'fast_answers',
                'detection_params' => json_encode(['count' => 5, 'max_time' => 2]),
                'auto_complete' => false
            ],
            [
                'name' => 'Touche-à-tout',
                'category' => 'Exploration',
                'condition' => 'Jouer dans 5 thèmes différents',
                'reward_coins' => 75,
                'rarity' => 'Rare',
                'badge_emoji' => '🌈',
                'badge_description' => 'Arc-en-ciel',
                'detection_code' => 'different_themes',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Machine à réponses',
                'category' => 'Performance',
                'condition' => 'Répondre correctement à 20 questions d\'affilée',
                'reward_coins' => 90,
                'rarity' => 'Rare',
                'badge_emoji' => '🤖',
                'badge_description' => 'Robot intelligent',
                'detection_code' => 'consecutive_correct',
                'detection_params' => json_encode(['count' => 20]),
                'auto_complete' => false
            ],
            [
                'name' => 'Champion du duo',
                'category' => 'Duo',
                'condition' => 'Gagner 5 matchs en mode Duo',
                'reward_coins' => 85,
                'rarity' => 'Rare',
                'badge_emoji' => '👥',
                'badge_description' => 'Duo victorieux',
                'detection_code' => 'duo_wins',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Expert thématique',
                'category' => 'Thème',
                'condition' => 'Obtenir 10 victoires dans un même thème',
                'reward_coins' => 80,
                'rarity' => 'Rare',
                'badge_emoji' => '📚',
                'badge_description' => 'Livre ouvert',
                'detection_code' => 'theme_wins',
                'detection_params' => json_encode(['count' => 10, 'same_theme' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Invincible',
                'category' => 'Série',
                'condition' => 'Jouer 10 matchs sans perdre',
                'reward_coins' => 95,
                'rarity' => 'Rare',
                'badge_emoji' => '🛡️',
                'badge_description' => 'Bouclier protecteur',
                'detection_code' => 'undefeated_streak',
                'detection_params' => json_encode(['count' => 10]),
                'auto_complete' => false
            ],
            [
                'name' => 'Précision mortelle',
                'category' => 'Performance',
                'condition' => 'Atteindre 95% de précision sur 20 questions',
                'reward_coins' => 85,
                'rarity' => 'Rare',
                'badge_emoji' => '🎯',
                'badge_description' => 'Cible précise',
                'detection_code' => 'accuracy_rate',
                'detection_params' => json_encode(['count' => 20, 'accuracy' => 95]),
                'auto_complete' => false
            ],
            [
                'name' => 'Marathonien',
                'category' => 'Endurance',
                'condition' => 'Jouer 3 heures en une journée',
                'reward_coins' => 90,
                'rarity' => 'Rare',
                'badge_emoji' => '🏃',
                'badge_description' => 'Coureur endurant',
                'detection_code' => 'daily_playtime',
                'detection_params' => json_encode(['hours' => 3]),
                'auto_complete' => false
            ],
            [
                'name' => 'Gentleman du buzzer',
                'category' => 'Fair-play',
                'condition' => 'Ne jamais buzzer incorrectement sur 10 matchs',
                'reward_coins' => 80,
                'rarity' => 'Rare',
                'badge_emoji' => '🎩',
                'badge_description' => 'Chapeau élégant',
                'detection_code' => 'perfect_buzz_accuracy',
                'detection_params' => json_encode(['matches' => 10]),
                'auto_complete' => false
            ],
            [
                'name' => 'Collectionneur d\'avatars',
                'category' => 'Collection',
                'condition' => 'Déverrouiller 10 avatars différents',
                'reward_coins' => 75,
                'rarity' => 'Rare',
                'badge_emoji' => '🎭',
                'badge_description' => 'Masques variés',
                'detection_code' => 'avatar_collection',
                'detection_params' => json_encode(['count' => 10]),
                'auto_complete' => false
            ],
            [
                'name' => 'Maître de la stratégie',
                'category' => 'Stratégie',
                'condition' => 'Utiliser une compétence d\'avatar 20 fois',
                'reward_coins' => 85,
                'rarity' => 'Rare',
                'badge_emoji' => '🧠',
                'badge_description' => 'Cerveau stratégique',
                'detection_code' => 'skill_usage',
                'detection_params' => json_encode(['count' => 20]),
                'auto_complete' => false
            ],
            [
                'name' => 'Ascension rapide',
                'category' => 'Progression',
                'condition' => 'Monter de 5 niveaux en une semaine',
                'reward_coins' => 90,
                'rarity' => 'Rare',
                'badge_emoji' => '📈',
                'badge_description' => 'Graphique montant',
                'detection_code' => 'weekly_level_gain',
                'detection_params' => json_encode(['levels' => 5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Commerçant avisé',
                'category' => 'Boutique',
                'condition' => 'Acheter 5 objets dans la boutique',
                'reward_coins' => 70,
                'rarity' => 'Rare',
                'badge_emoji' => '🛒',
                'badge_description' => 'Caddie rempli',
                'detection_code' => 'shop_purchases',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Combattant de boss',
                'category' => 'Boss',
                'condition' => 'Vaincre un boss',
                'reward_coins' => 100,
                'rarity' => 'Rare',
                'badge_emoji' => '⚔️',
                'badge_description' => 'Épées croisées',
                'detection_code' => 'boss_defeat',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Nocturne',
                'category' => 'Temporel',
                'condition' => 'Jouer 5 matchs entre minuit et 6h du matin',
                'reward_coins' => 75,
                'rarity' => 'Rare',
                'badge_emoji' => '🌙',
                'badge_description' => 'Lune croissant',
                'detection_code' => 'night_matches',
                'detection_params' => json_encode(['count' => 5, 'start_hour' => 0, 'end_hour' => 6]),
                'auto_complete' => false
            ],
            [
                'name' => 'Spécialiste du thème',
                'category' => 'Thème',
                'condition' => 'Jouer 50 matchs dans un seul thème',
                'reward_coins' => 85,
                'rarity' => 'Rare',
                'badge_emoji' => '🎓',
                'badge_description' => 'Chapeau diplômé',
                'detection_code' => 'theme_dedication',
                'detection_params' => json_encode(['matches' => 50, 'same_theme' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Comeback king',
                'category' => 'Résilience',
                'condition' => 'Gagner un match après avoir perdu les 2 premières manches',
                'reward_coins' => 95,
                'rarity' => 'Rare',
                'badge_emoji' => '👑',
                'badge_description' => 'Couronne royale',
                'detection_code' => 'comeback_victory',
                'detection_params' => json_encode(['rounds_lost_first' => 2]),
                'auto_complete' => false
            ],
            [
                'name' => 'Ambassadeur',
                'category' => 'Sociale',
                'condition' => 'Inviter 5 nouveaux joueurs',
                'reward_coins' => 100,
                'rarity' => 'Rare',
                'badge_emoji' => '📢',
                'badge_description' => 'Mégaphone',
                'detection_code' => 'referrals',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => false
            ]
        ];

        DB::table('quests')->insert($rareQuests);
    }
}
