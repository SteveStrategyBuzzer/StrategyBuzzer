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
                // Récupérer ou créer la progression, puis la verrouiller
                $progress = UserQuestProgress::firstOrCreate(
                    ['user_id' => $user->id, 'quest_id' => $quest->id],
                    ['progress' => [], 'rewarded' => false]
                );
                
                // Recharger avec verrouillage
                $progress = UserQuestProgress::where('id', $progress->id)->lockForUpdate()->first();
                
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
     * Calculer la progression d'une quête selon ses paramètres
     * @return array ['current' => int, 'max' => int]
     */
    protected function getQuestProgression(Quest $quest, $progressRecord, bool $isCompleted): array
    {
        $params = is_array($quest->detection_params) ? $quest->detection_params : [];
        
        // Par défaut : quête one-shot
        $max = 1;
        $current = $isCompleted ? 1 : 0;
        
        // Extraire max depuis detection_params si disponible
        if (isset($params['count'])) {
            $max = $params['count'];
        } elseif (isset($params['wins'])) {
            $max = $params['wins'];
        } elseif (isset($params['matches'])) {
            $max = $params['matches'];
        } elseif (isset($params['themes'])) {
            $max = $params['themes'];
        } elseif (isset($params['level'])) {
            $max = $params['level'];
        } elseif (isset($params['coins'])) {
            $max = $params['coins'];
        }
        
        // Extraire current depuis progress si disponible
        if ($progressRecord && $progressRecord->progress) {
            $progressData = $progressRecord->progress;
            
            // Essayer différentes clés selon le type de quête
            if (isset($progressData['current'])) {
                $current = $progressData['current'];
            } elseif (isset($progressData['fast_answers'])) {
                $current = $progressData['fast_answers'];
            } elseif (isset($progressData['fast_buzzes'])) {
                $current = $progressData['fast_buzzes'];
            } elseif (isset($progressData['count'])) {
                $current = $progressData['count'];
            }
        }
        
        return [
            'current' => min($current, $max), // Ne jamais dépasser max
            'max' => $max
        ];
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
            $progressRecord = $quest->getUserProgress($user->id);
            $isCompleted = $quest->isCompletedBy($user->id);
            
            // Extraire la progression depuis detection_params et progress
            $progression = $this->getQuestProgression($quest, $progressRecord, $isCompleted);
            $currentProgress = $progression['current'];
            $totalProgress = $progression['max'];
            
            if ($isCompleted) {
                $currentProgress = $totalProgress;
            }
            
            return [
                'quest' => $quest,
                'is_completed' => $isCompleted,
                'progress_current' => $currentProgress,
                'progress_total' => $totalProgress,
                'completed_at' => $progressRecord ? $progressRecord->completed_at : null,
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
    
    /**
     * Obtenir les 3 quêtes quotidiennes actives pour aujourd'hui
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDailyQuests()
    {
        $today = now()->toDateString();
        
        // Vérifier s'il y a une rotation pour aujourd'hui
        $rotation = DB::table('daily_quest_rotation')
            ->where('rotation_date', $today)
            ->first();
        
        // Si pas de rotation ou date périmée, en créer une nouvelle
        if (!$rotation) {
            $this->rotateDailyQuests();
            $rotation = DB::table('daily_quest_rotation')
                ->where('rotation_date', $today)
                ->first();
        }
        
        if (!$rotation) {
            return collect([]); // Retourner une Collection vide
        }
        
        // Récupérer les quêtes depuis les IDs
        $questIds = json_decode($rotation->quest_ids, true);
        return Quest::whereIn('id', $questIds)->get();
    }
    
    /**
     * Rotation des quêtes quotidiennes : sélectionner 3 quêtes aléatoires
     * @return void
     */
    public function rotateDailyQuests()
    {
        $today = now()->toDateString();
        
        // Récupérer toutes les quêtes quotidiennes disponibles
        $availableQuests = Quest::where('rarity', 'Quotidienne')->get();
        
        if ($availableQuests->count() < 3) {
            return; // Pas assez de quêtes quotidiennes
        }
        
        // Sélectionner 3 quêtes aléatoires
        $selectedQuests = $availableQuests->random(min(3, $availableQuests->count()));
        $questIds = $selectedQuests->pluck('id')->toArray();
        
        // Supprimer l'ancienne rotation et créer la nouvelle
        DB::table('daily_quest_rotation')->where('rotation_date', $today)->delete();
        DB::table('daily_quest_rotation')->insert([
            'rotation_date' => $today,
            'quest_ids' => json_encode($questIds),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    /**
     * Scanner l'historique de jeu et débloquer automatiquement les quêtes déjà accomplies
     * 
     * @param User $user
     * @return array - Quêtes débloquées
     */
    public function scanAndUnlockRetroactiveQuests(User $user): array
    {
        $unlockedQuests = [];
        
        // Récupérer toutes les quêtes non complétées pour cet utilisateur
        $allQuests = Quest::all();
        
        foreach ($allQuests as $quest) {
            // Ignorer les quêtes déjà complétées
            if ($quest->isCompletedBy($user->id)) {
                continue;
            }
            
            // Vérifier si la condition rétroactive est remplie
            $isMet = $this->checkRetroactiveCondition($user, $quest);
            
            if ($isMet) {
                $this->completeQuestRetroactive($user, $quest);
                $unlockedQuests[] = $quest;
            }
        }
        
        return $unlockedQuests;
    }
    
    /**
     * Vérifier si une quête est complétée rétroactivement selon son detection_code
     */
    protected function checkRetroactiveCondition(User $user, Quest $quest): bool
    {
        $code = $quest->detection_code;
        $params = is_array($quest->detection_params) ? $quest->detection_params : [];
        
        // Compter les parties totales
        $totalMatches = $this->getTotalMatchesCount($user);
        
        switch ($code) {
            // Parties jouées
            case 'first_match_10q':
            case 'play_50_matches':
            case 'play_100_matches':
            case 'play_250_matches':
            case 'play_500_matches':
                $required = $params['matches'] ?? 1;
                return $totalMatches >= $required;
            
            // Séries de victoires (impossible à détecter rétroactivement de manière fiable)
            case 'win_streak_3':
            case 'win_streak_5':
            case 'win_streak_10':
                return false; // Nécessite tracking en temps réel
            
            // Scores parfaits
            case 'perfect_score':
            case 'perfect_score_3':
            case 'perfect_score_10':
            case 'perfect_score_25':
                $required = $params['count'] ?? 1;
                $perfectScores = $this->getPerfectScoresCount($user);
                return $perfectScores >= $required;
            
            // Niveaux
            case 'level_25':
            case 'level_50':
            case 'level_75':
            case 'level_100':
                $requiredLevel = $params['level'] ?? 1;
                return ($user->level ?? 0) >= $requiredLevel;
            
            // Pièces accumulées
            case 'coins_1000':
            case 'coins_5000':
                $requiredCoins = $params['coins'] ?? 0;
                return ($user->coins ?? 0) >= $requiredCoins;
            
            // Divisions (Duo/Ligue)
            case 'division_silver':
            case 'division_gold':
            case 'division_legend':
                return $this->checkDivisionReached($user, $code);
            
            // Victoires Duo
            case 'duo_wins_10':
                $required = $params['wins'] ?? 10;
                $duoWins = $this->getDuoWinsCount($user);
                return $duoWins >= $required;
            
            // Boss defeats (Solo mode) - Table non disponible
            case 'boss_defeats_5':
            case 'boss_defeats_10':
                return false; // Aucune table solo_boss_history dans la base
            
            // Avatars débloqués
            case 'avatars_unlocked_10':
            case 'avatars_unlocked_25':
                $required = $params['count'] ?? 1;
                $avatarsUnlocked = DB::table('user_avatars')
                    ->where('user_id', $user->id)
                    ->count();
                return $avatarsUnlocked >= $required;
            
            // Thèmes joués
            case 'themes_5':
            case 'themes_10':
                $required = $params['themes'] ?? 1;
                $themesPlayed = $this->getUniqueThemesCount($user);
                return $themesPlayed >= $required;
            
            // Compétences utilisées
            case 'skill_used':
            case 'skills_used_50':
                $required = $params['count'] ?? 1;
                // Compter depuis game_history ou équivalent
                return false; // Nécessite tracking spécifique
            
            // Réponses rapides (nécessite tracking temps réel)
            case 'fast_answers_10':
            case 'ultra_fast_answers_10':
            case 'buzz_fast_10':
            case 'ultra_fast_buzz_20':
            case 'correct_streak_25':
            case 'correct_streak_50':
                return false; // Impossible à détecter rétroactivement de manière fiable
            
            // Quêtes spéciales
            case 'comeback_0_5':
            case 'perfect_10_0':
            case 'night_owl':
                return false; // Nécessite tracking en temps réel
            
            default:
                return false;
        }
    }
    
    /**
     * Compter le nombre total de parties jouées
     */
    protected function getTotalMatchesCount(User $user): int
    {
        $duoMatches = DB::table('duo_matches')
            ->where(function($query) use ($user) {
                $query->where('player1_id', $user->id)
                      ->orWhere('player2_id', $user->id);
            })
            ->where('status', 'completed')
            ->count();
        
        $leagueMatches = DB::table('league_individual_matches')
            ->where(function($query) use ($user) {
                $query->where('player1_id', $user->id)
                      ->orWhere('player2_id', $user->id);
            })
            ->where('status', 'completed')
            ->count();
        
        return $duoMatches + $leagueMatches;
    }
    
    /**
     * Compter les scores parfaits (10/10 bonnes réponses pour le joueur)
     * Note: Seuls les matchs Duo stockent les scores, pas les matchs Ligue
     */
    protected function getPerfectScoresCount(User $user): int
    {
        $duoPerfect = DB::table('duo_matches')
            ->where(function($query) use ($user) {
                $query->where(function($q) use ($user) {
                    $q->where('player1_id', $user->id)
                      ->whereRaw('player1_score >= 10');
                })
                ->orWhere(function($q) use ($user) {
                    $q->where('player2_id', $user->id)
                      ->whereRaw('player2_score >= 10');
                });
            })
            ->where('status', 'completed')
            ->count();
        
        return $duoPerfect;
    }
    
    /**
     * Compter les victoires Duo
     */
    protected function getDuoWinsCount(User $user): int
    {
        return DB::table('duo_matches')
            ->where(function($query) use ($user) {
                $query->where(function($q) use ($user) {
                    $q->where('player1_id', $user->id)
                      ->whereRaw('player1_score > player2_score');
                })
                ->orWhere(function($q) use ($user) {
                    $q->where('player2_id', $user->id)
                      ->whereRaw('player2_score > player1_score');
                });
            })
            ->where('status', 'completed')
            ->count();
    }
    
    /**
     * Vérifier si une division a été atteinte
     */
    protected function checkDivisionReached(User $user, string $code): bool
    {
        $divisionMap = [
            'division_silver' => ['Argent', 'Or', 'Platine', 'Diamant', 'Légende'],
            'division_gold' => ['Or', 'Platine', 'Diamant', 'Légende'],
            'division_legend' => ['Légende'],
        ];
        
        $requiredDivisions = $divisionMap[$code] ?? [];
        
        // Vérifier division Duo
        if (in_array($user->duo_division ?? 'Bronze', $requiredDivisions)) {
            return true;
        }
        
        // Vérifier division Ligue
        if (in_array($user->league_division ?? 'Bronze', $requiredDivisions)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Compter les thèmes uniques joués
     * Note: duo_matches stocke 'theme' (string), league n'a pas de thèmes
     */
    protected function getUniqueThemesCount(User $user): int
    {
        // Récupérer tous les thèmes uniques depuis Duo (colonne 'theme' pas 'theme_id')
        $duoThemes = DB::table('duo_matches')
            ->where(function($query) use ($user) {
                $query->where('player1_id', $user->id)
                      ->orWhere('player2_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereNotNull('theme')
            ->distinct()
            ->pluck('theme')
            ->toArray();
        
        return count($duoThemes);
    }
    
    /**
     * Compléter une quête de manière rétroactive avec transaction atomique
     */
    protected function completeQuestRetroactive(User $user, Quest $quest): void
    {
        DB::transaction(function () use ($user, $quest) {
            // Créer ou récupérer la progression avec verrouillage
            $progress = UserQuestProgress::firstOrCreate(
                ['user_id' => $user->id, 'quest_id' => $quest->id],
                ['progress' => [], 'rewarded' => false]
            );
            
            $progress = UserQuestProgress::where('id', $progress->id)->lockForUpdate()->first();
            
            // Si déjà complétée, skip
            if ($progress->completed_at !== null && $progress->rewarded) {
                return;
            }
            
            // Marquer comme complétée
            $progress->completed_at = now();
            $progress->rewarded = true;
            $progress->save();
            
            // Attribuer les pièces
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if ($lockedUser) {
                $lockedUser->coins = ($lockedUser->coins ?? 0) + $quest->reward_coins;
                $lockedUser->save();
            }
        });
    }
}
