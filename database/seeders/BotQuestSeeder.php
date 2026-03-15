<?php

namespace Database\Seeders;

use App\Models\Quest;
use Illuminate\Database\Seeder;

class BotQuestSeeder extends Seeder
{
    public function run(): void
    {
        Quest::updateOrCreate(
            ['detection_code' => 'bot_first_selection'],
            [
                'name' => 'Première sélection du bot',
                'category' => '🤖 Bot',
                'condition' => 'Votre bot a été sélectionné pour la première fois dans un match',
                'reward_coins' => 25,
                'coin_type' => 'intelligence',
                'rarity' => 'Standard',
                'badge_emoji' => '🤖',
                'badge_description' => 'Robot sélectionné',
                'detection_params' => null,
                'auto_complete' => true,
            ]
        );

        Quest::updateOrCreate(
            ['detection_code' => 'bot_first_win'],
            [
                'name' => 'Première victoire du bot',
                'category' => '🤖 Bot',
                'condition' => 'Votre bot a remporté son premier match',
                'reward_coins' => 50,
                'coin_type' => 'intelligence',
                'rarity' => 'Rare',
                'badge_emoji' => '🏆',
                'badge_description' => 'Trophée de victoire bot',
                'detection_params' => null,
                'auto_complete' => true,
            ]
        );
    }
}
