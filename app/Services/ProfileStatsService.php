<?php

namespace App\Services;

use App\Models\User;
use App\Models\ProfileStat;
use App\Models\MatchPerformance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProfileStatsService
{
    /**
     * Enregistre un match et met à jour les statistiques
     * Utilise des opérations atomiques pour éviter les race conditions
     * 
     * @param User $user
     * @param string $gameMode 'solo', 'duo', ou 'league'
     * @param string $gameId Identifiant unique du match
     * @param bool $isVictory
     * @param float $performance Performance du match (0-100%)
     * @param int|null $roundsPlayed Nombre de manches (pour Solo seulement)
     * @param int|null $newLevel Nouveau niveau atteint (pour Solo seulement)
     * @return ProfileStat Les stats mises à jour
     */
    public static function recordMatch(
        User $user,
        string $gameMode,
        string $gameId,
        bool $isVictory,
        float $performance,
        ?int $roundsPlayed = null,
        ?int $newLevel = null
    ): ProfileStat {
        // Enregistrer le match dans l'historique
        MatchPerformance::create([
            'user_id' => $user->id,
            'game_mode' => $gameMode,
            'game_id' => $gameId,
            'performance' => round($performance, 2),
            'rounds_played' => $roundsPlayed,
            'is_victory' => $isVictory,
            'played_at' => now(),
        ]);
        
        // Récupérer ou créer ProfileStat
        $profileStat = ProfileStat::firstOrCreate(
            ['user_id' => $user->id],
            self::getDefaultStats()
        );
        
        // Mettre à jour les compteurs avec opérations atomiques
        $prefix = $gameMode . '_';
        
        DB::transaction(function () use ($profileStat, $prefix, $isVictory, $roundsPlayed) {
            // Incrémenter matchs joués (atomique)
            $profileStat->increment($prefix . 'matchs_joues');
            
            // Victoire ou défaite (atomique)
            if ($isVictory) {
                $profileStat->increment($prefix . 'victoires');
                
                // Pour Solo: victoire en 3 manches
                if ($prefix === 'solo_' && $roundsPlayed === 3) {
                    $profileStat->increment($prefix . 'victoires_3_manches');
                }
            } else {
                $profileStat->increment($prefix . 'defaites');
            }
            
            // Pour Solo: match allé en 3ème manche
            if ($prefix === 'solo_' && $roundsPlayed === 3) {
                $profileStat->increment($prefix . 'matchs_3_manches');
            }
        });
        
        // Recharger pour avoir les valeurs à jour
        $profileStat->refresh();
        
        // Recalculer ratio de victoire
        $matchsJoues = $profileStat->{$prefix . 'matchs_joues'};
        $victoires = $profileStat->{$prefix . 'victoires'};
        $ratio = $matchsJoues > 0 ? round(($victoires / $matchsJoues) * 100, 2) : 0;
        $profileStat->{$prefix . 'ratio_victoire'} = $ratio;
        
        // Recalculer performance moyenne des 10 derniers matchs
        $performanceMoyenne = MatchPerformance::getAverageLast10($user->id, $gameMode);
        $profileStat->{$prefix . 'performance_moyenne'} = $performanceMoyenne;
        
        $profileStat->save();
        
        // Mettre à jour le niveau Solo dans profile_settings si fourni
        if ($gameMode === 'solo' && $newLevel !== null) {
            $settings = (array) ($user->profile_settings ?? []);
            $settings['choix_niveau'] = $newLevel;
            $user->profile_settings = $settings;
            $user->save();
        }
        
        Log::info('Stats mises à jour', [
            'user_id' => $user->id,
            'game_mode' => $gameMode,
            'matchs_joues' => $matchsJoues,
            'victoires' => $victoires,
            'ratio' => $ratio,
            'performance_moyenne' => $performanceMoyenne
        ]);
        
        return $profileStat;
    }
    
    /**
     * Raccourci pour mettre à jour stats Solo
     */
    public static function updateSoloStats(
        User $user, 
        bool $isVictory, 
        int $roundsPlayed, 
        float $performance,
        ?int $newLevel = null,
        ?string $gameId = null
    ): ProfileStat {
        $gameId = $gameId ?? 'solo_' . time() . '_' . $user->id;
        return self::recordMatch($user, 'solo', $gameId, $isVictory, $performance, $roundsPlayed, $newLevel);
    }
    
    /**
     * Raccourci pour mettre à jour stats Duo
     */
    public static function updateDuoStats(
        User $user, 
        bool $isVictory, 
        float $performance,
        ?string $gameId = null
    ): ProfileStat {
        $gameId = $gameId ?? 'duo_' . time() . '_' . $user->id;
        return self::recordMatch($user, 'duo', $gameId, $isVictory, $performance);
    }
    
    /**
     * Raccourci pour mettre à jour stats Ligue
     */
    public static function updateLeagueStats(
        User $user, 
        bool $isVictory, 
        float $performance,
        ?string $gameId = null
    ): ProfileStat {
        $gameId = $gameId ?? 'league_' . time() . '_' . $user->id;
        return self::recordMatch($user, 'league', $gameId, $isVictory, $performance);
    }
    
    /**
     * Récupère les stats d'un mode spécifique
     */
    public static function getStats(User $user, string $mode): ?ProfileStat
    {
        return ProfileStat::where('user_id', $user->id)->first();
    }
    
    /**
     * Récupère la performance moyenne des 10 derniers matchs
     * Utilisé pour le matchmaking/classification Duo
     */
    public static function getLast10MatchesAverage(User $user, string $mode = 'duo'): float
    {
        return MatchPerformance::getAverageLast10($user->id, $mode);
    }
    
    /**
     * Récupère le niveau actuel Solo
     */
    public static function getCurrentLevel(User $user): int
    {
        $settings = (array) ($user->profile_settings ?? []);
        return $settings['choix_niveau'] ?? 1;
    }
    
    /**
     * Stats par défaut pour un nouveau ProfileStat
     */
    private static function getDefaultStats(): array
    {
        return [
            'solo_matchs_joues' => 0,
            'solo_victoires' => 0,
            'solo_defaites' => 0,
            'solo_ratio_victoire' => 0,
            'solo_matchs_3_manches' => 0,
            'solo_victoires_3_manches' => 0,
            'solo_performance_moyenne' => 0,
            'duo_matchs_joues' => 0,
            'duo_victoires' => 0,
            'duo_defaites' => 0,
            'duo_ratio_victoire' => 0,
            'duo_performance_moyenne' => 0,
            'league_matchs_joues' => 0,
            'league_victoires' => 0,
            'league_defaites' => 0,
            'league_ratio_victoire' => 0,
            'league_performance_moyenne' => 0,
        ];
    }
}
