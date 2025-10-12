<?php

namespace App\Services;

use App\Models\User;
use App\Models\DuoMatch;
use App\Models\PlayerDuoStat;
use App\Models\PlayerDivision;
use Illuminate\Support\Collection;

class DuoMatchmakingService
{
    public function __construct(
        private DivisionService $divisionService
    ) {}

    public function createInvitation(User $inviter, int $invitedUserId): DuoMatch
    {
        $invited = User::findOrFail($invitedUserId);
        
        $inviterStats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $inviter->id],
            ['level' => 0]
        );
        
        $invitedStats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $invited->id],
            ['level' => 0]
        );

        return DuoMatch::create([
            'player1_id' => $inviter->id,
            'player2_id' => $invited->id,
            'status' => 'waiting',
            'match_type' => 'invitation',
            'player1_level' => $inviterStats->level,
            'player2_level' => $invitedStats->level,
        ]);
    }

    public function findRandomOpponent(User $player): ?User
    {
        $playerStats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $player->id],
            ['level' => 0]
        );

        $playerDivision = $this->divisionService->getOrCreateDivision($player, 'duo');

        $candidates = User::whereHas('playerDuoStat', function ($query) use ($playerStats) {
                $minLevel = max(1, $playerStats->level - 10);
                $maxLevel = $playerStats->level + 10;
                
                $query->whereBetween('level', [$minLevel, $maxLevel]);
            })
            ->whereHas('playerDivisions', function ($query) use ($playerDivision) {
                $query->where('mode', 'duo')
                    ->where('division', $playerDivision->division);
            })
            ->where('id', '!=', $player->id)
            ->whereDoesntHave('duoMatchesAsPlayer1', function ($query) {
                $query->whereIn('status', ['waiting', 'playing']);
            })
            ->whereDoesntHave('duoMatchesAsPlayer2', function ($query) {
                $query->whereIn('status', ['waiting', 'playing']);
            })
            ->with('playerDuoStat')
            ->get();

        if ($candidates->isEmpty()) {
            return $this->findOpponentAnyDivision($player, $playerStats);
        }

        return $candidates->random();
    }

    private function findOpponentAnyDivision(User $player, PlayerDuoStat $playerStats): ?User
    {
        $candidates = User::whereHas('playerDuoStat', function ($query) use ($playerStats) {
                $minLevel = max(1, $playerStats->level - 10);
                $maxLevel = $playerStats->level + 10;
                
                $query->whereBetween('level', [$minLevel, $maxLevel]);
            })
            ->where('id', '!=', $player->id)
            ->whereDoesntHave('duoMatchesAsPlayer1', function ($query) {
                $query->whereIn('status', ['waiting', 'playing']);
            })
            ->whereDoesntHave('duoMatchesAsPlayer2', function ($query) {
                $query->whereIn('status', ['waiting', 'playing']);
            })
            ->with('playerDuoStat')
            ->get();

        return $candidates->isNotEmpty() ? $candidates->random() : null;
    }

    public function createRandomMatch(User $player1, User $player2): DuoMatch
    {
        $player1Stats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $player1->id],
            ['level' => 0]
        );
        
        $player2Stats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $player2->id],
            ['level' => 0]
        );

        return DuoMatch::create([
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'status' => 'waiting',
            'match_type' => 'random',
            'player1_level' => $player1Stats->level,
            'player2_level' => $player2Stats->level,
        ]);
    }

    public function acceptMatch(DuoMatch $match): DuoMatch
    {
        $match->status = 'playing';
        $match->started_at = now();
        $match->save();

        return $match;
    }

    public function cancelMatch(DuoMatch $match): void
    {
        $match->status = 'cancelled';
        $match->save();
    }

    public function finishMatch(
        DuoMatch $match,
        int $player1Score,
        int $player2Score,
        array $gameState = []
    ): DuoMatch {
        if ($player1Score === $player2Score) {
            throw new \Exception('Tie games are not allowed. Match must have a clear winner.');
        }

        $winnerId = $player1Score > $player2Score ? $match->player1_id : $match->player2_id;
        
        $player1Won = $winnerId === $match->player1_id;
        $player2Won = $winnerId === $match->player2_id;

        $player1Points = $this->divisionService->calculatePoints(
            $match->player1_level,
            $match->player2_level,
            $player1Won
        );

        $player2Points = $this->divisionService->calculatePoints(
            $match->player2_level,
            $match->player1_level,
            $player2Won
        );

        $match->update([
            'status' => 'finished',
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
            'winner_id' => $winnerId,
            'player1_points_earned' => $player1Points,
            'player2_points_earned' => $player2Points,
            'game_state' => $gameState,
            'finished_at' => now(),
        ]);

        $player1Stats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $match->player1_id],
            ['level' => 0]
        );
        $player1Stats->updateAfterMatch($player1Won);

        $player2Stats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $match->player2_id],
            ['level' => 0]
        );
        $player2Stats->updateAfterMatch($player2Won);

        $this->divisionService->updateDivisionAfterMatch(
            $match->player1,
            'duo',
            $player1Points,
            $player1Stats->level
        );

        $this->divisionService->updateDivisionAfterMatch(
            $match->player2,
            'duo',
            $player2Points,
            $player2Stats->level
        );

        return $match;
    }
}
