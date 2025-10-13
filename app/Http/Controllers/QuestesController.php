<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quest;
use App\Models\UserQuestProgress;
use Illuminate\Support\Facades\Auth;

class QuestesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $activeQuests = UserQuestProgress::where('user_id', $user->id)
            ->where('completed', false)
            ->with('quest')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        $completedQuests = UserQuestProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->where('claimed', false)
            ->with('quest')
            ->get();
        
        $totalIntelligencePieces = $user->intelligence_pieces ?? 0;
        
        return view('quetes', compact('activeQuests', 'completedQuests', 'totalIntelligencePieces', 'user'));
    }
    
    public function claim($questId)
    {
        $user = Auth::user();
        
        $progress = UserQuestProgress::where('user_id', $user->id)
            ->where('quest_id', $questId)
            ->where('completed', true)
            ->where('claimed', false)
            ->first();
        
        if (!$progress) {
            return redirect()->back()->with('error', 'Quête non disponible');
        }
        
        $quest = Quest::find($questId);
        
        $user->intelligence_pieces = ($user->intelligence_pieces ?? 0) + $quest->reward_pieces;
        $user->save();
        
        $progress->claimed = true;
        $progress->save();
        
        return redirect()->back()->with('success', 'Récompense réclamée ! +' . $quest->reward_pieces . ' pièces d\'Intelligence');
    }
}
