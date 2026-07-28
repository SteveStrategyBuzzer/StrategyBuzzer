<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\Contracts\KernelKldCheckInterface;
use App\Services\QuestionBank\Rotation\Contracts\KeyStructurePipelineGateInterface;
use App\Services\QuestionBank\Rotation\DTO\LearningDirectionResult;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * KernelPipelineOrchestrator — chef d'orchestre du pipeline Kernel.
 *
 * Responsabilités :
 *   - Demander la création du Blueprint à KernelBlueprintFactory
 *   - Transmettre le Blueprint à KRP (planV2)
 *   - Gérer la boucle EMPTY : applyEmptyTransitionV2 → planV2 → Taxonomy
 *   - Vérifier KLD puis KEY_STRUCTURE après que Taxonomy fournit un territoire
 *   - Appeler confirmConsumed() UNIQUEMENT après KLD PASS + KS PASS (DEC-KLD-01, DEC-KS-01)
 *   - Engager le Blueprint lorsque les deux vérifications passent
 *
 * Interdictions :
 *   - ne décide jamais du Depth ou du Domaine
 *   - ne modifie jamais les slots métier du Blueprint (depth, domain exclus)
 *   - n'accède jamais directement à kernel_rotation_state_v2
 *   - ne simule jamais la logique métier de KLD ou KEY_STRUCTURE
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * FRONTIÈRES DÉCLARÉES (UNDER_REVIEW)
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * FRONTIÈRE 1 — IDEA_SLOT_LOADER (BLOQUÉE)
 *   Module absent       : IdeaSlotLoader
 *   Interface attendue  : fournit dominant_idea pour le sujet actif
 *   Point de blocage    : territory['dominant_idea'] absent → retour PIPELINE_BLOCKED_AWAITING_IDEA_SLOT_LOADER
 *   Code terminé avant  : Factory → KRP → Taxonomy (peekNext retourne sub_domain + subject sans dominant_idea)
 *
 * FRONTIÈRE 2 — KEY_STRUCTURE (BLOQUÉE)
 *   Module absent       : KEY_STRUCTURE (implémentation réelle)
 *   Interface attendue  : KeyStructurePipelineGateInterface::check() → PASS | FAIL
 *   Point de blocage    : après KLD PASS, avant confirmConsumed()
 *   Implémentation prod : BlockedKeyStructureGate → retourne BLOCKED systématiquement
 *   Retour orchestrateur: PIPELINE_BLOCKED_AWAITING_KEY_STRUCTURE
 *   Code terminé avant  : Factory → KRP → Taxonomy → KLD PASS
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * RÈGLE KRP-R11 (DEC-R11)
 * ═══════════════════════════════════════════════════════════════════════════════
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

    /** Blueprint engagé — KLD PASS + KS PASS + confirmConsumed() + ENGAGED_IN_PIPELINE. */
    public const STATUS_ENGAGED = 'ENGAGED';

    /** Aucun besoin de Depth actif — DepthNeedMatrix a atteint toutes ses cibles. */
    public const STATUS_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    /**
     * Pipeline bloqué — dominant_idea absent du territoire Taxonomy.
     * Frontière : IdeaSlotLoader non encore implanté.
     * Taxonomy fournit sub_domain + subject. IdeaSlotLoader doit compléter avec dominant_idea.
     * confirmConsumed() NON appelé.
     */
    public const STATUS_PIPELINE_BLOCKED_AWAITING_IDEA_SLOT_LOADER = 'PIPELINE_BLOCKED_AWAITING_IDEA_SLOT_LOADER';

    /**
     * Pipeline bloqué — KEY_STRUCTURE non encore implanté.
     * Frontière : KeyStructurePipelineGateInterface produit STATUS_BLOCKED.
     * KLD a passé. confirmConsumed() NON appelé.
     */
    public const STATUS_PIPELINE_BLOCKED_AWAITING_KEY_STRUCTURE = 'PIPELINE_BLOCKED_AWAITING_KEY_STRUCTURE';

    /**
     * KLD a rejeté la direction pédagogique.
     * confirmConsumed() NON appelé — le sujet n'est pas avancé.
     */
    public const STATUS_KLD_REJECTED = 'KLD_REJECTED';

    /**
     * KEY_STRUCTURE a rejeté la structure taxonomique.
     * confirmConsumed() NON appelé.
     */
    public const STATUS_KS_REJECTED = 'KS_REJECTED';

    public function __construct(
        private readonly KernelBlueprintFactory           $factory,
        private readonly KernelRotationPlanner            $planner,
        private readonly TaxonomyNavigatorInterface       $taxonomy,
        private readonly KernelKldCheckInterface          $kldGate,
        private readonly KeyStructurePipelineGateInterface $ksGate,
    ) {}

    /**
     * Exécute un cycle complet : Factory → KRP → Taxonomy → KLD → KS → ENGAGED.
     *
     * @param  string|null  $previousDomain  Domaine du Blueprint précédent.
     *
     * @return array{
     *     status:    string,
     *     blueprint: KernelBlueprint
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

        // Registre KLD partagé pour toute la durée de ce run()
        // (accumule les directions essayées à travers la boucle EMPTY)
        $kldRegistry   = new LearningDirectionRegistry();
        $emptyCount    = 0;
        $consumed      = false; // idempotence : confirmConsumed() au plus une fois par run()

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

            // ── Territoire fourni par Taxonomy ────────────────────────────────

            // ── FRONTIÈRE 1 : dominant_idea requis (IdeaSlotLoader manquant) ──
            $dominantIdea = $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? null;

            if ($dominantIdea === null || $dominantIdea === '') {
                // Pipeline BLOQUÉ : peekNext() retourne sub_domain + subject
                // mais IdeaSlotLoader n'existe pas encore pour compléter dominant_idea.
                // confirmConsumed() NON appelé.
                return [
                    'status'    => self::STATUS_PIPELINE_BLOCKED_AWAITING_IDEA_SLOT_LOADER,
                    'blueprint' => $blueprint,
                ];
            }

            // ── KLD ───────────────────────────────────────────────────────────
            $kldResult = $this->kldGate->check(
                $territory,
                (string) $blueprint->domain,
                (int) $blueprint->depth,
                $kldRegistry,
            );

            if ($kldResult->isFail() || $kldResult->isReviewStructure()) {
                // KLD FAIL ou REVIEW_STRUCTURE → confirmConsumed() NON appelé.
                // REVIEW_STRUCTURE est traité comme un FAIL provisoire
                // jusqu'à implantation complète de KEY_STRUCTURE.
                return [
                    'status'    => self::STATUS_KLD_REJECTED,
                    'blueprint' => $blueprint,
                ];
            }

            // KLD PASS — enregistrer la direction dans le registre
            $kldRegistry->add(
                $kldResult->normalizedSubject . '::' . $kldResult->normalizedDominantIdea,
                $kldResult->normalizedSubject,
                $kldResult->normalizedDominantIdea,
            );

            // ── FRONTIÈRE 2 : KEY_STRUCTURE ───────────────────────────────────
            $ksStatus = $this->ksGate->check(
                $territory,
                (string) $blueprint->domain,
                (int) $blueprint->depth,
            );

            if ($ksStatus === KeyStructurePipelineGateInterface::STATUS_BLOCKED) {
                // Pipeline BLOQUÉ : KEY_STRUCTURE non implanté.
                // confirmConsumed() NON appelé.
                return [
                    'status'    => self::STATUS_PIPELINE_BLOCKED_AWAITING_KEY_STRUCTURE,
                    'blueprint' => $blueprint,
                ];
            }

            if ($ksStatus === KeyStructurePipelineGateInterface::STATUS_FAIL) {
                // KS FAIL → confirmConsumed() NON appelé.
                return [
                    'status'    => self::STATUS_KS_REJECTED,
                    'blueprint' => $blueprint,
                ];
            }

            // ── KLD PASS + KS PASS → confirmer et engager ────────────────────
            $blueprint->fillTaxonomy(
                $territory['sub_domain']                                    ?? '',
                $territory['subject']                                       ?? '',
                $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? ''
            );

            $this->engageBlueprint($blueprint);

            // confirmConsumed() : appelé exactement une fois (idempotence par flag)
            if (! $consumed) {
                $this->taxonomy->confirmConsumed(
                    (int) $blueprint->depth,
                    (string) $blueprint->domain
                );
                $consumed = true;
            }

            return ['status' => self::STATUS_ENGAGED, 'blueprint' => $blueprint];
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
