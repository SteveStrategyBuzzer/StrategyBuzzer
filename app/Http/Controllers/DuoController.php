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
        private DuoFirestoreService $firestoreService,
        private PlayerContactService $contactService,
        private LobbyService $lobbyService,
        private GameServerService $gameServerService
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

        $gameServerUrl = env('GAME_SERVER_URL', 'http://localhost:3001');
        $roomId = $match->room_id ?? null;
        $lobbyCode = $match->lobby_code ?? null;
        
        $jwtToken = null;
        if ($roomId) {
            $gameServerService = app(\App\Services\GameServerService::class);
            $jwtToken = $gameServerService->generatePlayerToken($user->id, $roomId);
        }

        $skills = $this->getPlayerSkillsWithTriggers($user);

        return view('duo_game', [
            'match_id' => $match->id,
            'match' => $match->load(['player1', 'player2']),
            'game_server_url' => $gameServerUrl,
            'room_id' => $roomId,
            'lobby_code' => $lobbyCode,
            'jwt_token' => $jwtToken,
            'skills' => $skills,
        ]);
    }
    
    protected function getPlayerSkillsWithTriggers($user): array
    {
        $profileSettings = $user->profile_settings;
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true);
        }
        
        $avatarName = $profileSettings['strategic_avatar'] ?? 'Aucun';
        
        if ($avatarName === 'Aucun' || empty($avatarName)) {
            return [];
        }
        
        $catalog = \App\Services\AvatarCatalog::get();
        $strategicAvatars = $catalog['stratégiques']['items'] ?? [];
        $avatarInfo = null;
        
        foreach ($strategicAvatars as $avatar) {
            if (isset($avatar['name']) && $avatar['name'] === $avatarName) {
                $avatarInfo = $avatar;
                break;
            }
        }
        
        if (!$avatarInfo || empty($avatarInfo['skills'])) {
            return [];
        }
        
        $skills = [];
        foreach ($avatarInfo['skills'] as $skillId) {
            $skillData = \App\Services\SkillCatalog::getSkill($skillId);
            if ($skillData) {
                $skills[] = [
                    'id' => $skillData['id'],
                    'name' => $skillData['name'],
                    'icon' => $skillData['icon'],
                    'description' => $skillData['description'],
                    'trigger' => $skillData['trigger'],
                    'type' => $skillData['type'],
                    'auto' => $skillData['auto'] ?? false,
                    'uses_per_match' => $skillData['uses_per_match'] ?? 1,
                    'used' => false,
                ];
            }
        }
        
        return $skills;
    }
    
    public function activateSkill(Request $request, DuoMatch $match)
    {
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

        $playerToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);

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
}
