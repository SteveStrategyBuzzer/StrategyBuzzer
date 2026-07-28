<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use Illuminate\Support\Facades\DB;

/**
 * KernelRotationStateRepository — accès à kernel_rotation_state_v2.
 *
 * Responsabilités :
 *   - Encapsuler toutes les opérations DB sur kernel_rotation_state_v2.
 *   - La table ne contient qu'UNE seule ligne (état global de la rotation V2).
 *
 * Interdictions :
 *   - Ne décide jamais quel état écrire.
 *   - Ne connaît pas les règles du DepthCycle ni du Tour de Depth.
 */
final class KernelRotationStateRepository
{
    private const TABLE = 'kernel_rotation_state_v2';

    // =========================================================================
    // Lecture
    // =========================================================================

    /**
     * Retourne la ligne d'état unique, ou null si jamais initialisée.
     */
    public function first(): ?object
    {
        return DB::table(self::TABLE)->first();
    }

    /**
     * Retourne la ligne d'état avec verrou exclusif (pour transaction).
     */
    public function firstForUpdate(): ?object
    {
        return DB::table(self::TABLE)->lockForUpdate()->first();
    }

    // =========================================================================
    // Écriture
    // =========================================================================

    /**
     * Insère la ligne initiale (premier appel absolu).
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): void
    {
        DB::table(self::TABLE)->insert($data);
    }

    /**
     * Met à jour la ligne existante.
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): void
    {
        DB::table(self::TABLE)->update($data);
    }

    /**
     * Incrémente le lock_version et merge les données fournies.
     * Raccourci pour les mises à jour concurrentes.
     *
     * @param object               $current  Ligne actuelle (doit avoir lock_version).
     * @param array<string, mixed> $data     Champs à mettre à jour.
     */
    public function updateWithLock(object $current, array $data): void
    {
        DB::table(self::TABLE)->update(array_merge($data, [
            'lock_version' => (int) $current->lock_version + 1,
            'updated_at'   => now(),
        ]));
    }
}
