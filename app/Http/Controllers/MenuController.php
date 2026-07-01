<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DuoMatch;
use App\Models\PlayerMessage;
use App\Models\TeamInvitation;
use App\Models\UserQuestProgress;
use App\Models\Season;
use App\Models\ProfileStat;
use App\Models\PlayerDuoStat;
use App\Services\DailyQuestService;
use App\Services\GameModeAccessService;

class MenuController extends Controller
{
    public function index(GameModeAccessService $accessService)
    {
        $user = Auth::user();

        $profileSettings   = $user && $user->profile_settings ? $user->profile_settings : [];
        $profileComplete   = $user && ($user->profile_completed ?? false);

        $soloUnlocked      = $profileComplete;
        $masterPurchased   = $user && ($user->master_purchased ?? false);
        $masterUnlocked    = !$masterPurchased || ($masterPurchased && $profileComplete);

        // Centralized mode access
        $duoStatus         = $accessService->getModeStatus($user, 'duo');
        $ligueStatus       = $accessService->getModeStatus($user, 'league');
        $duoUnlockInfo     = $accessService->getDuoUnlockInfo($user);
        $ligueUnlockInfo   = $accessService->getLeagueUnlockInfo($user);

        // Keep legacy vars for backward compat (stats panels, etc.)
        $duoUnlocked       = $duoStatus === 'unlocked';
        $duoFullUnlocked   = $duoUnlockInfo['boss_beaten'] || $duoUnlockInfo['purchased'];
        $duoMatches        = $ligueUnlockInfo['duo_matches'];
        $ligueUnlocked     = $ligueStatus === 'unlocked';

        $duoNotifications        = 0;
        $ligueNotifications      = 0;
        $questsNotifications     = 0;
        $dailyQuestsNotifications = 0;

        if ($user) {
            $duoInvitations          = DuoMatch::where('player2_id', $user->id)->where('status', 'waiting')->count();
            $duoMessages             = PlayerMessage::where('receiver_id', $user->id)->where('is_read', false)->count();
            $duoNotifications        = $duoInvitations + $duoMessages;
            $ligueNotifications      = TeamInvitation::where('user_id', $user->id)
                ->where('status', 'pending')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count();
            $questsNotifications     = UserQuestProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')->where('rewarded', false)
                ->whereHas('quest', fn ($q) => $q->where('rarity', '!=', 'Quotidiennes'))
                ->count();
            $dailyQuestsNotifications = UserQuestProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')->where('rewarded', false)
                ->whereHas('quest', fn ($q) => $q->where('rarity', 'Quotidiennes'))
                ->count();
        }

        $season = Season::activeSeason('all');

        $dailyChallenge = null;
        if ($user) {
            try {
                $dailyService   = app(DailyQuestService::class);
                $dailyQuests    = $dailyService->getOrAssignDailyQuests($user);
                $dailyChallenge = !empty($dailyQuests) ? $dailyQuests[0] : null;
            } catch (\Throwable $e) {
            }
        }

        $playerStats = $user ? PlayerDuoStat::where('user_id', $user->id)->first() : null;

        $topPlayers = PlayerDuoStat::orderByDesc('victories')
            ->with('user')
            ->limit(3)
            ->get();

        $gamestoday = 0;
        $activeLeagues = 0;
        try {
            $gamesToday    = DuoMatch::whereDate('created_at', today())->count();
            $activeLeagues = Season::where('status', 'active')->count();
        } catch (\Throwable $e) {
        }

        $liveActivity = [
            'games_today'    => $gamesToday ?? 0,
            'active_leagues' => $activeLeagues ?? 0,
        ];

        return view('menu', compact(
            'user', 'profileSettings', 'season', 'dailyChallenge',
            'playerStats', 'topPlayers', 'liveActivity',
            'soloUnlocked', 'duoUnlocked', 'duoFullUnlocked',
            'ligueUnlocked', 'masterUnlocked', 'masterPurchased', 'profileComplete',
            'duoNotifications', 'ligueNotifications', 'questsNotifications', 'dailyQuestsNotifications',
            'duoMatches',
            'duoStatus', 'ligueStatus', 'duoUnlockInfo', 'ligueUnlockInfo'
        ));
    }

    public function notifications()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $duoInvitations   = DuoMatch::where('player2_id', $user->id)->where('status', 'waiting')->count();
        $duoMessages      = PlayerMessage::where('receiver_id', $user->id)->where('is_read', false)->count();
        $duoNotifications = $duoInvitations + $duoMessages;

        $ligueNotifications = TeamInvitation::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $questsNotifications = UserQuestProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')->where('rewarded', false)
            ->whereHas('quest', fn ($q) => $q->where('rarity', '!=', 'Quotidiennes'))
            ->count();

        $dailyQuestsNotifications = UserQuestProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')->where('rewarded', false)
            ->whereHas('quest', fn ($q) => $q->where('rarity', 'Quotidiennes'))
            ->count();

        return response()->json([
            'duo'   => $duoNotifications,
            'ligue' => $ligueNotifications,
            'quests' => $questsNotifications,
            'daily' => $dailyQuestsNotifications,
        ]);
    }
}
