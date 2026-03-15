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

        $coinType = $request->input('coin_type', 'competence');

        $validTypes = array_keys(config('ads.rewarded.rewards', []));
        if (!in_array($coinType, $validTypes, true)) {
            return response()->json(['success' => false, 'reason' => 'invalid_type'], 422);
        }

        $result = $this->adRewardService->reward($user, $coinType);

        return response()->json($result);
    }

    public function status(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['can_watch' => false, 'remaining' => 0]);
        }

        $rewards = config('ads.rewarded.rewards', []);

        return response()->json([
            'can_watch' => $this->adRewardService->canWatch($user),
            'remaining' => $this->adRewardService->remainingToday($user),
            'rewards'   => $rewards,
        ]);
    }
}
