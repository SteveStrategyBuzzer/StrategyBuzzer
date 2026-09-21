<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FirebaseService;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Reviewer;
use App\Services\QuestionBank\ValidationPhase2\QuestionApiValidationPhase2Reviewer;
use App\Services\QuestionApi\QuestionApiClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseService::class, function ($app) {
            return FirebaseService::getInstance();
        });
        $this->app->singleton(ValidationPhase2Reviewer::class, function ($app) {
            return new QuestionApiValidationPhase2Reviewer($app->make(QuestionApiClient::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // GARDE-FOU ABSOLU anti-effacement : bloque toute commande destructrice
        // sur une base non-sqlite (= Postgres/Neon de production). Aucun flag de
        // contournement n'existe : seule une modification volontaire de ce code
        // pourrait le désactiver. L'exception est levée AVANT toute connexion DB.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\CommandStarting::class,
            function ($event) {
                // migrate:rollback exécute les down() (drop table/colonne) → destructeur.
                $destructives = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe'];

                if (! in_array($event->command, $destructives, true)) {
                    return;
                }

                $connection = ($event->input
                    && $event->input->hasOption('database')
                    && $event->input->getOption('database'))
                        ? $event->input->getOption('database')
                        : config('database.default');

                $driver = config("database.connections.{$connection}.driver");

                if ($driver !== 'sqlite') {
                    throw new \RuntimeException(
                        "🚫 BLOQUÉ : la commande destructrice « {$event->command} » est INTERDITE sur "
                        . "la base « {$connection} » (driver {$driver}). Les données joueurs/gameplay "
                        . "sont protégées. Aucun contournement n'est possible."
                    );
                }
            }
        );
    }
}
