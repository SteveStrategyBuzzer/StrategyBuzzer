<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use Illuminate\Support\Facades\DB;

/**
 * KernelBlueprintRunRepository — accès à kernel_blueprint_runs.
 *
 * Responsabilités (DEC-058, DEC-067) :
 *   - Encapsuler toutes les opérations DB sur kernel_blueprint_runs.
 *   - Fournir des méthodes nommées d'après les transitions d'état du Blueprint.
 *
 * Interdictions :
 *   - Ne décide jamais quel état appliquer.
 *   - Ne lit jamais les tables V2 (kernel_rotation_state_v2, kernel_depth_matrix, etc.).
 */
final class KernelBlueprintRunRepository
{
    private const TABLE = 'kernel_blueprint_runs';

    /** États considérés « actifs » — un seul Blueprint actif autorisé (DEC-067). */
    public const ACTIVE_STATES = [
        'CREATED_UNENGAGED',
        'ENGAGED_IN_PIPELINE',
    ];

    // =========================================================================
    // Lecture
    // =========================================================================

    /**
     * Vérifie l'existence d'au moins un Blueprint actif
     * (CREATED_UNENGAGED ou ENGAGED_IN_PIPELINE).
     */
    public function hasActive(): bool
    {
        return DB::table(self::TABLE)
            ->whereIn('execution_state', self::ACTIVE_STATES)
            ->exists();
    }

    /**
     * Retourne le premier enregistrement actif, ou null si aucun.
     */
    public function findActive(): ?object
    {
        return DB::table(self::TABLE)
            ->whereIn('execution_state', self::ACTIVE_STATES)
            ->first();
    }

    /**
     * Retourne l'enregistrement par blueprint_id, ou null s'il est absent.
     */
    public function findById(string $blueprintId): ?object
    {
        return DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->first();
    }

    // =========================================================================
    // Écriture
    // =========================================================================

    /**
     * Crée un enregistrement en état CREATED_UNENGAGED.
     */
    public function create(string $blueprintId): void
    {
        DB::table(self::TABLE)->insert([
            'blueprint_id'    => $blueprintId,
            'execution_state' => 'CREATED_UNENGAGED',
            'depth'           => null,
            'domain_code'     => null,
            'engaged_at'      => null,
            'received_at'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Passe un Blueprint de CREATED_UNENGAGED → ENGAGED_IN_PIPELINE.
     * Écrit depth + domain_code + engaged_at.
     */
    public function markEngaged(string $blueprintId, int $depth, string $domain): void
    {
        DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->update([
                'execution_state' => 'ENGAGED_IN_PIPELINE',
                'depth'           => $depth,
                'domain_code'     => $domain,
                'engaged_at'      => now(),
                'updated_at'      => now(),
            ]);
    }

    /**
     * Passe un Blueprint de ENGAGED_IN_PIPELINE → READY_BANK_RECEIVED.
     * Appelé dans la même transaction que l'Outbox (DEC-063).
     *
     * @param string $receivedAt  Horodatage ISO-8601 de la réception.
     */
    public function markReadyBankReceived(string $blueprintId, string $receivedAt): void
    {
        DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->update([
                'execution_state' => 'READY_BANK_RECEIVED',
                'received_at'     => $receivedAt,
                'updated_at'      => now(),
            ]);
    }

    /**
     * Persiste le kernel_code attribué par KernelCodeEngine.
     *
     * NOTE : KernelCodeEngine écrit kernel_code dans sa propre transaction
     * (avec row-lock sur la séquence). Cette méthode est fournie pour les
     * orchestrateurs qui souhaitent persister le code en dehors d'une
     * transaction KernelCodeEngine — usage secondaire, pas le chemin principal.
     */
    public function markKernelCodeAssigned(string $blueprintId, string $kernelCode): void
    {
        DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->update(['kernel_code' => $kernelCode, 'updated_at' => now()]);
    }

    /**
     * Retourne le kernel_code persisté pour un Blueprint, ou null.
     */
    public function findKernelCode(string $blueprintId): ?string
    {
        $value = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->value('kernel_code');

        return $value !== null ? (string) $value : null;
    }

}
