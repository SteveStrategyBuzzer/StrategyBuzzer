<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function status()
    {
        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'message' => 'StrategyBuzzer API en ligne',
            'version' => 'v1',
        ]);
    }

    public function quests()
    {
        // Démo statique, à remplacer par DB/Firestore plus tard
        return response()->json([
            ['id' => 1, 'name' => 'Découvrir 5 capitales', 'reward' => 50, 'rarity' => 'rare'],
            ['id' => 2, 'name' => 'Gagner 3 duos', 'reward' => 120, 'rarity' => 'épique'],
        ]);
    }
}
