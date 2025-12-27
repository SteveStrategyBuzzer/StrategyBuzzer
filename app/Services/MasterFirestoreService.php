<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service pour gérer la synchronisation Firestore des sessions Master Mode
 * Gère les quiz en temps réel avec 3-40 participants
 */
class MasterFirestoreService
{
    private FirebaseService $firebase;
    
    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }
    
    /**
     * Crée une session Firestore pour un jeu Master
     */
    public function createGameSession(int $gameId, array $gameData): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        $sessionData = [
            'gameId' => $gameId,
            'mode' => 'master',
            'gameMode' => $gameData['game_mode'] ?? 'face_to_face', // face_to_face, one_vs_all, podium, groups
            'hostId' => $gameData['host_id'],
            'hostName' => $gameData['host_name'] ?? 'Host',
            'status' => 'lobby', // lobby, playing, finished
            'currentQuestion' => 0, // 0 = pas encore commencé
            'questionStartTime' => null,
            'totalQuestions' => $gameData['total_questions'],
            'participants' => [], // {userId: {name, score, answered, side}}
            'participantsExpected' => $gameData['participants_expected'],
            'participantsCount' => 0,
            'createdAt' => microtime(true),
            'lastActivity' => microtime(true),
        ];
        
        $result = $this->firebase->createGameSession($firestoreGameId, $sessionData);
        
        if ($result) {
            Log::info("Master Game session created in Firestore", [
                'game_id' => $gameId,
                'firestore_id' => $firestoreGameId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Ajoute un participant à la session
     */
    public function addParticipant(int $gameId, int $userId, string $userName, ?string $side = null): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            Log::warning("Cannot add participant: Master session does not exist", [
                'game_id' => $gameId,
                'user_id' => $userId
            ]);
            return false;
        }
        
        // Récupérer l'état actuel pour compter les participants
        $gameState = $this->firebase->getGameState($firestoreGameId);
        $participants = $gameState['participants'] ?? [];
        $participantKey = "user_{$userId}";
        
        // Ajouter ou mettre à jour le participant
        $participants[$participantKey] = [
            'userId' => $userId,
            'name' => $userName,
            'score' => 0,
            'answered' => [],
            'side' => $side, // Pour les modes face_to_face, groups
            'joinedAt' => microtime(true)
        ];
        
        $updateData = [
            'participants' => $participants,
            'participantsCount' => count($participants),
            'lastActivity' => microtime(true)
        ];
        
        $result = $this->firebase->updateGameState($firestoreGameId, $updateData);
        
        if ($result) {
            Log::info("Participant added to Master session", [
                'game_id' => $gameId,
                'user_id' => $userId,
                'total_participants' => count($participants)
            ]);
        }
        
        return $result;
    }
    
    /**
     * Retire un participant de la session
     */
    public function removeParticipant(int $gameId, int $userId): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return false;
        }
        
        $gameState = $this->firebase->getGameState($firestoreGameId);
        $participants = $gameState['participants'] ?? [];
        $participantKey = "user_{$userId}";
        
        if (isset($participants[$participantKey])) {
            unset($participants[$participantKey]);
            
            $updateData = [
                'participants' => $participants,
                'participantsCount' => count($participants),
                'lastActivity' => microtime(true)
            ];
            
            return $this->firebase->updateGameState($firestoreGameId, $updateData);
        }
        
        return true;
    }
    
    /**
     * Démarre le jeu (passe de lobby à playing)
     */
    public function startGame(int $gameId): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            Log::warning("Cannot start game: Master session does not exist", [
                'game_id' => $gameId
            ]);
            return false;
        }
        
        $updateData = [
            'status' => 'playing',
            'currentQuestion' => 1,
            'questionStartTime' => microtime(true),
            'startedAt' => microtime(true),
            'lastActivity' => microtime(true)
        ];
        
        $result = $this->firebase->updateGameState($firestoreGameId, $updateData);
        
        if ($result) {
            Log::info("Master Game started", ['game_id' => $gameId]);
        }
        
        return $result;
    }
    
    /**
     * Passe à la question suivante
     */
    public function nextQuestion(int $gameId, int $questionNumber): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return false;
        }
        
        $updateData = [
            'currentQuestion' => $questionNumber,
            'questionStartTime' => microtime(true),
            'lastActivity' => microtime(true)
        ];
        
        $result = $this->firebase->updateGameState($firestoreGameId, $updateData);
        
        if ($result) {
            Log::info("Master Game advanced to next question", [
                'game_id' => $gameId,
                'question_number' => $questionNumber
            ]);
        }
        
        return $result;
    }
    
    /**
     * Enregistre la réponse d'un participant
     */
    public function recordAnswer(
        int $gameId, 
        int $questionNumber, 
        int $userId, 
        int $answerIndex, 
        bool $isCorrect,
        int $score
    ): bool {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            Log::warning("Cannot record answer: Master session does not exist", [
                'game_id' => $gameId,
                'user_id' => $userId
            ]);
            return false;
        }
        
        $answerId = "q{$questionNumber}_u{$userId}";
        
        $answerData = [
            'questionNumber' => $questionNumber,
            'userId' => $userId,
            'answerIndex' => $answerIndex,
            'isCorrect' => $isCorrect,
            'score' => $score,
            'timestamp' => microtime(true)
        ];
        
        // Enregistrer dans la sous-collection answers
        $result = $this->firebase->createDocument(
            "games/{$firestoreGameId}/answers",
            $answerId,
            $answerData
        );
        
        if ($result) {
            Log::info("Answer recorded in Master Game", [
                'game_id' => $gameId,
                'question_number' => $questionNumber,
                'user_id' => $userId,
                'is_correct' => $isCorrect,
                'score' => $score
            ]);
        }
        
        return $result;
    }
    
    /**
     * Met à jour le score d'un participant
     */
    public function updateParticipantScore(int $gameId, int $userId, int $score, array $answered = []): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return false;
        }
        
        $gameState = $this->firebase->getGameState($firestoreGameId);
        $participants = $gameState['participants'] ?? [];
        $participantKey = "user_{$userId}";
        
        if (isset($participants[$participantKey])) {
            $participants[$participantKey]['score'] = $score;
            
            if (!empty($answered)) {
                $participants[$participantKey]['answered'] = $answered;
            }
            
            $updateData = [
                'participants' => $participants,
                'lastActivity' => microtime(true)
            ];
            
            return $this->firebase->updateGameState($firestoreGameId, $updateData);
        }
        
        return false;
    }
    
    /**
     * Met à jour plusieurs scores de participants (bulk update)
     */
    public function updateMultipleScores(int $gameId, array $scoresData): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return false;
        }
        
        $gameState = $this->firebase->getGameState($firestoreGameId);
        $participants = $gameState['participants'] ?? [];
        
        foreach ($scoresData as $userId => $data) {
            $participantKey = "user_{$userId}";
            if (isset($participants[$participantKey])) {
                $participants[$participantKey]['score'] = $data['score'];
                
                if (isset($data['answered'])) {
                    $participants[$participantKey]['answered'] = $data['answered'];
                }
            }
        }
        
        $updateData = [
            'participants' => $participants,
            'lastActivity' => microtime(true)
        ];
        
        return $this->firebase->updateGameState($firestoreGameId, $updateData);
    }
    
    /**
     * Termine le jeu
     */
    public function finishGame(int $gameId, ?int $winnerId = null): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return false;
        }
        
        $updateData = [
            'status' => 'finished',
            'winnerId' => $winnerId,
            'finishedAt' => microtime(true),
            'lastActivity' => microtime(true)
        ];
        
        $result = $this->firebase->updateGameState($firestoreGameId, $updateData);
        
        if ($result) {
            Log::info("Master Game finished", [
                'game_id' => $gameId,
                'winner_id' => $winnerId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Récupère l'état complet du jeu pour synchronisation
     */
    public function syncGameState(int $gameId): ?array
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return null;
        }
        
        $gameState = $this->firebase->getGameState($firestoreGameId);
        
        // Récupérer toutes les réponses de la question actuelle
        $currentQuestion = $gameState['currentQuestion'] ?? 0;
        
        if ($currentQuestion > 0) {
            $answers = $this->getQuestionAnswers($gameId, $currentQuestion);
            $gameState['currentQuestionAnswers'] = $answers;
        }
        
        return $gameState;
    }
    
    /**
     * Récupère toutes les réponses pour une question donnée
     */
    public function getQuestionAnswers(int $gameId, int $questionNumber): array
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return [];
        }
        
        $pattern = "q{$questionNumber}_";
        $allAnswers = $this->firebase->getCollection("games/{$firestoreGameId}/answers");
        
        $filtered = [];
        foreach ($allAnswers as $answerId => $answerData) {
            // Use strpos for PHP 7.x compatibility (str_starts_with is PHP 8+)
            if (strpos($answerId, $pattern) === 0) {
                $filtered[] = $answerData;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Récupère toutes les réponses du jeu (pour statistiques finales)
     */
    public function getAllAnswers(int $gameId): array
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return [];
        }
        
        return $this->firebase->getCollection("games/{$firestoreGameId}/answers");
    }
    
    /**
     * Supprime la session Firestore (cleanup)
     */
    public function deleteGameSession(int $gameId): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return true; // Déjà supprimée
        }
        
        $result = $this->firebase->deleteGameSession($firestoreGameId);
        
        if ($result) {
            Log::info("Master Game session deleted from Firestore", [
                'game_id' => $gameId,
                'firestore_id' => $firestoreGameId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Vérifie si une session Firestore existe
     */
    public function sessionExists(int $gameId): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        return $this->firebase->gameSessionExists($firestoreGameId);
    }
    
    /**
     * Met à jour l'état du jeu (générique)
     */
    public function updateGameState(int $gameId, array $data): bool
    {
        $firestoreGameId = "master-game-{$gameId}";
        
        if (!$this->sessionExists($gameId)) {
            return false;
        }
        
        $data['lastActivity'] = microtime(true);
        
        return $this->firebase->updateGameState($firestoreGameId, $data);
    }
}
