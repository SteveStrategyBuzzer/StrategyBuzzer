<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KernelBlueprintFactory — crée une nouvelle enveloppe Blueprint.
 *
 * Responsabilités (DEC-058, DEC-059, DEC-067) :
 *   - générer blueprint_id (UUIDv7 via Str::orderedUuid())
 *   - créer l'enregistrement dans kernel_blueprint_runs (état CREATED_UNENGAGED)
 *   - vérifier qu'aucun Blueprint actif n'existe déjà (garde unicité)
 *   - retourner un KernelBlueprint vide portant uniquement blueprint_id
 *
 * Interdictions :
 *   - ne remplit jamais depth, domain, ni aucun slot métier
 *   - ne sélectionne jamais un Depth ou un Domaine
 *   - ne modifie jamais kernel_rotation_state_v2
 */
final class KernelBlueprintFactory
{
    private const RUNS_TABLE = 'kernel_blueprint_runs';

    /** États considérés « actifs » — un seul Blueprint actif autorisé (DEC-067). */
    private const ACTIVE_STATES = [
        'CREATED_UNENGAGED',
        'ENGAGED_IN_PIPELINE',
    ];

    /**
     * Crée un nouveau KernelBlueprint.
     *
     * @throws RuntimeException si un Blueprint actif existe déjà.
     */
    public function create(): KernelBlueprint
    {
        $activeExists = DB::table(self::RUNS_TABLE)
            ->whereIn('execution_state', self::ACTIVE_STATES)
            ->exists();

        if ($activeExists) {
            throw new RuntimeException(
                '[KernelBlueprintFactory] STOP — un Blueprint actif existe déjà '
                . '(CREATED_UNENGAGED ou ENGAGED_IN_PIPELINE). '
                . 'Attendre CURRENT_KERNEL_RECEIVED ou NOT_ENGAGED_PRODUCTION_ON_HOLD.'
            );
        }

        $blueprintId = (string) Str::orderedUuid();

        DB::table(self::RUNS_TABLE)->insert([
            'blueprint_id'    => $blueprintId,
            'execution_state' => 'CREATED_UNENGAGED',
            'depth'           => null,
            'domain_code'     => null,
            'engaged_at'      => null,
            'received_at'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $blueprint               = new KernelBlueprint();
        $blueprint->blueprint_id = $blueprintId;

        return $blueprint;
    }
}
