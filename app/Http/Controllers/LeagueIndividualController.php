<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\LeagueIndividualMatch;
use App\Models\LeagueIndividualStat;
use App\Services\LeagueIndividualService;
use App\Services\GameStateService;
use App\Services\BuzzManagerService;
use App\Services\DivisionService;
use App\Services\GameServerService;

class LeagueIndividualController extends Controller
{
    public function __construct(
        private LeagueIndividualService $leagueService,
        private GameStateService $gameStateService,
        private BuzzManagerService $buzzManager,
        private DivisionService $divisionService,
        private GameServerService $gameServerService
    ) {}

    public function index()
    {
        $user = Auth::user();
        $isInitialized = $this->leagueService->isInitialized($user);

        if (!$isInitialized) {
            $stats = $this->leagueService->initializeLeague($user);
            $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
            
            return view('league_individual_welcome', [
                'stats' => $stats,
                'division' => $division,
            ]);
        }

        $stats = $this->leagueService->getOrCreateStats($user);
        $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
        $rankings = $this->leagueService->getRankingsByDivision($division->division, 10);
        $rank = $this->leagueService->getPlayerRank($user);
        
        $efficiency = 0;
        if ($stats && $stats->matches_played > 0) {
            $efficiency = ($stats->matches_won / $stats->matches_played) * 100;
        }
        
        $divisionEmojis = [
            'bronze' => '🥉',
            'argent' => '🥈',
            'or' => '🥇',
            'platine' => '💎',
            'diamant' => '💠',
            'legende' => '👑',
        ];
        $divisionEmoji = $divisionEmojis[$division->division ?? 'bronze'] ?? '🥉';

        $activeMatch = LeagueIndividualMatch::where(function ($query) use ($user) {
                $query->where('player1_id', $user->id)
                      ->orWhere('player2_id', $user->id);
            })
            ->where('status', 'in_progress')
            ->with(['player1', 'player2'])
            ->first();

        return view('league_individual_lobby', [
            'stats' => $stats,
            'division' => $division,
            'rankings' => $rankings,
            'rank' => $rank,
            'efficiency' => $efficiency,
            'divisionEmoji' => $divisionEmoji,
            'activeMatch' => $activeMatch,
        ]);
    }

    public function initialize()
    {
        $user = Auth::user();
        $stats = $this->leagueService->initializeLeague($user);
        $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'division' => $division,
        ]);
    }

    public function checkInitialized()
    {
        $user = Auth::user();
        $isInitialized = $this->leagueService->isInitialized($user);

        return response()->json([
            'success' => true,
            'initialized' => $isInitialized,
        ]);
    }

    public function createMatch(Request $request)
    {
        $user = Auth::user();

        if (!$this->leagueService->isInitialized($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez d\'abord initialiser votre Ligue',
            ], 400);
        }

        $opponentId = $request->input('opponent_id');
        $selectedDivision = $request->input('division', null);
        
        if ($opponentId) {
            $opponent = \App\Models\User::find($opponentId);
            
            if (!$opponent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Adversaire non trouvé',
                ], 404);
            }
        } else {
            $opponent = $this->leagueService->findRandomOpponent($user);

            if (!$opponent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun adversaire disponible dans votre division',
                ], 404);
            }
        }

        $match = $this->leagueService->createMatch($user, $opponent);

        return response()->json([
            'success' => true,
            'match_id' => $match->id,
            'opponent' => $opponent->only(['id', 'name', 'avatar_url']),
        ]);
    }
    
    private function mapDivisionToLevel(string $division): string
    {
        $mapping = [
            'bronze' => 'Facile',
            'argent' => 'Facile',
            'or' => 'Moyen',
            'platine' => 'Moyen',
            'diamant' => 'Difficile',
            'legende' => 'Difficile',
        ];
        
        return $mapping[$division] ?? 'Facile';
    }

    public function getGameState(LeagueIndividualMatch $match)
    {
        $user = Auth::user();

        if (!$match->isPlayerInMatch($user->id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'game_state' => $match->game_state,
            'status' => $match->status,
            'opponent' => $match->getOpponent($user->id),
        ]);
    }

    public function buzz(Request $request, LeagueIndividualMatch $match)
    {
        $user = Auth::user();

        if (!$match->isPlayerInMatch($user->id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $playerId = $match->player1_id == $user->id ? 'player' : 'opponent';
        $clientTime = $request->input('client_time');

        $buzz = $this->buzzManager->recordBuzz($playerId, $clientTime);

        $gameState = $match->game_state;
        $gameState['buzzes'][] = $buzz;
        $match->game_state = $gameState;
        $match->save();

        return response()->json([
            'success' => true,
            'buzz' => $buzz,
        ]);
    }

    public function submitAnswer(Request $request, LeagueIndividualMatch $match)
    {
        $user = Auth::user();

        if (!$match->isPlayerInMatch($user->id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'answer' => 'required|string',
        ]);

        $gameState = $match->game_state;
        $playerId = $match->player1_id == $user->id ? 'player' : 'opponent';
        $answer = $request->input('answer');
        $correctAnswer = $gameState['current_question']['correct_answer'] ?? null;

        $result = $this->buzzManager->processBuzzAnswer(
            $gameState,
            $playerId,
            $answer,
            $correctAnswer
        );

        $this->gameStateService->updateScore($gameState, $playerId, $result['points'], $result['is_correct']);
        $this->gameStateService->recordAnswer($gameState, [
            'question_id' => $gameState['current_question']['id'] ?? null,
            'question_text' => $gameState['current_question']['text'] ?? '',
            'player_answer' => $answer,
            'correct_answer' => $correctAnswer,
            'is_correct' => $result['is_correct'],
            'points_earned' => $result['points'],
            'buzz_time' => $result['server_time'] ?? null,
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
            'isCorrect' => $result['is_correct'],
            'points' => $result['points'],
            'gameState' => $gameState,
            'hasMoreQuestions' => $hasMoreQuestions,
            'roundFinished' => !$hasMoreQuestions,
            'roundResult' => $roundResult,
        ]);
    }

    public function finishMatch(LeagueIndividualMatch $match)
    {
        $user = Auth::user();

        if (!$match->isPlayerInMatch($user->id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $gameState = $match->game_state;

        if (!$this->gameStateService->isMatchFinished($gameState)) {
            return response()->json([
                'success' => false,
                'message' => 'Le match n\'est pas encore terminé',
            ], 400);
        }

        $matchResult = $this->gameStateService->getMatchResult($gameState);
        $this->leagueService->finishMatch($match, $matchResult);

        return response()->json([
            'success' => true,
            'match_result' => $matchResult,
            'points_earned' => $match->player1_id == $user->id ? $match->player1_points_earned : $match->player2_points_earned,
        ]);
    }

    public function syncGameState(LeagueIndividualMatch $match)
    {
        $user = Auth::user();

        if (!$match->isPlayerInMatch($user->id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
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
        $rankings = $this->leagueService->getRankingsByDivision($division);

        return response()->json([
            'success' => true,
            'division' => $division,
            'rankings' => $rankings,
        ]);
    }

    public function getMyStats()
    {
        $user = Auth::user();
        $stats = $this->leagueService->getOrCreateStats($user);
        $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
        $rank = $this->leagueService->getPlayerRank($user);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'division' => $division,
            'rank' => $rank,
        ]);
    }

    public function result(LeagueIndividualMatch $match)
    {
        $user = Auth::user();
        
        if (!$match->isPlayerInMatch($user->id)) {
            abort(403, 'Unauthorized');
        }

        $match->load(['player1', 'player2']);
        $gameState = $match->game_state;
        $matchResult = $this->gameStateService->getMatchResult($gameState);
        
        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;
        $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
        $opponentDivision = $this->divisionService->getOrCreateDivision($opponent, 'league_individual');
        $stats = $this->leagueService->getOrCreateStats($user);
        
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
        $coinsBonus = $coinsEarned > 0 ? $coinsEarned - $baseCoins : 0;

        return view('league_individual_results', [
            'match' => $match,
            'gameState' => $gameState,
            'stats' => $stats,
            'division' => $division,
            'match_result' => $matchResult,
            'opponent' => $opponent,
            'opponent_id' => $opponent->id ?? null,
            'opponent_name' => $opponent->name ?? 'Adversaire',
            'points_earned' => $pointsEarned,
            'coins_earned' => $coinsEarned,
            'coins_bonus' => $coinsBonus,
            'opponent_strength' => $opponentStrength,
            'global_stats' => $gameState['global_stats'] ?? [],
            'accuracy' => $accuracy,
            'round_details' => $gameState['answered_questions'] ?? [],
        ]);
    }

    public function getTemporaryAccessInfo()
    {
        $user = Auth::user();
        $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
        $tempInfo = $this->divisionService->getTemporaryAccessInfo($user);
        $nextDivision = $this->divisionService->getNextDivision($division->division);
        
        $accessCost = null;
        $canPurchase = false;
        
        if ($nextDivision) {
            $accessCost = $this->divisionService->getTemporaryAccessCost($nextDivision);
            $canPurchase = $this->divisionService->canPurchaseTemporaryAccess($user, $nextDivision);
        }

        return response()->json([
            'success' => true,
            'current_division' => $division->division,
            'next_division' => $nextDivision,
            'next_division_name' => $nextDivision ? $this->divisionService->getDivisionName($nextDivision) : null,
            'access_cost' => $accessCost,
            'can_purchase' => $canPurchase,
            'user_coins' => $user->coins ?? 0,
            'temporary_access' => $tempInfo,
        ]);
    }

    public function purchaseTemporaryAccess(Request $request)
    {
        $user = Auth::user();
        $targetDivision = $request->input('division');
        
        if (!$targetDivision || !isset(DivisionService::DIVISIONS[$targetDivision])) {
            return response()->json([
                'success' => false,
                'message' => 'Division invalide',
            ], 400);
        }
        
        $result = $this->divisionService->purchaseTemporaryAccess($user, $targetDivision);
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Erreur lors de l\'achat',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'division' => $result['division'],
            'division_name' => $this->divisionService->getDivisionName($result['division']),
            'expires_at' => $result['expires_at'],
            'cost' => $result['cost'],
            'remaining_coins' => $result['remaining_coins'],
        ]);
    }

    public function startGame(Request $request)
    {
        $user = Auth::user();
        $gameState = session('game_state');
        $lobbyCode = $request->input('lobby_code');
        
        // If no game_state but lobby_code provided, try to get match from lobby
        if ((!$gameState || !isset($gameState['match_id'])) && $lobbyCode) {
            $lobby = Cache::get('lobby:' . strtoupper($lobbyCode));
            
            if ($lobby) {
                $matchId = $lobby['match_id'] ?? null;
                
                // If no match_id in lobby, find match from lobby players
                if (!$matchId) {
                    $playerIds = array_keys($lobby['players'] ?? []);
                    if (count($playerIds) >= 2) {
                        $match = LeagueIndividualMatch::where(function($query) use ($playerIds) {
                            $query->where('player1_id', $playerIds[0])
                                  ->where('player2_id', $playerIds[1]);
                        })->orWhere(function($query) use ($playerIds) {
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
                    $gameState = [
                        'match_id' => $matchId,
                        'lobby_code' => $lobbyCode,
                        'theme' => $lobby['settings']['theme'] ?? 'Culture générale',
                        'nb_questions' => $lobby['settings']['nb_questions'] ?? 10,
                        'current_round' => 1,
                    ];
                    session(['game_state' => $gameState]);
                }
            }
        }
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('league.individual.lobby')->with('error', __('Aucune partie en cours'));
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Match introuvable'));
        }
        
        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }
        
        $match->status = 'in_progress';
        $match->started_at = now();
        $match->save();
        
        session(['game_state' => array_merge($gameState, [
            'started' => true,
            'started_at' => now()->timestamp,
        ])]);
        
        return redirect()->route('game.league.question');
    }

    public function showResume()
    {
        $user = Auth::user();
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('league.individual.lobby')->with('error', __('Aucune partie en cours'));
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Match introuvable'));
        }
        
        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }
        
        return $this->renderQuestionView($match, $user);
    }

    public function showQuestion()
    {
        $user = Auth::user();
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('league.individual.lobby')->with('error', __('Aucune partie en cours'));
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Match introuvable'));
        }
        
        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }
        
        return $this->renderQuestionView($match, $user);
    }

    public function showAnswer()
    {
        $user = Auth::user();
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('league.individual.lobby')->with('error', __('Aucune partie en cours'));
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Match introuvable'));
        }
        
        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }
        
        return $this->renderAnswerView($match, $user, $gameState);
    }

    public function showResult()
    {
        $user = Auth::user();
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('league.individual.lobby')->with('error', __('Aucune partie en cours'));
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Match introuvable'));
        }
        
        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
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
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
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
            'current_question' => $currentQuestion,
            'total_questions' => count($questions),
        ]);
    }

    public function useSkill(Request $request)
    {
        $user = Auth::user();
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return response()->json(['success' => false, 'error' => 'No active game'], 400);
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
        if (!$match) {
            return response()->json(['success' => false, 'error' => 'Match not found'], 404);
        }
        
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

    public function showMatchResult()
    {
        $user = Auth::user();
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return redirect()->route('league.individual.lobby')->with('error', __('Aucune partie en cours'));
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
        if (!$match) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Match introuvable'));
        }
        
        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            session()->forget('game_state');
            return redirect()->route('league.individual.lobby')->with('error', __('Vous n\'appartenez pas à ce match'));
        }
        
        $matchGameState = $match->game_state ?? [];
        $matchResult = $this->gameStateService->getMatchResult($matchGameState);
        
        $isPlayer1 = $match->player1_id == $user->id;
        $opponent = $isPlayer1 ? $match->player2 : $match->player1;
        $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
        
        $accuracy = 0;
        $total = ($matchGameState['global_stats']['correct'] ?? 0) + ($matchGameState['global_stats']['incorrect'] ?? 0);
        if ($total > 0) {
            $accuracy = round(($matchGameState['global_stats']['correct'] ?? 0) / $total * 100);
        }

        $pointsEarned = $isPlayer1 ? ($match->player1_points_earned ?? 0) : ($match->player2_points_earned ?? 0);
        $coinsEarned = $isPlayer1 ? ($match->player1_coins_earned ?? 0) : ($match->player2_coins_earned ?? 0);
        
        session()->forget('game_state');
        
        return view('league_individual_results', [
            'match' => $match,
            'gameState' => $matchGameState,
            'match_result' => $matchResult,
            'opponent' => $opponent,
            'opponent_id' => $opponent->id ?? null,
            'opponent_name' => $opponent->name ?? 'Adversaire',
            'division' => $division,
            'points_earned' => $pointsEarned,
            'coins_earned' => $coinsEarned,
            'coins_bonus' => 0,
            'opponent_strength' => 'equal',
            'global_stats' => $matchGameState['global_stats'] ?? [],
            'accuracy' => $accuracy,
            'round_details' => $matchGameState['answered_questions'] ?? [],
        ]);
    }

    public function handleForfeit(Request $request)
    {
        $user = Auth::user();
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return response()->json(['success' => false, 'error' => 'No active game'], 400);
        }
        
        $match = LeagueIndividualMatch::find($gameState['match_id']);
        
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
            'redirect_url' => route('league.individual.lobby'),
        ]);
    }

    protected function getMatchFromSession(): ?LeagueIndividualMatch
    {
        $gameState = session('game_state');
        
        if (!$gameState || !isset($gameState['match_id'])) {
            return null;
        }
        
        return LeagueIndividualMatch::find($gameState['match_id']);
    }

    protected function renderQuestionView(LeagueIndividualMatch $match, $user)
    {
        $gameServerUrl = env('GAME_SERVER_URL', 'http://localhost:3001');
        $roomId = $match->room_id ?? null;
        $lobbyCode = $match->lobby_code ?? null;
        
        $jwtToken = null;
        if ($roomId) {
            $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);
        }

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

        return response()->view('league_question', [
            'match_id' => $match->id,
            'match' => $match->load(['player1', 'player2']),
            'game_server_url' => $gameServerUrl,
            'room_id' => $roomId,
            'lobby_code' => $lobbyCode,
            'jwt_token' => $jwtToken,
            'skills' => $skills,
            'strategic_avatar' => $strategicAvatar,
            'currentUser' => $user,
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

    protected function renderAnswerView(LeagueIndividualMatch $match, $user, array $gameState)
    {
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
        
        $matchGameState = $match->game_state ?? [];
        $currentQuestion = $matchGameState['current_question_number'] ?? 1;
        
        $playerScore = $isPlayer1 
            ? ($matchGameState['player_scores_map']['player'] ?? 0) 
            : ($matchGameState['player_scores_map']['opponent'] ?? 0);
        $opponentScore = $isPlayer1 
            ? ($matchGameState['player_scores_map']['opponent'] ?? 0) 
            : ($matchGameState['player_scores_map']['player'] ?? 0);
        
        $stats = LeagueIndividualStat::firstOrCreate(['user_id' => $user->id], ['level' => 1]);
        $opponentStats = LeagueIndividualStat::firstOrCreate(['user_id' => $opponent->id], ['level' => 1]);

        $questionData = $gameState['current_question'] ?? [];
        $buzzWinner = $gameState['buzz_winner'] ?? 'player';

        return view('league_answer', [
            'match_id' => $match->id,
            'question' => $questionData,
            'buzz_winner' => $buzzWinner,
            'player_score' => $playerScore,
            'opponent_score' => $opponentScore,
            'current_question' => $currentQuestion,
            'total_questions' => 10,
            'player_info' => [
                'id' => $user->id,
                'name' => data_get($profileSettings, 'pseudonym', $user->name ?? 'Joueur'),
                'avatar' => data_get($profileSettings, 'avatar.url', 'images/avatars/standard/standard1.png'),
                'score' => $playerScore,
                'level' => $stats->level,
            ],
            'opponent_info' => [
                'id' => $opponent->id,
                'name' => data_get($opponentSettings, 'pseudonym', $opponent->name ?? 'Adversaire'),
                'avatar' => data_get($opponentSettings, 'avatar.url', 'images/avatars/standard/standard1.png'),
                'score' => $opponentScore,
                'level' => $opponentStats->level,
            ],
        ]);
    }

    protected function renderResultView(LeagueIndividualMatch $match, $user, array $gameState)
    {
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
        
        $matchGameState = $match->game_state ?? [];
        $currentQuestion = $matchGameState['current_question_number'] ?? 1;
        
        $playerScore = $isPlayer1 
            ? ($matchGameState['player_scores_map']['player'] ?? 0) 
            : ($matchGameState['player_scores_map']['opponent'] ?? 0);
        $opponentScore = $isPlayer1 
            ? ($matchGameState['player_scores_map']['opponent'] ?? 0) 
            : ($matchGameState['player_scores_map']['player'] ?? 0);
        
        $stats = LeagueIndividualStat::firstOrCreate(['user_id' => $user->id], ['level' => 1]);
        $opponentStats = LeagueIndividualStat::firstOrCreate(['user_id' => $opponent->id], ['level' => 1]);

        $lastAnswer = $gameState['last_answer'] ?? [];
        $isCorrect = $lastAnswer['is_correct'] ?? false;
        $pointsEarned = $lastAnswer['points'] ?? 0;

        return view('league_result', [
            'match_id' => $match->id,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'player_score' => $playerScore,
            'opponent_score' => $opponentScore,
            'current_question' => $currentQuestion,
            'total_questions' => 10,
            'player_info' => [
                'id' => $user->id,
                'name' => data_get($profileSettings, 'pseudonym', $user->name ?? 'Joueur'),
                'avatar' => data_get($profileSettings, 'avatar.url', 'images/avatars/standard/standard1.png'),
                'score' => $playerScore,
                'level' => $stats->level,
            ],
            'opponent_info' => [
                'id' => $opponent->id,
                'name' => data_get($opponentSettings, 'pseudonym', $opponent->name ?? 'Adversaire'),
                'avatar' => data_get($opponentSettings, 'avatar.url', 'images/avatars/standard/standard1.png'),
                'score' => $opponentScore,
                'level' => $opponentStats->level,
            ],
            'correct_answer' => $lastAnswer['correct_answer'] ?? '',
            'player_answer' => $lastAnswer['player_answer'] ?? '',
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

    public function game(LeagueIndividualMatch $match)
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
            $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);
        }

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

        return response()->view('league_question', [
            'match_id' => $match->id,
            'match' => $match->load(['player1', 'player2']),
            'game_server_url' => $gameServerUrl,
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
}
