<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quest;
use App\Models\UserQuestProgress;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DailyQuestsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Récupérer toutes les quêtes quotidiennes
        $allDailyQuests = Quest::where('rarity', 'Quotidiennes')->get();
        
        // Gérer la rotation des 3 quêtes actives (24h)
        $activeDailyQuestIds = $this->getActiveDailyQuests($user, $allDailyQuests);
        
        // Séparer les quêtes actives et inactives
        $activeQuests = collect();
        $inactiveQuests = collect();
        
        foreach ($allDailyQuests as $quest) {
            // Attacher le progrès de l'utilisateur
            $quest->progress = UserQuestProgress::where('user_id', $user->id)
                ->where('quest_id', $quest->id)
                ->first();
            
            if (in_array($quest->id, $activeDailyQuestIds)) {
                $activeQuests->push($quest);
            } else {
                $inactiveQuests->push($quest);
            }
        }
        
        // Calculer le temps restant avant réinitialisation
        $resetTime = $this->getResetTime($user);
        $timeRemaining = Carbon::now()->diffInSeconds($resetTime, false);
        
        return view('daily_quests', compact('activeQuests', 'inactiveQuests', 'timeRemaining', 'user'));
    }
    
    /**
     * Récupère les IDs des 3 quêtes quotidiennes actives
     * Rotation automatique toutes les 24h
     */
    private function getActiveDailyQuests($user, $allDailyQuests)
    {
        // Récupérer les données de rotation depuis profile_settings
        $profileSettings = $user->profile_settings ?? [];
        $dailyQuestsData = $profileSettings['daily_quests_rotation'] ?? null;
        
        $now = Carbon::now();
        
        // Vérifier si rotation nécessaire (pas de données ou 24h écoulées)
        if (!$dailyQuestsData || 
            !isset($dailyQuestsData['reset_at']) || 
            Carbon::parse($dailyQuestsData['reset_at'])->isPast()) {
            
            // Sélectionner 3 nouvelles quêtes aléatoires
            $selectedIds = $allDailyQuests->random(min(3, $allDailyQuests->count()))->pluck('id')->toArray();
            
            // Calculer le prochain reset (24h)
            $resetAt = $now->copy()->addHours(24);
            
            // Sauvegarder dans profile_settings
            $profileSettings['daily_quests_rotation'] = [
                'quest_ids' => $selectedIds,
                'reset_at' => $resetAt->toDateTimeString()
            ];
            
            $user->profile_settings = $profileSettings;
            $user->save();
            
            return $selectedIds;
        }
        
        // Retourner les quêtes actives existantes
        return $dailyQuestsData['quest_ids'] ?? [];
    }
    
    /**
     * Retourne l'heure de réinitialisation des quêtes quotidiennes
     */
    private function getResetTime($user)
    {
        $profileSettings = $user->profile_settings ?? [];
        $dailyQuestsData = $profileSettings['daily_quests_rotation'] ?? null;
        
        if ($dailyQuestsData && isset($dailyQuestsData['reset_at'])) {
            return Carbon::parse($dailyQuestsData['reset_at']);
        }
        
        // Par défaut, reset dans 24h
        return Carbon::now()->addHours(24);
    }
}
