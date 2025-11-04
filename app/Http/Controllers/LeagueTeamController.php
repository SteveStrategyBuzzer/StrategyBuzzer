<?php

namespace App\Http\Controllers;

use App\Services\TeamService;
use App\Services\LeagueTeamService;
use App\Services\LeagueTeamFirestoreService;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\LeagueTeamMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LeagueTeamController extends Controller
{
    private TeamService $teamService;
    private LeagueTeamService $leagueTeamService;
    private LeagueTeamFirestoreService $firestoreService;

    public function __construct(
        TeamService $teamService, 
        LeagueTeamService $leagueTeamService,
        LeagueTeamFirestoreService $firestoreService
    ) {
        $this->teamService = $teamService;
        $this->leagueTeamService = $leagueTeamService;
        $this->firestoreService = $firestoreService;
    }

    public function showTeamManagement()
    {
        $user = Auth::user();
        $team = $user->teams()->with(['captain', 'teamMembers.user'])->first();
        $pendingInvitations = $user->teamInvitations()
            ->with(['team.captain'])
            ->where('status', 'pending')
            ->get();

        return Inertia::render('LeagueTeamManagement', [
            'user' => $user,
            'team' => $team,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    public function showLobby()
    {
        $user = Auth::user();
        $team = $user->teams()->with(['captain', 'teamMembers.user'])->first();

        if (!$team) {
            return redirect()->route('league.team.management');
        }

        $rankings = $this->leagueTeamService->getTeamRankings($team->division);

        return Inertia::render('LeagueTeamLobby', [
            'user' => $user,
            'team' => $team,
            'rankings' => $rankings,
        ]);
    }

    public function createTeam(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'tag' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
        ]);

        try {
            $team = $this->teamService->createTeam(
                Auth::user(),
                $request->name,
                $request->tag
            );

            return response()->json([
                'success' => true,
                'team' => $team,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function invitePlayer(Request $request)
    {
        $request->validate([
            'player_name' => 'required|string',
        ]);

        $user = Auth::user();
        $team = $user->teams()->first();

        if (!$team) {
            return response()->json(['error' => 'Vous n\'êtes pas dans une équipe.'], 400);
        }

        try {
            $invitation = $this->teamService->invitePlayer(
                $team,
                $user,
                $request->player_name
            );

            return response()->json([
                'success' => true,
                'invitation' => $invitation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function acceptInvitation($invitationId)
    {
        $invitation = TeamInvitation::findOrFail($invitationId);

        try {
            $this->teamService->acceptInvitation($invitation, Auth::user());

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function declineInvitation($invitationId)
    {
        $invitation = TeamInvitation::findOrFail($invitationId);

        try {
            $this->teamService->declineInvitation($invitation, Auth::user());

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function leaveTeam()
    {
        $user = Auth::user();
        $team = $user->teams()->first();

        if (!$team) {
            return response()->json(['error' => 'Vous n\'êtes pas dans une équipe.'], 400);
        }

        try {
            $this->teamService->leaveTeam($team, $user);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function kickMember(Request $request)
    {
        $request->validate([
            'member_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $team = $user->teams()->first();

        if (!$team) {
            return response()->json(['error' => 'Vous n\'êtes pas dans une équipe.'], 400);
        }

        try {
            $this->teamService->kickMember($team, $user, $request->member_id);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function startMatchmaking()
    {
        $user = Auth::user();
        $team = $user->teams()->first();

        if (!$team) {
            return response()->json(['error' => 'Vous n\'êtes pas dans une équipe.'], 400);
        }

        try {
            $match = $this->leagueTeamService->initializeTeamMatch($team);

            $team1Players = $match->team1->teamMembers->map(fn($m) => [
                'id' => $m->user_id,
                'name' => $m->user->name ?? 'Player',
            ])->toArray();
            
            $team2Players = $match->team2->teamMembers->map(fn($m) => [
                'id' => $m->user_id,
                'name' => $m->user->name ?? 'Player',
            ])->toArray();

            $this->firestoreService->createMatchSession($match->id, [
                'team1_id' => $match->team1_id,
                'team2_id' => $match->team2_id,
                'team1_name' => $match->team1->name,
                'team2_name' => $match->team2->name,
                'team1_players' => $team1Players,
                'team2_players' => $team2Players,
                'questionStartTime' => microtime(true),
            ]);

            return response()->json([
                'success' => true,
                'match_id' => $match->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function showGame($matchId)
    {
        $match = LeagueTeamMatch::with(['team1.teamMembers.user', 'team2.teamMembers.user'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return redirect()->route('league.team.lobby')->with('error', 'Vous n\'êtes pas dans ce match.');
        }

        return Inertia::render('LeagueTeamGame', [
            'user' => $user,
            'match' => $match,
        ]);
    }

    public function getQuestion($matchId)
    {
        $match = LeagueTeamMatch::with(['team1.teamMembers', 'team2.teamMembers'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        try {
            $question = $this->leagueTeamService->getNextQuestion($match);

            return response()->json($question);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function buzz(Request $request, $matchId)
    {
        $request->validate([
            'buzz_time' => 'required|numeric',
        ]);

        $match = LeagueTeamMatch::with(['team1.teamMembers', 'team2.teamMembers'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        try {
            $result = $this->leagueTeamService->processBuzz($match, $user, $request->buzz_time);

            $teamId = $match->team1->teamMembers->contains('user_id', $user->id) ? 'team1' : 'team2';
            $this->firestoreService->recordBuzz($match->id, $teamId, $user->id, microtime(true));

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function submitAnswer(Request $request, $matchId)
    {
        $request->validate([
            'answer' => 'required|string',
        ]);

        $match = LeagueTeamMatch::with(['team1.teamMembers', 'team2.teamMembers'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        try {
            $result = $this->leagueTeamService->submitAnswer($match, $user, $request->answer);

            $gameState = $match->game_state ?? [];
            $this->firestoreService->updateScores(
                $match->id,
                $gameState['team1_score'] ?? 0,
                $gameState['team2_score'] ?? 0
            );

            if ($result['next_question'] ?? false) {
                $this->firestoreService->nextQuestion(
                    $match->id,
                    $gameState['current_question'] ?? 1,
                    microtime(true)
                );
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function showResults($matchId)
    {
        $match = LeagueTeamMatch::with(['team1.teamMembers.user', 'team2.teamMembers.user', 'winner'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return redirect()->route('league.team.lobby')->with('error', 'Vous n\'êtes pas dans ce match.');
        }

        $this->firestoreService->deleteMatchSession($match->id);

        return Inertia::render('LeagueTeamResults', [
            'user' => $user,
            'match' => $match,
        ]);
    }

    public function getRankings($division)
    {
        $rankings = $this->leagueTeamService->getTeamRankings($division);

        return response()->json($rankings);
    }

    /**
     * API: Synchronise l'état du jeu pour polling temps réel
     */
    public function syncGameState($matchId)
    {
        $match = LeagueTeamMatch::with(['team1.teamMembers', 'team2.teamMembers'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        $firestoreState = $this->firestoreService->syncGameState($match->id);

        return response()->json([
            'success' => true,
            'firestore_state' => $firestoreState,
            'status' => $match->status,
        ]);
    }
}
