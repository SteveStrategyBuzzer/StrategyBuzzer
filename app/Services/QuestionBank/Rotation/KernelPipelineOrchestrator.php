<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * KernelPipelineOrchestrator — chef d'orchestre du pipeline Kernel.
 *
 * Responsabilités :
 *   - Demander la création du Blueprint à KernelBlueprintFactory
 *   - Transmettre le Blueprint à KRP (planV2)
 *   - Gérer la boucle EMPTY : applyEmptyTransitionV2 → planV2 → Taxonomy
 *   - Engager le Blueprint lorsque Taxonomy fournit son territoire
 *
 * Interdictions :
 *   - ne décide jamais du Depth ou du Domaine
 *   - ne modifie jamais les slots métier du Blueprint (depth, domain exclus)
 *   - n'accède jamais directement à kernel_rotation_state_v2
 */
final class KernelPipelineOrchestrator
{
    private const RUNS_TABLE    = 'kernel_blueprint_runs';
    private const MAX_EMPTY_LOOP = 16; // garde-fou anti-boucle infinie

    public function __construct(
        private readonly KernelBlueprintFactory  $factory,
        private readonly KernelRotationPlanner   $planner,
        private readonly TaxonomyProgressManager $taxonomy,
    ) {}

    /**
     * Exécute un cycle complet : Factory → KRP → Taxonomy → ENGAGED.
     *
     * @param  string|null  $previousDomain  Domaine du Blueprint précédent (pour avancement du DomainCycle).
     *
     * @return array{
     *     status:    string,
     *     blueprint: KernelBlueprint
     * }
     *
     * Statuts possibles :
     *   ENGAGED              — Blueprint engagé, pipeline poursuit
     *   PRODUCTION_ON_HOLD   — aucun besoin de Depth actif
     *
     * @throws RuntimeException STOP si la garde anti-boucle est déclenchée.
     */
    public function run(?string $previousDomain = null): array
    {
        $blueprint = $this->factory->create();
        $result    = $this->planner->planV2($blueprint, $previousDomain);

        if ($result === KernelRotationPlanner::RESULT_PRODUCTION_ON_HOLD) {
            return ['status' => 'PRODUCTION_ON_HOLD', 'blueprint' => $blueprint];
        }

        $emptyCount = 0;

        while (true) {
            if ($emptyCount >= self::MAX_EMPTY_LOOP) {
                throw new RuntimeException(
                    '[KernelPipelineOrchestrator] STOP — boucle EMPTY infinie détectée '
                    . "après {$emptyCount} itérations."
                );
            }

            $territory = $this->taxonomy->peekNext(
                (int) $blueprint->depth,
                (string) $blueprint->domain
            );

            if ($territory !== null) {
                // Territoire fourni → engager le Blueprint
                $blueprint->fillTaxonomy(
                    $territory['sub_domain']        ?? $territory['subdomain']        ?? '',
                    $territory['subject']           ?? '',
                    $territory['dominant_idea']     ?? $territory['dominant_idea_active'] ?? ''
                );

                $this->engageBlueprint($blueprint);
                $this->taxonomy->confirmConsumed(
                    (int) $blueprint->depth,
                    (string) $blueprint->domain
                );

                return ['status' => 'ENGAGED', 'blueprint' => $blueprint];
            }

            // EMPTY — Taxonomy ne peut rien fournir pour ce Depth × Domaine
            $emptyDomain = (string) $blueprint->domain;

            $this->planner->applyEmptyTransitionV2($emptyDomain);
            $emptyCount++;

            $result = $this->planner->planV2($blueprint, $emptyDomain);

            if ($result === KernelRotationPlanner::RESULT_PRODUCTION_ON_HOLD) {
                return ['status' => 'PRODUCTION_ON_HOLD', 'blueprint' => $blueprint];
            }
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
