<?php

namespace App\Services;

use App\Models\User;
use App\Services\DuoFirestoreService;
use App\Services\GameServerService;
use App\Services\QuestionPlanBuilder;
use App\Services\QuestionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\GenerateMultiplayerQuestionsJob;

class LobbyService
{
    protected ?DuoFirestoreService $duoFirestoreService = null;
    private GameServerService $gameServerService;
    
    public function __construct(GameServerService $gameServerService)
    {
        $this->gameServerService = $gameServerService;
    }
    
    protected function getDuoFirestoreService(): DuoFirestoreService
    {
        if ($this->duoFirestoreService === null) {
            $this->duoFirestoreService = new DuoFirestoreService();
        }
        return $this->duoFirestoreService;
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
        
        // Use strpos for PHP 7.x compatibility
        // Skip normalization for full URLs and protocol-relative URLs - return as-is
        if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0 || strpos($avatar, '//') === 0) {
            return $avatar;
        }
        
        // For relative paths, normalize to just the base name
        if (strpos($avatar, '/') !== false || strpos($avatar, '.png') !== false) {
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
            // Use strpos for PHP 7.x compatibility (str_starts_with is PHP 8+)
            // Handle full URLs and protocol-relative URLs - return as-is
            if (strpos($avatarUrl, 'http://') === 0 || strpos($avatarUrl, 'https://') === 0 || strpos($avatarUrl, '//') === 0) {
                return $avatarUrl;
            }
            
            $avatarUrl = ltrim($avatarUrl, '/');
            
            // Already has images/ prefix - use as-is
            if (strpos($avatarUrl, 'images/') === 0) {
                if (substr($avatarUrl, -4) !== '.png') {
                    $avatarUrl .= '.png';
                }
                return $avatarUrl;
            }
            
            // Category/slug format like "animal/lynx" - needs prefix and suffix
            if (strpos($avatarUrl, '/') !== false && substr($avatarUrl, -4) !== '.png') {
                return 'images/avatars/' . $avatarUrl . '.png';
            }
            
            // Already has extension - use as relative path
            if (strpos($avatarUrl, '/') !== false) {
                return $avatarUrl;
            }
            
            // Simple name - add standard folder and extension
            $avatarUrl = preg_replace('/\.png$/', '', $avatarUrl);
            return 'images/avatars/standard/' . $avatarUrl . '.png';
        }
        
        $avatarId = $settings['avatar']['id'] ?? $settings['avatar'] ?? null;
        if ($avatarId && is_string($avatarId)) {
            // Handle full URLs in avatar id too
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
        $this->addPlayerToLobbyList($host->id, $lobbyCode, $mode);
        
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
            
            $mode = $lobby['mode'] ?? 'duo';
            $playerIds = array_keys($lobby['players']);
            
            // Mode Duo: Utiliser le Game Server Node.js
            if ($mode === 'duo') {
                try {
                    Log::info("[LobbyService] Starting Duo game via Game Server", [
                        'lobby_code' => $code,
                        'host_id' => $host->id,
                        'player_count' => count($playerIds),
                    ]);
                    
                    // 1. Créer une room sur le Game Server
                    $roomResult = $this->gameServerService->createRoom('DUO', $host->id, [
                        'lobbyCode' => $code,
                        'playerCount' => count($playerIds),
                    ]);
                    
                    if (!isset($roomResult['roomId'])) {
                        Log::error("[LobbyService] Failed to create Game Server room", [
                            'result' => $roomResult,
                        ]);
                        return [
                            'success' => false,
                            'error' => $roomResult['error'] ?? __('Erreur lors de la création de la room'),
                        ];
                    }
                    
                    $roomId = $roomResult['roomId'];
                    $gsLobbyCode = $roomResult['lobbyCode'] ?? $code;
                    $wsUrl = $this->gameServerService->getSocketUrl();
                    
                    Log::info("[LobbyService] Game Server room created", [
                        'roomId' => $roomId,
                        'gsLobbyCode' => $gsLobbyCode,
                        'wsUrl' => $wsUrl,
                    ]);
                    
                    // 2. Générer seulement la question 1 de manière synchrone (les autres en arrière-plan)
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
                    
                    // 3. Envoyer les questions au Game Server
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
                    
                    // Générer les questions 2-N en arrière-plan avec QuestionPlanBuilder
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
                            $roomId,
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
                    
                    // 4. Générer les tokens JWT pour chaque joueur
                    $playerTokens = [];
                    foreach ($playerIds as $playerId) {
                        $playerTokens[$playerId] = $this->gameServerService->generatePlayerToken($playerId, $roomId);
                    }
                    
                    Log::info("[LobbyService] Generated player tokens", [
                        'player_count' => count($playerTokens),
                    ]);
                    
                    // 5. Démarrer le jeu sur le Game Server AVANT de sauvegarder les métadonnées
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
                    
                    // 6. APRÈS succès: Stocker les informations Game Server et marquer comme démarré
                    $lobby['game_server'] = [
                        'roomId' => $roomId,
                        'lobbyCode' => $gsLobbyCode,
                        'wsUrl' => $wsUrl,
                        'player_tokens' => $playerTokens,
                    ];
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
            }
            // Modes League: Utiliser Firebase
            elseif (in_array($mode, ['league_individual', 'league_team'])) {
                $lobby['status'] = 'starting';
                $lobby['started_at'] = now()->toISOString();
                $this->saveLobby($code, $lobby);
                
                try {
                    $player1Id = $playerIds[0] ?? null;
                    $player2Id = $playerIds[1] ?? null;
                    
                    $matchData = [
                        'player1_id' => $player1Id,
                        'player2_id' => $player2Id,
                        'player1_name' => $lobby['players'][$player1Id]['name'] ?? 'Player 1',
                        'player2_name' => $lobby['players'][$player2Id]['name'] ?? 'Player 2',
                    ];
                    
                    $firestore = $this->getDuoFirestoreService();
                    
                    $firestore->prepareGameSession($code, $matchData);
                    
                    $gameData = [
                        'total_questions' => $lobby['settings']['nb_questions'] ?? 10,
                        'chrono_time' => $lobby['settings']['chrono_time'] ?? 8,
                        'current_round' => 1,
                    ];
                    
                    $firestore->sendGameStartSignal($code, $gameData);
                    
                    Log::info("[LobbyService] Firebase game start signal sent for lobby {$code}");
                } catch (\Exception $e) {
                    Log::error("[LobbyService] Failed to send Firebase game start signal: " . $e->getMessage());
                }
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
        
        $minPlayers = $lobby['settings']['min_players'] ?? 2;
        $playerIds = array_keys($lobby['players']);
        $maxRetries = 3;
        $retryDelay = 500000; // 500ms in microseconds
        
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
                    // Empty Firebase presence after all retries - cannot verify players
                    Log::warning("[VerifyPresence] All retries exhausted with empty Firebase data for lobby {$code}");
                    return [
                        'success' => false,
                        'error' => __('Impossible de vérifier la présence des joueurs. Veuillez réessayer.')
                    ];
                }
                
                $onlineThreshold = 90; // Increased to 90 seconds for more tolerance
                $now = time(); // Use seconds instead of microtime for consistency with Firebase timestamps
                $connectedPlayers = [];
                
                foreach ($presenceData as $playerId => $data) {
                    $lastSeen = $data['lastSeen'] ?? null;
                    $online = $data['online'] ?? false;
                    $lastSeenTime = null;
                    
                    // Handle different timestamp formats from Firebase
                    // FirebaseService::extractValue() returns timestamps as float (Unix seconds with microseconds)
                    if (is_float($lastSeen) || is_int($lastSeen)) {
                        // Float/int timestamp in seconds (from FirebaseService)
                        $lastSeenTime = (int)$lastSeen;
                    } elseif (is_numeric($lastSeen)) {
                        // Numeric string - could be seconds or milliseconds
                        $numericVal = (float)$lastSeen;
                        $lastSeenTime = $numericVal > 9999999999 ? (int)($numericVal / 1000) : (int)$numericVal;
                    } elseif ($lastSeen && is_array($lastSeen) && isset($lastSeen['_seconds'])) {
                        $lastSeenTime = (int)$lastSeen['_seconds'];
                    } elseif ($lastSeen && is_array($lastSeen) && isset($lastSeen['seconds'])) {
                        $lastSeenTime = (int)$lastSeen['seconds'];
                    }
                    
                    Log::debug("[VerifyPresence] Player {$playerId}: online={$online}, lastSeenTime={$lastSeenTime}, rawLastSeen=" . json_encode($lastSeen));
                    
                    if ($lastSeenTime === null) {
                        // If online flag is true and we can't parse timestamp, assume they're connected
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
                
                // Not enough players connected, try again if we have retries left
                if ($attempt < $maxRetries) {
                    usleep($retryDelay);
                    continue;
                }
                
                // Final attempt failed - report actual disconnects
                // Only fail-open on exceptions, not on genuine disconnects
                $missingCount = $minPlayers - count($connectedPlayers);
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
                // On final exception, allow the game to start (fail-open for better UX)
                Log::warning("[VerifyPresence] All retries exhausted with exceptions, allowing game start for lobby {$code}");
                return ['success' => true];
            }
        }
        
        // Should not reach here, but fail-open if we do
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
