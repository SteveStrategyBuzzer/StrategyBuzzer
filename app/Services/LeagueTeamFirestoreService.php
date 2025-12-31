<?php

namespace App\Services;

use App\Services\FirebaseService;
use App\Services\DuoFirestoreService;
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
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function createMatchSession($matchId, array $matchData): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-team-{$normalizedId}";
        
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
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function recordBuzz($matchId, string $teamId, int $playerId, float $timestamp): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-team-{$normalizedId}";
        
        $buzzId = "{$teamId}_{$playerId}";
        $result = $this->firebase->recordBuzz($gameId, $buzzId, $timestamp);
        
        if ($result) {
            Log::info("Buzz recorded in Firestore for League Team match #{$matchId}, team: {$teamId}, player: {$playerId}");
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
        $gameId = "league-team-{$normalizedId}";
        return $this->firebase->getBuzzes($gameId);
    }

    /**
     * Met à jour l'état du jeu dans Firestore
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function updateGameState($matchId, array $updates): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-team-{$normalizedId}";
        
        $updates['lastActivity'] = microtime(true);
        
        $result = $this->firebase->updateGameState($gameId, $updates);
        
        if ($result) {
            Log::info("Game state updated in Firestore for League Team match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Met à jour les scores des équipes
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function updateScores($matchId, int $team1Score, int $team2Score): bool
    {
        return $this->updateGameState($matchId, [
            'team1Score' => $team1Score,
            'team2Score' => $team2Score,
        ]);
    }

    /**
     * Passe à la question suivante avec timestamp
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function nextQuestion($matchId, int $questionNumber, float $timestamp): bool
    {
        return $this->updateGameState($matchId, [
            'currentQuestion' => $questionNumber,
            'questionStartTime' => $timestamp,
        ]);
    }

    /**
     * Termine une manche et met à jour les manches gagnées
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function finishRound($matchId, int $currentRound, int $team1RoundsWon, int $team2RoundsWon): bool
    {
        return $this->updateGameState($matchId, [
            'currentRound' => $currentRound,
            'team1RoundsWon' => $team1RoundsWon,
            'team2RoundsWon' => $team2RoundsWon,
        ]);
    }

    /**
     * Récupère l'état complet du jeu pour synchronisation client
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function syncGameState($matchId): ?array
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-team-{$normalizedId}";
        return $this->firebase->getGameState($gameId);
    }

    /**
     * Supprime la session Firestore (cleanup en fin de match)
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function deleteMatchSession($matchId): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-team-{$normalizedId}";
        
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
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function sessionExists($matchId): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $gameId = "league-team-{$normalizedId}";
        return $this->firebase->gameSessionExists($gameId);
    }

    /**
     * Met à jour le statut du match (active, finished, cancelled)
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     */
    public function updateMatchStatus($matchId, string $status): bool
    {
        return $this->updateGameState($matchId, [
            'status' => $status,
        ]);
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
            'questionsGenerated' => true,
            'questionsCount' => count($questions),
        ]);
        
        if ($result) {
            Log::info("Stored " . count($questions) . " questions for League Team match #{$matchId}");
        } else {
            Log::error("Failed to store questions for League Team match #{$matchId}");
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

    /**
     * Publie une question directement sur Firebase pour synchronisation
     * Tous les joueurs reçoivent la même question au même moment
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     * @param array $questionData Les données de la question
     * @param int $questionNumber Le numéro de la question
     */
    public function publishQuestion($matchId, array $questionData, int $questionNumber): bool
    {
        $publishTime = microtime(true);
        
        // Sanitize answers - NEVER include is_correct to protect answer integrity
        $sanitizedAnswers = [];
        foreach ($questionData['answers'] ?? [] as $answer) {
            if (is_array($answer)) {
                $sanitizedAnswers[] = [
                    'text' => $answer['text'] ?? $answer[0] ?? '',
                ];
            } else {
                $sanitizedAnswers[] = [
                    'text' => (string)$answer,
                ];
            }
        }
        
        $questionPayload = [
            'currentQuestion' => $questionNumber,
            'questionVersion' => $questionNumber,
            'questionPublishedAt' => $publishTime,
            'currentQuestionData' => [
                'question_number' => $questionNumber,
                'total_questions' => $questionData['total_questions'] ?? 10,
                'question_text' => $questionData['question_text'] ?? $questionData['text'] ?? '',
                'answers' => $sanitizedAnswers,
                'theme' => $questionData['theme'] ?? 'Général',
                'sub_theme' => $questionData['sub_theme'] ?? '',
                'chrono_time' => $questionData['chrono_time'] ?? 8,
            ],
            'buzzedPlayerId' => null,
        ];
        
        $result = $this->updateGameState($matchId, $questionPayload);
        
        if ($result) {
            Log::info("Question #{$questionNumber} published for League Team match #{$matchId}");
        } else {
            Log::error("Failed to publish question #{$questionNumber} for League Team match #{$matchId}");
        }
        
        return $result;
    }

    /**
     * Stocke une question pré-générée individuellement dans Firebase
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     * @param int $questionNumber Le numéro de la question (1-indexed)
     * @param array $questionData Les données de la question
     */
    public function storePreGeneratedQuestion($matchId, int $questionNumber, array $questionData): bool
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $collectionPath = "games/league-team-{$normalizedId}/preGeneratedQuestions";
        $documentId = (string)$questionNumber;
        
        // SECURITY: Sanitize answers - NEVER include is_correct flag to protect answer integrity
        $sanitizedAnswers = [];
        foreach ($questionData['answers'] ?? [] as $answer) {
            if (is_array($answer)) {
                $sanitizedAnswers[] = [
                    'text' => $answer['text'] ?? $answer[0] ?? '',
                ];
            } else {
                $sanitizedAnswers[] = [
                    'text' => (string)$answer,
                ];
            }
        }
        
        // SECURITY: NEVER store correct_index in Firebase - clients can read this and cheat
        // correct_index must only be validated server-side
        $sanitizedData = [
            'id' => $questionData['id'] ?? uniqid('q_'),
            'text' => $questionData['text'] ?? '',
            'answers' => $sanitizedAnswers,
            'sub_theme' => $questionData['sub_theme'] ?? '',
            'question_number' => $questionNumber,
            'generatedAt' => microtime(true),
        ];
        
        $result = $this->firebase->createDocument($collectionPath, $documentId, $sanitizedData);
        
        if ($result) {
            Log::info("Pre-generated question #{$questionNumber} stored for League Team match #{$matchId} (correct_index stripped for security)");
        }
        
        return $result;
    }

    /**
     * Récupère une question pré-générée depuis Firebase
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     * @param int $questionNumber Le numéro de la question (1-indexed)
     * @return array|null Les données de la question ou null si non disponible
     */
    public function getPreGeneratedQuestion($matchId, int $questionNumber): ?array
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $collectionPath = "games/league-team-{$normalizedId}/preGeneratedQuestions";
        
        $questions = $this->firebase->getCollection($collectionPath);
        
        $documentId = (string)$questionNumber;
        if (isset($questions[$documentId])) {
            return $questions[$documentId];
        }
        
        return null;
    }

    /**
     * Récupère toutes les questions pré-générées disponibles
     * @param string|int $matchId Le code de lobby ou match_id brut (sera normalisé)
     * @return array Tableau associatif [questionNumber => questionData]
     */
    public function getAllPreGeneratedQuestions($matchId): array
    {
        $normalizedId = DuoFirestoreService::normalizeMatchId($matchId);
        $collectionPath = "games/league-team-{$normalizedId}/preGeneratedQuestions";
        
        return $this->firebase->getCollection($collectionPath);
    }
}
