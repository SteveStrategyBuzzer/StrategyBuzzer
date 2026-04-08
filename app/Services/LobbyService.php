<?php

namespace App\Services;

use App\Models\User;
use App\Services\GameServerService;
use App\Services\QuestionPlanBuilder;
use App\Services\QuestionService;
use App\Services\AntiDuplicationCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\GenerateMultiplayerQuestionsJob;
use App\Services\CoinLedgerService;

class LobbyService
{
    private GameServerService $gameServerService;
    private CoinLedgerService $coinLedgerService;
    
    public function __construct(
        GameServerService $gameServerService,
        CoinLedgerService $coinLedgerService
    ) {
        $this->gameServerService = $gameServerService;
        $this->coinLedgerService = $coinLedgerService;
    }
    
    protected const LOBBY_PREFIX = 'lobby:';
    protected const LOBBY_TTL = 3600;
    protected const PLAYER_LOBBIES_PREFIX = 'player_lobbies:';
    protected const PLAYER_LOBBIES_TTL = 3600;
    
    protected array $teamColors = [
        ['id' => 'red', 'name' => 'Rouge', 'hex' => '#E53935', 'light' => '#FFCDD2'],
        ['id' => 'blue', 'name' => 'Bleu', 'hex' => '#1E88E5', 'light' => '#BBDEFB'],
        ['id' => 'green', 'name' => 'Vert', 'hex' => '#43A047', 'light' => '#C8E6C9'],
        ['id' => 'orange', 'name' => 'Orange', 'hex' => '#FB8C00', 'light' => '#FFE0B2'],
        ['id' => 'purple', 'name' => 'Violet', 'hex' => '#8E24AA', 'light' => '#E1BEE7'],
        ['id' => 'cyan', 'name' => 'Cyan', 'hex' => '#00ACC1', 'light' => '#B2EBF2'],
        ['id' => 'pink', 'name' => 'Rose', 'hex' => '#F50057', 'light' => '#FF80AB'],
        ['id' => 'yellow', 'name' => 'Jaune', 'hex' => '#FDD835', 'light' => '#FFF9C4'],
        ['id' => 'teal', 'name' => 'Turquoise', 'hex' => '#0097A7', 'light' => '#B2EBF2'],
        ['id' => 'indigo', 'name' => 'Indigo', 'hex' => '#3949AB', 'light' => '#C5CAE9'],
        ['id' => 'lime', 'name' => 'Lime', 'hex' => '#76FF03', 'light' => '#CCFF90'],
        ['id' => 'brown', 'name' => 'Marron', 'hex' => '#6D4C41', 'light' => '#D7CCC8'],
    ];
    
    public function getTeamColors(): array
    {
        return $this->teamColors;
    }
    
    protected function normalizeAvatar(?string $avatar): string
    {
        if (!$avatar) {
            return 'images/avatars/standard/default.png';
        }

        // URLs complètes
        if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0 || strpos($avatar, '//') === 0) {
            return $avatar;
        }

        $avatar = ltrim($avatar, '/');

        // Si déjà un vrai chemin images → on le garde
        if (strpos($avatar, 'images/') === 0) {
            if (substr($avatar, -4) !== '.png') {
                $avatar .= '.png';
            }
            return $avatar;
        }

        // Si contient un chemin mais pas images → on force dans avatars
        if (strpos($avatar, '/') !== false) {
            if (substr($avatar, -4) !== '.png') {
                $avatar .= '.png';
            }
            return 'images/avatars/' . $avatar;
        }

        // Cas ID simple → standard
        $avatar = preg_replace('/\.png$/', '', $avatar);
        return 'images/avatars/standard/' . $avatar . '.png';
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
    
    public function getUserAvatarPublic(User $user): string
    {
        return $this->getUserAvatar($user);
    }

    protected function getUserAvatar(User $user): string
    {
        $settings = is_string($user->profile_settings) 
            ? json_decode($user->profile_settings, true) 
            : (array) $user->profile_settings;
        
        $avatarUrl = $settings['avatar']['url'] ?? null;
        
        if ($avatarUrl && is_string($avatarUrl) && strlen($avatarUrl) > 0) {
            if (strpos($avatarUrl, 'http://') === 0 || strpos($avatarUrl, 'https://') === 0 || strpos($avatarUrl, '//') === 0) {
                return $avatarUrl;
            }
            
            $avatarUrl = ltrim($avatarUrl, '/');
            
            if (strpos($avatarUrl, 'images/') === 0) {
                if (substr($avatarUrl, -4) !== '.png') {
                    $avatarUrl .= '.png';
                }
                return $avatarUrl;
            }
            
            if (strpos($avatarUrl, '/') !== false && substr($avatarUrl, -4) !== '.png') {
                return 'images/avatars/' . $avatarUrl . '.png';
            }
            
            if (strpos($avatarUrl, '/') !== false) {
                return $avatarUrl;
            }
            
            $avatarUrl = preg_replace('/\.png$/', '', $avatarUrl);
            return 'images/avatars/standard/' . $avatarUrl . '.png';
        }
        
        $avatarId = $settings['avatar']['id'] ?? $settings['avatar'] ?? null;
        if ($avatarId && is_string($avatarId)) {
            if (strpos($avatarId, 'http://') === 0 || strpos($avatarId, 'https://') === 0 || strpos($avatarId, '//') === 0) {
                return $avatarId;
            }
            $avatarId = preg_replace('/\.png$/', '', $avatarId);
            return 'images/avatars/standard/' . $avatarId . '.png';
        }
        
        return 'images/avatars/standard/default.png';
    }
    
    public function createLobby(User $host, string $mode, array $settings = []): array
    {
        $lobbyCode = $this->generateLobbyCode();
        
        $hostDisplayName = $this->getPlayerDisplayName($host);
        
        $mergedSettings = array_merge([
            'max_players' => $this->getMaxPlayers($mode),
            'min_players' => $this->getMinPlayers($mode),
            'teams_enabled' => in_array($mode, ['league_team', 'master']),
            'theme' => __('Culture générale'),
            'nb_questions' => 10,
        ], $settings);

        $matchId = $settings['match_id'] ?? null;
        $playerIds = $this->resolvePlayerIds($host, $mode, $matchId);

        $gameServerData = [];
        try {
            $result = $this->gameServerService->createRoomAndGenerateTokens(
                $mode,
                $host->id,
                $playerIds,
                [
                    'match_id' => $matchId,
                    'nb_questions' => $mergedSettings['nb_questions'],
                    'lobby_code' => $lobbyCode,
                    'hasBot' => (bool) ($mergedSettings['hasBot'] ?? false),
                ]
            );

            if (isset($result['roomId'])) {
                $gameServerData = [
                    'roomId' => $result['roomId'],
                    'socket_url' => $this->gameServerService->getSocketUrl(),
                ];
                Log::info('LobbyService: Room created at lobby creation', [
                    'lobbyCode' => $lobbyCode,
                    'roomId' => $result['roomId'],
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LobbyService: Could not create room at lobby creation, will retry later', [
                'lobbyCode' => $lobbyCode,
                'error' => $e->getMessage(),
            ]);
        }

        $lobby = [
            'code' => $lobbyCode,
            'host_id' => $host->id,
            'host_name' => $hostDisplayName,
            'mode' => $mode,
            'settings' => $mergedSettings,
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
                    'intelligence_coins' => $host->coins ?? 0,
                ],
            ],
            'game_server' => $gameServerData,
            'created_at' => now()->toISOString(),
            'status' => 'waiting',
        ];
        
        $this->saveLobby($lobbyCode, $lobby);
        $this->addPlayerToLobbyList($host->id, $lobbyCode, $mode);
        
        return $lobby;
    }

    public function recreateLobby(string $code, User $host, string $mode, array $settings = []): array
    {
        $mergedSettings = array_merge([
            'max_players' => $this->getMaxPlayers($mode),
            'min_players' => $this->getMinPlayers($mode),
            'teams_enabled' => false,
            'theme' => __('Culture générale'),
            'nb_questions' => 10,
        ], $settings);

        $hostDisplayName = $this->getPlayerDisplayName($host);

        $lobby = [
            'code'       => strtoupper($code),
            'host_id'    => $host->id,
            'host_name'  => $hostDisplayName,
            'mode'       => $mode,
            'settings'   => $mergedSettings,
            'players'    => [
                $host->id => [
                    'id'               => $host->id,
                    'name'             => $hostDisplayName,
                    'player_code'      => $host->player_code,
                    'avatar'           => $this->getUserAvatar($host),
                    'color'            => 'blue',
                    'team'             => null,
                    'ready'            => false,
                    'is_host'          => true,
                    'joined_at'        => now()->toISOString(),
                    'competence_coins' => $host->competence_coins ?? 0,
                    'intelligence_coins' => $host->coins ?? 0,
                ],
            ],
            'game_server' => [],
            'created_at'  => now()->toISOString(),
            'status'      => 'waiting',
        ];

        $this->saveLobby($code, $lobby);
        $this->addPlayerToLobbyList($host->id, $code, $mode);

        Log::info('LobbyService: Lobby recreated from DB data', ['code' => $code, 'host_id' => $host->id]);

        return $lobby;
    }

    protected function resolvePlayerIds(User $host, string $mode, ?int $matchId = null): array
    {
        $playerIds = [$host->id];

        if ($matchId) {
            $match = \App\Models\DuoMatch::find($matchId);
            if ($match) {
                if ($match->player1_id && $match->player1_id != $host->id) {
                    $playerIds[] = $match->player1_id;
                }
                if ($match->player2_id && $match->player2_id != $host->id) {
                    $playerIds[] = $match->player2_id;
                }
            }
        }

        return array_unique($playerIds);
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
        $isBot = (bool) ($player->is_bot ?? false);
        $lobby['players'][$player->id] = [
            'id' => $player->id,
            'name' => $playerDisplayName,
            'player_code' => $player->player_code,
            'avatar' => $this->getUserAvatar($player),
            'color' => $assignedColor,
            'team' => null,
            'ready' => $isBot,
            'is_host' => false,
            'is_bot' => $isBot,
            'joined_at' => now()->toISOString(),
            'competence_coins' => $player->competence_coins ?? 0,
            'intelligence_coins' => $player->coins ?? 0,
        ];

        $this->saveLobby($code, $lobby);
        $this->addPlayerToLobbyList($player->id, $code, $lobby['mode'] ?? 'duo');

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
    
    public function removePlayerFromLobby(string $code, int $playerId): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            return ['success' => false, 'error' => __('Salon introuvable')];
        }
        
        if (!isset($lobby['players'][$playerId])) {
            return ['success' => true, 'already_removed' => true];
        }
        
        $wasHost = $lobby['players'][$playerId]['is_host'] ?? false;
        
        unset($lobby['players'][$playerId]);
        
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
        
        return ['success' => true, 'lobby' => $lobby];
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
            
            $presenceCheck = $this->verifyPlayersPresence($code, $lobby);
            if (!$presenceCheck['success']) {
                return $presenceCheck;
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
                            $player = $players->get($playerId);

                            $this->coinLedgerService->debit(
                                $player,
                                $betAmount,
                                'duo_bet_stake',
                                'lobby',
                                null,
                                'competence'
                            );

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
            
            $mode = $lobby['mode'] ?? 'duo';
            $playerIds = array_keys($lobby['players']);
            
            if ($mode === 'duo') {
                try {
                    Log::info("[LobbyService] Starting Duo game via Game Server", [
                        'lobby_code' => $code,
                        'host_id' => $host->id,
                        'player_count' => count($playerIds),
                    ]);

                    $roomId = $lobby['game_server']['roomId'] ?? null;
                    $gsLobbyCode = $lobby['game_server']['lobbyCode'] ?? $code;
                    $socketUrl = $lobby['game_server']['socket_url'] ?? $this->gameServerService->getSocketUrl();

                    if (!$roomId) {
                        Log::error("[LobbyService] Missing existing Game Server room in lobby cache", [
                            'lobby_code' => $code,
                        ]);
                        return [
                            'success' => false,
                            'error' => __('Room Game Server introuvable dans le lobby'),
                        ];
                    }
                    
                    Log::info("[LobbyService] Reusing existing Game Server room", [
                        'roomId' => $roomId,
                        'gsLobbyCode' => $gsLobbyCode,
                        'socketUrl' => $socketUrl,
                    ]);
                    
                    $questionService = app(QuestionService::class);
                    $questions = [];
                    $nbQuestions = $lobby['settings']['nb_questions'] ?? 10;
                    $theme = $lobby['settings']['theme'] ?? 'culture générale';
                    $niveau = $lobby['settings']['niveau'] ?? 3;
                    $language = $lobby['settings']['language'] ?? app()->getLocale();
                    
                    Log::info("[LobbyService] Generating question 1 synchronously, questions 2-{$nbQuestions} will be generated in background", [
                        'theme' => $theme,
                        'niveau' => $niveau,
                        'language' => $language,
                    ]);
                    
                    for ($i = 1; $i <= 1; $i++) {
                        $q = $questionService->generateQuestion(
                            $theme,
                            $niveau,
                            $i,
                            [],
                            [],
                            [],
                            [],
                            null,
                            false,
                            $language,
                            true
                        );
                        
                        if ($q) {
                            $questions[] = [
                                'id' => $q['id'] ?? 'q_' . $i,
                                'text' => $q['question_text'] ?? $q['text'] ?? '',
                                'answers' => $q['answers'] ?? [],
                                'correct_index' => $q['correct_id'] ?? $q['correct_index'] ?? 0,
                                'sub_theme' => $q['sub_theme'] ?? '',
                                'theme' => $theme,
                            ];
                        }
                    }
                    
                    Log::info("[LobbyService] Generated questions", [
                        'count' => count($questions),
                    ]);
                    
                    $sendResult = $this->gameServerService->sendQuestions($roomId, $questions);
                    
                    if (!($sendResult['success'] ?? false)) {
                        Log::error("[LobbyService] Failed to send questions to Game Server", [
                            'roomId' => $roomId,
                            'error' => $sendResult['error'] ?? 'Unknown error',
                        ]);
                        return [
                            'success' => false,
                            'error' => $sendResult['error'] ?? __('Erreur lors de l\'envoi des questions'),
                        ];
                    }
                    
                    $usedQuestionIds = array_map(fn($q) => $q['id'], $questions);
                    $usedAnswers = [];
                    $usedQuestionTexts = [];
                    foreach ($questions as $q) {
                        $usedQuestionTexts[] = $q['text'];
                        foreach ($q['answers'] as $answer) {
                            $answerText = is_array($answer) ? ($answer['text'] ?? '') : $answer;
                            if ($answerText) {
                                $usedAnswers[] = $answerText;
                            }
                        }
                    }
                    
                    $antiDuplicationCache = new AntiDuplicationCacheService();
                    if (!empty($questions)) {
                        $antiDuplicationCache->initialize($code, $questions[0]);
                        for ($i = 1; $i < count($questions); $i++) {
                            $antiDuplicationCache->addQuestion($code, $questions[$i]);
                        }
                    }
                    Log::info("[LobbyService] Initialized anti-duplication cache with Q1", [
                        'lobby_code' => $code,
                        'question_count' => count($questions),
                    ]);
                    
                    $hasStrategicAvatar = !empty($lobby['settings']['strategic_avatar'] ?? null) 
                        && ($lobby['settings']['strategic_avatar'] ?? 'Aucun') !== 'Aucun';
                    
                    $plan = QuestionPlanBuilder::build([
                        'nb_questions' => $nbQuestions,
                        'nb_rounds' => $lobby['settings']['nb_rounds'] ?? 3,
                        'strategic_avatar' => $lobby['settings']['strategic_avatar'] ?? 'Aucun',
                        'skill_bonus_enabled' => $hasStrategicAvatar,
                        'tiebreaker_questions' => 5,
                    ]);
                    
                    $totalQuestions = $plan['total_questions'];
                    
                    if ($totalQuestions > 1) {
                        GenerateMultiplayerQuestionsJob::dispatch(
                            $code,
                            'duo',
                            $theme,
                            $niveau,
                            $language,
                            $totalQuestions,
                            2,
                            4,
                            $usedQuestionIds,
                            $usedAnswers,
                            $usedQuestionTexts,
                            $hasStrategicAvatar,
                            true,
                            $plan['main_questions'],
                            $plan['skill_bonus_questions']
                        );
                        Log::info("[LobbyService] Dispatched background job for questions 2-{$totalQuestions}", [
                            'plan' => $plan,
                        ]);
                    }
                    
                    $startResult = $this->gameServerService->startGame($roomId, (string) $host->id);
                    
                    if (!($startResult['success'] ?? false)) {
                        Log::error("[LobbyService] Failed to start game on Game Server", [
                            'roomId' => $roomId,
                            'error' => $startResult['error'] ?? 'Unknown error',
                        ]);
                        return [
                            'success' => false,
                            'error' => $startResult['error'] ?? __('Erreur lors du démarrage du jeu'),
                        ];
                    }

                    $lobby['game_server']['roomId'] = $roomId;
                    $lobby['game_server']['socket_url'] = $socketUrl;
                    $lobby['status'] = 'started';
                    $lobby['started_at'] = now()->toISOString();
                    $this->saveLobby($code, $lobby);
                    
                    Log::info("[LobbyService] Duo game started successfully via Game Server", [
                        'lobby_code' => $code,
                        'roomId' => $roomId,
                    ]);
                  
                } catch (\Exception $e) {
                    Log::error("[LobbyService] Exception during Game Server Duo start", [
                        'lobby_code' => $code,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return [
                        'success' => false,
                        'error' => __('Erreur lors du démarrage du jeu: :error', ['error' => $e->getMessage()]),
                    ];
                }
            } elseif (in_array($mode, ['league_individual', 'league_team'])) {
                $lobby['status'] = 'starting';
                $lobby['started_at'] = now()->toISOString();
                $this->saveLobby($code, $lobby);
            } else {
                $this->saveLobby($code, $lobby);
            }
            
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
                        $player = User::find($playerId);

                        if ($player) {
                            $this->coinLedgerService->credit(
                                $player,
                                $amount,
                                'duo_bet_refund',
                                'lobby',
                                null,
                                'competence'
                            );

                            $refundedPlayers[$playerId] = $amount;
                        }
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
    
    protected function addPlayerToLobbyList(int $playerId, string $code, string $mode): void
    {
        $lobbies = $this->getPlayerLobbyList($playerId);
        $lobbies[strtoupper($code)] = [
            'code' => strtoupper($code),
            'mode' => $mode,
            'joined_at' => now()->toISOString(),
        ];
        Cache::put(self::PLAYER_LOBBIES_PREFIX . $playerId, $lobbies, self::PLAYER_LOBBIES_TTL);
    }
    
    protected function removePlayerFromLobbyList(int $playerId, string $code): void
    {
        $lobbies = $this->getPlayerLobbyList($playerId);
        unset($lobbies[strtoupper($code)]);
        if (empty($lobbies)) {
            Cache::forget(self::PLAYER_LOBBIES_PREFIX . $playerId);
        } else {
            Cache::put(self::PLAYER_LOBBIES_PREFIX . $playerId, $lobbies, self::PLAYER_LOBBIES_TTL);
        }
    }
    
    protected function getPlayerLobbyList(int $playerId): array
    {
        return Cache::get(self::PLAYER_LOBBIES_PREFIX . $playerId, []);
    }
    
    public function getPlayerOpenLobbies(int $playerId): array
    {
        $lobbyList = $this->getPlayerLobbyList($playerId);
        $openLobbies = [];
        
        foreach ($lobbyList as $code => $info) {
            $lobby = $this->getLobby($code);
            if ($lobby && $lobby['status'] === 'waiting' && isset($lobby['players'][$playerId])) {
                $openLobbies[] = [
                    'code' => $code,
                    'mode' => $lobby['mode'] ?? $info['mode'],
                    'host_name' => $lobby['host_name'] ?? 'Inconnu',
                    'player_count' => count($lobby['players']),
                    'max_players' => $lobby['settings']['max_players'] ?? 2,
                    'theme' => $lobby['settings']['theme'] ?? __('Culture générale'),
                    'joined_at' => $info['joined_at'] ?? $lobby['created_at'],
                ];
            } else {
                $this->removePlayerFromLobbyList($playerId, $code);
            }
        }
        
        return $openLobbies;
    }
    
    public function closeLobbyForPlayer(string $code, int $playerId): array
    {
        $lobby = $this->getLobby($code);
        
        if (!$lobby) {
            $this->removePlayerFromLobbyList($playerId, $code);
            return ['success' => true, 'already_closed' => true];
        }
        
        if (!isset($lobby['players'][$playerId])) {
            $this->removePlayerFromLobbyList($playerId, $code);
            return ['success' => true, 'not_in_lobby' => true];
        }
        
        $wasHost = $lobby['players'][$playerId]['is_host'] ?? false;
        unset($lobby['players'][$playerId]);
        $this->removePlayerFromLobbyList($playerId, $code);
        
        if (empty($lobby['players'])) {
            $this->deleteLobby($code);
            return ['success' => true, 'lobby_deleted' => true];
        }
        
        if ($wasHost) {
            $newHostId = array_key_first($lobby['players']);
            $lobby['players'][$newHostId]['is_host'] = true;
            $lobby['host_id'] = $newHostId;
            $lobby['host_name'] = $lobby['players'][$newHostId]['name'];
        }
        
        $this->saveLobby($code, $lobby);
        
        return ['success' => true, 'lobby' => $lobby];
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
            // Bot players are always considered ready
            if ($player['is_bot'] ?? false) {
                continue;
            }
            if (!$player['ready']) {
                return false;
            }
        }
        
        return true;
    }
    
    public function verifyPlayersPresence(string $code, array $lobby): array
    {
        $mode = $lobby['mode'] ?? 'duo';
        
        if (!in_array($mode, ['duo', 'league_individual', 'league_team'])) {
            return ['success' => true];
        }
        
        // Only count human (non-bot) players for presence check — bots have no browser
        $humanPlayerIds = array_keys(array_filter(
            $lobby['players'],
            fn($p) => !($p['is_bot'] ?? false)
        ));
        $minPlayers = count($humanPlayerIds);
        
        // If there are no human players to verify (all bots), skip the check
        if ($minPlayers === 0) {
            return ['success' => true];
        }

        $playerIds = $humanPlayerIds;
        $maxRetries = 3;
        $retryDelay = 500000;
        
        Log::info("[VerifyPresence] Starting for lobby {$code}, mode: {$mode}, minPlayers: {$minPlayers}");
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $firebase = \App\Services\FirebaseService::getInstance();
                
                $presencePath = "lobbies/{$code}/presence";
                $presenceData = $firebase->getCollection($presencePath);
                
                $presenceCount = is_array($presenceData) ? count($presenceData) : 0;
                Log::info("[VerifyPresence] Attempt {$attempt}/{$maxRetries} - Found {$presenceCount} presence entries");
                
                if (empty($presenceData)) {
                    Log::warning("[VerifyPresence] Attempt {$attempt}/{$maxRetries} - No presence data for lobby {$code}");
                    if ($attempt < $maxRetries) {
                        usleep($retryDelay);
                        continue;
                    }
                    Log::warning("[VerifyPresence] All retries exhausted with empty Firebase data for lobby {$code}");
                    return [
                        'success' => false,
                        'error' => __('Impossible de vérifier la présence des joueurs. Veuillez réessayer.')
                    ];
                }
                
                $onlineThreshold = 90;
                $now = time();
                $connectedPlayers = [];
               
                foreach ($presenceData as $playerId => $data) {
                    $lastSeen = $data['lastSeen'] ?? null;
                    $online = $data['online'] ?? false;
                    $lastSeenTime = null;
                    
                    if (is_float($lastSeen) || is_int($lastSeen)) {
                        $lastSeenTime = (int)$lastSeen;
                    } elseif (is_numeric($lastSeen)) {
                        $numericVal = (float)$lastSeen;
                        $lastSeenTime = $numericVal > 9999999999 ? (int)($numericVal / 1000) : (int)$numericVal;
                    } elseif ($lastSeen && is_array($lastSeen) && isset($lastSeen['_seconds'])) {
                        $lastSeenTime = (int)$lastSeen['_seconds'];
                    } elseif ($lastSeen && is_array($lastSeen) && isset($lastSeen['seconds'])) {
                        $lastSeenTime = (int)$lastSeen['seconds'];
                    }
                    
                    Log::debug("[VerifyPresence] Player {$playerId}: online={$online}, lastSeenTime={$lastSeenTime}, rawLastSeen=" . json_encode($lastSeen));
                    
                    if ($lastSeenTime === null) {
                        if ($online) {
                            $connectedPlayers[] = (int)$playerId;
                            Log::debug("[VerifyPresence] Player {$playerId} added (online flag, no timestamp)");
                        }
                        continue;
                    }
                    
                    $timeSinceLastSeen = $now - $lastSeenTime;
                    
                    if ($online && $timeSinceLastSeen < $onlineThreshold) {
                        $connectedPlayers[] = (int)$playerId;
                        Log::debug("[VerifyPresence] Player {$playerId} added (online + recent: {$timeSinceLastSeen}s ago)");
                    } else {
                        Log::debug("[VerifyPresence] Player {$playerId} NOT added: online={$online}, timeSince={$timeSinceLastSeen}s");
                    }
                }
                
                Log::info("[VerifyPresence] Connected: " . count($connectedPlayers) . ", required: {$minPlayers}");
                
                if (count($connectedPlayers) >= $minPlayers) {
                    Log::info("[VerifyPresence] SUCCESS for lobby {$code}");
                    return ['success' => true];
                }
                
                if ($attempt < $maxRetries) {
                    usleep($retryDelay);
                    continue;
                }
                
                Log::warning("[VerifyPresence] FAILED for lobby {$code}: only " . count($connectedPlayers) . " of {$minPlayers} verified");
                
                return [
                    'success' => false,
                    'error' => __('Un ou plusieurs joueurs ne sont plus connectés. Veuillez vérifier que tous les joueurs sont présents.')
                ];
                
            } catch (\Exception $e) {
                Log::error("[VerifyPresence] Exception on attempt {$attempt}/{$maxRetries} for lobby {$code}: " . $e->getMessage());
                if ($attempt < $maxRetries) {
                    usleep($retryDelay);
                    continue;
                }
                Log::warning("[VerifyPresence] All retries exhausted with exceptions, allowing game start for lobby {$code}");
                return ['success' => true];
            }
        }
        
        return ['success' => true];
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
