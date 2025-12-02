<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\PlayerCodeService;

class GeneratePlayerCodes extends Command
{
    protected $signature = 'players:generate-codes';
    
    protected $description = 'Generate player codes (SB-XXXX) for users who do not have one';
    
    public function handle()
    {
        $usersWithoutCode = User::whereNull('player_code')
            ->orWhere('player_code', '')
            ->get();
        
        $count = $usersWithoutCode->count();
        
        if ($count === 0) {
            $this->info('All users already have a player code.');
            return 0;
        }
        
        $this->info("Found {$count} users without player code. Generating...");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        $generated = 0;
        $failed = 0;
        
        foreach ($usersWithoutCode as $user) {
            try {
                $code = PlayerCodeService::generateUniqueCode();
                $user->player_code = $code;
                $user->save();
                $generated++;
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to generate code for user {$user->id}: {$e->getMessage()}");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("Generated {$generated} player codes.");
        
        if ($failed > 0) {
            $this->warn("{$failed} codes failed to generate.");
        }
        
        return 0;
    }
}
