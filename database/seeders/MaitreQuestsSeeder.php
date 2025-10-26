<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaitreQuestsSeeder extends Seeder
{
    public function run(): void
    {
        $maitreQuests = [
            [
                'name' => 'Dieu vivant de StrategyBuzzer',
                'category' => 'Ultime',
                'condition' => 'Compléter toutes les quêtes du jeu',
                'reward_coins' => 10000,
                'rarity' => 'Maître',
                'badge_emoji' => '🌌',
                'badge_description' => 'Galaxie univers',
                'detection_code' => 'all_quests_completed',
                'detection_params' => json_encode(['all_quests' => true]),
                'auto_complete' => false
            ],
            [
                'name' => 'Maître de l\'univers StrategyBuzzer',
                'category' => 'Ultime',
                'condition' => 'Atteindre le rang #1 mondial',
                'reward_coins' => 15000,
                'rarity' => 'Maître',
                'badge_emoji' => '🏅',
                'badge_description' => 'Médaille suprême',
                'detection_code' => 'world_rank_1',
                'detection_params' => json_encode(['rank' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Éternel champion',
                'category' => 'Ultime',
                'condition' => 'Maintenir 100% de taux de victoire sur 1000 matchs',
                'reward_coins' => 12000,
                'rarity' => 'Maître',
                'badge_emoji' => '💠',
                'badge_description' => 'Diamant parfait',
                'detection_code' => 'perfect_win_rate',
                'detection_params' => json_encode(['matches' => 1000, 'win_rate' => 100]),
                'auto_complete' => false
            ],
            [
                'name' => 'Architecte de légendes',
                'category' => 'Ultime',
                'condition' => 'Organiser 1000 quiz en mode Maître du jeu',
                'reward_coins' => 11000,
                'rarity' => 'Maître',
                'badge_emoji' => '🎯',
                'badge_description' => 'Cible absolue',
                'detection_code' => 'master_games_hosted_maitre',
                'detection_params' => json_encode(['count' => 1000]),
                'auto_complete' => false
            ],
            [
                'name' => 'Créateur d\'empire',
                'category' => 'Ultime',
                'condition' => 'Inviter 500 nouveaux joueurs',
                'reward_coins' => 20000,
                'rarity' => 'Maître',
                'badge_emoji' => '🌟',
                'badge_description' => 'Étoile suprême',
                'detection_code' => 'referrals_maitre',
                'detection_params' => json_encode(['count' => 500]),
                'auto_complete' => false
            ]
        ];

        DB::table('quests')->insert($maitreQuests);
    }
}
