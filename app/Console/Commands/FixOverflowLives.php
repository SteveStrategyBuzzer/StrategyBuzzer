<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\LifeService;

class FixOverflowLives extends Command
{
    protected $signature = 'stratbuzz:fix-overflow-lives';
    protected $description = 'Fix overflow lives for all users (retroactive bug #2 correction)';

    public function handle()
    {
        $this->info('Starting overflow lives correction for all users...');
        
        $lifeService = new LifeService();
        $maxLives = $lifeService->getMaxLives();
        
        $users = User::all();
        $fixedCount = 0;
        $totalUsers = $users->count();
        
        foreach ($users as $user) {
            $currentLives = $user->lives ?? 0;
            
            if ($currentLives > $maxLives) {
                $user->lives = $maxLives;
                $user->save();
                
                $this->line("Fixed user #{$user->id}: {$currentLives} → {$maxLives} lives");
                $fixedCount++;
            }
        }
        
        $this->newLine();
        $this->info("Correction completed!");
        $this->info("Total users: {$totalUsers}");
        $this->info("Users fixed: {$fixedCount}");
        $this->info("Users unchanged: " . ($totalUsers - $fixedCount));
        
        return Command::SUCCESS;
    }
}
