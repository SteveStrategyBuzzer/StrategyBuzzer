<?php

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Console\Command;

class DistributeSeasonRewards extends Command
{
    protected $signature   = 'season:distribute-rewards {season_id? : ID of the season (defaults to latest active)}';
    protected $description = 'Distribute end-of-season rewards: coins for threshold achievers and promotions for top 10';

    public function __construct(private SeasonService $seasonService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $seasonId = $this->argument('season_id');

        $season = $seasonId
            ? Season::find($seasonId)
            : Season::where('status', 'active')->latest()->first();

        if (!$season) {
            $this->error('No active season found.');
            return self::FAILURE;
        }

        if ($season->rewards_distributed_at) {
            $this->warn("Rewards already distributed at {$season->rewards_distributed_at}.");
            if (!$this->confirm('Distribute again?')) {
                return self::SUCCESS;
            }
        }

        $this->info("Distributing rewards for season: [{$season->id}] {$season->name}");

        $summary = $this->seasonService->distributeRewards($season);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Coins distributed (players)', $summary['coins_distributed']],
                ['Promotions',                  $summary['promotions']],
                ['Exclusive frames awarded',    $summary['frames_awarded']],
            ]
        );

        $this->info('Done. Season marked as ended.');
        return self::SUCCESS;
    }
}
