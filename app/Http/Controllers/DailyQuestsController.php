<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\UserDailyQuest;
use App\Services\DailyQuestService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyQuestsController extends Controller
{
    public function __construct(private DailyQuestService $dailyQuestService) {}

    /**
     * Page principale : /quetes-quotidiennes
     * Remplace l'ancienne approche profile_settings par la table user_daily_quests.
     */
    public function index()
    {
        $user = Auth::user();

        // Assigner/récupérer les 3 quêtes du jour via DailyQuestService
        $activeQuestData = $this->dailyQuestService->getOrAssignDailyQuests($user);
        $today           = Carbon::today()->toDateString();
        $activeIds       = array_column($activeQuestData, 'quest_id');

        // Toutes les quêtes quotidiennes
        $allDailyQuests = Quest::whereBetween('id', [76, 95])->get();

        // Enrichir les quêtes actives avec les données de progression
        $activeQuests = collect($activeQuestData)->map(function ($data) {
            $q = Quest::find($data['quest_id']);
            if (!$q) return null;
            $q->user_progress_data = $data;
            $q->progress_current   = $data['current'];
            $q->progress_max       = $data['max'];
            $q->is_completed       = $data['completed'];
            return $q;
        })->filter()->values();

        // Quêtes inactives du jour (les autres)
        $inactiveQuests = $allDailyQuests->whereNotIn('id', $activeIds)->values();

        // Temps restant avant prochain reset (minuit)
        $midnight      = Carbon::tomorrow()->startOfDay();
        $timeRemaining = max(0, Carbon::now()->diffInSeconds($midnight, false));

        return view('daily_quests', compact('activeQuests', 'inactiveQuests', 'timeRemaining', 'user'));
    }

    /**
     * POST /quetes-quotidiennes/action
     * Déclenchement d'actions manuelles (visite boutique, changement avatar, etc.)
     */
    public function triggerAction(Request $request)
    {
        $user   = Auth::user();
        $action = $request->input('action');

        $actionCodeMap = [
            'visited_shop'        => 'daily_visit_shop',
            'avatar_changed'      => 'daily_change_avatar',
            'result_shared'       => 'daily_share_result',
            'avatar_desc_read'    => 'daily_read_avatar_desc',
            'player_invited'      => 'daily_invite_player',
            'ai_question_created' => 'daily_create_ai_question',
            'helped_player'       => 'daily_help_player',
            'shop_purchase'       => 'daily_buy_item',
        ];

        if ($user && isset($actionCodeMap[$action])) {
            try {
                $this->dailyQuestService->checkAndCompleteDailyQuest(
                    $user,
                    $actionCodeMap[$action],
                    [$action => true]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('DailyQuestsController::triggerAction: ' . $e->getMessage());
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/daily-quests
     * API JSON : retourne les 3 quêtes du jour avec progression.
     */
    public function apiIndex()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $quests        = $this->dailyQuestService->getOrAssignDailyQuests($user);
        $midnight      = Carbon::tomorrow()->startOfDay();
        $timeRemaining = max(0, Carbon::now()->diffInSeconds($midnight, false));

        return response()->json([
            'quests'        => $quests,
            'date'          => now()->toDateString(),
            'reset_in_secs' => $timeRemaining,
        ]);
    }
}
