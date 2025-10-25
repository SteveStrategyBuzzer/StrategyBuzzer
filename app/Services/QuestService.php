<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\UserQuestProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuestService
{
    /**
     * Vérifier et compléter les quêtes basées sur un événement
     * 
     * @param User $user
     * @param string $eventCode - Code de l'événement (ex: 'first_match_10q', 'perfect_score', etc.)
     * @param array $context - Données contextuelles pour la validation
     * @return array - Liste des quêtes complétées lors de cet appel
     */
    public function checkAndCompleteQuests(User $user, string $eventCode, array $context = []): array
    {
        $completedQuests = [];
        
        // Récupérer toutes les quêtes correspondant à cet événement
        $quests = Quest::where('detection_code', $eventCode)
            ->where('auto_complete', true)
            ->get();
        
        foreach ($quests as $quest) {
            // Vérifier si l'utilisateur a déjà complété cette quête
            if ($quest->isCompletedBy($user->id)) {
                continue;
            }
            
            // Récupérer ou créer la progression
            $progress = UserQuestProgress::firstOrCreate(
                ['user_id' => $user->id, 'quest_id' => $quest->id],
                ['progress' => [], 'rewarded' => false]
            );
            
            // Vérifier si la quest est remplie selon le contexte
            if ($this->isQuestConditionMet($quest, $progress, $context)) {
                // Marquer comme complétée
                $progress->completed_at = now();
                $progress->save();
                
                // Attribuer la récompense immédiatement
                $this->rewardUser($user, $quest);
                
                $completedQuests[] = $quest;
            }
        }
        
        return $completedQuests;
    }
    
    /**
     * Vérifier si les conditions de la quête sont remplies
     */
    protected function isQuestConditionMet(Quest $quest, UserQuestProgress $progress, array $context): bool
    {
        $params = $quest->detection_params ?? [];
        
        // La logique spécifique dépend du detection_code
        // Pour l'instant, simple validation true
        // TODO: Implémenter la logique pour chaque type de quête
        
        return true;
    }
    
    /**
     * Attribuer la récompense à l'utilisateur
     */
    protected function rewardUser(User $user, Quest $quest): void
    {
        DB::transaction(function () use ($user, $quest) {
            // Incrémenter les coins
            $user->coins = ($user->coins ?? 0) + $quest->reward_coins;
            $user->save();
            
            // Marquer la progression comme récompensée
            $progress = $quest->getUserProgress($user->id);
            if ($progress) {
                $progress->rewarded = true;
                $progress->save();
            }
        });
    }
    
    /**
     * Récupérer toutes les quêtes avec progression pour un utilisateur
     * 
     * @param User $user
     * @param string $rarity - Filtrer par rareté (optionnel)
     * @return array
     */
    public function getUserQuests(User $user, ?string $rarity = null)
    {
        $query = Quest::query();
        
        if ($rarity) {
            $query->where('rarity', $rarity);
        }
        
        $quests = $query->get();
        
        // Ajouter la progression pour chaque quête
        return $quests->map(function ($quest) use ($user) {
            $progress = $quest->getUserProgress($user->id);
            
            return [
                'quest' => $quest,
                'is_completed' => $quest->isCompletedBy($user->id),
                'progress' => $progress ? $progress->progress : null,
                'completed_at' => $progress ? $progress->completed_at : null,
            ];
        });
    }
    
    /**
     * Obtenir les quêtes récemment complétées non notifiées
     */
    public function getUnnotifiedCompletedQuests(User $user): array
    {
        // Retourner les quêtes complétées mais pas encore notifiées
        // Pour afficher la popup de notification
        
        // Pour l'instant, retourner vide
        // TODO: Implémenter un système de notification
        return [];
    }
}
