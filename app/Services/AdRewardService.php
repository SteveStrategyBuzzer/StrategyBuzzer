<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdRewardService
{
    public function __construct(
        private CoinLedgerService $coinLedgerService
    ) {}

    public function canWatch(User $user): bool
    {
        if (!config('ads.rewarded.enabled', false)) {
            return false;
        }

        $maxPerDay = (int) config('ads.rewarded.max_per_day', 3);
        $today = now()->toDateString();

        $count = DB::table('ad_rewards')
            ->where('user_id', $user->id)
            ->whereDate('rewarded_at', $today)
            ->count();

        return $count < $maxPerDay;
    }

    public function reward(User $user, string $coinType = 'competence'): array
    {
        if (!config('ads.rewarded.enabled', false)) {
            return ['success' => false, 'reason' => 'disabled'];
        }

        $rewards = config('ads.rewarded.rewards', []);
        if (!isset($rewards[$coinType])) {
            return ['success' => false, 'reason' => 'invalid_type'];
        }

        $rewardConfig = $rewards[$coinType];
        $amount    = (int) $rewardConfig['amount'];
        $maxPerDay = (int) config('ads.rewarded.max_per_day', 3);
        $today     = now()->toDateString();

        return DB::transaction(function () use ($user, $coinType, $amount, $maxPerDay, $today) {
            $count = DB::table('ad_rewards')
                ->where('user_id', $user->id)
                ->whereDate('rewarded_at', $today)
                ->lockForUpdate()
                ->count();

            if ($count >= $maxPerDay) {
                return ['success' => false, 'reason' => 'limit_reached'];
            }

            DB::table('ad_rewards')->insert([
                'user_id'     => $user->id,
                'coin_type'   => $coinType,
                'coin_amount' => $amount,
                'rewarded_at' => now(),
                'ip_address'  => request()->ip(),
            ]);

            $reason = $coinType === 'intelligence' ? 'ad_reward_intelligence' : 'ad_reward_competence';

            $this->coinLedgerService->credit(
                $user,
                $amount,
                $reason,
                'ad',
                null,
                $coinType
            );

            return [
                'success'   => true,
                'coins'     => $amount,
                'coin_type' => $coinType,
                'remaining' => max(0, $maxPerDay - $count - 1),
            ];
        });
    }

    public function usedToday(User $user): int
    {
        $today = now()->toDateString();

        return DB::table('ad_rewards')
            ->where('user_id', $user->id)
            ->whereDate('rewarded_at', $today)
            ->count();
    }

    public function remainingToday(User $user): int
    {
        $maxPerDay = (int) config('ads.rewarded.max_per_day', 3);
        return max(0, $maxPerDay - $this->usedToday($user));
    }
}
