<?php

namespace App\Http\Controllers;

use App\Services\TeamService;
use App\Services\LeagueTeamService;
use App\Services\LeagueTeamFirestoreService;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\LeagueTeamMatch;
use App\Models\User;
use App\Models\ProfileStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function showLigue()
    {
        $user = Auth::user();
        $userTeams = $user->teams()->with(['captain', 'members'])->get();
        $pendingInvitations = TeamInvitation::where('user_id', $user->id)
            ->with(['team.captain'])
            ->where('status', 'pending')
            ->get();

        return view('ligue', compact('user', 'userTeams', 'pendingInvitations'));
    }

    public function showLeagueEntry()
    {
        $user = Auth::user();
        $userTeams = $user->teams()->with(['captain', 'members'])->get();
        $pendingInvitations = TeamInvitation::where('user_id', $user->id)
            ->with(['team.captain'])
            ->where('status', 'pending')
            ->get();

        return view('league_entry', compact('user', 'userTeams', 'pendingInvitations'));
    }

    public function showCreateTeam()
    {
        return view('league_team_create');
    }

    public function getContacts()
    {
        $user = Auth::user();
        $contacts = \App\Models\PlayerContact::where('user_id', $user->id)
            ->with(['contact'])
            ->get()
            ->map(function($pc) {
                $contact = $pc->contact;
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'player_code' => $contact->player_code ?? 'SB-XXXX',
                    'avatar_url' => $contact->avatar_url,
                    'elo' => $contact->league_elo ?? 1000,
                    'wins' => $contact->league_wins ?? 0,
                    'losses' => $contact->league_losses ?? 0,
                ];
            });

        return response()->json(['contacts' => $contacts]);
    }

    public function showTeamManagement($teamId = null)
    {
        $user = Auth::user();
        
        $eagerLoad = ['captain', 'members'];
        
        if ($teamId) {
            $team = $user->teams()->with($eagerLoad)->where('teams.id', $teamId)->first();
            if (!$team) {
                return redirect()->route('league.entry')->with('error', __('Équipe non trouvée ou accès non autorisé'));
            }
        } else {
            $team = $user->teams()->with($eagerLoad)->first();
        }
        
        $pendingInvitations = TeamInvitation::where('user_id', $user->id)
            ->with(['team.captain'])
            ->where('status', 'pending')
            ->get();
        
        $pendingRequestsCount = 0;
        if ($team && $team->captain_id === $user->id) {
            $pendingRequestsCount = TeamJoinRequest::where('team_id', $team->id)
                ->where('status', 'pending')
                ->count();
        }
        
        $selectedTeamId = $team ? $team->id : null;

        return view('league_team_management', compact('user', 'team', 'pendingInvitations', 'pendingRequestsCount', 'selectedTeamId'));
    }

    public function searchTeams(Request $request)
    {
        $user = Auth::user();
        $search = $request->get('q', '');
        
        $teamsQuery = Team::where('is_recruiting', true)
            ->withCount('members')
            ->having('members_count', '<', 5)
            ->with(['captain', 'members']);
        
        if ($search) {
            $teamsQuery->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('tag', 'ilike', "%{$search}%");
            });
        }
        
        $teams = $teamsQuery->orderBy('elo', 'desc')->limit(20)->get();
        
        foreach ($teams as $team) {
            $team->member_count = $team->members_count;
        }

        return view('league_team_search', compact('user', 'teams'));
    }

    public function searchTeamsApi(Request $request)
    {
        $search = $request->get('q', '');
        $recruiting = $request->has('recruiting') && $request->get('recruiting') !== '0';
        
        $teamsQuery = Team::withCount('members')->having('members_count', '<', 5);
        
        if ($recruiting) {
            $teamsQuery->where('is_recruiting', true);
        }
        
        if ($search) {
            $teamsQuery->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('tag', 'ilike', "%{$search}%");
            });
        }
        
        $teams = $teamsQuery->orderBy('elo', 'desc')->limit(20)->get();
        
        $teamsData = $teams->map(fn($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'tag' => $team->tag,
            'elo' => $team->elo ?? 1000,
            'division' => $team->division ?? 'Bronze',
            'total_wins' => $team->total_wins ?? 0,
            'total_losses' => $team->total_losses ?? 0,
            'member_count' => $team->members_count,
            'is_recruiting' => $team->is_recruiting,
        ]);
        
        return response()->json(['teams' => $teamsData]);
    }

    public function showTeamDetails($teamId)
    {
        $user = Auth::user();
        $team = Team::with(['captain', 'members'])->findOrFail($teamId);
        $userTeam = $user->teams()->first();
        
        $hasPendingRequest = TeamJoinRequest::where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
        
        $isOwnTeam = $userTeam && $userTeam->id === $team->id;
        $isMember = $team->isMember($user->id);
        
        $teamStrengths = $this->calculateTeamStrengths($team);
        
        $themeLabels = [
            __('Géographie'), __('Histoire'), __('Sports'), __('Sciences'),
            __('Cinéma'), __('Art'), __('Animaux'), __('Cuisine')
        ];
        $themeKeys = ['geography', 'history', 'sports', 'sciences', 'cinema', 'art', 'animals', 'cuisine'];
        
        $formattedStrengths = [];
        foreach ($themeKeys as $i => $key) {
            $formattedStrengths[$themeLabels[$i]] = $teamStrengths[$key] ?? 50;
        }
        
        $memberStats = [];
        $memberContributions = [];
        
        foreach ($team->members as $member) {
            $memberStrengths = [];
            $contributions = [];
            
            foreach ($themeKeys as $i => $theme) {
                $strength = 50;
                $memberStrengths[$themeLabels[$i]] = $strength;
            }
            
            $memberStats[$member->id] = $memberStrengths;
            $memberContributions[$member->id] = [];
        }

        return view('league_team_details', compact(
            'user', 'team', 'userTeam', 'hasPendingRequest', 'isOwnTeam', 'isMember',
            'memberStats', 'memberContributions'
        ))->with('teamStrengths', $formattedStrengths);
    }

    public function showCaptainPanel($teamId = null)
    {
        $user = Auth::user();
        
        if ($teamId) {
            $team = $user->teams()->with(['captain', 'members'])->where('teams.id', $teamId)->first();
        } else {
            $team = $user->teams()->with(['captain', 'members'])->first();
        }
        
        if (!$team || !$team->isCaptain($user->id)) {
            return redirect()->route('league.team.management')
                ->with('error', __('Vous devez être capitaine pour accéder à cette page.'));
        }
        
        $pendingRequests = TeamJoinRequest::where('team_id', $team->id)
            ->where('status', 'pending')
            ->with(['user'])
            ->get();
        
        $sentInvitations = TeamInvitation::where('team_id', $team->id)
            ->where('status', 'pending')
            ->with('user')
            ->get();
        
        $selectedTeamId = $team->id;

        return view('league_team_captain', compact('user', 'team', 'pendingRequests', 'sentInvitations', 'selectedTeamId'));
    }

    public function requestJoin(Request $request, $teamId)
    {
        $user = Auth::user();
        $team = Team::findOrFail($teamId);
        
        if ($user->teams()->exists()) {
            return response()->json([
                'success' => false,
                'error' => __('Vous êtes déjà dans une équipe.')
            ], 400);
        }
        
        if ($team->isFull()) {
            return response()->json([
                'success' => false,
                'error' => __('Cette équipe est complète.')
            ], 400);
        }
        
        $existingRequest = TeamJoinRequest::where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();
        
        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'error' => __('Vous avez déjà une demande en attente pour cette équipe.')
            ], 400);
        }
        
        TeamJoinRequest::create([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'message' => $request->get('message', ''),
            'status' => 'pending',
        ]);
        
        return response()->json(['success' => true]);
    }

    public function cancelRequest($teamId)
    {
        $user = Auth::user();
        
        TeamJoinRequest::where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->delete();
        
        return response()->json(['success' => true]);
    }

    public function acceptJoinRequest($requestId)
    {
        $user = Auth::user();
        $joinRequest = TeamJoinRequest::with('user')->findOrFail($requestId);
        $team = Team::findOrFail($joinRequest->team_id);
        
        if (!$team->isCaptain($user->id)) {
            return response()->json([
                'success' => false,
                'error' => __('Seul le capitaine peut accepter les demandes.')
            ], 403);
        }
        
        if ($team->isFull()) {
            return response()->json([
                'success' => false,
                'error' => __('L\'équipe est complète.')
            ], 400);
        }
        
        $this->teamService->addMember($team, $joinRequest->user);
        $joinRequest->update(['status' => 'accepted']);
        
        TeamJoinRequest::where('user_id', $joinRequest->user_id)
            ->where('status', 'pending')
            ->where('id', '!=', $requestId)
            ->update(['status' => 'cancelled']);
        
        return response()->json(['success' => true]);
    }

    public function rejectJoinRequest($requestId)
    {
        $user = Auth::user();
        $joinRequest = TeamJoinRequest::findOrFail($requestId);
        $team = Team::findOrFail($joinRequest->team_id);
        
        if (!$team->isCaptain($user->id)) {
            return response()->json([
                'success' => false,
                'error' => __('Seul le capitaine peut refuser les demandes.')
            ], 403);
        }
        
        $joinRequest->update(['status' => 'rejected']);
        
        return response()->json(['success' => true]);
    }

    public function toggleRecruiting()
    {
        $user = Auth::user();
        $team = $user->teams()->first();
        
        if (!$team || !$team->isCaptain($user->id)) {
            return response()->json([
                'success' => false,
                'error' => __('Seul le capitaine peut modifier ce paramètre.')
            ], 403);
        }
        
        $team->update(['is_recruiting' => !$team->is_recruiting]);
        
        return response()->json([
            'success' => true,
            'is_recruiting' => $team->is_recruiting
        ]);
    }

    private function calculateTeamStrengths(Team $team): array
    {
        $members = $team->members;
        if ($members->isEmpty()) {
            return [
                'geography' => 0,
                'history' => 0,
                'sports' => 0,
                'sciences' => 0,
                'cinema' => 0,
                'art' => 0,
                'animals' => 0,
                'cuisine' => 0,
            ];
        }
        
        $themes = ['geography', 'history', 'sports', 'sciences', 'cinema', 'art', 'animals', 'cuisine'];
        $strengths = [];
        
        foreach ($themes as $theme) {
            $strengths[$theme] = 50;
        }
        
        return $strengths;
    }

    private function calculateUserContribution(User $user, Team $team): array
    {
        $themes = ['geography', 'history', 'sports', 'sciences', 'cinema', 'art', 'animals', 'cuisine'];
        $contribution = [];
        
        $teamStrengths = $this->calculateTeamStrengths($team);
        
        foreach ($themes as $theme) {
            $userStrength = 50;
            $diff = $userStrength - ($teamStrengths[$theme] ?? 50);
            $contribution[$theme] = [
                'user_strength' => round($userStrength, 1),
                'team_strength' => $teamStrengths[$theme] ?? 50,
                'diff' => round($diff, 1),
            ];
        }
        
        return $contribution;
    }

    public function showLobby($teamId = null)
    {
        $user = Auth::user();
        
        if ($teamId) {
            $team = $user->teams()->with(['captain', 'members'])->where('teams.id', $teamId)->first();
        } else {
            $team = $user->teams()->with(['captain', 'members'])->first();
        }

        if (!$team) {
            return redirect()->route('league.team.management');
        }

        $rankings = $this->leagueTeamService->getTeamRankings($team->division);
        $selectedTeamId = $team->id;

        return view('league_team_lobby', compact('user', 'team', 'rankings', 'selectedTeamId'));
    }

    public function createTeam(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:10',
            'emblem_category' => 'nullable|string|max:50',
            'emblem_index' => 'nullable|integer|min:1|max:50',
            'custom_emblem' => 'nullable|string',
        ]);

        try {
            $customEmblemPath = null;
            
            if ($request->custom_emblem && str_starts_with($request->custom_emblem, 'data:image/')) {
                $imageData = $request->custom_emblem;
                $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
                $imageData = base64_decode($imageData);
                
                $fileName = 'team_emblems/' . uniqid() . '_' . time() . '.png';
                $storagePath = storage_path('app/public/' . $fileName);
                
                if (!is_dir(dirname($storagePath))) {
                    mkdir(dirname($storagePath), 0755, true);
                }
                
                file_put_contents($storagePath, $imageData);
                $customEmblemPath = $fileName;
            }
            
            $team = $this->teamService->createTeam(
                Auth::user(),
                $request->name,
                $request->emblem_category ?: 'animals',
                (int)($request->emblem_index ?: 1),
                $customEmblemPath
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
            'team_id' => 'required|integer|exists:teams,id',
        ]);

        $user = Auth::user();
        $team = Team::findOrFail($request->team_id);

        if ($team->captain_id !== $user->id) {
            return response()->json(['success' => false, 'error' => __('Seul le capitaine peut inviter des joueurs.')], 403);
        }

        $playerIdentifier = $request->player_code ?? $request->player_name;
        
        if (!$playerIdentifier) {
            return response()->json(['success' => false, 'error' => __('Veuillez entrer un code ou nom de joueur.')], 400);
        }

        try {
            $invitation = $this->teamService->invitePlayer(
                $team,
                $user,
                $playerIdentifier
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

    public function acceptInvitation(Request $request, $invitationId)
    {
        $invitation = TeamInvitation::findOrFail($invitationId);

        try {
            $this->teamService->acceptInvitation($invitation, Auth::user());

            if ($request->expectsJson()) {
                return response()->json(['success' => true]);
            }
            
            return redirect()->route('league.team.management', $invitation->team_id)
                ->with('success', __('Vous avez rejoint l\'équipe avec succès !'));
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 400);
            }
            
            return redirect()->route('ligue')->with('error', $e->getMessage());
        }
    }

    public function declineInvitation(Request $request, $invitationId)
    {
        $invitation = TeamInvitation::findOrFail($invitationId);

        try {
            $this->teamService->declineInvitation($invitation, Auth::user());

            if ($request->expectsJson()) {
                return response()->json(['success' => true]);
            }
            
            return redirect()->route('ligue')->with('success', __('Invitation refusée.'));
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 400);
            }
            
            return redirect()->route('ligue')->with('error', $e->getMessage());
        }
    }

    public function leaveTeam()
    {
        $user = Auth::user();
        $team = $user->teams()->first();

        if (!$team) {
            return response()->json(['error' => __('Vous n\'êtes pas dans une équipe.')], 400);
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
            return response()->json(['error' => __('Vous n\'êtes pas dans une équipe.')], 400);
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
            return response()->json(['error' => __('Vous n\'êtes pas dans une équipe.')], 400);
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
            return redirect()->route('league.team.lobby')->with('error', __('Vous n\'êtes pas dans ce match.'));
        }

        return view('league_team_game', compact('user', 'match'));
    }

    public function getQuestion($matchId)
    {
        $match = LeagueTeamMatch::with(['team1.teamMembers', 'team2.teamMembers'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => __('Non autorisé.')], 403);
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
            return response()->json(['error' => __('Non autorisé.')], 403);
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
            return response()->json(['error' => __('Non autorisé.')], 403);
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
            return redirect()->route('league.team.lobby')->with('error', __('Vous n\'êtes pas dans ce match.'));
        }

        $this->firestoreService->deleteMatchSession($match->id);

        return view('league_team_results', compact('user', 'match'));
    }

    public function getRankings($division)
    {
        $rankings = $this->leagueTeamService->getTeamRankings($division);

        return response()->json($rankings);
    }

    public function syncGameState($matchId)
    {
        $match = LeagueTeamMatch::with(['team1.teamMembers', 'team2.teamMembers'])->findOrFail($matchId);
        $user = Auth::user();

        $isPlayer = $match->team1->teamMembers->contains('user_id', $user->id) || 
                    $match->team2->teamMembers->contains('user_id', $user->id);

        if (!$isPlayer) {
            return response()->json(['error' => __('Non autorisé.')], 403);
        }

        $firestoreState = $this->firestoreService->syncGameState($match->id);

        return response()->json([
            'success' => true,
            'firestore_state' => $firestoreState,
            'status' => $match->status,
        ]);
    }
}
