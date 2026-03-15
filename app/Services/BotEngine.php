<?php

namespace App\Services;

use App\Models\User;
use App\Models\BotProfile;
use App\Models\PlayerAvatarStat;
use Illuminate\Support\Facades\Log;

class BotEngine
{
    public function getSimulationParams(User $user): array
    {
        $botProfile = BotProfile::find($user->id);
        $avatarSlug = $botProfile->bot_avatar_slug ?? null;

        $globalStats = $this->getGlobalStats($user);

        if ($avatarSlug) {
            $avatarStats = PlayerAvatarStat::where('user_id', $user->id)
                ->where('avatar_slug', $avatarSlug)
                ->first();

            if ($avatarStats && $avatarStats->matches_played > 0) {
                return [
                    'avg_buzz_ms'   => $avatarStats->avg_buzz_ms,
                    'accuracy_rate' => $avatarStats->accuracy_rate,
                    'avatar_slug'   => $avatarSlug,
                    'source'        => 'avatar',
                ];
            }
        }

        return array_merge($globalStats, ['source' => 'global']);
    }

    public function selectBotForDivision(string $division, ?int $excludeUserId = null): ?array
    {
        $query = BotProfile::where('is_active', true)
            ->join('bot_qualification_events', 'bot_profiles.user_id', '=', 'bot_qualification_events.user_id')
            ->select('bot_profiles.user_id')
            ->groupBy('bot_profiles.user_id')
            ->havingRaw('COUNT(*) >= 10');

        if ($excludeUserId) {
            $query->where('bot_profiles.user_id', '!=', $excludeUserId);
        }

        $candidates = $query->orderByRaw('COUNT(*) DESC')->limit(20)->pluck('user_id');

        if ($candidates->isEmpty()) {
            return null;
        }

        $selectedUserId = $candidates->random();
        $user = User::find($selectedUserId);
        if (!$user) return null;

        $botProfile = BotProfile::find($selectedUserId);
        $params = $this->getSimulationParams($user);

        return [
            'owner_id'      => $user->id,
            'display_name'  => 'Bot · ' . ($user->display_name ?? 'Joueur'),
            'avatar_slug'   => $botProfile->bot_avatar_slug,
            'avatar_url'    => $user->avatar_url,
            'stake_enabled' => $botProfile->stake_enabled,
            'max_stake'     => $botProfile->max_stake_per_match,
            'params'        => $params,
        ];
    }

    public function recordBotResult(int $ownerUserId, bool $won): void
    {
        $botProfile = BotProfile::find($ownerUserId);
        if (!$botProfile) return;

        if ($won) {
            $botProfile->increment('bot_wins');
        } else {
            $botProfile->increment('bot_losses');
        }
    }

    public static function updateAvatarStats(User $user, string $avatarSlug, int $buzzMs, bool $correct): void
    {
        $stat = PlayerAvatarStat::firstOrCreate(
            ['user_id' => $user->id, 'avatar_slug' => $avatarSlug],
            ['matches_played' => 0, 'avg_buzz_ms' => 3000, 'accuracy_rate' => 0.5]
        );

        $oldCount = $stat->matches_played;
        $newCount = $oldCount + 1;

        $stat->avg_buzz_ms = (int) (($stat->avg_buzz_ms * $oldCount + $buzzMs) / $newCount);
        $stat->accuracy_rate = ($stat->accuracy_rate * $oldCount + ($correct ? 1 : 0)) / $newCount;
        $stat->matches_played = $newCount;
        $stat->save();
    }

    private function getGlobalStats(User $user): array
    {
        $allAvatarStats = PlayerAvatarStat::where('user_id', $user->id)->get();

        if ($allAvatarStats->isEmpty() || $allAvatarStats->sum('matches_played') === 0) {
            return [
                'avg_buzz_ms'   => 3000,
                'accuracy_rate' => 0.5,
                'avatar_slug'   => null,
            ];
        }

        $totalMatches = $allAvatarStats->sum('matches_played');
        $weightedBuzz = $allAvatarStats->sum(fn($s) => $s->avg_buzz_ms * $s->matches_played);
        $weightedAcc  = $allAvatarStats->sum(fn($s) => $s->accuracy_rate * $s->matches_played);

        return [
            'avg_buzz_ms'   => (int) ($weightedBuzz / $totalMatches),
            'accuracy_rate' => round($weightedAcc / $totalMatches, 3),
            'avatar_slug'   => null,
        ];
    }
}
