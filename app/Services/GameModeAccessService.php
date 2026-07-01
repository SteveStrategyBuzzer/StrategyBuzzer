<?php

namespace App\Services;

use App\Models\User;
use App\Models\DuoMatch;
use App\Models\TeamInvitation;
use App\Models\ProfileStat;

class GameModeAccessService
{
    /**
     * Whether the user has full (permanent) access to a game mode.
     * Modes: 'duo' | 'league'
     */
    public function canAccessMode(?User $user, string $mode): bool
    {
        if (!$user) {
            return false;
        }

        return match ($mode) {
            'duo'    => $this->canAccessDuo($user),
            'league' => $this->canAccessLeague($user),
            default  => false,
        };
    }

    /**
     * Whether the user has a temporary invite-only access (not full access).
     */
    public function hasTemporaryInviteAccess(?User $user, string $mode): bool
    {
        if (!$user) {
            return false;
        }

        return match ($mode) {
            'duo'    => $this->hasDuoInviteAccess($user),
            'league' => $this->hasLeagueInviteAccess($user),
            default  => false,
        };
    }

    /**
     * Returns the display state for a mode: 'unlocked' | 'invite_only' | 'locked'
     */
    public function getModeStatus(?User $user, string $mode): string
    {
        if ($this->canAccessMode($user, $mode)) {
            return 'unlocked';
        }

        if ($this->hasTemporaryInviteAccess($user, $mode)) {
            return 'invite_only';
        }

        return 'locked';
    }

    /**
     * Unlock details for Duo (for display purposes only).
     */
    public function getDuoUnlockInfo(?User $user): array
    {
        $settings    = is_array($user?->profile_settings) ? $user->profile_settings : [];
        $choixNiveau = (int) ($settings['choix_niveau'] ?? 1);

        return [
            'boss_beaten'  => $choixNiveau >= 11,
            'purchased'    => (bool) ($user?->duo_purchased ?? false),
            'choix_niveau' => $choixNiveau,
        ];
    }

    /**
     * Unlock details for League (for display purposes only).
     */
    public function getLeagueUnlockInfo(?User $user): array
    {
        $stats      = $user ? ProfileStat::where('user_id', $user->id)->first() : null;
        $duoMatches = $stats ? (($stats->duo_victoires ?? 0) + ($stats->duo_defaites ?? 0)) : 0;

        return [
            'duo_matches'          => $duoMatches,
            'duo_matches_required' => 25,
            'purchased'            => (bool) ($user?->league_purchased ?? false),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    private function canAccessDuo(User $user): bool
    {
        if ($user->duo_purchased ?? false) {
            return true;
        }

        // Vaincre "Le Stratège" (boss niveau 10) → choix_niveau ≥ 11
        $settings    = is_array($user->profile_settings) ? $user->profile_settings : [];
        $choixNiveau = (int) ($settings['choix_niveau'] ?? 1);

        return $choixNiveau >= 11;
    }

    private function hasDuoInviteAccess(User $user): bool
    {
        return DuoMatch::where('player2_id', $user->id)
            ->where('status', 'waiting')
            ->exists();
    }

    private function canAccessLeague(User $user): bool
    {
        if ($user->league_purchased ?? false) {
            return true;
        }

        $stats      = ProfileStat::where('user_id', $user->id)->first();
        $duoMatches = $stats ? (($stats->duo_victoires ?? 0) + ($stats->duo_defaites ?? 0)) : 0;

        return $duoMatches >= 25;
    }

    private function hasLeagueInviteAccess(User $user): bool
    {
        return TeamInvitation::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }
}
