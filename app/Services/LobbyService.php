<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LobbyService
{
    protected const LOBBY_PREFIX = 'lobby:';
    protected const LOBBY_TTL = 3600;
    
    protected array $teamColors = [
        ['id' => 'red', 'name' => 'Rouge', 'hex' => '#E53935', 'light' => '#FFCDD2'],
        ['id' => 'blue', 'name' => 'Bleu', 'hex' => '#1E88E5', 'light' => '#BBDEFB'],
        ['id' => 'green', 'name' => 'Vert', 'hex' => '#43A047', 'light' => '#C8E6C9'],
        ['id' => 'orange', 'name' => 'Orange', 'hex' => '#FB8C00', 'light' => '#FFE0B2'],
        ['id' => 'purple', 'name' => 'Violet', 'hex' => '#8E24AA', 'light' => '#E1BEE7'],
        ['id' => 'cyan', 'name' => 'Cyan', 'hex' => '#00ACC1', 'light' => '#B2EBF2'],
        ['id' => 'pink', 'name' => 'Rose', 'hex' => '#D81B60', 'light' => '#F8BBD9'],
        ['id' => 'yellow', 'name' => 'Jaune', 'hex' => '#FDD835', 'light' => '#FFF9C4'],
        ['id' => 'teal', 'name' => 'Turquoise', 'hex' => '#00897B', 'light' => '#B2DFDB'],
        ['id' => 'indigo', 'name' => 'Indigo', 'hex' => '#3949AB', 'light' => '#C5CAE9'],
        ['id' => 'lime', 'name' => 'Lime', 'hex' => '#C0CA33', 'light' => '#F0F4C3'],
        ['id' => 'brown', 'name' => 'Marron', 'hex' => '#6D4C41', 'light' => '#D7CCC8'],
    ];
    
    public function getTeamColors(): array
    {
        return $this->teamColors;
    }
    
    public function createLobby(User $host, string $mode, array $settings = []): array
    {
        $lobbyCode = $this->generateLobbyCode();
        
        $lobby = [
            'code' => $lobbyCode,
            'host_id' => $host->id,
            'host_name' => $host->name,
            'mode' => $mode,
            'settings' => array_merge([
                'max_players' => $this->getMaxPlayers($mode),
                'min_players' => $this->getMinPlayers($mode),
                'teams_enabled' => in_array($mode, ['league_team', 'master']),
                'theme' => __('Culture générale'),
                'nb_questions' => 10,
            ], $settings),
            'players' => [
                $host->id => [
                    'id' => $host->id,
                    'name' => $host->name,
                    'player_code' => $host->player_code,
                    'avatar' => session('selected_avatar', 'default'),
                    'color' => 'blue',
                    'team' => null,
                    'ready' => false,
                    'is_host' => true,
                    'joined_at' => now()->toISOString(),
                ],
            ],
            'teams' => [],
            'created_at' => now()->toISOString(),
            'status' => 'waiting',
        ];
        
        $this->saveLobby($lobbyCode, $lobby);
        
        return $lobby;
    }
    
    public function joinLobby(string $code, User $player): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if ($lobby['status'] !== 'waiting') {
            return ['success' => false, 'error' => __('La partie a déjà commencé')];
        }
        
        $maxPlayers = $lobby['settings']['max_players'] ?? 40;
        if (count($lobby['players']) >= $maxPlayers) {
            return ['success' => false, 'error' => __('Salon complet')];
        }
        
        if (isset($lobby['players'][$player->id])) {
            return ['success' => true, 'lobby' => $lobby, 'already_joined' => true];
        }
        
        $availableColors = $this->getAvailableColors($lobby);
        $assignedColor = !empty($availableColors) ? $availableColors[0]['id'] : 'blue';
        
        $lobby['players'][$player->id] = [
            'id' => $player->id,
            'name' => $player->name,
            'player_code' => $player->player_code,
            'avatar' => session('selected_avatar', 'default'),
            'color' => $assignedColor,
            'team' => null,
            'ready' => false,
            'is_host' => false,
            'joined_at' => now()->toISOString(),
        ];
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
    }
    
    public function leaveLobby(string $code, User $player): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if (!isset($lobby['players'][$player->id])) {
            return ['success' => true, 'already_left' => true];
        }
        
        $wasHost = $lobby['players'][$player->id]['is_host'] ?? false;
        
        unset($lobby['players'][$player->id]);
        
        if (empty($lobby['players'])) {
            $this->deleteLobby($code);
            return ['success' => true, 'lobby_closed' => true];
        }
        
        if ($wasHost) {
            $newHostId = array_key_first($lobby['players']);
            $lobby['players'][$newHostId]['is_host'] = true;
            $lobby['host_id'] = $newHostId;
            $lobby['host_name'] = $lobby['players'][$newHostId]['name'];
        }
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby, 'new_host' => $wasHost ? $lobby['host_id'] : null];
    }
    
    public function setPlayerReady(string $code, User $player, bool $ready): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby || !isset($lobby['players'][$player->id])) {
            return ['success' => false, 'error' => __('Joueur non trouvé dans le salon')];
        }
        
        $lobby['players'][$player->id]['ready'] = $ready;
        
        $this->saveLobby($code, $lobby);
        
        return [
            'success' => true,
            'lobby' => $lobby,
            'all_ready' => $this->areAllPlayersReady($lobby),
        ];
    }
    
    public function setPlayerColor(string $code, User $player, string $colorId): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby || !isset($lobby['players'][$player->id])) {
            return ['success' => false, 'error' => __('Joueur non trouvé dans le salon')];
        }
        
        $validColor = collect($this->teamColors)->firstWhere('id', $colorId);
        if (!$validColor) {
            return ['success' => false, 'error' => __('Couleur invalide')];
        }
        
        $lobby['players'][$player->id]['color'] = $colorId;
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
    }
    
    public function setPlayerTeam(string $code, User $player, ?string $teamId): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby || !isset($lobby['players'][$player->id])) {
            return ['success' => false, 'error' => __('Joueur non trouvé dans le salon')];
        }
        
        if (!$lobby['settings']['teams_enabled']) {
            return ['success' => false, 'error' => __('Les équipes ne sont pas activées')];
        }
        
        $lobby['players'][$player->id]['team'] = $teamId;
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
    }
    
    public function createTeam(string $code, User $host, string $teamName, string $colorId): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if ($lobby['host_id'] !== $host->id) {
            return ['success' => false, 'error' => __('Seul l\'hôte peut créer des équipes')];
        }
        
        $teamId = Str::uuid()->toString();
        
        $lobby['teams'][$teamId] = [
            'id' => $teamId,
            'name' => $teamName,
            'color' => $colorId,
            'created_at' => now()->toISOString(),
        ];
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby, 'team_id' => $teamId];
    }
    
    public function updateLobbySettings(string $code, User $host, array $settings): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if ($lobby['host_id'] !== $host->id) {
            return ['success' => false, 'error' => __('Seul l\'hôte peut modifier les paramètres')];
        }
        
        $lobby['settings'] = array_merge($lobby['settings'], $settings);
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
    }
    
    public function startGame(string $code, User $host): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if ($lobby['host_id'] !== $host->id) {
            return ['success' => false, 'error' => __('Seul l\'hôte peut lancer la partie')];
        }
        
        $minPlayers = $lobby['settings']['min_players'] ?? 2;
        if (count($lobby['players']) < $minPlayers) {
            return ['success' => false, 'error' => __('Pas assez de joueurs (minimum :min)', ['min' => $minPlayers])];
        }
        
        if (!$this->areAllPlayersReady($lobby)) {
            return ['success' => false, 'error' => __('Tous les joueurs ne sont pas prêts')];
        }
        
        $lobby['status'] = 'starting';
        $lobby['started_at'] = now()->toISOString();
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
    }
    
    public function getLobby(string $code): ?array
    {
        return Cache::get(self::LOBBY_PREFIX . strtoupper($code));
    }
    
    public function getPlayerLobbyState(string $code, int $playerId): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['exists' => false];
        }
        
        $isInLobby = isset($lobby['players'][$playerId]);
        $isHost = $lobby['host_id'] === $playerId;
        
        return [
            'exists' => true,
            'in_lobby' => $isInLobby,
            'is_host' => $isHost,
            'lobby' => $lobby,
            'colors' => $this->teamColors,
            'available_colors' => $this->getAvailableColors($lobby),
            'all_ready' => $this->areAllPlayersReady($lobby),
            'can_start' => $isHost && $this->canStartGame($lobby),
        ];
    }
    
    protected function saveLobby(string $code, array $lobby): void
    {
        Cache::put(self::LOBBY_PREFIX . strtoupper($code), $lobby, self::LOBBY_TTL);
    }
    
    protected function deleteLobby(string $code): void
    {
        Cache::forget(self::LOBBY_PREFIX . strtoupper($code));
    }
    
    protected function generateLobbyCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        if ($this->getLobby($code)) {
            return $this->generateLobbyCode();
        }
        
        return $code;
    }
    
    protected function getMaxPlayers(string $mode): int
    {
        return match($mode) {
            'duo' => 2,
            'league_individual' => 2,
            'league_team' => 10,
            'master' => 40,
            default => 10,
        };
    }
    
    protected function getMinPlayers(string $mode): int
    {
        return match($mode) {
            'duo' => 2,
            'league_individual' => 2,
            'league_team' => 4,
            'master' => 3,
            default => 2,
        };
    }
    
    protected function getAvailableColors(array $lobby): array
    {
        $usedColors = collect($lobby['players'])->pluck('color')->toArray();
        
        return collect($this->teamColors)
            ->filter(fn($color) => !in_array($color['id'], $usedColors))
            ->values()
            ->toArray();
    }
    
    protected function areAllPlayersReady(array $lobby): bool
    {
        foreach ($lobby['players'] as $player) {
            if ($player['is_host']) {
                continue;
            }
            if (!$player['ready']) {
                return false;
            }
        }
        
        return true;
    }
    
    protected function canStartGame(array $lobby): bool
    {
        $minPlayers = $lobby['settings']['min_players'] ?? 2;
        
        if (count($lobby['players']) < $minPlayers) {
            return false;
        }
        
        return $this->areAllPlayersReady($lobby);
    }
}
