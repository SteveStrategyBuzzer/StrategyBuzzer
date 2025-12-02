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
        private PlayerContactService $contactService
    ) {}

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
        
        // Chercher le joueur par code
        $opponent = \App\Services\PlayerCodeService::findByCode($request->player_code);
        
        if (!$opponent) {
            return response()->json([
                'success' => false,
                'message' => 'Joueur introuvable avec ce code',
            ], 404);
        }
        
        if ($opponent->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas vous inviter vous-même',
            ], 400);
        }

        $match = $this->matchmaking->createInvitation($user, $opponent->id);

        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
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
                'message' => 'Vous n\'êtes pas autorisé à accepter ce match.',
            ], 403);
        }

        $this->matchmaking->acceptMatch($match);

        $this->contactService->registerMutualContacts($match->player1_id, $match->player2_id);

        $gameState = $this->gameStateService->initializeGame([
            'players' => [
                ['id' => 'player', 'user_id' => $match->player1_id],
                ['id' => 'opponent', 'user_id' => $match->player2_id],
            ],
            'mode' => 'duo',
            'theme' => $request->input('theme', 'general'),
            'nb_questions' => 10,
            'niveau' => max($match->player1_level, $match->player2_level),
        ]);

        $gameState['buzzes'] = [];
        $gameState['question_start_time'] = microtime(true);

        $match->game_state = $gameState;
        $match->save();

        $this->firestoreService->createMatchSession($match->id, [
            'player1_id' => $match->player1_id,
            'player2_id' => $match->player2_id,
            'player1_name' => $match->player1->name ?? 'Player 1',
            'player2_name' => $match->player2->name ?? 'Player 2',
            'questionStartTime' => $gameState['question_start_time'],
        ]);

        return response()->json([
            'success' => true,
            'match' => $match->load(['player1', 'player2']),
            'gameState' => $gameState,
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

        return view('duo_lobby', [
            'stats' => $stats,
            'division' => $division,
            'rankings' => $rankings,
            'duoFullUnlocked' => $duoFullUnlocked,
            'choixNiveau' => $choixNiveau,
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

        return view('duo_game', [
            'match_id' => $match->id,
            'match' => $match->load(['player1', 'player2']),
        ]);
    }

    public function result(DuoMatch $match)
    {
        $user = Auth::user();
        
        if ($match->player1_id != $user->id && $match->player2_id != $user->id) {
            abort(403, 'Unauthorized');
        }

        $gameState = $match->game_state;
        $matchResult = $this->gameStateService->getMatchResult($gameState);
        
        $opponent = $match->player1_id == $user->id ? $match->player2 : $match->player1;
        $division = $this->divisionService->getOrCreateDivision($user, 'duo');
        
        // Ensure both players are in each other's contact book after match
        $this->contactService->registerMutualContacts($match->player1_id, $match->player2_id);
        
        $accuracy = 0;
        $total = ($gameState['global_stats']['correct'] ?? 0) + ($gameState['global_stats']['incorrect'] ?? 0);
        if ($total > 0) {
            $accuracy = round(($gameState['global_stats']['correct'] ?? 0) / $total * 100);
        }

        $pointsEarned = $matchResult['points_earned'] ?? 0;

        return view('duo_result', [
            'match_result' => $matchResult,
            'opponent' => $opponent,
            'opponent_id' => $opponent->id ?? null,
            'opponent_name' => $opponent->name ?? 'Adversaire',
            'new_division' => $division,
            'points_earned' => $pointsEarned,
            'global_stats' => $gameState['global_stats'] ?? [],
            'accuracy' => $accuracy,
            'round_details' => $gameState['answered_questions'] ?? [],
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
        
        $invitations = DuoMatch::where('player2_id', $user->id)
            ->where('status', 'waiting')
            ->with('player1')
            ->get()
            ->map(function ($match) {
                return [
                    'match_id' => $match->id,
                    'from_player' => $match->player1,
                ];
            });

        return response()->json([
            'success' => true,
            'invitations' => $invitations,
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
}
