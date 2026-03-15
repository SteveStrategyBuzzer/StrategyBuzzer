<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AdRewardService;

class AdController extends Controller
{
    public function __construct(
        private AdRewardService $adRewardService
    ) {}

    public function reward(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'reason' => 'unauthenticated'], 401);
        }

        $result = $this->adRewardService->reward($user);

        return response()->json($result);
    }

    public function status(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['can_watch' => false, 'remaining' => 0]);
        }

        return response()->json([
            'can_watch' => $this->adRewardService->canWatch($user),
            'remaining' => $this->adRewardService->remainingToday($user),
            'reward_amount' => (int) config('ads.rewarded.reward.amount', 10),
            'reward_type'   => config('ads.rewarded.reward.type', 'competence'),
        ]);
    }
}
