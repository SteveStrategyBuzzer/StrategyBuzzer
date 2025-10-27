<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StatisticsService;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vous devez être connecté pour voir vos statistiques.');
        }
        
        $statsService = new StatisticsService();
        
        $globalStatsSolo = $statsService->getPlayerStatistics($user->id, 'solo', 'global');
        $globalStatsDuo = $statsService->getPlayerStatistics($user->id, 'duo', 'global');
        $globalStatsLeague = $statsService->getPlayerStatistics($user->id, 'league_individual', 'global');
        
        $recentMatchesSolo = $statsService->getMatchHistory($user->id, 'solo', 10);
        $recentMatchesDuo = $statsService->getMatchHistory($user->id, 'duo', 10);
        $recentMatchesLeague = $statsService->getMatchHistory($user->id, 'league_individual', 10);
        
        $params = [
            'global_stats' => [
                'solo' => $globalStatsSolo,
                'duo' => $globalStatsDuo,
                'league' => $globalStatsLeague,
            ],
            'recent_matches' => [
                'solo' => $recentMatchesSolo,
                'duo' => $recentMatchesDuo,
                'league' => $recentMatchesLeague,
            ],
        ];
        
        return view('statistics', compact('params'));
    }
}
