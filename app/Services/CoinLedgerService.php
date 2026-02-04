<?php

namespace App\Services;

use App\Models\User;
use App\Models\CoinLedger;
use Illuminate\Support\Facades\DB;

class CoinLedgerService
{
    public function credit(User $user, int $amount, string $reason, ?string $refType = null, ?int $refId = null, string $coinType = 'intelligence'): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId, $coinType) {
            $user->lockForUpdate()->find($user->id);
            
            if ($coinType === 'competence') {
                $user->competence_coins = ($user->competence_coins ?? 0) + $amount;
                $balanceAfter = $user->competence_coins;
            } else {
                $user->coins = ($user->coins ?? 0) + $amount;
                $balanceAfter = $user->coins;
            }
            $user->save();

            return CoinLedger::create([
                'user_id' => $user->id,
                'delta' => $amount,
                'reason' => $reason,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'balance_after' => $balanceAfter,
            ]);
        });
    }

    public function debit(User $user, int $amount, string $reason, ?string $refType = null, ?int $refId = null, string $coinType = 'intelligence'): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId, $coinType) {
            $user->lockForUpdate()->find($user->id);
            
            if ($coinType === 'competence') {
                $currentBalance = $user->competence_coins ?? 0;
                if ($currentBalance < $amount) {
                    throw new \Exception("Insufficient competence coins");
                }
                $user->competence_coins = $currentBalance - $amount;
                $balanceAfter = $user->competence_coins;
            } else {
                if ($user->coins < $amount) {
                    throw new \Exception("Insufficient coins");
                }
                $user->coins -= $amount;
                $balanceAfter = $user->coins;
            }
            $user->save();

            return CoinLedger::create([
                'user_id' => $user->id,
                'delta' => -$amount,
                'reason' => $reason,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'balance_after' => $balanceAfter,
            ]);
        });
    }
}
