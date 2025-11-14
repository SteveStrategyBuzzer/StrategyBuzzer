<?php

namespace App\Services;

use App\Models\PlayerContact;
use App\Models\User;
use App\Models\PlayerDuoStat;
use Illuminate\Support\Collection;

class PlayerContactService
{
    public function addOrUpdateContact(int $userId, int $contactUserId, ?bool $userWon, bool $wasDecisiveRound): void
    {
        try {
            $contact = PlayerContact::firstOrCreate(
                [
                    'user_id' => $userId,
                    'contact_user_id' => $contactUserId,
                ],
                [
                    'matches_played_together' => 0,
                    'matches_won' => 0,
                    'matches_lost' => 0,
                    'decisive_rounds_played' => 0,
                    'decisive_rounds_won' => 0,
                ]
            );

            $contact->matches_played_together++;
            
            if ($userWon === true) {
                $contact->matches_won++;
            } elseif ($userWon === false) {
                $contact->matches_lost++;
            }

            if ($wasDecisiveRound) {
                $contact->decisive_rounds_played++;
                if ($userWon === true) {
                    $contact->decisive_rounds_won++;
                }
            }

            $contact->last_played_at = now();
            $contact->save();
        } catch (\Exception $e) {
            \Log::error('Failed to update player contact', [
                'user_id' => $userId,
                'contact_user_id' => $contactUserId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getContacts(int $userId): Collection
    {
        $contacts = PlayerContact::where('user_id', $userId)
            ->with(['contact'])
            ->orderBy('matches_played_together', 'desc')
            ->get();

        $contactUserIds = $contacts->pluck('contact_user_id')->toArray();
        
        $duoStatsMap = PlayerDuoStat::whereIn('user_id', $contactUserIds)
            ->get()
            ->keyBy('user_id');

        $divisionService = app(DivisionService::class);

        return $contacts->map(function ($contact) use ($duoStatsMap, $divisionService) {
            $contactUser = $contact->contact;
            $duoStats = $duoStatsMap->get($contactUser->id);
            $division = $divisionService->getOrCreateDivision($contactUser, 'duo');

            $totalDuoMatches = $duoStats ? ($duoStats->matches_won + $duoStats->matches_lost) : 0;
            $duoEfficiency = $duoStats && $totalDuoMatches > 0
                ? round(($duoStats->correct_answers / max($duoStats->total_answers, 1)) * 100, 1)
                : 0;

            return [
                'id' => $contactUser->id,
                'name' => $contactUser->name,
                'player_code' => $contactUser->player_code,
                'level' => $duoStats ? $duoStats->level : 0,
                'division' => $division['name'] ?? 'Bronze',
                'division_rank' => $division['rank'] ?? 0,
                'duo_efficiency' => $duoEfficiency,
                'duo_total_matches' => $totalDuoMatches,
                'duo_wins' => $duoStats ? $duoStats->matches_won : 0,
                'duo_losses' => $duoStats ? $duoStats->matches_lost : 0,
                'matches_played_together' => $contact->matches_played_together,
                'matches_won' => $contact->matches_won,
                'matches_lost' => $contact->matches_lost,
                'win_rate' => $contact->win_rate,
                'decisive_rounds_played' => $contact->decisive_rounds_played,
                'decisive_rounds_stats' => $contact->decisive_rounds_stats_formatted,
                'last_played_at' => $contact->last_played_formatted,
            ];
        });
    }
}
