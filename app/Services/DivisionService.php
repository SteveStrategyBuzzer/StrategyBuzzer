<?php

namespace App\Services;

use App\Models\User;
use App\Models\PlayerDivision;
use Illuminate\Support\Facades\DB;

class DivisionService
{
    public function __construct(
        private CoinLedgerService $coinLedgerService
    ) {}

    const DIVISIONS = [
        'bronze' => ['min' => 0, 'max' => 99, 'name' => 'Bronze', 'coins' => 10],
        'argent' => ['min' => 100, 'max' => 199, 'name' => 'Argent', 'coins' => 30],
        'or' => ['min' => 200, 'max' => 299, 'name' => 'Or', 'coins' => 70],
        'platine' => ['min' => 300, 'max' => 399, 'name' => 'Platine', 'coins' => 150],
        'diamant' => ['min' => 400, 'max' => 499, 'name' => 'Diamant', 'coins' => 310],
        'legende' => ['min' => 500, 'max' => PHP_INT_MAX, 'name' => 'Légende', 'coins' => 630],
    ];

    const DIVISIONS_DUO = [
        'novice'        => ['min' => 0,   'max' => 99,           'name' => 'Novice',        'coins' => 10],
        'intermediaire' => ['min' => 100, 'max' => 199,          'name' => 'Intermédiaire', 'coins' => 30],
        'expert'        => ['min' => 200, 'max' => PHP_INT_MAX,  'name' => 'Expert',        'coins' => 70],
    ];

    private function getDivisionsForMode(string $mode): array
    {
        return $mode === 'duo' ? self::DIVISIONS_DUO : self::DIVISIONS;
    }

    private function allDivisionsMap(): array
    {
        return self::DIVISIONS + self::DIVISIONS_DUO;
    }

    const TEMP_ACCESS_MULTIPLIER = 2;
    const TEMP_ACCESS_DURATION_HOURS = 6;
    const EFFICIENCY_THRESHOLD_PERCENT = 15;

    public function getOrCreateDivision(User $user, string $mode, ?float $initialEfficiency = null): PlayerDivision
    {
        $existing = PlayerDivision::where('user_id', $user->id)
            ->where('mode', $mode)
            ->first();

        if ($existing) {
            return $existing;
        }

        return PlayerDivision::create([
            'user_id' => $user->id,
            'mode' => $mode,
            'division' => array_key_first($this->getDivisionsForMode($mode)),
            'points' => 0,
            'level' => 1,
            'initial_efficiency' => $initialEfficiency ?? 0,
        ]);
    }

    public function calculatePoints(string $strength, bool $won): int
    {
        if (!$won) {
            return -5;
        }

        switch ($strength) {
            case 'stronger':
                return 10;
            case 'same':
                return 5;
            case 'weaker':
                return 2;
            default:
                return 5;
        }
    }

    public function determineOpponentStrength(
        string $myDivision,
        string $opponentDivision,
        float $myEfficiency,
        float $opponentEfficiency,
        bool $isTemporaryAccess = false,
        string $mode = 'ligue'
    ): string {
        if ($isTemporaryAccess) {
            return 'stronger';
        }

        $divisionOrder = array_keys($this->getDivisionsForMode($mode));
        $myIndex = array_search($myDivision, $divisionOrder);
        $oppIndex = array_search($opponentDivision, $divisionOrder);

        if ($oppIndex > $myIndex) {
            return 'stronger';
        } elseif ($oppIndex < $myIndex) {
            return 'weaker';
        }

        $thresholdPercent = self::EFFICIENCY_THRESHOLD_PERCENT / 100;
        $threshold = $myEfficiency * $thresholdPercent;

        if ($opponentEfficiency > $myEfficiency + $threshold) {
            return 'stronger';
        } elseif ($opponentEfficiency < $myEfficiency - $threshold) {
            return 'weaker';
        }

        return 'same';
    }

    public function updateDivisionAfterMatch(
        User $user,
        string $mode,
        int $pointsEarned,
        int $newLevel
    ): PlayerDivision {
        $division = $this->getOrCreateDivision($user, $mode);

        $division->points = max(0, $division->points + $pointsEarned);
        $division->level = $newLevel;
        $division->division = $this->calculateDivisionFromPoints($division->points, $mode);
        $division->save();

        return $division;
    }

    public function calculateDivisionFromPoints(int $points, string $mode = 'ligue'): string
    {
        $divisions = $this->getDivisionsForMode($mode);
        foreach ($divisions as $key => $range) {
            if ($points >= $range['min'] && $points <= $range['max']) {
                return $key;
            }
        }
        return array_key_first($divisions);
    }

    public function getDivisionName(string $division): string
    {
        $all = $this->allDivisionsMap();
        return $all[$division]['name'] ?? ucfirst($division);
    }

    public function getRankingsForDivision(string $mode, string $division, int $limit = 100): array
    {
        return PlayerDivision::where('mode', $mode)
            ->where('division', $division)
            ->orderByDesc('level')
            ->orderByDesc('points')
            ->orderBy('created_at')
            ->limit($limit)
            ->with('user')
            ->get()
            ->map(function ($division, $index) {
                $division->rank = $index + 1;
                $division->save();
                return $division;
            })
            ->toArray();
    }

    public function getPlayerRank(User $user, string $mode): ?int
    {
        $division = $this->getOrCreateDivision($user, $mode);

        $rank = PlayerDivision::where('mode', $mode)
            ->where('division', $division->division)
            ->where(function ($query) use ($division) {
                $query->where('level', '>', $division->level)
                    ->orWhere(function ($q) use ($division) {
                        $q->where('level', $division->level)
                          ->where('points', '>', $division->points);
                    })
                    ->orWhere(function ($q) use ($division) {
                        $q->where('level', $division->level)
                          ->where('points', $division->points)
                          ->where('created_at', '<', $division->created_at);
                    });
            })
            ->count();

        return $rank + 1;
    }

    public function getVictoryCoins(string $division): int
    {
        $all = $this->allDivisionsMap();
        return $all[$division]['coins'] ?? 10;
    }

    public function getTemporaryAccessCost(string $targetDivision): int
    {
        $all = $this->allDivisionsMap();
        $coins = $all[$targetDivision]['coins'] ?? 10;
        return $coins * self::TEMP_ACCESS_MULTIPLIER;
    }

    public function calculateVictoryReward(
        string $playingDivision,
        string $strength,
        bool $won,
        bool $isTemporaryAccess = false
    ): array {
        if (!$won) {
            return ['coins' => 0, 'base' => 0, 'bonus' => 0, 'multiplier' => 0, 'strength' => $strength];
        }

        $baseCoins = $this->getVictoryCoins($playingDivision);

        $multiplier = 1.0;
        $bonus = 0;

        if ($isTemporaryAccess || $strength === 'stronger') {
            $multiplier = 1.5;
            $bonus = (int) ceil($baseCoins * 0.5);
        } elseif ($strength === 'weaker') {
            $multiplier = 0.5;
            $bonus = -(int) ceil($baseCoins * 0.5);
        }

        $totalCoins = (int) ceil($baseCoins * $multiplier);

        return [
            'coins' => $totalCoins,
            'base' => $baseCoins,
            'bonus' => $bonus,
            'multiplier' => $multiplier,
            'strength' => $strength,
        ];
    }

    public function getNextDivision(string $currentDivision, string $mode = 'ligue'): ?string
    {
        $divisions = array_keys($this->getDivisionsForMode($mode));
        $currentIndex = array_search($currentDivision, $divisions);

        if ($currentIndex === false || $currentIndex >= count($divisions) - 1) {
            return null;
        }

        return $divisions[$currentIndex + 1];
    }

    public function canPurchaseTemporaryAccess(User $user, string $targetDivision): bool
    {
        $cost = $this->getTemporaryAccessCost($targetDivision);
        return ($user->coins ?? 0) >= $cost;
    }

    public function purchaseTemporaryAccess(User $user, string $targetDivision): array
    {
        $cost = $this->getTemporaryAccessCost($targetDivision);

        if (($user->coins ?? 0) < $cost) {
            return ['success' => false, 'error' => 'Pas assez de pièces'];
        }

        DB::transaction(function () use ($user, $targetDivision, $cost) {
            $user->refresh();

            if (($user->coins ?? 0) < $cost) {
                throw new \RuntimeException('INSUFFICIENT_COINS');
            }

            $this->coinLedgerService->debit(
                $user,
                $cost,
                'division_temp_access:' . $targetDivision,
                null,
                null,
                'intelligence'
            );

            $user->temp_access_division = $targetDivision;
            $user->temp_access_expires_at = now()->addHours(self::TEMP_ACCESS_DURATION_HOURS);
            $user->save();
        });

        $user->refresh();

        return [
            'success' => true,
            'division' => $targetDivision,
            'expires_at' => $user->temp_access_expires_at,
            'cost' => $cost,
            'remaining_coins' => $user->coins,
        ];
    }

    public function hasActiveTemporaryAccess(User $user, string $division): bool
    {
        if (!$user->temp_access_division || !$user->temp_access_expires_at) {
            return false;
        }

        return $user->temp_access_division === $division &&
               $user->temp_access_expires_at->isFuture();
    }

    public function hasTemporaryAccessOrOngoingMatch(User $user, string $division): bool
    {
        if ($this->hasActiveTemporaryAccess($user, $division)) {
            return true;
        }

        if ($user->current_match_id && $user->temp_access_division === $division) {
            return true;
        }

        return false;
    }

    public function canStartMatchInDivision(User $user, string $targetDivision, string $userDivision): bool
    {
        if ($targetDivision === $userDivision) {
            return true;
        }

        return $this->hasActiveTemporaryAccess($user, $targetDivision);
    }

    public function startMatchWithTemporaryAccess(User $user, string $matchId): void
    {
        $user->current_match_id = $matchId;
        $user->match_started_at = now();
        $user->save();
    }

    public function canFinishCurrentMatch(User $user): bool
    {
        return $user->current_match_id !== null;
    }

    public function clearCurrentMatch(User $user): void
    {
        $user->current_match_id = null;
        $user->match_started_at = null;
        $user->save();
    }

    public function getTemporaryAccessInfo(User $user): ?array
    {
        if (!$user->temp_access_division || !$user->temp_access_expires_at) {
            return null;
        }

        $isActive = $user->temp_access_expires_at->isFuture();
        $remainingMinutes = $isActive ? now()->diffInMinutes($user->temp_access_expires_at) : 0;

        return [
            'division' => $user->temp_access_division,
            'division_name' => $this->getDivisionName($user->temp_access_division),
            'expires_at' => $user->temp_access_expires_at,
            'is_active' => $isActive,
            'remaining_minutes' => $remainingMinutes,
            'remaining_hours' => round($remainingMinutes / 60, 1),
        ];
    }

    public function getMinPointsForDivision(string $division): int
    {
        $all = $this->allDivisionsMap();
        return $all[$division]['min'] ?? 0;
    }

    public function updateDivisionPointsWithFloor(PlayerDivision $playerDivision, int $pointsChange): PlayerDivision
    {
        $currentDivisionMin = $this->getMinPointsForDivision($playerDivision->division);
        $newPoints = $playerDivision->points + $pointsChange;

        if ($pointsChange < 0) {
            $playerDivision->points = max($currentDivisionMin, $newPoints);
        } else {
            $playerDivision->points = max(0, $newPoints);
        }

        $playerDivision->division = $this->calculateDivisionFromPoints($playerDivision->points, $playerDivision->mode ?? 'ligue');
        $playerDivision->save();

        return $playerDivision;
    }

    public function demotePlayer(PlayerDivision $playerDivision): PlayerDivision
    {
        $divisionsConfig = $this->getDivisionsForMode($playerDivision->mode ?? 'ligue');
        $divisionOrder = array_keys($divisionsConfig);
        $currentIndex = array_search($playerDivision->division, $divisionOrder);

        if ($currentIndex === false || $currentIndex <= 0) {
            return $playerDivision;
        }

        $newDivision = $divisionOrder[$currentIndex - 1];
        $playerDivision->division = $newDivision;
        $playerDivision->points = $divisionsConfig[$newDivision]['max'];
        $playerDivision->save();

        return $playerDivision;
    }
}
