<?php

namespace App\Services;

use App\Models\User;

abstract class GameModeProvider
{
    protected User $player;
    protected array $gameState;
    protected string $mode;

    public function __construct(User $player, array $gameState = [])
    {
        $this->player = $player;
        $this->gameState = $gameState;
    }

    abstract public function getMode(): string;

    abstract public function getOpponentType(): string;

    abstract public function getOpponentInfo(): array;

    abstract public function handleBuzz(float $buzzTime): array;

    abstract public function handleOpponentBuzz(): array;

    abstract public function submitAnswer(int $answerId, bool $isCorrect, bool $timedOut = false, ?int $pointsValue = null): array;

    abstract public function handleOpponentAnswer(): array;

    abstract public function calculatePoints(bool $isCorrect, float $buzzTime): int;

    abstract public function getScoring(): array;

    abstract public function isMatchComplete(): bool;

    abstract public function getMatchResult(): array;

    abstract public function getNextQuestion(): ?array;

    public function getGameState(): array
    {
        return $this->gameState;
    }

    public function setGameState(array $state): void
    {
        $this->gameState = $state;
    }

    public function getPlayer(): User
    {
        return $this->player;
    }

    protected function initializeBaseState(): array
    {
        return [
            'mode' => $this->getMode(),
            'player_id' => $this->player->id,
            'player_score' => 0,
            'opponent_score' => 0,
            'current_question' => 1,
            'total_questions' => 10,
            'player_rounds_won' => 0,
            'opponent_rounds_won' => 0,
            'current_round' => 1,
            'max_rounds' => 3,
            'started_at' => now()->toISOString(),
        ];
    }

    public static function create(string $mode, User $player, array $gameState = []): GameModeProvider
    {
        return match ($mode) {
            'solo' => new SoloGameProvider($player, $gameState),
            'duo' => new DuoGameProvider($player, $gameState),
            'league_individual' => new LeagueGameProvider($player, $gameState),
            'master' => new MasterGameProvider($player, $gameState),
            default => throw new \InvalidArgumentException("Unknown game mode: {$mode}"),
        };
    }
}
