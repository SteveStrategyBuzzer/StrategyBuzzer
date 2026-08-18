<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;

/**
 * KernelPipelineOrchestrator — raccord d'entrée du module 02.
 *
 * Périmètre actif :
 *
 *   KernelBlueprintFactory
 *   ↓
 *   Blueprint canonique CREATED_UNENGAGED
 *   ↓
 *   KernelRotationPlanner
 *   ↓
 *   fillRotation(depth, domain)
 *   ↓
 *   FIN DU MODULE 02
 *
 * Taxonomy, ValidationDominantIdeas, QuestionIntent, KernelCodeEngine et les
 * phases aval ne sont jamais exécutés ici. Ils appartiennent aux modules suivants.
 */
final class KernelPipelineOrchestrator
{
    private const STATE_TABLE = 'kernel_rotation_state_v2';

    /** Blueprint créé et estampillé depth + domain, prêt pour le module 03. */
    public const STATUS_ROTATION_ASSIGNED = 'ROTATION_ASSIGNED';

    /** Toutes les cibles globales sont satisfaites : aucun Blueprint créé. */
    public const STATUS_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    /** Persistance KRP en incident terminal : aucune nouvelle rotation autorisée. */
    public const STATUS_BLOCKED = 'BLOCKED';

    /** Tour courant fermé côté Domaines, en attente du signal DEPTH_EXHAUSTED Taxonomy. */
    public const STATUS_AWAITING_DEPTH_EXHAUSTED = 'AWAITING_DEPTH_EXHAUSTED';

    public function __construct(
        private readonly KernelBlueprintFactory $factory,
        private readonly KernelRotationPlanner $planner,
    ) {}

    /**
     * Crée au plus un Blueprint et exécute strictement le module 02.
     *
     * @return array{status:string, blueprint:KernelBlueprint|null}
     */
    public function run(?string $previousDomain = null): array
    {
        $blueprint = null;
        $resolution = null;

        DB::transaction(function () use (&$blueprint, &$resolution): void {
            $state = DB::table(self::STATE_TABLE)->lockForUpdate()->first();
            $resolution = $this->planner->resolveNextRotation($state);

            if ($resolution->isNoRotation()) {
                return;
            }

            // Le Blueprint existe AVANT toute écriture KRP.
            $blueprint = $this->factory->create();
            $this->planner->registerActiveBlueprintIdentity($blueprint->blueprint_id, $state);

            // KRP écrit immédiatement son unique responsabilité : depth + domain.
            $this->planner->applyRotation(
                $blueprint,
                (int) $resolution->depth,
                (string) $resolution->domain,
                (int) $resolution->domainPosition,
            );
        });

        if ($blueprint !== null) {
            return [
                'status' => self::STATUS_ROTATION_ASSIGNED,
                'blueprint' => $blueprint,
            ];
        }

        $reason = $resolution?->noRotationReason();

        return [
            'status' => match ($reason) {
                KernelRotationPlanner::DEPTH_STATE_BLOCKED => self::STATUS_BLOCKED,
                'AWAITING_DEPTH_EXHAUSTED' => self::STATUS_AWAITING_DEPTH_EXHAUSTED,
                default => self::STATUS_PRODUCTION_ON_HOLD,
            },
            'blueprint' => null,
        ];
    }
}
