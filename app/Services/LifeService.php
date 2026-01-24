<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class LifeService
{
    /** Valeur max de vies (config/game.php -> life_max, fallback 5) */
    protected function maxLives(): int
    {
        return (int) config('game.life_max', 5);
    }

    /** Minutes entre régénérations (config/game.php -> life_regen_minutes, fallback 60) */
    protected function regenMinutes(): int
    {
        return (int) config('game.life_regen_minutes', 60);
    }

    /**
     * Ajoute 1 vie si l'échéance est passée (et pas au max).
     * Continue de régénérer vie par vie jusqu'à atteindre le maximum.
     * Accepte un User nullable pour éviter les erreurs quand non connecté.
     */
    public function regenerateLives(?User $user): void
    {
        if (!$user) {
            return;
        }
        
        // BUG FIX #2: Garantir que les vies ne dépassent jamais le maximum (même rétroactivement)
        $currentLives = (int) ($user->lives ?? 0);
        if ($currentLives > $this->maxLives()) {
            $user->lives = $this->maxLives();
            $user->next_life_regen = null;
            $user->save();
            return;
        }

        // Si déjà au max, pas besoin de timer
        if ($currentLives >= $this->maxLives()) {
            if ($user->next_life_regen) {
                $user->next_life_regen = null;
                $user->save();
            }
            return;
        }

        // Init si vide (joueur en dessous du max sans timer actif)
        if (!$user->next_life_regen) {
            $user->next_life_regen = now()->addMinutes($this->regenMinutes());
            $user->save();
            return;
        }

        // Calculer combien de vies auraient dû être régénérées pendant l'absence
        $target = Carbon::parse($user->next_life_regen);
        
        // Si le temps n'est pas encore écoulé, rien à faire
        if (now()->lt($target)) {
            return;
        }
        
        // Calculer le temps écoulé depuis la première échéance
        $minutesElapsed = now()->diffInMinutes($target);
        $regenMinutes = $this->regenMinutes();
        
        // Nombre de vies à régénérer : 1 (pour l'échéance passée) + 1 par période complète écoulée
        $livesToRegen = 1 + (int) floor($minutesElapsed / $regenMinutes);
        
        // Calculer les nouvelles vies (sans dépasser le max)
        $newLives = min($currentLives + $livesToRegen, $this->maxLives());
        $user->lives = $newLives;
        
        // Si on n'est toujours pas au max, calculer le prochain timer
        if ($newLives < $this->maxLives()) {
            // Temps restant de la période en cours
            $remainingMinutes = $regenMinutes - ($minutesElapsed % $regenMinutes);
            $user->next_life_regen = now()->addMinutes($remainingMinutes);
        } else {
            // Au max, supprimer le timer
            $user->next_life_regen = null;
        }
        
        $user->save();
    }

    /**
     * Retourne "HHh MMm SSs" jusqu'à la prochaine vie,
     * ou le texte d'attente figé si au max, ou null si non applicable.
     * Accepte un User nullable.
     */
    public function timeUntilNextRegen(?User $user): ?string
    {
        if (!$user || !$user->next_life_regen) {
            return null;
        }

        // Si déjà au max → texte d'attente figé
        if (((int) ($user->lives ?? 0)) >= $this->maxLives()) {
            return (string) config('game.life_wait_text', 'en attente 1h 00m 00s');
        }

        // Différence maintenant -> prochaine échéance
        $target = Carbon::parse($user->next_life_regen);
        if (now()->greaterThanOrEqualTo($target)) {
            return '00h 00m 00s';
        }

        $diff  = now()->diff($target);
        $hours = $diff->h + ($diff->d * 24);
        return sprintf('%02dh %02dm %02ds', $hours, $diff->i, $diff->s);
    }

    /**
     * Déduit 1 vie au joueur. 
     * Si le joueur passe en dessous du max, lance le cooldown de régénération.
     * Accepte un User nullable.
     */
    public function deductLife(?User $user): void
    {
        if (!$user) {
            return;
        }

        $currentLives = (int) ($user->lives ?? 0);
        
        // Déduire 1 vie (minimum 0)
        $user->lives = max(0, $currentLives - 1);
        
        // Si le joueur n'est pas au max de vies et qu'il n'y a pas de cooldown en cours
        // Démarrer le cooldown pour régénérer progressivement jusqu'à atteindre le max
        if ($user->lives < $this->maxLives() && !$user->next_life_regen) {
            $user->next_life_regen = now()->addMinutes($this->regenMinutes());
        }
        
        $user->save();
    }

    /**
     * Vérifie si le joueur a au moins 1 vie disponible.
     * Accepte un User nullable (retourne true pour les invités).
     */
    public function hasLivesAvailable(?User $user): bool
    {
        // Les invités peuvent toujours jouer
        if (!$user) {
            return true;
        }

        // Les utilisateurs avec pack infini peuvent toujours jouer
        if ($user->infinite_lives_until && now()->lt($user->infinite_lives_until)) {
            return true;
        }

        // Vérifier les vies normales
        return ((int) ($user->lives ?? 0)) > 0;
    }
}
