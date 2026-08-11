<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * KernelPipelineOrchestrator — chef d'orchestre du KRP.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * PÉRIMÈTRE STRICTEMENT KRP (02_KernelRotationPlanner)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * FLOW CANONIQUE (verrouillé 2026-08-11 — KLD / KEY_STRUCTURE SUPERSEDED,
 * responsabilités absorbées par ValidationDominantIdeas dans Taxonomy) :
 *
 *   CRÉATION DU KERNELBLUEPRINT (Factory — AVANT KRP, jamais PAR KRP)
 *     → KernelRotationPlanner (depth + domain)
 *     → Taxonomy ↕ ValidationDominantIdeas (sous-domaine, sujet, idée dominante VALIDÉE)
 *     → QuestionIntent (QuestionIntentEncoder — encodage passif, RACCORDEMENT A)
 *     → Phase 1 → Validation Phase 1 → Phase 2 → Validation Phase 2
 *       (tapis roulant questions:kernel:advance)
 *     → ReadyBank (KernelBlueprintReadyBankReceiver)
 *     → Taxonomy.confirmConsumed() idempotent (RACCORDEMENT B — gated par le reçu)
 *     → CURRENT_KERNEL_RECEIVED → CRÉATION DU BLUEPRINT SUIVANT → KRP.
 *
 * Ce que cet orchestrateur fait :
 *   1. Demander la création du Blueprint à KernelBlueprintFactory
 *   2. Transmettre le Blueprint à KRP (planV2) → obtient depth + domain
 *   3. Transmettre depth + domain à Taxonomy (peekNext)
 *   4. Sur EMPTY (null) : applyEmptyTransitionV2 → planV2 → peekNext (boucle)
 *   5. Sur TERRITORY_PROVIDED : fillTaxonomy → engagement + encodage QuestionIntent
 *      dans UNE transaction (RACCORDEMENT A) → ROTATION_ASSIGNED
 *   6. Sur aucun Depth disponible : retourner PRODUCTION_ON_HOLD
 *
 * Ce que cet orchestrateur NE fait PAS :
 *   - N'exécute pas Phase 1 / Validation 1 / Phase 2 / Validation 2
 *     (→ questions:kernel:advance)
 *   - N'appelle pas confirmConsumed() — la consommation n'est confirmée qu'après
 *     acceptation ReadyBank (→ ApplyCurrentKernelReceivedToRotation)
 *   - ⛔ N'invoque JAMAIS KLD / KEY_STRUCTURE / IdeaSlotLoader (SUPERSEDED)
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * CONTRATS D'ENTRÉE / SORTIE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * TaxonomyInputPort (peekNext) :
 *   Entrée  : depth (int), domain (string)
 *   Sortie  : array{sub_domain, subject, ...} | null
 *   null    = signal EMPTY — ce Domaine × Depth est épuisé
 *
 * Résultats de run() :
 *   ROTATION_ASSIGNED   — Blueprint avec depth + domain + slots Taxonomy écrits.
 *                          Prêt à entrer dans le pipeline intellectuel.
 *   PRODUCTION_ON_HOLD  — DepthNeedMatrix a atteint toutes ses cibles.
 *                          Aucun Blueprint n'est engagé.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * RÈGLE KRP-R11 (DEC-R11)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Après une production engagée ayant atteint ReadyBank,
 * CURRENT_KERNEL_RECEIVED est le seul déclencheur autorisant
 * la création du Blueprint suivant.
 *
 * Pendant qu'un Blueprint est encore CREATED_UNENGAGED,
 * EMPTY déclenche immédiatement une nouvelle sélection
 * Depth + Domaine dans le même Blueprint.
 *
 * EMPTY ne crée jamais un nouveau Blueprint.
 * Taxonomy ne décide jamais du prochain Depth ou Domaine.
 * KRP reste seul décideur de la sélection.
 */
final class KernelPipelineOrchestrator
{
    private const RUNS_TABLE     = 'kernel_blueprint_runs';
    private const MAX_EMPTY_LOOP = 16; // garde-fou anti-boucle infinie

    // =========================================================================
    // Statuts de retour de run()
    // =========================================================================

    /**
     * Blueprint engagé — KRP a écrit depth + domain,
     * Taxonomy a fourni le territoire, Blueprint = ENGAGED_IN_PIPELINE,
     * QuestionIntent encodé (RACCORDEMENT A).
     *
     * Les Phases 1-2 (questions:kernel:advance) commencent après ce retour.
     */
    public const STATUS_ROTATION_ASSIGNED = 'ROTATION_ASSIGNED';

    /** Aucun besoin de Depth actif — DepthNeedMatrix a atteint toutes ses cibles. */
    public const STATUS_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    public function __construct(
        private readonly KernelBlueprintFactory     $factory,
        private readonly KernelRotationPlanner      $planner,
        private readonly TaxonomyNavigatorInterface $taxonomy,
        private readonly QuestionIntentEncoder      $intentEncoder,
    ) {}

    /**
     * Exécute un cycle KRP complet : Factory → KRP → Taxonomy → ROTATION_ASSIGNED.
     *
     * @param  string|null  $previousDomain  Domaine du Blueprint précédent.
     *
     * @return array{
     *     status:     string,
     *     blueprint:  KernelBlueprint,
     *     intent_id?: int
     * }
     *
     * @throws RuntimeException STOP si la garde anti-boucle est déclenchée.
     */
    public function run(?string $previousDomain = null): array
    {
        $blueprint = $this->factory->create();
        $result    = $this->planner->planV2($blueprint, $previousDomain);

        if ($result === KernelRotationPlanner::RESULT_PRODUCTION_ON_HOLD) {
            return ['status' => self::STATUS_PRODUCTION_ON_HOLD, 'blueprint' => $blueprint];
        }

        $emptyCount = 0;

        while (true) {
            if ($emptyCount >= self::MAX_EMPTY_LOOP) {
                throw new RuntimeException(
                    '[KernelPipelineOrchestrator] STOP — boucle EMPTY infinie détectée '
                    . "après {$emptyCount} itérations."
                );
            }

            // ── TaxonomyInputPort : peekNext ──────────────────────────────────
            $territory = $this->taxonomy->peekNext(
                (int) $blueprint->depth,
                (string) $blueprint->domain
            );

            if ($territory === null) {
                // ── EMPTY — ce Domaine × Depth est épuisé ────────────────────
                // RÈGLE KRP-R11 : EMPTY → nouvelle sélection Depth+Domaine dans
                // le MÊME Blueprint. EMPTY ne crée jamais un nouveau Blueprint.
                $emptyDomain = (string) $blueprint->domain;

                $this->planner->applyEmptyTransitionV2($emptyDomain);
                $emptyCount++;

                $result = $this->planner->planV2($blueprint, $emptyDomain);

                if ($result === KernelRotationPlanner::RESULT_PRODUCTION_ON_HOLD) {
                    return ['status' => self::STATUS_PRODUCTION_ON_HOLD, 'blueprint' => $blueprint];
                }

                continue;
            }

            // ── TERRITORY_PROVIDED — KRP a terminé sa responsabilité ──────────
            // Écrire les slots Taxonomy, puis engager le Blueprint ET encoder le
            // QuestionIntent dans UNE transaction (RACCORDEMENT A) : le territoire
            // ne vit pas dans kernel_blueprint_runs — l'atomicité interdit un
            // Blueprint engagé sans intent (perte de territoire sur crash).
            $blueprint->fillTaxonomy(
                $territory['sub_domain']                                        ?? '',
                $territory['subject']                                           ?? '',
                $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? '',
            );

            $intentId = DB::transaction(function () use ($blueprint): int {
                $this->engageBlueprint($blueprint);

                return $this->intentEncoder->encode($blueprint);
            });

            return [
                'status'    => self::STATUS_ROTATION_ASSIGNED,
                'blueprint' => $blueprint,
                'intent_id' => $intentId,
            ];
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
