<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\DuoMatchmakingService;
use App\Services\DivisionService;
use App\Services\GameStateService;
use App\Services\BuzzManagerService;
use App\Services\DuoFirestoreService;
use App\Services\PlayerContactService;
use App\Services\LobbyService;
use App\Models\DuoMatch;
use App\Models\PlayerDuoStat;
use App\Models\User;
use App\Services\AvatarCatalog;

class DuoController extends Controller
{
    public function __construct(
        private DuoMatchmakingService $matchmaking,
        private DivisionService $divisionService,
        private GameStateService $gameStateService,
        private BuzzManagerService $buzzManager,
        private DuoFirestoreService $firestoreService,
        private PlayerContactService $contactService,
        private LobbyService $lobbyService
    ) {}

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

        $match = $this->matchmaking->createInvitation($user, $opponent->id);

        $lobby = $this->lobbyService->createLobby($user, 'duo', [
            'theme' => __('Culture générale'),
            'nb_questions' => 10,
            'match_id' => $match->id,
        ]);

        $match->lobby_code = $lobby['code'];
        $match->save();

        $this->firestoreService->createMatchSession($match->id, [
            'player1_id' => $match->player1_id,
            'player2_id' => $match->player2_id,
            'player1_name' => $user->name ?? 'Player 1',
            'player2_name' => $opponent->name ?? 'Player 2',
            'lobby_code' => $lobby['code'],
            'status' => 'waiting',
        ]);

        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
            'lobby_code' => $lobby['code'],
            'redirect_url' => route('lobby.show', ['code' => $lobby['code']]),
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

        $this->matchmaking->acceptMatch($match);

        $this->contactService->registerMutualContacts($match->player1_id, $match->player2_id);

        $this->lobbyService->joinLobby($match->lobby_code, $user);

        $this->firestoreService->updateGameState($match->id, [
            'status' => 'lobby',
            'player2Joined' => true,
        ]);

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

        $this->firestoreService->updateGameState($match->id, [
            'status' => 'declined',
            'declinedBy' => $user->id,
            'declinedByName' => $user->name,
        ]);

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

        if ($this->firestoreService->sessionExists($match->id)) {
            $this->firestoreService->deleteMatchSession($match->id);
        }
        
        // Nettoyer les sessions de jeu incluant les skills
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

        $this->firestoreService->recordBuzz(
            $match->id,
            $playerId,
            $buzzRecord['server_time']
        );

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

        $this->firestoreService->updateScores(
            $match->id,
            $gameState['player_scores_map']['player'] ?? 0,
            $gameState['player_scores_map']['opponent'] ?? 0
        );

        if ($hasMoreQuestions) {
            $this->firestoreService->updateGameState($match->id, [
                'currentQuestion' => $gameState['current_question_number'],
                'questionStartTime' => $gameState['question_start_time'] ?? microtime(true),
            ]);
        } else {
            $this->firestoreService->finishRound(
                $match->id,
                $gameState['current_round'],
                $gameState['player_rounds_won'],
                $gameState['opponent_rounds_won']
            );
            
            if (!$this->gameStateService->isMatchFinished($gameState)) {
                $this->firestoreService->updateGameState($match->id, [
                    'currentQuestion' => $gameState['current_question_number'],
                    'questionStartTime' => $gameState['question_start_time'] ?? microtime(true),
                    'currentRound' => $gameState['current_round'],
                ]);
            }
        }

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

            $this->firestoreService->finishMatch($match->id, $matchResult['winner']);
            $this->firestoreService->deleteMatchSession($match->id);

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

        $firestoreState = $this->firestoreService->getGameState($match->id);
        $firestoreBuzzes = $this->firestoreService->getBuzzes($match->id);

        if (!$firestoreState) {
            return response()->json([
                'success' => false,
                'message' => 'Session Firestore introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'firestoreState' => $firestoreState,
            'buzzes' => $firestoreBuzzes,
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

        // Vérifier le niveau d'accès Duo (partiel vs complet)
        $profileSettings = $user->profile_settings ?? [];
        $choixNiveau = is_array($profileSettings) ? ($profileSettings['choix_niveau'] ?? 1) : 1;
        $duoFullUnlocked = $choixNiveau >= 11; // Accès complet après boss niveau 10

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

        $match->load(['player1', 'player2']);
        
        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;
        
        $gameState = session('game_state', $match->game_state ?? []);
        
        $opponentDivision = $this->divisionService->getOrCreateDivision($opponent, 'duo');
        $opponentStats = PlayerDuoStat::firstOrCreate(['user_id' => $opponent->id], ['level' => 0]);
        
        $opponentAvatarPath = $this->normalizeAvatarPath($this->getPlayerAvatarFromUser($opponent));
        
        $opponentInfo = [
            'name' => $opponent->pseudo ?? $opponent->name ?? __('Adversaire'),
            'avatar' => $opponentAvatarPath,
            'division' => $opponentDivision->division ?? 'Bronze',
            'level' => $opponentStats->level ?? 1,
        ];
        
        $avatarData = $this->getAvatarData($user);
        
        $lobbyCode = $gameState['lobby_code'] ?? $match->lobby_code ?? null;
        $hostId = $gameState['host_id'] ?? $match->player1_id;
        $isHost = (int)$hostId === (int)$user->id;
        
        $matchArray = $match->toArray();
        $betInfo = $gameState['bet_info'] ?? $matchArray['bet_info'] ?? null;
        
        $params = [
            'mode' => 'duo',
            'opponent_type' => 'human',
            'opponent_info' => $opponentInfo,
            'current' => $gameState['current_question'] ?? 1,
            'current_question' => $gameState['current_question'] ?? 1,
            'nb_questions' => $gameState['total_questions'] ?? 10,
            'niveau' => $gameState['niveau'] ?? session('choix_niveau', 1),
            'theme' => $gameState['theme'] ?? 'Culture générale',
            'sub_theme' => $gameState['sub_theme'] ?? '',
            'score' => $gameState['player_score'] ?? 0,
            'opponent_score' => $gameState['opponent_score'] ?? 0,
            'current_round' => $gameState['current_round'] ?? 1,
            'player_rounds_won' => $gameState['player_rounds_won'] ?? 0,
            'opponent_rounds_won' => $gameState['opponent_rounds_won'] ?? 0,
            'avatar' => $avatarData['name'],
            'avatar_skills_full' => $avatarData['skills_full'],
            'match_id' => $match->id,
            'session_id' => $lobbyCode ?? $match->id,
            'firebase_sync' => true,
            'is_host' => $isHost,
            'opponent_id' => $opponent->id,
            'match' => $matchArray,
            'bet_info' => $betInfo,
            'status' => $match->status ?? 'in_progress',
        ];

        return view('game_unified', ['params' => $params]);
    }
    
    protected function getAvatarData($user): array
    {
        $avatarName = 'Aucun';
        
        $profileSettings = $user->profile_settings;
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        } elseif (is_object($profileSettings)) {
            $profileSettings = (array) $profileSettings;
        }
        
        if (is_array($profileSettings) && isset($profileSettings['strategic_avatar'])) {
            $strategicData = $profileSettings['strategic_avatar'];
            if (is_object($strategicData)) {
                $strategicData = (array) $strategicData;
            }
            if (is_array($strategicData) && !empty($strategicData['name'])) {
                $avatarName = $strategicData['name'];
            }
        }
        
        if (empty($avatarName) || $avatarName === 'Aucun' || $avatarName === null) {
            $avatarName = session('avatar', 'Aucun');
        }
        
        if ($avatarName === 'Aucun' || empty($avatarName)) {
            return [
                'name' => 'Aucun',
                'skills_full' => ['rarity' => null, 'skills' => []],
            ];
        }
        
        $catalog = AvatarCatalog::get();
        $strategicAvatars = $catalog['stratégiques']['items'] ?? [];
        $avatarInfo = null;
        
        foreach ($strategicAvatars as $avatar) {
            if (isset($avatar['name']) && $avatar['name'] === $avatarName) {
                $avatarInfo = $avatar;
                break;
            }
        }
        
        if (!$avatarInfo) {
            return [
                'name' => $avatarName,
                'skills_full' => ['rarity' => null, 'skills' => []],
            ];
        }
        
        return [
            'name' => $avatarName,
            'skills_full' => [
                'rarity' => $avatarInfo['tier'] ?? 'Rare',
                'skills' => $avatarInfo['skills'] ?? [],
            ],
        ];
    }
    
    protected function getPlayerAvatarFromUser($user): string
    {
        $profileSettings = $user->profile_settings;
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        } elseif (is_object($profileSettings)) {
            $profileSettings = (array) $profileSettings;
        }
        
        if (is_array($profileSettings) && isset($profileSettings['avatar'])) {
            $avatarData = $profileSettings['avatar'];
            if (is_object($avatarData)) {
                $avatarData = (array) $avatarData;
            }
            if (is_array($avatarData)) {
                return $avatarData['url'] ?? $avatarData['id'] ?? 'default';
            } elseif (is_string($avatarData)) {
                return $avatarData;
            }
        }
        
        return 'default';
    }
    
    protected function normalizeAvatarPath(?string $avatarValue): string
    {
        if (!$avatarValue || $avatarValue === 'default') {
            return 'images/avatars/standard/default.png';
        }
        
        if (strpos($avatarValue, 'http://') === 0 || strpos($avatarValue, 'https://') === 0 || strpos($avatarValue, '//') === 0) {
            return $avatarValue;
        }
        
        $avatarValue = ltrim($avatarValue, '/');
        
        if (strpos($avatarValue, 'images/') === 0) {
            if (substr($avatarValue, -4) !== '.png') {
                $avatarValue .= '.png';
            }
            return $avatarValue;
        }
        
        if (strpos($avatarValue, '/') !== false && substr($avatarValue, -4) !== '.png') {
            return 'images/avatars/' . $avatarValue . '.png';
        }
        
        if (strpos($avatarValue, '/') !== false) {
            return $avatarValue;
        }
        
        $avatarValue = preg_replace('/\.png$/', '', $avatarValue);
        return 'images/avatars/standard/' . $avatarValue . '.png';
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

        $globalStats = $gameState['global_stats'] ?? [];
        $totalCorrect = $globalStats['correct'] ?? 0;
        $totalIncorrect = $globalStats['incorrect'] ?? 0;
        $totalUnanswered = $globalStats['unanswered'] ?? 0;

        $divisionRanks = ['bronze' => 1, 'argent' => 2, 'or' => 3, 'platine' => 4, 'diamant' => 5, 'legende' => 6];
        $previousDivision = $gameState['previous_division'] ?? $division->division;
        $currentRank = $divisionRanks[strtolower($division->division ?? 'bronze')] ?? 1;
        $previousRank = $divisionRanks[strtolower($previousDivision ?? 'bronze')] ?? 1;
        $promoted = $currentRank > $previousRank;
        $demoted = $currentRank < $previousRank;

        $opponentAvatarPath = $this->normalizeAvatarPath($opponent->avatar ?? null);

        $params = [
            'mode' => 'duo',
            'match_result' => [
                'player_won' => $matchResult['player_won'] ?? false,
                'is_draw' => $matchResult['is_draw'] ?? false,
                'player_rounds_won' => $matchResult['player_rounds_won'] ?? 0,
                'opponent_rounds_won' => $matchResult['opponent_rounds_won'] ?? 0,
                'player_total_score' => $matchResult['player_total_score'] ?? ($gameState['player_total_score'] ?? 0),
                'opponent_total_score' => $matchResult['opponent_total_score'] ?? ($gameState['opponent_total_score'] ?? 0),
                'coins_earned' => $coinsEarned,
                'xp_earned' => 0,
                'division_points' => $pointsEarned,
                'new_division' => $division->division ?? 'bronze',
                'promoted' => $promoted,
                'demoted' => $demoted,
                'coins_bonus' => $coinsBonus,
                'party_efficiency' => $accuracy,
                'global_efficiency' => $accuracy,
                'total_correct' => $totalCorrect,
                'total_incorrect' => $totalIncorrect,
                'total_unanswered' => $totalUnanswered,
                'round_summaries' => $gameState['round_summaries'] ?? [],
                'bet_info' => $betInfo,
                'bet_winnings' => $betWinnings,
                'forfeit' => $matchResult['forfeit'] ?? false,
                'forfeit_label' => $matchResult['forfeit_label'] ?? null,
            ],
            'opponent_info' => [
                'id' => $opponent->id ?? null,
                'name' => $opponent->name ?? __('Adversaire'),
                'avatar' => $opponentAvatarPath,
                'division' => ucfirst($opponentDivision->division ?? 'bronze'),
                'level' => $opponentDivision->level ?? 1,
                'is_boss' => false,
            ],
        ];

        return view('game_match_result', ['params' => $params]);
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

    public function getInvitations()
    {
        $user = Auth::user();
        
        $receivedInvitations = DuoMatch::where('player2_id', $user->id)
            ->where('status', 'waiting')
            ->with('player1')
            ->get()
            ->map(function ($match) {
                return [
                    'match_id' => $match->id,
                    'from_player' => $match->player1,
                ];
            });

        $sentInvitations = DuoMatch::where('player1_id', $user->id)
            ->where('status', 'waiting')
            ->with('player2')
            ->get()
            ->map(function ($match) {
                return [
                    'match_id' => $match->id,
                    'to_player' => $match->player2,
                    'lobby_code' => $match->lobby_code,
                ];
            });

        return response()->json([
            'success' => true,
            'invitations' => $receivedInvitations,
            'sent_invitations' => $sentInvitations,
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

    public function addContact(Request $request)
    {
        $request->validate([
            'player_code' => 'required|string|max:20',
        ]);

        $user = Auth::user();
        $contact = $this->contactService->addContactByCode($user->id, $request->player_code);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => __('Joueur introuvable'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'contact' => $contact,
            'message' => __('Contact ajouté'),
        ]);
    }

    /**
     * Join the matchmaking queue (validate entry fee for higher divisions)
     */
    public function joinQueue(Request $request)
    {
        $request->validate([
            'target_division' => 'required|string|in:bronze,argent,or,platine,diamant,legende',
        ]);

        $user = Auth::user();
        $targetDivision = $request->target_division;
        
        // Get user's current division
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $currentDivision = $division->division ?? 'bronze';
        
        // Division hierarchy and fees
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
        
        // Validate target division is within 2 divisions above current
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
        
        // Calculate entry fee (only if playing in higher division)
        $entryFee = 0;
        if ($targetIndex > $currentIndex) {
            $entryFee = $divisionFees[$targetDivision];
        }
        
        // Check if user has enough coins
        if ($entryFee > 0 && $user->coins < $entryFee) {
            return response()->json([
                'success' => false,
                'message' => __('Vous n\'avez pas assez de pièces pour cette division. Il vous faut :amount pièces.', ['amount' => $entryFee]),
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => __('Prêt à rejoindre la file'),
            'entry_fee' => $entryFee,
        ]);
    }

    /**
     * Create a match from queue selection
     */
    public function createQueueMatch(Request $request)
    {
        $request->validate([
            'opponent_id' => 'required|integer|exists:users,id',
            'target_division' => 'required|string|in:bronze,argent,or,platine,diamant,legende',
        ]);

        $user = Auth::user();
        $opponentId = $request->opponent_id;
        $targetDivision = $request->target_division;
        
        // Validate opponent exists
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
        
        // Load both players' DuoStats for level validation
        $userStats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $user->id],
            ['level' => 0]
        );
        $opponentStats = PlayerDuoStat::firstOrCreate(
            ['user_id' => $opponentId],
            ['level' => 0]
        );
        
        // Validate level difference is within ±10
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
        
        // Get user's current division
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        $currentDivision = $division->division ?? 'bronze';
        
        // Division hierarchy and fees
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
        
        // Validate target division is within allowed range (current to current+2)
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
        
        // Calculate entry fee (only if playing in higher division)
        $entryFee = 0;
        if ($targetIndex > $currentIndex) {
            $entryFee = $divisionFees[$targetDivision];
        }
        
        // Deduct entry fee if applicable
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
        
        // Create the match with target division info
        $match = DuoMatch::create([
            'player1_id' => $user->id,
            'player2_id' => $opponentId,
            'status' => 'accepted',
            'is_random_match' => true,
            'division' => $targetDivision,
        ]);
        
        // Create lobby for the match
        $lobby = $this->lobbyService->createLobby($user, 'duo', [
            'theme' => __('Culture générale'),
            'nb_questions' => 10,
            'match_id' => $match->id,
        ]);
        
        $match->lobby_code = $lobby['code'];
        $match->save();
        
        // Register mutual contacts
        $this->contactService->registerMutualContacts($user->id, $opponentId);
        
        // Create Firestore match session
        $this->firestoreService->createMatchSession($match->id, [
            'player1_id' => $match->player1_id,
            'player2_id' => $match->player2_id,
            'player1_name' => $user->name ?? 'Player 1',
            'player2_name' => $opponent->name ?? 'Player 2',
            'lobby_code' => $lobby['code'],
            'status' => 'lobby',
            'division' => $targetDivision,
        ]);
        
        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
            'lobby_code' => $lobby['code'],
            'redirect_url' => route('lobby.show', ['code' => $lobby['code']]),
            'entry_fee_deducted' => $entryFee,
        ]);
    }
}
