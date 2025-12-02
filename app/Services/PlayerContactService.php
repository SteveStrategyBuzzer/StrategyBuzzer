<?php

namespace App\Services;

use App\Models\PlayerContact;
use App\Models\User;
use App\Models\PlayerDuoStat;
use Illuminate\Support\Collection;

class PlayerContactService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

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

    public function ensureContactExists(int $userId, int $contactUserId): bool
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
                    'last_played_at' => now(),
                ]
            );
            
            return $contact->wasRecentlyCreated;
        } catch (\Exception $e) {
            \Log::error('Failed to create player contact', [
                'user_id' => $userId,
                'contact_user_id' => $contactUserId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function registerMutualContacts(int $player1Id, int $player2Id): void
    {
        $created1 = $this->ensureContactExists($player1Id, $player2Id);
        $created2 = $this->ensureContactExists($player2Id, $player1Id);
        
        // Always notify Firestore to trigger real-time updates for both players
        // Even if contacts already exist, the version change triggers the listener
        $this->notifyContactsUpdate($player1Id, $player2Id);
    }
    
    private function notifyContactsUpdate(int $player1Id, int $player2Id): void
    {
        try {
            $player1 = User::find($player1Id);
            $player2 = User::find($player2Id);
            
            if (!$player1 || !$player2) {
                return;
            }
            
            $timestamp = microtime(true);
            
            $this->firebase->createDocument(
                'duoContacts',
                "user-{$player1Id}",
                [
                    'userId' => $player1Id,
                    'lastContactAdded' => $player2Id,
                    'lastContactName' => $player2->name,
                    'lastContactCode' => $player2->player_code ?? '',
                    'updatedAt' => $timestamp,
                    'version' => uniqid(),
                ]
            );
            
            $this->firebase->createDocument(
                'duoContacts',
                "user-{$player2Id}",
                [
                    'userId' => $player2Id,
                    'lastContactAdded' => $player1Id,
                    'lastContactName' => $player1->name,
                    'lastContactCode' => $player1->player_code ?? '',
                    'updatedAt' => $timestamp,
                    'version' => uniqid(),
                ]
            );
            
            \Log::info('Firestore contact notification sent', [
                'player1_id' => $player1Id,
                'player2_id' => $player2Id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to notify Firestore about contacts update', [
                'player1_id' => $player1Id,
                'player2_id' => $player2Id,
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
