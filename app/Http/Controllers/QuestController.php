<?php

namespace App\Http\Controllers;

use App\Services\QuestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuestController extends Controller
{
    protected $questService;

    public function __construct(QuestService $questService)
    {
        $this->questService = $questService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Scanner l'historique et débloquer les quêtes rétroactives (une seule fois)
        $sessionKey = 'quests_retroactive_scan_' . $user->id;
        if (!session()->has($sessionKey)) {
            $unlockedQuests = $this->questService->scanAndUnlockRetroactiveQuests($user);
            session()->put($sessionKey, true);
            
            // Recharger l'utilisateur pour avoir les coins à jour
            $user->refresh();
        }
        
        $rarity = $request->query('rarity', 'Standard');
        
        $questsWithProgress = $this->questService->getUserQuests($user, $rarity);
        
        return view('quests', [
            'quests' => $questsWithProgress,
            'currentRarity' => $rarity,
            'userCoins' => $user->coins ?? 0,
            'user' => $user,
        ]);
    }

    public function getQuestsByRarity(Request $request, string $rarity)
    {
        $user = $request->user();
        
        $questsWithProgress = $this->questService->getUserQuests($user, $rarity);
        
        return response()->json([
            'quests' => $questsWithProgress,
        ]);
    }

    public function getUserProgress(Request $request)
    {
        $user = $request->user();
        
        $allQuests = $this->questService->getUserQuests($user);
        
        $stats = [
            'total_completed' => $allQuests->filter(fn($q) => $q['is_completed'])->count(),
            'total_quests' => $allQuests->count(),
            'total_coins_earned' => $allQuests
                ->filter(fn($q) => $q['is_completed'])
                ->sum(fn($q) => $q['quest']->reward_coins),
        ];
        
        return response()->json([
            'progress' => $allQuests,
            'stats' => $stats,
        ]);
    }
}
