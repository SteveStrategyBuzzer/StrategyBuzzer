<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KernelRotationPlanner
 *
 * Machine d'état persistante de la rotation globale.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * INTERFACE V2 (DEC-058 à DEC-068) — méthodes actives
 * ═══════════════════════════════════════════════════════════════════════
 *
 *   planV2(KernelBlueprint $blueprint, ?string $previousDomain)
 *     → écrit depth + domain dans le Blueprint reçu
 *     → retourne ROTATION_ASSIGNED | NOT_ENGAGED_PRODUCTION_ON_HOLD
 *
 *   applyEmptyTransitionV2(string $emptyDomain)
 *     → Domaine ON → OFF dans le Tour de Depth
 *     → si Tour 8/8 : cycle_completed++ et nouveau Depth
 *
 *   receiveKernelReceivedV2(string $blueprintId, int $depth, string $domain)
 *     → comptabilisation idempotente via kernel_current_kernel_receipts
 *
 * ═══════════════════════════════════════════════════════════════════════
 * INTERFACE LEGACY (DEPRECATED — non utilisées par KRP V2)
 * ═══════════════════════════════════════════════════════════════════════
 *
 *   initialize(?int $startDepth)
 *   plan(DomainExhaustionChecker $checker)
 *   buildDepthNeedMatrix(array $existingByDepth)
 *   chooseDepth(array $matrix)
 *   advanceDomainIndex(?int $currentIndex, array $domains)
 *   loadDomains()
 *
 * Les méthodes legacy restent physiquement présentes pour le retour arrière.
 * Leur suppression physique est planifiée dans un patch séparé post-validation.
 *
 * Gestion d'erreur : RuntimeException (STOP) — jamais de fallback, jamais de retry.
 */
final class KernelRotationPlanner
{
    // =========================================================================
    // Tables V2
    // =========================================================================

    private const STATE_TABLE_V2   = 'kernel_rotation_state_v2';
    private const RUNS_TABLE       = 'kernel_blueprint_runs';
    private const RECEIPTS_TABLE   = 'kernel_current_kernel_receipts';

    /** Retour de planV2 : depth + domain écrits dans le Blueprint. */
    public const RESULT_ROTATION_ASSIGNED  = 'ROTATION_ASSIGNED';

    /** Retour de planV2 : aucun Depth requis actif. */
    public const RESULT_PRODUCTION_ON_HOLD = 'NOT_ENGAGED_PRODUCTION_ON_HOLD';

    // =========================================================================
    // Tables legacy (DEPRECATED — conservées pour retour arrière)
    // =========================================================================

    /** @deprecated LEGACY — non utilisé par KRP V2 */
    private const STATE_TABLE = 'kernel_rotation_state';

    /**
     * Depths autorisés (legacy v1).
     * @deprecated LEGACY — remplacé par DepthNeedMatrix::DEPTH_CYCLE
     */
    private const ALLOWED_DEPTHS = [4, 6, 7, 8, 9];

    /**
     * Cibles officielles de noyaux par depth (v1 production).
     * @deprecated LEGACY — remplacé par DepthNeedMatrix::CYCLE_TARGET
     */
    public const DEPTH_TARGETS = [
        4 => 3000,
        6 => 3000,
        7 => 2500,
        8 => 2000,
        9 => 1500,
    ];

    // =========================================================================
    // V2 — Interface active (DEC-058 à DEC-068)
    // =========================================================================

    /**
     * V2 — Écrit depth + domain dans le Blueprint reçu (DEC-058).
     *
     * Sélectionne le Depth actif via DepthNeedMatrix et le prochain Domaine ON
     * via DepthTourState. Initialise l'état V2 au premier appel.
     *
     * @param KernelBlueprint $blueprint      Blueprint vide (CREATED_UNENGAGED).
     * @param string|null     $previousDomain Domaine précédent pour avancer le DomainCycle.
     *
     * @return string ROTATION_ASSIGNED | NOT_ENGAGED_PRODUCTION_ON_HOLD
     */
    public function planV2(KernelBlueprint $blueprint, ?string $previousDomain = null): string
    {
        $depthMatrix = new DepthNeedMatrix();
        $state       = DB::table(self::STATE_TABLE_V2)->first();

        // ── Premier appel absolu ──────────────────────────────────────────────
        if ($state === null) {
            $firstDepth = $depthMatrix->nextRequiredDepth(null);

            if ($firstDepth === null) {
                $this->markBlueprintOnHold($blueprint->blueprint_id);
                return self::RESULT_PRODUCTION_ON_HOLD;
            }

            $tourState  = DepthTourState::initTour();
            $nextDomain = $tourState->getNextOnDomain(null);

            DB::table(self::STATE_TABLE_V2)->insert([
                'active_depth'                    => $firstDepth,
                'active_tour_id'                  => (string) Str::orderedUuid(),
                'rotation_status'                 => 'TOUR_IN_PROGRESS',
                'tour_domain_states'              => json_encode($tourState->toArray()),
                'active_blueprint_identity'       => $blueprint->blueprint_id,
                'last_counted_blueprint_identity' => null,
                'lock_version'                    => 1,
                'created_at'                      => now(),
                'updated_at'                      => now(),
            ]);

            $blueprint->fillRotation($firstDepth, (string) $nextDomain);
            return self::RESULT_ROTATION_ASSIGNED;
        }

        // ── État PRODUCTION_ON_HOLD → vérifier si des besoins ont réapparu ──
        if ($state->rotation_status === 'NOT_ENGAGED_PRODUCTION_ON_HOLD') {
            $requiredDepth = $depthMatrix->nextRequiredDepth(
                $state->active_depth !== null ? (int) $state->active_depth : null
            );

            if ($requiredDepth === null) {
                $this->markBlueprintOnHold($blueprint->blueprint_id);
                return self::RESULT_PRODUCTION_ON_HOLD;
            }

            $tourState  = DepthTourState::initTour();
            $nextDomain = $tourState->getNextOnDomain(null);

            DB::table(self::STATE_TABLE_V2)->update([
                'active_depth'              => $requiredDepth,
                'active_tour_id'            => (string) Str::orderedUuid(),
                'rotation_status'           => 'TOUR_IN_PROGRESS',
                'tour_domain_states'        => json_encode($tourState->toArray()),
                'active_blueprint_identity' => $blueprint->blueprint_id,
                'lock_version'              => (int) $state->lock_version + 1,
                'updated_at'                => now(),
            ]);

            $blueprint->fillRotation($requiredDepth, (string) $nextDomain);
            return self::RESULT_ROTATION_ASSIGNED;
        }

        // ── Tour en cours — restaurer DepthTourState ──────────────────────────
        $tourData    = json_decode((string) $state->tour_domain_states, true);
        $tourState   = DepthTourState::fromArray($tourData);
        $activeDepth = (int) $state->active_depth;

        $nextDomain = $tourState->getNextOnDomain($previousDomain);

        if ($nextDomain === null) {
            // Tour épuisé sans signal EMPTY explicite — on ferme proprement
            $this->markBlueprintOnHold($blueprint->blueprint_id);
            return self::RESULT_PRODUCTION_ON_HOLD;
        }

        DB::table(self::STATE_TABLE_V2)->update([
            'active_blueprint_identity' => $blueprint->blueprint_id,
            'lock_version'              => (int) $state->lock_version + 1,
            'updated_at'                => now(),
        ]);

        $blueprint->fillRotation($activeDepth, $nextDomain);
        return self::RESULT_ROTATION_ASSIGNED;
    }

    /**
     * V2 — Applique un signal EMPTY sur un Domaine du Tour courant.
     *
     * Appelé par KernelPipelineOrchestrator lorsque Taxonomy retourne null.
     *
     * Effets dans une transaction atomique :
     *   - Domaine ON → OFF (idempotent)
     *   - Si Tour 8/8 : cycle_completed[active_depth]++ → prochain Depth (ou PRODUCTION_ON_HOLD)
     *
     * @throws RuntimeException STOP si l'état V2 n'est pas initialisé.
     */
    public function applyEmptyTransitionV2(string $emptyDomain): void
    {
        DB::transaction(function () use ($emptyDomain) {
            $state = DB::table(self::STATE_TABLE_V2)->lockForUpdate()->first();

            if ($state === null) {
                throw new RuntimeException(
                    '[KernelRotationPlannerV2] STOP — applyEmptyTransitionV2 '
                    . 'appelé avant initialisation de l\'état V2.'
                );
            }

            if ($state->rotation_status === 'NOT_ENGAGED_PRODUCTION_ON_HOLD') {
                return; // NO-OP
            }

            $tourData  = json_decode((string) $state->tour_domain_states, true);
            $tourState = DepthTourState::fromArray($tourData);

            $newTourState = $tourState->applyEmpty($emptyDomain);
            $activeDepth  = (int) $state->active_depth;

            $depthMatrix = new DepthNeedMatrix();

            if ($newTourState->isTourComplete()) {
                // Tour 8/8 → fermer le Tour, passer au prochain Depth
                $depthMatrix->incrementCycleCompleted($activeDepth);

                $nextDepth = $depthMatrix->nextRequiredDepth($activeDepth);

                if ($nextDepth === null) {
                    DB::table(self::STATE_TABLE_V2)->update([
                        'rotation_status'    => 'NOT_ENGAGED_PRODUCTION_ON_HOLD',
                        'tour_domain_states' => json_encode($newTourState->toArray()),
                        'lock_version'       => (int) $state->lock_version + 1,
                        'updated_at'         => now(),
                    ]);
                } else {
                    $freshTour = DepthTourState::initTour();

                    DB::table(self::STATE_TABLE_V2)->update([
                        'active_depth'       => $nextDepth,
                        'active_tour_id'     => (string) Str::orderedUuid(),
                        'rotation_status'    => 'TOUR_IN_PROGRESS',
                        'tour_domain_states' => json_encode($freshTour->toArray()),
                        'lock_version'       => (int) $state->lock_version + 1,
                        'updated_at'         => now(),
                    ]);
                }
            } else {
                // Tour encore en cours
                DB::table(self::STATE_TABLE_V2)->update([
                    'tour_domain_states' => json_encode($newTourState->toArray()),
                    'lock_version'       => (int) $state->lock_version + 1,
                    'updated_at'         => now(),
                ]);
            }
        });
    }

    /**
     * V2 — Comptabilise la réception d'un Blueprint par ReadyBank.
     *
     * Idempotent via kernel_current_kernel_receipts (PK blueprint_id — DEC-063).
     * En production, déléguer au listener ApplyCurrentKernelReceivedToRotation.
     */
    public function receiveKernelReceivedV2(string $blueprintId, int $depth, string $domain): void
    {
        DB::transaction(function () use ($blueprintId, $depth, $domain) {
            $alreadyReceived = DB::table(self::RECEIPTS_TABLE)
                ->where('blueprint_id', $blueprintId)
                ->exists();

            if ($alreadyReceived) {
                return;
            }

            DB::table(self::RECEIPTS_TABLE)->insert([
                'blueprint_id' => $blueprintId,
                'event_id'     => (string) Str::orderedUuid(),
                'depth'        => $depth,
                'domain_code'  => $domain,
                'received_at'  => now(),
            ]);

            (new DepthNeedMatrix())->incrementKernelReceived($depth, $domain);

            DB::table(self::STATE_TABLE_V2)
                ->whereNotNull('id')
                ->update([
                    'last_counted_blueprint_identity' => $blueprintId,
                    'updated_at'                      => now(),
                ]);
        });
    }

    // =========================================================================
    // Helpers V2 privés
    // =========================================================================

    private function markBlueprintOnHold(?string $blueprintId): void
    {
        if ($blueprintId === null) {
            return;
        }

        DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprintId)
            ->whereIn('execution_state', ['CREATED_UNENGAGED'])
            ->update([
                'execution_state' => 'NOT_ENGAGED_PRODUCTION_ON_HOLD',
                'updated_at'      => now(),
            ]);
    }

    // =========================================================================
    // LEGACY — Initialisation — appelée une seule fois par le worker au démarrage
    // =========================================================================

    /**
     * Initialise l'état de rotation persistant.
     *
     * Si l'état existe déjà → no-op (idempotent).
     * Si $startDepth est null → choisit le depth selon DepthNeedMatrix (interroge question_groups).
     *
     * @throws RuntimeException STOP si aucun depth disponible.
     */
    public function initialize(?int $startDepth = null): void
    {
        if (DB::table(self::STATE_TABLE)->exists()) {
            return;
        }

        if ($startDepth === null) {
            $existingByDepth = $this->loadExistingKernelCounts();
            $matrix          = $this->buildDepthNeedMatrix($existingByDepth);
            $startDepth      = $this->chooseDepth($matrix);
        }

        DB::table(self::STATE_TABLE)->insert([
            'current_depth'            => $startDepth,
            'current_domain_index'     => 0,
            'completed_domains'        => 0,
            'last_rotation_identifier' => null,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    // =========================================================================
    // Point d'entrée principal — une rotation
    // =========================================================================

    /**
     * Produit le contexte de rotation pour la prochaine création de noyau.
     *
     * Mécanisme (par rotation) :
     *   1. Charger l'état persisté
     *   2. Interroger DomainExhaustionChecker : isExhausted(current_depth, current_domain) ?
     *      — UNE seule requête, jamais de scan des 8 domaines
     *   3. Si EXHAUSTED → completed_domains++
     *      a. completed_domains == 8 → depth suivant (DepthNeedMatrix), reset DomainCycle
     *      b. sinon → domaine suivant (DomainCycle.advance)
     *      Persister l'état dans les deux cas.
     *   4. Générer rotation_identifier
     *   5. Retourner depth · domain_code · rotation_identifier
     *
     * @return array{
     *     rotation_context: array{
     *         depth_slot:          array{depth: int},
     *         domain_slot:         array{domain_id: string, domain_code: string},
     *         rotation_identifier: string
     *     }
     * }
     *
     * @throws RuntimeException STOP si état non initialisé ou aucun depth disponible.
     */
    public function plan(DomainExhaustionChecker $checker): array
    {
        $state = DB::table(self::STATE_TABLE)->first();

        if ($state === null) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — état non initialisé. '
                . 'Appeler initialize() avant le premier plan().'
            );
        }

        $domains       = $this->loadDomains();
        $currentDepth  = (int) $state->current_depth;
        $currentIdx    = (int) $state->current_domain_index;
        $currentDomain = $domains[$currentIdx];

        if ($checker->isExhausted($currentDepth, $currentDomain)) {
            $completedDomains = (int) $state->completed_domains + 1;

            if ($completedDomains >= 8) {
                // ── Depth cycle complet → passer au prochain depth ────────────
                $existingByDepth = $this->loadExistingKernelCounts();
                $matrix          = $this->buildDepthNeedMatrix($existingByDepth);
                $nextDepth       = $this->chooseDepth($matrix);

                DB::table(self::STATE_TABLE)->update([
                    'current_depth'        => $nextDepth,
                    'current_domain_index' => 0,
                    'completed_domains'    => 0,
                    'updated_at'           => now(),
                ]);

                $currentDepth  = $nextDepth;
                $currentDomain = $domains[0];
            } else {
                // ── Avancer vers le prochain domaine dans le DomainCycle ──────
                $nextIdx = $this->advanceDomainIndex($currentIdx, $domains);

                DB::table(self::STATE_TABLE)->update([
                    'current_domain_index' => $nextIdx,
                    'completed_domains'    => $completedDomains,
                    'updated_at'           => now(),
                ]);

                $currentDomain = $domains[$nextIdx];
            }
        }

        $rotationIdentifier = (string) Str::uuid();

        DB::table(self::STATE_TABLE)->update([
            'last_rotation_identifier' => $rotationIdentifier,
            'updated_at'               => now(),
        ]);

        return [
            'rotation_context' => [
                'depth_slot'          => ['depth' => $currentDepth],
                'domain_slot'         => ['domain_id' => $currentDomain, 'domain_code' => $currentDomain],
                'rotation_identifier' => $rotationIdentifier,
            ],
        ];
    }

    // =========================================================================
    // DepthNeedMatrix — calcul pur (testable sans base de données)
    // =========================================================================

    /**
     * Construit la matrice des besoins par depth à partir des comptes fournis.
     *
     * Calcul pur — ne touche jamais la base de données.
     * En production, l'appelant passe loadExistingKernelCounts() comme source.
     * En test, on injecte directement les comptes.
     *
     * @param  array<int, int>  $existingByDepth  Ex : [4 => 2000, 6 => 500]
     * @return array<int, array{
     *     depth:             int,
     *     target_kernels:    int,
     *     existing_kernels:  int,
     *     remaining_kernels: int,
     *     completed:         bool
     * }>
     */
    public function buildDepthNeedMatrix(array $existingByDepth = []): array
    {
        $matrix = [];

        foreach (self::DEPTH_TARGETS as $depth => $target) {
            $existing  = (int) ($existingByDepth[$depth] ?? 0);
            $remaining = max(0, $target - $existing);

            $matrix[] = [
                'depth'             => $depth,
                'target_kernels'    => $target,
                'existing_kernels'  => $existing,
                'remaining_kernels' => $remaining,
                'completed'         => $remaining === 0,
            ];
        }

        return $matrix;
    }

    // =========================================================================
    // Sélection du depth
    // =========================================================================

    /**
     * Choisit le depth ayant le plus grand remaining_kernels.
     * En cas d'égalité : depth le plus bas.
     * STOP si aucun remaining > 0 ou matrice vide.
     *
     * @param  array<int, array{depth: int, remaining_kernels: int}>  $matrix
     *
     * @throws RuntimeException STOP si aucun depth disponible.
     */
    public function chooseDepth(array $matrix): int
    {
        if (empty($matrix)) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — DepthNeedMatrix absente ou invalide.'
            );
        }

        $candidates = array_values(array_filter(
            $matrix,
            static fn($row) => (int) ($row['remaining_kernels'] ?? 0) > 0
        ));

        if (empty($candidates)) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — aucun depth restant. Tous les targets sont atteints.'
            );
        }

        usort($candidates, static function (array $a, array $b): int {
            $cmp = (int) $b['remaining_kernels'] <=> (int) $a['remaining_kernels'];
            return $cmp !== 0 ? $cmp : ((int) $a['depth'] <=> (int) $b['depth']);
        });

        $depth = (int) $candidates[0]['depth'];

        if (!in_array($depth, self::ALLOWED_DEPTHS, true)) {
            throw new RuntimeException(
                "[KernelRotationPlanner] STOP — depth choisi ({$depth}) hors de la liste autorisée [4,6,7,8,9]."
            );
        }

        return $depth;
    }

    // =========================================================================
    // DomainCycle
    // =========================================================================

    /**
     * Charge la liste officielle des 8 domaines Gameplay.
     *
     * DomainCycle Gameplay v1 — ordre officiel figé :
     *   histoire, geographie, sport, art, cuisine, science, cinema, faune
     *
     * "general" n'est PAS un domaine Gameplay et n'apparaît jamais ici.
     *
     * @return string[]
     * @throws RuntimeException STOP si liste vide.
     */
    public function loadDomains(): array
    {
        $domains = [
            'histoire',
            'geographie',
            'sport',
            'art',
            'cuisine',
            'science',
            'cinema',
            'faune',
        ];

        if (empty($domains)) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — DomainCycle absent. '
                . 'La liste de domaines Gameplay est vide.'
            );
        }

        return $domains;
    }

    /**
     * Avance d'une position dans le cycle domaine.
     *
     * Règles :
     *   null  → retourne 0 (début du cycle)
     *   last  → retourne 0 (bouclage)
     *   other → retourne currentIndex + 1
     *
     * Aucun saut. Aucune optimisation. Aucune heuristique.
     *
     * @param  int|null  $currentIndex  null = début du cycle
     * @param  string[]  $domains
     *
     * @throws RuntimeException STOP si liste vide.
     */
    public function advanceDomainIndex(?int $currentIndex, array $domains): int
    {
        $count = count($domains);

        if ($count === 0) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — liste de domaines vide.'
            );
        }

        if ($currentIndex === null) {
            return 0;
        }

        return ($currentIndex + 1) % $count;
    }

    // =========================================================================
    // Chargement DB (production uniquement — isolé pour testabilité)
    // =========================================================================

    /**
     * Charge le nombre de noyaux existants par depth depuis question_groups.
     *
     * @return array<int, int>  Ex : [4 => 1200, 6 => 300]
     */
    private function loadExistingKernelCounts(): array
    {
        return DB::table('question_groups')
            ->select('difficulty_depth', DB::raw('COUNT(*) as n'))
            ->whereIn('difficulty_depth', self::ALLOWED_DEPTHS)
            ->groupBy('difficulty_depth')
            ->get()
            ->pluck('n', 'difficulty_depth')
            ->toArray();
    }
}
