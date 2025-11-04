<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LeagueIndividualMatch;
use App\Services\LeagueIndividualService;
use App\Services\GameStateService;
use App\Services\BuzzManagerService;
use App\Services\DivisionService;
use App\Services\LeagueIndividualFirestoreService;

class LeagueIndividualController extends Controller
{
    public function __construct(
        private LeagueIndividualService $leagueService,
        private GameStateService $gameStateService,
        private BuzzManagerService $buzzManager,
        private DivisionService $divisionService,
        private LeagueIndividualFirestoreService $firestoreService
    ) {}

    /**
     * Page d'accueil Ligue Individuel
     */
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

        return view('league_individual_lobby', [
            'stats' => $stats,
            'division' => $division,
            'rankings' => $rankings,
            'rank' => $rank,
        ]);
    }

    /**
     * API: Initialise la Ligue Individuel
     */
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

    /**
     * API: Vérifie si la Ligue est initialisée
     */
    public function checkInitialized()
    {
        $user = Auth::user();
        $isInitialized = $this->leagueService->isInitialized($user);

        return response()->json([
            'success' => true,
            'initialized' => $isInitialized,
        ]);
    }

    /**
     * API: Crée un match avec matchmaking aléatoire
     */
    public function createMatch()
    {
        $user = Auth::user();

        if (!$this->leagueService->isInitialized($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez d\'abord initialiser votre Ligue',
            ], 400);
        }

        $opponent = $this->leagueService->findRandomOpponent($user);

        if (!$opponent) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun adversaire disponible dans votre division',
            ], 404);
        }

        $match = $this->leagueService->createMatch($user, $opponent);

        $this->firestoreService->createMatchSession($match->id, [
            'player1_id' => $match->player1_id,
            'player2_id' => $match->player2_id,
            'player1_name' => $user->name,
            'player2_name' => $opponent->name,
            'questionStartTime' => microtime(true),
        ]);

        return response()->json([
            'success' => true,
            'match_id' => $match->id,
            'opponent' => $opponent->only(['id', 'name', 'avatar_url']),
        ]);
    }

    /**
     * API: Récupère l'état du jeu
     */
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

    /**
     * API: Enregistre un buzz
     */
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

        $this->firestoreService->recordBuzz($match->id, $playerId, $buzz['server_time']);

        return response()->json([
            'success' => true,
            'buzz' => $buzz,
        ]);
    }

    /**
     * API: Soumet une réponse
     */
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

        $this->firestoreService->updateScores(
            $match->id,
            $gameState['player_score'] ?? 0,
            $gameState['opponent_score'] ?? 0
        );

        if ($hasMoreQuestions) {
            $this->firestoreService->nextQuestion(
                $match->id,
                $gameState['current_question_index'] ?? 1,
                microtime(true)
            );
        } elseif ($roundResult && !$this->gameStateService->isMatchFinished($gameState)) {
            $this->firestoreService->finishRound(
                $match->id,
                $gameState['current_round'] ?? 1,
                $gameState['player_rounds_won'] ?? 0,
                $gameState['opponent_rounds_won'] ?? 0
            );
        }

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

    /**
     * API: Termine le match
     */
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

        $this->firestoreService->deleteMatchSession($match->id);

        return response()->json([
            'success' => true,
            'match_result' => $matchResult,
            'points_earned' => $match->player1_id == $user->id ? $match->player1_points_earned : $match->player2_points_earned,
        ]);
    }

    /**
     * API: Synchronise l'état du jeu pour polling temps réel
     */
    public function syncGameState(LeagueIndividualMatch $match)
    {
        $user = Auth::user();

        if (!$match->isPlayerInMatch($user->id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $firestoreState = $this->firestoreService->syncGameState($match->id);

        return response()->json([
            'success' => true,
            'firestore_state' => $firestoreState,
            'status' => $match->status,
        ]);
    }

    /**
     * API: Récupère les classements par division
     */
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

    /**
     * API: Récupère les statistiques du joueur
     */
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
}
