<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DuoMatch;
use App\Models\PlayerMessage;
use App\Models\TeamInvitation;
use App\Models\UserQuestProgress;

class MenuController extends Controller
{
    public function index()
    {
        return view('menu');
    }
    
    public function notifications()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }
        
        $duoInvitations = DuoMatch::where('player2_id', $user->id)
            ->where('status', 'waiting')
            ->count();
        
        $duoMessages = PlayerMessage::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
        
        $duoNotifications = $duoInvitations + $duoMessages;
        
        $ligueNotifications = TeamInvitation::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
        
        $questsNotifications = UserQuestProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('rewarded', false)
            ->whereHas('quest', function($q) {
                $q->where('rarity', '!=', 'Quotidiennes');
            })
            ->count();
        
        $dailyQuestsNotifications = UserQuestProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('rewarded', false)
            ->whereHas('quest', function($q) {
                $q->where('rarity', 'Quotidiennes');
            })
            ->count();
        
        return response()->json([
            'duo' => $duoNotifications,
            'ligue' => $ligueNotifications,
            'quests' => $questsNotifications,
            'daily' => $dailyQuestsNotifications
        ]);
    }
}
