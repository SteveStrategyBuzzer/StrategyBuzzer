<?php

namespace App\Http\Controllers;

use App\Services\DailyQuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyQuestController extends Controller
{
    public function __construct(private DailyQuestService $dailyQuestService) {}

    /**
     * GET /api/daily-quests
     * Retourne les 3 quêtes quotidiennes actives pour l'utilisateur connecté.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $quests = $this->dailyQuestService->getOrAssignDailyQuests($user);

        return response()->json([
            'quests' => $quests,
            'date'   => now()->toDateString(),
        ]);
    }

    /**
     * POST /api/daily-quests/action
     * Déclenche une action manuelle (ex: visite boutique, partage résultat…)
     * Body: { action: 'visited_shop' | 'avatar_changed' | 'result_shared' | 'avatar_desc_read' }
     */
    public function action(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $action = $request->input('action');
        $allowedActions = [
            'visited_shop',
            'avatar_changed',
            'result_shared',
            'avatar_desc_read',
            'player_invited',
            'ai_question_created',
            'helped_player',
            'shop_purchase',
        ];

        if (!in_array($action, $allowedActions, true)) {
            return response()->json(['error' => 'Invalid action'], 422);
        }

        // Mapping action → context
        $context = [$action => true];

        // Codes de quêtes quotidiennes liées aux actions manuelles
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

        $completed = [];
        if (isset($actionCodeMap[$action])) {
            $done = $this->dailyQuestService->checkAndCompleteDailyQuest(
                $user,
                $actionCodeMap[$action],
                $context
            );
            if ($done) {
                $completed[] = $actionCodeMap[$action];
            }
        }

        // Récupérer l'état mis à jour
        $quests = $this->dailyQuestService->getOrAssignDailyQuests($user);

        return response()->json([
            'quests'    => $quests,
            'completed' => $completed,
        ]);
    }
}
