<?php

namespace App\Services;

/**
 * Largest-Remainder (Hamilton) allocator pour convertir des pourcentages en
 * quotas entiers de manière déterministe.
 *
 * 1. quotas bruts = pct * total / 100
 * 2. parties entières
 * 3. reste = total − Σ parties entières
 * 4. distribuer reste +1 aux quotas avec les plus grandes fractions résiduelles
 * 5. en cas d'égalité, ordre stable défini en config
 *    (recognition > deceptive_trap > reasoning par défaut).
 */
class QuotaAllocator
{
    /**
     * @param array<string,int|float> $weights      code → poids/pourcentage (peuvent ne pas sommer à 100)
     * @param int                     $total        total à répartir en entiers
     * @param array<int,string>       $stableOrder  ordre stable pour départager les fractions égales
     *
     * @return array<string,int> code → quota entier, somme = $total
     */
    public static function allocate(array $weights, int $total, array $stableOrder = []): array
    {
        if ($total <= 0 || empty($weights)) {
            return array_map(fn () => 0, $weights);
        }

        $sum = array_sum($weights);
        if ($sum <= 0) {
            // Tous les poids sont nuls → on ne peut rien répartir intelligemment.
            return array_map(fn () => 0, $weights);
        }

        $allocations = [];
        $fractions = [];
        $allocatedSum = 0;

        foreach ($weights as $code => $weight) {
            $exact = ($weight / $sum) * $total;
            $floor = (int) floor($exact);
            $allocations[$code] = $floor;
            $fractions[$code] = $exact - $floor;
            $allocatedSum += $floor;
        }

        $remaining = $total - $allocatedSum;
        if ($remaining <= 0) {
            return $allocations;
        }

        // Index pour ordre stable : code → priorité (plus petit = prioritaire).
        $stableIndex = [];
        foreach ($stableOrder as $i => $code) {
            $stableIndex[$code] = $i;
        }
        $defaultIndex = count($stableOrder);

        $codes = array_keys($weights);
        usort($codes, function ($a, $b) use ($fractions, $stableIndex, $defaultIndex) {
            if ($fractions[$a] === $fractions[$b]) {
                $ia = $stableIndex[$a] ?? $defaultIndex;
                $ib = $stableIndex[$b] ?? $defaultIndex;
                if ($ia === $ib) {
                    return strcmp((string) $a, (string) $b);
                }
                return $ia <=> $ib;
            }
            return $fractions[$b] <=> $fractions[$a];
        });

        foreach ($codes as $code) {
            if ($remaining <= 0) {
                break;
            }
            $allocations[$code]++;
            $remaining--;
        }

        return $allocations;
    }
}
