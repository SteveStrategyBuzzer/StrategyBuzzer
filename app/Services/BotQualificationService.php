<?php

namespace App\Services;

use App\Models\User;
use App\Models\BotProfile;
use App\Models\BotQualificationEvent;
use Illuminate\Support\Facades\Log;

class BotQualificationService
{
    public function recordSoloLevel(User $user, int $levelNumber): bool
    {
        try {
            BotQualificationEvent::firstOrCreate([
                'user_id'      => $user->id,
                'event_type'   => 'solo_level',
                'reference_id' => $levelNumber,
            ], [
                'counted_at' => now(),
            ]);
            $this->ensureBotProfile($user);
            return true;
        } catch (\Throwable $e) {
            Log::warning('BotQualification: solo level record failed: ' . $e->getMessage());
            return false;
        }
    }

    public function recordMultiplayerMatch(User $user, string $type, int $matchId): bool
    {
        $validTypes = ['duo_match', 'league_individual_match'];
        if (!in_array($type, $validTypes)) {
            Log::warning("BotQualification: invalid event type: {$type}");
            return false;
        }

        try {
            BotQualificationEvent::firstOrCreate([
                'user_id'      => $user->id,
                'event_type'   => $type,
                'reference_id' => $matchId,
            ], [
                'counted_at' => now(),
            ]);
            $this->ensureBotProfile($user);
            return true;
        } catch (\Throwable $e) {
            Log::warning('BotQualification: multiplayer match record failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getQualifyingCount(User $user): int
    {
        return BotQualificationEvent::where('user_id', $user->id)->count();
    }

    public function isQualified(User $user): bool
    {
        return $this->getQualifyingCount($user) >= 10;
    }

    public function getTier(User $user): string
    {
        $count = $this->getQualifyingCount($user);
        if ($count >= 200) return 'gold';
        if ($count >= 50)  return 'silver';
        if ($count >= 10)  return 'bronze';
        return 'none';
    }

    private function ensureBotProfile(User $user): BotProfile
    {
        return BotProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['is_active' => false]
        );
    }
}
