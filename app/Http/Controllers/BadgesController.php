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
        
        $bronzeQuests = Quest::where('tier', 'bronze')->orderBy('order')->get();
        $silverQuests = Quest::where('tier', 'silver')->orderBy('order')->get();
        $goldQuests = Quest::where('tier', 'gold')->orderBy('order')->get();
        
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
