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
            try {
                app(\App\Services\QuestService::class)->checkAndCompleteQuests($user, 'view_history_1', [
                    'action_done' => true,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Quest hook error in HistoriqueController: ' . $e->getMessage());
            }
        }

        return view('historique');
    }
}
