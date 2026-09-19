<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class Phase1ExecutionRepository
{
    public const NO_OP_ACTIVE = 'NO_OP_ACTIVE';
    public const NO_OP_COMPLETED = 'NO_OP_COMPLETED';
    public const CLAIMED = 'CLAIMED';
    public const STALE_RESULT = 'STALE_RESULT';
    public const PHASE1_CREATION_COMPLETED = 'PHASE1_CREATION_COMPLETED';

    /**
     * @return array{status:string, execution_id:?string, lease_token:?string, identity_revision:string}
     */
    public function claim(string $blueprintId): array
    {
        return DB::transaction(function () use ($blueprintId): array {
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->first();
            $revision = $this->assertCurrentBlueprint($run, $blueprintId);

            $existing = DB::table('kernel_phase1_executions')
                ->where('blueprint_id', $blueprintId)
                ->where('identity_revision', $revision)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return [
                    'status' => $existing->state === 'COMPLETED'
                        ? self::NO_OP_COMPLETED
                        : self::NO_OP_ACTIVE,
                    'execution_id' => (string) $existing->execution_id,
                    'lease_token' => null,
                    'identity_revision' => $revision,
                ];
            }

            $executionId = (string) Str::uuid();
            $leaseToken = (string) Str::uuid();
            DB::table('kernel_phase1_executions')->insert([
                'execution_id' => $executionId,
                'blueprint_id' => $blueprintId,
                'identity_revision' => $revision,
                'state' => 'ACTIVE',
                'lease_token' => $leaseToken,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'status' => self::CLAIMED,
                'execution_id' => $executionId,
                'lease_token' => $leaseToken,
                'identity_revision' => $revision,
            ];
        });
    }

    public function currentIdentityRevision(string $blueprintId): string
    {
        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)
            ->first();

        return $this->assertCurrentBlueprint($run, $blueprintId);
    }

    /**
     * Reload and verify the persisted Phase 1 terminal before Validation Phase 1.
     *
     * @return array{identity_revision:string, execution_id:string, slots_revision:string}
     */
    public function validationPrerequisites(string $blueprintId): array
    {
        return DB::transaction(function () use ($blueprintId): array {
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->first();
            $revision = $this->assertCurrentBlueprint($run, $blueprintId);
            $execution = DB::table('kernel_phase1_executions')
                ->where('blueprint_id', $blueprintId)
                ->where('identity_revision', $revision)
                ->lockForUpdate()
                ->first();
            $this->assertCompletedCreation($execution, $blueprintId);
            $slotsRevision = $this->currentCreatedSlotsRevision($blueprintId);

            return [
                'identity_revision' => $revision,
                'execution_id' => (string) $execution->execution_id,
                'slots_revision' => $slotsRevision,
            ];
        });
    }

    /**
     * Fence Validation Phase 1's own write against the persisted Phase 1 terminal.
     */
    public function withCompletedPhase1(
        string $blueprintId,
        string $expectedIdentityRevision,
        string $expectedSlotsRevision,
        Closure $callback,
    ): mixed {
        return DB::transaction(function () use (
            $blueprintId,
            $expectedIdentityRevision,
            $expectedSlotsRevision,
            $callback,
        ): mixed {
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->first();
            $actualRevision = $this->assertCurrentBlueprint($run, $blueprintId);
            if (! hash_equals($expectedIdentityRevision, $actualRevision)) {
                throw new LogicException(
                    "[ValidationPhase1] Révision Phase 1 périmée: {$blueprintId}."
                );
            }
            $execution = DB::table('kernel_phase1_executions')
                ->where('blueprint_id', $blueprintId)
                ->where('identity_revision', $actualRevision)
                ->lockForUpdate()
                ->first();
            $this->assertCompletedCreation($execution, $blueprintId);
            $actualSlotsRevision = $this->currentCreatedSlotsRevision($blueprintId);
            if (! hash_equals($expectedSlotsRevision, $actualSlotsRevision)) {
                throw new LogicException(
                    "[ValidationPhase1] CognitiveSlots Phase 1 périmés: {$blueprintId}."
                );
            }

            return $callback();
        });
    }

    /**
     * Lock the persisted identity for the complete slot-write transaction.
     *
     * The provider call must happen before this method. Once entered, no
     * Rotation/Taxonomy/VVVV identity update can interleave with slot writes.
     */
    public function withCurrentIdentity(
        string $blueprintId,
        string $expectedIdentityRevision,
        Closure $callback,
    ): mixed {
        return DB::transaction(function () use (
            $blueprintId,
            $expectedIdentityRevision,
            $callback,
        ): mixed {
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->first();
            $actual = $this->assertCurrentBlueprint($run, $blueprintId);
            if (! hash_equals($expectedIdentityRevision, $actual)) {
                throw new LogicException(
                    "[Phase1] Résultat fournisseur obsolète avant écriture: {$blueprintId}."
                );
            }
            return $callback();
        });
    }

    /**
     * Fence a write against the current identity and the one ACTIVE lease.
     *
     * Lock order is always Blueprint identity, then Phase 1 execution. Slot
     * repositories may lock their rows only after this method invokes the
     * callback.
     */
    public function withActiveLease(
        string $executionId,
        string $leaseToken,
        string $blueprintId,
        string $identityRevision,
        Closure $callback,
    ): mixed {
        return DB::transaction(function () use (
            $executionId,
            $leaseToken,
            $blueprintId,
            $identityRevision,
            $callback,
        ): mixed {
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->first();
            $actualRevision = $this->assertCurrentBlueprint($run, $blueprintId);
            $execution = DB::table('kernel_phase1_executions')
                ->where('execution_id', $executionId)
                ->lockForUpdate()
                ->first();

            if (! hash_equals($identityRevision, $actualRevision)
                || $execution === null
                || (string) $execution->blueprint_id !== $blueprintId
                || (string) $execution->identity_revision !== $identityRevision
                || (string) $execution->lease_token !== $leaseToken
                || (string) $execution->state !== 'ACTIVE') {
                throw new LogicException(
                    "[Phase1] Lease ou identité obsolète avant écriture: {$blueprintId}."
                );
            }

            return $callback();
        });
    }

    /**
     * @param array<string, mixed>|null $result
     * @return string One of COMPLETED, NO_OP_COMPLETED, STALE_RESULT.
     */
    public function complete(
        string $executionId,
        string $leaseToken,
        string $blueprintId,
        string $identityRevision,
        array $result = [],
    ): string {
        return DB::transaction(function () use (
            $executionId,
            $leaseToken,
            $blueprintId,
            $identityRevision,
            $result,
        ): string {
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->first();
            $actualRevision = $this->assertCurrentBlueprint($run, $blueprintId);
            $row = DB::table('kernel_phase1_executions')
                ->where('execution_id', $executionId)
                ->lockForUpdate()
                ->first();
            if ($row === null || (string) $row->blueprint_id !== $blueprintId) {
                return self::STALE_RESULT;
            }

            if (! hash_equals($identityRevision, $actualRevision)
                || (string) $row->identity_revision !== $identityRevision
                || (string) $row->lease_token !== $leaseToken) {
                return self::STALE_RESULT;
            }
            if ($row->state === 'COMPLETED') {
                return self::NO_OP_COMPLETED;
            }
            if ($row->state !== 'ACTIVE') {
                return self::STALE_RESULT;
            }

            DB::table('kernel_phase1_executions')
                ->where('execution_id', $executionId)
                ->update([
                    'state' => 'COMPLETED',
                    'result' => json_encode($result, JSON_THROW_ON_ERROR),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            return 'COMPLETED';
        });
    }

    /**
     * Commit Phase 1's successful terminal only while the seven current slots
     * are fully CREATED under the same persisted identity and ACTIVE lease.
     */
    public function completeCreatedPhase1(
        string $executionId,
        string $leaseToken,
        string $blueprintId,
        string $identityRevision,
    ): string {
        return DB::transaction(function () use (
            $executionId,
            $leaseToken,
            $blueprintId,
            $identityRevision,
        ): string {
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->first();
            $actualRevision = $this->assertCurrentBlueprint($run, $blueprintId);
            $execution = DB::table('kernel_phase1_executions')
                ->where('execution_id', $executionId)
                ->lockForUpdate()
                ->first();

            if (! hash_equals($identityRevision, $actualRevision)
                || $execution === null
                || (string) $execution->blueprint_id !== $blueprintId
                || (string) $execution->identity_revision !== $identityRevision
                || (string) $execution->lease_token !== $leaseToken) {
                return self::STALE_RESULT;
            }
            if ((string) $execution->state === 'COMPLETED') {
                return self::NO_OP_COMPLETED;
            }
            if ((string) $execution->state !== 'ACTIVE') {
                return self::STALE_RESULT;
            }

            $slots = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->get();
            $expectedTypes = \App\Services\QuestionBank\KernelBlueprint::COGNITIVE_TYPES;
            $actualTypes = $slots->pluck('cognitive_type')->map(
                static fn(mixed $type): string => (string) $type
            )->all();
            sort($actualTypes);
            $sortedExpected = $expectedTypes;
            sort($sortedExpected);
            if ($actualTypes !== $sortedExpected
                || $slots->contains(
                    static fn(object $slot): bool => (string) $slot->creation_status !== 'CREATED'
                )) {
                throw new LogicException(
                    "[Phase1] Terminal interdit sans sept slots CREATED: {$blueprintId}."
                );
            }

            DB::table('kernel_phase1_executions')
                ->where('execution_id', $executionId)
                ->update([
                    'state' => 'COMPLETED',
                    'result' => json_encode([
                        'phase1_terminal' => self::PHASE1_CREATION_COMPLETED,
                        'creation_status' => 'CREATED',
                    ], JSON_THROW_ON_ERROR),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            return 'COMPLETED';
        });
    }

    /**
     * @param object|null $run
     */
    private function assertCurrentBlueprint(?object $run, string $blueprintId): string
    {
        if ($run === null) {
            throw new LogicException("[Phase1] Blueprint introuvable: {$blueprintId}.");
        }

        $required = [
            'depth', 'domain_code', 'subdomain_active', 'subject_active',
            'dominant_idea_active', 'kernel_code_dd', 'kernel_code_do',
            'kernel_code_sub', 'kernel_code_suj', 'kernel_code_ide',
            'kernel_code_vvvv', 'kernel_code',
        ];
        foreach ($required as $column) {
            if (! isset($run->{$column}) || trim((string) $run->{$column}) === '') {
                throw new LogicException("[Phase1] Identité persistante incomplète: {$column}.");
            }
        }

        $identity = [];
        foreach ($required as $column) {
            $identity[$column] = (string) $run->{$column};
        }
        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    private function assertCompletedCreation(?object $execution, string $blueprintId): void
    {
        if ($execution === null || (string) $execution->state !== 'COMPLETED') {
            throw new LogicException(
                "[ValidationPhase1] Phase 1 non terminale: {$blueprintId}."
            );
        }
        $result = is_array($execution->result ?? null)
            ? $execution->result
            : json_decode((string) ($execution->result ?? ''), true);
        if (! is_array($result)
            || ($result['phase1_terminal'] ?? null) !== self::PHASE1_CREATION_COMPLETED
            || ($result['creation_status'] ?? null) !== 'CREATED') {
            throw new LogicException(
                "[ValidationPhase1] Terminal de création Phase 1 invalide: {$blueprintId}."
            );
        }
    }

    private function currentCreatedSlotsRevision(string $blueprintId): string
    {
        $slots = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $blueprintId)
            ->orderBy('cognitive_type')
            ->lockForUpdate()
            ->get();
        $expectedTypes = \App\Services\QuestionBank\KernelBlueprint::COGNITIVE_TYPES;
        $actualTypes = $slots->pluck('cognitive_type')->map(
            static fn(mixed $type): string => (string) $type
        )->all();
        sort($actualTypes);
        $sortedExpected = $expectedTypes;
        sort($sortedExpected);
        if ($actualTypes !== $sortedExpected
            || $slots->contains(
                static fn(object $slot): bool => (string) $slot->creation_status !== 'CREATED'
            )) {
            throw new LogicException(
                "[ValidationPhase1] Sept slots Phase 1 CREATED requis: {$blueprintId}."
            );
        }

        $snapshot = [];
        foreach ($slots as $slot) {
            $snapshot[] = [
                'cognitive_type' => (string) $slot->cognitive_type,
                'source' => json_decode((string) $slot->source, true, 512, JSON_THROW_ON_ERROR),
                'creation_status' => (string) $slot->creation_status,
                'creation_failure' => $slot->creation_failure === null
                    ? null
                    : json_decode((string) $slot->creation_failure, true, 512, JSON_THROW_ON_ERROR),
                'canonical_revision' => property_exists($slot, 'canonical_revision')
                    ? (string) $slot->canonical_revision
                    : null,
            ];
        }

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}