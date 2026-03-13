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
     * Record points earned during a match into the active season.
     * Called after each league/duo match finish.
     */
    public function recordMatchPoints(User $user, string $mode, int $pointsEarned): void
    {
        if ($pointsEarned <= 0) {
            return;
        }

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
            ]
        );

        $stat->increment('season_points', $pointsEarned);
        $stat->increment('matches_played');
    }

    /**
     * Get the current season stats for a user and mode.
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

        $config = config("seasons.divisions.{$division}");
        $seasonPoints = $stat?->season_points ?? 0;
        $threshold = $config['points_threshold'] ?? 50;
        $reward = $config['coins_reward'] ?? 0;

        return [
            'active_season'   => [
                'id'             => $season->id,
                'name'           => $season->name,
                'ends_at'        => $season->ends_at->toIso8601String(),
                'days_remaining' => $season->daysRemaining(),
            ],
            'season_points'       => $seasonPoints,
            'division'            => $division,
            'threshold'           => $threshold,
            'threshold_reached'   => $seasonPoints >= $threshold,
            'coins_reward'        => $reward,
            'exclusive_frame'     => $config['exclusive_frame'] ?? false,
            'progress_percent'    => min(100, $threshold > 0 ? round(($seasonPoints / $threshold) * 100) : 0),
        ];
    }

    /**
     * Distribute season-end rewards:
     *   Layer 1: coins for everyone above threshold
     *   Layer 2: promote top 10 (+ ties) per division
     *
     * Returns a summary array for logging.
     */
    public function distributeRewards(Season $season): array
    {
        $topCount = config('seasons.top_promotion_count', 10);
        $summary  = ['coins_distributed' => 0, 'promotions' => 0, 'frames_awarded' => 0];

        $modes = $season->mode === 'all'
            ? ['duo', 'league_individual', 'league_team']
            : [$season->mode];

        foreach ($modes as $mode) {
            $divisionOrder = ['bronze', 'argent', 'or', 'platine', 'diamant', 'legende'];

            foreach ($divisionOrder as $divisionKey) {
                $divConfig  = config("seasons.divisions.{$divisionKey}");
                $threshold  = $divConfig['points_threshold'];
                $coinsReward = $divConfig['coins_reward'];
                $hasFrame   = $divConfig['exclusive_frame'];

                $stats = SeasonPlayerStat::where('season_id', $season->id)
                    ->where('mode', $mode)
                    ->where('season_points', '>=', $threshold)
                    ->orderByDesc('season_points')
                    ->get();

                // Layer 1: Give coins to everyone above threshold
                foreach ($stats as $stat) {
                    if ($stat->reward_coins_distributed) {
                        continue;
                    }

                    $user = $stat->user;
                    if (!$user) {
                        continue;
                    }

                    DB::transaction(function () use ($user, $stat, $coinsReward, $hasFrame, &$summary) {
                        $user->coins = ($user->coins ?? 0) + $coinsReward;
                        $user->save();

                        $stat->reward_coins_distributed = true;
                        $stat->coins_awarded = $coinsReward;
                        $stat->division_at_end = PlayerDivision::where('user_id', $user->id)
                            ->where('mode', $stat->mode)
                            ->value('division');

                        if ($hasFrame) {
                            $stat->exclusive_frame_awarded = true;
                            $summary['frames_awarded']++;
                        }

                        $stat->save();
                        $summary['coins_distributed']++;
                    });

                    Log::info("[Season] Coins distributed", [
                        'user_id'  => $user->id,
                        'division' => $divisionKey,
                        'coins'    => $coinsReward,
                        'mode'     => $stat->mode,
                    ]);
                }

                // Layer 2: Promote top 10 + ties
                if ($divisionKey === 'legende') {
                    continue;
                }

                $nextDivision  = $divisionOrder[array_search($divisionKey, $divisionOrder) + 1];
                $eligibleStats = SeasonPlayerStat::where('season_id', $season->id)
                    ->where('mode', $mode)
                    ->where('season_points', '>=', $threshold)
                    ->orderByDesc('season_points')
                    ->get();

                if ($eligibleStats->isEmpty()) {
                    continue;
                }

                // Find the score at position topCount (cutoff)
                $cutoffScore = $eligibleStats->count() >= $topCount
                    ? $eligibleStats->values()[$topCount - 1]->season_points
                    : $eligibleStats->last()->season_points;

                foreach ($eligibleStats as $stat) {
                    if ($stat->season_points < $cutoffScore || $stat->promoted) {
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

                    Log::info("[Season] Player promoted", [
                        'user_id'        => $stat->user_id,
                        'from_division'  => $divisionKey,
                        'to_division'    => $nextDivision,
                        'season_points'  => $stat->season_points,
                        'mode'           => $mode,
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
            'name'       => $name,
            'mode'       => $mode,
            'starts_at'  => now(),
            'ends_at'    => now()->addDays($durationDays),
            'status'     => 'active',
        ]);
    }
}
