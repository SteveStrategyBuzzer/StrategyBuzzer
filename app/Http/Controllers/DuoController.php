<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Services\DuoMatchmakingService;
use App\Services\DivisionService;
use App\Services\GameStateService;
use App\Services\PlayerProfileSnapshotService;
use App\Services\BuzzManagerService;
use App\Services\PlayerContactService;
use App\Services\LobbyService;
use App\Services\GameServerService;
use App\Models\DuoMatch;
use App\Models\PlayerDuoStat;
use App\Models\User;

class DuoController extends Controller
{
    public function __construct(
        private DuoMatchmakingService $matchmaking,
        private DivisionService $divisionService,
        private GameStateService $gameStateService,
        private BuzzManagerService $buzzManager,
        private PlayerContactService $contactService,
        private LobbyService $lobbyService,
        private GameServerService $gameServerService
    ) {}

    private function getPlayerSnapshot(User $user): array
    {
        return PlayerProfileSnapshotService::build($user);
    }

    private function getSnapshotDisplayName(array $snapshot, User $fallbackUser, string $fallback = 'Joueur'): string
    {
        return (string) data_get($snapshot, 'identity.display_name', $fallbackUser->name ?? $fallback)
            ?: ($fallbackUser->name ?? $fallback);
    }

    private function getSnapshotAvatarPath(array $snapshot, string $default = 'images/avatars/standard/standard1.png'): string
    {
        $path = (string) data_get($snapshot, 'avatar.url', $default);
        if ($path === '') {
            $path = $default;
        }

        if (!str_starts_with($path, '/') && !str_starts_with($path, 'http')) {
            return '/' . ltrim($path, '/');
        }

        return $path;
    }

    private function getSnapshotStrategicAvatarName(array $snapshot): string
    {
        return (string) data_get($snapshot, 'strategic_avatar.name', 'Aucun') ?: 'Aucun';
    }

    private function getSnapshotStrategicAvatarPath(array $snapshot): ?string
    {
        $avatarName = $this->getSnapshotStrategicAvatarName($snapshot);

        if ($avatarName === 'Aucun') {
            return null;
        }

        $catalog = \App\Services\AvatarCatalog::get();
        $strategicAvatars = $catalog['stratégiques']['items'] ?? [];

        foreach ($strategicAvatars as $avatar) {
            if (($avatar['name'] ?? null) === $avatarName) {
                return $avatar['image'] ?? $avatar['path'] ?? null;
            }
        }

        return null;
    }

    private function generateFreshGameplayToken(?string $roomId, int $userId): ?string
    {
        if (!$roomId) {
            return null;
        }

        return $this->gameServerService->generatePlayerToken($userId, $roomId);
    }


    public function showSplash()
    {
        $redirectUrl = route('duo.lobby');
        return view('duo_splash', compact('redirectUrl'));
    }

    public function index()
    {
        $user = Auth::user();
        $stats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $user->id],
            ['level' => 0]
        );
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');

        return inertia('Duo/Index', [
            'stats' => $stats,
            'division' => $division,
            'hasPlayedEnoughForLeague' => $stats->hasPlayedEnoughForLeague(),
        ]);
    }

    public function invitePlayer(Request $request)
    {
        $request->validate([
            'player_code' => 'required|string',
        ]);

        $user = Auth::user();

        $opponent = \App\Services\PlayerCodeService::findByCode($request->player_code);

        if (!$opponent) {
            return response()->json([
                'success' => false,
                'message' => __('Joueur introuvable avec ce code'),
            ], 404);
        }

        if ($opponent->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('Vous ne pouvez pas vous inviter vous-même'),
            ], 400);
        }

        if ($this->matchmaking->hasPendingInvitation($user->id, $opponent->id)) {
            return response()->json([
                'success' => false,
                'message' => __('Vous avez déjà une invitation en attente pour ce joueur'),
            ], 400);
        }

        $pendingCounts = $this->matchmaking->getPendingInvitationCounts($user->id);
        if ($pendingCounts['sent'] >= 5) {
            return response()->json([
                'success' => false,
                'message' => __('Vous avez atteint la limite de 5 invitations en attente'),
            ], 400);
        }

        $match = $this->matchmaking->createInvitation($user, $opponent->id);

        $lobby = $this->lobbyService->createLobby($user, 'duo', [
            'theme' => __('Culture générale'),
            'nb_questions' => 10,
            'match_id' => $match->id,
            'hasBot' => $opponent->is_bot,
        ]);

        $match->lobby_code = $lobby['code'];
        $match->save();

        // Bot opponents are auto-accepted so the host can play immediately
        if ($opponent->is_bot) {
            $this->matchmaking->acceptMatch($match);
            $this->contactService->registerMutualContacts($match->player1_id, $match->player2_id);
            $this->lobbyService->joinLobby($lobby['code'], $opponent);
        }

        try {
            app(\App\Services\DailyQuestService::class)->checkAndCompleteDailyQuest(
                $user,
                'daily_invite_player',
                ['player_invited' => true]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('DailyQuest player_invited (Duo) failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
            'lobby_code' => $lobby['code'],
            'redirect_url' => route('lobby.show', ['code' => $lobby['code']]),
            'is_bot_match' => (bool) $opponent->is_bot,
        ]);
    }

    public function findRandomOpponent(Request $request)
    {
        $user = Auth::user();
        $opponent = $this->matchmaking->findRandomOpponent($user);

        if (!$opponent) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun adversaire disponible pour le moment.',
            ]);
        }

        $match = $this->matchmaking->createRandomMatch($user, $opponent);

        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
        ]);
    }

    public function acceptMatch(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('Vous n\'êtes pas autorisé à accepter ce match.'),
            ], 403);
        }

        if (!$match->lobby_code) {
            return response()->json([
                'success' => false,
                'message' => __('Le salon n\'existe plus. L\'invitation a peut-être expiré.'),
            ], 400);
        }

        $lobbyExists = (bool) $this->lobbyService->getLobby($match->lobby_code);

        if (!$lobbyExists) {
            $host = \App\Models\User::find($match->player1_id);
            if ($host) {
                $this->lobbyService->recreateLobby($match->lobby_code, $host, 'duo', [
                    'theme'        => $match->theme ?? __('Culture générale'),
                    'nb_questions' => 10,
                    'match_id'     => $match->id,
                ]);
            }
        }

        $this->matchmaking->acceptMatch($match);

        $this->contactService->registerMutualContacts($match->player1_id, $match->player2_id);

        $joinResult = $this->lobbyService->joinLobby($match->lobby_code, $user);

        if (!($joinResult['success'] ?? false) && !($joinResult['already_joined'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => __('Le salon n\'a pas pu être rejoint. Demandez à votre adversaire de renvoyer une invitation.'),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
            'lobby_code' => $match->lobby_code,
            'redirect_url' => route('lobby.show', ['code' => $match->lobby_code]),
        ]);
    }

    public function declineMatch(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('Vous n\'êtes pas autorisé à refuser ce match.'),
            ], 403);
        }

        if ($match->lobby_code) {
            $this->lobbyService->deleteLobby($match->lobby_code);
        }

        $this->matchmaking->cancelMatch($match);

        return response()->json([
            'success' => true,
            'message' => __('Invitation refusée'),
        ]);
    }

    public function cancelMatch(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id !== $user->id && $match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à annuler ce match.',
            ], 403);
        }

        $this->matchmaking->cancelMatch($match);

        session()->forget(['game_state', 'game_mode', 'used_skills', 'skill_usage_counts']);

        return response()->json([
            'success' => true,
        ]);
    }

    public function buzz(Request $request, DuoMatch $match)
    {
        $request->validate([
            'question_id' => 'required|string',
            'client_time' => 'nullable|numeric',
        ]);

        $user = Auth::user();

        if ($match->player1_id !== $user->id && $match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'appartenez pas à ce match.',
            ], 403);
        }

        $playerId = $match->player1_id === $user->id ? 'player' : 'opponent';

        $gameState = $match->game_state;
        $buzzes = $gameState['buzzes'] ?? [];

        if (!$this->buzzManager->canBuzz($playerId, $buzzes)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de buzz. Veuillez patienter.',
            ], 429);
        }

        $buzzRecord = $this->buzzManager->recordBuzz($playerId, $request->input('client_time'));
        $buzzes[] = $buzzRecord;

        $gameState['buzzes'] = $buzzes;
        $match->game_state = $gameState;
        $match->save();

        $fastest = $this->buzzManager->determineFastest($buzzes);

        return response()->json([
            'success' => true,
            'buzzRecord' => $buzzRecord,
            'fastest' => $fastest,
            'canAnswer' => $fastest && $fastest['player_id'] === $playerId,
        ]);
    }

    public function submitAnswer(Request $request, DuoMatch $match)
    {
        $request->validate([
            'question_id' => 'required|string',
            'answer' => 'required|string',
            'correct_answer' => 'required|string',
        ]);

        $user = Auth::user();

        if ($match->player1_id !== $user->id && $match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'appartenez pas à ce match.',
            ], 403);
        }

        $playerId = $match->player1_id === $user->id ? 'player' : 'opponent';

        $gameState = $match->game_state;
        $buzzes = $gameState['buzzes'] ?? [];

        $fastest = $this->buzzManager->determineFastest($buzzes);
        $isFirst = $fastest && $fastest['player_id'] === $playerId;

        $isCorrect = strtolower(trim($request->answer)) === strtolower(trim($request->correct_answer));

        $points = $this->buzzManager->calculatePoints($isFirst, $isCorrect);

        $this->gameStateService->updateScore($gameState, $playerId, $points, $isCorrect);

        $this->gameStateService->recordAnswer($gameState, [
            'question_id' => $request->question_id,
            'question_text' => $request->input('question_text', ''),
            'player_answer' => $request->answer,
            'correct_answer' => $request->correct_answer,
            'is_correct' => $isCorrect,
            'points_earned' => $points,
            'buzz_time' => $fastest['server_time'] ?? null,
        ]);

        $gameState['buzzes'] = [];
        $gameState['question_start_time'] = microtime(true);

        $hasMoreQuestions = $this->gameStateService->nextQuestion($gameState);

        $roundResult = null;
        if (!$hasMoreQuestions) {
            $roundResult = $this->gameStateService->finishRound($gameState);

            if ($roundResult['winner'] === 'draw' || $roundResult['is_draw']) {
                $this->gameStateService->resetForNewRound($gameState);
            } elseif (!$this->gameStateService->isMatchFinished($gameState)) {
                $this->gameStateService->nextRound($gameState);
            }
        }

        $match->game_state = $gameState;
        $match->save();

        return response()->json([
            'success' => true,
            'isCorrect' => $isCorrect,
            'points' => $points,
            'gameState' => $gameState,
            'hasMoreQuestions' => $hasMoreQuestions,
            'roundFinished' => !$hasMoreQuestions,
            'roundResult' => $roundResult,
        ]);
    }

    public function finishMatch(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id !== $user->id && $match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        $gameState = $match->game_state;

        if (!$this->gameStateService->isMatchFinished($gameState)) {
            return response()->json([
                'success' => false,
                'message' => 'Le match n\'est pas terminé.',
            ]);
        }

        $matchResult = $this->gameStateService->getMatchResult($gameState);

        $player1Score = $gameState['player_total_score'] ?? 0;
        $player2Score = $gameState['opponent_total_score'] ?? 0;

        try {
            $finishedMatch = $this->matchmaking->finishMatch(
                $match,
                $player1Score,
                $player2Score,
                $gameState
            );

            $wasDecisiveRound = ($gameState['current_round'] ?? 1) === 3;

            $player1Won = $matchResult['winner'] === 'player1' ? true : ($matchResult['winner'] === 'player2' ? false : null);
            $player2Won = $matchResult['winner'] === 'player2' ? true : ($matchResult['winner'] === 'player1' ? false : null);

            $this->contactService->addOrUpdateContact(
                $match->player1_id,
                $match->player2_id,
                $player1Won,
                $wasDecisiveRound
            );

            $this->contactService->addOrUpdateContact(
                $match->player2_id,
                $match->player1_id,
                $player2Won,
                $wasDecisiveRound
            );

            return response()->json([
                'success' => true,
                'match' => $finishedMatch->load(['player1', 'player2', 'winner']),
                'matchResult' => $matchResult,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function finishMatchSocketIO(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id !== $user->id && $match->player2_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        if ($match->status === 'finished') {
            return response()->json(['success' => true, 'already_finished' => true]);
        }

        // Read authoritative match result written by the game server to Redis.
        // Client-provided winner data is intentionally ignored — only the game server writes this key.
        $roomId = $match->room_id ?? $match->lobby_code;
        if (!$roomId) {
            return response()->json(['success' => false, 'message' => 'Room ID introuvable.'], 400);
        }

        $redisKey = 'gs:match:' . $roomId . ':result';
        $raw = Redis::connection('game_server')->get($redisKey);

        if (!$raw) {
            // Game server hasn't written the result yet — tell client to retry
            return response()->json(['success' => false, 'pending' => true, 'message' => 'Résultat pas encore disponible, réessayez.'], 202);
        }

        $result = json_decode($raw, true);
        $winnerId    = $result['winnerId'] ?? null;
        $finalScores = $result['finalScores'] ?? [];
        $isTie       = $result['isTie'] ?? false;

        $player1Id = (string) $match->player1_id;
        $player2Id = (string) $match->player2_id;

        // Determine scores: use actual final scores to compare, fallback to 1/0 differential
        $p1 = isset($finalScores[$player1Id]) ? (int) $finalScores[$player1Id] : -1;
        $p2 = isset($finalScores[$player2Id]) ? (int) $finalScores[$player2Id] : -1;

        if ($p1 >= 0 && $p2 >= 0 && $p1 !== $p2) {
            $player1Score = $p1;
            $player2Score = $p2;
        } elseif ($winnerId) {
            $player1Won   = (string) $winnerId === $player1Id;
            $player1Score = $player1Won ? 1 : 0;
            $player2Score = $player1Won ? 0 : 1;
        } else {
            // Genuine tie — no winner; still persist via finishMatch but skip division ranking
            $player1Score = 0;
            $player2Score = 0;
        }

        try {
            $gameState = $match->game_state ?? [];

            $this->matchmaking->finishMatch(
                $match,
                $player1Score,
                $player2Score,
                $gameState
            );

            $player1Won = $player1Score > $player2Score;
            $player2Won = $player2Score > $player1Score;

            $this->contactService->addOrUpdateContact(
                $match->player1_id,
                $match->player2_id,
                $player1Won,
                true
            );
            $this->contactService->addOrUpdateContact(
                $match->player2_id,
                $match->player1_id,
                $player2Won,
                true
            );

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'MATCH_ALREADY_FINISHED') {
                return response()->json(['success' => true, 'already_finished' => true]);
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function getMatch(DuoMatch $match)
    {
        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2', 'winner']),
        ]);
    }

    public function syncGameState(DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id !== $user->id && $match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'appartenez pas à ce match.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'gameState' => $match->game_state ?? [],
            'matchId' => $match->id,
            'timestamp' => microtime(true),
        ]);
    }

    public function getRankings(Request $request)
    {
        $division = $request->input('division', 'bronze');
        $rankings = $this->divisionService->getRankingsForDivision('duo', $division);

        return response()->json([
            'success' => true,
            'rankings' => $rankings,
            'division' => $division,
        ]);
    }

    public function getMyStats()
    {
        $user = Auth::user();
        $stats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $user->id],
            ['level' => 0]
        );
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $rank = $this->divisionService->getPlayerRank($user, 'duo');

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'division' => $division,
            'rank' => $rank,
        ]);
    }

    public function lobby()
    {
        $user = Auth::user();
        $stats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $user->id],
            ['level' => 0]
        );
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $rankings = $this->divisionService->getRankingsForDivision('duo', $division->division, 10);

        $profileSettings = $user->profile_settings ?? [];
        $choixNiveau = is_array($profileSettings) ? ($profileSettings['choix_niveau'] ?? 1) : 1;
        $duoFullUnlocked = $choixNiveau >= 11;

        $activeLobbyCode = null;
        $activeLobby = null;
        $currentLobbyCode = session('current_lobby_code');
        if ($currentLobbyCode) {
            $lobby = $this->lobbyService->getLobby($currentLobbyCode);
            if ($lobby && isset($lobby['players'][$user->id]) && ($lobby['mode'] ?? '') === 'duo') {
                $activeLobbyCode = $currentLobbyCode;
                $activeLobby = $lobby;
            } else {
                session()->forget('current_lobby_code');
            }
        }

        return view('duo_lobby', [
            'stats' => $stats,
            'division' => $division,
            'rankings' => $rankings,
            'duoFullUnlocked' => $duoFullUnlocked,
            'choixNiveau' => $choixNiveau,
            'activeLobbyCode' => $activeLobbyCode,
            'activeLobby' => $activeLobby,
        ]);
    }

    public function matchmaking(Request $request)
    {
        $user = Auth::user();
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $stats = PlayerDuoStat::firstOrCreate(['user_id' => $user->id], ['level' => 0]);

        return view('duo_matchmaking', [
            'division' => $division->division,
            'player_level' => $stats->level,
        ]);
    }

    public function game(DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            abort(403, 'Unauthorized');
        }

        $roomId = $match->room_id ?? null;
        $lobbyCode = $match->lobby_code ?? null;
        $jwtToken = $this->generateFreshGameplayToken($roomId, $user->id);


        $profileSettings = $user->profile_settings ?? [];
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        }
        if (!is_array($profileSettings)) {
            $profileSettings = [];
        }
        $strategicAvatar = data_get($profileSettings, 'strategic_avatar', 'Aucun');

        $skills = $this->getPlayerSkillsWithTriggers($user);

        $opponent = $match->player1_id == $user->id ? $match->player2 : $match->player1;
        $opponentSettings = $opponent->profile_settings ?? [];
        if (is_string($opponentSettings)) {
            $opponentSettings = json_decode($opponentSettings, true) ?? [];
        }
        $opponentName = data_get($opponentSettings, 'pseudonym', $opponent->name ?? 'Adversaire');
        $opponentAvatarPath = data_get($opponentSettings, 'avatar.url', 'images/avatars/standard/standard1.png');

        $playerAvatarPath = data_get($profileSettings, 'avatar.url', 'images/avatars/standard/standard1.png');

        $avatarName = is_array($strategicAvatar) ? ($strategicAvatar['name'] ?? 'Aucun') : $strategicAvatar;
        $strategicAvatarPath = null;
        if ($avatarName !== 'Aucun' && !empty($avatarName)) {
            $catalog = \App\Services\AvatarCatalog::get();
            $strategicAvatars = $catalog['stratégiques']['items'] ?? [];
            foreach ($strategicAvatars as $avatar) {
                if (isset($avatar['name']) && $avatar['name'] === $avatarName) {
                    $strategicAvatarPath = $avatar['image'] ?? null;
                    break;
                }
            }
        }

        $totalQuestions = 10;
        $currentQuestion = 1;
        $theme = 'Culture générale';
        $themeDisplay = '🧠 Culture générale';
        $playerScore = 0;
        $opponentScore = 0;
        $currentUser = $user;

        return response()->view('duo_question', [
            'match_id' => $match->id,
            'match' => $match->load(['player1', 'player2']),

            'room_id' => $roomId,
            'lobby_code' => $lobbyCode,
            'jwt_token' => $jwtToken,
            'skills' => $skills,
            'strategic_avatar' => $strategicAvatar,
            'currentUser' => $currentUser,
            'avatarName' => $avatarName,
            'strategicAvatarPath' => $strategicAvatarPath,
            'playerAvatarPath' => $playerAvatarPath,
            'opponentAvatarPath' => $opponentAvatarPath,
            'opponentName' => $opponentName,
            'playerScore' => $playerScore,
            'opponentScore' => $opponentScore,
            'totalQuestions' => $totalQuestions,
            'currentQuestion' => $currentQuestion,
            'theme' => $theme,
            'themeDisplay' => $themeDisplay,
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    protected function getPlayerSkillsWithTriggers($user): array
    {
        $snapshot = $this->getPlayerSnapshot($user);
        $avatarName = $this->getSnapshotStrategicAvatarName($snapshot);

        if ($avatarName === 'Aucun' || empty($avatarName)) {
            return [];
        }

        $avatarData = \App\Services\AvatarSkillService::getAvatarSkills($avatarName, $user->id);
        $rawSkills  = $avatarData['skills'] ?? [];

        $skills = [];
        foreach ($rawSkills as $skillData) {
            if (!is_array($skillData) || empty($skillData['id'])) {
                continue;
            }
            $skills[] = [
                'id'            => $skillData['id'],
                'name'          => $skillData['name'] ?? '',
                'icon'          => $skillData['icon'] ?? '⭐',
                'description'   => $skillData['description'] ?? '',
                'trigger'       => $skillData['trigger'] ?? 'question',
                'type'          => $skillData['type'] ?? 'unknown',
                'auto'          => $skillData['auto'] ?? false,
                'uses_per_match'=> $skillData['uses_per_match'] ?? 1,
                'used'          => false,
            ];
        }

        return $skills;
    }

    public function activateSkill(Request $request, DuoMatch $match)
    {
        \Log::warning('[DEPRECATED] DuoController::activateSkill() called via HTTP. Skills must be activated via Socket.IO skill_activate event. This route will be removed in a future release.', [
            'user_id' => Auth::id(),
            'match_id' => $match->id,
            'skill_id' => $request->input('skill_id'),
            'ip' => $request->ip(),
        ]);

        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $skillId = $request->input('skill_id');
        $skillData = \App\Services\SkillCatalog::getSkill($skillId);

        if (!$skillData) {
            return response()->json(['success' => false, 'error' => 'Skill not found']);
        }

        return response()->json([
            'success' => true,
            'skill_id' => $skillId,
            'affects_opponent' => $skillData['affects_opponent'] ?? false,
        ]);
    }

    public function getHint(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $question = $request->input('question', '');

        if (empty($question)) {
            return response()->json(['hint' => 'Aucune question disponible']);
        }

        try {
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                return response()->json(['hint' => 'Réfléchissez bien à cette question...']);
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                'json' => [
                    'contents' => [[
                        'parts' => [[
                            'text' => "Donne un indice court (max 15 mots) pour aider à répondre à cette question de quiz sans donner la réponse directement: \"{$question}\""
                        ]]
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 50,
                    ]
                ],
                'timeout' => 5,
            ]);

            $result = json_decode($response->getBody(), true);
            $hint = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Réfléchissez bien...';

            return response()->json(['hint' => trim($hint)]);
        } catch (\Exception $e) {
            return response()->json(['hint' => 'Cherchez dans vos connaissances...']);
        }
    }

    public function getAISuggestion(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $question = $request->input('question', '');
        $answers = $request->input('answers', []);

        if (empty($question) || empty($answers)) {
            $randomIndex = rand(0, 3);
            return response()->json(['suggestion' => $randomIndex, 'confidence' => 50]);
        }

        try {
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                $randomIndex = rand(0, count($answers) - 1);
                return response()->json(['suggestion' => $randomIndex, 'confidence' => 60]);
            }

            $answersText = implode("\n", array_map(fn($a, $i) => ($i + 1) . ". " . $a, $answers, array_keys($answers)));

            $client = new \GuzzleHttp\Client();
            $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                'json' => [
                    'contents' => [[
                        'parts' => [[
                            'text' => "Question: {$question}\nRéponses possibles:\n{$answersText}\n\nRéponds UNIQUEMENT avec le numéro de la réponse la plus probable (1, 2, 3 ou 4). Rien d'autre."
                        ]]
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 10,
                    ]
                ],
                'timeout' => 5,
            ]);

            $result = json_decode($response->getBody(), true);
            $answerText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '1';
            $answerNum = (int) preg_replace('/[^0-9]/', '', $answerText);

            $suggestion = max(0, min(count($answers) - 1, $answerNum - 1));
            $confidence = rand(75, 90);

            return response()->json(['suggestion' => $suggestion, 'confidence' => $confidence]);
        } catch (\Exception $e) {
            $randomIndex = rand(0, count($answers) - 1);
            return response()->json(['suggestion' => $randomIndex, 'confidence' => 55]);
        }
    }

    public function getPreviewQuestions(Request $request, DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $gameState = $match->game_state;
        $currentQuestion = $gameState['question_number'] ?? 1;
        $allQuestions = $gameState['questions'] ?? [];

        $futureQuestions = [];
        for ($i = $currentQuestion; $i < min($currentQuestion + 5, count($allQuestions)); $i++) {
            if (isset($allQuestions[$i])) {
                $futureQuestions[] = [
                    'theme' => $allQuestions[$i]['theme'] ?? '',
                    'subtheme' => $allQuestions[$i]['subtheme'] ?? '',
                    'difficulty' => $allQuestions[$i]['difficulty'] ?? 'medium',
                ];
            }
        }

        if (empty($futureQuestions)) {
            $themes = ['Histoire', 'Sciences', 'Géographie', 'Sport', 'Culture'];
            for ($i = 0; $i < 5; $i++) {
                $futureQuestions[] = [
                    'theme' => $themes[array_rand($themes)],
                    'subtheme' => '',
                    'difficulty' => ['easy', 'medium', 'hard'][rand(0, 2)],
                ];
            }
        }

        return response()->json(['questions' => $futureQuestions]);
    }

    public function result(DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            abort(403, 'Unauthorized');
        }

        $gameState = $match->game_state;
        $matchResult = $this->gameStateService->getMatchResult($gameState);

        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $opponentDivision = $this->divisionService->getOrCreateDivision($opponent, 'duo');

        $this->contactService->registerMutualContacts($match->player1_id, $match->player2_id);

        $accuracy = 0;
        $total = ($gameState['global_stats']['correct'] ?? 0) + ($gameState['global_stats']['incorrect'] ?? 0);
        if ($total > 0) {
            $accuracy = round(($gameState['global_stats']['correct'] ?? 0) / $total * 100);
        }

        $pointsEarned = $isPlayer1 ? ($match->player1_points_earned ?? 0) : ($match->player2_points_earned ?? 0);
        $coinsEarned = $isPlayer1 ? ($match->player1_coins_earned ?? 0) : ($match->player2_coins_earned ?? 0);

        $myEfficiency = $division->initial_efficiency ?? 0;
        $oppEfficiency = $opponentDivision->initial_efficiency ?? 0;
        $opponentStrength = $this->divisionService->determineOpponentStrength(
            $division->division,
            $opponentDivision->division,
            $myEfficiency,
            $oppEfficiency
        );

        $baseCoins = $this->divisionService->getVictoryCoins($division->division);
        $coinsBonus = $coinsEarned - $baseCoins;

        $betInfo = $gameState['bet_info'] ?? null;
        $betWinnings = 0;
        if ($betInfo && ($betInfo['total_pot'] ?? 0) > 0) {
            $playerWon = $matchResult['player_won'] ?? false;
            $betWinnings = $playerWon ? $betInfo['total_pot'] : 0;
        }

        return view('duo_result', [
            'match_result' => $matchResult,
            'opponent' => $opponent,
            'opponent_id' => $opponent->id ?? null,
            'opponent_name' => $opponent->name ?? 'Adversaire',
            'new_division' => $division,
            'points_earned' => $pointsEarned,
            'coins_earned' => $coinsEarned,
            'coins_bonus' => $coinsBonus,
            'opponent_strength' => $opponentStrength,
            'global_stats' => $gameState['global_stats'] ?? [],
            'accuracy' => $accuracy,
            'round_details' => $gameState['answered_questions'] ?? [],
            'bet_info' => $betInfo,
            'bet_winnings' => $betWinnings,
        ]);
    }

    public function rankings(Request $request)
    {
        $user = Auth::user();
        $division = $request->input('division');

        if (!$division) {
            $userDivision = $this->divisionService->getOrCreateDivision($user, 'duo');
            $division = $userDivision->division;
        }

        $rankings = $this->divisionService->getRankingsForDivision('duo', $division);
        $myRank = $this->divisionService->getPlayerRank($user, 'duo');

        return view('duo_rankings', [
            'division' => $division,
            'rankings' => $rankings,
            'my_rank' => $myRank,
        ]);
    }

    public function startGame(Request $request)
    {
        $user = Auth::user();
        $gameState = session('game_state');
        $lobbyCode = $request->input('lobby_code');

        if ((!$gameState || !isset($gameState['match_id'])) && $lobbyCode) {
            $lobby = Cache::get('lobby:' . strtoupper($lobbyCode));

            if ($lobby) {
                $matchId = $lobby['settings']['match_id'] ?? ($lobby['match_id'] ?? null);

                if (!$matchId) {
                    $playerIds = array_keys($lobby['players'] ?? []);
                    if (count($playerIds) >= 2) {
                        $match = DuoMatch::where(function ($query) use ($playerIds) {
                            $query->where('player1_id', $playerIds[0])
                                  ->where('player2_id', $playerIds[1]);
                        })->orWhere(function ($query) use ($playerIds) {
                            $query->where('player1_id', $playerIds[1])
                                  ->where('player2_id', $playerIds[0]);
                        })
                        ->whereIn('status', ['pending', 'accepted', 'starting'])
                        ->orderBy('created_at', 'desc')
                        ->first();

                        if ($match) {
                            $matchId = $match->id;
                        }
                    }
                }

                if ($matchId) {
                    $gameServer = $lobby['game_server'] ?? [];
                    $roomId = $gameServer['roomId'] ?? null;
                    $freshJwtToken = $this->generateFreshGameplayToken($roomId, $user->id);

                    $gameState = [
                        'match_id' => $matchId,
                        'lobby_code' => $lobbyCode,
                        'theme' => $lobby['settings']['theme'] ?? 'Culture générale',
                        'nb_questions' => $lobby['settings']['nb_questions'] ?? 10,
                        'current_round' => 1,
                        'room_id' => $roomId,
                        'jwt_token' => $freshJwtToken,
                    ];
                    session(['game_state' => $gameState]);
                }
            }
        }

        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('duo.lobby')->with('error', __('Aucune partie en cours'));
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Match introuvable'));
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }

        $match->status = 'in_progress';
        $match->started_at = now();

        $lobbyCode = $request->input('lobby_code') ?: ($gameState['lobby_code'] ?? null);
        $lobby = null;
        $roomId = null;

        if ($lobbyCode) {
            $lobby = Cache::get('lobby:' . strtoupper($lobbyCode));
            if ($lobby && isset($lobby['game_server']['roomId'])) {
                $roomId = $lobby['game_server']['roomId'];
            }
        }

        if (!$roomId) {
            $roomId = $gameState['room_id'] ?? $match->room_id ?? null;
        }

        if (!$roomId) {
            return redirect()->route('duo.lobby')->with('error', __('Room introuvable pour cette partie'));
        }

        $jwtToken = $this->generateFreshGameplayToken($roomId, $user->id);

        $match->room_id = $roomId;
        $match->save();

        session(['game_state' => array_merge($gameState, [
            'started' => true,
            'started_at' => now()->timestamp,
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'lobby_code' => $lobbyCode,
        ])]);

        return redirect()->route('game.duo.intro');
    }

    public function showIntro()
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('duo.lobby')->with('error', __('Aucune partie en cours'));
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Match introuvable'));
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }

        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;

        $profileSettings = $user->profile_settings ?? [];
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        }

        $opponentSettings = $opponent->profile_settings ?? [];
        if (is_string($opponentSettings)) {
            $opponentSettings = json_decode($opponentSettings, true) ?? [];
        }

        $playerName = data_get($profileSettings, 'pseudonym', $user->name ?? 'Joueur');
        $playerAvatar = data_get($profileSettings, 'avatar.url', 'images/avatars/standard/default.png');

        $opponentName = data_get($opponentSettings, 'pseudonym', $opponent->name ?? 'Adversaire');
        $opponentAvatar = data_get($opponentSettings, 'avatar.url', 'images/avatars/standard/default.png');

        $playerStats = PlayerDuoStat::firstOrCreate(['user_id' => $user->id], ['level' => 0]);
        $opponentStats = PlayerDuoStat::firstOrCreate(['user_id' => $opponent->id], ['level' => 0]);

        $playerDivision = $this->divisionService->getOrCreateDivision($user, 'duo')->division ?? 'Bronze';
        $opponentDivision = $this->divisionService->getOrCreateDivision($opponent, 'duo')->division ?? 'Bronze';

        $theme = $gameState['theme'] ?? 'Culture générale';
        $nbQuestions = $gameState['nb_questions'] ?? 10;

        $roomId = $gameState['room_id'] ?? $match->room_id ?? null;
        $lobbyCode = $gameState['lobby_code'] ?? $match->lobby_code ?? null;
        $jwtToken = $this->generateFreshGameplayToken($roomId, $user->id);

        session(['game_state' => array_merge($gameState, [
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'lobby_code' => $lobbyCode,
        ])]);

        return view('game_intro', [
            'params' => [
                'mode' => 'duo',
                'theme' => $theme,
                'nb_questions' => $nbQuestions,
                'player_name' => $playerName,
                'player_avatar' => $playerAvatar,
                'opponent_name' => $opponentName,
                'opponent_avatar' => $opponentAvatar,
                'player_division' => $playerDivision,
                'opponent_division' => $opponentDivision,
                'redirect_url' => route('game.duo.question'),
                'match_id' => $match->id,
                'session_id' => $match->id,
                'opponent_id' => $opponent->id,
                'is_host' => $isPlayer1,
                'room_id' => $roomId,
                'lobby_code' => $lobbyCode,
                'jwt_token' => $jwtToken,
            ],

        ]);
    }

    public function showResume()
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('duo.lobby')->with('error', __('Aucune partie en cours'));
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Match introuvable'));
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }

        return $this->renderQuestionView($match, $user);
    }

    public function showQuestion()
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('duo.lobby')->with('error', __('Aucune partie en cours'));
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Match introuvable'));
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }

        $phaseRedirect = $this->validatePhaseAccess($match, $user, 'question');
        if ($phaseRedirect) {
            return $phaseRedirect;
        }

        return $this->renderQuestionView($match, $user);
    }

    public function showAnswer(Request $request)
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('duo.lobby')->with('error', __('Aucune partie en cours'));
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Match introuvable'));
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }

        $phaseRedirect = $this->validatePhaseAccess($match, $user, 'answer');
        if ($phaseRedirect) {
            return $phaseRedirect;
        }

        if ($request->has('buzzed') && $request->buzzed === 'true') {
            $gameState['buzz_winner'] = 'player';
        } elseif ($request->has('opponent_buzzed') && $request->opponent_buzzed === 'true') {
            $gameState['buzz_winner'] = 'opponent';
        } elseif ($request->has('timeout') && $request->timeout === 'true') {
            $gameState['buzz_winner'] = 'opponent';
        } else {
            $gameState['buzz_winner'] = $gameState['buzz_winner'] ?? 'opponent';
        }

        session(['game_state' => $gameState]);

        return $this->renderAnswerView($match, $user, $gameState);
    }

    public function showResult()
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('duo.lobby')->with('error', __('Aucune partie en cours'));
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Match introuvable'));
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }

        $phaseRedirect = $this->validatePhaseAccess($match, $user, 'result');
        if ($phaseRedirect) {
            return $phaseRedirect;
        }

        return $this->renderResultView($match, $user, $gameState);
    }

    public function fetchQuestionJson(Request $request)
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return response()->json(['success' => false, 'error' => 'No active game'], 400);
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            return response()->json(['success' => false, 'error' => 'Match not found'], 404);
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $matchGameState = $match->game_state ?? [];
        $currentQuestion = $matchGameState['current_question_number'] ?? 1;
        $questions = $matchGameState['questions'] ?? [];

        if ($currentQuestion > count($questions)) {
            return response()->json([
                'success' => false,
                'finished' => true,
                'message' => 'No more questions'
            ]);
        }

        $questionData = $questions[$currentQuestion - 1] ?? null;

        return response()->json([
            'success' => true,
            'question' => $questionData,
            'total_questions' => count($questions),
        ]);
    }

    public function showMatchResult()
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('duo.lobby')->with('error', __('Aucune partie en cours'));
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Match introuvable'));
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('duo.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }

        $matchGameState = $match->game_state ?? [];
        $matchResult = $this->gameStateService->getMatchResult($matchGameState);

        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $opponentDivision = $this->divisionService->getOrCreateDivision($opponent, 'duo');

        $this->contactService->registerMutualContacts($match->player1_id, $match->player2_id);

        $accuracy = 0;
        $total = ($matchGameState['global_stats']['correct'] ?? 0) + ($matchGameState['global_stats']['incorrect'] ?? 0);
        if ($total > 0) {
            $accuracy = round(($matchGameState['global_stats']['correct'] ?? 0) / $total * 100);
        }

        $pointsEarned = $isPlayer1 ? ($match->player1_points_earned ?? 0) : ($match->player2_points_earned ?? 0);
        $coinsEarned = $isPlayer1 ? ($match->player1_coins_earned ?? 0) : ($match->player2_coins_earned ?? 0);

        // Match efficiency: player_total_score / (2 × N) × 100 where N = total questions played
        $totalQuestionsPlayed = count($matchGameState['answered_questions'] ?? []);
        $playerTotalScore = $matchGameState['player_total_score'] ?? 0;
        $playerMatchEfficiency = 0;
        if ($totalQuestionsPlayed > 0) {
            $playerMatchEfficiency = max(0, min(100, round($playerTotalScore / (2 * $totalQuestionsPlayed) * 100)));
        }

        // Tiebreaker detection
        $isTiebreaker = ($matchResult['decided_by'] ?? 'rounds') === 'total_score';
        $tiebreakerWon = $isTiebreaker && ($matchResult['player_won'] ?? false);

        session()->forget('game_state');

        return view('duo_match_result', [
            'match_result' => $matchResult,
            'opponent' => $opponent,
            'opponent_id' => $opponent->id ?? null,
            'opponent_name' => $opponent->name ?? 'Adversaire',
            'new_division' => $division,
            'points_earned' => $pointsEarned,
            'coins_earned' => $coinsEarned,
            'coins_bonus' => 0,
            'opponent_strength' => 'equal',
            'global_stats' => $matchGameState['global_stats'] ?? [],
            'accuracy' => $accuracy,
            'round_details' => $matchGameState['answered_questions'] ?? [],
            'bet_info' => null,
            'bet_winnings' => 0,
            'player_match_efficiency' => $playerMatchEfficiency,
            'total_questions_played' => $totalQuestionsPlayed,
            'is_tiebreaker' => $isTiebreaker,
            'tiebreaker_won' => $tiebreakerWon,
        ]);
    }

    public function handleForfeit(Request $request)
    {
        $user = Auth::user();
        $gameState = session('game_state');

        if (!$gameState || !isset($gameState['match_id'])) {
            return response()->json(['success' => false, 'error' => 'No active game'], 400);
        }

        $match = DuoMatch::find($gameState['match_id']);

        if (!$match) {
            session()->forget('game_state');
            return response()->json(['success' => false, 'error' => 'Match not found'], 404);
        }

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $isPlayer1 = $match->player1_id == $user->id;
        $winnerId = $isPlayer1 ? $match->player2_id : $match->player1_id;

        $match->status = 'completed';
        $match->winner_id = $winnerId;
        $match->finished_at = now();
        $match->forfeit_by = $user->id;
        $match->save();

        session()->forget('game_state');

        return response()->json([
            'success' => true,
            'message' => __('Vous avez abandonné la partie'),
            'redirect_url' => route('duo.lobby'),
        ]);
    }

    protected function renderQuestionView(DuoMatch $match, $user)
    {
        $roomId = $match->room_id ?? null;
        $lobbyCode = $match->lobby_code ?? null;
        $gameState = session('game_state', []);

        $jwtToken = $this->generateFreshGameplayToken($roomId, $user->id);

        session(['game_state' => array_merge($gameState, [
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'lobby_code' => $lobbyCode,
        ])]);

        $playerSnapshot = $this->getPlayerSnapshot($user);
        $strategicAvatar = data_get($playerSnapshot, 'strategic_avatar', ['name' => 'Aucun']);

        $skills = $this->getPlayerSkillsWithTriggers($user);

        $opponent = $match->player1_id == $user->id ? $match->player2 : $match->player1;
        $opponentSnapshot = $this->getPlayerSnapshot($opponent);

        $opponentName = $this->getSnapshotDisplayName($opponentSnapshot, $opponent, 'Adversaire');
        $opponentAvatarPath = $this->getSnapshotAvatarPath($opponentSnapshot);

        $playerAvatarPath = $this->getSnapshotAvatarPath($playerSnapshot);
        $playerName = $this->getSnapshotDisplayName($playerSnapshot, $user, 'Joueur');

        $avatarName = $this->getSnapshotStrategicAvatarName($playerSnapshot);
        $strategicAvatarPath = $this->getSnapshotStrategicAvatarPath($playerSnapshot);

        $totalQuestions = 10;
        $currentQuestion = 1;
        $theme = 'Culture générale';
        $themeDisplay = '🧠 Culture générale';
        $playerScore = 0;
        $opponentScore = 0;

        return response()->view('duo_question', [
            'match_id' => $match->id,
            'match' => $match->load(['player1', 'player2']),
            'room_id' => $roomId,
            'lobby_code' => $lobbyCode,
            'jwt_token' => $jwtToken,
            'skills' => $skills,
            'strategic_avatar' => $strategicAvatar,
            'currentUser' => $user,
            'playerName' => $playerName,
            'avatarName' => $avatarName,
            'strategicAvatarPath' => $strategicAvatarPath,
            'playerAvatarPath' => $playerAvatarPath,
            'opponentAvatarPath' => $opponentAvatarPath,
            'opponentName' => $opponentName,
            'playerScore' => $playerScore,
            'opponentScore' => $opponentScore,
            'totalQuestions' => $totalQuestions,
            'currentQuestion' => $currentQuestion,
            'theme' => $theme,
            'themeDisplay' => $themeDisplay,
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    protected function renderAnswerView(DuoMatch $match, $user, array $gameState)
    {
        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;

        $playerSnapshot = $this->getPlayerSnapshot($user);
        $opponentSnapshot = $this->getPlayerSnapshot($opponent);

        $matchGameState = $match->game_state ?? [];
        $currentQuestionNumber = $matchGameState['current_question_number'] ?? 1;

        $playerScore = $isPlayer1
            ? ($matchGameState['player_scores_map']['player'] ?? 0)
            : ($matchGameState['player_scores_map']['opponent'] ?? 0);
        $opponentScore = $isPlayer1
            ? ($matchGameState['player_scores_map']['opponent'] ?? 0)
            : ($matchGameState['player_scores_map']['player'] ?? 0);

        $stats = PlayerDuoStat::firstOrCreate(['user_id' => $user->id], ['level' => 0]);
        $opponentStats = PlayerDuoStat::firstOrCreate(['user_id' => $opponent->id], ['level' => 0]);

        $questionData = $gameState['current_question'] ?? [];

        if (empty($questionData) || empty($questionData['text'])) {
            $questions = $matchGameState['questions'] ?? [];
            $questionData = $questions[$currentQuestionNumber - 1] ?? [];
        }

        // Final fallback: read live question from game-server Redis room state
        if (empty($questionData) || empty($questionData['choices'])) {
            $roomId = $gameState['room_id'] ?? $match->room_id ?? null;
            if ($roomId) {
                try {
                    $rawState = \Illuminate\Support\Facades\Redis::connection('game_server')
                        ->get("room:{$roomId}:state");
                    if ($rawState) {
                        $roomState = json_decode($rawState, true);
                        $qIdx      = $roomState['questionIndex'] ?? 0;
                        $questions = $roomState['questions'] ?? [];
                        if (isset($questions[$qIdx])) {
                            $q = $questions[$qIdx];
                            $questionData = [
                                'text'    => $q['text'] ?? ($questionData['text'] ?? ''),
                                'choices' => $q['choices'] ?? $q['answers'] ?? [],
                                // Never expose correct_answer index before reveal
                                'correct_answer' => null,
                                'correct_index'  => null,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('[DUO-ANSWER] Redis question fallback failed', ['error' => $e->getMessage()]);
                }
            }
        }

        if (!isset($questionData['choices']) && isset($questionData['answers'])) {
            $questionData['choices'] = $questionData['answers'];
        }

        // Filtrer les valeurs null dans les choix (questions true_false ont ['Vrai', null, 'Faux', null])
        if (isset($questionData['choices']) && is_array($questionData['choices'])) {
            $originalChoices = $questionData['choices'];
            $originalCorrectIndex = $questionData['correct_answer'] ?? $questionData['correct_index'] ?? null;
            $filteredChoices = [];
            $newCorrectIndex = $originalCorrectIndex;
            $newIdx = 0;
            foreach ($originalChoices as $oldIdx => $choice) {
                if ($choice !== null && $choice !== '') {
                    if ($oldIdx === $originalCorrectIndex) {
                        $newCorrectIndex = $newIdx;
                    }
                    $filteredChoices[] = $choice;
                    $newIdx++;
                }
            }
            $questionData['choices'] = $filteredChoices;
            if (isset($questionData['correct_answer'])) {
                $questionData['correct_answer'] = $newCorrectIndex;
            }
            if (isset($questionData['correct_index'])) {
                $questionData['correct_index'] = $newCorrectIndex;
            }
        }

        $buzzWinner = $gameState['buzz_winner'] ?? 'player';
        $buzzTime = $gameState['buzz_time'] ?? 0;
        $noBuzz = $gameState['no_buzz'] ?? false;

        $roomId = $gameState['room_id'] ?? $match->room_id ?? null;
        $lobbyCode = $gameState['lobby_code'] ?? $match->lobby_code ?? null;
        $jwtToken = $this->generateFreshGameplayToken($roomId, $user->id);

        session(['game_state' => array_merge($gameState, [
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'lobby_code' => $lobbyCode,
        ])]);

        $strategicAvatar = data_get($playerSnapshot, 'strategic_avatar', ['name' => 'Aucun']);
        $skills = $this->getPlayerSkillsWithTriggers($user);
        $avatarName = $this->getSnapshotStrategicAvatarName($playerSnapshot);
        $strategicAvatarPath = $this->getSnapshotStrategicAvatarPath($playerSnapshot);

        $playerAvatar = $this->getSnapshotAvatarPath($playerSnapshot);
        $opponentAvatar = $this->getSnapshotAvatarPath($opponentSnapshot);

        $opponentName = $this->getSnapshotDisplayName($opponentSnapshot, $opponent, 'Adversaire');
        $playerName = $this->getSnapshotDisplayName($playerSnapshot, $user, 'Joueur');

        $skillsKey = "duo_skills_{$match->id}";
        $matchSkills = session($skillsKey, [
            'used_skills' => [],
            'reduce_time_active' => false,
            'reduce_time_questions_left' => 0,
            'shuffle_answers_active' => false,
            'shuffle_answers_questions_left' => 0,
            'last_question_decremented' => 0,
        ]);

        if ($matchSkills['last_question_decremented'] < $currentQuestionNumber) {
            if ($matchSkills['shuffle_answers_active'] && $matchSkills['shuffle_answers_questions_left'] > 0) {
                $matchSkills['shuffle_answers_questions_left']--;
                if ($matchSkills['shuffle_answers_questions_left'] <= 0) {
                    $matchSkills['shuffle_answers_active'] = false;
                    \Log::info('[DUO-CHALLENGER] Skill shuffle_answers épuisé', ['match_id' => $match->id]);
                }
            }

            if ($matchSkills['reduce_time_active'] && $matchSkills['reduce_time_questions_left'] > 0) {
                $matchSkills['reduce_time_questions_left']--;
                if ($matchSkills['reduce_time_questions_left'] <= 0) {
                    $matchSkills['reduce_time_active'] = false;
                    \Log::info('[DUO-CHALLENGER] Skill reduce_time épuisé', ['match_id' => $match->id]);
                }
            }

            $matchSkills['last_question_decremented'] = $currentQuestionNumber;
            session([$skillsKey => $matchSkills]);
        }

        $shuffleAnswersActive = $matchSkills['shuffle_answers_active'];
        $shuffleQuestionsLeft = $matchSkills['shuffle_answers_questions_left'];

        return view('duo_answer', [
            'match_id' => $match->id,
            'room_id' => $roomId,
            'lobby_code' => $lobbyCode,
            'jwt_token' => $jwtToken,
            'question' => $questionData,
            'buzz_winner' => $buzzWinner,
            'buzz_time' => $buzzTime,
            'no_buzz' => $noBuzz,
            'playerScore' => $playerScore,
            'opponentScore' => $opponentScore,
            'currentQuestion' => $currentQuestionNumber,
            'totalQuestions' => 10,
            'skills' => $skills,
            'avatarName' => $avatarName,
            'strategicAvatarPath' => $strategicAvatarPath,
            'playerAvatarPath' => $playerAvatar,
            'opponentAvatarPath' => $opponentAvatar,
            'playerName' => $playerName,
            'opponentName' => $opponentName,
            'shuffleAnswersActive' => $shuffleAnswersActive,
            'shuffleQuestionsLeft' => $shuffleQuestionsLeft,
            'player_info' => [
                'id' => $user->id,
                'name' => $this->getSnapshotDisplayName($playerSnapshot, $user, 'Joueur'),
                'avatar' => $playerAvatar,
                'score' => $playerScore,
                'level' => $stats->level,
            ],
            'opponent_info' => [
                'id' => $opponent->id,
                'name' => $opponentName,
                'avatar' => $opponentAvatar,
                'score' => $opponentScore,
                'level' => $opponentStats->level,
            ],
        ]);
    }

    protected function renderResultView(DuoMatch $match, $user, array $gameState)
    {
        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;

        $playerSnapshot = $this->getPlayerSnapshot($user);
        $opponentSnapshot = $this->getPlayerSnapshot($opponent);

        $matchGameState = $match->game_state ?? [];
        $currentQuestion = $matchGameState['current_question_number'] ?? 1;

        $playerScore = $isPlayer1
            ? ($matchGameState['player_scores_map']['player'] ?? 0)
            : ($matchGameState['player_scores_map']['opponent'] ?? 0);
        $opponentScore = $isPlayer1
            ? ($matchGameState['player_scores_map']['opponent'] ?? 0)
            : ($matchGameState['player_scores_map']['player'] ?? 0);

        $stats = PlayerDuoStat::firstOrCreate(['user_id' => $user->id], ['level' => 0]);
        $opponentStats = PlayerDuoStat::firstOrCreate(['user_id' => $opponent->id], ['level' => 0]);

        $lastAnswer = $gameState['last_answer'] ?? [];
        $isCorrect = $lastAnswer['is_correct'] ?? false;
        $pointsEarned = $lastAnswer['points'] ?? 0;

        $roomId = $gameState['room_id'] ?? $match->room_id ?? null;
        $lobbyCode = $gameState['lobby_code'] ?? $match->lobby_code ?? null;
        $jwtToken = $this->generateFreshGameplayToken($roomId, $user->id);

        session(['game_state' => array_merge($gameState, [
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'lobby_code' => $lobbyCode,
        ])]);

        // Read current question from Redis game-server room state to get fun_fact
        $resultQuestion = $gameState['last_question'] ?? [];
        if ($roomId && empty($resultQuestion['fun_fact'])) {
            try {
                $rawState = \Illuminate\Support\Facades\Redis::connection('game_server')
                    ->get("room:{$roomId}:state");
                if ($rawState) {
                    $roomState = json_decode($rawState, true);
                    $qIdx = $roomState['questionIndex'] ?? 0;
                    $questions = $roomState['questions'] ?? [];
                    if (isset($questions[$qIdx])) {
                        $q = $questions[$qIdx];
                        $resultQuestion = [
                            'text'     => $q['text'] ?? '',
                            'fun_fact' => $q['funFact'] ?? $q['fun_fact'] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('[DUO-RESULT] Redis question fallback failed', ['error' => $e->getMessage()]);
            }
        }

        $strategicAvatar = data_get($playerSnapshot, 'strategic_avatar', ['name' => 'Aucun']);
        $skills = $this->getPlayerSkillsWithTriggers($user);
        $avatarName = $this->getSnapshotStrategicAvatarName($playerSnapshot);
        $strategicAvatarPath = $this->getSnapshotStrategicAvatarPath($playerSnapshot);

        $playerAvatar = $this->getSnapshotAvatarPath($playerSnapshot);
        $opponentAvatar = $this->getSnapshotAvatarPath($opponentSnapshot);

        $playerBuzzed = $gameState['last_player_buzzed'] ?? false;

        return view('duo_result', [
            'match_id' => $match->id,
            'room_id' => $roomId,
            'lobby_code' => $lobbyCode,
            'jwt_token' => $jwtToken,
            'is_correct' => $isCorrect,
            'wasCorrect' => $isCorrect,
            'points_earned' => $pointsEarned,
            'pointsEarned' => $pointsEarned,
            'playerBuzzed' => $playerBuzzed,
            'playerPoints' => $pointsEarned,
            'player_score' => $playerScore,
            'opponent_score' => $opponentScore,
            'currentQuestion' => $currentQuestion,
            'total_questions' => 10,
            'skills' => $skills,
            'avatarName' => $avatarName,
            'strategicAvatarPath' => $strategicAvatarPath,
            'playerAvatarPath' => $playerAvatar,
            'opponentAvatarPath' => $opponentAvatar,
            'opponentName' => $this->getSnapshotDisplayName($opponentSnapshot, $opponent, 'Adversaire'),
            'question' => $resultQuestion,
            'correct_answer' => $lastAnswer['correct_answer'] ?? '',
            'player_answer' => $lastAnswer['player_answer'] ?? '',
            'playerAnswer' => $lastAnswer['player_answer'] ?? '',
            'correctAnswer' => $lastAnswer['correct_answer'] ?? '',
            'player_info' => [
                'id' => $user->id,
                'name' => $this->getSnapshotDisplayName($playerSnapshot, $user, 'Joueur'),
                'avatar' => $playerAvatar,
                'score' => $playerScore,
                'level' => $stats->level,
            ],
            'opponent_info' => [
                'id' => $opponent->id,
                'name' => $this->getSnapshotDisplayName($opponentSnapshot, $opponent, 'Adversaire'),
                'avatar' => $opponentAvatar,
                'score' => $opponentScore,
                'level' => $opponentStats->level,
            ],
        ]);
    }

    public function question(DuoMatch $match)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            abort(403, 'Unauthorized');
        }

        return $this->renderQuestionView($match, $user);
    }

    public function answer(DuoMatch $match, Request $request)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            abort(403, 'Unauthorized');
        }


        $currentQuestion = $request->input('question_number', $match->game_state['current_question_number'] ?? 1);

        $playerBuzzed = $request->input('buzzed', false);
        $opponentBuzzed = $request->input('opponent_buzzed', false);
        $timeout = $request->input('timeout', false);


        $questionData = $request->input('question', []);
        $questionText = $questionData['text'] ?? '';
        $answers = $questionData['answers'] ?? [];
        $correctIndex = $questionData['correct_index'] ?? 0;

        return $this->renderAnswerView($match, $user, [
            'current_question_number' => $currentQuestion,
            'current_question' => [
                'text' => $questionText,
                'answers' => $answers,
                'choices' => $answers,
                'correct_index' => $correctIndex,
            ],
            'buzz_winner' => $playerBuzzed ? 'player' : (($timeout || !$playerBuzzed) ? 'opponent' : 'opponent'),
            'buzz_time' => 0,
            'no_buzz' => (bool) $timeout,
        ]);
    }

    public function waiting(DuoMatch $match, Request $request)
    {
        $user = Auth::user();

        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            abort(403, 'Unauthorized');
        }

        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;

        $profileSettings = $user->profile_settings ?? [];
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        }

        $opponentSettings = $opponent->profile_settings ?? [];
        if (is_string($opponentSettings)) {
            $opponentSettings = json_decode($opponentSettings, true) ?? [];
        }

        $gameState = $match->game_state ?? [];
        $currentQuestion = $request->input('current_question', $gameState['current_question_number'] ?? 1);

        $playerScore = $isPlayer1
            ? ($gameState['player_scores_map']['player'] ?? 0)
            : ($gameState['player_scores_map']['opponent'] ?? 0);
        $opponentScore = $isPlayer1
            ? ($gameState['player_scores_map']['opponent'] ?? 0)
            : ($gameState['player_scores_map']['player'] ?? 0);

        $stats = PlayerDuoStat::firstOrCreate(['user_id' => $user->id], ['level' => 0]);
        $opponentStats = PlayerDuoStat::firstOrCreate(['user_id' => $opponent->id], ['level' => 0]);

        $nextQuestionNumber = $currentQuestion + 1;
        if ($nextQuestionNumber > 10) {
            $nextQuestionNumber = 10;
        }

        $playerBuzzed = $gameState['last_player_buzzed'] ?? false;
        $buzzOrder = $gameState['last_buzz_order'] ?? 0;
        $opponentFaster = ($buzzOrder === 2);
        $playerPoints = $gameState['last_points_earned'] ?? 0;
        $jwtToken = $this->generateFreshGameplayToken($match->room_id ?? null, $user->id);

        return view('duo_waiting', [
            'match_id' => $match->id,
            'room_id' => $match->room_id ?? null,
            'lobby_code' => $match->lobby_code ?? null,
            'jwt_token' => $jwtToken,
            'playerId' => $user->id,
            'playerName' => data_get($profileSettings, 'pseudonym', $user->name ?? 'Joueur'),
            'playerAvatarPath' => asset(data_get($profileSettings, 'avatar.url', 'images/avatars/standard/standard1.png')),
            'opponentId' => $opponent->id,
            'opponentName' => data_get($opponentSettings, 'pseudonym', $opponent->name ?? 'Adversaire'),
            'opponentAvatarPath' => asset(data_get($opponentSettings, 'avatar.url', 'images/avatars/standard/standard1.png')),
            'playerScore' => $playerScore,
            'opponentScore' => $opponentScore,
            'currentQuestion' => $currentQuestion,
            'totalQuestions' => $gameState['total_questions'] ?? 10,
            'currentRound' => $gameState['current_round'] ?? 1,
            'wasCorrect' => $gameState['last_was_correct'] ?? false,
            'pointsEarned' => $gameState['last_points_earned'] ?? 0,
            'playerAnswer' => $gameState['last_player_answer'] ?? null,
            'question' => $gameState['last_question'] ?? [],
            'skills' => $gameState['player_skills'] ?? [],
            'avatarName' => $gameState['avatar_name'] ?? '',
            'playerBuzzed' => $playerBuzzed,
            'playerPoints' => $playerPoints,
            'opponentFaster' => $opponentFaster,
        ]);
    }

    public function getInvitations()
    {
        $user = Auth::user();

        $receivedInvitations = DuoMatch::where('player2_id', $user->id)
            ->where('status', 'waiting')
            ->with('player1')
            ->get()
            ->map(function ($match) {
                $player = $match->player1;
                return [
                    'match_id'    => $match->id,
                    'id'          => $match->id,
                    'status'      => $match->status,
                    'lobby_code'  => $match->lobby_code,
                    'room_id'     => $match->room_id,
                    'from_player' => $player ? [
                        'id'          => $player->id,
                        'name'        => $player->name,
                        'player_code' => $player->player_code ?? null,
                        'avatar_url'  => $player->avatar_url ?? null,
                    ] : [
                        'id'          => null,
                        'name'        => 'Joueur inconnu',
                        'player_code' => null,
                        'avatar_url'  => null,
                    ],
                ];
            })
            ->values();

        $sentInvitations = DuoMatch::where('player1_id', $user->id)
            ->where('status', 'waiting')
            ->with('player2')
            ->get()
            ->map(function ($match) {
                $player = $match->player2;
                return [
                    'match_id'   => $match->id,
                    'id'         => $match->id,
                    'status'     => $match->status,
                    'lobby_code' => $match->lobby_code,
                    'room_id'    => $match->room_id,
                    'to_player'  => $player ? [
                        'id'          => $player->id,
                        'name'        => $player->name,
                        'player_code' => $player->player_code ?? null,
                        'avatar_url'  => $player->avatar_url ?? null,
                    ] : [
                        'id'          => null,
                        'name'        => 'Joueur inconnu',
                        'player_code' => null,
                        'avatar_url'  => null,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'invitations' => $receivedInvitations,
            'received_invitations' => $receivedInvitations,
            'sent_invitations' => $sentInvitations,
            'pending_invitations' => $sentInvitations,
            'counts' => [
                'received' => $receivedInvitations->count(),
                'sent' => $sentInvitations->count(),
            ],
        ]);
    }

    public function getContacts()
    {
        $user = Auth::user();
        $contacts = $this->contactService->getContacts($user->id);

        return response()->json([
            'success' => true,
            'contacts' => $contacts,
        ]);
    }

    public function deleteContact(int $contactId)
    {
        $user = Auth::user();
        $success = $this->contactService->deleteContact($user->id, $contactId);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => __('Contact introuvable'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('Contact supprimé'),
        ]);
    }

    public function contactLookup(string $playerCode)
    {
        $user   = Auth::user();
        $player = $this->contactService->lookupByCode($user->id, $playerCode);

        if (!$player) {
            return response()->json([
                'success' => false,
                'message' => __('Joueur introuvable'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'player'  => $player,
        ]);
    }

    public function addContact(Request $request)
    {
        $request->validate([
            'player_code'         => 'required|string|max:20',
            'display_name_choice' => 'nullable|string|in:name,code',
            'display_id_choice'   => 'nullable|string|in:code,id',
        ]);

        $user    = Auth::user();
        $contact = $this->contactService->addContactByCode(
            $user->id,
            $request->player_code,
            $request->input('display_name_choice', 'name'),
            $request->input('display_id_choice', 'code')
        );

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => __('Joueur introuvable'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'contact' => $contact,
            'message' => __('Contact ajouté au carnet !'),
        ]);
    }

    public function joinQueue(Request $request)
    {
        $request->validate([
            'target_division' => 'required|string|in:bronze,argent,or,platine,diamant,legende',
        ]);

        $user = Auth::user();
        $targetDivision = $request->target_division;

        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $currentDivision = $division->division ?? 'bronze';

        $divisions = ['bronze', 'argent', 'or', 'platine', 'diamant', 'legende'];
        $divisionFees = [
            'bronze' => 0,
            'argent' => 0,
            'or' => 50,
            'platine' => 100,
            'diamant' => 200,
            'legende' => 500
        ];

        $currentIndex = array_search($currentDivision, $divisions);
        $targetIndex = array_search($targetDivision, $divisions);

        if ($targetIndex < $currentIndex) {
            return response()->json([
                'success' => false,
                'message' => __('Vous ne pouvez pas jouer dans une division inférieure'),
            ], 400);
        }

        if ($targetIndex > $currentIndex + 2) {
            return response()->json([
                'success' => false,
                'message' => __('Vous ne pouvez pas jouer plus de 2 divisions au-dessus de la vôtre'),
            ], 400);
        }

        $entryFee = 0;
        if ($targetIndex > $currentIndex) {
            $entryFee = $divisionFees[$targetDivision];
        }

        if ($entryFee > 0 && $user->coins < $entryFee) {
            return response()->json([
                'success' => false,
                'message' => __('Vous n\'avez pas assez de pièces pour cette division. Il vous faut :amount pièces.', ['amount' => $entryFee]),
            ], 400);
        }

        $stats = PlayerDuoStat::where('user_id', $user->id)->first();
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url ?? '',
            'division' => $currentDivision,
            'target_division' => $targetDivision,
            'level' => $stats->level ?? 1,
            'efficiency' => $division->initial_efficiency ?? 0,
            'matches_won' => $stats->matches_won ?? 0,
            'matches_lost' => $stats->matches_lost ?? 0,
            'timestamp' => now()->timestamp,
        ];

        Cache::put("duo_queue:{$user->id}", $data, 300);

        $queueIndex = Cache::get('duo_queue_index', []);
        $queueIndex[$user->id] = $targetDivision;
        Cache::put('duo_queue_index', $queueIndex, 300);

        return response()->json([
            'success' => true,
            'message' => __('Prêt à rejoindre la file'),
            'entry_fee' => $entryFee,
        ]);
    }

    public function leaveQueue(Request $request)
    {
        $user = Auth::user();

        Cache::forget("duo_queue:{$user->id}");

        $queueIndex = Cache::get('duo_queue_index', []);
        if (isset($queueIndex[$user->id])) {
            unset($queueIndex[$user->id]);
            Cache::put('duo_queue_index', $queueIndex, 300);
        }

        return response()->json([
            'success' => true,
            'message' => __('Vous avez quitté la file d\'attente'),
        ]);
    }

    public function getQueueOpponents(Request $request)
    {
        $request->validate([
            'target_division' => 'required|string|in:bronze,argent,or,platine,diamant,legende',
        ]);

        $user = Auth::user();
        $targetDivision = $request->target_division;

        $queueIndex = Cache::get('duo_queue_index', []);
        $now = now()->timestamp;
        $userStats = PlayerDuoStat::where('user_id', $user->id)->first();
        $userLevel = $userStats->level ?? 1;

        $opponents = [];
        foreach ($queueIndex as $userId => $division) {
            if ($userId == $user->id) {
                continue;
            }
            if ($division !== $targetDivision) {
                continue;
            }

            $entry = Cache::get("duo_queue:{$userId}");
            if (!$entry) {
                continue;
            }

            if (($now - ($entry['timestamp'] ?? 0)) >= 120) {
                continue;
            }

            $levelDiff = abs(($entry['level'] ?? 1) - $userLevel);
            if ($levelDiff <= 10) {
                $opponents[] = $entry;
            }
        }

        usort($opponents, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));

        return response()->json([
            'success' => true,
            'opponents' => array_slice($opponents, 0, 5),
            'queue_size' => count($opponents),
        ]);
    }

    public function createQueueMatch(Request $request)
    {
        $request->validate([
            'opponent_id' => 'required|integer|exists:users,id',
            'target_division' => 'required|string|in:bronze,argent,or,platine,diamant,legende',
        ]);

        $user = Auth::user();
        $opponentId = $request->opponent_id;
        $targetDivision = $request->target_division;

        $opponent = User::find($opponentId);
        if (!$opponent) {
            return response()->json([
                'success' => false,
                'message' => __('Adversaire introuvable'),
            ], 404);
        }

        if ($opponentId === $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('Vous ne pouvez pas jouer contre vous-même'),
            ], 400);
        }

        $userStats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $user->id],
            ['level' => 0]
        );
        $opponentStats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $opponentId],
            ['level' => 0]
        );

        $levelDiff = abs($userStats->level - $opponentStats->level);
        if ($levelDiff > 10) {
            return response()->json([
                'success' => false,
                'message' => __('La différence de niveau est trop grande (max ±10). Votre niveau: :user, Adversaire: :opponent', [
                    'user' => $userStats->level,
                    'opponent' => $opponentStats->level
                ]),
            ], 400);
        }

        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $currentDivision = $division->division ?? 'bronze';

        $divisions = ['bronze', 'argent', 'or', 'platine', 'diamant', 'legende'];
        $divisionFees = [
            'bronze' => 0,
            'argent' => 0,
            'or' => 50,
            'platine' => 100,
            'diamant' => 200,
            'legende' => 500
        ];

        $currentIndex = array_search($currentDivision, $divisions);
        $targetIndex = array_search($targetDivision, $divisions);

        if ($targetIndex < $currentIndex) {
            return response()->json([
                'success' => false,
                'message' => __('Vous ne pouvez pas jouer dans une division inférieure à la vôtre.'),
            ], 400);
        }

        if ($targetIndex > $currentIndex + 2) {
            return response()->json([
                'success' => false,
                'message' => __('Vous ne pouvez jouer que jusqu\'à 2 divisions au-dessus de la vôtre.'),
            ], 400);
        }

        $entryFee = 0;
        if ($targetIndex > $currentIndex) {
            $entryFee = $divisionFees[$targetDivision];
        }

        if ($entryFee > 0) {
            if ($user->coins < $entryFee) {
                return response()->json([
                    'success' => false,
                    'message' => __('Vous n\'avez pas assez de pièces. Il vous faut :amount pièces.', ['amount' => $entryFee]),
                ], 400);
            }
            $user->coins -= $entryFee;
            $user->save();
        }

        $match = DuoMatch::create([
            'player1_id' => $user->id,
            'player2_id' => $opponentId,
            'status' => 'accepted',
            'is_random_match' => true,
            'division' => $targetDivision,
        ]);

        $lobby = $this->lobbyService->createLobby($user, 'duo', [
            'theme' => __('Culture générale'),
            'nb_questions' => 10,
            'match_id' => $match->id,
        ]);

        $match->lobby_code = $lobby['code'];
        $match->save();

        $this->contactService->registerMutualContacts($user->id, $opponentId);

        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
            'lobby_code' => $lobby['code'],
            'redirect_url' => route('lobby.show', ['code' => $lobby['code']]),
            'entry_fee_deducted' => $entryFee,
        ]);
    }

    public function createGameServerRoom(Request $request)
    {
        $request->validate([
            'match_id' => 'required|integer|exists:duo_matches,id',
            'mode' => 'nullable|string',
            'config' => 'nullable|array',
        ]);

        $user = Auth::user();
        $match = DuoMatch::findOrFail($request->match_id);

        if ($match->player1_id !== $user->id && $match->player2_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('Vous n\'êtes pas autorisé à créer une salle pour ce match.'),
            ], 403);
        }

        $mode = $request->input('mode', 'duo');
        $config = $request->input('config', []);

        $roomResult = $this->gameServerService->createRoom($mode, $user->id, $config);

        if (!isset($roomResult['roomId']) && !isset($roomResult['room_id'])) {
            return response()->json([
                'success' => false,
                'message' => $roomResult['error'] ?? __('Échec de la création de la salle de jeu.'),
            ], 500);
        }

        $roomId = $roomResult['roomId'] ?? $roomResult['room_id'];
        $lobbyCode = $roomResult['lobbyCode'] ?? $roomResult['lobby_code'] ?? $match->lobby_code;

        $match->room_id = $roomId;
        if ($lobbyCode && !$match->lobby_code) {
            $match->lobby_code = $lobbyCode;
        }
        $match->save();

        $playerToken = $this->generateFreshGameplayToken($roomId, $user->id);

        $socketUrl = $this->gameServerService->getSocketUrl();

        return response()->json([
            'success' => true,
            'room_id' => $roomId,
            'lobby_code' => $match->lobby_code,
            'socket_url' => $socketUrl,
            'player_token' => $playerToken,
            'match' => $match->load(['player1', 'player2']),
        ]);
    }

    private function validatePhaseAccess(DuoMatch $match, $user, string $expectedPage): ?\Illuminate\Http\RedirectResponse
    {
        $roomId = $match->room_id;

        if (!$roomId) {
            return null;
        }

        try {
            $roomData = $this->gameServerService->getRoom($roomId);

            if (!$roomData || !isset($roomData['state'])) {
                return null;
            }

            $state = $roomData['state'] ?? [];
            $currentPhase = $state['phase'] ?? null;
            $lockedAnswerPlayerId = $state['lockedAnswerPlayerId'] ?? null;

            if (!$currentPhase) {
                return null;
            }

            $questionPhases = ['INTRO', 'QUESTION_ACTIVE', 'WAITING'];
            $answerPhases = ['ANSWER_SELECTION'];
            $resultPhases = ['REVEAL'];
            $terminalPhases = ['ROUND_SCOREBOARD', 'MATCH_END', 'FINISHED'];

            switch ($expectedPage) {
                case 'question':
                    if (in_array($currentPhase, $answerPhases)) {
                        $playerId = (string) $user->id;
                        if ($lockedAnswerPlayerId === $playerId) {
                            return redirect()->route('game.duo.answer');
                        }
                    }
                    // Allow REVEAL phase on the question page: the result page navigates
                    // here 2s early so the next question loads while the reveal is finishing.
                    // JavaScript handles the waiting overlay for REVEAL state.
                    if (in_array($currentPhase, $terminalPhases)) {
                        return redirect()->route('game.duo.match-result');
                    }
                    break;

                case 'answer':
                    // Accept QUESTION_ACTIVE: buzz just registered, server transition in progress
                    // Accept WAITING: both players valid on answer page during transition
                    // Accept ANSWER_SELECTION: normal answer phase
                    // QUESTION_ACTIVE and WAITING are added so a buzz navigating to answer
                    // is never bounced back to question before the phase transitions
                    $acceptedForAnswer = array_merge($answerPhases, ['QUESTION_ACTIVE', 'WAITING']);
                    if (!in_array($currentPhase, $acceptedForAnswer)) {
                        // INTRO is the only remaining question phase (QUESTION_ACTIVE/WAITING accepted above)
                        if (in_array($currentPhase, $questionPhases)) {
                            return redirect()->route('game.duo.question');
                        }
                        if (in_array($currentPhase, $resultPhases)) {
                            return redirect()->route('game.duo.result');
                        }
                        if (in_array($currentPhase, $terminalPhases)) {
                            return redirect()->route('game.duo.match-result');
                        }
                    }
                    break;

                case 'result':
                    // ROUND_SCOREBOARD and REVEAL are both valid on the result/scoreboard page.
                    $validForResult = array_merge($resultPhases, ['ROUND_SCOREBOARD']);
                    if (!in_array($currentPhase, $validForResult)) {
                        if (in_array($currentPhase, $questionPhases)) {
                            return redirect()->route('game.duo.question');
                        }
                        if (in_array($currentPhase, $answerPhases)) {
                            $playerId = (string) $user->id;
                            if ($lockedAnswerPlayerId === $playerId) {
                                return redirect()->route('game.duo.answer');
                            }
                            return redirect()->route('game.duo.question');
                        }
                        // Only MATCH_END / FINISHED redirect to final results — not ROUND_SCOREBOARD.
                        if (in_array($currentPhase, ['MATCH_END', 'FINISHED'])) {
                            return redirect()->route('game.duo.match-result');
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Phase validation failed', [
                'match_id' => $match->id,
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function useSkill(Request $request)
    {
        \Log::warning('[DEPRECATED] DuoController::useSkill() called via HTTP. Skills must be activated via Socket.IO skill_activate event. This route will be removed in a future release.', [
            'user_id' => Auth::id(),
            'skill_id' => $request->input('skill_id'),
            'ip' => $request->ip(),
        ]);

        $user = Auth::user();
        $skillId = $request->input('skill_id');

        $gameState = session('game_state');
        if (!$gameState || !isset($gameState['match_id'])) {
            return response()->json([
                'success' => false,
                'message' => __('Aucune partie en cours'),
            ]);
        }

        $matchId = $gameState['match_id'];

        $profileSettings = $user->profile_settings;
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true);
        }

        $avatarName = $profileSettings['strategic_avatar'] ?? 'Aucun';

        if ($avatarName !== 'Challenger') {
            return response()->json([
                'success' => false,
                'message' => __('Skill non disponible pour cet avatar'),
            ]);
        }

        $skillsKey = "duo_skills_{$matchId}";
        $matchSkills = session($skillsKey, [
            'used_skills' => [],
            'reduce_time_active' => false,
            'reduce_time_questions_left' => 0,
            'shuffle_answers_active' => false,
            'shuffle_answers_questions_left' => 0,
            'last_question_decremented' => 0,
        ]);

        if (in_array($skillId, $matchSkills['used_skills'])) {
            return response()->json([
                'success' => false,
                'message' => __('Ce skill a déjà été utilisé'),
            ]);
        }

        $currentQuestion = $gameState['current_question_number'] ?? 1;

        if ($currentQuestion <= 5) {
            $questionsAffected = 5;
        } elseif ($currentQuestion <= 8) {
            $questionsAffected = 3;
        } else {
            $questionsAffected = 1;
        }

        if ($skillId === 'reduce_time') {
            $matchSkills['reduce_time_active'] = true;
            $matchSkills['reduce_time_questions_left'] = $questionsAffected;
            $matchSkills['used_skills'][] = 'reduce_time';
            session([$skillsKey => $matchSkills]);

            \Log::info('[DUO-CHALLENGER] Skill reduce_time activé', [
                'user_id' => $user->id,
                'match_id' => $matchId,
                'questions_affected' => $questionsAffected,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('⏱️ Chrono Réduit activé! -2 sec pour l\'adversaire pendant :count questions', ['count' => $questionsAffected]),
                'skill_id' => 'reduce_time',
                'questions_affected' => $questionsAffected,
            ]);
        } elseif ($skillId === 'shuffle_answers') {
            $matchSkills['shuffle_answers_active'] = true;
            $matchSkills['shuffle_answers_questions_left'] = $questionsAffected;
            $matchSkills['used_skills'][] = 'shuffle_answers';
            session([$skillsKey => $matchSkills]);

            \Log::info('[DUO-CHALLENGER] Skill shuffle_answers activé', [
                'user_id' => $user->id,
                'match_id' => $matchId,
                'questions_affected' => $questionsAffected,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('🔀 Mélange Réponses activé! Réponses en mouvement pendant :count questions', ['count' => $questionsAffected]),
                'skill_id' => 'shuffle_answers',
                'questions_affected' => $questionsAffected,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => __('Skill non reconnu'),
            ]);
        }
    }
}
