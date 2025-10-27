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
        
        // Si rareté "Quotidiennes", récupérer les quêtes quotidiennes actives
        if ($rarity === 'Quotidiennes') {
            $dailyQuests = $this->questService->getDailyQuests();
            $questsWithProgress = $dailyQuests->map(function($quest) use ($user) {
                $progressRecord = $quest->getUserProgress($user->id);
                $isCompleted = $quest->isCompletedBy($user->id);
                $progressCurrent = $progressRecord ? $progressRecord->progress_current : 0;
                
                return [
                    'quest' => $quest,
                    'is_completed' => $isCompleted,
                    'has_progress' => $progressCurrent > 0 && !$isCompleted,
                    'progress_current' => $isCompleted ? 1 : $progressCurrent,
                    'progress_total' => 1,
                    'completed_at' => $progressRecord ? $progressRecord->completed_at : null,
                ];
            });
        } else {
            $questsWithProgress = $this->questService->getUserQuests($user, $rarity);
        }
        
        // Calculer quelles raretés ont des quêtes en progression
        $raritiesWithProgress = [];
        $allRarities = ['Standard', 'Rare', 'Épique', 'Légendaire', 'Maître', 'Quotidiennes'];
        
        foreach ($allRarities as $rarityCheck) {
            if ($rarityCheck === 'Quotidiennes') {
                $dailyQuests = $this->questService->getDailyQuests();
                $hasProgress = $dailyQuests->contains(function($quest) use ($user) {
                    $progressRecord = $quest->getUserProgress($user->id);
                    $isCompleted = $quest->isCompletedBy($user->id);
                    $progressCurrent = $progressRecord ? $progressRecord->progress_current : 0;
                    return $progressCurrent > 0 && !$isCompleted;
                });
            } else {
                $quests = $this->questService->getUserQuests($user, $rarityCheck);
                $hasProgress = $quests->contains(fn($q) => $q['has_progress'] ?? false);
            }
            
            if ($hasProgress) {
                $raritiesWithProgress[] = $rarityCheck;
            }
        }
        
        return view('quests', [
            'quests' => $questsWithProgress,
            'currentRarity' => $rarity,
            'userCoins' => $user->coins ?? 0,
            'user' => $user,
            'raritiesWithProgress' => $raritiesWithProgress,
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
