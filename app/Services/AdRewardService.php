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
        if ($user->master_purchased ?? false) {
            return false;
        }

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

    public function reward(User $user): array
    {
        if ($user->master_purchased ?? false) {
            return ['success' => false, 'reason' => 'premium'];
        }

        if (!config('ads.rewarded.enabled', false)) {
            return ['success' => false, 'reason' => 'disabled'];
        }

        $coinType  = config('ads.rewarded.reward.type', 'competence');
        $amount    = (int) config('ads.rewarded.reward.amount', 10);
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

            $this->coinLedgerService->credit(
                $user,
                $amount,
                'ad_reward_competence',
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

    public function remainingToday(User $user): int
    {
        if ($user->master_purchased ?? false) {
            return 0;
        }

        $maxPerDay = (int) config('ads.rewarded.max_per_day', 3);
        $today = now()->toDateString();

        $used = DB::table('ad_rewards')
            ->where('user_id', $user->id)
            ->whereDate('rewarded_at', $today)
            ->count();

        return max(0, $maxPerDay - $used);
    }
}
