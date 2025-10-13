<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Quest;

class QuestsSeeder extends Seeder
{
    public function run(): void
    {
        $quests = [
            
            [
                'key' => 'complete_profile',
                'name' => 'Premiers Pas',
                'description' => 'Remplis ton profil à 100% et enregistre',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'profile',
                'target_value' => 1,
                'order' => 1
            ],
            [
                'key' => 'choose_avatar',
                'name' => 'Choisis ton Identité',
                'description' => 'Sélectionne un avatar',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'profile',
                'target_value' => 1,
                'order' => 2
            ],
            
            [
                'key' => 'first_quiz',
                'name' => 'Premier Quiz',
                'description' => 'Participe à ton premier quiz',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 10
            ],
            [
                'key' => 'first_victory',
                'name' => 'Première Victoire',
                'description' => 'Gagne ton premier match',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 11
            ],
            [
                'key' => 'answer_10_questions',
                'name' => 'Apprenti Quizzer',
                'description' => 'Réponds à 10 questions',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 12
            ],
            [
                'key' => 'answer_20_questions',
                'name' => 'Quizzer Débutant',
                'description' => 'Réponds à 20 questions',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 20,
                'order' => 13
            ],
            [
                'key' => 'answer_30_questions',
                'name' => 'Quizzer Confirmé',
                'description' => 'Réponds à 30 questions',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 30,
                'order' => 14
            ],
            [
                'key' => 'answer_40_questions',
                'name' => 'Quizzer Expérimenté',
                'description' => 'Réponds à 40 questions',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 40,
                'order' => 15
            ],
            [
                'key' => 'answer_50_questions',
                'name' => 'Expert Questions',
                'description' => 'Réponds à 50 questions',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 50,
                'order' => 16
            ],
            [
                'key' => 'win_20_question_game',
                'name' => 'Vainqueur de 20 Questions',
                'description' => 'Gagne une partie de 20 questions',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 17
            ],
            [
                'key' => 'play_5_matches',
                'name' => 'Joueur Régulier',
                'description' => 'Joue 5 matchs',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 5,
                'order' => 18
            ],
            [
                'key' => 'play_10_matches',
                'name' => 'Joueur Assidu',
                'description' => 'Joue 10 matchs',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 19
            ],
            [
                'key' => 'win_3_matches',
                'name' => 'Triplé',
                'description' => 'Gagne 3 matchs',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 3,
                'order' => 20
            ],
            [
                'key' => 'win_5_matches',
                'name' => 'Quintuplé',
                'description' => 'Gagne 5 matchs',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 5,
                'order' => 21
            ],
            [
                'key' => 'win_10_matches',
                'name' => 'Vainqueur x10',
                'description' => 'Gagne 10 matchs',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 22
            ],
            [
                'key' => 'reach_level_3',
                'name' => 'Niveau 3',
                'description' => 'Atteins le niveau 3 en Solo',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 3,
                'order' => 23
            ],
            [
                'key' => 'reach_level_5',
                'name' => 'Niveau 5',
                'description' => 'Atteins le niveau 5 en Solo',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 5,
                'order' => 24
            ],
            [
                'key' => 'win_streak_3',
                'name' => 'Série de 3',
                'description' => 'Gagne 3 matchs d\'affilée',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 3,
                'order' => 25
            ],
            [
                'key' => 'correct_10_answers',
                'name' => '10 Bonnes Réponses',
                'description' => 'Donne 10 réponses correctes',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 26
            ],
            [
                'key' => 'correct_20_answers',
                'name' => '20 Bonnes Réponses',
                'description' => 'Donne 20 réponses correctes',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 20,
                'order' => 27
            ],
            [
                'key' => 'correct_30_answers',
                'name' => '30 Bonnes Réponses',
                'description' => 'Donne 30 réponses correctes',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 30,
                'order' => 28
            ],
            [
                'key' => 'first_buzz',
                'name' => 'Premier Buzz',
                'description' => 'Buzze pour la première fois',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 29
            ],
            [
                'key' => 'buzz_10_times',
                'name' => 'Buzzeur Actif',
                'description' => 'Buzze 10 fois',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 30
            ],
            [
                'key' => 'play_20_matches',
                'name' => 'Déblocage Duo',
                'description' => 'Joue 20 matchs Solo pour débloquer Duo',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 20,
                'order' => 31,
                'unlocks' => ['duo_mode']
            ],
            [
                'key' => 'answer_100_questions',
                'name' => 'Centenaire',
                'description' => 'Réponds à 100 questions',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 100,
                'order' => 32
            ],
            [
                'key' => 'correct_50_answers',
                'name' => '50 Bonnes Réponses',
                'description' => 'Donne 50 réponses correctes',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 50,
                'order' => 33
            ],
            [
                'key' => 'use_strategic_avatar',
                'name' => 'Tactique',
                'description' => 'Utilise un avatar stratégique',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 34
            ],
            [
                'key' => 'win_with_strategic_avatar',
                'name' => 'Stratège Victorieux',
                'description' => 'Gagne avec un avatar stratégique',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 35
            ],
            [
                'key' => 'play_all_themes',
                'name' => 'Polyvalent',
                'description' => 'Joue avec tous les thèmes disponibles',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 7,
                'order' => 36
            ],
            [
                'key' => 'perfect_round',
                'name' => 'Perfection',
                'description' => 'Gagne un round sans erreur',
                'tier' => 'bronze',
                'reward_pieces' => 10,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 37
            ],
            
            [
                'key' => 'reach_level_10',
                'name' => 'Boss Battle 1',
                'description' => 'Atteins le niveau 10 (Premier Boss)',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 40
            ],
            [
                'key' => 'beat_boss_level_10',
                'name' => 'Vainqueur du Boss 1',
                'description' => 'Bats le Boss du niveau 10',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 41
            ],
            [
                'key' => 'win_20_matches',
                'name' => 'Vainqueur x20',
                'description' => 'Gagne 20 matchs',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 20,
                'order' => 42
            ],
            [
                'key' => 'win_30_matches',
                'name' => 'Vainqueur x30',
                'description' => 'Gagne 30 matchs',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 30,
                'order' => 43
            ],
            [
                'key' => 'win_streak_5',
                'name' => 'Série de 5',
                'description' => 'Gagne 5 matchs d\'affilée',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 5,
                'order' => 44
            ],
            [
                'key' => 'win_streak_10',
                'name' => 'Série de 10',
                'description' => 'Gagne 10 matchs d\'affilée',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 45
            ],
            [
                'key' => 'answer_200_questions',
                'name' => '200 Questions',
                'description' => 'Réponds à 200 questions',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 200,
                'order' => 46
            ],
            [
                'key' => 'answer_500_questions',
                'name' => '500 Questions',
                'description' => 'Réponds à 500 questions',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 500,
                'order' => 47
            ],
            [
                'key' => 'correct_100_answers',
                'name' => '100 Bonnes Réponses',
                'description' => 'Donne 100 réponses correctes',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 100,
                'order' => 48
            ],
            [
                'key' => 'correct_200_answers',
                'name' => '200 Bonnes Réponses',
                'description' => 'Donne 200 réponses correctes',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 200,
                'order' => 49
            ],
            [
                'key' => 'correct_500_answers',
                'name' => '500 Bonnes Réponses',
                'description' => 'Donne 500 réponses correctes',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 500,
                'order' => 50
            ],
            [
                'key' => 'play_50_matches',
                'name' => '50 Matchs Joués',
                'description' => 'Joue 50 matchs',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 50,
                'order' => 51
            ],
            [
                'key' => 'win_50_matches',
                'name' => 'Vainqueur x50',
                'description' => 'Gagne 50 matchs',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 50,
                'order' => 52
            ],
            [
                'key' => 'reach_level_20',
                'name' => 'Boss Battle 2',
                'description' => 'Atteins le niveau 20 (Deuxième Boss)',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 20,
                'order' => 53
            ],
            [
                'key' => 'beat_boss_level_20',
                'name' => 'Vainqueur du Boss 2',
                'description' => 'Bats le Boss du niveau 20',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 54
            ],
            [
                'key' => 'reach_level_30',
                'name' => 'Boss Battle 3',
                'description' => 'Atteins le niveau 30 (Troisième Boss)',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 30,
                'order' => 55
            ],
            [
                'key' => 'beat_boss_level_30',
                'name' => 'Vainqueur du Boss 3',
                'description' => 'Bats le Boss du niveau 30',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 56
            ],
            [
                'key' => 'buzz_50_times',
                'name' => '50 Buzzs',
                'description' => 'Buzze 50 fois',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 50,
                'order' => 57
            ],
            [
                'key' => 'perfect_5_rounds',
                'name' => '5 Rounds Parfaits',
                'description' => 'Réalise 5 rounds parfaits',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 5,
                'order' => 58
            ],
            [
                'key' => 'accuracy_80_percent',
                'name' => 'Précision 80%',
                'description' => 'Atteins 80% de précision sur 50 questions',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 59
            ],
            [
                'key' => 'first_duo_match',
                'name' => 'Premier Duo',
                'description' => 'Joue ton premier match Duo',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 1,
                'order' => 60
            ],
            [
                'key' => 'win_first_duo',
                'name' => 'Victoire Duo',
                'description' => 'Gagne ton premier match Duo',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 1,
                'order' => 61
            ],
            [
                'key' => 'play_10_duo',
                'name' => '10 Duos',
                'description' => 'Joue 10 matchs Duo',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 10,
                'order' => 62
            ],
            [
                'key' => 'win_5_duo',
                'name' => '5 Victoires Duo',
                'description' => 'Gagne 5 matchs Duo',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 5,
                'order' => 63
            ],
            [
                'key' => 'win_10_duo',
                'name' => '10 Victoires Duo',
                'description' => 'Gagne 10 matchs Duo',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 10,
                'order' => 64
            ],
            [
                'key' => 'reach_division_silver',
                'name' => 'Division Argent',
                'description' => 'Atteins la division Argent en Duo',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 1,
                'order' => 65
            ],
            [
                'key' => 'reach_division_gold',
                'name' => 'Division Or',
                'description' => 'Atteins la division Or en Duo',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 1,
                'order' => 66
            ],
            [
                'key' => 'win_streak_duo_3',
                'name' => 'Série Duo x3',
                'description' => 'Gagne 3 matchs Duo d\'affilée',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 3,
                'order' => 67
            ],
            [
                'key' => 'win_streak_duo_5',
                'name' => 'Série Duo x5',
                'description' => 'Gagne 5 matchs Duo d\'affilée',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'duo',
                'target_value' => 5,
                'order' => 68
            ],
            [
                'key' => 'first_league_match',
                'name' => 'Premier Match Ligue',
                'description' => 'Joue ton premier match Ligue',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'league',
                'target_value' => 1,
                'order' => 69
            ],
            [
                'key' => 'join_team',
                'name' => 'Membre d\'Équipe',
                'description' => 'Rejoins ou crée une équipe',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'league',
                'target_value' => 1,
                'order' => 70
            ],
            [
                'key' => 'win_5_league',
                'name' => '5 Victoires Ligue',
                'description' => 'Gagne 5 matchs Ligue',
                'tier' => 'silver',
                'reward_pieces' => 25,
                'category' => 'league',
                'target_value' => 5,
                'order' => 71
            ],
            
            [
                'key' => 'reach_level_50',
                'name' => 'Niveau 50',
                'description' => 'Atteins le niveau 50 en Solo',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'solo',
                'target_value' => 50,
                'order' => 80
            ],
            [
                'key' => 'beat_boss_level_50',
                'name' => 'Vainqueur du Boss 5',
                'description' => 'Bats le Boss du niveau 50',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 81
            ],
            [
                'key' => 'reach_level_100',
                'name' => 'Niveau Maximum',
                'description' => 'Atteins le niveau 100 en Solo',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 100,
                'order' => 82
            ],
            [
                'key' => 'beat_final_boss',
                'name' => 'Vainqueur du Boss Final',
                'description' => 'Bats le Boss final du niveau 100',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 83
            ],
            [
                'key' => 'win_100_matches',
                'name' => 'Champion Centenaire',
                'description' => 'Gagne 100 matchs Solo',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'solo',
                'target_value' => 100,
                'order' => 84
            ],
            [
                'key' => 'win_200_matches',
                'name' => 'Champion Bicentenaire',
                'description' => 'Gagne 200 matchs Solo',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 200,
                'order' => 85
            ],
            [
                'key' => 'win_streak_20',
                'name' => 'Série de 20',
                'description' => 'Gagne 20 matchs d\'affilée',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 20,
                'order' => 86
            ],
            [
                'key' => 'answer_1000_questions',
                'name' => 'Millier de Questions',
                'description' => 'Réponds à 1000 questions',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'solo',
                'target_value' => 1000,
                'order' => 87
            ],
            [
                'key' => 'answer_2000_questions',
                'name' => '2000 Questions',
                'description' => 'Réponds à 2000 questions',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 2000,
                'order' => 88
            ],
            [
                'key' => 'correct_1000_answers',
                'name' => '1000 Bonnes Réponses',
                'description' => 'Donne 1000 réponses correctes',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'solo',
                'target_value' => 1000,
                'order' => 89
            ],
            [
                'key' => 'correct_2000_answers',
                'name' => '2000 Bonnes Réponses',
                'description' => 'Donne 2000 réponses correctes',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 2000,
                'order' => 90
            ],
            [
                'key' => 'accuracy_90_percent',
                'name' => 'Précision 90%',
                'description' => 'Atteins 90% de précision sur 100 questions',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 91
            ],
            [
                'key' => 'accuracy_95_percent',
                'name' => 'Précision 95%',
                'description' => 'Atteins 95% de précision sur 100 questions',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 1,
                'order' => 92
            ],
            [
                'key' => 'perfect_10_rounds',
                'name' => '10 Rounds Parfaits',
                'description' => 'Réalise 10 rounds parfaits',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 93
            ],
            [
                'key' => 'win_50_duo',
                'name' => '50 Victoires Duo',
                'description' => 'Gagne 50 matchs Duo',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'duo',
                'target_value' => 50,
                'order' => 94
            ],
            [
                'key' => 'win_100_duo',
                'name' => '100 Victoires Duo',
                'description' => 'Gagne 100 matchs Duo',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'duo',
                'target_value' => 100,
                'order' => 95
            ],
            [
                'key' => 'reach_division_platinum',
                'name' => 'Division Platine',
                'description' => 'Atteins la division Platine',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'duo',
                'target_value' => 1,
                'order' => 96
            ],
            [
                'key' => 'reach_division_diamond',
                'name' => 'Division Diamant',
                'description' => 'Atteins la division Diamant',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'duo',
                'target_value' => 1,
                'order' => 97
            ],
            [
                'key' => 'reach_division_legend',
                'name' => 'Division Légende',
                'description' => 'Atteins la division Légende',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'duo',
                'target_value' => 1,
                'order' => 98
            ],
            [
                'key' => 'win_streak_duo_10',
                'name' => 'Série Duo x10',
                'description' => 'Gagne 10 matchs Duo d\'affilée',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'duo',
                'target_value' => 10,
                'order' => 99
            ],
            [
                'key' => 'win_50_league',
                'name' => '50 Victoires Ligue',
                'description' => 'Gagne 50 matchs Ligue',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'league',
                'target_value' => 50,
                'order' => 100
            ],
            [
                'key' => 'win_100_league',
                'name' => '100 Victoires Ligue',
                'description' => 'Gagne 100 matchs Ligue',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'league',
                'target_value' => 100,
                'order' => 101
            ],
            [
                'key' => 'team_champion',
                'name' => 'Champion d\'Équipe',
                'description' => 'Gagne 20 matchs en équipe',
                'tier' => 'gold',
                'reward_pieces' => 75,
                'category' => 'league',
                'target_value' => 20,
                'order' => 102
            ],
            [
                'key' => 'all_bosses_defeated',
                'name' => 'Tueur de Boss',
                'description' => 'Bats tous les Boss (10, 20, 30... 100)',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'solo',
                'target_value' => 10,
                'order' => 103
            ],
            [
                'key' => 'ultimate_champion',
                'name' => 'Champion Ultime',
                'description' => 'Atteins niveau 100 Solo ET division Légende',
                'tier' => 'gold',
                'reward_pieces' => 100,
                'category' => 'general',
                'target_value' => 1,
                'order' => 104
            ],
        ];

        foreach ($quests as $quest) {
            Quest::create($quest);
        }
    }
}
