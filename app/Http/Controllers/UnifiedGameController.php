<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\GameModeProvider;
use App\Services\SoloGameProvider;
use App\Services\DuoGameProvider;
use App\Services\LeagueGameProvider;
use App\Services\MasterGameProvider;
use App\Services\QuestionService;
use App\Services\GameStateService;
use App\Services\AvatarCatalog;
use App\Services\LobbyService;

class UnifiedGameController extends Controller
{
    protected QuestionService $questionService;
    protected GameStateService $gameStateService;
    protected LobbyService $lobbyService;
    
    public function __construct(QuestionService $questionService, GameStateService $gameStateService, LobbyService $lobbyService)
    {
        $this->questionService = $questionService;
        $this->gameStateService = $gameStateService;
        $this->lobbyService = $lobbyService;
    }
    
    protected function getProvider(string $mode): GameModeProvider
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        return GameModeProvider::create($mode, $user, $gameState);
    }
    
    public function startGame(Request $request, string $mode)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'theme' => 'required|string',
            'nb_questions' => 'required|integer|min:1|max:20',
            'niveau' => 'integer|min:1|max:100',
            'opponent_id' => 'nullable|integer',
            'match_id' => 'nullable|string',
            'room_code' => 'nullable|string',
            'lobby_code' => 'nullable|string',
        ]);
        
        $provider = $this->getProvider($mode);
        
        $gameState = [
            'mode' => $mode,
            'theme' => $validated['theme'],
            'total_questions' => $validated['nb_questions'],
            'niveau' => $validated['niveau'] ?? session('choix_niveau', 1),
            'current_question' => 1,
            'current_round' => 1,
            'player_score' => 0,
            'opponent_score' => 0,
            'player_rounds_won' => 0,
            'opponent_rounds_won' => 0,
            'player_total_score' => 0,
            'opponent_total_score' => 0,
            'started_at' => now()->toISOString(),
        ];
        
        if ($mode === 'duo' && isset($validated['lobby_code'])) {
            $lobby = $this->lobbyService->getLobby($validated['lobby_code']);
            
            if (!$lobby) {
                return redirect()->route('duo.lobby')->with('error', __('Le salon n\'existe plus'));
            }
            
            if (!empty($lobby['players'])) {
                $players = $lobby['players'];
                $opponentId = null;
                $opponentAvatar = 'default';
                $opponentName = 'Adversaire';
                
                foreach ($players as $playerId => $playerData) {
                    if ((int)$playerId !== (int)$user->id) {
                        $opponentId = (int)$playerId;
                        $opponentAvatar = $playerData['avatar'] ?? 'default';
                        $opponentName = $playerData['name'] ?? 'Adversaire';
                        break;
                    }
                }
                
                if (!$opponentId) {
                    return redirect()->route('duo.lobby')->with('error', __('Adversaire introuvable dans le salon'));
                }
                
                $gameState['opponent_id'] = $opponentId;
                $gameState['opponent_avatar'] = $opponentAvatar;
                $gameState['opponent_name'] = $opponentName;
                $gameState['lobby_code'] = $validated['lobby_code'];
                $gameState['match_id'] = $validated['match_id'] ?? $validated['lobby_code'];
            }
        } elseif ($mode === 'duo' && isset($validated['opponent_id'])) {
            $gameState['opponent_id'] = $validated['opponent_id'];
            $gameState['match_id'] = $validated['match_id'] ?? null;
        }
        
        if ($mode === 'master' && isset($validated['room_code'])) {
            $gameState['room_code'] = $validated['room_code'];
        }
        
        $provider->setGameState($gameState);
        
        session(['game_state' => $provider->getGameState()]);
        session(['game_mode' => $mode]);
        
        if ($mode === 'duo' || $mode === 'league_individual') {
            return redirect()->route('game.resume', ['mode' => $mode]);
        }
        
        return redirect()->route('game.question', ['mode' => $mode]);
    }
    
    protected function getModeIndexRoute(string $mode): string
    {
        $routeMap = [
            'duo' => 'duo.lobby',
            'league_individual' => 'ligue',
            'league_team' => 'ligue',
            'master' => 'master.index',
            'solo' => 'solo.index',
        ];
        return $routeMap[$mode] ?? 'menu';
    }
    
    public function showResume(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $opponentInfo = $provider->getOpponentInfo();
        
        $playerName = $user->name ?? 'Joueur';
        $playerAvatar = session('selected_avatar', 'default');
        $playerDivision = 'Bronze';
        
        if ($user->duoStats) {
            $playerDivision = app(\App\Services\DivisionService::class)->getOrCreateDivision($user, 'duo')['name'] ?? 'Bronze';
        }
        
        $params = [
            'mode' => $mode,
            'theme' => $gameState['theme'] ?? 'Culture générale',
            'nb_questions' => $gameState['total_questions'] ?? 10,
            'player_name' => $playerName,
            'player_avatar' => $playerAvatar,
            'player_division' => $playerDivision,
            'opponent_name' => $opponentInfo['name'] ?? 'Adversaire',
            'opponent_avatar' => $opponentInfo['avatar'] ?? 'default',
            'opponent_division' => $opponentInfo['division'] ?? 'Bronze',
            'redirect_url' => route('game.question', ['mode' => $mode]),
        ];
        
        return view('duo_resume', ['params' => $params]);
    }
    
    public function showQuestion(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $question = $this->getCurrentQuestion($gameState);
        
        if (!$question) {
            return redirect()->route('game.round-result', ['mode' => $mode]);
        }
        
        $opponentInfo = $provider->getOpponentInfo();
        $scoring = $provider->getScoring();
        
        $avatarData = $this->getAvatarData($user);
        
        $params = [
            'mode' => $mode,
            'opponent_type' => $provider->getOpponentType(),
            'opponent_info' => $opponentInfo,
            'question_text' => $question['text'],
            'answers' => $question['answers'],
            'correct_answer_index' => $question['correct_index'],
            'current' => $gameState['current_question'],
            'nb_questions' => $gameState['total_questions'],
            'niveau' => $gameState['niveau'] ?? 1,
            'theme' => $gameState['theme'] ?? 'Culture générale',
            'sub_theme' => $question['sub_theme'] ?? '',
            'score' => $gameState['player_score'] ?? 0,
            'opponent_score' => $gameState['opponent_score'] ?? 0,
            'current_round' => $gameState['current_round'] ?? 1,
            'player_rounds_won' => $gameState['player_rounds_won'] ?? 0,
            'opponent_rounds_won' => $gameState['opponent_rounds_won'] ?? 0,
            'scoring' => $scoring,
            'avatar' => $avatarData['name'],
            'avatar_skills_full' => $avatarData['skills_full'],
        ];
        
        if ($mode === 'duo' || $mode === 'league_individual') {
            $params['match_id'] = $gameState['match_id'] ?? null;
            $params['firebase_sync'] = true;
        }
        
        if ($mode === 'master') {
            $params['room_code'] = $gameState['room_code'] ?? null;
            $params['is_host'] = ($gameState['host_id'] ?? null) === $user->id;
            $params['players'] = $gameState['players'] ?? [];
        }
        
        return view('game_unified', ['params' => $params]);
    }
    
    public function handleBuzz(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return response()->json(['error' => __('Aucune partie en cours')], 400);
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $buzzTime = $request->input('buzz_time', 5.0);
        $result = $provider->handleBuzz((float)$buzzTime);
        
        session(['game_state' => $provider->getGameState()]);
        
        return response()->json($result);
    }
    
    public function submitAnswer(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return response()->json(['error' => __('Aucune partie en cours')], 400);
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $validated = $request->validate([
            'answer_id' => 'required|integer',
            'is_correct' => 'required|boolean',
            'buzz_time' => 'numeric',
        ]);
        
        $result = $provider->submitAnswer(
            $validated['answer_id'],
            $validated['is_correct']
        );
        
        if ($provider->getOpponentType() === 'ai') {
            $opponentResult = $provider->handleOpponentAnswer();
            $result['opponent'] = $opponentResult;
        }
        
        $updatedState = $provider->getGameState();
        $updatedState['current_question'] = ($updatedState['current_question'] ?? 1) + 1;
        
        session(['game_state' => $updatedState]);
        
        $nextQuestion = $provider->getNextQuestion();
        $result['has_next_question'] = $nextQuestion !== null;
        $result['current_question'] = $updatedState['current_question'];
        
        return response()->json($result);
    }
    
    public function showRoundResult(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $roundResult = $provider->finishRound();
        
        session(['game_state' => $provider->getGameState()]);
        
        $params = [
            'mode' => $mode,
            'round_result' => $roundResult,
            'player_score' => $gameState['player_score'] ?? 0,
            'opponent_score' => $gameState['opponent_score'] ?? 0,
            'player_rounds_won' => $roundResult['player_rounds_won'],
            'opponent_rounds_won' => $roundResult['opponent_rounds_won'],
            'match_complete' => $roundResult['match_complete'],
            'opponent_info' => $provider->getOpponentInfo(),
        ];
        
        $params['round_result'] = $roundResult;
        
        if ($roundResult['match_complete'] && $roundResult['player_rounds_won'] === $roundResult['opponent_rounds_won']) {
            return redirect()->route('game.tiebreaker-choice', ['mode' => $mode]);
        }
        
        if (in_array($mode, ['duo', 'league_individual', 'league_team'])) {
            return view('unified_round_result', ['params' => $params]);
        }
        
        return view('round_result', ['params' => $params]);
    }
    
    public function startNextRound(Request $request, string $mode)
    {
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode));
        }
        
        $gameState['current_round'] = ($gameState['current_round'] ?? 1) + 1;
        $gameState['current_question'] = 1;
        $gameState['player_score'] = 0;
        $gameState['opponent_score'] = 0;
        
        session(['game_state' => $gameState]);
        
        return redirect()->route('game.question', ['mode' => $mode]);
    }
    
    public function showMatchResult(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $matchResult = $provider->getMatchResult();
        
        session()->forget(['game_state', 'game_mode']);
        
        $params = [
            'mode' => $mode,
            'match_result' => $matchResult,
            'opponent_info' => $provider->getOpponentInfo(),
        ];
        
        return view('game_match_result', ['params' => $params]);
    }
    
    public function getGameState(Request $request, string $mode)
    {
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return response()->json(['error' => __('Aucune partie en cours')], 400);
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        return response()->json([
            'mode' => $mode,
            'state' => $gameState,
            'opponent_info' => $provider->getOpponentInfo(),
            'scoring' => $provider->getScoring(),
        ]);
    }
    
    public function syncFromFirebase(Request $request, string $mode)
    {
        if (!in_array($mode, ['duo', 'league_individual', 'master'])) {
            return response()->json(['error' => __('Ce mode ne supporte pas la synchronisation Firebase')], 400);
        }
        
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return response()->json(['error' => __('Aucune partie en cours')], 400);
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $firestoreState = $request->input('firestore_state', []);
        
        if (method_exists($provider, 'updateFromFirebase')) {
            $provider->updateFromFirebase($firestoreState);
        }
        
        session(['game_state' => $provider->getGameState()]);
        
        return response()->json([
            'success' => true,
            'state' => $provider->getGameState(),
        ]);
    }
    
    protected function getCurrentQuestion(array $gameState): ?array
    {
        $currentQuestionNumber = $gameState['current_question'] ?? 1;
        $totalQuestions = $gameState['total_questions'] ?? 10;
        
        if ($currentQuestionNumber > $totalQuestions) {
            return null;
        }
        
        $currentQuestion = session('unified_current_question');
        $storedQuestionNumber = session('unified_question_number', 0);
        
        if ($currentQuestion && $storedQuestionNumber === $currentQuestionNumber) {
            return $currentQuestion;
        }
        
        $usedQuestionIds = session('used_question_ids', []);
        $usedAnswers = session('used_answers', []);
        $sessionUsedAnswers = session('session_used_answers', []);
        $sessionUsedQuestionTexts = session('session_used_question_texts', []);
        
        $theme = $gameState['theme'] ?? 'Culture générale';
        $niveau = $gameState['niveau'] ?? 1;
        $language = Auth::user()->preferred_language ?? 'fr';
        
        $generatedQuestion = $this->questionService->generateQuestion(
            $theme,
            $niveau,
            $currentQuestionNumber,
            $usedQuestionIds,
            $usedAnswers,
            $sessionUsedAnswers,
            $sessionUsedQuestionTexts,
            null,
            false,
            $language
        );
        
        if (!$generatedQuestion) {
            return null;
        }
        
        $question = [
            'id' => $generatedQuestion['id'] ?? uniqid(),
            'text' => $generatedQuestion['question_text'] ?? $generatedQuestion['text'] ?? '',
            'answers' => $generatedQuestion['answers'] ?? [],
            'correct_index' => $generatedQuestion['correct_id'] ?? $generatedQuestion['correct_index'] ?? 0,
            'sub_theme' => $generatedQuestion['sub_theme'] ?? '',
        ];
        
        $usedQuestionIds[] = $question['id'];
        $sessionUsedQuestionTexts[] = $question['text'];
        foreach ($question['answers'] as $answer) {
            $answerText = is_array($answer) ? ($answer['text'] ?? '') : $answer;
            if ($answerText) {
                $usedAnswers[] = $answerText;
                $sessionUsedAnswers[] = $answerText;
            }
        }
        
        session([
            'unified_current_question' => $question,
            'unified_question_number' => $currentQuestionNumber,
            'used_question_ids' => $usedQuestionIds,
            'used_answers' => $usedAnswers,
            'session_used_answers' => $sessionUsedAnswers,
            'session_used_question_texts' => $sessionUsedQuestionTexts,
        ]);
        
        return $question;
    }
    
    protected function getAvatarData($user): array
    {
        $avatarName = session('avatar', 'Aucun');
        
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
    
    public function tiebreakerChoice(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $isMultiplayer = in_array($mode, ['duo', 'league_individual', 'league_team']);
        $isHost = $gameState['is_host'] ?? true;
        
        return view('unified_tiebreaker_choice', [
            'mode' => $mode,
            'gameState' => $gameState,
            'is_multiplayer' => $isMultiplayer,
            'is_host' => $isHost,
            'player_name' => $user->pseudo ?? $user->name,
            'opponent_name' => $gameState['opponent_name'] ?? __('Adversaire'),
            'player_score' => $gameState['player_total_score'] ?? 0,
            'opponent_score' => $gameState['opponent_total_score'] ?? 0,
            'player_avatar' => $gameState['player_avatar'] ?? null,
            'opponent_avatar' => $gameState['opponent_avatar'] ?? null,
            'match_id' => $gameState['match_id'] ?? null,
        ]);
    }
    
    public function tiebreakerSelect(Request $request, string $mode)
    {
        $validated = $request->validate([
            'choice' => 'required|string|in:bonus,efficiency,sudden_death',
        ]);
        
        $gameState = session('game_state', []);
        $gameState['tiebreaker_choice'] = $validated['choice'];
        session(['game_state' => $gameState]);
        
        switch ($validated['choice']) {
            case 'bonus':
                return redirect()->route('game.tiebreaker-bonus', ['mode' => $mode]);
            case 'efficiency':
                return redirect()->route('game.tiebreaker-efficiency', ['mode' => $mode]);
            case 'sudden_death':
                return redirect()->route('game.tiebreaker-sudden-death', ['mode' => $mode]);
            default:
                return redirect()->route('game.tiebreaker-choice', ['mode' => $mode]);
        }
    }
    
    public function tiebreakerBonus(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $question = $this->generateTiebreakerQuestion($gameState);
        
        $gameState['tiebreaker_question'] = $question;
        $gameState['tiebreaker_started_at'] = now()->toISOString();
        session(['game_state' => $gameState]);
        
        return view('unified_tiebreaker_bonus', [
            'mode' => $mode,
            'question' => $question,
            'gameState' => $gameState,
            'player_name' => $user->pseudo ?? $user->name,
            'opponent_name' => $gameState['opponent_name'] ?? __('Adversaire'),
            'player_avatar' => $gameState['player_avatar'] ?? null,
            'opponent_avatar' => $gameState['opponent_avatar'] ?? null,
            'match_id' => $gameState['match_id'] ?? null,
            'is_multiplayer' => in_array($mode, ['duo', 'league_individual', 'league_team']),
        ]);
    }
    
    public function tiebreakerBonusAnswer(Request $request, string $mode)
    {
        $validated = $request->validate([
            'answer_index' => 'required|integer|min:0|max:3',
            'buzz_time' => 'nullable|numeric',
        ]);
        
        $gameState = session('game_state', []);
        $question = $gameState['tiebreaker_question'] ?? null;
        
        if (!$question) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Question non trouvée'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $isCorrect = ($validated['answer_index'] == $question['correct_index']);
        $buzzTime = $validated['buzz_time'] ?? 10;
        
        $gameState['player_tiebreaker_correct'] = $isCorrect;
        $gameState['player_tiebreaker_time'] = $buzzTime;
        
        $isMultiplayer = in_array($mode, ['duo', 'league_individual', 'league_team']);
        
        if ($isMultiplayer) {
            $opponentCorrect = $gameState['opponent_tiebreaker_correct'] ?? null;
            $opponentTime = $gameState['opponent_tiebreaker_time'] ?? null;
            
            if ($opponentCorrect === null) {
                session(['game_state' => $gameState]);
                return view('unified_tiebreaker_waiting', [
                    'mode' => $mode,
                    'gameState' => $gameState,
                    'player_answered' => true,
                    'player_correct' => $isCorrect,
                    'match_id' => $gameState['match_id'] ?? null,
                ]);
            }
            
            $winner = $this->determineTiebreakerWinner($isCorrect, $buzzTime, $opponentCorrect, $opponentTime);
        } else {
            $opponentCorrect = rand(0, 100) < 50;
            $opponentTime = rand(2, 8);
            $winner = $this->determineTiebreakerWinner($isCorrect, $buzzTime, $opponentCorrect, $opponentTime);
        }
        
        $gameState['tiebreaker_winner'] = $winner;
        session(['game_state' => $gameState]);
        
        return redirect()->route('game.match-result', ['mode' => $mode]);
    }
    
    public function tiebreakerEfficiency(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $globalStats = $gameState['global_stats'] ?? [];
        $playerEfficiency = $this->calculatePlayerEfficiency($globalStats);
        
        $isMultiplayer = in_array($mode, ['duo', 'league_individual', 'league_team']);
        
        if ($isMultiplayer) {
            $opponentEfficiency = $gameState['opponent_efficiency'] ?? $playerEfficiency;
        } else {
            $opponentEfficiency = rand(40, 80);
        }
        
        $winner = 'draw';
        if ($playerEfficiency > $opponentEfficiency) {
            $winner = 'player';
        } elseif ($playerEfficiency < $opponentEfficiency) {
            $winner = 'opponent';
        } else {
            $playerTotal = $gameState['player_total_score'] ?? 0;
            $opponentTotal = $gameState['opponent_total_score'] ?? 0;
            $winner = $playerTotal >= $opponentTotal ? 'player' : 'opponent';
        }
        
        $gameState['tiebreaker_winner'] = $winner;
        $gameState['player_efficiency'] = $playerEfficiency;
        $gameState['opponent_efficiency'] = $opponentEfficiency;
        session(['game_state' => $gameState]);
        
        return view('unified_tiebreaker_efficiency', [
            'mode' => $mode,
            'gameState' => $gameState,
            'player_name' => $user->pseudo ?? $user->name,
            'opponent_name' => $gameState['opponent_name'] ?? __('Adversaire'),
            'player_efficiency' => $playerEfficiency,
            'opponent_efficiency' => $opponentEfficiency,
            'winner' => $winner,
            'player_avatar' => $gameState['player_avatar'] ?? null,
            'opponent_avatar' => $gameState['opponent_avatar'] ?? null,
        ]);
    }
    
    public function tiebreakerSuddenDeath(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $questionNumber = $gameState['sudden_death_question_number'] ?? 1;
        $question = $this->generateTiebreakerQuestion($gameState);
        
        $gameState['tiebreaker_question'] = $question;
        $gameState['sudden_death_question_number'] = $questionNumber;
        $gameState['sudden_death_active'] = true;
        session(['game_state' => $gameState]);
        
        return view('unified_tiebreaker_sudden_death', [
            'mode' => $mode,
            'question' => $question,
            'question_number' => $questionNumber,
            'gameState' => $gameState,
            'player_name' => $user->pseudo ?? $user->name,
            'opponent_name' => $gameState['opponent_name'] ?? __('Adversaire'),
            'player_avatar' => $gameState['player_avatar'] ?? null,
            'opponent_avatar' => $gameState['opponent_avatar'] ?? null,
            'match_id' => $gameState['match_id'] ?? null,
            'is_multiplayer' => in_array($mode, ['duo', 'league_individual', 'league_team']),
        ]);
    }
    
    public function tiebreakerSuddenDeathAnswer(Request $request, string $mode)
    {
        $validated = $request->validate([
            'answer_index' => 'required|integer|min:0|max:3',
            'buzz_time' => 'nullable|numeric',
        ]);
        
        $gameState = session('game_state', []);
        $question = $gameState['tiebreaker_question'] ?? null;
        
        if (!$question) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Question non trouvée'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $isCorrect = ($validated['answer_index'] == $question['correct_index']);
        $buzzTime = $validated['buzz_time'] ?? 10;
        
        $isMultiplayer = in_array($mode, ['duo', 'league_individual', 'league_team']);
        
        if ($isMultiplayer) {
            $opponentCorrect = $gameState['opponent_sudden_death_correct'] ?? null;
            
            if ($opponentCorrect === null) {
                $gameState['player_sudden_death_correct'] = $isCorrect;
                $gameState['player_sudden_death_time'] = $buzzTime;
                session(['game_state' => $gameState]);
                
                return view('unified_tiebreaker_waiting', [
                    'mode' => $mode,
                    'gameState' => $gameState,
                    'player_answered' => true,
                    'player_correct' => $isCorrect,
                    'sudden_death' => true,
                    'match_id' => $gameState['match_id'] ?? null,
                ]);
            }
        } else {
            $opponentCorrect = rand(0, 100) < 60;
        }
        
        if (!$isCorrect && $opponentCorrect) {
            $gameState['tiebreaker_winner'] = 'opponent';
            session(['game_state' => $gameState]);
            return redirect()->route('game.match-result', ['mode' => $mode]);
        }
        
        if ($isCorrect && !$opponentCorrect) {
            $gameState['tiebreaker_winner'] = 'player';
            session(['game_state' => $gameState]);
            return redirect()->route('game.match-result', ['mode' => $mode]);
        }
        
        $gameState['sudden_death_question_number'] = ($gameState['sudden_death_question_number'] ?? 1) + 1;
        unset($gameState['opponent_sudden_death_correct']);
        session(['game_state' => $gameState]);
        
        return redirect()->route('game.tiebreaker-sudden-death', ['mode' => $mode]);
    }
    
    protected function generateTiebreakerQuestion(array $gameState): array
    {
        $theme = $gameState['theme'] ?? 'Culture générale';
        $niveau = $gameState['niveau'] ?? 1;
        $language = auth()->user()?->preferred_language ?? 'fr';
        
        try {
            $usedQuestionIds = session('used_question_ids', []);
            $usedAnswers = session('used_answers', []);
            
            $generatedQuestion = $this->questionService->generateSingleQuestion(
                $theme,
                $niveau,
                $language,
                $usedQuestionIds,
                $usedAnswers
            );
            
            return [
                'id' => $generatedQuestion['id'] ?? uniqid('tb_'),
                'text' => $generatedQuestion['question_text'] ?? $generatedQuestion['text'] ?? '',
                'answers' => $generatedQuestion['answers'] ?? [],
                'correct_index' => $generatedQuestion['correct_id'] ?? $generatedQuestion['correct_index'] ?? 0,
                'sub_theme' => $generatedQuestion['sub_theme'] ?? '',
            ];
        } catch (\Exception $e) {
            \Log::error('Erreur génération question tiebreaker: ' . $e->getMessage());
            
            return [
                'id' => uniqid('tb_fallback_'),
                'text' => __('Question de départage'),
                'answers' => [
                    ['id' => 0, 'text' => 'A'],
                    ['id' => 1, 'text' => 'B'],
                    ['id' => 2, 'text' => 'C'],
                    ['id' => 3, 'text' => 'D'],
                ],
                'correct_index' => 0,
                'sub_theme' => '',
            ];
        }
    }
    
    protected function determineTiebreakerWinner(bool $playerCorrect, float $playerTime, bool $opponentCorrect, float $opponentTime): string
    {
        if ($playerCorrect && !$opponentCorrect) {
            return 'player';
        }
        if (!$playerCorrect && $opponentCorrect) {
            return 'opponent';
        }
        if ($playerCorrect && $opponentCorrect) {
            return $playerTime <= $opponentTime ? 'player' : 'opponent';
        }
        return 'draw';
    }
    
    protected function calculatePlayerEfficiency(array $globalStats): float
    {
        if (empty($globalStats)) {
            return 50.0;
        }
        
        $correctAnswers = 0;
        $totalQuestions = 0;
        $totalTime = 0;
        
        foreach ($globalStats as $stat) {
            if (isset($stat['is_bonus']) && $stat['is_bonus']) continue;
            $totalQuestions++;
            if (!empty($stat['player_correct'])) {
                $correctAnswers++;
            }
            $totalTime += $stat['player_time'] ?? 10;
        }
        
        if ($totalQuestions === 0) {
            return 50.0;
        }
        
        $accuracy = ($correctAnswers / $totalQuestions) * 100;
        $avgTime = $totalTime / $totalQuestions;
        $speedBonus = max(0, (10 - $avgTime) * 2);
        
        return min(100, $accuracy + $speedBonus);
    }
}
