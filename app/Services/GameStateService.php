<?php

namespace App\Services;

/**
 * Service centralisé pour la gestion de l'état de partie
 * Compatible avec tous les modes : Solo, Duo, Ligue, Maître du jeu
 */
class GameStateService
{
    /**
     * Initialise une nouvelle partie avec les paramètres donnés
     */
    public function initializeGame(array $params): array
    {
        $players = $params['players'] ?? [];
        
        // Initialiser les scores par joueur
        $playerScores = [];
        $playerStats = [];
        $playerRoundsWon = [];
        
        foreach ($players as $player) {
            $playerId = $player['id'];
            $playerScores[$playerId] = 0;
            $playerRoundsWon[$playerId] = 0;
            $playerStats[$playerId] = [
                'correct' => 0,
                'incorrect' => 0,
                'unanswered' => 0,
                'total_points' => 0,
            ];
        }
        
        $gameState = [
            // Configuration de base
            'mode' => $params['mode'] ?? 'solo',
            'theme' => $params['theme'],
            'nb_questions' => $params['nb_questions'],
            'niveau' => $params['niveau'],
            
            // Système "Best of 3"
            'current_round' => 1,
            'total_rounds' => 3,
            
            // Joueurs et leurs données (système généralisé)
            'players' => $players,
            'player_scores_map' => $playerScores,      // Map : player_id => score actuel
            'player_rounds_won_map' => $playerRoundsWon, // Map : player_id => manches gagnées
            'player_stats_map' => $playerStats,        // Map : player_id => stats
            
            // Compatibilité rétroactive pour mode Solo (scalaires legacy)
            'score' => 0,
            'opponent_score' => 0,
            'player_rounds_won' => 0,
            'opponent_rounds_won' => 0,
            'player_total_score' => 0,     // Score total cumulé pour tiebreaker
            'opponent_total_score' => 0,   // Score total cumulé pour tiebreaker
            
            // État de la manche actuelle
            'current_question_number' => 1,
            
            // Questions et historique
            'answered_questions' => [],
            'used_question_ids' => [],
            'current_question' => null,
            
            // Statistiques globales (toutes manches confondues)
            'global_stats' => [
                'correct' => 0,
                'incorrect' => 0,
                'unanswered' => 0,
                'total_points' => 0,
            ],
            
            // Flags et tracking
            'match_result_processed' => false,
            'used_skills' => [],
            'game_started_at' => now()->toDateTimeString(),
        ];
        
        return $gameState;
    }
    
    /**
     * Met à jour le score après une réponse
     */
    public function updateScore(array &$gameState, string $playerId, int $points, bool $isCorrect): void
    {
        // Mise à jour du score par joueur (système généralisé avec clés distinctes)
        if (isset($gameState['player_scores_map'][$playerId])) {
            $gameState['player_scores_map'][$playerId] += $points;
            
            // Stats par joueur (inclure les pénalités dans total_points)
            if ($isCorrect) {
                $gameState['player_stats_map'][$playerId]['correct']++;
            } else {
                $gameState['player_stats_map'][$playerId]['incorrect']++;
            }
            // Inclure les points négatifs pour refléter les pénalités
            $gameState['player_stats_map'][$playerId]['total_points'] += $points;
        }
        
        // Compatibilité rétroactive pour mode Solo (scalaires legacy)
        if ($playerId === 'player') {
            $gameState['score'] += $points;
            // Tracking du score total pour tiebreaker
            if (!isset($gameState['player_total_score'])) {
                $gameState['player_total_score'] = 0;
            }
            $gameState['player_total_score'] += $points;
        } elseif ($playerId === 'opponent') {
            $gameState['opponent_score'] += $points;
            // Tracking du score total pour tiebreaker
            if (!isset($gameState['opponent_total_score'])) {
                $gameState['opponent_total_score'] = 0;
            }
            $gameState['opponent_total_score'] += $points;
        }
        
        // Mettre à jour les stats globales (pour le joueur principal uniquement)
        if ($playerId === 'player') {
            if ($isCorrect) {
                $gameState['global_stats']['correct']++;
            } else {
                $gameState['global_stats']['incorrect']++;
            }
            $gameState['global_stats']['total_points'] += $points;
        }
    }
    
    /**
     * Enregistre une question répondue
     */
    public function recordAnswer(array &$gameState, array $answerData): void
    {
        $gameState['answered_questions'][] = [
            'question_id' => $answerData['question_id'] ?? null,
            'question_text' => $answerData['question_text'] ?? '',
            'player_answer' => $answerData['player_answer'] ?? null,
            'correct_answer' => $answerData['correct_answer'] ?? null,
            'is_correct' => $answerData['is_correct'] ?? false,
            'points_earned' => $answerData['points_earned'] ?? 0,
            'buzz_time' => $answerData['buzz_time'] ?? null,
            'round' => $gameState['current_round'],
        ];
    }
    
    /**
     * Passe à la question suivante
     */
    public function nextQuestion(array &$gameState): bool
    {
        $gameState['current_question_number']++;
        $gameState['current_question'] = null;
        
        return $gameState['current_question_number'] <= $gameState['nb_questions'];
    }
    
    /**
     * Termine la manche actuelle et détermine le gagnant
     */
    public function finishRound(array &$gameState): array
    {
        $mode = $gameState['mode'] ?? 'solo';
        
        // Mode Solo/Duo : logique player vs opponent
        if ($mode === 'solo' || $mode === 'duo') {
            $playerScore = $gameState['score'];
            $opponentScore = $gameState['opponent_score'];
            
            $result = [
                'round' => $gameState['current_round'],
                'player_score' => $playerScore,
                'opponent_score' => $opponentScore,
                'winner' => null,
                'is_draw' => false,
            ];
            
            if ($playerScore > $opponentScore) {
                $gameState['player_rounds_won']++;
                $result['winner'] = 'player';
            } elseif ($opponentScore > $playerScore) {
                $gameState['opponent_rounds_won']++;
                $result['winner'] = 'opponent';
            } else {
                // Égalité : on ne compte pas la manche, on continue
                $result['is_draw'] = true;
                $result['winner'] = 'draw';
            }
            
            return $result;
        }
        
        // Mode multi-joueurs (Ligue/MdJ) : trouver le score le plus élevé
        $scores = $gameState['player_scores_map'] ?? [];
        if (empty($scores)) {
            return ['round' => $gameState['current_round'], 'winner' => null, 'is_draw' => true];
        }
        
        $maxScore = max($scores);
        $winners = array_keys(array_filter($scores, fn($s) => $s === $maxScore));
        
        $result = [
            'round' => $gameState['current_round'],
            'scores' => $scores,
            'winner' => null,
            'is_draw' => count($winners) > 1,
        ];
        
        if (count($winners) === 1) {
            $winnerId = $winners[0];
            $gameState['player_rounds_won_map'][$winnerId]++;
            $result['winner'] = $winnerId;
        }
        
        return $result;
    }
    
    /**
     * Passe à la manche suivante
     */
    public function nextRound(array &$gameState): void
    {
        $gameState['current_round']++;
        $gameState['current_question_number'] = 1;
        
        // Réinitialiser les scores legacy
        $gameState['score'] = 0;
        $gameState['opponent_score'] = 0;
        
        // Réinitialiser les scores par joueur (maps)
        if (isset($gameState['player_scores_map'])) {
            foreach ($gameState['player_scores_map'] as $playerId => $score) {
                $gameState['player_scores_map'][$playerId] = 0;
            }
        }
        
        $gameState['answered_questions'] = [];
        $gameState['current_question'] = null;
    }
    
    /**
     * Vérifie si le match est terminé (2 manches gagnées ou max rounds atteint)
     */
    public function isMatchFinished(array $gameState): bool
    {
        $mode = $gameState['mode'] ?? 'solo';
        
        // Mode Solo/Duo : vérifier si player ou opponent a gagné 2 manches
        if ($mode === 'solo' || $mode === 'duo') {
            $playerWon = $gameState['player_rounds_won'] >= 2;
            $opponentWon = $gameState['opponent_rounds_won'] >= 2;
            
            // Compter les rounds décisifs (non-draws) au lieu de current_round
            // car les draws se rejouent sans consommer un round
            $decisiveRoundsPlayed = $gameState['player_rounds_won'] + $gameState['opponent_rounds_won'];
            $maxDecisiveRoundsReached = $decisiveRoundsPlayed >= $gameState['total_rounds'];
            
            // Match terminé si quelqu'un a 2 victoires OU si on a joué 3 rounds décisifs
            // (dans ce cas, on départage par le score total)
            return $playerWon || $opponentWon || $maxDecisiveRoundsReached;
        }
        
        // Mode multi-joueurs : vérifier si un joueur a atteint 2 manches gagnées
        $roundsWon = $gameState['player_rounds_won_map'] ?? [];
        foreach ($roundsWon as $wins) {
            if ($wins >= 2) {
                return true;
            }
        }
        
        // Si on a atteint le max de rounds, match terminé
        return $gameState['current_round'] >= $gameState['total_rounds'];
    }
    
    /**
     * Détermine le résultat final du match avec tiebreaker par score total
     */
    public function getMatchResult(array $gameState): array
    {
        $mode = $gameState['mode'] ?? 'solo';
        
        // Mode Solo/Duo
        if ($mode === 'solo' || $mode === 'duo') {
            $playerRoundsWon = $gameState['player_rounds_won'];
            $opponentRoundsWon = $gameState['opponent_rounds_won'];
            
            $result = [
                'player_rounds_won' => $playerRoundsWon,
                'opponent_rounds_won' => $opponentRoundsWon,
                'global_stats' => $gameState['global_stats'],
                'player_won' => false,
                'decided_by' => 'rounds',
            ];
            
            // Si quelqu'un a 2 manches, victoire claire
            if ($playerRoundsWon >= 2) {
                $result['player_won'] = true;
                return $result;
            }
            
            if ($opponentRoundsWon >= 2) {
                $result['player_won'] = false;
                return $result;
            }
            
            // Sinon tiebreaker par score total (3 rounds sans 2 victoires claires)
            $playerTotalScore = $gameState['player_total_score'] ?? 0;
            $opponentTotalScore = $gameState['opponent_total_score'] ?? 0;
            
            $result['decided_by'] = 'total_score';
            $result['player_total_score'] = $playerTotalScore;
            $result['opponent_total_score'] = $opponentTotalScore;
            $result['player_won'] = $playerTotalScore > $opponentTotalScore;
            
            return $result;
        }
        
        // Mode multi-joueurs
        $roundsWon = $gameState['player_rounds_won_map'] ?? [];
        $totalScores = $gameState['player_stats_map'] ?? [];
        
        // Trouver le gagnant par manches
        $maxRounds = max(array_values($roundsWon));
        $winnersbyRounds = array_keys(array_filter($roundsWon, fn($w) => $w === $maxRounds));
        
        $result = [
            'rounds_won' => $roundsWon,
            'total_scores' => array_map(fn($s) => $s['total_points'] ?? 0, $totalScores),
            'winner' => null,
            'decided_by' => 'rounds',
        ];
        
        // Victoire claire par manches
        if (count($winnersbyRounds) === 1 && $maxRounds >= 2) {
            $result['winner'] = $winnersbyRounds[0];
            return $result;
        }
        
        // Tiebreaker par score total (incluant les pénalités)
        $scores = array_map(fn($s) => $s['total_points'] ?? 0, $totalScores);
        if (empty($scores)) {
            return $result;
        }
        $maxScore = max($scores);
        $winnersByScore = array_keys(array_filter($scores, fn($s) => $s === $maxScore));
        
        $result['decided_by'] = 'total_score';
        if (count($winnersByScore) === 1) {
            $result['winner'] = $winnersByScore[0];
        }
        
        return $result;
    }
    
    /**
     * Réinitialise pour une nouvelle manche tout en gardant les manches gagnées
     */
    public function resetForNewRound(array &$gameState): void
    {
        $gameState['current_question_number'] = 1;
        
        // Réinitialiser les scores legacy
        $gameState['score'] = 0;
        $gameState['opponent_score'] = 0;
        
        // Réinitialiser les scores par joueur (maps)
        if (isset($gameState['player_scores_map'])) {
            foreach ($gameState['player_scores_map'] as $playerId => $score) {
                $gameState['player_scores_map'][$playerId] = 0;
            }
        }
        
        $gameState['answered_questions'] = [];
        $gameState['current_question'] = null;
    }
    
    /**
     * Sauvegarde l'état dans la session (pour Laravel)
     */
    public function saveToSession(array $gameState): void
    {
        session($gameState);
    }
    
    /**
     * Charge l'état depuis la session (pour Laravel)
     */
    public function loadFromSession(): array
    {
        return [
            'mode' => session('mode', 'solo'),
            'theme' => session('theme'),
            'nb_questions' => session('nb_questions'),
            'niveau' => session('niveau_selectionne'),
            'current_round' => session('current_round', 1),
            'total_rounds' => session('total_rounds', 3),
            'player_rounds_won' => session('player_rounds_won', 0),
            'opponent_rounds_won' => session('opponent_rounds_won', 0),
            'current_question_number' => session('current_question_number', 1),
            'score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
            'answered_questions' => session('answered_questions', []),
            'used_question_ids' => session('used_question_ids', []),
            'current_question' => session('current_question'),
            'global_stats' => session('global_stats', [
                'correct' => 0,
                'incorrect' => 0,
                'unanswered' => 0,
                'total_points' => 0,
            ]),
            'players' => session('players', []),
            'match_result_processed' => session('match_result_processed', false),
            'used_skills' => session('used_skills', []),
            'game_started_at' => session('game_started_at'),
        ];
    }
}
