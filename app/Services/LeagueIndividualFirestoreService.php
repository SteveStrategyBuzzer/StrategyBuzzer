<?php

namespace App\Services;

use App\Services\FirebaseService;
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
     */
    public function createMatchSession(int $matchId, array $matchData): bool
    {
        $gameId = "league-individual-{$matchId}";
        
        $sessionData = [
            'matchId' => $matchId,
            'mode' => 'league_individual',
            'player1Id' => $matchData['player1_id'],
            'player2Id' => $matchData['player2_id'],
            'player1Name' => $matchData['player1_name'] ?? 'Player 1',
            'player2Name' => $matchData['player2_name'] ?? 'Player 2',
            'status' => 'active',
            'currentRound' => 1,
            'currentQuestion' => 1,
            'questionStartTime' => $matchData['questionStartTime'] ?? microtime(true),
            'player1Score' => 0,
            'player2Score' => 0,
            'player1RoundsWon' => 0,
            'player2RoundsWon' => 0,
            'lastActivity' => microtime(true),
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
     */
    public function recordBuzz(int $matchId, string $playerId, float $timestamp): bool
    {
        $gameId = "league-individual-{$matchId}";
        
        $result = $this->firebase->recordBuzz($gameId, $playerId, $timestamp);
        
        if ($result) {
            Log::info("Buzz recorded in Firestore for League Individual match #{$matchId}, player: {$playerId}");
        }
        
        return $result;
    }

    /**
     * Récupère tous les buzzes d'un match
     */
    public function getBuzzes(int $matchId): array
    {
        $gameId = "league-individual-{$matchId}";
        return $this->firebase->getBuzzes($gameId);
    }

    /**
     * Met à jour l'état du jeu dans Firestore
     */
    public function updateGameState(int $matchId, array $updates): bool
    {
        $gameId = "league-individual-{$matchId}";
        
        $updates['lastActivity'] = microtime(true);
        
        $result = $this->firebase->updateGameState($gameId, $updates);
        
        if ($result) {
            Log::info("Game state updated in Firestore for League Individual match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Met à jour les scores des joueurs
     */
    public function updateScores(int $matchId, int $player1Score, int $player2Score): bool
    {
        return $this->updateGameState($matchId, [
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
        ]);
    }

    /**
     * Passe à la question suivante avec timestamp
     */
    public function nextQuestion(int $matchId, int $questionNumber, float $timestamp): bool
    {
        return $this->updateGameState($matchId, [
            'currentQuestion' => $questionNumber,
            'questionStartTime' => $timestamp,
        ]);
    }

    /**
     * Termine une manche et met à jour les manches gagnées
     */
    public function finishRound(int $matchId, int $currentRound, int $player1RoundsWon, int $player2RoundsWon): bool
    {
        return $this->updateGameState($matchId, [
            'currentRound' => $currentRound,
            'player1RoundsWon' => $player1RoundsWon,
            'player2RoundsWon' => $player2RoundsWon,
        ]);
    }

    /**
     * Récupère l'état complet du jeu pour synchronisation client
     */
    public function syncGameState(int $matchId): ?array
    {
        $gameId = "league-individual-{$matchId}";
        return $this->firebase->getGameState($gameId);
    }

    /**
     * Supprime la session Firestore (cleanup en fin de match)
     */
    public function deleteMatchSession(int $matchId): bool
    {
        $gameId = "league-individual-{$matchId}";
        
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
     */
    public function sessionExists(int $matchId): bool
    {
        $gameId = "league-individual-{$matchId}";
        return $this->firebase->gameSessionExists($gameId);
    }
}
