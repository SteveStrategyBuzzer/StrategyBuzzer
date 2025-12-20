<?php

namespace App\Services;

use App\Services\FirebaseService;
use App\Services\DuoFirestoreService;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration Firestore pour le mode League Individual
 * Gère la synchronisation temps réel entre 2 joueurs (matchmaking aléatoire)
 */
class LeagueIndividualFirestoreService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    /**
     * Crée une session Firestore pour un match League Individual
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function createMatchSession($matchId, array $matchData): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-individual-{$normalizedId}";
        
        $sessionData = [
            'matchId' => $matchId,
            'mode' => 'league_individual',
            'player1_id' => $matchData['player1_id'],
            'player2_id' => $matchData['player2_id'],
            'player1_name' => $matchData['player1_name'] ?? 'Player 1',
            'player2_name' => $matchData['player2_name'] ?? 'Player 2',
            'status' => $matchData['status'] ?? 'active',
            'currentRound' => 1,
            'currentQuestionNumber' => $matchData['currentQuestionNumber'] ?? 1,
            'totalQuestions' => $matchData['totalQuestions'] ?? 10,
            'questionStartTime' => $matchData['questionStartTime'] ?? microtime(true),
            'currentQuestionData' => $matchData['currentQuestionData'] ?? null,
            'player1_score' => 0,
            'player2_score' => 0,
            'player1_rounds_won' => 0,
            'player2_rounds_won' => 0,
            'buzzer' => null,
            'last_activity' => microtime(true),
        ];

        $result = $this->firebase->createGameSession($gameId, $sessionData);
        
        if ($result) {
            Log::info("Firestore session created for League Individual match #{$matchId}");
        } else {
            Log::error("Failed to create Firestore session for League Individual match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Enregistre un buzz dans Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function recordBuzz($matchId, string $playerId, float $timestamp): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-individual-{$normalizedId}";
        
        $result = $this->firebase->recordBuzz($gameId, $playerId, $timestamp);
        
        if ($result) {
            Log::info("Buzz recorded in Firestore for League Individual match #{$matchId}, player: {$playerId}");
        }
        
        return $result;
    }

    /**
     * Récupère tous les buzzes d'un match
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function getBuzzes($matchId): array
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-individual-{$normalizedId}";
        return $this->firebase->getBuzzes($gameId);
    }

    /**
     * Met à jour l'état du jeu dans Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function updateGameState($matchId, array $updates): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-individual-{$normalizedId}";
        
        $updates['last_activity'] = microtime(true);
        
        $result = $this->firebase->updateGameState($gameId, $updates);
        
        if ($result) {
            Log::info("Game state updated in Firestore for League Individual match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Met à jour les scores des joueurs
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function updateScores($matchId, int $player1Score, int $player2Score): bool
    {
        return $this->updateGameState($matchId, [
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
        ]);
    }

    /**
     * Passe à la question suivante avec timestamp
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function nextQuestion($matchId, int $questionNumber, float $timestamp): bool
    {
        return $this->updateGameState($matchId, [
            'currentQuestionNumber' => $questionNumber,
            'questionStartTime' => $timestamp,
        ]);
    }

    /**
     * Termine une manche et met à jour les manches gagnées
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function finishRound($matchId, int $currentRound, int $player1RoundsWon, int $player2RoundsWon): bool
    {
        return $this->updateGameState($matchId, [
            'currentRound' => $currentRound,
            'player1_rounds_won' => $player1RoundsWon,
            'player2_rounds_won' => $player2RoundsWon,
        ]);
    }

    /**
     * Récupère l'état complet du jeu pour synchronisation client
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function syncGameState($matchId): ?array
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-individual-{$normalizedId}";
        return $this->firebase->getGameState($gameId);
    }

    /**
     * Supprime la session Firestore (cleanup en fin de match)
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function deleteMatchSession($matchId): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-individual-{$normalizedId}";
        
        $result = $this->firebase->deleteGameSession($gameId);
        
        if ($result) {
            Log::info("Firestore session deleted for League Individual match #{$matchId}");
        } else {
            Log::warning("Failed to delete Firestore session for League Individual match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Vérifie si une session existe dans Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function sessionExists($matchId): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-individual-{$normalizedId}";
        return $this->firebase->gameSessionExists($gameId);
    }

    /**
     * Stocke les questions pré-générées pour le match dans Firestore
     * Appelé par le premier joueur (host) au démarrage
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function storeMatchQuestions($matchId, array $questions): bool
    {
        $result = $this->updateGameState($matchId, [
            'questions' => $questions,
            'questions_generated' => true,
            'questions_count' => count($questions),
        ]);
        
        if ($result) {
            Log::info("Stored " . count($questions) . " questions for League Individual match #{$matchId}");
        } else {
            Log::error("Failed to store questions for League Individual match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Récupère les questions partagées depuis Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function getMatchQuestions($matchId): ?array
    {
        $state = $this->syncGameState($matchId);
        
        if ($state && isset($state['questions'])) {
            return $state['questions'];
        }
        
        return null;
    }

    /**
     * Récupère une question spécifique par son index
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function getQuestion($matchId, int $questionIndex): ?array
    {
        $questions = $this->getMatchQuestions($matchId);
        
        if ($questions && isset($questions[$questionIndex])) {
            return $questions[$questionIndex];
        }
        
        return null;
    }

    /**
     * Vérifie si les questions ont été générées pour ce match
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function hasQuestions($matchId): bool
    {
        $state = $this->syncGameState($matchId);
        
        return $state && ($state['questions_generated'] ?? false);
    }

    /**
     * Met à jour le numéro de question actuelle pour synchroniser les joueurs
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function syncCurrentQuestion($matchId, int $questionNumber): bool
    {
        return $this->updateGameState($matchId, [
            'currentQuestionNumber' => $questionNumber,
            'question_sync_time' => microtime(true),
        ]);
    }
}
