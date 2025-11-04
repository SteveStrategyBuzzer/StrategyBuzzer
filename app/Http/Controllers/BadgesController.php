<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quest;
use App\Models\UserQuestProgress;
use Illuminate\Support\Facades\Auth;

class BadgesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Mapper les rarités aux tiers (bronze/silver/gold)
        // Bronze: Quêtes faciles (Standard, Quotidiennes)
        $bronzeQuests = Quest::whereIn('rarity', ['Standard', 'Quotidiennes'])->get();
        
        // Silver: Quêtes moyennes (Rare, Épique)
        $silverQuests = Quest::whereIn('rarity', ['Rare', 'Épique'])->get();
        
        // Gold: Quêtes difficiles (Légendaire, Maître)
        $goldQuests = Quest::whereIn('rarity', ['Légendaire', 'Maître'])->get();
        
        // Attacher les progrès de l'utilisateur à chaque quête
        foreach ($bronzeQuests as $quest) {
            $quest->progress = UserQuestProgress::where('user_id', $user->id)
                ->where('quest_id', $quest->id)
                ->first();
        }
        
        foreach ($silverQuests as $quest) {
            $quest->progress = UserQuestProgress::where('user_id', $user->id)
                ->where('quest_id', $quest->id)
                ->first();
        }
        
        foreach ($goldQuests as $quest) {
            $quest->progress = UserQuestProgress::where('user_id', $user->id)
                ->where('quest_id', $quest->id)
                ->first();
        }
        
        return view('badges', compact('bronzeQuests', 'silverQuests', 'goldQuests', 'user'));
    }
}
