<?php

namespace App\Services;

use App\Models\User;
use App\Models\CoinLedger;
use Illuminate\Support\Facades\DB;

class CoinLedgerService
{
    public function credit(User $user, int $amount, string $reason, ?string $refType = null, ?int $refId = null): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId) {
            $user->lockForUpdate()->find($user->id);
            
            $user->coins += $amount;
            $user->save();

            return CoinLedger::create([
                'user_id' => $user->id,
                'delta' => $amount,
                'reason' => $reason,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'balance_after' => $user->coins,
            ]);
        });
    }

    public function debit(User $user, int $amount, string $reason, ?string $refType = null, ?int $refId = null): CoinLedger
    {
        return DB::transaction(function () use ($user, $amount, $reason, $refType, $refId) {
            $user->lockForUpdate()->find($user->id);
            
            if ($user->coins < $amount) {
                throw new \Exception("Insufficient coins");
            }

            $user->coins -= $amount;
            $user->save();

            return CoinLedger::create([
                'user_id' => $user->id,
                'delta' => -$amount,
                'reason' => $reason,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'balance_after' => $user->coins,
            ]);
        });
    }
}
