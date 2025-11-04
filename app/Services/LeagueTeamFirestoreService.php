<?php

namespace App\Services;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration Firestore pour le mode League Team
 * Gère la synchronisation temps réel entre 2 équipes de 5 joueurs (5v5)
 */
class LeagueTeamFirestoreService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    /**
     * Crée une session Firestore pour un match League Team (5v5)
     */
    public function createMatchSession(int $matchId, array $matchData): bool
    {
        $gameId = "league-team-{$matchId}";
        
        $sessionData = [
            'matchId' => $matchId,
            'mode' => 'league_team',
            'team1Id' => $matchData['team1_id'],
            'team2Id' => $matchData['team2_id'],
            'team1Name' => $matchData['team1_name'] ?? 'Team 1',
            'team2Name' => $matchData['team2_name'] ?? 'Team 2',
            'team1Players' => $matchData['team1_players'] ?? [],
            'team2Players' => $matchData['team2_players'] ?? [],
            'status' => 'active',
            'currentRound' => 1,
            'currentQuestion' => 1,
            'questionStartTime' => $matchData['questionStartTime'] ?? microtime(true),
            'team1Score' => 0,
            'team2Score' => 0,
            'team1RoundsWon' => 0,
            'team2RoundsWon' => 0,
            'lastActivity' => microtime(true),
        ];

        $result = $this->firebase->createGameSession($gameId, $sessionData);
        
        if ($result) {
            Log::info("Firestore session created for League Team match #{$matchId}");
        } else {
            Log::error("Failed to create Firestore session for League Team match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Enregistre un buzz dans Firestore (avec playerId pour identifier le joueur dans l'équipe)
     */
    public function recordBuzz(int $matchId, string $teamId, int $playerId, float $timestamp): bool
    {
        $gameId = "league-team-{$matchId}";
        
        $buzzId = "{$teamId}_{$playerId}";
        $result = $this->firebase->recordBuzz($gameId, $buzzId, $timestamp);
        
        if ($result) {
            Log::info("Buzz recorded in Firestore for League Team match #{$matchId}, team: {$teamId}, player: {$playerId}");
        }
        
        return $result;
    }

    /**
     * Récupère tous les buzzes d'un match
     */
    public function getBuzzes(int $matchId): array
    {
        $gameId = "league-team-{$matchId}";
        return $this->firebase->getBuzzes($gameId);
    }

    /**
     * Met à jour l'état du jeu dans Firestore
     */
    public function updateGameState(int $matchId, array $updates): bool
    {
        $gameId = "league-team-{$matchId}";
        
        $updates['lastActivity'] = microtime(true);
        
        $result = $this->firebase->updateGameState($gameId, $updates);
        
        if ($result) {
            Log::info("Game state updated in Firestore for League Team match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Met à jour les scores des équipes
     */
    public function updateScores(int $matchId, int $team1Score, int $team2Score): bool
    {
        return $this->updateGameState($matchId, [
            'team1Score' => $team1Score,
            'team2Score' => $team2Score,
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
    public function finishRound(int $matchId, int $currentRound, int $team1RoundsWon, int $team2RoundsWon): bool
    {
        return $this->updateGameState($matchId, [
            'currentRound' => $currentRound,
            'team1RoundsWon' => $team1RoundsWon,
            'team2RoundsWon' => $team2RoundsWon,
        ]);
    }

    /**
     * Récupère l'état complet du jeu pour synchronisation client
     */
    public function syncGameState(int $matchId): ?array
    {
        $gameId = "league-team-{$matchId}";
        return $this->firebase->getGameState($gameId);
    }

    /**
     * Supprime la session Firestore (cleanup en fin de match)
     */
    public function deleteMatchSession(int $matchId): bool
    {
        $gameId = "league-team-{$matchId}";
        
        $result = $this->firebase->deleteGameSession($gameId);
        
        if ($result) {
            Log::info("Firestore session deleted for League Team match #{$matchId}");
        } else {
            Log::warning("Failed to delete Firestore session for League Team match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Vérifie si une session existe dans Firestore
     */
    public function sessionExists(int $matchId): bool
    {
        $gameId = "league-team-{$matchId}";
        return $this->firebase->gameSessionExists($gameId);
    }

    /**
     * Met à jour le statut du match (active, finished, cancelled)
     */
    public function updateMatchStatus(int $matchId, string $status): bool
    {
        return $this->updateGameState($matchId, [
            'status' => $status,
        ]);
    }
}
