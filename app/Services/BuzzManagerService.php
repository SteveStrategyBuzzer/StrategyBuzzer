<?php

namespace App\Services;

/**
 * Service de gestion des buzz multi-joueurs
 * Garantit l'équité avec timestamp serveur et validation
 */
class BuzzManagerService
{
    /**
     * Enregistre un buzz avec timestamp serveur
     */
    public function recordBuzz(string $playerId, float $clientTime = null): array
    {
        $serverTime = microtime(true);
        
        return [
            'player_id' => $playerId,
            'server_time' => $serverTime,
            'client_time' => $clientTime,
            'timestamp' => now()->toDateTimeString(),
        ];
    }
    
    /**
     * Détermine qui a buzzé en premier (validation serveur)
     */
    public function determineFastest(array $buzzes): ?array
    {
        if (empty($buzzes)) {
            return null;
        }
        
        usort($buzzes, function($a, $b) {
            return $a['server_time'] <=> $b['server_time'];
        });
        
        return $buzzes[0];
    }
    
    /**
     * Calcule les points selon la règle : +2 premier correct, +1 second correct, -2 erreur, 0 pas de buzz
     */
    public function calculatePoints(bool $isFirst, bool $isCorrect): int
    {
        if (!$isCorrect) {
            return -2; // Mauvaise réponse = -2 points
        }
        
        return $isFirst ? 2 : 1; // Premier correct = +2, second correct = +1
    }
    
    /**
     * Valide un buzz (anti-triche : vérifier délai min/max)
     */
    public function validateBuzz(array $buzz, float $questionStartTime): bool
    {
        $buzzTime = $buzz['server_time'];
        $elapsed = $buzzTime - $questionStartTime;
        
        // Le buzz doit être entre 0.1s (réaction humaine min) et 30s (timeout max)
        return $elapsed >= 0.1 && $elapsed <= 30;
    }
    
    /**
     * Crée un résumé de buzz pour affichage
     */
    public function createBuzzSummary(array $buzzes, array $answers): array
    {
        $summary = [];
        
        foreach ($buzzes as $index => $buzz) {
            $playerId = $buzz['player_id'];
            $isFirst = $index === 0;
            $answer = $answers[$playerId] ?? null;
            $isCorrect = $answer['is_correct'] ?? false;
            
            $summary[$playerId] = [
                'buzzed' => true,
                'is_first' => $isFirst,
                'buzz_time' => $buzz['server_time'],
                'answer' => $answer,
                'is_correct' => $isCorrect,
                'points' => $this->calculatePoints($isFirst, $isCorrect),
            ];
        }
        
        return $summary;
    }
    
    /**
     * Gère le cas spécial : réponse sans buzz (0 points, mais comptabilisé)
     */
    public function handleNoBuzzAnswer(string $playerId, bool $isCorrect): array
    {
        return [
            'player_id' => $playerId,
            'buzzed' => false,
            'is_correct' => $isCorrect,
            'points' => 0, // Pas de buzz = 0 point même si correct
            'timestamp' => now()->toDateTimeString(),
        ];
    }
    
    /**
     * Rate limiting : vérifie si un joueur peut buzzer (anti-spam)
     */
    public function canBuzz(string $playerId, array $recentBuzzes, int $maxBuzzPerSecond = 5): bool
    {
        $now = microtime(true);
        $playerBuzzes = array_filter($recentBuzzes, function($buzz) use ($playerId, $now) {
            return $buzz['player_id'] === $playerId && 
                   ($now - $buzz['server_time']) < 1; // Dernière seconde
        });
        
        return count($playerBuzzes) < $maxBuzzPerSecond;
    }
    
    /**
     * Traite une réponse après un buzz et calcule les points
     */
    public function processBuzzAnswer(array &$gameState, string $playerId, string $answer, string $correctAnswer): array
    {
        $serverTime = microtime(true);
        $isCorrect = strcasecmp(trim($answer), trim($correctAnswer)) === 0;
        
        $buzzes = $gameState['buzzes'] ?? [];
        $fastestBuzz = $this->determineFastest($buzzes);
        $isFirst = $fastestBuzz && $fastestBuzz['player_id'] === $playerId;
        
        $points = $isCorrect ? ($isFirst ? 2 : 1) : -2;
        
        return [
            'is_correct' => $isCorrect,
            'is_first' => $isFirst,
            'points' => $points,
            'server_time' => $serverTime,
            'answer' => $answer,
        ];
    }
}
