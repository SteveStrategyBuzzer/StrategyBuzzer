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
use App\Services\SkillCatalog;
use App\Services\LobbyService;
use App\Services\DuoFirestoreService;
use App\Services\LeagueIndividualFirestoreService;
use App\Services\LeagueTeamFirestoreService;
use Illuminate\Support\Facades\Log;

class UnifiedGameController extends Controller
{
    protected QuestionService $questionService;
    protected GameStateService $gameStateService;
    protected LobbyService $lobbyService;
    protected DuoFirestoreService $duoFirestoreService;
    protected LeagueIndividualFirestoreService $leagueIndividualFirestoreService;
    protected LeagueTeamFirestoreService $leagueTeamFirestoreService;
    
    public function __construct(
        QuestionService $questionService, 
        GameStateService $gameStateService, 
        LobbyService $lobbyService, 
        DuoFirestoreService $duoFirestoreService,
        LeagueIndividualFirestoreService $leagueIndividualFirestoreService,
        LeagueTeamFirestoreService $leagueTeamFirestoreService
    )
    {
        $this->questionService = $questionService;
        $this->gameStateService = $gameStateService;
        $this->lobbyService = $lobbyService;
        $this->duoFirestoreService = $duoFirestoreService;
        $this->leagueIndividualFirestoreService = $leagueIndividualFirestoreService;
        $this->leagueTeamFirestoreService = $leagueTeamFirestoreService;
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
                
                if (!empty($lobby['bet_info'])) {
                    $gameState['bet_info'] = $lobby['bet_info'];
                }
            }
        } elseif ($mode === 'duo' && isset($validated['opponent_id'])) {
            $gameState['opponent_id'] = $validated['opponent_id'];
            $gameState['match_id'] = $validated['match_id'] ?? null;
        }
        
        if ($mode === 'master' && isset($validated['room_code'])) {
            $gameState['room_code'] = $validated['room_code'];
        }
        
        $provider->setGameState($gameState);
        
        if (in_array($mode, ['duo', 'league_individual', 'league_team'])) {
            $this->generateAndStoreQuestionsForMatch($gameState, $user);
        }
        
        session(['game_state' => $provider->getGameState()]);
        session(['game_mode' => $mode]);
        
        // Réinitialiser les skills pour la nouvelle partie
        session(['used_skills' => []]);
        session(['skill_usage_counts' => []]);
        
        if (in_array($mode, ['duo', 'league_individual', 'league_team'])) {
            return redirect()->route('game.resume', ['mode' => $mode]);
        }
        
        return redirect()->route('game.question', ['mode' => $mode]);
    }
    
    protected function generateAndStoreQuestionsForMatch(array $gameState, $user): void
    {
        $matchId = $gameState['match_id'] ?? $gameState['lobby_code'] ?? null;
        $mode = $gameState['mode'] ?? 'duo';
        
        if (!$matchId) {
            Log::warning('No match_id found for question generation');
            return;
        }
        
        $firestoreService = $this->getFirestoreServiceForMode($mode);
        
        if ($firestoreService->hasQuestions($matchId)) {
            Log::info("Questions already generated for {$mode} match {$matchId}");
            return;
        }
        
        $theme = $gameState['theme'] ?? 'Culture générale';
        $totalQuestions = $gameState['total_questions'] ?? 10;
        $niveau = $gameState['niveau'] ?? 1;
        $language = $user->preferred_language ?? 'fr';
        
        $questions = [];
        $usedQuestionIds = [];
        $usedAnswers = [];
        $sessionUsedAnswers = [];
        $sessionUsedQuestionTexts = [];
        
        for ($i = 1; $i <= $totalQuestions; $i++) {
            $generatedQuestion = $this->questionService->generateQuestion(
                $theme,
                $niveau,
                $i,
                $usedQuestionIds,
                $usedAnswers,
                $sessionUsedAnswers,
                $sessionUsedQuestionTexts,
                null,
                false,
                $language
            );
            
            if ($generatedQuestion) {
                $question = [
                    'id' => $generatedQuestion['id'] ?? uniqid(),
                    'text' => $generatedQuestion['question_text'] ?? $generatedQuestion['text'] ?? '',
                    'answers' => $generatedQuestion['answers'] ?? [],
                    'correct_index' => $generatedQuestion['correct_id'] ?? $generatedQuestion['correct_index'] ?? 0,
                    'sub_theme' => $generatedQuestion['sub_theme'] ?? '',
                ];
                
                $questions[] = $question;
                
                $usedQuestionIds[] = $question['id'];
                $sessionUsedQuestionTexts[] = $question['text'];
                foreach ($question['answers'] as $answer) {
                    $answerText = is_array($answer) ? ($answer['text'] ?? '') : $answer;
                    if ($answerText) {
                        $usedAnswers[] = $answerText;
                        $sessionUsedAnswers[] = $answerText;
                    }
                }
            }
        }
        
        if (!empty($questions)) {
            $firestoreService->storeMatchQuestions($matchId, $questions);
            
            session(['match_questions' => $questions]);
            session(['match_questions_id' => $matchId]);
            session(['match_questions_mode' => $mode]);
            
            Log::info("Generated and stored " . count($questions) . " questions for {$mode} match {$matchId}");
        }
    }
    
    protected function getFirestoreServiceForMode(string $mode)
    {
        switch ($mode) {
            case 'league_individual':
                return $this->leagueIndividualFirestoreService;
            case 'league_team':
                return $this->leagueTeamFirestoreService;
            case 'duo':
            default:
                return $this->duoFirestoreService;
        }
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
            'question' => $question,
            'question_text' => $question['text'],
            'answers' => $question['answers'],
            'correct_answer_index' => $question['correct_index'],
            'current' => $gameState['current_question'],
            'current_question' => $gameState['current_question'],
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
        
        return view('game_question', ['params' => $params]);
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
        session(['last_buzz_time' => $buzzTime]);
        session(['last_buzz_winner' => 'player']);
        session(['buzz_time' => $buzzTime]);
        session(['buzzed' => true]);
        
        $result['success'] = true;
        
        if ($mode === 'solo') {
            $result['redirect'] = route('solo.answer') . 
                '?buzz_time=' . urlencode($buzzTime) . 
                '&buzz_winner=player';
        } else {
            $result['redirect'] = route('game.answers', ['mode' => $mode]) . 
                '?buzz_time=' . urlencode($buzzTime) . 
                '&buzz_winner=player';
        }
        
        return response()->json($result);
    }
    
    public function showAnswers(Request $request, string $mode)
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
        $avatarData = $this->getAvatarData($user);
        
        $buzzTime = $request->query('buzz_time', session('last_buzz_time', 0));
        $buzzWinner = $request->query('buzz_winner', session('last_buzz_winner', 'player'));
        
        $params = [
            'mode' => $mode,
            'opponent_type' => $provider->getOpponentType(),
            'opponent_info' => $opponentInfo,
            'question' => $question,
            'answers' => $question['answers'],
            'correct_answer_index' => $question['correct_index'],
            'current' => $gameState['current_question'],
            'nb_questions' => $gameState['total_questions'],
            'theme' => $gameState['theme'] ?? 'Culture générale',
            'sub_theme' => $question['sub_theme'] ?? '',
            'score' => $gameState['player_score'] ?? 0,
            'opponent_score' => $gameState['opponent_score'] ?? 0,
            'current_round' => $gameState['current_round'] ?? 1,
            'player_rounds_won' => $gameState['player_rounds_won'] ?? 0,
            'opponent_rounds_won' => $gameState['opponent_rounds_won'] ?? 0,
            'avatar' => $avatarData['name'],
            'avatar_skills_full' => $avatarData['skills_full'],
            'buzz_time' => (float)$buzzTime,
            'buzz_winner' => $buzzWinner,
        ];
        
        if (in_array($mode, ['duo', 'league_individual', 'master'])) {
            $params['match_id'] = $gameState['match_id'] ?? null;
        }
        
        return view('game_answers', ['params' => $params]);
    }
    
    public function showTransition(Request $request, string $mode)
    {
        if ($mode === 'solo') {
            $queryString = $request->getQueryString();
            $url = route('solo.timeout');
            if ($queryString) {
                $url .= '?' . $queryString;
            }
            return redirect($url);
        }
        
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $provider = $this->getProvider($mode);
        $provider->setGameState($gameState);
        
        $opponentInfo = $provider->getOpponentInfo();
        
        $lastResult = session('last_answer_result', []);
        
        $wasCorrect = $request->query('correct', '0') === '1';
        $pointsEarned = (int)$request->query('points', $lastResult['points_earned'] ?? ($wasCorrect ? 10 : 0));
        $noBuzz = $request->query('no_buzz', '0') === '1';
        $timeout = $request->query('timeout', '0') === '1';
        $opponentAnswered = $request->query('opponent_answered', '0') === '1';
        
        $buzzWinner = session('last_buzz_winner', 'player');
        $buzzTime = session('last_buzz_time', 0);
        
        $opponentPointsEarned = $lastResult['opponent_points_earned'] ?? 0;
        $opponentWasCorrect = $lastResult['opponent_was_correct'] ?? false;
        $correctAnswer = $lastResult['correct_answer'] ?? '';
        
        $playerScore = $lastResult['player_score'] ?? ($gameState['player_score'] ?? 0);
        $opponentScore = $lastResult['opponent_score'] ?? ($gameState['opponent_score'] ?? 0);
        
        if ($noBuzz || $timeout) {
            if ($provider->getOpponentType() === 'ai') {
                $opponentWasCorrect = rand(0, 100) < 70;
                if ($opponentWasCorrect) {
                    $opponentPointsEarned = 10;
                }
            }
        } elseif ($opponentAnswered && $buzzWinner !== 'player') {
            $opponentWasCorrect = $wasCorrect;
            if ($opponentWasCorrect) {
                $opponentPointsEarned = 10;
            }
        }
        
        if (empty($correctAnswer)) {
            $question = $this->getCurrentQuestion($gameState);
            if ($question && isset($question['answers'][$question['correct_index']])) {
                $answer = $question['answers'][$question['correct_index']];
                $correctAnswer = is_array($answer) ? ($answer['text'] ?? '') : $answer;
            }
        }
        
        $currentQuestion = $gameState['current_question'] ?? 1;
        $totalQuestions = $gameState['total_questions'] ?? 10;
        $isLastQuestion = $currentQuestion >= $totalQuestions;
        
        $params = [
            'mode' => $mode,
            'opponent_type' => $provider->getOpponentType(),
            'opponent_info' => $opponentInfo,
            'opponent_id' => $gameState['opponent_id'] ?? null,
            'match_id' => $gameState['match_id'] ?? null,
            'current' => $currentQuestion,
            'nb_questions' => $totalQuestions,
            'theme' => $gameState['theme'] ?? 'Culture générale',
            'score' => $playerScore,
            'opponent_score' => $opponentScore,
            'current_round' => $gameState['current_round'] ?? 1,
            'player_rounds_won' => $gameState['player_rounds_won'] ?? 0,
            'opponent_rounds_won' => $gameState['opponent_rounds_won'] ?? 0,
            'was_correct' => $wasCorrect,
            'points_earned' => $pointsEarned,
            'opponent_points_earned' => $opponentPointsEarned,
            'buzz_winner' => $buzzWinner,
            'buzz_time' => $buzzTime,
            'opponent_was_correct' => $opponentWasCorrect,
            'no_buzz' => $noBuzz,
            'timeout' => $timeout,
            'correct_answer' => $correctAnswer,
            'is_last_question' => $isLastQuestion,
            'is_host' => ($gameState['host_id'] ?? null) === $user->id,
        ];
        
        session()->forget('last_answer_result');
        
        return view('game_transition', ['params' => $params]);
    }
    
    public function advanceToNextQuestion(Request $request, string $mode)
    {
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return redirect()->route($this->getModeIndexRoute($mode))->with('error', __('Aucune partie en cours'));
        }
        
        $gameState['current_question'] = ($gameState['current_question'] ?? 1) + 1;
        
        session(['game_state' => $gameState]);
        
        session()->forget([
            'last_buzz_time', 
            'last_buzz_winner',
            'last_answer_result',
            'unified_current_question',
            'unified_question_number',
        ]);
        
        if ($mode === 'solo') {
            session(['current_question_number' => $gameState['current_question']]);
            session()->forget('current_question');
            return redirect()->route('solo.game');
        }
        
        return redirect()->route('game.question', ['mode' => $mode]);
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
            'is_correct' => 'boolean',
            'buzz_time' => 'numeric',
        ]);
        
        $question = $this->getCurrentQuestion($gameState);
        $correctIndex = $question['correct_index'] ?? 0;
        $isCorrect = $validated['answer_id'] === $correctIndex;
        
        $result = $provider->submitAnswer(
            $validated['answer_id'],
            $isCorrect
        );
        
        $result['is_correct'] = $isCorrect;
        $result['was_correct'] = $isCorrect;
        $result['correct_index'] = $correctIndex;
        
        $opponentWasCorrect = false;
        $opponentPointsEarned = 0;
        
        if ($provider->getOpponentType() === 'ai') {
            $opponentResult = $provider->handleOpponentAnswer();
            $result['opponent'] = $opponentResult;
            $opponentWasCorrect = $opponentResult['correct'] ?? false;
            $opponentPointsEarned = $opponentResult['points'] ?? 0;
        }
        
        $updatedState = $provider->getGameState();
        
        $question = $this->getCurrentQuestion($gameState);
        $correctAnswer = '';
        if ($question && isset($question['answers'][$question['correct_index']])) {
            $answer = $question['answers'][$question['correct_index']];
            $correctAnswer = is_array($answer) ? ($answer['text'] ?? '') : $answer;
        }
        
        session(['last_answer_result' => [
            'was_correct' => $isCorrect,
            'points_earned' => $result['points'] ?? ($isCorrect ? 10 : 0),
            'opponent_was_correct' => $opponentWasCorrect,
            'opponent_points_earned' => $opponentPointsEarned,
            'correct_answer' => $correctAnswer,
            'player_score' => $updatedState['player_score'] ?? 0,
            'opponent_score' => $updatedState['opponent_score'] ?? 0,
        ]]);
        
        session(['game_state' => $updatedState]);
        
        $isLastQuestion = ($gameState['current_question'] ?? 1) >= ($gameState['total_questions'] ?? 10);
        
        $result['redirect'] = route('game.transition', ['mode' => $mode]) . 
            '?correct=' . ($isCorrect ? '1' : '0') .
            '&points=' . ($result['points'] ?? ($isCorrect ? 10 : 0));
        $result['has_next_question'] = !$isLastQuestion;
        $result['current_question'] = $gameState['current_question'];
        
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
        
        // Nettoyer toutes les sessions de jeu incluant les skills
        session()->forget(['game_state', 'game_mode', 'used_skills', 'skill_usage_counts']);
        
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
    
    public function fetchQuestionJson(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return response()->json(['error' => __('Aucune partie en cours')], 400);
        }
        
        $isHost = ($gameState['host_id'] ?? null) === $user->id;
        if (!$isHost) {
            return response()->json(['error' => __('Seul l\'hôte peut récupérer les questions')], 403);
        }
        
        $questionNumber = $request->input('question_number', $gameState['current_question'] ?? 1);
        
        $tempState = $gameState;
        $tempState['current_question'] = $questionNumber;
        
        $cachedQuestion = session("prefetched_question_{$questionNumber}");
        if ($cachedQuestion) {
            session()->forget("prefetched_question_{$questionNumber}");
            $question = $cachedQuestion;
        } else {
            session()->forget(['unified_current_question', 'unified_question_number']);
            $question = $this->getCurrentQuestion($tempState);
            
            if ($question) {
                $gameState['current_question'] = $questionNumber;
                session(['game_state' => $gameState]);
            }
        }
        
        if (!$question) {
            return response()->json(['error' => __('Impossible de générer la question')], 500);
        }
        
        $answers = [];
        foreach ($question['answers'] as $index => $answer) {
            $answerText = is_array($answer) ? ($answer['text'] ?? $answer) : $answer;
            $answers[] = [
                'index' => $index,
                'text' => $answerText,
            ];
        }
        
        return response()->json([
            'success' => true,
            'question_number' => $questionNumber,
            'total_questions' => $gameState['total_questions'] ?? 10,
            'question_text' => $question['text'] ?? '',
            'answers' => $answers,
            'theme' => $gameState['theme'] ?? 'Culture générale',
            'sub_theme' => $question['sub_theme'] ?? '',
            'niveau' => $gameState['niveau'] ?? 1,
            'player_score' => $gameState['player_score'] ?? 0,
            'opponent_score' => $gameState['opponent_score'] ?? 0,
        ]);
    }
    
    public function useSkill(Request $request, string $mode)
    {
        $validated = $request->validate([
            'skill_id' => 'required|string',
        ]);
        
        $skillId = $validated['skill_id'];
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return response()->json(['error' => __('Aucune partie en cours')], 400);
        }
        
        $skill = SkillCatalog::getSkill($skillId);
        
        if (!$skill) {
            return response()->json(['error' => __('Skill inconnu')], 400);
        }
        
        $skillUsageCounts = session('skill_usage_counts', []);
        $currentUsage = $skillUsageCounts[$skillId] ?? 0;
        $maxUses = $skill['uses_per_match'] ?? 1;
        
        if ($maxUses > 0 && $currentUsage >= $maxUses) {
            return response()->json([
                'error' => __('Skill épuisé'),
                'current_usage' => $currentUsage,
                'max_uses' => $maxUses,
            ], 400);
        }
        
        $skillUsageCounts[$skillId] = $currentUsage + 1;
        session(['skill_usage_counts' => $skillUsageCounts]);
        
        $usedSkills = session('used_skills', []);
        if ($maxUses > 0 && $skillUsageCounts[$skillId] >= $maxUses) {
            if (!in_array($skillId, $usedSkills)) {
                $usedSkills[] = $skillId;
                session(['used_skills' => $usedSkills]);
            }
        }
        
        $usesLeft = $maxUses > 0 ? max(0, $maxUses - $skillUsageCounts[$skillId]) : -1;
        $isFullyUsed = $maxUses > 0 && $usesLeft === 0;
        
        return response()->json([
            'success' => true,
            'skill_id' => $skillId,
            'is_attack' => SkillCatalog::isAttackSkill($skillId),
            'affects_opponent' => SkillCatalog::affectsOpponent($skillId),
            'skill_info' => $skill,
            'usage_count' => $skillUsageCounts[$skillId],
            'uses_left' => $usesLeft,
            'is_fully_used' => $isFullyUsed,
        ]);
    }
    
    protected function getCurrentQuestion(array $gameState): ?array
    {
        $currentQuestionNumber = $gameState['current_question'] ?? 1;
        $totalQuestions = $gameState['total_questions'] ?? 10;
        $mode = $gameState['mode'] ?? 'solo';
        
        if ($currentQuestionNumber > $totalQuestions) {
            return null;
        }
        
        $currentQuestion = session('unified_current_question');
        $storedQuestionNumber = session('unified_question_number', 0);
        
        if ($currentQuestion && $storedQuestionNumber === $currentQuestionNumber) {
            return $currentQuestion;
        }
        
        if (in_array($mode, ['duo', 'league_individual', 'league_team'])) {
            $question = $this->getSharedQuestion($gameState, $currentQuestionNumber);
            if ($question) {
                session([
                    'unified_current_question' => $question,
                    'unified_question_number' => $currentQuestionNumber,
                ]);
                return $question;
            }
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
    
    protected function getSharedQuestion(array $gameState, int $questionNumber): ?array
    {
        $matchQuestions = session('match_questions');
        $storedMatchId = session('match_questions_id');
        $storedMode = session('match_questions_mode');
        $currentMatchId = $gameState['match_id'] ?? $gameState['lobby_code'] ?? null;
        $mode = $gameState['mode'] ?? 'duo';
        
        if ($matchQuestions && $storedMatchId === $currentMatchId && $storedMode === $mode) {
            $index = $questionNumber - 1;
            if (isset($matchQuestions[$index])) {
                Log::info("Using cached question {$questionNumber} from session for {$mode} match {$currentMatchId}");
                return $matchQuestions[$index];
            }
        }
        
        if ($currentMatchId) {
            $firestoreService = $this->getFirestoreServiceForMode($mode);
            $firestoreQuestions = $firestoreService->getMatchQuestions($currentMatchId);
            
            if ($firestoreQuestions) {
                session(['match_questions' => $firestoreQuestions]);
                session(['match_questions_id' => $currentMatchId]);
                session(['match_questions_mode' => $mode]);
                
                $index = $questionNumber - 1;
                if (isset($firestoreQuestions[$index])) {
                    Log::info("Retrieved question {$questionNumber} from Firestore for {$mode} match {$currentMatchId}");
                    return $firestoreQuestions[$index];
                }
            }
        }
        
        Log::warning("Could not get shared question {$questionNumber} for {$mode} match {$currentMatchId}");
        return null;
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
            'opponent_choice' => 'nullable|string|in:bonus,efficiency,sudden_death',
        ]);
        
        $gameState = session('game_state', []);
        $myChoice = $validated['choice'];
        $opponentChoice = $validated['opponent_choice'] ?? null;
        
        $gameState['tiebreaker_my_choice'] = $myChoice;
        
        $isMultiplayer = in_array($mode, ['duo', 'league_individual', 'league_team']);
        
        if ($isMultiplayer && $opponentChoice) {
            $finalChoice = $this->determineTiebreakerChoice($myChoice, $opponentChoice);
            $gameState['tiebreaker_choice'] = $finalChoice;
            $gameState['tiebreaker_choice_reason'] = ($myChoice === $opponentChoice) 
                ? 'same_choice' 
                : 'third_option';
        } else {
            $gameState['tiebreaker_choice'] = $myChoice;
        }
        
        session(['game_state' => $gameState]);
        
        $finalChoice = $gameState['tiebreaker_choice'];
        
        switch ($finalChoice) {
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
    
    protected function determineTiebreakerChoice(string $choice1, string $choice2): string
    {
        if ($choice1 === $choice2) {
            return $choice1;
        }
        
        $allOptions = ['bonus', 'efficiency', 'sudden_death'];
        $chosenOptions = [$choice1, $choice2];
        
        foreach ($allOptions as $option) {
            if (!in_array($option, $chosenOptions)) {
                return $option;
            }
        }
        
        return 'bonus';
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
    
    /**
     * Handle opponent forfeit/disconnect
     * Called when opponent abandons the match
     */
    public function handleForfeit(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState)) {
            return response()->json(['error' => __('Aucune partie en cours')], 400);
        }
        
        $validated = $request->validate([
            'opponent_id' => 'required',
            'reason' => 'nullable|string|in:disconnect,abandon,timeout',
            'match_id' => 'nullable|string'
        ]);
        
        $opponentId = $validated['opponent_id'];
        $reason = $validated['reason'] ?? 'disconnect';
        $requestMatchId = $validated['match_id'] ?? null;
        
        // Verify match_id matches session if provided (security check)
        $sessionMatchId = $gameState['match_id'] ?? $gameState['session_id'] ?? null;
        if ($requestMatchId && $sessionMatchId && $requestMatchId !== $sessionMatchId) {
            Log::warning('[Forfeit] Match ID mismatch - possible security issue', [
                'request_match_id' => $requestMatchId,
                'session_match_id' => $sessionMatchId,
                'user_id' => $user->id
            ]);
            return response()->json(['error' => __('ID de match invalide')], 403);
        }
        
        // Collect all valid opponent identifiers from session
        $validOpponentIds = [];
        
        // Standard opponent_id
        if (!empty($gameState['opponent_id'])) {
            $validOpponentIds[] = (string) $gameState['opponent_id'];
        }
        
        // Team opponent ID
        if (!empty($gameState['opponent_team_id'])) {
            $validOpponentIds[] = (string) $gameState['opponent_team_id'];
        }
        
        // Team members (for League Team mode)
        if (!empty($gameState['opponent_team_members'])) {
            foreach ($gameState['opponent_team_members'] as $member) {
                if (!empty($member['id'])) {
                    $validOpponentIds[] = (string) $member['id'];
                }
            }
        }
        
        // Player list (for Master mode)
        if (!empty($gameState['players'])) {
            foreach ($gameState['players'] as $playerId => $playerData) {
                if ((string) $playerId !== (string) $user->id) {
                    $validOpponentIds[] = (string) $playerId;
                }
            }
        }
        
        // Validate opponent_id against known valid IDs
        $opponentIdStr = (string) $opponentId;
        if (!empty($validOpponentIds) && !in_array($opponentIdStr, $validOpponentIds)) {
            Log::warning('[Forfeit] Invalid opponent ID - not in game session', [
                'received' => $opponentId,
                'valid_ids' => $validOpponentIds,
                'mode' => $mode,
                'user_id' => $user->id
            ]);
            return response()->json(['error' => __('Adversaire non reconnu')], 403);
        }
        
        Log::info('[Forfeit] Opponent forfeited', [
            'mode' => $mode,
            'winner_id' => $user->id,
            'forfeited_id' => $opponentId,
            'reason' => $reason,
            'match_id' => $gameState['match_id'] ?? null
        ]);
        
        // Update game state for forfeit win
        $gameState['forfeit'] = true;
        $gameState['forfeit_reason'] = $reason;
        $gameState['forfeit_opponent_id'] = $opponentId;
        $gameState['player_rounds_won'] = 2; // Auto-win
        $gameState['opponent_rounds_won'] = 0;
        $gameState['tiebreaker_winner'] = 'player';
        
        session(['game_state' => $gameState]);
        
        // Update player stats
        $this->updateForfeitStats($user, $opponentId, $mode, $gameState);
        
        // Handle bet refund if applicable
        $this->handleForfeitBetReward($user, $gameState);
        
        return response()->json([
            'success' => true,
            'redirect_url' => route('game.match-result', ['mode' => $mode]),
            'forfeit' => true,
            'reason' => $reason
        ]);
    }
    
    /**
     * Update stats after forfeit victory
     */
    protected function updateForfeitStats($user, $opponentId, string $mode, array $gameState): void
    {
        try {
            // Update user match history
            $matchHistory = $user->recent_matches ?? [];
            
            // Add forfeit win to history
            $matchHistory[] = [
                'mode' => $mode,
                'opponent_id' => $opponentId,
                'result' => 'victory',
                'forfeit' => true,
                'date' => now()->toISOString()
            ];
            
            // Keep only last 10 matches
            $matchHistory = array_slice($matchHistory, -10);
            
            $user->recent_matches = $matchHistory;
            $user->wins = ($user->wins ?? 0) + 1;
            $user->save();
            
            Log::info('[Forfeit] Stats updated', [
                'user_id' => $user->id,
                'new_wins' => $user->wins
            ]);
        } catch (\Exception $e) {
            Log::error('[Forfeit] Stats update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }
    }
    
    /**
     * Handle bet reward after forfeit
     */
    protected function handleForfeitBetReward($user, array $gameState): void
    {
        if (empty($gameState['bet_info'])) {
            return;
        }
        
        try {
            $betInfo = $gameState['bet_info'];
            $betAmount = $betInfo['amount'] ?? 0;
            
            if ($betAmount <= 0) {
                return;
            }
            
            // Full bet winnings (own bet + opponent's bet)
            $totalWinnings = $betAmount * 2;
            
            $user->coins = ($user->coins ?? 0) + $totalWinnings;
            $user->save();
            
            Log::info('[Forfeit] Bet reward credited', [
                'user_id' => $user->id,
                'bet_amount' => $betAmount,
                'total_winnings' => $totalWinnings
            ]);
        } catch (\Exception $e) {
            Log::error('[Forfeit] Bet reward failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }
    }
    
    /**
     * Show forfeit result page
     */
    public function showForfeitResult(Request $request, string $mode)
    {
        $user = Auth::user();
        $gameState = session('game_state', []);
        
        if (empty($gameState) || empty($gameState['forfeit'])) {
            return redirect()->route($this->getModeIndexRoute($mode));
        }
        
        $reasonLabels = [
            'disconnect' => __('Déconnexion de l\'adversaire'),
            'abandon' => __('Abandon du joueur adverse'),
            'timeout' => __('Temps d\'attente dépassé')
        ];
        
        $params = [
            'mode' => $mode,
            'match_result' => [
                'winner' => 'player',
                'victory' => true,
                'forfeit' => true,
                'forfeit_reason' => $gameState['forfeit_reason'] ?? 'disconnect',
                'forfeit_label' => $reasonLabels[$gameState['forfeit_reason'] ?? 'disconnect'] ?? __('Forfait'),
                'player_rounds_won' => 2,
                'opponent_rounds_won' => 0,
                'bet_info' => $gameState['bet_info'] ?? null
            ],
            'opponent_info' => [
                'id' => $gameState['forfeit_opponent_id'] ?? null,
                'name' => $gameState['opponent_name'] ?? __('Adversaire'),
                'avatar' => $gameState['opponent_avatar'] ?? 'default'
            ]
        ];
        
        // Nettoyer toutes les sessions de jeu incluant les skills
        session()->forget(['game_state', 'game_mode', 'used_skills', 'skill_usage_counts']);
        
        return view('game_match_result', ['params' => $params]);
    }
}
