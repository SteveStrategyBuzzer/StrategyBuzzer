<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DepthNeedMatrix — service de lecture / écriture des besoins par Depth.
 *
 * Responsabilités (DEC-060) :
 *   - Exposer le DepthCycle officiel [2, 4, 6, 7, 8, 9, 10]
 *   - Exposer les cibles de Tours par Depth (cycle_target — constantes)
 *   - Lire / incrémenter cycle_completed par Depth (kernel_depth_matrix)
 *   - Lire / incrémenter kernel_received_total par Depth × Domaine (kernel_depth_domain_totals)
 *   - Retourner le prochain Depth requis dans le cycle
 *   - Initialiser les totaux depuis ReadyBank au démarrage
 *
 * Interdictions :
 *   - Ne porte pas les états ON/OFF des Domaines (→ DepthTourState)
 *   - Ne prend aucune décision de rotation
 *   - Ne connaît pas les Blueprints
 */
final class DepthNeedMatrix
{
    private const DEPTH_MATRIX_TABLE = 'kernel_depth_matrix';
    private const DEPTH_TOTALS_TABLE = 'kernel_depth_domain_totals';

    /**
     * Ordre officiel du DepthCycle (DEC-065).
     * Après Depth 10 : reprend à Depth 2.
     */
    public const DEPTH_CYCLE = [2, 4, 6, 7, 8, 9, 10];

    /**
     * Cibles officielles de Tours par Depth (constantes — non persistées).
     *
     * @var array<int, int>
     */
    public const CYCLE_TARGET = [
        2  => 250,
        4  => 300,
        6  => 350,
        7  => 350,
        8  => 350,
        9  => 250,
        10 => 100,
    ];

    // =========================================================================
    // Sélection du prochain Depth
    // =========================================================================

    /**
     * Retourne le prochain Depth requis après $afterDepth dans le DepthCycle.
     *
     * Règles :
     *   - Si $afterDepth est null : commence au début du cycle (Depth 2).
     *   - Cherche, en ordre cyclique, le premier Depth tel que
     *     cycle_completed[depth] < cycle_target[depth].
     *   - Après Depth 10 : reprend à Depth 2.
     *   - Retourne null si tous les Depths ont atteint leur cycle_target
     *     (état PRODUCTION_ON_HOLD).
     *
     * @throws RuntimeException si les lignes kernel_depth_matrix sont absentes.
     */
    public function nextRequiredDepth(?int $afterDepth): ?int
    {
        $rows = DB::table(self::DEPTH_MATRIX_TABLE)
            ->whereIn('depth', self::DEPTH_CYCLE)
            ->get()
            ->keyBy('depth');

        $cycle = self::DEPTH_CYCLE;
        $count = count($cycle);

        if ($afterDepth === null) {
            // Commence au début du cycle
            foreach ($cycle as $depth) {
                $row       = $rows->get($depth);
                $completed = $row ? (int) $row->cycle_completed : 0;

                if ($completed < self::CYCLE_TARGET[$depth]) {
                    return $depth;
                }
            }

            return null;
        }

        $startIndex = array_search($afterDepth, $cycle, true);

        // Si $afterDepth inconnu, traiter comme si on partait du début
        if ($startIndex === false) {
            $startIndex = -1;
        }

        for ($offset = 1; $offset <= $count; $offset++) {
            $depth = $cycle[($startIndex + $offset) % $count];
            $row   = $rows->get($depth);

            $completed = $row ? (int) $row->cycle_completed : 0;

            if ($completed < self::CYCLE_TARGET[$depth]) {
                return $depth;
            }
        }

        return null;
    }

    // =========================================================================
    // cycle_completed
    // =========================================================================

    /**
     * Retourne cycle_completed pour un Depth (0 si la ligne n'existe pas).
     */
    public function getCycleCompleted(int $depth): int
    {
        $row = DB::table(self::DEPTH_MATRIX_TABLE)
            ->where('depth', $depth)
            ->first();

        return $row ? (int) $row->cycle_completed : 0;
    }

    /**
     * Incrémente cycle_completed[depth] de 1.
     *
     * Appelé par KRP à la fermeture d'un Tour (8/8).
     */
    public function incrementCycleCompleted(int $depth): void
    {
        DB::table(self::DEPTH_MATRIX_TABLE)
            ->where('depth', $depth)
            ->increment('cycle_completed');
    }

    // =========================================================================
    // kernel_received_total
    // =========================================================================

    /**
     * Retourne kernel_received_total pour un couple depth × domain_code.
     */
    public function getKernelReceivedTotal(int $depth, string $domain): int
    {
        $row = DB::table(self::DEPTH_TOTALS_TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domain)
            ->first();

        return $row ? (int) $row->kernel_received_total : 0;
    }

    /**
     * Incrémente kernel_received_total[depth][domain] de 1.
     *
     * Appelé par le listener ApplyCurrentKernelReceivedToRotation.
     */
    public function incrementKernelReceived(int $depth, string $domain): void
    {
        DB::table(self::DEPTH_TOTALS_TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domain)
            ->increment('kernel_received_total');
    }

    /**
     * Initialise kernel_received_total depuis les données ReadyBank au démarrage.
     *
     * Interdit d'initialiser à 0 si ReadyBank contient déjà des noyaux.
     * N'écrase jamais une valeur existante supérieure.
     *
     * @param int                $depth
     * @param array<string, int> $receivedByDomain  Ex : ['geographie' => 12, 'histoire' => 5]
     */
    public function initializeFromReadyBank(int $depth, array $receivedByDomain): void
    {
        foreach ($receivedByDomain as $domain => $count) {
            if ($count <= 0) {
                continue;
            }

            DB::table(self::DEPTH_TOTALS_TABLE)
                ->where('depth', $depth)
                ->where('domain_code', $domain)
                ->where('kernel_received_total', '<', $count)
                ->update([
                    'kernel_received_total' => $count,
                    'updated_at'            => now(),
                ]);
        }
    }

    // =========================================================================
    // Progression complète
    // =========================================================================

    /**
     * Retourne la progression complète pour un Depth (usage ops / debug).
     *
     * @return array{
     *     depth:          int,
     *     cycle_target:   int,
     *     cycle_completed: int,
     *     remaining:      int,
     *     domains:        array<string, int>
     * }
     */
    public function getProgress(int $depth): array
    {
        $matrixRow = DB::table(self::DEPTH_MATRIX_TABLE)
            ->where('depth', $depth)
            ->first();

        $totals = DB::table(self::DEPTH_TOTALS_TABLE)
            ->where('depth', $depth)
            ->get()
            ->pluck('kernel_received_total', 'domain_code')
            ->toArray();

        $target    = self::CYCLE_TARGET[$depth] ?? 0;
        $completed = $matrixRow ? (int) $matrixRow->cycle_completed : 0;

        return [
            'depth'           => $depth,
            'cycle_target'    => $target,
            'cycle_completed' => $completed,
            'remaining'       => max(0, $target - $completed),
            'domains'         => $totals,
        ];
    }
}
