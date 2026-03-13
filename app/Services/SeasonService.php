<?php

namespace App\Services;

use App\Models\Season;
use App\Models\SeasonPlayerStat;
use App\Models\PlayerDivision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeasonService
{
    public function activeSeason(string $mode = 'all'): ?Season
    {
        return Season::activeSeason($mode);
    }

    /**
     * Record the result of a match into the active season.
     * Only wins are counted toward the season threshold.
     * Losses increment matches_played but not matches_won.
     */
    public function recordMatchResult(User $user, string $mode, bool $won): void
    {
        $season = $this->activeSeason($mode) ?? $this->activeSeason('all');
        if (!$season) {
            return;
        }

        $division = PlayerDivision::where('user_id', $user->id)
            ->where('mode', $mode)
            ->value('division') ?? 'bronze';

        $stat = SeasonPlayerStat::firstOrCreate(
            [
                'season_id' => $season->id,
                'user_id'   => $user->id,
                'mode'      => $mode,
            ],
            [
                'division_at_start' => $division,
                'season_points'     => 0,
                'matches_played'    => 0,
                'matches_won'       => 0,
            ]
        );

        $stat->increment('matches_played');

        if ($won) {
            $stat->increment('matches_won');
        }
    }

    /**
     * Get the current season info for a user and mode.
     * Returns wins count, threshold, and prize tiers for their division.
     */
    public function getPlayerSeasonInfo(User $user, string $mode): array
    {
        $season = $this->activeSeason($mode) ?? $this->activeSeason('all');
        if (!$season) {
            return ['active_season' => null];
        }

        $stat = SeasonPlayerStat::where('season_id', $season->id)
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->first();

        $division = PlayerDivision::where('user_id', $user->id)
            ->where('mode', $mode)
            ->value('division') ?? 'bronze';

        $divConfig      = config("seasons.divisions.{$division}");
        $winsThreshold  = $divConfig['wins_threshold'] ?? 10;
        $prizes         = $divConfig['prizes'] ?? [];
        $matchesWon     = $stat?->matches_won ?? 0;
        $eligible       = $matchesWon >= $winsThreshold;

        return [
            'active_season' => [
                'id'             => $season->id,
                'name'           => $season->name,
                'ends_at'        => $season->ends_at->toIso8601String(),
                'days_remaining' => $season->daysRemaining(),
            ],
            'matches_won'      => $matchesWon,
            'division'         => $division,
            'wins_threshold'   => $winsThreshold,
            'eligible'         => $eligible,
            'prizes'           => $prizes,
            'progress_percent' => min(100, $winsThreshold > 0 ? round(($matchesWon / $winsThreshold) * 100) : 0),
        ];
    }

    /**
     * Distribute season-end rewards based on wins ranking within each division.
     *
     * Eligibility: matches_won >= wins_threshold
     * Ranking: sorted by matches_won DESC, ties share the same rank
     * Prizes: 1st/2nd/3rd place (or fewer tiers in lower divisions) per division config
     * Also promotes top 10 + ties to the next division.
     *
     * Returns summary array for logging.
     */
    public function distributeRewards(Season $season): array
    {
        $summary = ['coins_distributed' => 0, 'promotions' => 0, 'frames_awarded' => 0];

        $modes = $season->mode === 'all'
            ? ['duo', 'league_individual', 'league_team']
            : [$season->mode];

        $divisionOrder = ['bronze', 'argent', 'or', 'platine', 'diamant', 'legende'];

        foreach ($modes as $mode) {
            foreach ($divisionOrder as $divisionKey) {
                $divConfig      = config("seasons.divisions.{$divisionKey}");
                $winsThreshold  = $divConfig['wins_threshold'];
                $prizes         = $divConfig['prizes'] ?? [];

                // Fetch all eligible players sorted by wins descending
                $eligibleStats = SeasonPlayerStat::where('season_id', $season->id)
                    ->where('mode', $mode)
                    ->where('division_at_start', $divisionKey)
                    ->where('matches_won', '>=', $winsThreshold)
                    ->orderByDesc('matches_won')
                    ->get();

                if ($eligibleStats->isEmpty()) {
                    continue;
                }

                // Build dense rank: players with the same wins count share the same rank
                $currentRank     = 1;
                $previousWins    = null;
                $rankCounter     = 0;

                foreach ($eligibleStats as $stat) {
                    if ($stat->reward_coins_distributed) {
                        continue;
                    }

                    $wins = $stat->matches_won;

                    if ($previousWins !== null && $wins < $previousWins) {
                        $currentRank = $rankCounter + 1;
                    }

                    $rankCounter++;
                    $previousWins = $wins;

                    // Find prize for this rank (null = no prize beyond the tiers defined)
                    $prize = collect($prizes)->firstWhere('rank', $currentRank);
                    if (!$prize) {
                        // Still mark as processed so we don't retry
                        $stat->reward_coins_distributed = true;
                        $stat->coins_awarded = 0;
                        $stat->prize_rank = null;
                        $stat->division_at_end = PlayerDivision::where('user_id', $stat->user_id)
                            ->where('mode', $mode)
                            ->value('division');
                        $stat->save();
                        continue;
                    }

                    $coinsReward = $prize['coins'];
                    $hasFrame    = $prize['exclusive_frame'] ?? false;
                    $user        = $stat->user;

                    if (!$user) {
                        continue;
                    }

                    DB::transaction(function () use ($user, $stat, $coinsReward, $hasFrame, $currentRank, $mode, &$summary) {
                        $user->coins = ($user->coins ?? 0) + $coinsReward;
                        $user->save();

                        $stat->reward_coins_distributed = true;
                        $stat->coins_awarded = $coinsReward;
                        $stat->prize_rank    = $currentRank;
                        $stat->division_at_end = PlayerDivision::where('user_id', $user->id)
                            ->where('mode', $mode)
                            ->value('division');

                        if ($hasFrame) {
                            $stat->exclusive_frame_awarded = true;
                            $summary['frames_awarded']++;
                        }

                        $stat->save();
                        $summary['coins_distributed']++;
                    });

                    Log::info('[Season] Prize distributed', [
                        'user_id'   => $user->id,
                        'division'  => $divisionKey,
                        'rank'      => $currentRank,
                        'coins'     => $coinsReward,
                        'wins'      => $wins,
                        'mode'      => $mode,
                    ]);
                }

                // Promote top 10 (+ ties) to the next division
                if ($divisionKey === 'legende') {
                    continue;
                }

                $nextDivision  = $divisionOrder[array_search($divisionKey, $divisionOrder) + 1];
                $topTen        = $eligibleStats->take(10);
                $cutoffWins    = $topTen->count() >= 10 ? $topTen->last()->matches_won : ($eligibleStats->last()->matches_won);

                foreach ($eligibleStats as $stat) {
                    if ($stat->matches_won < $cutoffWins || $stat->promoted) {
                        continue;
                    }

                    $playerDiv = PlayerDivision::where('user_id', $stat->user_id)
                        ->where('mode', $mode)
                        ->first();

                    if (!$playerDiv || $playerDiv->division !== $divisionKey) {
                        continue;
                    }

                    DB::transaction(function () use ($playerDiv, $nextDivision, $stat, &$summary) {
                        $playerDiv->division = $nextDivision;
                        $playerDiv->save();

                        $stat->promoted = true;
                        $stat->save();
                        $summary['promotions']++;
                    });

                    Log::info('[Season] Player promoted', [
                        'user_id'       => $stat->user_id,
                        'from_division' => $divisionKey,
                        'to_division'   => $nextDivision,
                        'wins'          => $stat->matches_won,
                        'mode'          => $mode,
                    ]);
                }
            }
        }

        $season->status = 'ended';
        $season->rewards_distributed_at = now();
        $season->save();

        return $summary;
    }

    /**
     * Create and activate a new season.
     */
    public function startNewSeason(string $name, string $mode = 'all', int $durationDays = null): Season
    {
        $durationDays = $durationDays ?? config('seasons.season_duration_days', 90);

        Season::where('mode', $mode)->where('status', 'active')->update(['status' => 'ended']);

        return Season::create([
            'name'      => $name,
            'mode'      => $mode,
            'starts_at' => now(),
            'ends_at'   => now()->addDays($durationDays),
            'status'    => 'active',
        ]);
    }
}
