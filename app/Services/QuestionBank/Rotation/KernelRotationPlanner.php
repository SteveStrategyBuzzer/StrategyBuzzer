<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KernelRotationPlanner — machine d'état persistante de la rotation globale.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * INTERFACE V3 (02_KernelRotationPlanner v3.2 — VERROUILLÉ 2026-08-13)
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 *   resolveNextRotation(?object $state) : RotationResolution
 *     Gate : lit l'état V2 (reçu verrouillé depuis l'orchestrateur).
 *     Retourne RotationResolution::available ou ::noRotation.
 *     NE déclenche JAMAIS DOMAIN_EXHAUSTED ni DEPTH_EXHAUSTED (DEC-087 / DEC-089).
 *
 *   registerActiveBlueprintIdentity(string $blueprintId, ?object $state) : void
 *     Persiste active_blueprint_identity dans l'état V2.
 *     Crée la ligne si absente (premier appel absolu).
 *     DOIT être appelée à l'intérieur de la transaction FOR UPDATE de l'orchestrateur.
 *
 *   applyRotation(KernelBlueprint $blueprint, int $depth, string $domain, int $domainPosition) : void
 *     Appelée UNE SEULE FOIS (KRP-R01) quand Taxonomy confirme un territoire.
 *     Écrit depth + domain dans le Blueprint (fillRotation — write-once).
 *     Persiste active_depth + domain_position dans l'état V2.
 *
 *   applyEmptyAndGetNext(int $emptyDepth, string $emptyDomain) : array
 *     ⚠ LEGACY SUPERSEDED — conservé jusqu'à LOT C (branchement Taxonomy → KRP).
 *     Avance le DomainCycle via tour_domain_states (DepthTourState).
 *     Retourne [int $newDepth, ?string $newDomain] ; null = PRODUCTION_ON_HOLD.
 *
 *   receiveDomainExhausted(int $depth, string $domain) : void
 *     Reçoit le signal DOMAIN_EXHAUSTED de Taxonomy (LOT C → produit en V4).
 *     Marque domain_states[depth][domain] = DOMAIN_EXHAUSTED (idempotent).
 *
 *   receiveDepthExhausted(int $depth) : void
 *     Reçoit le signal DEPTH_EXHAUSTED de Taxonomy (LOT C → produit en V4).
 *     Mémorise pending_depth_exhausted_depth.
 *
 *   receiveKernelReceivedV2(string $blueprintId, int $depth, string $domain) : void
 *     Comptabilisation idempotente + vérification transition pending (DEC-093).
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * RÈGLES CLÉS
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 *   KRP-R01  fillRotation = write-once ; appel unique dans applyRotation().
 *   DEC-087  KRP ne produit JAMAIS DOMAIN_EXHAUSTED.
 *   DEC-089  KRP ne produit JAMAIS DEPTH_EXHAUSTED.
 *   DEC-091  domain_position = index (0-7) du dernier domaine sélectionné.
 *            NULL = non démarré → prochain domaine = 'geographie' (index 0).
 *   DEC-093  Dans receiveKernelReceivedV2 : compteur AVANT vérification transition.
 */
class KernelRotationPlanner
{
    // =========================================================================
    // Tables
    // =========================================================================

    private const STATE_TABLE_V2 = 'kernel_rotation_state_v2';
    private const RECEIPTS_TABLE = 'kernel_current_kernel_receipts';

    // =========================================================================
    // Constantes publiques
    // =========================================================================

    /** Résultat de resolveNextRotation : rotation disponible. */
    public const RESULT_ROTATION_ASSIGNED  = 'ROTATION_ASSIGNED';

    /** Résultat de resolveNextRotation / applyEmptyAndGetNext : aucun besoin actif. */
    public const RESULT_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    // =========================================================================
    // V3 — Gate : résolution de la rotation
    // =========================================================================

    /**
     * Résout la prochaine rotation disponible.
     *
     * CONTRAT D'APPEL : doit être appelée DEPUIS UNE TRANSACTION avec verrou
     * FOR UPDATE sur kernel_rotation_state_v2 (cf. KernelPipelineOrchestrator).
     *
     * @param object|null $state  Ligne verrouillée, ou null au premier appel absolu.
     */
    public function resolveNextRotation(?object $state): RotationResolution
    {
        // ── Gate 1 : PRODUCTION_ON_HOLD explicite ─────────────────────────────
        $depthState = $state?->depth_state ?? 'ROTATION_ACTIVE';

        if ($depthState === 'PRODUCTION_ON_HOLD') {
            return RotationResolution::noRotation('PRODUCTION_ON_HOLD');
        }

        // ── Depth actif ───────────────────────────────────────────────────────
        $activeDepth    = $state?->active_depth !== null ? (int) $state->active_depth : null;
        $domainPosition = $state?->domain_position !== null ? (int) $state->domain_position : null;
        $depth          = $activeDepth ?? DepthNeedMatrix::DEPTH_CYCLE[0]; // 2

        // ── domain_states (nouveau schéma, initié en V3) ──────────────────────
        $domainStates = $this->loadDomainStates($state);
        $pendingDepth = $state?->pending_depth_exhausted_depth !== null
                        ? (int) $state->pending_depth_exhausted_depth
                        : null;

        // ── Sélection du prochain Domaine dans le cycle ───────────────────────
        $next = $this->selectNextDomain($depth, $domainPosition, $domainStates, $pendingDepth);

        if ($next === null) {
            return RotationResolution::noRotation('PENDING_DEPTH_TRANSITION');
        }

        return RotationResolution::available($depth, $next['domain'], $next['index']);
    }

    /**
     * Sélectionne le prochain Domaine disponible dans le DomainCycle.
     *
     * Règle (DEC-091) :
     *   - Part de (domain_position ?? -1), avance de 1 circulairement.
     *   - Ignore les domaines marqués DOMAIN_EXHAUSTED.
     *   - Retourne null si tous DOMAIN_EXHAUSTED ET pending_depth_exhausted_depth ≠ null.
     *
     * @return array{domain: string, index: int}|null
     * @throws RuntimeException INCOHÉRENCE_ÉTAT si tous exhausted mais sans pending.
     */
    private function selectNextDomain(
        int   $depth,
        ?int  $position,
        array $domainStates,
        ?int  $pendingDepth,
    ): ?array {
        $cycle    = DepthTourState::DOMAIN_CYCLE;
        $pos      = $position ?? -1;
        $depthKey = (string) $depth;

        for ($offset = 1; $offset <= 8; $offset++) {
            $index     = ($pos + $offset) % 8;
            $candidate = $cycle[$index];
            $status    = $domainStates[$depthKey][$candidate] ?? 'ACTIF';

            if ($status !== 'DOMAIN_EXHAUSTED') {
                return ['domain' => $candidate, 'index' => $index];
            }
        }

        // Tous les 8 Domaines sont DOMAIN_EXHAUSTED
        if ($pendingDepth === null) {
            throw new RuntimeException(
                "[KRP] INCOHÉRENCE_ÉTAT : tous les domaines de Depth {$depth} sont DOMAIN_EXHAUSTED "
                . "mais aucun signal DEPTH_EXHAUSTED n'est mémorisé. "
                . "Taxonomy est l'unique producteur de ce signal (DEC-087/DEC-089)."
            );
        }

        return null; // PENDING_DEPTH_TRANSITION — attendre le prochain CKR
    }

    // =========================================================================
    // V3 — Enregistrement de l'identité Blueprint (à l'intérieur de la transaction)
    // =========================================================================

    /**
     * Enregistre l'identité du Blueprint dans kernel_rotation_state_v2.
     *
     * CONTRAT D'APPEL : doit être appelée DEPUIS LA MÊME TRANSACTION que
     * resolveNextRotation (verrou FOR UPDATE maintenu).
     *
     * Si aucune ligne n'existe → INSERT (premier appel absolu) :
     *   - depth_state = ROTATION_ACTIVE
     *   - domain_states = 7 Depths × 8 Domaines × ACTIF (56 paires)
     *   - tour_domain_states = DepthTourState initialisé (legacy EMPTY loop)
     *   - active_depth = null (écrit par applyRotation après territoire confirmé)
     *
     * @param object|null $state  Ligne verrouillée lue par resolveNextRotation, ou null.
     */
    public function registerActiveBlueprintIdentity(string $blueprintId, ?object $state): void
    {
        if ($state === null) {
            DB::table(self::STATE_TABLE_V2)->insert([
                'depth_state'                    => 'ROTATION_ACTIVE',
                'active_depth'                   => null,
                'domain_position'                => null,
                'domain_states'                  => json_encode($this->buildInitialDomainStates()),
                'tour_domain_states'             => json_encode(DepthTourState::initTour()->toArray()),
                'active_blueprint_identity'      => $blueprintId,
                'last_counted_blueprint_identity' => null,
                'pending_depth_exhausted_depth'  => null,
                'lock_version'                   => 1,
                'created_at'                     => now(),
                'updated_at'                     => now(),
            ]);
        } else {
            DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                'active_blueprint_identity' => $blueprintId,
                'lock_version'              => (int) $state->lock_version + 1,
                'updated_at'                => now(),
            ]);
        }
    }

    // =========================================================================
    // V3 — Application finale de la rotation (write-once)
    // =========================================================================

    /**
     * Applique la rotation confirmée sur le Blueprint et persiste l'état final.
     *
     * Appelée UNE SEULE FOIS (KRP-R01), après que Taxonomy a fourni un territoire
     * non-null. Le Blueprint reçoit son depth + domain via fillRotation (write-once).
     *
     * @param int $domainPosition  Index (0-7) du domaine dans DepthTourState::DOMAIN_CYCLE.
     */
    public function applyRotation(
        KernelBlueprint $blueprint,
        int             $depth,
        string          $domain,
        int             $domainPosition,
    ): void {
        // Write-once sur le Blueprint (KRP-R01)
        $blueprint->fillRotation($depth, $domain);

        // Persister depth + domain_position dans l'état V2
        DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
            'active_depth'    => $depth,
            'domain_position' => $domainPosition,
            'updated_at'      => now(),
        ]);
    }

    // =========================================================================
    // V3 — EMPTY loop (legacy SUPERSEDED — conservée jusqu'à LOT C)
    // =========================================================================

    /**
     * Applique un signal EMPTY sur un Domaine et retourne le prochain [depth, domain].
     *
     * ⚠ Architecture SUPERSEDED — LOT C supprimera cette méthode lors du branchement
     * des signaux Taxonomy → KRP (receiveDomainExhausted / receiveDepthExhausted).
     *
     * Mécanisme legacy (DepthTourState / tour_domain_states) :
     *   - Domaine ON → OFF dans le tour courant.
     *   - Tour 8/8 : cycle_completed++ → prochain Depth (ou PRODUCTION_ON_HOLD).
     *   - Tour incomplet : retourne le prochain Domaine ON.
     *
     * @return array{0: int, 1: string|null}
     *   [newDepth, newDomain] — $newDomain === null = PRODUCTION_ON_HOLD.
     */
    public function applyEmptyAndGetNext(int $emptyDepth, string $emptyDomain): array
    {
        $result = [$emptyDepth, null];

        DB::transaction(function () use ($emptyDepth, $emptyDomain, &$result) {
            $state = DB::table(self::STATE_TABLE_V2)->lockForUpdate()->first();

            if ($state === null) {
                throw new RuntimeException(
                    '[KRP] STOP — applyEmptyAndGetNext appelé sans état V2 initialisé.'
                );
            }

            $tourData  = json_decode((string) ($state->tour_domain_states ?? '{}'), true);
            $tourState = $this->makeTourState($tourData);

            $newTourState = $tourState->applyEmpty($emptyDomain);

            if ($newTourState->isTourComplete()) {
                // ── Tour 8/8 → avancer au Depth suivant ──────────────────────
                $depthMatrix = new DepthNeedMatrix();
                $depthMatrix->incrementCycleCompleted($emptyDepth);
                $nextDepth = $depthMatrix->nextRequiredDepth($emptyDepth);

                if ($nextDepth === null) {
                    // Tous les Depths saturés → PRODUCTION_ON_HOLD
                    DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                        'depth_state'        => 'PRODUCTION_ON_HOLD',
                        'tour_domain_states' => json_encode($newTourState->toArray()),
                        'lock_version'       => (int) $state->lock_version + 1,
                        'updated_at'         => now(),
                    ]);
                    $result = [$emptyDepth, null];
                } else {
                    $freshTour  = DepthTourState::initTour();
                    $nextDomain = $freshTour->getNextOnDomain(null); // 'geographie'

                    DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                        'active_depth'       => $nextDepth,
                        'depth_state'        => 'ROTATION_ACTIVE',
                        'tour_domain_states' => json_encode($freshTour->toArray()),
                        'lock_version'       => (int) $state->lock_version + 1,
                        'updated_at'         => now(),
                    ]);
                    $result = [$nextDepth, $nextDomain];
                }
            } else {
                // ── Tour encore en cours → prochain Domaine ON ────────────────
                $nextDomain = $newTourState->getNextOnDomain($emptyDomain);

                DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                    'tour_domain_states' => json_encode($newTourState->toArray()),
                    'lock_version'       => (int) $state->lock_version + 1,
                    'updated_at'         => now(),
                ]);
                $result = [$emptyDepth, $nextDomain];
            }
        });

        return $result;
    }

    // =========================================================================
    // V3 — Réception des signaux Taxonomy (LOT C — interfaces actives)
    // =========================================================================

    /**
     * Reçoit le signal DOMAIN_EXHAUSTED émis par Taxonomy.
     *
     * Marque domain_states[depth][domain] = DOMAIN_EXHAUSTED dans une transaction
     * atomique. Idempotent : un second appel pour le même (depth, domain) est un NO-OP.
     *
     * Taxonomy est l'unique producteur de ce signal (DEC-087).
     *
     * @throws RuntimeException domaine inconnu ou état non initialisé.
     */
    public function receiveDomainExhausted(int $depth, string $domain): void
    {
        if (!in_array($domain, DepthTourState::DOMAIN_CYCLE, true)) {
            throw new RuntimeException(
                "[KRP] receiveDomainExhausted — domaine inconnu : '{$domain}'. "
                . 'Domaines valides : ' . implode(', ', DepthTourState::DOMAIN_CYCLE) . '.'
            );
        }

        DB::transaction(function () use ($depth, $domain) {
            $state = DB::table(self::STATE_TABLE_V2)->lockForUpdate()->first();

            if ($state === null) {
                throw new RuntimeException(
                    '[KRP] STOP — receiveDomainExhausted appelé sans état V2 initialisé.'
                );
            }

            $domainStates = json_decode((string) ($state->domain_states ?? '{}'), true) ?: [];
            $depthKey     = (string) $depth;

            // Idempotence
            if (($domainStates[$depthKey][$domain] ?? 'ACTIF') === 'DOMAIN_EXHAUSTED') {
                return;
            }

            $domainStates[$depthKey][$domain] = 'DOMAIN_EXHAUSTED';

            DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                'domain_states' => json_encode($domainStates),
                'lock_version'  => (int) $state->lock_version + 1,
                'updated_at'    => now(),
            ]);
        });
    }

    /**
     * Reçoit le signal DEPTH_EXHAUSTED émis par Taxonomy.
     *
     * Mémorise pending_depth_exhausted_depth. La transition vers le Depth suivant
     * sera appliquée au prochain receiveKernelReceivedV2() (DEC-093).
     *
     * Taxonomy est l'unique producteur de ce signal (DEC-089).
     *
     * Invariants vérifiés :
     *   - Si un autre pending est mémorisé → ERREUR D'INVARIANT (RuntimeException).
     *   - Si active_depth ≠ depth (non null) → INCOHÉRENCE_ÉTAT (RuntimeException).
     *   - Même depth déjà mémorisé → NO-OP idempotent.
     *
     * @throws RuntimeException violation d'invariant ou incohérence d'état.
     */
    public function receiveDepthExhausted(int $depth): void
    {
        DB::transaction(function () use ($depth) {
            $state = DB::table(self::STATE_TABLE_V2)->lockForUpdate()->first();

            if ($state === null) {
                throw new RuntimeException(
                    '[KRP] STOP — receiveDepthExhausted appelé sans état V2 initialisé.'
                );
            }

            $pending     = $state->pending_depth_exhausted_depth !== null
                           ? (int) $state->pending_depth_exhausted_depth
                           : null;
            $activeDepth = $state->active_depth !== null ? (int) $state->active_depth : null;

            // Idempotence — même signal reçu deux fois pour le même Depth
            if ($pending === $depth) {
                return;
            }

            // Invariant : un seul signal pending à la fois
            if ($pending !== null) {
                throw new RuntimeException(
                    "[KRP] ERREUR D'INVARIANT — receiveDepthExhausted(depth={$depth}) "
                    . "mais pending_depth_exhausted_depth={$pending} déjà mémorisé. "
                    . "Taxonomy doit émettre les signaux en ordre strict."
                );
            }

            // Cohérence : le Depth du signal doit correspondre au Depth actif
            if ($activeDepth !== null && $activeDepth !== $depth) {
                throw new RuntimeException(
                    "[KRP] INCOHÉRENCE_ÉTAT — receiveDepthExhausted(depth={$depth}) "
                    . "mais active_depth={$activeDepth}. "
                    . "Le signal DEPTH_EXHAUSTED doit correspondre au Depth actif (DEC-090)."
                );
            }

            DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                'pending_depth_exhausted_depth' => $depth,
                'lock_version'                  => (int) $state->lock_version + 1,
                'updated_at'                    => now(),
            ]);
        });
    }

    /**
     * Comptabilise la réception d'un Blueprint par ReadyBank.
     *
     * Idempotent via kernel_current_kernel_receipts (PK blueprint_id — DEC-063).
     *
     * ORDRE OBLIGATOIRE (DEC-093) :
     *   1. INSERT receipt (idempotence)
     *   2. incrementKernelReceived (compteur)
     *   3. Mise à jour last_counted_blueprint_identity
     *   4. Vérification transition pending_depth_exhausted_depth → applyDepthTransition
     *
     * Chemin production : ProcessKernelPipelineOutbox → receiveKernelReceivedV2 (DEC-093).
     */
    public function receiveKernelReceivedV2(string $blueprintId, int $depth, string $domain): void
    {
        DB::transaction(function () use ($blueprintId, $depth, $domain) {
            // ── 1. Idempotence ────────────────────────────────────────────────
            $alreadyReceived = DB::table(self::RECEIPTS_TABLE)
                ->where('blueprint_id', $blueprintId)
                ->exists();

            if ($alreadyReceived) {
                return;
            }

            // ── 2. Insérer le reçu ────────────────────────────────────────────
            DB::table(self::RECEIPTS_TABLE)->insert([
                'blueprint_id' => $blueprintId,
                'event_id'     => (string) Str::orderedUuid(),
                'depth'        => $depth,
                'domain_code'  => $domain,
                'received_at'  => now(),
            ]);

            // ── 3. Incrémenter kernel_received_total (AVANT transition) ────────
            (new DepthNeedMatrix())->incrementKernelReceived($depth, $domain);

            // ── 4. Mettre à jour last_counted_blueprint_identity ──────────────
            DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                'last_counted_blueprint_identity' => $blueprintId,
                'updated_at'                      => now(),
            ]);

            // ── 5. Vérifier si une transition de Depth est en attente ─────────
            $state   = DB::table(self::STATE_TABLE_V2)->first();
            $pending = $state?->pending_depth_exhausted_depth !== null
                       ? (int) $state->pending_depth_exhausted_depth
                       : null;

            if ($pending !== null && $pending === $depth) {
                $this->applyDepthTransition($state, $pending);
            }
        });
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * Applique la transition vers le Depth suivant après traitement d'un signal
     * DEPTH_EXHAUSTED en attente.
     *
     * Appelé depuis receiveKernelReceivedV2() uniquement.
     * Réinitialise domain_position et pending_depth_exhausted_depth.
     */
    private function applyDepthTransition(object $state, int $exhaustedDepth): void
    {
        $depthMatrix = new DepthNeedMatrix();
        $depthMatrix->incrementCycleCompleted($exhaustedDepth);
        $nextDepth = $depthMatrix->nextRequiredDepth($exhaustedDepth);

        $domainStates = json_decode((string) ($state->domain_states ?? '{}'), true) ?: [];

        $updates = [
            'pending_depth_exhausted_depth' => null,
            'domain_position'               => null,
            'lock_version'                  => (int) $state->lock_version + 1,
            'updated_at'                    => now(),
        ];

        if ($nextDepth !== null) {
            // Réinitialiser domain_states pour le nouveau Depth (56 → ACTIF uniquement ce Depth)
            $newDepthKey = (string) $nextDepth;
            foreach (DepthTourState::DOMAIN_CYCLE as $d) {
                $domainStates[$newDepthKey][$d] = 'ACTIF';
            }
            $updates['active_depth']  = $nextDepth;
            $updates['domain_states'] = json_encode($domainStates);
            $updates['depth_state']   = 'ROTATION_ACTIVE';
        } else {
            $updates['depth_state'] = 'PRODUCTION_ON_HOLD';
        }

        DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update($updates);
    }

    /**
     * Charge et décode domain_states depuis l'état V2.
     *
     * @return array<string, array<string, string>>
     */
    private function loadDomainStates(?object $state): array
    {
        if ($state === null || empty($state->domain_states)) {
            return [];
        }
        $decoded = json_decode((string) $state->domain_states, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Construit domain_states initiaux : 7 Depths × 8 Domaines = 56 entrées ACTIF.
     *
     * @return array<string, array<string, string>>
     */
    private function buildInitialDomainStates(): array
    {
        $states = [];
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $key          = (string) $depth;
            $states[$key] = [];
            foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
                $states[$key][$domain] = 'ACTIF';
            }
        }
        return $states; // 7 × 8 = 56 paires
    }

    /**
     * Reconstruit un DepthTourState depuis un tableau persisté (legacy EMPTY loop).
     * Retourne un tour frais si les données sont absentes.
     */
    private function makeTourState(?array $data): DepthTourState
    {
        if (empty($data) || !isset($data['states'])) {
            return DepthTourState::initTour();
        }
        return DepthTourState::fromArray($data);
    }
}
