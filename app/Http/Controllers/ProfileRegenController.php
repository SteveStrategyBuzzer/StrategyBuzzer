<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\LifeService;
use Illuminate\Http\JsonResponse;

class ProfileRegenController extends Controller
{
    public function __invoke(Request $request, LifeService $lifeService): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Met à jour si l’heure est passée + recalcule les champs
        $lifeService->regenerateLives($user);

        return response()->json([
            'lives'          => (int) ($user->lives ?? 0),
            'next_life_regen'=> (string) $user->next_life_regen, // string via cast Eloquent
            'countdown'      => $lifeService->timeUntilNextRegen($user),
        ]);
    }
}
