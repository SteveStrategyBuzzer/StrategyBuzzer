<?php

namespace App\Services;

use App\Models\User;

class DuoGameProvider extends GameModeProvider
{
    protected string $mode = 'duo';
    protected array $opponentInfo;
    protected ?DuoFirestoreService $firestoreService = null;
    protected ?DivisionService $divisionService = null;
    
    public function __construct(User $player, array $gameState = [])
    {
        parent::__construct($player, $gameState);
        
        if (empty($this->gameState)) {
            $this->gameState = $this->initializeBaseState();
        }
        
        $this->firestoreService = app(DuoFirestoreService::class);
        $this->divisionService = app(DivisionService::class);
    }
    
    public function getMode(): string
    {
        return 'duo';
    }
    
    public function getOpponentType(): string
    {
        return 'human';
    }
    
    public function setOpponent(User $opponent): void
    {
        $duoStats = $opponent->duoStats;
        $division = $this->divisionService->getOrCreateDivision($opponent, 'duo');
        
        $this->opponentInfo = [
            'is_human' => true,
            'user_id' => $opponent->id,
            'name' => $opponent->name,
            'player_code' => $opponent->player_code,
            'avatar' => $opponent->profile_settings['avatar'] ?? 'default',
            'level' => $duoStats ? $duoStats->level : 1,
            'division' => $division['name'] ?? 'Bronze',
            'division_rank' => $division['rank'] ?? 0,
            'efficiency' => $duoStats ? round(($duoStats->correct_answers / max($duoStats->total_answers, 1)) * 100, 1) : 0,
        ];
        
        $this->gameState['opponent_id'] = $opponent->id;
        $this->gameState['opponent_info'] = $this->opponentInfo;
    }
    
    public function getOpponentInfo(): array
    {
        return $this->opponentInfo ?? [
            'is_human' => true,
            'user_id' => null,
            'name' => 'Adversaire',
            'avatar' => 'default',
            'level' => 1,
            'division' => 'Bronze',
        ];
    }
    
    public function handleBuzz(float $buzzTime): array
    {
        $matchId = $this->gameState['match_id'] ?? null;
        
        if ($matchId) {
            $this->firestoreService->recordBuzz((int)$matchId, (string)$this->player->id, microtime(true));
        }
        
        $this->gameState['player_buzzed'] = true;
        $this->gameState['player_buzz_time'] = $buzzTime;
        
        return [
            'success' => true,
            'player_buzzed' => true,
            'buzz_time' => $buzzTime,
            'waiting_for_opponent' => true,
        ];
    }
    
    public function handleOpponentBuzz(): array
    {
        return [
            'opponent_buzzed' => $this->gameState['opponent_buzzed'] ?? false,
            'opponent_buzz_time' => $this->gameState['opponent_buzz_time'] ?? null,
        ];
    }
    
    public function submitAnswer(int $answerId, bool $isCorrect): array
    {
        $matchId = $this->gameState['match_id'] ?? null;
        $buzzTime = $this->gameState['player_buzz_time'] ?? 5.0;
        $points = $this->calculatePoints($isCorrect, $buzzTime);
        
        if ($matchId) {
            $player1Score = $this->gameState['player_score'] ?? 0;
            $player2Score = $this->gameState['opponent_score'] ?? 0;
            $this->firestoreService->updateScores((int)$matchId, $player1Score + $points, $player2Score);
        }
        
        $this->gameState['player_score'] = ($this->gameState['player_score'] ?? 0) + $points;
        
        return [
            'success' => true,
            'is_correct' => $isCorrect,
            'points' => $points,
            'player_score' => $this->gameState['player_score'],
            'waiting_for_opponent' => true,
        ];
    }
    
    public function handleOpponentAnswer(): array
    {
        return [
            'opponent_answered' => $this->gameState['opponent_answered'] ?? false,
            'opponent_correct' => $this->gameState['opponent_correct'] ?? false,
            'opponent_points' => $this->gameState['opponent_points'] ?? 0,
            'opponent_score' => $this->gameState['opponent_score'] ?? 0,
        ];
    }
    
    public function calculatePoints(bool $isCorrect, float $buzzTime): int
    {
        if (!$isCorrect) {
            return -5;
        }
        
        if ($buzzTime < 3) {
            return 15;
        } elseif ($buzzTime < 5) {
            return 12;
        } elseif ($buzzTime < 8) {
            return 10;
        } else {
            return 8;
        }
    }
    
    public function getScoring(): array
    {
        return [
            'type' => 'elo',
            'points_correct_fast' => 15,
            'points_correct_medium' => 12,
            'points_correct_slow' => 10,
            'points_correct_min' => 8,
            'points_incorrect' => -5,
            'elo_win_base' => 25,
            'elo_loss_base' => -20,
            'division_points_win' => 30,
            'division_points_loss' => -20,
        ];
    }
    
    public function isMatchComplete(): bool
    {
        $playerRoundsWon = $this->gameState['player_rounds_won'] ?? 0;
        $opponentRoundsWon = $this->gameState['opponent_rounds_won'] ?? 0;
        
        return $playerRoundsWon >= 2 || $opponentRoundsWon >= 2;
    }
    
    public function getMatchResult(): array
    {
        $playerRoundsWon = $this->gameState['player_rounds_won'] ?? 0;
        $opponentRoundsWon = $this->gameState['opponent_rounds_won'] ?? 0;
        $playerWon = $playerRoundsWon >= 2;
        
        $scoring = $this->getScoring();
        
        $eloChange = $playerWon ? $scoring['elo_win_base'] : $scoring['elo_loss_base'];
        $divisionPoints = $playerWon ? $scoring['division_points_win'] : $scoring['division_points_loss'];
        
        return [
            'player_won' => $playerWon,
            'player_rounds_won' => $playerRoundsWon,
            'opponent_rounds_won' => $opponentRoundsWon,
            'player_total_score' => $this->gameState['player_total_score'] ?? 0,
            'opponent_total_score' => $this->gameState['opponent_total_score'] ?? 0,
            'elo_change' => $eloChange,
            'division_points_change' => $divisionPoints,
            'opponent_info' => $this->opponentInfo,
        ];
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
    
    public function updateFromFirebase(array $firestoreState): void
    {
        if (isset($firestoreState['opponent_buzzed'])) {
            $this->gameState['opponent_buzzed'] = $firestoreState['opponent_buzzed'];
        }
        if (isset($firestoreState['opponent_buzz_time'])) {
            $this->gameState['opponent_buzz_time'] = $firestoreState['opponent_buzz_time'];
        }
        if (isset($firestoreState['opponent_answered'])) {
            $this->gameState['opponent_answered'] = $firestoreState['opponent_answered'];
        }
        if (isset($firestoreState['opponent_correct'])) {
            $this->gameState['opponent_correct'] = $firestoreState['opponent_correct'];
        }
        if (isset($firestoreState['opponent_points'])) {
            $this->gameState['opponent_points'] = $firestoreState['opponent_points'];
        }
        if (isset($firestoreState['opponent_score'])) {
            $this->gameState['opponent_score'] = $firestoreState['opponent_score'];
        }
    }
    
    public function finishRound(): array
    {
        $playerScore = $this->gameState['player_score'] ?? 0;
        $opponentScore = $this->gameState['opponent_score'] ?? 0;
        
        $this->gameState['player_total_score'] = ($this->gameState['player_total_score'] ?? 0) + $playerScore;
        $this->gameState['opponent_total_score'] = ($this->gameState['opponent_total_score'] ?? 0) + $opponentScore;
        
        if ($playerScore > $opponentScore) {
            $this->gameState['player_rounds_won'] = ($this->gameState['player_rounds_won'] ?? 0) + 1;
            $roundWinner = 'player';
        } elseif ($opponentScore > $playerScore) {
            $this->gameState['opponent_rounds_won'] = ($this->gameState['opponent_rounds_won'] ?? 0) + 1;
            $roundWinner = 'opponent';
        } else {
            $roundWinner = 'tie';
        }
        
        $matchId = $this->gameState['match_id'] ?? null;
        if ($matchId) {
            $this->firestoreService->finishRound(
                (int)$matchId, 
                ($this->gameState['current_round'] ?? 1) + 1,
                $this->gameState['player_rounds_won'] ?? 0,
                $this->gameState['opponent_rounds_won'] ?? 0
            );
        }
        
        return [
            'round' => $this->gameState['current_round'] ?? 1,
            'player_score' => $playerScore,
            'opponent_score' => $opponentScore,
            'round_winner' => $roundWinner,
            'player_rounds_won' => $this->gameState['player_rounds_won'] ?? 0,
            'opponent_rounds_won' => $this->gameState['opponent_rounds_won'] ?? 0,
            'match_complete' => $this->isMatchComplete(),
        ];
    }
    
    public function getFirestoreMatchPath(): ?string
    {
        $matchId = $this->gameState['match_id'] ?? null;
        return $matchId ? "duoMatches/{$matchId}" : null;
    }
}
