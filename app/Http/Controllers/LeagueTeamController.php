<?php

namespace App\Http\Controllers;

use App\Services\TeamService;
use App\Services\LeagueTeamService;
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

    public function __construct(TeamService $teamService, LeagueTeamService $leagueTeamService)
    {
        $this->teamService = $teamService;
        $this->leagueTeamService = $leagueTeamService;
    }

    public function showTeamManagement()
    {
        $user = Auth::user();
        $team = $user->teams()->with(['captain', 'members.user'])->first();
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
        $team = $user->teams()->with(['captain', 'members.user'])->first();

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
        $match = LeagueTeamMatch::with(['team1.members.user', 'team2.members.user'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->members->contains('user_id', $user->id) || 
                    $match->team2->members->contains('user_id', $user->id);

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
        $match = LeagueTeamMatch::findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->members->contains('user_id', $user->id) || 
                    $match->team2->members->contains('user_id', $user->id);

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

        $match = LeagueTeamMatch::findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->members->contains('user_id', $user->id) || 
                    $match->team2->members->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        try {
            $result = $this->leagueTeamService->processBuzz($match, $user, $request->buzz_time);

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

        $match = LeagueTeamMatch::findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->members->contains('user_id', $user->id) || 
                    $match->team2->members->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        try {
            $result = $this->leagueTeamService->submitAnswer($match, $user, $request->answer);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function showResults($matchId)
    {
        $match = LeagueTeamMatch::with(['team1.members.user', 'team2.members.user', 'winner'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->members->contains('user_id', $user->id) || 
                    $match->team2->members->contains('user_id', $user->id);

        if (!$isPlayer) {
            return redirect()->route('league.team.lobby')->with('error', 'Vous n\'êtes pas dans ce match.');
        }

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
}
