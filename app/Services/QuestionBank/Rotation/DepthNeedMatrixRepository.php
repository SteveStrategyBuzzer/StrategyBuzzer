<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\CreatorDomainRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DepthNeedMatrixRepository — accès aux tables kernel_depth_matrix
 * et kernel_depth_domain_totals.
 *
 * Responsabilités :
 *   - Encapsuler toutes les requêtes DB sur les deux tables de la matrice.
 *   - Fournir des méthodes de lecture (collection, ligne unique) et
 *     d'écriture (increment, update conditionnel).
 *
 * Interdictions :
 *   - Ne connaît pas le DepthCycle ni les CYCLE_TARGET (→ DepthNeedMatrix).
 *   - Ne prend aucune décision sur le prochain Depth ou Domaine.
 */
final class DepthNeedMatrixRepository
{
    private const MATRIX_TABLE = 'kernel_depth_matrix';
    private const TOTALS_TABLE = 'kernel_depth_domain_totals';

    // =========================================================================
    // kernel_depth_matrix
    // =========================================================================

    /**
     * Retourne les lignes de kernel_depth_matrix pour les depths donnés,
     * indexées par depth.
     *
     * @param  int[]  $depths
     */
    public function getMatrixRows(array $depths): Collection
    {
        return DB::table(self::MATRIX_TABLE)
            ->whereIn('depth', $depths)
            ->get()
            ->keyBy('depth');
    }

    /**
     * Retourne la ligne pour un Depth donné, ou null si absente.
     */
    public function getMatrixRow(int $depth): ?object
    {
        return DB::table(self::MATRIX_TABLE)
            ->where('depth', $depth)
            ->first();
    }

    /**
     * Incrémente cycle_completed pour un Depth de 1.
     */
    public function incrementCycleCompleted(int $depth): void
    {
        DB::table(self::MATRIX_TABLE)
            ->where('depth', $depth)
            ->increment('cycle_completed');
    }

    // =========================================================================
    // kernel_depth_domain_totals
    // =========================================================================

    /**
     * Retourne la ligne pour un couple depth × domain_code, ou null.
     */
    public function getTotalsRow(int $depth, string $domain): ?object
    {
        [$canonical, $slug] = $this->domainForms($domain);
        $rows = DB::table(self::TOTALS_TABLE)
            ->where('depth', $depth)
            ->whereIn('domain_code', [$canonical, $slug])
            ->get();
        if ($rows->count() > 1) {
            throw new RuntimeException("Représentations concurrentes pour {$depth}/{$canonical}.");
        }
        return $rows->first();
    }

    /**
     * Retourne toutes les lignes de totaux pour un Depth donné.
     * Indexées par domain_code.
     */
    public function getTotalsForDepth(int $depth): Collection
    {
        $rows = DB::table(self::TOTALS_TABLE)
            ->where('depth', $depth)
            ->get();
        $result = collect();
        foreach ($rows as $row) {
            $code = CreatorDomainRegistry::fromInput((string) $row->domain_code);
            if ($result->has($code)) {
                throw new RuntimeException("Représentations concurrentes pour {$depth}/{$code}.");
            }
            $result->put($code, $row);
        }
        return $result;
    }

    /**
     * Incrémente kernel_received_total pour un couple depth × domain de 1.
     */
    public function incrementKernelReceived(int $depth, string $domain): void
    {
        $stored = $this->requireStoredDomain($depth, $domain);
        DB::table(self::TOTALS_TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $stored)
            ->increment('kernel_received_total');
    }

    /**
     * Met à jour kernel_received_total uniquement si la valeur actuelle
     * est inférieure à $count (protection contre l'écrasement d'une valeur plus haute).
     *
     * Utilisé lors de l'initialisation depuis ReadyBank au démarrage.
     */
    public function updateKernelReceivedIfLess(int $depth, string $domain, int $count): void
    {
        $stored = $this->requireStoredDomain($depth, $domain);
        DB::table(self::TOTALS_TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $stored)
            ->where('kernel_received_total', '<', $count)
            ->update([
                'kernel_received_total' => $count,
                'updated_at'            => now(),
            ]);
    }

    /** @return array{string, string} */
    private function domainForms(string $domain): array
    {
        $canonical = CreatorDomainRegistry::fromInput($domain);
        return [$canonical, CreatorDomainRegistry::get($canonical)['slug']];
    }

    private function requireStoredDomain(int $depth, string $domain): string
    {
        [$canonical, $slug] = $this->domainForms($domain);
        $row = $this->getTotalsRow($depth, $canonical);
        if ($row === null) {
            throw new RuntimeException("Ligne absente pour {$depth}/{$canonical}.");
        }
        return (string) $row->domain_code;
    }
}
