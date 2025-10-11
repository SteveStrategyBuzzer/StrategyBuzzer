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
     * Ajoute 1 vie si l’échéance est passée (et pas au max).
     * Accepte un User nullable pour éviter les erreurs quand non connecté.
     */
    public function regenerateLives(?User $user): void
    {
        if (!$user) {
            return;
        }

        // Init si vide
        if (!$user->next_life_regen) {
            $user->next_life_regen = now()->addMinutes($this->regenMinutes());
            $user->save();
            return;
        }

        // Si l’heure est atteinte et qu’on n’est pas au max
        $currentLives = (int) ($user->lives ?? 0);
        if (now()->greaterThanOrEqualTo($user->next_life_regen) && $currentLives < $this->maxLives()) {
            $user->lives         = $currentLives + 1;
            $user->next_life_regen = now()->addMinutes($this->regenMinutes());
            $user->save();
        }
    }

    /**
     * Retourne "HHh MMm SSs" jusqu’à la prochaine vie,
     * ou le texte d’attente figé si au max, ou null si non applicable.
     * Accepte un User nullable.
     */
    public function timeUntilNextRegen(?User $user): ?string
    {
        if (!$user || !$user->next_life_regen) {
            return null;
        }

        // Si déjà au max → texte d’attente figé
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
     * Si c'était la dernière vie, lance le cooldown de régénération.
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
        
        // Si le joueur n'a plus de vies et qu'il n'y a pas de cooldown en cours
        if ($user->lives === 0 && !$user->next_life_regen) {
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
