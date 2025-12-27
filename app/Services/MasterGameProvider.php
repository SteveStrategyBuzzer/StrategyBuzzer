<?php

namespace App\Services;

use App\Models\User;

class MasterGameProvider extends GameModeProvider
{
    protected string $mode = 'master';
    protected array $players = [];
    protected ?User $host = null;
    protected bool $isHost = false;
    protected ?MasterFirestoreService $firestoreService = null;
    
    public function __construct(User $player, array $gameState = [])
    {
        parent::__construct($player, $gameState);
        
        if (empty($this->gameState)) {
            $this->gameState = $this->initializeMasterState();
        }
        
        $this->firestoreService = app(MasterFirestoreService::class);
    }
    
    protected function initializeMasterState(): array
    {
        $base = $this->initializeBaseState();
        $base['mode'] = 'master';
        $base['players'] = [];
        $base['player_scores'] = [];
        $base['player_buzzed'] = [];
        $base['min_players'] = 3;
        $base['max_players'] = 40;
        $base['host_id'] = null;
        $base['room_code'] = null;
        $base['game_status'] = 'waiting';
        return $base;
    }
    
    public function getMode(): string
    {
        return 'master';
    }
    
    public function getOpponentType(): string
    {
        return 'multiplayer';
    }
    
    public function setAsHost(bool $isHost = true): void
    {
        $this->isHost = $isHost;
        if ($isHost) {
            $this->gameState['host_id'] = $this->player->id;
        }
    }
    
    public function isHost(): bool
    {
        return $this->isHost;
    }
    
    public function addPlayer(User $player): void
    {
        $playerId = $player->id;
        
        if (count($this->players) >= ($this->gameState['max_players'] ?? 40)) {
            throw new \Exception("Maximum number of players reached");
        }
        
        $this->players[$playerId] = [
            'user_id' => $playerId,
            'name' => $player->name,
            'avatar' => $player->profile_settings['avatar'] ?? 'default',
            'player_code' => $player->player_code,
        ];
        
        $this->gameState['players'][$playerId] = $this->players[$playerId];
        $this->gameState['player_scores'][$playerId] = 0;
        $this->gameState['player_buzzed'][$playerId] = false;
    }
    
    public function removePlayer(int $playerId): void
    {
        unset($this->players[$playerId]);
        unset($this->gameState['players'][$playerId]);
        unset($this->gameState['player_scores'][$playerId]);
        unset($this->gameState['player_buzzed'][$playerId]);
    }
    
    public function getOpponentInfo(): array
    {
        return [
            'is_multiplayer' => true,
            'player_count' => count($this->players),
            'players' => array_values($this->players),
        ];
    }
    
    public function handleBuzz(float $buzzTime): array
    {
        $roomCode = $this->gameState['room_code'] ?? null;
        $playerId = $this->player->id;
        
        if ($roomCode) {
            $this->firestoreService->recordBuzz($roomCode, $playerId, $buzzTime);
        }
        
        $this->gameState['player_buzzed'][$playerId] = true;
        $this->gameState['buzz_times'][$playerId] = $buzzTime;
        
        return [
            'success' => true,
            'player_id' => $playerId,
            'buzz_time' => $buzzTime,
        ];
    }
    
    public function handleOpponentBuzz(): array
    {
        return [
            'all_buzzed' => $this->gameState['player_buzzed'] ?? [],
            'buzz_times' => $this->gameState['buzz_times'] ?? [],
        ];
    }
    
    public function submitAnswer(int $answerId, bool $isCorrect, bool $timedOut = false): array
    {
        $roomCode = $this->gameState['room_code'] ?? null;
        $playerId = $this->player->id;
        $buzzTime = $this->gameState['buzz_times'][$playerId] ?? 5.0;
        // Timeout = 0 points, MasterGameProvider already returns 0 for incorrect
        $points = $timedOut ? 0 : $this->calculatePoints($isCorrect, $buzzTime);
        
        if ($roomCode) {
            $this->firestoreService->recordAnswer($roomCode, $playerId, $answerId, $isCorrect, $points);
        }
        
        $this->gameState['player_scores'][$playerId] = 
            ($this->gameState['player_scores'][$playerId] ?? 0) + $points;
        
        return [
            'success' => true,
            'player_id' => $playerId,
            'is_correct' => $isCorrect,
            'points' => $points,
            'player_score' => $this->gameState['player_scores'][$playerId],
            'timed_out' => $timedOut,
        ];
    }
    
    public function handleOpponentAnswer(): array
    {
        return [
            'all_scores' => $this->gameState['player_scores'] ?? [],
        ];
    }
    
    public function calculatePoints(bool $isCorrect, float $buzzTime): int
    {
        if (!$isCorrect) {
            return 0;
        }
        
        $ranking = $this->getBuzzRanking($this->player->id);
        
        switch ($ranking) {
            case 1: return 10;
            case 2: return 7;
            case 3: return 5;
            default: return 3;
        }
    }
    
    protected function getBuzzRanking(int $playerId): int
    {
        $buzzTimes = $this->gameState['buzz_times'] ?? [];
        $playerTime = $buzzTimes[$playerId] ?? PHP_FLOAT_MAX;
        
        $ranking = 1;
        foreach ($buzzTimes as $id => $time) {
            if ($id !== $playerId && $time < $playerTime) {
                $ranking++;
            }
        }
        
        return $ranking;
    }
    
    public function getScoring(): array
    {
        return [
            'type' => 'ranking',
            'points_1st' => 10,
            'points_2nd' => 7,
            'points_3rd' => 5,
            'points_other' => 3,
            'points_incorrect' => 0,
            'no_negative_points' => true,
        ];
    }
    
    public function isMatchComplete(): bool
    {
        $currentQuestion = $this->gameState['current_question'] ?? 1;
        $totalQuestions = $this->gameState['total_questions'] ?? 10;
        
        return $currentQuestion > $totalQuestions;
    }
    
    public function getMatchResult(): array
    {
        $scores = $this->gameState['player_scores'] ?? [];
        arsort($scores);
        
        $ranking = [];
        $position = 1;
        foreach ($scores as $playerId => $score) {
            $playerInfo = $this->gameState['players'][$playerId] ?? [];
            $ranking[] = [
                'position' => $position,
                'player_id' => $playerId,
                'name' => $playerInfo['name'] ?? 'Joueur',
                'score' => $score,
            ];
            $position++;
        }
        
        $winnerId = array_key_first($scores);
        
        return [
            'ranking' => $ranking,
            'winner_id' => $winnerId,
            'winner_name' => $this->gameState['players'][$winnerId]['name'] ?? 'Joueur',
            'winner_score' => $scores[$winnerId] ?? 0,
            'total_players' => count($scores),
            'player_position' => $this->getPlayerPosition($this->player->id, $ranking),
            'player_score' => $scores[$this->player->id] ?? 0,
        ];
    }
    
    protected function getPlayerPosition(int $playerId, array $ranking): int
    {
        foreach ($ranking as $entry) {
            if ($entry['player_id'] === $playerId) {
                return $entry['position'];
            }
        }
        return 0;
    }
    
    public function getNextQuestion(): ?array
    {
        $currentQuestion = $this->gameState['current_question'] ?? 1;
        $totalQuestions = $this->gameState['total_questions'] ?? 10;
        
        if ($currentQuestion > $totalQuestions) {
            return null;
        }
        
        return [
            'question_number' => $currentQuestion,
            'total_questions' => $totalQuestions,
        ];
    }
    
    public function resetForNextQuestion(): void
    {
        $this->gameState['current_question'] = ($this->gameState['current_question'] ?? 1) + 1;
        
        foreach ($this->gameState['player_buzzed'] as $playerId => $value) {
            $this->gameState['player_buzzed'][$playerId] = false;
        }
        $this->gameState['buzz_times'] = [];
    }
    
    public function generateRoomCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $this->gameState['room_code'] = $code;
        return $code;
    }
    
    public function getRoomCode(): ?string
    {
        return $this->gameState['room_code'] ?? null;
    }
    
    public function getPlayersCount(): int
    {
        return count($this->players);
    }
    
    public function canStartGame(): bool
    {
        $minPlayers = $this->gameState['min_players'] ?? 3;
        return count($this->players) >= $minPlayers;
    }
    
    public function finishRound(): array
    {
        return [
            'scores' => $this->gameState['player_scores'] ?? [],
            'question_number' => $this->gameState['current_question'] ?? 1,
            'total_questions' => $this->gameState['total_questions'] ?? 10,
            'match_complete' => $this->isMatchComplete(),
        ];
    }
}
