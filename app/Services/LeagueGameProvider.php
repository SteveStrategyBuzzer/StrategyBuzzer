<?php

namespace App\Services;

use App\Models\User;

class LeagueGameProvider extends GameModeProvider
{
    protected string $mode = 'league_individual';
    protected array $opponentInfo;
    protected ?LeagueIndividualService $leagueService = null;
    protected ?LeagueIndividualFirestoreService $firestoreService = null;
    protected ?DivisionService $divisionService = null;
    
    public function __construct(User $player, array $gameState = [])
    {
        parent::__construct($player, $gameState);
        
        if (empty($this->gameState)) {
            $this->gameState = $this->initializeBaseState();
            $this->gameState['mode'] = 'league_individual';
        }
        
        $this->leagueService = app(LeagueIndividualService::class);
        $this->firestoreService = app(LeagueIndividualFirestoreService::class);
        $this->divisionService = app(DivisionService::class);
    }
    
    public function getMode(): string
    {
        return 'league_individual';
    }
    
    public function getOpponentType(): string
    {
        return 'human';
    }
    
    public function setOpponent(User $opponent): void
    {
        $leagueStats = $opponent->leagueIndividualStats ?? null;
        $division = $this->divisionService->getOrCreateDivision($opponent, 'league_individual');
        
        $this->opponentInfo = [
            'is_human' => true,
            'user_id' => $opponent->id,
            'name' => $opponent->name,
            'player_code' => $opponent->player_code,
            'avatar' => $opponent->profile_settings['avatar'] ?? 'default',
            'league_level' => $leagueStats ? $leagueStats->level : 1,
            'division' => $division['name'] ?? 'Bronze',
            'division_rank' => $division['rank'] ?? 0,
            'elo_rating' => $leagueStats ? $leagueStats->elo_rating : 1000,
            'season_wins' => $leagueStats ? $leagueStats->season_wins : 0,
        ];
        
        $this->gameState['opponent_id'] = $opponent->id;
        $this->gameState['opponent_info'] = $this->opponentInfo;
    }
    
    public function getOpponentInfo(): array
    {
        return $this->opponentInfo ?? [
            'is_human' => true,
            'user_id' => null,
            'name' => 'Adversaire Ligue',
            'avatar' => 'default',
            'league_level' => 1,
            'division' => 'Bronze',
            'elo_rating' => 1000,
        ];
    }
    
    public function handleBuzz(float $buzzTime): array
    {
        $matchId = $this->gameState['match_id'] ?? null;
        
        if ($matchId) {
            $this->firestoreService->recordBuzz($matchId, $this->player->id, $buzzTime);
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
    
    public function submitAnswer(int $answerId, bool $isCorrect, bool $timedOut = false, ?int $pointsValue = null): array
    {
        $matchId = $this->gameState['match_id'] ?? null;
        // Use client-side points_value (2/1/0) if provided, otherwise fallback to buzz_time calculation
        if ($timedOut) {
            $points = 0;
        } elseif ($pointsValue !== null) {
            $points = $isCorrect ? $pointsValue : -2;
        } else {
            $buzzTime = $this->gameState['player_buzz_time'] ?? 5.0;
            $points = $this->calculatePoints($isCorrect, $buzzTime);
        }
        
        if ($matchId) {
            $this->firestoreService->recordAnswer($matchId, $this->player->id, $answerId, $isCorrect, $points);
        }
        
        $this->gameState['player_score'] = ($this->gameState['player_score'] ?? 0) + $points;
        
        return [
            'success' => true,
            'is_correct' => $isCorrect,
            'points' => $points,
            'player_score' => $this->gameState['player_score'],
            'waiting_for_opponent' => true,
            'timed_out' => $timedOut,
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
            return -2;
        }
        
        if ($buzzTime < 2) {
            return 18;
        } elseif ($buzzTime < 4) {
            return 15;
        } elseif ($buzzTime < 6) {
            return 12;
        } else {
            return 10;
        }
    }
    
    public function getScoring(): array
    {
        return [
            'type' => 'elo_competitive',
            'points_correct_lightning' => 18,
            'points_correct_fast' => 15,
            'points_correct_medium' => 12,
            'points_correct_slow' => 10,
            'points_incorrect' => -2,
            'elo_k_factor' => 32,
            'season_points_win' => 50,
            'season_points_loss' => -30,
            'streak_bonus' => 10,
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
        
        $playerElo = $this->player->leagueIndividualStats->elo_rating ?? 1000;
        $opponentElo = $this->opponentInfo['elo_rating'] ?? 1000;
        
        $scoring = $this->getScoring();
        $kFactor = $scoring['elo_k_factor'];
        
        $expectedScore = 1 / (1 + pow(10, ($opponentElo - $playerElo) / 400));
        $actualScore = $playerWon ? 1 : 0;
        $eloChange = round($kFactor * ($actualScore - $expectedScore));
        
        $seasonPoints = $playerWon ? $scoring['season_points_win'] : $scoring['season_points_loss'];
        
        return [
            'player_won' => $playerWon,
            'player_rounds_won' => $playerRoundsWon,
            'opponent_rounds_won' => $opponentRoundsWon,
            'player_total_score' => $this->gameState['player_total_score'] ?? 0,
            'opponent_total_score' => $this->gameState['opponent_total_score'] ?? 0,
            'elo_change' => $eloChange,
            'new_elo' => $playerElo + $eloChange,
            'season_points_change' => $seasonPoints,
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
            $this->firestoreService->updateRoundResult($matchId, [
                'round' => $this->gameState['current_round'] ?? 1,
                'player_score' => $playerScore,
                'opponent_score' => $opponentScore,
                'round_winner' => $roundWinner,
            ]);
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
}
