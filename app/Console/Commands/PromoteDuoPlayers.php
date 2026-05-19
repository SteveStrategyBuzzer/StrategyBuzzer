<?php

namespace App\Console\Commands;

use App\Models\PlayerDivision;
use App\Models\MatchPerformance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PromoteDuoPlayers extends Command
{
    protected $signature = 'duo:promote-weekly {--dry-run : Run without making changes}';
    protected $description = 'Promote top 10 Novice players to Intermediaire based on weekly efficiency';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Starting weekly Duo promotion...');
        
        $bronzePlayers = PlayerDivision::where('mode', 'duo')
            ->where('division', 'novice')
            ->with('user')
            ->get();
        
        if ($bronzePlayers->isEmpty()) {
            $this->info('No Novice players found.');
            return 0;
        }
        
        $playersWithEfficiency = [];
        
        foreach ($bronzePlayers as $division) {
            if (!$division->user) continue;
            
            $stats = MatchPerformance::getLast10Stats($division->user_id, 'duo');
            
            $efficiency = $stats['global_efficiency'] ?? 0;
            if ($stats['count'] == 0 && $division->initial_efficiency > 0) {
                $efficiency = $division->initial_efficiency;
            }
            
            $playersWithEfficiency[] = [
                'division' => $division,
                'user' => $division->user,
                'global_efficiency' => $efficiency,
                'matches_played' => $stats['count'],
            ];
        }
        
        usort($playersWithEfficiency, function ($a, $b) {
            return $b['global_efficiency'] <=> $a['global_efficiency'];
        });
        
        $topPlayers = array_slice($playersWithEfficiency, 0, 10);
        
        $this->info('Top 10 Novice players by efficiency:');
        $this->table(
            ['Rank', 'Player', 'Efficiency', 'Matches'],
            array_map(function ($player, $index) {
                return [
                    $index + 1,
                    $player['user']->name ?? $player['user']->username ?? 'Unknown',
                    number_format($player['global_efficiency'], 1) . '%',
                    $player['matches_played'],
                ];
            }, $topPlayers, array_keys($topPlayers))
        );
        
        if ($dryRun) {
            $this->warn('Dry run mode - no changes made.');
            return 0;
        }
        
        $promoted = 0;
        foreach ($topPlayers as $player) {
            $player['division']->division = 'intermediaire';
            $player['division']->save();
            
            Log::info('Player promoted to Intermediaire', [
                'user_id' => $player['user']->id,
                'efficiency' => $player['global_efficiency'],
            ]);
            
            $promoted++;
        }
        
        $this->info("Promoted {$promoted} players from Novice to Intermediaire.");
        
        return 0;
    }
}
