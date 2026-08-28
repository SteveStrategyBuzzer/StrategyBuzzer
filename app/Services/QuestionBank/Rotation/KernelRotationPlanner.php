<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KernelRotationPlanner — KRP v4.0 / DEC-119.
 *
 * Taxonomy's future responsibility is limited to declaring an immutable terminal
 * fact (fact_id, depth, domain). KRP validates it against its active Blueprint
 * and captures the active tour internally, then consumes it only when a new
 * Factory Blueprint is being prepared. KRP owns the whole lifecycle:
 *
 *   terminal fact -> DOMAIN_EXHAUSTED -> VISIBLE/ESTOMPÉ
 *                 -> DEPTH_EXHAUSTED (last visible Domain only)
 *                 -> close tour + DepthNeedMatrix + next Depth or HOLD.
 *
 * KRP is the sole writer of Blueprint depth + domain.
 */
final class KernelRotationPlanner
{
    private const STATE_TABLE_V2 = 'kernel_rotation_state_v2';
    private const RECEIPTS_TABLE = 'kernel_current_kernel_receipts';

    public const RESULT_ROTATION_ASSIGNED = 'ROTATION_ASSIGNED';
    public const RESULT_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    private const DEPTH_STATE_ACTIVE = 'ROTATION_ACTIVE';
    private const DEPTH_STATE_HOLD = 'PRODUCTION_ON_HOLD';
    private const TOUR_OPEN = 'OPEN';
    private const TOUR_CLOSED = 'CLOSED';
    private const DOMAIN_VISIBLE = 'VISIBLE';
    private const DOMAIN_ESTOMPE = 'ESTOMPÉ';

    private KernelRotationStateRepository $stateRepository;
    private KernelTerminalFactRepository $terminalFactRepository;
    private DepthNeedMatrix $depthNeedMatrix;

    public function __construct(
        ?KernelRotationStateRepository $stateRepository = null,
        ?KernelTerminalFactRepository $terminalFactRepository = null,
        ?DepthNeedMatrix $depthNeedMatrix = null,
    ) {
        $this->stateRepository = $stateRepository ?? new KernelRotationStateRepository();
        $this->terminalFactRepository = $terminalFactRepository ?? new KernelTerminalFactRepository();
        $this->depthNeedMatrix = $depthNeedMatrix ?? new DepthNeedMatrix();
    }

    /**
     * KRP's public boundary for the future Taxonomy v1.1 producer.
     *
     * A replay with the same immutable fact identity is persisted as a NO-OP.
     * The fact is deliberately not processed here: it is consumed only while
     * preparing the next Factory Blueprint.
     */
    public function receiveTaxonomyTerminalFact(string $factId, int $depth, string $domain): void
    {
        $this->assertDepth($depth);
        $this->assertDomain($domain);

        if ($factId === '') {
            throw new RuntimeException('[KRP] fact_id terminal requis.');
        }

        DB::transaction(function () use ($factId, $depth, $domain) {
            $existing = $this->terminalFactRepository->lockByFactId($factId);

            if ($existing !== null) {
                if ((int) $existing->depth !== $depth
                    || (string) $existing->domain_code !== $domain) {
                    throw new RuntimeException(
                        "[KRP] Violation d'immuabilité du fait terminal {$factId}."
                    );
                }

                return;
            }

            $state = $this->stateRepository->firstForUpdate();

            if ($state === null
                || $state->depth_state === self::DEPTH_STATE_HOLD
                || $state->active_depth === null
                || $state->active_tour_id === null
                || $state->active_blueprint_identity === null) {
                throw new RuntimeException(
                    '[KRP] Fait terminal refusé : aucun Blueprint KRP actif à corréler.'
                );
            }

            $activeDepth = (int) $state->active_depth;
            $activePosition = $state->domain_position !== null
                ? (int) $state->domain_position
                : null;
            $activeDomain = $activePosition !== null
                ? DepthTourState::DOMAIN_CYCLE[$activePosition] ?? null
                : null;

            if ($activeDepth !== $depth || $activeDomain !== $domain) {
                throw new RuntimeException(
                    '[KRP] Fait terminal refusé : depth/domain ne correspondent pas '
                    . 'au Blueprint KRP actif.'
                );
            }

            $this->terminalFactRepository->record(
                $factId,
                $depth,
                $domain,
                (string) $state->active_tour_id,
            );
        });
    }

    /**
     * Resolves a rotation without consuming terminal facts.
     *
     * This remains available for read-only callers. The production Factory path
     * must use prepareNewBlueprint(), which consumes exactly one pending fact.
     */
    public function resolveNextRotation(?object $state): RotationResolution
    {
        if ($this->isProductionOnHold($state)) {
            return RotationResolution::noRotation(self::RESULT_PRODUCTION_ON_HOLD);
        }

        if ($state === null) {
            return RotationResolution::available(
                DepthNeedMatrix::DEPTH_CYCLE[0],
                DepthTourState::DOMAIN_CYCLE[0],
                0,
            );
        }

        $depth = $state->active_depth !== null
            ? (int) $state->active_depth
            : DepthNeedMatrix::DEPTH_CYCLE[0];
        $domainStates = $this->loadDomainStates($state);
        $next = $this->selectNextVisibleDomain(
            $depth,
            $state->domain_position !== null ? (int) $state->domain_position : null,
            $domainStates,
        );

        if ($next === null) {
            throw new RuntimeException(
                '[KRP] État de tour fermé rencontré hors de DOMAIN_EXHAUSTED.'
            );
        }

        return RotationResolution::available($depth, $next['domain'], $next['index']);
    }

    /**
     * Returns true only when the persisted DEPTH_EXHAUSTED result is HOLD.
     */
    public function isProductionOnHold(?object $state): bool
    {
        return $state?->depth_state === self::DEPTH_STATE_HOLD;
    }

    /**
     * Factory -> KRP entry point. Must run in the transaction that owns the
     * rotation-state row lock.
     *
     * It consumes one terminal fact for the active Depth, runs internal
     * DOMAIN_EXHAUSTED / DEPTH_EXHAUSTED as required, selects the next visible
     * Domain, and writes depth + domain once into this new Blueprint.
     */
    public function prepareNewBlueprint(KernelBlueprint $blueprint, ?object $lockedState): RotationResolution
    {
        $state = $this->ensureInitializedState($lockedState);

        if ($this->isProductionOnHold($state)) {
            return RotationResolution::noRotation(self::RESULT_PRODUCTION_ON_HOLD);
        }

        $state = $this->consumeOnePendingTerminalFact($state);

        if ($this->isProductionOnHold($state)) {
            return RotationResolution::noRotation(self::RESULT_PRODUCTION_ON_HOLD);
        }

        $depth = (int) $state->active_depth;
        $domainStates = $this->loadDomainStates($state);
        $next = $this->selectNextVisibleDomain(
            $depth,
            $state->domain_position !== null ? (int) $state->domain_position : null,
            $domainStates,
        );

        if ($next === null) {
            throw new RuntimeException(
                '[KRP] INCOHÉRENCE : aucun Domain VISIBLE après traitement du tour ouvert.'
            );
        }

        $blueprint->fillRotation($depth, $next['domain']);

        $this->stateRepository->updateWithLock($state, [
            'active_blueprint_identity' => $blueprint->blueprint_id,
            'active_depth'              => $depth,
            'domain_position'           => $next['index'],
            'updated_at'                => now(),
        ]);

        return RotationResolution::available($depth, $next['domain'], $next['index']);
    }

    /**
     * Legacy compatibility entry. New production code uses prepareNewBlueprint.
     */
    public function registerActiveBlueprintIdentity(string $blueprintId, ?object $state): void
    {
        $state = $this->ensureInitializedState($state);

        $this->stateRepository->updateWithLock($state, [
            'active_blueprint_identity' => $blueprintId,
        ]);
    }

    /**
     * Legacy compatibility entry. New production code writes the Blueprint
     * inside prepareNewBlueprint so its state update shares the same lock.
     */
    public function applyRotation(
        KernelBlueprint $blueprint,
        int $depth,
        string $domain,
        int $domainPosition,
    ): void {
        $this->assertDepth($depth);
        $this->assertDomain($domain);

        DB::transaction(function () use ($blueprint, $depth, $domain, $domainPosition) {
            $state = $this->ensureInitializedState($this->stateRepository->firstForUpdate());
            $blueprint->fillRotation($depth, $domain);

            $this->stateRepository->updateWithLock($state, [
                'active_depth'              => $depth,
                'domain_position'           => $domainPosition,
                'active_blueprint_identity' => $blueprint->blueprint_id,
            ]);
        });
    }

    /**
     * v3 external signal is intentionally disabled. Future Taxonomy must call
     * receiveTaxonomyTerminalFact() with its own immutable fact identity.
     */
    public function receiveDomainExhausted(int $depth, string $domain): void
    {
        throw new RuntimeException(
            '[KRP] Entrée v3 receiveDomainExhausted interdite. '
            . 'Utiliser receiveTaxonomyTerminalFact(fact_id, depth, domain).'
        );
    }

    /**
     * DEPTH_EXHAUSTED is internal to KRP in v4.0.
     */
    public function receiveDepthExhausted(int $depth): void
    {
        throw new RuntimeException(
            '[KRP] DEPTH_EXHAUSTED est interne à KRP v4.0 et ne peut pas être reçu.'
        );
    }

    /**
     * READY_BANK receipt accounting remains idempotent and does not decide any
     * lifecycle transition. DEPTH_EXHAUSTED is processed only from a terminal
     * fact during the next Factory Blueprint preparation.
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

            DB::statement('SAVEPOINT ckr_receipt_insert');
            try {
                DB::table(self::RECEIPTS_TABLE)->insert([
                    'blueprint_id' => $blueprintId,
                    'event_id'     => (string) Str::orderedUuid(),
                    'depth'        => $depth,
                    'domain_code'  => $domain,
                    'received_at'  => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                DB::statement('ROLLBACK TO SAVEPOINT ckr_receipt_insert');
                return;
            }

            $this->depthNeedMatrix->incrementKernelReceived($depth, $domain);

            $state = $this->stateRepository->firstForUpdate();
            if ($state !== null) {
                $this->stateRepository->updateWithLock($state, [
                    'last_counted_blueprint_identity' => $blueprintId,
                ]);
            }
        });
    }

    /**
     * Consumes at most one pending fact for the active Depth. A fact belonging
     * to a later Depth remains pending until that Depth becomes active.
     */
    private function consumeOnePendingTerminalFact(object $state): object
    {
        $activeDepth = (int) $state->active_depth;
        $fact = $this->terminalFactRepository->lockNextPendingForTour(
            $activeDepth,
            (string) $state->active_tour_id,
        );

        if ($fact === null) {
            return $state;
        }

        $domain = (string) $fact->domain_code;
        $this->assertDomain($domain);

        $domainStates = $this->loadDomainStates($state);
        $depthKey = (string) $activeDepth;
        $currentStatus = $domainStates[$depthKey][$domain] ?? self::DOMAIN_VISIBLE;

        if ($currentStatus === self::DOMAIN_ESTOMPE) {
            $this->terminalFactRepository->markConsumed((string) $fact->fact_id);
            return $state;
        }

        if ($currentStatus !== self::DOMAIN_VISIBLE) {
            throw new RuntimeException(
                "[KRP] État Domain inconnu pour {$activeDepth}/{$domain}: {$currentStatus}."
            );
        }

        // DOMAIN_EXHAUSTED: VISIBLE -> ESTOMPÉ, persisted before any depth work.
        $domainStates[$depthKey][$domain] = self::DOMAIN_ESTOMPE;
        $this->stateRepository->updateWithLock($state, [
            'domain_states' => json_encode($domainStates, JSON_UNESCAPED_UNICODE),
        ]);

        $state = $this->requireLockedState();

        if (! $this->hasVisibleDomain($activeDepth, $domainStates)) {
            // DOMAIN_EXHAUSTED calls the internal DEPTH_EXHAUSTED engine only
            // after the final visible Domain has been persisted as ESTOMPÉ.
            $state = $this->applyDepthExhausted($state, $activeDepth, $domainStates);
        }

        $this->terminalFactRepository->markConsumed((string) $fact->fact_id);

        return $state;
    }

    /**
     * Internal DEPTH_EXHAUSTED engine.
     *
     * It closes the current tour idempotently in the same transaction, advances
     * the matrix, finds the next required Depth in the circular cycle, resets a
     * fresh DomainRotation, or persists HOLD when no need remains.
     */
    private function applyDepthExhausted(object $state, int $closedDepth, array $domainStates): object
    {
        if ($state->tour_state === self::TOUR_CLOSED
            && (int) ($state->last_closed_depth ?? 0) === $closedDepth) {
            return $state;
        }

        if ($this->hasVisibleDomain($closedDepth, $domainStates)) {
            throw new RuntimeException(
                '[KRP] DEPTH_EXHAUSTED interdit tant qu’un Domain VISIBLE existe.'
            );
        }

        $nextDepth = $this->depthNeedMatrix->completeTourAndFindNextDepth($closedDepth);

        $updates = [
            'last_closed_tour_id' => $state->active_tour_id,
            'last_closed_depth'   => $closedDepth,
            'domain_position'     => null,
            'pending_depth_exhausted_depth' => null,
        ];

        if ($nextDepth === null) {
            $updates['depth_state'] = self::DEPTH_STATE_HOLD;
            $updates['tour_state'] = self::TOUR_CLOSED;
            $updates['active_blueprint_identity'] = null;

            $this->stateRepository->updateWithLock($state, $updates);

            return $this->requireLockedState();
        }

        $domainStates[(string) $nextDepth] = $this->freshVisibleDomainStates();
        $updates['active_depth'] = $nextDepth;
        $updates['active_tour_id'] = (string) Str::orderedUuid();
        $updates['tour_state'] = self::TOUR_OPEN;
        $updates['depth_state'] = self::DEPTH_STATE_ACTIVE;
        $updates['domain_states'] = json_encode($domainStates, JSON_UNESCAPED_UNICODE);
        $updates['active_blueprint_identity'] = null;

        $this->stateRepository->updateWithLock($state, $updates);

        return $this->requireLockedState();
    }

    private function ensureInitializedState(?object $state): object
    {
        if ($state === null) {
            $this->stateRepository->insert([
                'depth_state'                    => self::DEPTH_STATE_ACTIVE,
                'active_depth'                   => DepthNeedMatrix::DEPTH_CYCLE[0],
                'active_tour_id'                 => (string) Str::orderedUuid(),
                'tour_state'                     => self::TOUR_OPEN,
                'last_closed_tour_id'            => null,
                'last_closed_depth'              => null,
                'domain_position'                => null,
                'domain_states'                  => json_encode($this->buildInitialDomainStates(), JSON_UNESCAPED_UNICODE),
                'active_blueprint_identity'      => null,
                'last_counted_blueprint_identity' => null,
                'pending_depth_exhausted_depth'  => null,
                'lock_version'                   => 1,
                'created_at'                     => now(),
                'updated_at'                     => now(),
            ]);

            return $this->requireLockedState();
        }

        $updates = [];

        if ($state->active_depth === null && $state->depth_state !== self::DEPTH_STATE_HOLD) {
            $updates['active_depth'] = DepthNeedMatrix::DEPTH_CYCLE[0];
        }

        if ($state->active_tour_id === null && $state->depth_state !== self::DEPTH_STATE_HOLD) {
            $updates['active_tour_id'] = (string) Str::orderedUuid();
        }

        if ($state->tour_state === null) {
            $updates['tour_state'] = self::TOUR_OPEN;
        }

        if (empty($state->domain_states)) {
            $updates['domain_states'] = json_encode($this->buildInitialDomainStates(), JSON_UNESCAPED_UNICODE);
        }

        if ($updates !== []) {
            $this->stateRepository->updateWithLock($state, $updates);
            return $this->requireLockedState();
        }

        return $state;
    }

    private function requireLockedState(): object
    {
        $state = $this->stateRepository->firstForUpdate();

        if ($state === null) {
            throw new RuntimeException('[KRP] État de rotation absent après écriture.');
        }

        return $state;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function loadDomainStates(?object $state): array
    {
        $decoded = [];

        if ($state !== null && ! empty($state->domain_states)) {
            $value = json_decode((string) $state->domain_states, true);
            $decoded = is_array($value) ? $value : [];
        }

        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $depthKey = (string) $depth;
            foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
                $status = $decoded[$depthKey][$domain] ?? self::DOMAIN_VISIBLE;

                // One-way compatibility for state rows created under KRP v3.x.
                $decoded[$depthKey][$domain] = match ($status) {
                    'ACTIF', 'ON', self::DOMAIN_VISIBLE => self::DOMAIN_VISIBLE,
                    'DOMAIN_EXHAUSTED', 'OFF', self::DOMAIN_ESTOMPE => self::DOMAIN_ESTOMPE,
                    default => (string) $status,
                };
            }
        }

        return $decoded;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function buildInitialDomainStates(): array
    {
        $states = [];

        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $states[(string) $depth] = $this->freshVisibleDomainStates();
        }

        return $states;
    }

    /**
     * @return array<string, string>
     */
    private function freshVisibleDomainStates(): array
    {
        return array_fill_keys(DepthTourState::DOMAIN_CYCLE, self::DOMAIN_VISIBLE);
    }

    /**
     * @return array{domain: string, index: int}|null
     */
    private function selectNextVisibleDomain(int $depth, ?int $position, array $domainStates): ?array
    {
        $cycle = DepthTourState::DOMAIN_CYCLE;
        $count = count($cycle);
        $start = $position ?? -1;

        if ($start < -1 || $start >= $count) {
            $start = -1;
        }

        foreach (range(1, $count) as $offset) {
            $index = ($start + $offset) % $count;
            $domain = $cycle[$index];

            if (($domainStates[(string) $depth][$domain] ?? self::DOMAIN_VISIBLE) === self::DOMAIN_VISIBLE) {
                return ['domain' => $domain, 'index' => $index];
            }
        }

        return null;
    }

    private function hasVisibleDomain(int $depth, array $domainStates): bool
    {
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            if (($domainStates[(string) $depth][$domain] ?? self::DOMAIN_VISIBLE) === self::DOMAIN_VISIBLE) {
                return true;
            }
        }

        return false;
    }

    private function assertDepth(int $depth): void
    {
        if (! in_array($depth, DepthNeedMatrix::DEPTH_CYCLE, true)) {
            throw new RuntimeException(
                '[KRP] Depth invalide : ' . $depth . '. Cycle : '
                . implode('→', DepthNeedMatrix::DEPTH_CYCLE) . '.'
            );
        }
    }

    private function assertDomain(string $domain): void
    {
        if (! in_array($domain, DepthTourState::DOMAIN_CYCLE, true)) {
            throw new RuntimeException(
                "[KRP] Domain invalide : '{$domain}'. DomainCycle : "
                . implode(', ', DepthTourState::DOMAIN_CYCLE) . '.'
            );
        }
    }
}