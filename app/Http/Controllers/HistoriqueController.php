<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoriqueController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user) {
            app(\App\Services\QuestService::class)->checkAndCompleteQuests($user, 'view_history_1', [
                'action_done' => true,
            ]);
        }

        return view('historique');
    }
}
