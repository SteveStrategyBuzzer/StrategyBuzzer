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
 *   peekNext(depth, domain)
 *   ├── territory non null → applyRotation + fillTaxonomy + engage + assignKernelCode
 *   │                       → STATUS_ROTATION_ASSIGNED
 *   └── null              → CONTAINMENT (EMPTY SUPERSEDED)
 *                            Blueprint supprimé, état restauré, RuntimeException explicite.
 *                            Aucune inférence DOMAIN_EXHAUSTED.
 *                            Aucune réaffectation du Blueprint à un autre domaine.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * RÈGLES CLÉS
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 *   KRP-R01  fillRotation = write-once ; appel unique dans applyRotation().
 *   KRP-R11  EMPTY ne crée jamais un nouveau Blueprint.
 *   KRP-R20  Factory appelée une seule fois, avant toute logique KRP.
 *   DEC-079  Gate : si NoRotation → aucun Blueprint créé.
 *   DEC-087  KRP ne produit JAMAIS DOMAIN_EXHAUSTED.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * EMPTY LOOP — SUPERSEDED (2026-08-14)
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * La boucle EMPTY précédente (applyEmptyAndGetNext) est DÉSACTIVÉE.
 *
 * Raison : peekNext() == null ≠ DOMAIN_EXHAUSTED.
 *   - Taxonomy est le seul émetteur de DOMAIN_EXHAUSTED (DEC-087).
 *   - Interpréter null comme épuisement crée une inférence non autorisée.
 *   - Avancer le domaine sur le même Blueprint viole le write-once si
 *     applyRotation a déjà été appelé, ou produit une divergence
 *     Blueprint.domain ≠ domaine de travail Taxonomy avant applyRotation.
 *
 * Comportement temporaire si peekNext() == null :
 *   - Blueprint supprimé (pas d'orphelin).
 *   - active_blueprint_identity réinitialisé en DB.
 *   - RuntimeException explicite avec context.
 *
 * Ce comportement sera remplacé lors du branchement Taxonomy → KRP (LOT C),
 * quand 03_Taxonomy sera spécifié.
 */
final class KernelPipelineOrchestrator
{
    private const RUNS_TABLE   = 'kernel_blueprint_runs';
    private const STATE_TABLE  = 'kernel_rotation_state_v2';

    /**
     * Blueprint engagé — KRP a écrit depth + domain, Taxonomy a fourni le territoire.
     */
    public const STATUS_ROTATION_ASSIGNED  = 'ROTATION_ASSIGNED';

    /**
     * Aucun besoin de Depth actif, ou transition de Depth en attente.
     * Aucun Blueprint engagé.
     */
    public const STATUS_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    public function __construct(
        private readonly KernelBlueprintFactory       $factory,
        private readonly KernelRotationPlanner        $planner,
        private readonly TaxonomyNavigatorInterface   $taxonomy,
        private readonly KernelCodeEngine             $kernelCodeEngine,
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
     * @throws RuntimeException CONTAINMENT si peekNext() retourne null (EMPTY SUPERSEDED).
     */
    public function run(?string $previousDomain = null): array
    {
        $blueprint  = null;
        $resolution = null;

        // ── Étape 1 : transaction atomique gate + Factory + enregistrement ────
        DB::transaction(function () use (&$blueprint, &$resolution) {
            $state      = DB::table(self::STATE_TABLE)->lockForUpdate()->first();
            $resolution = $this->planner->resolveNextRotation($state);

            if ($resolution->isNoRotation()) {
                return; // Pas de Blueprint créé (DEC-079)
            }

            $blueprint = $this->factory->create();
            $this->planner->registerActiveBlueprintIdentity($blueprint->blueprint_id, $state);
        });

        if ($blueprint === null) {
            return ['status' => self::STATUS_PRODUCTION_ON_HOLD, 'blueprint' => null];
        }

        // ── Étape 2 : tentative de territoire Taxonomy ────────────────────────
        $depth  = $resolution->depth;
        $domain = $resolution->domain;

        $territory = $this->taxonomy->peekNext($depth, $domain);

        if ($territory === null) {
            // ── CONTAINMENT EMPTY SUPERSEDED ─────────────────────────────────
            // peekNext() == null ne signifie pas DOMAIN_EXHAUSTED (DEC-087).
            // Aucune inférence d'épuisement, aucune réaffectation de domaine.
            // Supprimer le Blueprint pour éviter un orphelin qui bloquerait
            // le prochain cycle (Factory::create guard CREATED_UNENGAGED).
            $this->cleanupBlueprint($blueprint->blueprint_id);
            throw new RuntimeException(
                '[KRP CONTAINMENT] peekNext() a retourné null pour '
                . "depth={$depth}, domain={$domain}. "
                . 'EMPTY loop désactivée jusqu\'à spécification de 03_Taxonomy. '
                . 'Blueprint supprimé. Aucune inférence DOMAIN_EXHAUSTED effectuée (DEC-087).'
            );
        }

        // ── Étape 3 : rotation finale (write-once KRP-R01) ────────────────────
        $domainCycle    = DepthTourState::DOMAIN_CYCLE;
        $domainPosition = (int) array_search($domain, $domainCycle, true);

        $this->planner->applyRotation($blueprint, $depth, $domain, $domainPosition);

        $blueprint->fillTaxonomy(
            $territory['sub_domain']                                          ?? '',
            $territory['subject']                                             ?? '',
            $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? '',
        );

        $this->engageBlueprint($blueprint);
        $this->kernelCodeEngine->assignKernelCode($blueprint);

        return ['status' => self::STATUS_ROTATION_ASSIGNED, 'blueprint' => $blueprint];
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * Supprime un Blueprint CREATED_UNENGAGED et réinitialise active_blueprint_identity.
     *
     * Appelé UNIQUEMENT dans le path CONTAINMENT (peekNext == null).
     * Garantit qu'aucun orphelin ne bloque le prochain Factory::create().
     */
    private function cleanupBlueprint(string $blueprintId): void
    {
        DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('execution_state', 'CREATED_UNENGAGED')
            ->delete();

        DB::table(self::STATE_TABLE)
            ->whereNotNull('id')
            ->update([
                'active_blueprint_identity' => null,
                'updated_at'                => now(),
            ]);
    }

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
