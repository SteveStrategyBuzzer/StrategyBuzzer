<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Régénération des vies chaque minute
        $schedule->command('lives:regen')->everyMinute();

        // Rotation des quêtes quotidiennes à minuit (nettoyage des anciens enregistrements)
        $schedule->command('daily:rotate --prune')->dailyAt('00:00');

        // Récupération des matchs Duo bloqués en état "playing" depuis +30 min
        $schedule->command('matches:recover')->everyFiveMinutes();

        // Traitement de l'Outbox Kernel (CURRENT_KERNEL_RECEIVED → Blueprint suivant)
        // withoutOverlapping() : interdit deux exécutions simultanées
        $schedule->command('questions:kernel:process-outbox')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
