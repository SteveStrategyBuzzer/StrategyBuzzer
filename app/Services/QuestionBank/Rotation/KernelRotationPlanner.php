<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * KernelRotationPlanner — autorité du prochain couple depth + domain.
 *
 * Contrat actif v3.3 (DEC-094 / DEC-108 / DEC-111) :
 *   - reçoit un KernelBlueprint déjà créé ;
 *   - choisit uniquement depth + domain ;
 *   - écrit depth + domain via fillRotation() ;
 *   - persiste le cadran du tour courant ;
 *   - reçoit DOMAIN_EXHAUSTED / DEPTH_EXHAUSTED de Taxonomy ;
 *   - ne reçoit pas CURRENT_KERNEL_RECEIVED comme déclencheur de rotation.
 *
 * CURRENT_KERNEL_RECEIVED appartient à la frontière de création du Blueprint
 * suivant. Le Blueprint nouvellement créé revient ensuite à KRP.
 */
final class KernelRotationPlanner
{
    private const STATE_TABLE_V2 = 'kernel_rotation_state_v2';
    private const RUNS_TABLE = 'kernel_blueprint_runs';
    private const RECEIPTS_TABLE = 'kernel_current_kernel_receipts';

    public const RESULT_ROTATION_ASSIGNED = 'ROTATION_ASSIGNED';
    public const RESULT_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    public const DEPTH_STATE_ROTATION_ACTIVE = 'ROTATION_ACTIVE';
    public const DEPTH_STATE_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';
    public const DEPTH_STATE_BLOCKED = 'BLOCKED';

    public const DOMAIN_VISIBLE = 'VISIBLE';
    public const DOMAIN_DIMMED = 'ESTOMPÉ';

    /** 1 tentative initiale + 3 retries techniques. */
    public const MAX_STATE_PERSIST_RETRIES = 3;

    public function __construct(
        private readonly ?DepthNeedMatrix $depthNeedMatrix = null,
        private readonly ?KernelRotationStateRepository $stateRepository = null,
    ) {}

    /**
     * Résout le prochain couple sans écrire le Blueprint.
     *
     * Le caller doit fournir l'état verrouillé lorsqu'il existe. Au premier
     * démarrage, DepthNeedMatrix choisit le premier Depth dont le besoin global
     * reste ouvert.
     */
    public function resolveNextRotation(?object $state): RotationResolution
    {
        $depthState = (string) ($state?->depth_state ?? self::DEPTH_STATE_ROTATION_ACTIVE);

        if ($depthState === self::DEPTH_STATE_BLOCKED) {
            return RotationResolution::noRotation(self::DEPTH_STATE_BLOCKED);
        }

        if ($depthState === self::DEPTH_STATE_PRODUCTION_ON_HOLD) {
            return RotationResolution::noRotation(self::DEPTH_STATE_PRODUCTION_ON_HOLD);
        }

        $matrix = $this->matrix();
        $activeDepth = $state?->active_depth !== null ? (int) $state->active_depth : null;
        $depth = $activeDepth ?? $matrix->nextRequiredDepth(null);

        if ($depth === null) {
            return RotationResolution::noRotation(self::DEPTH_STATE_PRODUCTION_ON_HOLD);
        }

        $domainStates = $this->loadDomainStates($state);
        $position = $state?->domain_position !== null ? (int) $state->domain_position : null;
        $next = $this->selectNextVisibleDomain($depth, $position, $domainStates);

        if ($next === null) {
            return RotationResolution::noRotation('AWAITING_DEPTH_EXHAUSTED');
        }

        return RotationResolution::available($depth, $next['domain'], $next['index']);
    }

    /** Enregistre le Blueprint déjà créé comme Blueprint actif de la rotation. */
    public function registerActiveBlueprintIdentity(string $blueprintId, ?object $state): void
    {
        if ($state === null) {
            DB::table(self::STATE_TABLE_V2)->insert([
                'depth_state' => self::DEPTH_STATE_ROTATION_ACTIVE,
                'active_depth' => null,
                'domain_position' => null,
                'domain_states' => json_encode($this->buildInitialDomainStates(), JSON_UNESCAPED_UNICODE),
                'tour_domain_states' => json_encode(DepthTourState::initTour()->toArray()),
                'active_blueprint_identity' => $blueprintId,
                'last_counted_blueprint_identity' => null,
                'pending_depth_exhausted_depth' => null,
                'lock_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
            'active_blueprint_identity' => $blueprintId,
            'lock_version' => (int) $state->lock_version + 1,
            'updated_at' => now(),
        ]);
    }

    /** Écrit immédiatement le couple choisi dans le Blueprint, AVANT Taxonomy. */
    public function applyRotation(
        KernelBlueprint $blueprint,
        int $depth,
        string $domain,
        int $domainPosition,
    ): void {
        if (!in_array($depth, DepthNeedMatrix::DEPTH_CYCLE, true)) {
            throw new RuntimeException("[KRP] Depth {$depth} hors DepthCycle officiel.");
        }
        if (!in_array($domain, DepthTourState::DOMAIN_CYCLE, true)) {
            throw new RuntimeException("[KRP] Domaine inconnu : {$domain}.");
        }

        $blueprint->fillRotation($depth, $domain);

        DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
            'active_depth' => $depth,
            'domain_position' => $domainPosition,
            'depth_state' => self::DEPTH_STATE_ROTATION_ACTIVE,
            'updated_at' => now(),
        ]);

        DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->update([
                'depth' => $depth,
                'domain_code' => $domain,
                'updated_at' => now(),
            ]);
    }

    /** DOMAIN_EXHAUSTED : VISIBLE -> ESTOMPÉ, idempotent et durable. */
    public function receiveDomainExhausted(int $depth, string $domain): void
    {
        if (!in_array($depth, DepthNeedMatrix::DEPTH_CYCLE, true)) {
            throw new RuntimeException("[KRP] receiveDomainExhausted — Depth {$depth} hors cycle officiel.");
        }
        if (!in_array($domain, DepthTourState::DOMAIN_CYCLE, true)) {
            throw new RuntimeException("[KRP] receiveDomainExhausted — domaine inconnu : '{$domain}'.");
        }

        $this->persistExhaustionTransition('KRP-002', function () use ($depth, $domain): void {
            $state = $this->stateRepo()->firstForUpdate();
            if ($state === null) {
                throw new RuntimeException('[KRP] DOMAIN_EXHAUSTED reçu sans RotationState initialisé.');
            }

            $activeDepth = $state->active_depth !== null ? (int) $state->active_depth : null;
            if ($activeDepth !== $depth) {
                Log::warning('[KRP] DOMAIN_EXHAUSTED hors tour actif — NO-OP.', [
                    'signal_depth' => $depth,
                    'active_depth' => $activeDepth,
                    'domain' => $domain,
                ]);
                return;
            }

            $domainStates = $this->loadDomainStates($state);
            $current = $domainStates[(string) $depth][$domain] ?? self::DOMAIN_VISIBLE;
            if ($this->isDimmedState($current)) {
                return;
            }

            $domainStates[(string) $depth][$domain] = self::DOMAIN_DIMMED;
            $this->stateRepo()->updateWithLock($state, [
                'domain_states' => json_encode($domainStates, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    /** DEPTH_EXHAUSTED termine UN tour du Depth courant. */
    public function receiveDepthExhausted(int $depth): void
    {
        if (!in_array($depth, DepthNeedMatrix::DEPTH_CYCLE, true)) {
            throw new RuntimeException("[KRP] receiveDepthExhausted — Depth {$depth} hors cycle officiel.");
        }

        $this->persistExhaustionTransition('KRP-003', function () use ($depth): void {
            $state = $this->stateRepo()->firstForUpdate();
            if ($state === null) {
                throw new RuntimeException('[KRP] DEPTH_EXHAUSTED reçu sans RotationState initialisé.');
            }

            $activeDepth = $state->active_depth !== null ? (int) $state->active_depth : null;
            if ($activeDepth !== $depth) {
                Log::warning('[KRP] DEPTH_EXHAUSTED hors tour actif — NO-OP.', [
                    'signal_depth' => $depth,
                    'active_depth' => $activeDepth,
                ]);
                return;
            }

            $domainStates = $this->loadDomainStates($state);
            if (!$this->allDomainsDimmed($depth, $domainStates)) {
                Log::warning('[KRP] DEPTH_EXHAUSTED reçu avant 8/8 Domaines ESTOMPÉS — NO-OP.', [
                    'depth' => $depth,
                ]);
                return;
            }

            $matrix = $this->matrix();
            $matrix->incrementCycleCompleted($depth);
            $nextDepth = $matrix->nextRequiredDepth($depth);

            $updates = [
                'pending_depth_exhausted_depth' => null,
                'domain_position' => null,
            ];

            if ($nextDepth === null) {
                $updates['active_depth'] = null;
                $updates['depth_state'] = self::DEPTH_STATE_PRODUCTION_ON_HOLD;
            } else {
                $this->resetDepthToVisible($domainStates, $nextDepth);
                $updates['active_depth'] = $nextDepth;
                $updates['depth_state'] = self::DEPTH_STATE_ROTATION_ACTIVE;
                $updates['domain_states'] = json_encode($domainStates, JSON_UNESCAPED_UNICODE);
            }

            $this->stateRepo()->updateWithLock($state, $updates);
        });
    }

    /**
     * @deprecated CURRENT_KERNEL_RECEIVED ne pilote plus KRP. Compatibilité legacy :
     *             reçu/compteur seulement, aucune transition de Depth.
     */
    public function receiveKernelReceivedV2(string $blueprintId, int $depth, string $domain): void
    {
        DB::transaction(function () use ($blueprintId, $depth, $domain): void {
            if (DB::table(self::RECEIPTS_TABLE)->where('blueprint_id', $blueprintId)->exists()) {
                return;
            }

            DB::statement('SAVEPOINT ckr_receipt_insert');
            try {
                DB::table(self::RECEIPTS_TABLE)->insert([
                    'blueprint_id' => $blueprintId,
                    'event_id' => (string) Str::orderedUuid(),
                    'depth' => $depth,
                    'domain_code' => $domain,
                    'received_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                DB::statement('ROLLBACK TO SAVEPOINT ckr_receipt_insert');
                return;
            }

            $this->matrix()->incrementKernelReceived($depth, $domain);
            DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                'last_counted_blueprint_identity' => $blueprintId,
                'updated_at' => now(),
            ]);
        });
    }

    private function selectNextVisibleDomain(int $depth, ?int $position, array $domainStates): ?array
    {
        $cycle = DepthTourState::DOMAIN_CYCLE;
        $pos = $position ?? -1;
        $depthKey = (string) $depth;
        $count = count($cycle);

        for ($offset = 1; $offset <= $count; $offset++) {
            $index = ($pos + $offset) % $count;
            $candidate = $cycle[$index];
            $status = $domainStates[$depthKey][$candidate] ?? self::DOMAIN_VISIBLE;
            if (!$this->isDimmedState($status)) {
                return ['domain' => $candidate, 'index' => $index];
            }
        }
        return null;
    }

    private function allDomainsDimmed(int $depth, array $domainStates): bool
    {
        $depthKey = (string) $depth;
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $status = $domainStates[$depthKey][$domain] ?? self::DOMAIN_VISIBLE;
            if (!$this->isDimmedState($status)) {
                return false;
            }
        }
        return true;
    }

    private function isDimmedState(string $status): bool
    {
        return $status === self::DOMAIN_DIMMED || $status === 'DOMAIN_EXHAUSTED';
    }

    private function resetDepthToVisible(array &$domainStates, int $depth): void
    {
        $key = (string) $depth;
        $domainStates[$key] = [];
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $domainStates[$key][$domain] = self::DOMAIN_VISIBLE;
        }
    }

    private function loadDomainStates(?object $state): array
    {
        if ($state === null || empty($state->domain_states)) {
            return $this->buildInitialDomainStates();
        }
        $decoded = json_decode((string) $state->domain_states, true);
        return is_array($decoded) ? $decoded : $this->buildInitialDomainStates();
    }

    private function buildInitialDomainStates(): array
    {
        $states = [];
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $this->resetDepthToVisible($states, $depth);
        }
        return $states;
    }

    private function persistExhaustionTransition(string $failureCode, callable $operation): void
    {
        $last = null;
        for ($attempt = 0; $attempt <= self::MAX_STATE_PERSIST_RETRIES; $attempt++) {
            try {
                DB::transaction($operation);
                return;
            } catch (Throwable $e) {
                $last = $e;
                Log::warning('[KRP] Échec persistance transition, retry.', [
                    'failure_code' => $failureCode,
                    'attempt' => $attempt + 1,
                    'max_attempts' => self::MAX_STATE_PERSIST_RETRIES + 1,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->bestEffortBlockState($failureCode, $last);
        throw new RuntimeException(
            "[{$failureCode}] Persistance KRP impossible après "
            . (self::MAX_STATE_PERSIST_RETRIES + 1) . ' tentatives.',
            0,
            $last,
        );
    }

    private function bestEffortBlockState(string $failureCode, ?Throwable $last): void
    {
        Log::critical('[KRP] Échec terminal de persistance — BLOCKED.', [
            'failure_code' => $failureCode,
            'error' => $last?->getMessage(),
        ]);
        try {
            DB::table(self::STATE_TABLE_V2)->whereNotNull('id')->update([
                'depth_state' => self::DEPTH_STATE_BLOCKED,
                'updated_at' => now(),
            ]);
        } catch (Throwable $ignored) {
            Log::critical('[KRP] Impossible de persister depth_state=BLOCKED.', [
                'failure_code' => $failureCode,
                'error' => $ignored->getMessage(),
            ]);
        }
    }

    private function matrix(): DepthNeedMatrix
    {
        return $this->depthNeedMatrix ?? new DepthNeedMatrix();
    }

    private function stateRepo(): KernelRotationStateRepository
    {
        return $this->stateRepository ?? new KernelRotationStateRepository();
    }
}
