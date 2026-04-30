<?php

namespace App\Services;

use App\Models\User;
use App\Models\CoinLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;

class CoinLedgerService
{
    /**
     * Credit coins to a user.
     * Uses pessimistic locking (SELECT FOR UPDATE) to prevent lost updates under concurrency.
     */
    public function credit(User $user, int $amount, string $reason, ?string $refType = null, ?int $refId = null, string $coinType = 'intelligence'): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId, $coinType) {
            $locked = User::lockForUpdate()->findOrFail($user->id);

            if ($coinType === 'competence') {
                $locked->competence_coins = ($locked->competence_coins ?? 0) + $amount;
                $balanceAfter = $locked->competence_coins;
            } else {
                $locked->coins = ($locked->coins ?? 0) + $amount;
                $balanceAfter = $locked->coins;
            }

            $locked->save();

            $entry = CoinLedger::create([
                'user_id'       => $locked->id,
                'delta'         => $amount,
                'coin_type'     => $coinType,
                'reason'        => $reason,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'balance_after' => $balanceAfter,
            ]);

            $user->refresh();

            return $entry;
        });
    }

    /**
     * Debit coins from a user.
     * Uses pessimistic locking (SELECT FOR UPDATE) to prevent lost updates under concurrency.
     */
    public function debit(User $user, int $amount, string $reason, ?string $refType = null, ?int $refId = null, string $coinType = 'intelligence'): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId, $coinType) {
            $locked = User::lockForUpdate()->findOrFail($user->id);

            if ($coinType === 'competence') {
                $currentBalance = $locked->competence_coins ?? 0;
                if ($currentBalance < $amount) {
                    throw new \Exception('Insufficient competence coins');
                }
                $locked->competence_coins = $currentBalance - $amount;
                $balanceAfter = $locked->competence_coins;
            } else {
                $currentBalance = $locked->coins ?? 0;
                if ($currentBalance < $amount) {
                    throw new \Exception('Insufficient coins');
                }
                $locked->coins = $currentBalance - $amount;
                $balanceAfter = $locked->coins;
            }

            $locked->save();

            $entry = CoinLedger::create([
                'user_id'       => $locked->id,
                'delta'         => -$amount,
                'coin_type'     => $coinType,
                'reason'        => $reason,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'balance_after' => $balanceAfter,
            ]);

            $user->refresh();

            return $entry;
        });
    }

    /**
     * Idempotent credit: will NEVER double-credit the same (user, ref_type, ref_id, reason, coin_type).
     *
     * Idempotency is enforced at two levels:
     *   1. Application layer: checks for an existing entry inside a transaction before inserting.
     *   2. Database layer: partial UNIQUE index on (user_id, ref_type, ref_id, reason, coin_type)
     *      WHERE ref_type IS NOT NULL AND ref_id IS NOT NULL acts as a last resort.
     *
     * ref_type and ref_id MUST be provided (they form the idempotency key).
     * Returns the existing ledger entry if already credited, otherwise credits and returns the new entry.
     */
    public function creditOnce(User $user, int $amount, string $reason, string $refType, int $refId, string $coinType = 'intelligence'): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId, $coinType) {
            $existing = CoinLedger::where('user_id', $user->id)
                ->where('ref_type', $refType)
                ->where('ref_id', $refId)
                ->where('reason', $reason)
                ->where('coin_type', $coinType)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $this->credit($user, $amount, $reason, $refType, $refId, $coinType);
        }, 3);
    }

    /**
     * Idempotent debit: will NEVER double-debit the same (user, ref_type, ref_id, reason, coin_type).
     *
     * Same dual-layer idempotency as creditOnce.
     * ref_type and ref_id MUST be provided.
     * Returns the existing ledger entry if already debited, otherwise debits and returns the new entry.
     */
    public function debitOnce(User $user, int $amount, string $reason, string $refType, int $refId, string $coinType = 'intelligence'): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId, $coinType) {
            $existing = CoinLedger::where('user_id', $user->id)
                ->where('ref_type', $refType)
                ->where('ref_id', $refId)
                ->where('reason', $reason)
                ->where('coin_type', $coinType)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $this->debit($user, $amount, $reason, $refType, $refId, $coinType);
        }, 3);
    }
}
