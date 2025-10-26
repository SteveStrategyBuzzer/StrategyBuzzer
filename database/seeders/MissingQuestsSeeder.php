<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MissingQuestsSeeder extends Seeder
{
    public function run(): void
    {
        $missingQuests = [
            [
                'name' => 'Virtuose des thèmes',
                'category' => 'Thème',
                'condition' => 'Gagner 100 matchs répartis sur 10 thèmes différents',
                'reward_coins' => 95,
                'rarity' => 'Rare',
                'badge_emoji' => '🎨',
                'badge_description' => 'Palette multicolore',
                'detection_code' => 'multi_theme_wins_rare',
                'detection_params' => json_encode(['wins' => 100, 'themes' => 10]),
                'auto_complete' => false
            ],
            [
                'name' => 'Seigneur du buzzer',
                'category' => 'Buzz',
                'condition' => 'Être le premier à buzzer 1000 fois',
                'reward_coins' => 420,
                'rarity' => 'Légendaire',
                'badge_emoji' => '👑',
                'badge_description' => 'Couronne buzzer',
                'detection_code' => 'first_buzz_total_legendaire',
                'detection_params' => json_encode(['count' => 1000]),
                'auto_complete' => false
            ],
            [
                'name' => 'Titan indomptable',
                'category' => 'Série',
                'condition' => 'Gagner 200 matchs d\'affilée',
                'reward_coins' => 700,
                'rarity' => 'Légendaire',
                'badge_emoji' => '⚡',
                'badge_description' => 'Foudre titan',
                'detection_code' => 'win_streak_titan',
                'detection_params' => json_encode(['count' => 200]),
                'auto_complete' => false
            ]
        ];

        DB::table('quests')->insert($missingQuests);
    }
}
