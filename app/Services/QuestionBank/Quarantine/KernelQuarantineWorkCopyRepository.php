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
    public const DEFAULT_CLAIM_TTL_SECONDS = 900;

    /** @return object|null */
    public function find(string $copyId, bool $lock = false): ?object
    {
        $query = DB::table(self::TABLE)->where('copy_id', $copyId);
        if ($lock) {
            $query->lock(DB::getDriverName() === 'pgsql' ? 'FOR UPDATE SKIP LOCKED' : true);
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
    public function claim(
        string $copyId,
        int $copyVersion,
        string $claimToken,
        int $ttlSeconds = self::DEFAULT_CLAIM_TTL_SECONDS
    ): bool
    {
        return DB::transaction(function () use ($copyId, $copyVersion, $claimToken, $ttlSeconds): bool {
            $now = now();
            if (Schema::hasColumn(self::TABLE, 'claim_expires_at')) {
                $expiredRows = DB::table(self::TABLE)
                    ->where('state', 'IN_FLIGHT')
                    ->whereNotNull('claim_expires_at')
                    ->where('claim_expires_at', '<=', $now)
                    ->lockForUpdate()->get(['copy_id','copy_version']);
                foreach ($expiredRows as $expired) {
                    DB::table(self::TABLE)->where('copy_id', $expired->copy_id)
                    ->where('state', 'IN_FLIGHT')
                    ->update([
                        'state' => 'READY',
                        'claim_token' => null,
                        'claimed_version' => null,
                        'claimed_at' => null,
                        'claim_expires_at' => null,
                        'updated_at' => $now,
                    ]);
                    DB::table('kernel_current_kernel_dispatches')
                        ->where('copy_id', $expired->copy_id)->where('state', 'IN_FLIGHT')
                        ->update(['state' => 'READY', 'claimed_at' => null, 'updated_at' => $now]);
                    DB::table('kernel_current_kernel_route_gate')->where('gate_id', 1)
                        ->where('active_copy_id', $expired->copy_id)->update([
                            'active_copy_id' => null, 'active_copy_version' => null,
                            'active_claim_token' => null, 'updated_at' => $now,
                        ]);
                    $this->transition((string) $expired->copy_id, 'IN_FLIGHT', 'READY', (int) $expired->copy_version, 'CLAIM_EXPIRED');
                }
            }
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
                if (Schema::hasColumn(self::TABLE, 'claim_expires_at')) {
                    DB::table(self::TABLE)->where('copy_id', $copyId)->update([
                        'claim_expires_at' => now()->addSeconds(max(1, $ttlSeconds)),
                    ]);
                }
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
                $this->transition($copyId, 'READY', 'IN_FLIGHT', $copyVersion, 'CLAIM');
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
        if (property_exists($copy, 'claim_expires_at')
            && $copy->claim_expires_at !== null
            && now()->gte($copy->claim_expires_at)) {
            throw new LogicException('Claim Quarantaine expiré.');
        }
    }

    public function claimOldestReady(
        string $claimToken,
        int $ttlSeconds = self::DEFAULT_CLAIM_TTL_SECONDS
    ): ?object {
        return DB::transaction(function () use ($claimToken, $ttlSeconds): ?object {
            $copy = $this->oldestReady(true);
            if ($copy === null || ! $this->claim(
                (string) $copy->copy_id,
                (int) $copy->copy_version,
                $claimToken,
                $ttlSeconds,
            )) {
                return null;
            }
            return $this->find((string) $copy->copy_id);
        });
    }

    public function finishClaim(
        string $copyId,
        int $copyVersion,
        string $claimToken,
        bool $returned = false
    ): bool {
        return DB::transaction(function () use ($copyId, $copyVersion, $claimToken, $returned): bool {
            if (! $returned) {
                throw new LogicException('La décision terminale ReadyBank n’est pas une transition Quarantaine.');
            }
            $copy = $this->find($copyId, true);
            if ($copy === null) {
                return false;
            }
            $this->assertClaim($copy, $copyVersion, $claimToken);
            $values = [
                'state' => 'IN_FLIGHT',
                'updated_at' => now(),
            ];
            $updated = DB::table(self::TABLE)->where('copy_id', $copyId)->update($values);
            return $updated === 1;
        });
    }

    public function bumpVersion(string $copyId, int $expectedVersion, bool $advance = true): int
    {
        $values = [
            'updated_at' => now(),
        ];
        if ($advance) {
            $values['copy_version'] = $expectedVersion + 1;
        }
        $updated = DB::table(self::TABLE)
            ->where('copy_id', $copyId)
            ->whereIn('state', ['EDITABLE', 'READY', 'IN_FLIGHT'])
            ->where('copy_version', $expectedVersion)
            ->update($values);
        if ($updated !== 1) {
            throw new LogicException('Copie Quarantaine modifiée depuis sa lecture.');
        }
        return $advance ? $expectedVersion + 1 : $expectedVersion;
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
        if (Schema::hasColumn(self::TABLE, 'claim_expires_at')) {
            DB::table(self::TABLE)->where('copy_id', $copyId)->update(['claim_expires_at' => null]);
        }
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

    private function transition(string $copyId, string $from, string $to, int $version, string $reason): void
    {
        if (! Schema::hasTable('kernel_quarantine_transition_history')) {
            return;
        }
        DB::table('kernel_quarantine_transition_history')->insert([
            'transition_id' => (string) Str::orderedUuid(),
            'copy_id' => $copyId,
            'from_state' => $from,
            'to_state' => $to,
            'copy_version' => $version,
            'reason_code' => $reason,
            'payload' => null,
            'created_at' => now(),
        ]);
    }
}