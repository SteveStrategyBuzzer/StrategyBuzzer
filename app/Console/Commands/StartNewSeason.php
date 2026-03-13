<?php

namespace App\Console\Commands;

use App\Services\SeasonService;
use Illuminate\Console\Command;

class StartNewSeason extends Command
{
    protected $signature   = 'season:start {name : Name of the season (ex: "Saison 1 - Printemps 2026")} {--mode=all : Game mode (all, duo, league_individual, league_team)} {--days= : Duration in days (default from config)}';
    protected $description = 'Start a new season and activate it immediately';

    public function __construct(private SeasonService $seasonService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $mode = $this->option('mode');
        $days = $this->option('days') ? (int) $this->option('days') : null;

        $season = $this->seasonService->startNewSeason($name, $mode, $days);

        $this->info("Season started successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID',      $season->id],
                ['Name',    $season->name],
                ['Mode',    $season->mode],
                ['Starts',  $season->starts_at->toDateTimeString()],
                ['Ends',    $season->ends_at->toDateTimeString()],
                ['Status',  $season->status],
            ]
        );

        return self::SUCCESS;
    }
}
