<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\LifeService;

class RegenLives extends Command
{
    protected $signature = 'lives:regen';
    protected $description = 'Régénère les vies des utilisateurs quand la minuterie est atteinte.';

    public function handle(LifeService $lifeService): int
    {
        $maxLives = (int) config('game.life_max', 5);

        User::query()
            ->whereNotNull('next_life_regen')
            ->where('next_life_regen', '<=', now())
            ->where('lives', '<', $maxLives)
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($lifeService) {
                foreach ($users as $user) {
                    $lifeService->regenerateLives($user);
                }
            });

        $this->info('Régénération des vies: OK');
        return self::SUCCESS;
    }
}
