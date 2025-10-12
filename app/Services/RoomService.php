<?php

namespace App\Services;

/**
 * Service de gestion des sessions/rooms de jeu
 * Abstraction pour gérer tous les modes : Solo, Duo, Ligue, Maître du jeu
 */
class RoomService
{
    /**
     * Crée une nouvelle room/session de jeu
     */
    public function createRoom(array $config): array
    {
        $roomId = $this->generateRoomId();
        
        $room = [
            'id' => $roomId,
            'mode' => $config['mode'] ?? 'solo',
            'host_id' => $config['host_id'] ?? null,
            'theme' => $config['theme'],
            'nb_questions' => $config['nb_questions'],
            'niveau' => $config['niveau'] ?? 1,
            'max_players' => $this->getMaxPlayers($config['mode']),
            'players' => [],
            'status' => 'waiting', // waiting, playing, finished
            'created_at' => now()->toDateTimeString(),
            'started_at' => null,
            'finished_at' => null,
            'game_state' => null,
        ];
        
        // Pour le mode solo, ajouter directement le joueur
        if ($config['mode'] === 'solo') {
            $room['players'][] = [
                'id' => $config['host_id'] ?? 'player',
                'name' => $config['player_name'] ?? 'Joueur',
                'is_host' => true,
                'joined_at' => now()->toDateTimeString(),
            ];
        }
        
        return $room;
    }
    
    /**
     * Ajoute un joueur à la room
     */
    public function addPlayer(array &$room, array $player): bool
    {
        if (count($room['players']) >= $room['max_players']) {
            return false; // Room pleine
        }
        
        // Déterminer si ce joueur sera l'hôte AVANT de l'ajouter
        $isHost = count($room['players']) === 0;
        
        $room['players'][] = [
            'id' => $player['id'],
            'name' => $player['name'] ?? 'Joueur',
            'avatar' => $player['avatar'] ?? null,
            'is_host' => $isHost,
            'joined_at' => now()->toDateTimeString(),
        ];
        
        return true;
    }
    
    /**
     * Retire un joueur de la room
     */
    public function removePlayer(array &$room, string $playerId): void
    {
        $room['players'] = array_filter($room['players'], function($p) use ($playerId) {
            return $p['id'] !== $playerId;
        });
        
        // Réindexer le tableau pour éviter les index épars
        $room['players'] = array_values($room['players']);
        
        // Réassigner l'hôte si nécessaire
        if (!empty($room['players'])) {
            $hasHost = false;
            foreach ($room['players'] as $player) {
                if ($player['is_host']) {
                    $hasHost = true;
                    break;
                }
            }
            
            if (!$hasHost) {
                $room['players'][0]['is_host'] = true;
            }
        }
    }
    
    /**
     * Démarre la partie (change le statut à "playing")
     */
    public function startGame(array &$room, array $gameState): void
    {
        $room['status'] = 'playing';
        $room['started_at'] = now()->toDateTimeString();
        $room['game_state'] = $gameState;
    }
    
    /**
     * Termine la partie
     */
    public function finishGame(array &$room, array $finalResult): void
    {
        $room['status'] = 'finished';
        $room['finished_at'] = now()->toDateTimeString();
        $room['final_result'] = $finalResult;
    }
    
    /**
     * Vérifie si la room est prête à démarrer
     */
    public function isReady(array $room): bool
    {
        $mode = $room['mode'];
        $playerCount = count($room['players']);
        
        switch ($mode) {
            case 'solo':
                return $playerCount >= 1;
            case 'duo':
                return $playerCount >= 2;
            case 'ligue':
                return $playerCount >= 2; // Min 2 joueurs pour une ligue
            case 'master':
                return $playerCount >= 2; // Maître du jeu + au moins 1 joueur
            default:
                return false;
        }
    }
    
    /**
     * Obtient le nombre max de joueurs selon le mode
     */
    private function getMaxPlayers(string $mode): int
    {
        return match($mode) {
            'solo' => 1,
            'duo' => 2,
            'ligue' => 10, // Exemple : max 10 joueurs en ligue
            'master' => 40, // Maître du jeu peut gérer jusqu'à 40 joueurs
            default => 1,
        };
    }
    
    /**
     * Génère un ID unique pour la room
     */
    private function generateRoomId(): string
    {
        return 'room_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
    
    /**
     * Sauvegarde la room dans la session (pour Laravel)
     */
    public function saveToSession(array $room): void
    {
        session(['game_room' => $room]);
    }
    
    /**
     * Charge la room depuis la session
     */
    public function loadFromSession(): ?array
    {
        return session('game_room');
    }
    
    /**
     * Supprime la room de la session
     */
    public function clearSession(): void
    {
        session()->forget('game_room');
    }
    
    /**
     * Obtient un joueur spécifique de la room
     */
    public function getPlayer(array $room, string $playerId): ?array
    {
        foreach ($room['players'] as $player) {
            if ($player['id'] === $playerId) {
                return $player;
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si un joueur est l'hôte
     */
    public function isHost(array $room, string $playerId): bool
    {
        $player = $this->getPlayer($room, $playerId);
        return $player && ($player['is_host'] ?? false);
    }
}
