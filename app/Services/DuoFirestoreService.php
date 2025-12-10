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
     * Normalise un identifiant de match (lobby code, match_id, etc.) en entier unique
     * Utilise crc32 pour les codes alphanumériques afin d'assurer une cohérence partout
     * 
     * @param string|int $matchId Le code de lobby ou match_id brut
     * @return int L'identifiant numérique normalisé
     */
    public static function normalizeMatchId($matchId): int
    {
        if (is_int($matchId) && $matchId > 0) {
            return $matchId;
        }
        
        $matchIdStr = (string)$matchId;
        $numericId = (int)preg_replace('/[^0-9]/', '', $matchIdStr);
        
        if ($numericId === 0) {
            $numericId = crc32($matchIdStr) & 0x7FFFFFFF;
        }
        
        Log::debug("Normalized matchId '{$matchIdStr}' to {$numericId}");
        
        return $numericId;
    }

    /**
     * Crée une session Firestore pour un match Duo
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function createMatchSession($matchId, array $matchData): bool
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        
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
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function recordBuzz($matchId, string $playerId, float $timestamp, ?string $player1Id = null, ?string $player2Id = null): bool
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        
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
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function resetBuzzFlags($matchId): bool
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
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function getBuzzes($matchId): array
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        return $this->firebase->getBuzzes($gameId);
    }

    /**
     * Met à jour l'état du jeu dans Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function updateGameState($matchId, array $updates): bool
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        
        $updates['lastActivity'] = microtime(true);
        
        $result = $this->firebase->updateGameState($gameId, $updates);
        
        if ($result) {
            Log::info("Game state updated in Firestore for match #{$matchId}");
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
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
        ]);
    }

    /**
     * Passe à la question suivante
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function nextQuestion($matchId, int $questionNumber): bool
    {
        return $this->updateGameState($matchId, [
            'currentQuestion' => $questionNumber,
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
            'player1RoundsWon' => $player1RoundsWon,
            'player2RoundsWon' => $player2RoundsWon,
        ]);
    }

    /**
     * Marque le match comme terminé
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function finishMatch($matchId, string $winner): bool
    {
        return $this->updateGameState($matchId, [
            'status' => 'finished',
            'winner' => $winner,
        ]);
    }

    /**
     * Récupère l'état complet du jeu depuis Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function getGameState($matchId): ?array
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        return $this->firebase->getGameState($gameId);
    }

    /**
     * Supprime une session Firestore (cleanup)
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function deleteMatchSession($matchId): bool
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        
        $result = $this->firebase->deleteGameSession($gameId);
        
        if ($result) {
            Log::info("Firestore session deleted for Duo match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Vérifie si une session existe dans Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function sessionExists($matchId): bool
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
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
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function clearBuzzes($matchId): bool
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        
        // Note: Firestore ne permet pas de supprimer une sous-collection directement via REST API
        // Les buzzes seront ignorés en vérifiant le numéro de question
        // Alternative: on pourrait ajouter un champ 'questionNumber' aux buzzes pour les filtrer
        
        Log::info("Buzz clearing handled by question number filtering for match #{$matchId}");
        return true;
    }

    /**
     * Stocke les questions pré-générées pour le match dans Firestore
     * Appelé par le premier joueur (host) au démarrage
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function storeMatchQuestions($matchId, array $questions): bool
    {
        $normalizedId = self::normalizeMatchId($matchId);
        $gameId = "duo-match-{$normalizedId}";
        
        $result = $this->updateGameState($matchId, [
            'questions' => $questions,
            'questionsGenerated' => true,
            'questionsCount' => count($questions),
        ]);
        
        if ($result) {
            Log::info("Stored " . count($questions) . " questions for match #{$matchId}");
        } else {
            Log::error("Failed to store questions for match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Récupère les questions partagées depuis Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function getMatchQuestions($matchId): ?array
    {
        $state = $this->getGameState($matchId);
        
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
        $state = $this->getGameState($matchId);
        
        return $state && ($state['questionsGenerated'] ?? false);
    }

    /**
     * Met à jour le numéro de question actuelle pour synchroniser les joueurs
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function syncCurrentQuestion($matchId, int $questionNumber): bool
    {
        return $this->updateGameState($matchId, [
            'currentQuestion' => $questionNumber,
            'questionSyncTime' => microtime(true),
        ]);
    }
}
