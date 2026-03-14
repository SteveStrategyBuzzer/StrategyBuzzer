<?php

namespace App\Console\Commands;

use App\Models\UserDailyQuest;
use App\Services\DailyQuestService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RotateDailyQuests extends Command
{
    protected $signature   = 'daily:rotate {--prune : Supprimer les enregistrements anciens (>7 jours)}';
    protected $description = 'Réinitialise le pool de quêtes quotidiennes pour le nouveau jour.';

    public function handle(DailyQuestService $service): int
    {
        $today = Carbon::today()->toDateString();
        $this->info("Rotation quotidienne — {$today}");

        if ($this->option('prune')) {
            $deleted = $service->pruneOldDailyQuests(7);
            $this->info("Anciens enregistrements supprimés : {$deleted}");
            Log::info("daily:rotate — pruned {$deleted} old records");
        }

        $todayCount = UserDailyQuest::where('quest_date', $today)->count();
        $this->info("Enregistrements pour aujourd'hui : {$todayCount}");
        $this->info("Les nouvelles assignations se font à la connexion de chaque joueur.");

        return Command::SUCCESS;
    }
}
