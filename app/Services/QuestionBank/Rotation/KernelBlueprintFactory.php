<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KernelBlueprintFactory — crée une nouvelle enveloppe Blueprint.
 *
 * Responsabilités (DEC-058, DEC-059, DEC-067) :
 *   - générer blueprint_id (UUIDv7 via Str::orderedUuid())
 *   - créer l'enregistrement dans kernel_blueprint_runs (état CREATED_UNENGAGED)
 *   - garantir qu'un seul Blueprint actif peut exister à la fois (B1)
 *   - retourner un KernelBlueprint vide portant uniquement blueprint_id
 *
 * Garantie d'unicité — deux niveaux :
 *   1. Vérification applicative EXISTS (chemin rapide, séquentiel).
 *   2. Index unique partiel PostgreSQL one_active_blueprint_idx (atomique,
 *      protège contre les créations concurrentes simultanées).
 *      Sur UniqueConstraintViolationException : même RuntimeException levée.
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

    private const STOP_MESSAGE =
        '[KernelBlueprintFactory] STOP — un Blueprint actif existe déjà '
        . '(CREATED_UNENGAGED ou ENGAGED_IN_PIPELINE). '
        . 'Attendre CURRENT_KERNEL_RECEIVED ou NOT_ENGAGED_PRODUCTION_ON_HOLD.';

    public function __construct(
        private readonly KernelBlueprintCognitiveSlotRepository $slots =
            new KernelBlueprintCognitiveSlotRepository(),
    ) {}

    /**
     * Crée un nouveau KernelBlueprint.
     *
     * @throws RuntimeException si un Blueprint actif existe déjà.
     */
    public function create(): KernelBlueprint
    {
        // ── Niveau 1 : vérification applicative (séquentielle, chemin rapide) ─
        $activeExists = DB::table(self::RUNS_TABLE)
            ->whereIn('execution_state', self::ACTIVE_STATES)
            ->exists();

        if ($activeExists) {
            throw new RuntimeException(self::STOP_MESSAGE);
        }

        // ── Niveau 2 : INSERT avec filet atomique DB (index partiel PostgreSQL) ─
        $blueprintId = (string) Str::orderedUuid();

        try {
            $slots = DB::transaction(function () use ($blueprintId): array {
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

                return $this->slots->initializeEmptySlots($blueprintId);
            });
        } catch (UniqueConstraintViolationException) {
            // Deux créations simultanées ont passé le EXISTS en même temps :
            // l'index partiel one_active_blueprint_idx a rejeté la seconde.
            throw new RuntimeException(self::STOP_MESSAGE);
        }

        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($blueprintId);
        $blueprint->initializeCognitiveSlots($slots);

        return $blueprint;
    }
}
