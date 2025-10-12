<?php

namespace App\Services;

use App\Models\User;
use App\Models\PlayerDivision;

class DivisionService
{
    const DIVISIONS = [
        'bronze' => ['min' => 0, 'max' => 99, 'name' => 'Bronze'],
        'argent' => ['min' => 100, 'max' => 199, 'name' => 'Argent'],
        'or' => ['min' => 200, 'max' => 299, 'name' => 'Or'],
        'platine' => ['min' => 300, 'max' => 399, 'name' => 'Platine'],
        'diamant' => ['min' => 400, 'max' => 499, 'name' => 'Diamant'],
        'legende' => ['min' => 500, 'max' => PHP_INT_MAX, 'name' => 'Légende'],
    ];

    public function getOrCreateDivision(User $user, string $mode): PlayerDivision
    {
        return PlayerDivision::firstOrCreate(
            ['user_id' => $user->id, 'mode' => $mode],
            ['division' => 'bronze', 'points' => 0, 'level' => 1]
        );
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
}
