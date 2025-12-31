<?php

namespace App\Services;

use App\Models\User;

class SoloGameProvider extends GameModeProvider
{
    protected string $mode = 'solo';
    protected array $opponentInfo;
    protected ?QuestionService $questionService = null;
    
    public function __construct(User $player, array $gameState = [])
    {
        parent::__construct($player, $gameState);
        
        if (empty($this->gameState)) {
            $this->gameState = $this->initializeBaseState();
        }
        
        $this->loadOpponentInfo();
    }
    
    public function getMode(): string
    {
        return 'solo';
    }
    
    public function getOpponentType(): string
    {
        return 'ai';
    }
    
    protected function loadOpponentInfo(): void
    {
        $niveau = $this->gameState['niveau'] ?? session('choix_niveau', 1);
        
        $isBoss = $niveau % 10 === 0 && $niveau > 0;
        
        if ($isBoss) {
            $bossIndex = (int)($niveau / 10) - 1;
            $bosses = $this->getBossList();
            $boss = $bosses[$bossIndex] ?? $bosses[0];
            
            $this->opponentInfo = [
                'is_boss' => true,
                'name' => $boss['name'],
                'avatar' => $boss['avatar'],
                'title' => $boss['title'],
                'level' => $niveau,
                'competencies' => $boss['competencies'] ?? [],
                'difficulty' => 'boss',
            ];
        } else {
            $studentNames = [
                'Hugo', 'Léa', 'Lucas', 'Emma', 'Nathan', 'Chloé', 
                'Louis', 'Jade', 'Arthur', 'Inès', 'Raphaël', 'Camille'
            ];
            $studentAvatars = [
                'student_boy_1', 'student_girl_1', 'student_boy_2', 'student_girl_2',
                'student_boy_3', 'student_girl_3', 'student_boy_4', 'student_girl_4'
            ];
            
            $nextBossLevel = (int)(ceil($niveau / 10) * 10);
            $nextBossIndex = (int)($nextBossLevel / 10) - 1;
            $bosses = $this->getBossList();
            $nextBoss = $bosses[$nextBossIndex] ?? $bosses[0];
            
            $age = 6 + min(12, (int)($niveau / 8));
            
            $this->opponentInfo = [
                'is_boss' => false,
                'name' => $studentNames[array_rand($studentNames)],
                'avatar' => $studentAvatars[array_rand($studentAvatars)],
                'age' => $age,
                'level' => $niveau,
                'next_boss' => $nextBoss['name'],
                'difficulty' => $this->calculateDifficulty($niveau),
            ];
        }
    }
    
    protected function getBossList(): array
    {
        return [
            ['name' => 'Le Stratège', 'avatar' => 'boss_stratege', 'title' => 'Maître de la tactique', 'competencies' => ['strategie' => 90, 'reflexion' => 85]],
            ['name' => 'La Savante', 'avatar' => 'boss_savante', 'title' => 'Génie des sciences', 'competencies' => ['sciences' => 95, 'logique' => 88]],
            ['name' => 'L\'Explorateur', 'avatar' => 'boss_explorateur', 'title' => 'Globe-trotter légendaire', 'competencies' => ['geographie' => 92, 'culture' => 80]],
            ['name' => 'L\'Historien', 'avatar' => 'boss_historien', 'title' => 'Gardien du passé', 'competencies' => ['histoire' => 94, 'memoire' => 87]],
            ['name' => 'Le Champion', 'avatar' => 'boss_champion', 'title' => 'Athlète suprême', 'competencies' => ['sport' => 93, 'rapidite' => 90]],
            ['name' => 'L\'Artiste', 'avatar' => 'boss_artiste', 'title' => 'Maître des arts', 'competencies' => ['art' => 91, 'creativite' => 89]],
            ['name' => 'Le Naturaliste', 'avatar' => 'boss_naturaliste', 'title' => 'Ami des animaux', 'competencies' => ['animaux' => 96, 'nature' => 84]],
            ['name' => 'Le Chef Étoilé', 'avatar' => 'boss_chef', 'title' => 'Maître cuisinier', 'competencies' => ['cuisine' => 94, 'gastronomie' => 88]],
            ['name' => 'Le Cinéphile', 'avatar' => 'boss_cinephile', 'title' => 'Expert du 7ème art', 'competencies' => ['cinema' => 95, 'culture_pop' => 86]],
            ['name' => 'Le Grand Maître', 'avatar' => 'boss_grand_maitre', 'title' => 'Boss final ultime', 'competencies' => ['all' => 97, 'speed' => 92]],
        ];
    }
    
    protected function calculateDifficulty(int $niveau): string
    {
        if ($niveau <= 10) return 'facile';
        if ($niveau <= 30) return 'moyen';
        if ($niveau <= 60) return 'difficile';
        return 'expert';
    }
    
    public function getOpponentInfo(): array
    {
        return $this->opponentInfo;
    }
    
    public function handleBuzz(float $buzzTime): array
    {
        $this->gameState['player_buzzed'] = true;
        $this->gameState['player_buzz_time'] = $buzzTime;
        
        return [
            'success' => true,
            'player_buzzed' => true,
            'buzz_time' => $buzzTime,
            'opponent_will_buzz' => false,
        ];
    }
    
    public function handleOpponentBuzz(): array
    {
        $niveau = $this->gameState['niveau'] ?? 1;
        $isBoss = $this->opponentInfo['is_boss'] ?? false;
        
        $baseBuzzChance = $isBoss ? 0.7 : 0.3;
        $levelModifier = min(0.4, $niveau * 0.005);
        $buzzChance = $baseBuzzChance + $levelModifier;
        
        $opponentBuzzes = (mt_rand(0, 100) / 100) < $buzzChance;
        
        if ($opponentBuzzes) {
            $baseSpeed = $isBoss ? 2.0 : 4.0;
            $speedVariation = mt_rand(0, 200) / 100;
            $opponentBuzzTime = max(1.5, $baseSpeed - ($niveau * 0.02) + $speedVariation);
            
            $this->gameState['opponent_buzzed'] = true;
            $this->gameState['opponent_buzz_time'] = $opponentBuzzTime;
            
            return [
                'opponent_buzzed' => true,
                'opponent_buzz_time' => $opponentBuzzTime,
            ];
        }
        
        return [
            'opponent_buzzed' => false,
            'opponent_buzz_time' => null,
        ];
    }
    
    public function submitAnswer(int $answerId, bool $isCorrect, bool $timedOut = false): array
    {
        $buzzTime = $this->gameState['player_buzz_time'] ?? 5.0;
        // Timeout = 0 points (no penalty for not buzzing), wrong answer = -5
        $points = $timedOut ? 0 : $this->calculatePoints($isCorrect, $buzzTime);
        
        $this->gameState['player_score'] = ($this->gameState['player_score'] ?? 0) + $points;
        
        return [
            'success' => true,
            'is_correct' => $isCorrect,
            'points' => $points,
            'player_score' => $this->gameState['player_score'],
            'timed_out' => $timedOut,
        ];
    }
    
    public function handleOpponentAnswer(): array
    {
        if (!($this->gameState['opponent_buzzed'] ?? false)) {
            return [
                'opponent_answered' => false,
                'opponent_correct' => false,
                'opponent_points' => 0,
            ];
        }
        
        $niveau = $this->gameState['niveau'] ?? 1;
        $isBoss = $this->opponentInfo['is_boss'] ?? false;
        
        $baseAccuracy = $isBoss ? 0.75 : 0.5;
        $levelModifier = min(0.35, $niveau * 0.004);
        $accuracy = $baseAccuracy + $levelModifier;
        
        $opponentCorrect = (mt_rand(0, 100) / 100) < $accuracy;
        
        $buzzTime = $this->gameState['opponent_buzz_time'] ?? 4.0;
        $opponentPoints = $this->calculatePoints($opponentCorrect, $buzzTime);
        
        $this->gameState['opponent_score'] = ($this->gameState['opponent_score'] ?? 0) + $opponentPoints;
        
        return [
            'opponent_answered' => true,
            'opponent_correct' => $opponentCorrect,
            'opponent_points' => $opponentPoints,
            'opponent_score' => $this->gameState['opponent_score'],
        ];
    }
    
    public function calculatePoints(bool $isCorrect, float $buzzTime): int
    {
        if (!$isCorrect) {
            return -2;
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
            'type' => 'progression',
            'points_correct_fast' => 15,
            'points_correct_medium' => 12,
            'points_correct_slow' => 10,
            'points_correct_min' => 8,
            'points_incorrect' => -2,
            'round_win_bonus' => 50,
            'match_win_bonus' => 100,
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
        
        $result = [
            'player_won' => $playerWon,
            'player_rounds_won' => $playerRoundsWon,
            'opponent_rounds_won' => $opponentRoundsWon,
            'player_total_score' => $this->gameState['player_total_score'] ?? 0,
            'opponent_total_score' => $this->gameState['opponent_total_score'] ?? 0,
        ];
        
        if ($playerWon) {
            $currentLevel = $this->gameState['niveau'] ?? 1;
            if ($currentLevel < 100) {
                $result['level_up'] = true;
                $result['new_level'] = $currentLevel + 1;
            }
        }
        
        return $result;
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
    
    public function startNewRound(): void
    {
        $this->gameState['current_round'] = ($this->gameState['current_round'] ?? 1) + 1;
        $this->gameState['current_question'] = 1;
        $this->gameState['player_score'] = 0;
        $this->gameState['opponent_score'] = 0;
        $this->gameState['player_buzzed'] = false;
        $this->gameState['opponent_buzzed'] = false;
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
