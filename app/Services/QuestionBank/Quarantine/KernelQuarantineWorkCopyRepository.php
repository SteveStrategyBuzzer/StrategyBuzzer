<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

/**
 * Persistence owner for non-canonical quarantine copies.
 *
 * This repository intentionally has no methods which write a canonical slot.
 */
final class KernelQuarantineWorkCopyRepository
{
    public const TABLE = 'kernel_quarantine_work_copies';

    /** @return object|null */
    public function find(string $copyId, bool $lock = false): ?object
    {
        $query = DB::table(self::TABLE)->where('copy_id', $copyId);
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first();
    }

    /** @return object|null */
    public function oldestReady(bool $lock = false): ?object
    {
        $query = DB::table(self::TABLE)->whereIn('state', ['READY'])
            ->orderBy('ready_order')->orderBy('copy_id');
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first();
    }

    public function create(array $attributes, string $copyId = ''): string
    {
        $copyId = $copyId !== '' ? $copyId : (string) Str::orderedUuid();
        $attributes['copy_id'] = $copyId;
        $attributes += [
            'copy_version' => 1,
            'state' => 'EDITABLE',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table(self::TABLE)->insert($attributes);
        return $copyId;
    }

    /**
     * Claiming is the only transition which assigns a token.  It is guarded
     * by both the version and READY state to make stale workers harmless.
     */
    public function claim(string $copyId, int $copyVersion, string $claimToken): bool
    {
        return DB::transaction(function () use ($copyId, $copyVersion, $claimToken): bool {
            $gate = DB::table('kernel_current_kernel_route_gate')
                ->where('gate_id', 1)->lockForUpdate()->first();
            if ($gate !== null && $gate->active_copy_id !== null
                && (string) $gate->active_copy_id !== $copyId) {
                return false;
            }
            $inFlight = DB::table(self::TABLE)->where('state', 'IN_FLIGHT')
                ->where('copy_id', '<>', $copyId)->lockForUpdate()->exists();
            if ($inFlight) {
                return false;
            }
            $requestedDispatch = DB::table('kernel_current_kernel_dispatches')
                ->where('copy_id', $copyId)->where('state', 'READY')
                ->lockForUpdate()->first();
            if ($requestedDispatch !== null) {
                $oldestDispatch = DB::table('kernel_current_kernel_dispatches')
                    ->where('state', 'READY')->orderBy('ready_order')->orderBy('event_id')
                    ->lockForUpdate()->first();
                if ($oldestDispatch !== null
                    && (string) $oldestDispatch->event_id !== (string) $requestedDispatch->event_id) {
                    return false;
                }
            }
            $claimed = DB::table(self::TABLE)
                ->where('copy_id', $copyId)
                ->where('state', 'READY')
                ->where('copy_version', $copyVersion)
                ->update([
                    'state' => 'IN_FLIGHT',
                    'claim_token' => $claimToken,
                    'claimed_version' => $copyVersion,
                    'claimed_at' => now(),
                    'updated_at' => now(),
                ]) === 1;
            if ($claimed) {
                DB::table('kernel_current_kernel_dispatches')->where('copy_id', $copyId)
                    ->where('state', 'READY')->update([
                        'state' => 'IN_FLIGHT', 'claimed_at' => now(), 'updated_at' => now(),
                    ]);
                if (Schema::hasTable('kernel_current_kernel_route_gate')) {
                    DB::table('kernel_current_kernel_route_gate')->where('gate_id', 1)->update([
                        'active_copy_id' => $copyId,
                        'active_copy_version' => $copyVersion,
                        'active_claim_token' => $claimToken,
                        'updated_at' => now(),
                    ]);
                }
            }
            return $claimed;
        });
    }

    public function assertClaim(object $copy, int $copyVersion, string $claimToken): void
    {
        if ((int) $copy->copy_version !== $copyVersion
            || (string) ($copy->claim_token ?? '') !== $claimToken
            || (string) $copy->state !== 'IN_FLIGHT') {
            throw new LogicException('Référence de copie Quarantaine périmée.');
        }
    }

    public function bumpVersion(string $copyId, int $expectedVersion): int
    {
        $updated = DB::table(self::TABLE)
            ->where('copy_id', $copyId)
            ->whereIn('state', ['EDITABLE', 'READY'])
            ->where('copy_version', $expectedVersion)
            ->update([
                'copy_version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new LogicException('Copie Quarantaine modifiée depuis sa lecture.');
        }
        return $expectedVersion + 1;
    }

    public function markReady(string $copyId): void
    {
        DB::table(self::TABLE)->where('copy_id', $copyId)->update([
            'state' => 'READY',
            'claimed_event_id' => null,
            'claim_token' => null,
            'claimed_version' => null,
            'claimed_at' => null,
            'updated_at' => now(),
        ]);
        if (Schema::hasTable('kernel_current_kernel_route_gate')) {
            DB::table('kernel_current_kernel_route_gate')->where('gate_id', 1)
                ->where('active_copy_id', $copyId)->update([
                    'active_copy_id' => null,
                    'active_copy_version' => null,
                    'active_claim_token' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function setReadyRequest(string $copyId, string $requestId, int $order): void
    {
        DB::table(self::TABLE)->where('copy_id', $copyId)->update([
            'state' => 'READY',
            'ready_request_id' => $requestId,
            'ready_order' => $order,
            'updated_at' => now(),
        ]);
    }
}