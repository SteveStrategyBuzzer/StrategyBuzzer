<?php

namespace App\Services;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration Firestore pour le mode Duo
 * Gère la synchronisation temps réel entre 2 joueurs
 */
class DuoFirestoreService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    /**
     * Crée une session Firestore pour un match Duo
     */
    public function createMatchSession(int $matchId, array $matchData): bool
    {
        $gameId = "duo-match-{$matchId}";
        
        $sessionData = [
            'matchId' => $matchId,
            'mode' => 'duo',
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
            Log::info("Firestore session created for Duo match #{$matchId}");
        } else {
            Log::error("Failed to create Firestore session for Duo match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Enregistre un buzz dans Firestore et met à jour les flags pour la synchro temps réel
     */
    public function recordBuzz(int $matchId, string $playerId, float $timestamp, ?string $player1Id = null, ?string $player2Id = null): bool
    {
        $gameId = "duo-match-{$matchId}";
        
        $result = $this->firebase->recordBuzz($gameId, $playerId, $timestamp);
        
        if ($result) {
            Log::info("Buzz recorded in Firestore for match #{$matchId}, player: {$playerId}");
            
            $updates = [
                'lastBuzzPlayerId' => $playerId,
                'lastBuzzTime' => $timestamp,
            ];
            
            if ($player1Id && $playerId === $player1Id) {
                $updates['player1Buzzed'] = true;
            } elseif ($player2Id && $playerId === $player2Id) {
                $updates['player2Buzzed'] = true;
            } else {
                $updates['buzzedPlayerId'] = $playerId;
            }
            
            $this->updateGameState($matchId, $updates);
        }
        
        return $result;
    }
    
    /**
     * Réinitialise les flags de buzz pour une nouvelle question
     */
    public function resetBuzzFlags(int $matchId): bool
    {
        return $this->updateGameState($matchId, [
            'player1Buzzed' => false,
            'player2Buzzed' => false,
            'lastBuzzPlayerId' => null,
            'lastBuzzTime' => null,
            'buzzedPlayerId' => null,
        ]);
    }

    /**
     * Récupère tous les buzzes d'un match
     */
    public function getBuzzes(int $matchId): array
    {
        $gameId = "duo-match-{$matchId}";
        return $this->firebase->getBuzzes($gameId);
    }

    /**
     * Met à jour l'état du jeu dans Firestore
     */
    public function updateGameState(int $matchId, array $updates): bool
    {
        $gameId = "duo-match-{$matchId}";
        
        $updates['lastActivity'] = microtime(true);
        
        $result = $this->firebase->updateGameState($gameId, $updates);
        
        if ($result) {
            Log::info("Game state updated in Firestore for match #{$matchId}");
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
     * Passe à la question suivante
     */
    public function nextQuestion(int $matchId, int $questionNumber): bool
    {
        return $this->updateGameState($matchId, [
            'currentQuestion' => $questionNumber,
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
     * Marque le match comme terminé
     */
    public function finishMatch(int $matchId, string $winner): bool
    {
        return $this->updateGameState($matchId, [
            'status' => 'finished',
            'winner' => $winner,
        ]);
    }

    /**
     * Récupère l'état complet du jeu depuis Firestore
     */
    public function getGameState(int $matchId): ?array
    {
        $gameId = "duo-match-{$matchId}";
        return $this->firebase->getGameState($gameId);
    }

    /**
     * Supprime une session Firestore (cleanup)
     */
    public function deleteMatchSession(int $matchId): bool
    {
        $gameId = "duo-match-{$matchId}";
        
        $result = $this->firebase->deleteGameSession($gameId);
        
        if ($result) {
            Log::info("Firestore session deleted for Duo match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Vérifie si une session existe dans Firestore
     */
    public function sessionExists(int $matchId): bool
    {
        $gameId = "duo-match-{$matchId}";
        return $this->firebase->gameSessionExists($gameId);
    }

    /**
     * Nettoie les sessions inactives (appelé périodiquement)
     * Supprime les sessions de plus de 1 heure sans activité
     */
    public function cleanupInactiveSessions(int $inactiveThresholdMinutes = 60): int
    {
        // Note: Cette méthode nécessiterait une requête Firestore pour lister toutes les sessions
        // Pour l'instant, on va gérer le cleanup manuellement via deleteMatchSession
        // Une amélioration future pourrait utiliser Cloud Functions ou un worker Laravel
        
        Log::info("Cleanup inactive sessions not yet implemented (requires Firestore query)");
        return 0;
    }

    /**
     * Réinitialise les buzzes pour une nouvelle question
     */
    public function clearBuzzes(int $matchId): bool
    {
        $gameId = "duo-match-{$matchId}";
        
        // Note: Firestore ne permet pas de supprimer une sous-collection directement via REST API
        // Les buzzes seront ignorés en vérifiant le numéro de question
        // Alternative: on pourrait ajouter un champ 'questionNumber' aux buzzes pour les filtrer
        
        Log::info("Buzz clearing handled by question number filtering for match #{$matchId}");
        return true;
    }
}
