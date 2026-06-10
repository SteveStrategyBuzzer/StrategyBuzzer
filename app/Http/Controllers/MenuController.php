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

class MenuController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $profileSettings   = $user && $user->profile_settings ? $user->profile_settings : [];
        $profileComplete   = $user && ($user->profile_completed ?? false);

        $choixNiveau       = is_array($profileSettings) ? ($profileSettings['choix_niveau'] ?? 1) : 1;
        $soloUnlocked      = $profileComplete;
        $duoUnlocked       = true;
        $duoFullUnlocked   = $choixNiveau >= 11;

        $profileStats      = $user ? ProfileStat::where('user_id', $user->id)->first() : null;
        $duoMatches        = $profileStats ? (($profileStats->duo_victoires ?? 0) + ($profileStats->duo_defaites ?? 0)) : 0;
        $leaguePurchased   = $user && ($user->league_purchased ?? false);
        $ligueUnlocked     = $leaguePurchased || $duoMatches >= 25;
        $masterPurchased   = $user && ($user->master_purchased ?? false);
        $masterUnlocked    = !$masterPurchased || ($masterPurchased && $profileComplete);

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
            'duoMatches'
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
