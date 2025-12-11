<?php

namespace App\Services;

use App\Models\User;
use App\Models\PlayerDivision;

class DivisionService
{
    const DIVISIONS = [
        'bronze' => ['min' => 0, 'max' => 99, 'name' => 'Bronze', 'coins' => 10],
        'argent' => ['min' => 100, 'max' => 199, 'name' => 'Argent', 'coins' => 30],
        'or' => ['min' => 200, 'max' => 299, 'name' => 'Or', 'coins' => 70],
        'platine' => ['min' => 300, 'max' => 399, 'name' => 'Platine', 'coins' => 150],
        'diamant' => ['min' => 400, 'max' => 499, 'name' => 'Diamant', 'coins' => 310],
        'legende' => ['min' => 500, 'max' => PHP_INT_MAX, 'name' => 'Légende', 'coins' => 630],
    ];
    
    const TEMP_ACCESS_MULTIPLIER = 2;
    const TEMP_ACCESS_DURATION_HOURS = 6;

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
            'division' => 'bronze',
            'points' => 0,
            'level' => 1,
            'initial_efficiency' => $initialEfficiency ?? 0,
        ]);
    }

    public function calculatePoints(int $myLevel, int $opponentLevel, bool $won): int
    {
        if (!$won) {
            return -2; // Pénalité défaite
        }

        $levelDiff = $opponentLevel - $myLevel;

        if ($levelDiff >= 1) {
            return 5; // Victoire vs supérieur (n'importe quel niveau plus élevé)
        } elseif ($levelDiff <= -1) {
            return 1; // Victoire vs inférieur (n'importe quel niveau plus bas)
        } else {
            return 2; // Victoire vs même niveau (±0)
        }
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
        $division->division = $this->calculateDivisionFromPoints($division->points);
        $division->save();

        return $division;
    }

    public function calculateDivisionFromPoints(int $points): string
    {
        foreach (self::DIVISIONS as $key => $range) {
            if ($points >= $range['min'] && $points <= $range['max']) {
                return $key;
            }
        }
        return 'bronze';
    }

    public function getDivisionName(string $division): string
    {
        return self::DIVISIONS[$division]['name'] ?? 'Bronze';
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
        return self::DIVISIONS[$division]['coins'] ?? 10;
    }
    
    public function getTemporaryAccessCost(string $targetDivision): int
    {
        $coins = self::DIVISIONS[$targetDivision]['coins'] ?? 10;
        return $coins * self::TEMP_ACCESS_MULTIPLIER;
    }
    
    public function calculateVictoryReward(string $myDivision, string $opponentDivision, bool $won): array
    {
        if (!$won) {
            return ['coins' => 0, 'bonus' => 0, 'multiplier' => 0];
        }
        
        $baseCoins = $this->getVictoryCoins($myDivision);
        $divisionOrder = array_keys(self::DIVISIONS);
        $myIndex = array_search($myDivision, $divisionOrder);
        $oppIndex = array_search($opponentDivision, $divisionOrder);
        
        $multiplier = 1.0;
        $bonus = 0;
        
        if ($oppIndex > $myIndex) {
            $multiplier = 1.5;
            $bonus = (int) ceil($baseCoins * 0.5);
        } elseif ($oppIndex < $myIndex) {
            $multiplier = 0.5;
            $bonus = -(int) ceil($baseCoins * 0.5);
        }
        
        $totalCoins = (int) ceil($baseCoins * $multiplier);
        
        return [
            'coins' => $totalCoins,
            'base' => $baseCoins,
            'bonus' => $bonus,
            'multiplier' => $multiplier,
        ];
    }
    
    public function getNextDivision(string $currentDivision): ?string
    {
        $divisions = array_keys(self::DIVISIONS);
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
}
