<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    
    protected function normalizeAvatar(?string $avatar): string
    {
        if (!$avatar) {
            return 'default';
        }
        
        if (str_contains($avatar, '/') || str_contains($avatar, '.png')) {
            $avatar = preg_replace('/\.png$/', '', $avatar);
            $avatar = basename($avatar);
        }
        
        return $avatar ?: 'default';
    }
    
    protected function getPlayerDisplayName(User $user): string
    {
        $settings = (array) ($user->profile_settings ?? []);
        $pseudonym = trim((string) data_get($settings, 'pseudonym', ''));
        
        if ($pseudonym !== '') {
            return $pseudonym;
        }
        
        return $user->name ?? 'Joueur';
    }
    
    protected function getUserAvatar(User $user): string
    {
        $settings = is_string($user->profile_settings) 
            ? json_decode($user->profile_settings, true) 
            : (array) $user->profile_settings;
        
        $avatarUrl = $settings['avatar']['url'] ?? null;
        
        if ($avatarUrl && is_string($avatarUrl) && strlen($avatarUrl) > 0) {
            $avatarUrl = ltrim($avatarUrl, '/');
            if (!str_starts_with($avatarUrl, 'images/')) {
                $avatarUrl = 'images/avatars/standard/' . $avatarUrl;
            }
            if (!str_ends_with($avatarUrl, '.png')) {
                $avatarUrl .= '.png';
            }
            return $avatarUrl;
        }
        
        $avatarId = $settings['avatar']['id'] ?? $settings['avatar'] ?? null;
        if ($avatarId && is_string($avatarId)) {
            $avatarId = preg_replace('/\.png$/', '', $avatarId);
            return 'images/avatars/standard/' . $avatarId . '.png';
        }
        
        return 'images/avatars/standard/default.png';
    }
    
    public function createLobby(User $host, string $mode, array $settings = []): array
    {
        $lobbyCode = $this->generateLobbyCode();
        
        $hostDisplayName = $this->getPlayerDisplayName($host);
        
        $lobby = [
            'code' => $lobbyCode,
            'host_id' => $host->id,
            'host_name' => $hostDisplayName,
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
                    'name' => $hostDisplayName,
                    'player_code' => $host->player_code,
                    'avatar' => $this->getUserAvatar($host),
                    'color' => 'blue',
                    'team' => null,
                    'ready' => false,
                    'is_host' => true,
                    'joined_at' => now()->toISOString(),
                    'competence_coins' => $host->competence_coins ?? 0,
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
        
        $playerDisplayName = $this->getPlayerDisplayName($player);
        
        $lobby['players'][$player->id] = [
            'id' => $player->id,
            'name' => $playerDisplayName,
            'player_code' => $player->player_code,
            'avatar' => $this->getUserAvatar($player),
            'color' => $assignedColor,
            'team' => null,
            'ready' => false,
            'is_host' => false,
            'joined_at' => now()->toISOString(),
            'competence_coins' => $player->competence_coins ?? 0,
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
    
    public function proposeBet(string $code, User $player, int $amount): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if (!isset($lobby['players'][$player->id])) {
            return ['success' => false, 'error' => __('Joueur non trouvé dans le salon')];
        }
        
        if ($player->competence_coins < $amount) {
            return ['success' => false, 'error' => __('Vous n\'avez pas assez de pièces pour cette mise')];
        }
        
        $lobby['bet_negotiation'] = [
            'status' => 'proposed',
            'proposer_id' => $player->id,
            'proposer_name' => $lobby['players'][$player->id]['name'],
            'proposed_amount' => $amount,
            'responses' => [],
            'proposed_at' => now()->toISOString(),
        ];
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
    }
    
    public function respondToBet(string $code, User $player, string $action, ?int $counterAmount = null): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if (!isset($lobby['players'][$player->id])) {
            return ['success' => false, 'error' => __('Joueur non trouvé dans le salon')];
        }
        
        if (!isset($lobby['bet_negotiation']) || $lobby['bet_negotiation']['status'] !== 'proposed') {
            return ['success' => false, 'error' => __('Aucune proposition de pari en cours')];
        }
        
        $negotiation = $lobby['bet_negotiation'];
        
        if ($negotiation['proposer_id'] === $player->id && $action !== 'accept') {
            return ['success' => false, 'error' => __('Vous ne pouvez pas répondre à votre propre proposition')];
        }
        
        switch ($action) {
            case 'accept':
                $betAmount = $negotiation['proposed_amount'];
                if ($player->competence_coins < $betAmount) {
                    return ['success' => false, 'error' => __('Vous n\'avez pas assez de pièces pour accepter cette mise')];
                }
                
                $lobby['bet_negotiation']['status'] = 'accepted';
                $lobby['bet_negotiation']['responses'][$player->id] = [
                    'action' => 'accept',
                    'amount' => $betAmount,
                    'responded_at' => now()->toISOString(),
                ];
                
                $lobby['settings']['bet_amount'] = $betAmount;
                $lobby['settings']['bet_accepted'] = true;
                break;
                
            case 'raise':
                if ($counterAmount === null || $counterAmount <= $negotiation['proposed_amount']) {
                    return ['success' => false, 'error' => __('La relance doit être supérieure à la mise actuelle')];
                }
                
                if ($player->competence_coins < $counterAmount) {
                    return ['success' => false, 'error' => __('Vous n\'avez pas assez de pièces pour cette relance')];
                }
                
                $lobby['bet_negotiation'] = [
                    'status' => 'proposed',
                    'proposer_id' => $player->id,
                    'proposer_name' => $lobby['players'][$player->id]['name'],
                    'proposed_amount' => $counterAmount,
                    'previous_amount' => $negotiation['proposed_amount'],
                    'responses' => [],
                    'proposed_at' => now()->toISOString(),
                ];
                break;
                
            case 'refuse':
                $lobby['bet_negotiation']['status'] = 'refused';
                $lobby['bet_negotiation']['responses'][$player->id] = [
                    'action' => 'refuse',
                    'responded_at' => now()->toISOString(),
                ];
                
                $lobby['settings']['bet_amount'] = 0;
                $lobby['settings']['bet_accepted'] = false;
                break;
                
            default:
                return ['success' => false, 'error' => __('Action invalide')];
        }
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby, 'action' => $action];
    }
    
    public function cancelBet(string $code, User $player): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if (!isset($lobby['bet_negotiation'])) {
            return ['success' => true, 'lobby' => $lobby];
        }
        
        if ($lobby['bet_negotiation']['proposer_id'] !== $player->id) {
            return ['success' => false, 'error' => __('Seul le proposeur peut annuler la mise')];
        }
        
        unset($lobby['bet_negotiation']);
        $lobby['settings']['bet_amount'] = 0;
        $lobby['settings']['bet_accepted'] = false;
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
    }
    
    public function startGame(string $code, User $host): array
    {
        $lockKey = 'lobby_start_lock:' . strtoupper($code);
        $lock = Cache::lock($lockKey, 10);
        
        if (!$lock->get()) {
            return ['success' => true, 'already_starting' => true, 'message' => __('Lancement en cours...')];
        }
        
        try {
            $lobby = $this->getLobby($code);
            
            if (!$lobby) {
                return ['success' => false, 'error' => __('Salon introuvable')];
            }
            
            if ($lobby['host_id'] !== $host->id) {
                return ['success' => false, 'error' => __('Seul l\'hôte peut lancer la partie')];
            }
            
            if (in_array($lobby['status'] ?? 'waiting', ['starting', 'started'])) {
                return ['success' => true, 'lobby' => $lobby, 'already_starting' => true];
            }
            
            $minPlayers = $lobby['settings']['min_players'] ?? 2;
            if (count($lobby['players']) < $minPlayers) {
                return ['success' => false, 'error' => __('Pas assez de joueurs (minimum :min)', ['min' => $minPlayers])];
            }
            
            if (!$this->areAllPlayersReady($lobby)) {
                return ['success' => false, 'error' => __('Tous les joueurs ne sont pas prêts')];
            }
            
            $betAmount = $lobby['settings']['bet_amount'] ?? 0;
            $playerBets = [];
            
            if ($betAmount > 0) {
                $playerIds = array_keys($lobby['players']);
                $gameStartId = Str::uuid()->toString();
                
                try {
                    DB::transaction(function () use ($playerIds, $betAmount, &$playerBets, $lobby, $code, $gameStartId) {
                        $existingStart = DB::table('lobby_game_starts')
                            ->where('lobby_code', strtoupper($code))
                            ->where('created_at', '>', now()->subMinutes(5))
                            ->first();
                        
                        if ($existingStart) {
                            throw new \Exception('ALREADY_STARTED');
                        }
                        
                        DB::table('lobby_game_starts')->insert([
                            'id' => $gameStartId,
                            'lobby_code' => strtoupper($code),
                            'bet_amount' => $betAmount,
                            'created_at' => now(),
                        ]);
                        
                        $players = User::whereIn('id', $playerIds)->lockForUpdate()->get()->keyBy('id');
                        
                        foreach ($playerIds as $playerId) {
                            $player = $players->get($playerId);
                            if (!$player || $player->competence_coins < $betAmount) {
                                $playerName = $lobby['players'][$playerId]['name'] ?? 'Joueur';
                                throw new \Exception(__(':name n\'a pas assez de pièces pour la mise', ['name' => $playerName]));
                            }
                        }
                        
                        foreach ($playerIds as $playerId) {
                            User::where('id', $playerId)->decrement('competence_coins', $betAmount);
                            $playerBets[$playerId] = $betAmount;
                        }
                    });
                } catch (\Exception $e) {
                    if ($e->getMessage() === 'ALREADY_STARTED') {
                        return ['success' => true, 'already_starting' => true];
                    }
                    return [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
                
                $lobby['bet_info'] = [
                    'bet_amount' => $betAmount,
                    'total_pot' => $betAmount * count($playerIds),
                    'player_bets' => $playerBets,
                    'deducted_at' => now()->toISOString(),
                    'game_start_id' => $gameStartId,
                ];
            }
            
            $lobby['status'] = 'starting';
            $lobby['started_at'] = now()->toISOString();
            
            $this->saveLobby($code, $lobby);
            
            return ['success' => true, 'lobby' => $lobby];
        } finally {
            $lock->release();
        }
    }
    
    public function refundBets(string $code, ?string $reason = null): array
    {
        $lockKey = 'lobby_refund_lock:' . strtoupper($code);
        $lock = Cache::lock($lockKey, 10);
        
        if (!$lock->get()) {
            return ['success' => true, 'refunded' => false, 'message' => __('Remboursement en cours...')];
        }
        
        try {
            $lobby = $this->getLobby($code);
            
            if (!$lobby) {
                return ['success' => false, 'error' => __('Salon introuvable')];
            }
            
            $betInfo = $lobby['bet_info'] ?? null;
            
            if (!$betInfo || empty($betInfo['player_bets'])) {
                return ['success' => true, 'refunded' => false, 'message' => __('Aucune mise à rembourser')];
            }
            
            if (isset($betInfo['refunded_at'])) {
                return ['success' => true, 'refunded' => false, 'message' => __('Mises déjà remboursées')];
            }
            
            if (isset($betInfo['winner_id'])) {
                return ['success' => false, 'error' => __('Le match est terminé, les gains ont été attribués')];
            }
            
            $gameStartId = $betInfo['game_start_id'] ?? null;
            
            $refundedPlayers = [];
            
            try {
                DB::transaction(function () use ($betInfo, &$refundedPlayers, $code, $gameStartId, $reason) {
                    if ($gameStartId) {
                        $gameStart = DB::table('lobby_game_starts')
                            ->where('id', $gameStartId)
                            ->lockForUpdate()
                            ->first();
                        
                        if (!$gameStart) {
                            throw new \Exception('NO_BET_RECORD');
                        }
                        
                        if ($gameStart->refunded_at) {
                            throw new \Exception('ALREADY_REFUNDED');
                        }
                        
                        DB::table('lobby_game_starts')
                            ->where('id', $gameStartId)
                            ->update([
                                'refunded_at' => now(),
                                'refund_reason' => $reason ?? 'match_cancelled',
                            ]);
                    }
                    
                    foreach ($betInfo['player_bets'] as $playerId => $amount) {
                        User::where('id', $playerId)->increment('competence_coins', $amount);
                        $refundedPlayers[$playerId] = $amount;
                    }
                });
            } catch (\Exception $e) {
                if ($e->getMessage() === 'ALREADY_REFUNDED') {
                    $lobby['bet_info']['refunded_at'] = now()->toISOString();
                    $this->saveLobby($code, $lobby);
                    return ['success' => true, 'refunded' => false, 'message' => __('Mises déjà remboursées')];
                }
                if ($e->getMessage() === 'NO_BET_RECORD') {
                    return ['success' => false, 'error' => __('Aucun enregistrement de mise trouvé')];
                }
                return [
                    'success' => false,
                    'error' => __('Erreur lors du remboursement: :error', ['error' => $e->getMessage()])
                ];
            }
            
            $lobby['bet_info']['refunded_at'] = now()->toISOString();
            $lobby['bet_info']['refund_reason'] = $reason ?? 'match_cancelled';
            $lobby['bet_info']['refunded_players'] = $refundedPlayers;
            
            $this->saveLobby($code, $lobby);
            
            return [
                'success' => true,
                'refunded' => true,
                'refunded_players' => $refundedPlayers,
                'total_refunded' => array_sum($refundedPlayers),
            ];
        } finally {
            $lock->release();
        }
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
