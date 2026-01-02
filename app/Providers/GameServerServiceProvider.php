<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GameServerService;

class GameServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameServerService::class, function ($app) {
            return new GameServerService();
        });
    }

    public function boot(): void
    {
    }
}
