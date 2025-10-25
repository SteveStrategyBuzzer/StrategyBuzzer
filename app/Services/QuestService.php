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
     * TOUT dans une transaction atomique pour éviter les race conditions
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
            // Traiter chaque quête dans une transaction atomique
            $completed = DB::transaction(function () use ($user, $quest, $context) {
                // Verrouiller et récupérer/créer la progression
                $progress = UserQuestProgress::lockForUpdate()
                    ->firstOrCreate(
                        ['user_id' => $user->id, 'quest_id' => $quest->id],
                        ['progress' => [], 'rewarded' => false]
                    );
                
                // Si déjà complétée et récompensée, skip
                if ($progress->completed_at !== null && $progress->rewarded) {
                    return false;
                }
                
                // Vérifier si la quête est remplie (avec mutation atomique de la progression)
                $isCompleted = $this->isQuestConditionMet($quest, $progress, $context);
                
                if ($isCompleted) {
                    // Marquer comme complétée
                    $progress->completed_at = now();
                    $progress->rewarded = true;
                    $progress->save();
                    
                    // Verrouiller l'utilisateur et attribuer les coins
                    $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                    if ($lockedUser) {
                        $lockedUser->coins = ($lockedUser->coins ?? 0) + $quest->reward_coins;
                        $lockedUser->save();
                    }
                    
                    return true;
                }
                
                return false;
            });
            
            if ($completed) {
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
        $detectionCode = $quest->detection_code;
        
        switch ($detectionCode) {
            case 'first_match_10q':
                return $this->checkFirstMatch10Q($context);
            
            case 'perfect_score':
                return $this->checkPerfectScore($context);
            
            case 'fast_answers_10':
                return $this->checkFastAnswers10($progress, $context);
            
            case 'buzz_fast_10':
                return $this->checkBuzzFast10($progress, $context);
            
            case 'skill_used':
                return $this->checkSkillUsed($context);
            
            default:
                return false;
        }
    }
    
    protected function checkFirstMatch10Q(array $context): bool
    {
        return isset($context['match_completed']) 
            && $context['match_completed'] === true
            && isset($context['total_questions'])
            && $context['total_questions'] >= 10;
    }
    
    protected function checkPerfectScore(array $context): bool
    {
        return isset($context['user_correct_answers']) 
            && isset($context['total_questions'])
            && $context['user_correct_answers'] == $context['total_questions']
            && $context['total_questions'] >= 10;
    }
    
    protected function checkFastAnswers10(UserQuestProgress $progress, array $context): bool
    {
        if (!isset($context['answer_time']) || $context['answer_time'] >= 2) {
            return false;
        }
        
        // Ne pas incrémenter si déjà complété
        if ($progress->completed_at !== null) {
            return false;
        }
        
        $progressData = $progress->progress ?? [];
        $fastAnswers = $progressData['fast_answers'] ?? 0;
        
        // Ne pas dépasser 10
        if ($fastAnswers >= 10) {
            return true;
        }
        
        $fastAnswers++;
        
        $progress->progress = array_merge($progressData, ['fast_answers' => $fastAnswers]);
        $progress->save();
        
        return $fastAnswers >= 10;
    }
    
    protected function checkBuzzFast10(UserQuestProgress $progress, array $context): bool
    {
        if (!isset($context['buzz_time']) || $context['buzz_time'] >= 3) {
            return false;
        }
        
        // Ne pas incrémenter si déjà complété
        if ($progress->completed_at !== null) {
            return false;
        }
        
        $progressData = $progress->progress ?? [];
        $fastBuzzes = $progressData['fast_buzzes'] ?? 0;
        
        // Ne pas dépasser 10
        if ($fastBuzzes >= 10) {
            return true;
        }
        
        $fastBuzzes++;
        
        $progress->progress = array_merge($progressData, ['fast_buzzes' => $fastBuzzes]);
        $progress->save();
        
        return $fastBuzzes >= 10;
    }
    
    protected function checkSkillUsed(array $context): bool
    {
        return isset($context['skill_used']) && $context['skill_used'] === true;
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
