<?php

namespace App\Services\QuestionBank;

/**
 * Deterministic largest-remainder (Hamilton method) allocation of integer
 * quotas from percentage shares.
 *
 * The sum of allocations always equals the requested total. Ties on residual
 * fractions are broken by a stable priority order defined in config
 * (`question_bank_profiles.cognitive_priority_order`).
 */
class QuotaAllocator
{
    /**
     * Allocate `$total` units across the given percentage shares.
     *
     * @param  array<string,int|float>  $sharesPct  e.g. ['recognition' => 50, 'reasoning' => 20, 'deceptive_trap' => 30]
     * @param  int                      $total      Total units to distribute.
     * @param  array<string>            $tieOrder   Stable order for breaking ties (highest priority first).
     * @return array<string,int>                    Allocations summing to $total.
     */
    public static function allocate(array $sharesPct, int $total, array $tieOrder = []): array
    {
        if ($total <= 0 || empty($sharesPct)) {
            return array_map(fn () => 0, $sharesPct);
        }

        $sumShares = array_sum($sharesPct);
        if ($sumShares <= 0) {
            return array_map(fn () => 0, $sharesPct);
        }

        $raw = [];
        $floors = [];
        $remainders = [];
        foreach ($sharesPct as $key => $pct) {
            $rawValue = ($pct / $sumShares) * $total;
            $raw[$key] = $rawValue;
            $floors[$key] = (int) floor($rawValue);
            $remainders[$key] = $rawValue - $floors[$key];
        }

        $allocated = array_sum($floors);
        $remaining = $total - $allocated;

        if ($remaining > 0) {
            $priority = self::buildTieRank($tieOrder, array_keys($sharesPct));

            $keys = array_keys($remainders);
            usort($keys, function ($a, $b) use ($remainders, $priority) {
                if ($remainders[$a] === $remainders[$b]) {
                    return ($priority[$a] ?? PHP_INT_MAX) <=> ($priority[$b] ?? PHP_INT_MAX);
                }
                return $remainders[$b] <=> $remainders[$a];
            });

            foreach (array_slice($keys, 0, $remaining) as $key) {
                $floors[$key] += 1;
            }
        }

        return $floors;
    }

    /**
     * Allocate per-round counts so that summing across rounds equals the
     * global quota for that cognitive type. Each round receives a target
     * approximating quota / rounds and the remainder is distributed by
     * largest-remainder with the same tie order.
     *
     * @param  int            $globalQuota  Total units for this cognitive type across all rounds.
     * @param  int            $rounds       Number of rounds.
     * @return array<int,int>               1-indexed array roundNumber => count, summing to $globalQuota.
     */
    public static function allocatePerRound(int $globalQuota, int $rounds): array
    {
        if ($rounds <= 0) {
            return [];
        }
        if ($globalQuota <= 0) {
            return array_fill(1, $rounds, 0);
        }

        $base = intdiv($globalQuota, $rounds);
        $remainder = $globalQuota - ($base * $rounds);

        $perRound = [];
        for ($r = 1; $r <= $rounds; $r++) {
            $perRound[$r] = $base;
        }

        // Distribute the remainder to the first $remainder rounds (stable: round 1, 2, ...).
        for ($r = 1; $r <= $remainder; $r++) {
            $perRound[$r] += 1;
        }

        return $perRound;
    }

    /**
     * @param  array<string>  $tieOrder
     * @param  array<string>  $allKeys
     * @return array<string,int>  key => priority rank (lower = higher priority)
     */
    private static function buildTieRank(array $tieOrder, array $allKeys): array
    {
        $rank = [];
        foreach ($tieOrder as $i => $key) {
            $rank[$key] = $i;
        }
        $next = count($tieOrder);
        foreach ($allKeys as $key) {
            if (!isset($rank[$key])) {
                $rank[$key] = $next++;
            }
        }
        return $rank;
    }
}
