<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegendaireQuestsSeeder extends Seeder
{
    public function run(): void
    {
        $legendaireQuests = [
            [
                'name' => 'Dieu du buzzer',
                'category' => 'Buzz',
                'condition' => 'Être le premier à buzzer 500 fois',
                'reward_coins' => 300,
                'rarity' => 'Légendaire',
                'badge_emoji' => '⚡',
                'badge_description' => 'Éclair divin',
                'detection_code' => 'first_buzz_total',
                'detection_params' => json_encode(['count' => 500]),
                'auto_complete' => false
            ],
            [
                'name' => 'Immortel',
                'category' => 'Série',
                'condition' => 'Gagner 100 matchs d\'affilée',
                'reward_coins' => 350,
                'rarity' => 'Légendaire',
                'badge_emoji' => '♾️',
                'badge_description' => 'Infini éternel',
                'detection_code' => 'win_streak_legendaire',
                'detection_params' => json_encode(['count' => 100]),
                'auto_complete' => false
            ],
            [
                'name' => 'Exterminateur de boss',
                'category' => 'Boss',
                'condition' => 'Vaincre tous les boss du jeu',
                'reward_coins' => 400,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🗡️',
                'badge_description' => 'Épée légendaire',
                'detection_code' => 'all_bosses_defeated',
                'detection_params' => json_encode(['all_bosses' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Perfectionniste absolu',
                'category' => 'Performance',
                'condition' => 'Obtenir 100% sur 500 questions',
                'reward_coins' => 380,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🌟',
                'badge_description' => 'Étoile brillante',
                'detection_code' => 'perfect_accuracy_legendaire',
                'detection_params' => json_encode(['count' => 500, 'accuracy' => 100]),
                'auto_complete' => false
            ],
            [
                'name' => 'Empereur de StrategyBuzzer',
                'category' => 'Progression',
                'condition' => 'Atteindre le niveau 100',
                'reward_coins' => 500,
                'rarity' => 'Légendaire',
                'badge_emoji' => '👑',
                'badge_description' => 'Couronne impériale',
                'detection_code' => 'level_reached_legendaire',
                'detection_params' => json_encode(['level' => 100]),
                'auto_complete' => false
            ],
            [
                'name' => 'Collectionneur ultime',
                'category' => 'Collection',
                'condition' => 'Déverrouiller tous les avatars du jeu',
                'reward_coins' => 450,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🎁',
                'badge_description' => 'Cadeau complet',
                'detection_code' => 'all_avatars_unlocked',
                'detection_params' => json_encode(['all_avatars' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Milliardaire',
                'category' => 'Richesse',
                'condition' => 'Accumuler 10 000 000 coins',
                'reward_coins' => 600,
                'rarity' => 'Légendaire',
                'badge_emoji' => '💎',
                'badge_description' => 'Diamant fortune',
                'detection_code' => 'coins_accumulated_legendaire',
                'detection_params' => json_encode(['amount' => 10000000]),
                'auto_complete' => false
            ],
            [
                'name' => 'Champion du monde',
                'category' => 'Ligue',
                'condition' => 'Atteindre le top 10 mondial en Ligue',
                'reward_coins' => 550,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🥇',
                'badge_description' => 'Médaille d\'or',
                'detection_code' => 'league_ranking',
                'detection_params' => json_encode(['rank' => 10]),
                'auto_complete' => false
            ],
            [
                'name' => 'Vitesse lumière',
                'category' => 'Vitesse',
                'condition' => 'Répondre à 100 questions en moins de 0.5 seconde',
                'reward_coins' => 320,
                'rarity' => 'Légendaire',
                'badge_emoji' => '💫',
                'badge_description' => 'Étoile filante',
                'detection_code' => 'ultra_fast_answers_legendaire',
                'detection_params' => json_encode(['count' => 100, 'max_time' => 0.5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Légende vivante',
                'category' => 'Expérience',
                'condition' => 'Jouer 5000 matchs',
                'reward_coins' => 480,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🏛️',
                'badge_description' => 'Temple antique',
                'detection_code' => 'total_matches_legendaire',
                'detection_params' => json_encode(['count' => 5000]),
                'auto_complete' => false
            ],
            [
                'name' => 'Maître omniscient',
                'category' => 'Connaissance',
                'condition' => 'Atteindre 100% de victoires dans tous les thèmes',
                'reward_coins' => 520,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🔮',
                'badge_description' => 'Boule cristal',
                'detection_code' => 'all_themes_mastery',
                'detection_params' => json_encode(['all_themes' => true, 'win_rate' => 100]),
                'auto_complete' => false
            ],
            [
                'name' => 'Grand maître des quiz',
                'category' => 'Master',
                'condition' => 'Organiser 500 quiz en mode Maître du jeu',
                'reward_coins' => 420,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🎭',
                'badge_description' => 'Masque maître',
                'detection_code' => 'master_games_hosted_legendaire',
                'detection_params' => json_encode(['count' => 500]),
                'auto_complete' => false
            ],
            [
                'name' => 'Influenceur légendaire',
                'category' => 'Sociale',
                'condition' => 'Inviter 100 nouveaux joueurs',
                'reward_coins' => 580,
                'rarity' => 'Légendaire',
                'badge_emoji' => '📡',
                'badge_description' => 'Antenne signal',
                'detection_code' => 'referrals_legendaire',
                'detection_params' => json_encode(['count' => 100]),
                'auto_complete' => false
            ],
            [
                'name' => 'Stratège suprême',
                'category' => 'Stratégie',
                'condition' => 'Utiliser toutes les compétences d\'avatar du jeu',
                'reward_coins' => 460,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🧩',
                'badge_description' => 'Pièce puzzle complète',
                'detection_code' => 'all_skills_used',
                'detection_params' => json_encode(['all_skills' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Phoenix éternel',
                'category' => 'Résilience',
                'condition' => 'Gagner 50 matchs après avoir perdu les 2 premières manches',
                'reward_coins' => 440,
                'rarity' => 'Légendaire',
                'badge_emoji' => '🔥',
                'badge_description' => 'Phoenix flammes',
                'detection_code' => 'comeback_victories_legendaire',
                'detection_params' => json_encode(['count' => 50, 'rounds_lost_first' => 2]),
                'auto_complete' => false
            ],
            [
                'name' => 'Gardien du temps',
                'category' => 'Temporel',
                'condition' => 'Jouer au moins 1 match chaque jour pendant 365 jours',
                'reward_coins' => 650,
                'rarity' => 'Légendaire',
                'badge_emoji' => '⏳',
                'badge_description' => 'Sablier temps',
                'detection_code' => 'daily_streak',
                'detection_params' => json_encode(['days' => 365]),
                'auto_complete' => false
            ]
        ];

        DB::table('quests')->insert($legendaireQuests);
    }
}
