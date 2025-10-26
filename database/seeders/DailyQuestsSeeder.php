<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DailyQuestsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Supprimer les quêtes quotidiennes existantes (legacy + current)
        DB::table('quests')->whereIn('rarity', ['Quotidienne', 'Quotidiennes'])->delete();
        
        // Insérer les 20 quêtes quotidiennes
        $dailyQuests = [
            [
                'name' => 'Réveil du génie',
                'category' => 'Intellectuelle',
                'condition' => 'Gagné une manche sans buzzer 5X',
                'reward_coins' => 50,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '☀️',
                'badge_description' => 'Ampoule matinale',
                'detection_code' => 'daily_wins_no_buzz',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Coup de buzz',
                'category' => 'Jeu',
                'condition' => 'Être le premier à buzzer au moins 3 fois dans la journée',
                'reward_coins' => 55,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🔔',
                'badge_description' => 'Buzzer rapide',
                'detection_code' => 'daily_first_buzz_3',
                'detection_params' => json_encode(['count' => 3]),
                'auto_complete' => false
            ],
            [
                'name' => 'Ami du jour',
                'category' => 'Sociale',
                'condition' => 'Envoyer une invitation à un joueur',
                'reward_coins' => 50,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🤝',
                'badge_description' => 'Icône main tendue',
                'detection_code' => 'daily_invite_player',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Quiz éclair',
                'category' => 'Jeu',
                'condition' => 'Finir un quiz de 10 questions sans erreur',
                'reward_coins' => 70,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '⚡',
                'badge_description' => 'Éclair jaune',
                'detection_code' => 'daily_perfect_10',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Exploration express',
                'category' => 'Exploration',
                'condition' => 'Jouer un quiz d\'un thème différent du jour précédent',
                'reward_coins' => 55,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🌎',
                'badge_description' => 'Globe bleu',
                'detection_code' => 'daily_different_theme',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Avatar du matin',
                'category' => 'Avatars',
                'condition' => 'Changer d\'avatar avant ta première partie',
                'reward_coins' => 50,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '👤',
                'badge_description' => 'Silhouette mobile',
                'detection_code' => 'daily_change_avatar',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Buzz du soir',
                'category' => 'Jeu',
                'condition' => 'Finir une partie entre 19h et 23h',
                'reward_coins' => 50,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🌙',
                'badge_description' => 'Demi-lune',
                'detection_code' => 'daily_evening_play',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Apprenti du jour',
                'category' => 'Maîtrise du Jeu',
                'condition' => 'Créer une question personnalisée avec l\'IA',
                'reward_coins' => 70,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🤖',
                'badge_description' => 'Plume IA',
                'detection_code' => 'daily_create_ai_question',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Partage matinal',
                'category' => 'Sociale',
                'condition' => 'Publier un résultat sur les réseaux',
                'reward_coins' => 50,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '📤',
                'badge_description' => 'Icône sociale',
                'detection_code' => 'daily_share_result',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Collectionneur actif',
                'category' => 'Boutique & Monnaie',
                'condition' => 'Consulter la boutique',
                'reward_coins' => 25,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🛒',
                'badge_description' => 'Panier bleu',
                'detection_code' => 'daily_visit_shop',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Curieux constant',
                'category' => 'Intellectuelle',
                'condition' => 'Lire la description d\'un Avatar Stratégique',
                'reward_coins' => 25,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '📚',
                'badge_description' => 'Livre ouvert',
                'detection_code' => 'daily_read_avatar_desc',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Duel du jour',
                'category' => 'Jeu',
                'condition' => 'Gagner une partie Duo',
                'reward_coins' => 75,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🤜🤛',
                'badge_description' => 'Icône duel',
                'detection_code' => 'daily_win_duo',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Maître en herbe',
                'category' => 'Maîtrise du Jeu',
                'condition' => 'Finissez une partie personnalisée 4+ joueurs',
                'reward_coins' => 75,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🎛️',
                'badge_description' => 'Icône console',
                'detection_code' => 'daily_finish_custom_4plus',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Réactif',
                'category' => 'Intellectuelle',
                'condition' => 'sélectionné une réponse en moins d\'1,5 sec',
                'reward_coins' => 50,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '⏱️',
                'badge_description' => 'Chrono vert',
                'detection_code' => 'daily_answer_fast_1_5sec',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Fidèle du jour',
                'category' => 'Sociale',
                'condition' => 'Une partie en Solo dans Ligue',
                'reward_coins' => 75,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '📅',
                'badge_description' => 'Soleil',
                'detection_code' => 'daily_league_solo',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Petit investisseur',
                'category' => 'Boutique & Monnaie',
                'condition' => 'Acheter un objet dans la boutique',
                'reward_coins' => 200,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '💰',
                'badge_description' => 'Pièce dorée',
                'detection_code' => 'daily_buy_item',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Découvreur du jour',
                'category' => 'Exploration',
                'condition' => 'Jouer 5 quiz "Général"',
                'reward_coins' => 75,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🔬',
                'badge_description' => 'Icône microscope',
                'detection_code' => 'daily_play_general_5',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => false
            ],
            [
                'name' => 'Stratégie éclair',
                'category' => 'Avatars',
                'condition' => 'Utiliser un skill d\'avatar stratégique',
                'reward_coins' => 50,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🎯',
                'badge_description' => 'Icône pouvoir',
                'detection_code' => 'daily_use_skill',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Coach social',
                'category' => 'Sociale',
                'condition' => 'Aider un joueur dans le besoin vie ou pièces',
                'reward_coins' => 100,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '🧑‍🏫',
                'badge_description' => 'Étoile bleue',
                'detection_code' => 'daily_help_player',
                'detection_params' => json_encode(['count' => 1]),
                'auto_complete' => false
            ],
            [
                'name' => 'Focus ultime',
                'category' => 'Intellectuelle',
                'condition' => 'Terminer 5 Parties en 2 manche',
                'reward_coins' => 75,
                'rarity' => 'Quotidiennes',
                'badge_emoji' => '👁️',
                'badge_description' => 'Œil concentré',
                'detection_code' => 'daily_finish_5_fast',
                'detection_params' => json_encode(['count' => 5]),
                'auto_complete' => false
            ],
        ];
        
        foreach ($dailyQuests as $quest) {
            DB::table('quests')->insert(array_merge($quest, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
        
        $this->command->info('✅ 20 quêtes quotidiennes insérées avec succès !');
    }
}
