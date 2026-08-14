<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * KernelPipelineOrchestrator — chef d'orchestre du KRP (02_KernelRotationPlanner v3.2).
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * FLOW CANONIQUE V3 (VERROUILLÉ 2026-08-13)
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 *   ┌── BEGIN TRANSACTION (FOR UPDATE) ───────────────────────────────────────┐
 *   │  state = SELECT … FOR UPDATE                                            │
 *   │  resolution = resolveNextRotation(state)                                │
 *   │  if NoRotation → COMMIT, retour STATUS_PRODUCTION_ON_HOLD (sans bp)     │
 *   │  blueprint = Factory::create()                                          │
 *   │  registerActiveBlueprintIdentity(blueprint.id, state)                   │
 *   └── COMMIT ───────────────────────────────────────────────────────────────┘
 *
 *   ┌── EMPTY LOOP (legacy SUPERSEDED — LOT C supprimera cette boucle) ──────┐
 *   │  currentDepth  = resolution.depth                                       │
 *   │  currentDomain = resolution.domain                                      │
 *   │  while true:                                                            │
 *   │    territory = peekNext(currentDepth, currentDomain)                    │
 *   │    if null → applyEmptyAndGetNext → null ? PRODUCTION_ON_HOLD : continue│
 *   │    else → applyRotation + fillTaxonomy + engage + assignKernelCode      │
 *   │            → STATUS_ROTATION_ASSIGNED                                   │
 *   └────────────────────────────────────────────────────────────────────────┘
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * RÈGLES CLÉS
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 *   KRP-R01  fillRotation = write-once ; appel unique dans applyRotation().
 *   KRP-R11  EMPTY ne crée jamais un nouveau Blueprint.
 *   KRP-R20  Factory appelée une seule fois, avant toute logique KRP.
 *   DEC-079  Gate : si NoRotation → aucun Blueprint créé.
 *
 * Ce que cet orchestrateur NE fait PAS :
 *   - N'appelle pas confirmConsumed (BLOCKER 2).
 *   - N'exécute pas les Phases (BLOCKER 2).
 *   - ⛔ N'invoque jamais KLD / KEY_STRUCTURE / IdeaSlotLoader (SUPERSEDED).
 */
final class KernelPipelineOrchestrator
{
    private const RUNS_TABLE     = 'kernel_blueprint_runs';
    private const MAX_EMPTY_LOOP = 16;

    /**
     * Blueprint engagé — KRP a écrit depth + domain, Taxonomy a fourni le
     * territoire, Blueprint = ENGAGED_IN_PIPELINE.
     */
    public const STATUS_ROTATION_ASSIGNED  = 'ROTATION_ASSIGNED';

    /**
     * Aucun besoin de Depth actif, ou transition de Depth en attente.
     * Aucun Blueprint n'est engagé. blueprint peut être null (V3) ou orphelin
     * pré-engagement (edge case EMPTY → tour complet).
     */
    public const STATUS_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    public function __construct(
        private readonly KernelBlueprintFactory     $factory,
        private readonly KernelRotationPlanner      $planner,
        private readonly TaxonomyNavigatorInterface $taxonomy,
        private readonly KernelCodeEngine           $kernelCodeEngine,
    ) {}

    /**
     * Exécute un cycle KRP complet.
     *
     * @param  string|null  $previousDomain  Ignoré en V3 (sélection via domain_states).
     *
     * @return array{
     *     status:    string,
     *     blueprint: KernelBlueprint|null
     * }
     *
     * @throws RuntimeException STOP si la garde anti-boucle EMPTY est déclenchée.
     */
    public function run(?string $previousDomain = null): array
    {
        $blueprint  = null;
        $resolution = null;

        // ── Étape 1 : transaction atomique gate + Factory + enregistrement ────
        DB::transaction(function () use (&$blueprint, &$resolution) {
            $state      = DB::table('kernel_rotation_state_v2')->lockForUpdate()->first();
            $resolution = $this->planner->resolveNextRotation($state);

            if ($resolution->isNoRotation()) {
                // Gate : aucun Blueprint créé (DEC-079)
                return;
            }

            $blueprint = $this->factory->create();
            $this->planner->registerActiveBlueprintIdentity($blueprint->blueprint_id, $state);
        });

        if ($blueprint === null) {
            // PRODUCTION_ON_HOLD ou PENDING_DEPTH_TRANSITION
            return ['status' => self::STATUS_PRODUCTION_ON_HOLD, 'blueprint' => null];
        }

        // ── Étape 2 : EMPTY loop (legacy SUPERSEDED — LOT C supprimera ceci) ─
        // La sélection initiale du domaine vient de resolveNextRotation (domain_states).
        // Si peekNext retourne null, on avance via tour_domain_states (legacy).
        // fillRotation est appelé UNE SEULE FOIS via applyRotation, après territoire confirmé.
        $currentDepth  = $resolution->depth;
        $currentDomain = $resolution->domain;
        $emptyCount    = 0;

        while (true) {
            if ($emptyCount >= self::MAX_EMPTY_LOOP) {
                throw new RuntimeException(
                    '[KernelPipelineOrchestrator] STOP — boucle EMPTY infinie détectée '
                    . "après {$emptyCount} itérations."
                );
            }

            $territory = $this->taxonomy->peekNext($currentDepth, $currentDomain);

            if ($territory === null) {
                // ── EMPTY : avancer le DomainCycle via mécanisme legacy ───────
                [$currentDepth, $currentDomain] = $this->planner->applyEmptyAndGetNext(
                    $currentDepth,
                    $currentDomain,
                );

                if ($currentDomain === null) {
                    // Tour complet → PRODUCTION_ON_HOLD
                    return ['status' => self::STATUS_PRODUCTION_ON_HOLD, 'blueprint' => $blueprint];
                }

                $emptyCount++;
                continue;
            }

            // ── Territoire confirmé — rotation finale ─────────────────────────
            $domainCycle    = DepthTourState::DOMAIN_CYCLE;
            $domainPosition = (int) array_search($currentDomain, $domainCycle, true);

            // Write-once fillRotation via applyRotation (KRP-R01)
            $this->planner->applyRotation($blueprint, $currentDepth, $currentDomain, $domainPosition);

            $blueprint->fillTaxonomy(
                $territory['sub_domain']                                          ?? '',
                $territory['subject']                                             ?? '',
                $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? '',
            );

            $this->engageBlueprint($blueprint);
            $this->kernelCodeEngine->assignKernelCode($blueprint);

            return ['status' => self::STATUS_ROTATION_ASSIGNED, 'blueprint' => $blueprint];
        }
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    private function engageBlueprint(KernelBlueprint $blueprint): void
    {
        DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->update([
                'execution_state' => 'ENGAGED_IN_PIPELINE',
                'depth'           => $blueprint->depth,
                'domain_code'     => $blueprint->domain,
                'engaged_at'      => now(),
                'updated_at'      => now(),
            ]);
    }
}
