<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\QuestService;

class TriggerRetroactiveQuests extends Command
{
    protected $signature = 'quests:retroactive {user_id?}';
    protected $description = 'Déclenche le scan rétroactif des quêtes pour un utilisateur (ou tous si aucun ID)';

    public function handle(QuestService $questService)
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("Utilisateur #{$userId} introuvable !");
                return 1;
            }

            $this->info("🔍 Scan rétroactif pour {$user->name} (ID: {$user->id})...");
            $unlockedQuests = $questService->scanAndUnlockRetroactiveQuests($user);
            
            $totalCoins = 0;
            foreach ($unlockedQuests as $quest) {
                $totalCoins += $quest->reward_coins;
            }
            
            $this->info("✅ Scan terminé !");
            $this->info("📊 Quêtes débloquées : " . count($unlockedQuests));
            $this->info("💰 Pièces distribuées : {$totalCoins}");
            
            if (!empty($unlockedQuests)) {
                $this->info("\n🎯 Quêtes débloquées :");
                foreach ($unlockedQuests as $quest) {
                    $this->line("   • {$quest->badge_emoji} {$quest->name} (+{$quest->reward_coins} pièces)");
                }
            }
        } else {
            $this->info("🔍 Scan rétroactif pour TOUS les utilisateurs...");
            $totalUnlocked = 0;
            $totalCoins = 0;

            // Traiter les utilisateurs par lots de 100 pour éviter les problèmes de mémoire
            User::chunkById(100, function ($users) use ($questService, &$totalUnlocked, &$totalCoins) {
                foreach ($users as $user) {
                    $unlockedQuests = $questService->scanAndUnlockRetroactiveQuests($user);
                    $userCoins = 0;
                    foreach ($unlockedQuests as $quest) {
                        $userCoins += $quest->reward_coins;
                    }
                    
                    $totalUnlocked += count($unlockedQuests);
                    $totalCoins += $userCoins;
                    
                    if (count($unlockedQuests) > 0) {
                        $this->info("   {$user->name}: " . count($unlockedQuests) . " quêtes, +{$userCoins} pièces");
                    }
                }
            });

            $this->info("\n✅ Scan global terminé !");
            $this->info("📊 Total quêtes débloquées : {$totalUnlocked}");
            $this->info("💰 Total pièces distribuées : {$totalCoins}");
        }

        return 0;
    }
}
