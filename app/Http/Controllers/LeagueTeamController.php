<?php

namespace App\Http\Controllers;

use App\Services\TeamService;
use App\Services\LeagueTeamService;
use App\Services\LeagueTeamFirestoreService;
use App\Services\GameServerService;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\LeagueTeamMatch;
use App\Models\User;
use App\Models\ProfileStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class LeagueTeamController extends Controller
{
    private TeamService $teamService;
    private LeagueTeamService $leagueTeamService;
    private LeagueTeamFirestoreService $firestoreService;
    private GameServerService $gameServerService;

    public function __construct(
        TeamService $teamService,
        LeagueTeamService $leagueTeamService,
        LeagueTeamFirestoreService $firestoreService,
        GameServerService $gameServerService
    ) {
        $this->teamService = $teamService;
        $this->leagueTeamService = $leagueTeamService;
        $this->firestoreService = $firestoreService;
        $this->gameServerService = $gameServerService;
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
        return redirect()->route('league.team.management');
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
            ->map(function ($pc) {
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

        $duoMatchesPlayed = \App\Models\DuoMatch::where(function ($q) use ($user) {
            $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
        })->where('status', 'completed')->count();

        $canCreateTeam = $duoMatchesPlayed >= 25;

        return view('league_team_management', compact('user', 'team', 'pendingInvitations', 'pendingRequestsCount', 'selectedTeamId', 'duoMatchesPlayed', 'canCreateTeam'));
    }

    public function searchTeams(Request $request)
    {
        $user = Auth::user();
        $search = $request->get('q', '');

        $teamsQuery = Team::where('is_recruiting', true)
            ->withCount('members')
            ->with(['captain', 'members']);

        if ($search) {
            $teamsQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('tag', 'ilike', "%{$search}%");
            });
        }

        $teams = $teamsQuery->orderBy('points', 'desc')->limit(50)->get()
            ->filter(fn($team) => $team->members_count < 5)
            ->take(20);

        foreach ($teams as $team) {
            $team->member_count = $team->members_count;
        }

        return view('league_team_search', compact('user', 'teams'));
    }

    public function searchTeamsApi(Request $request)
    {
        $search = $request->get('q', '');
        $recruiting = $request->has('recruiting') && $request->get('recruiting') !== '0';

        $teamsQuery = Team::withCount('members');

        if ($recruiting) {
            $teamsQuery->where('is_recruiting', true);
        }

        if ($search) {
            $teamsQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('tag', 'ilike', "%{$search}%");
            });
        }

        $teams = $teamsQuery->orderBy('points', 'desc')->limit(50)->get()
            ->filter(fn($team) => $team->members_count < 5)
            ->take(20);

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
            'user',
            'team',
            'userTeam',
            'hasPendingRequest',
            'isOwnTeam',
            'isMember',
            'memberStats',
            'memberContributions'
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

        $activeGathering = null;
        $activeSessionId = \Illuminate\Support\Facades\Cache::get('team_active_gathering:' . $team->id);
        if ($activeSessionId) {
            $gatheringData = \Illuminate\Support\Facades\Cache::get('team_gathering:' . $activeSessionId);
            if ($gatheringData && $gatheringData['team_id'] == $team->id) {
                $activeGathering = [
                    'sessionId' => $activeSessionId,
                    'teamId' => $team->id,
                    'connected' => count($gatheringData['connected'] ?? []),
                    'total' => count($gatheringData['members'] ?? []),
                ];
            } else {
                \Illuminate\Support\Facades\Cache::forget('team_active_gathering:' . $team->id);
            }
        }

        return view('league_team_lobby', compact('user', 'team', 'rankings', 'selectedTeamId', 'activeGathering'));
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

            if ($request->custom_emblem && strpos($request->custom_emblem, 'data:image/') === 0) {
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
                (int) ($request->emblem_index ?: 1),
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

            // Quête quotidienne : inviter un joueur
            try {
                app(\App\Services\DailyQuestService::class)->checkAndCompleteDailyQuest(
                    $user, 'daily_invite_player', ['player_invited' => true]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('DailyQuest player_invited (LeagueTeam) failed: ' . $e->getMessage());
            }

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

    public function transferCaptain(Request $request)
    {
        $request->validate([
            'member_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $newCaptainId = (int) $request->member_id;

        $team = $user->teams()
            ->where('captain_id', $user->id)
            ->first();

        if (!$team) {
            return response()->json(['error' => __('Vous n\'êtes pas capitaine d\'une équipe.')], 403);
        }

        if ($newCaptainId === $user->id) {
            return response()->json(['error' => __('Vous êtes déjà le capitaine.')], 400);
        }

        $isMember = $team->teamMembers()->where('user_id', $newCaptainId)->exists();
        if (!$isMember) {
            return response()->json(['error' => __('Ce joueur n\'est pas membre de l\'équipe.')], 400);
        }

        try {
            \DB::transaction(function () use ($team, $user, $newCaptainId) {
                $team->captain_id = $newCaptainId;
                $team->save();

                $team->teamMembers()->where('user_id', $user->id)->update(['role' => 'member']);
                $team->teamMembers()->where('user_id', $newCaptainId)->update(['role' => 'captain']);
            });

            return response()->json(['success' => true, 'message' => __('Capitaine transféré avec succès.')]);
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

        // Task #50 (Phase A) — issue per-user JWT and surface the game-server
        // context to the view so [data-stat][data-player] slots can connect via
        // GameplayRuntime. JWT generation is opportunistic: if room_id is missing
        // (e.g. legacy match created before the migration) the view degrades to
        // the REST-only path and live-stats slots stay at their default values.
        $roomId = $match->room_id ?? null;
        $lobbyCode = $match->lobby_code ?? null;
        $jwtToken = null;
        $gameServerUrl = config('app.game_server_url', env('GAME_SERVER_URL', 'http://localhost:3001'));

        if ($roomId) {
            try {
                $jwtToken = $this->gameServerService->generatePlayerToken((int) $user->id, $roomId);
            } catch (\Throwable $e) {
                Log::warning('LeagueTeamController: JWT generation failed (non-fatal)', [
                    'match_id' => $match->id,
                    'user_id' => $user->id,
                    'room_id' => $roomId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $totalQuestions = 18; // LEAGUE_TEAM: 9 questions/round × 2 rounds-to-win

        return view('league_team_game', compact(
            'user',
            'match',
            'roomId',
            'lobbyCode',
            'jwtToken',
            'gameServerUrl',
            'totalQuestions'
        ));
    }

    /**
     * Server-to-server finalize endpoint, called by the Node game server when a
     * League Team match ends without an active client (disconnect / timeout /
     * natural end with closed browser).
     *
     * Auth: short-lived JWT signed with GAME_SERVER_JWT_SECRET, claim purpose='internal_finalize'.
     * Body: { roomId: string }
     * No CSRF (excluded), no Auth::user(). Idempotent.
     */
    public function internalFinalize(Request $request)
    {
        $authHeader = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
            return response()->json(['success' => false, 'error' => 'Missing bearer token'], 401);
        }
        $token = $m[1];

        try {
            $secret  = $this->gameServerService->getJwtSecret();
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            Log::warning('LeagueTeamController.internalFinalize JWT verify failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Invalid token'], 401);
        }

        $purpose = $decoded->purpose ?? null;
        if ($purpose !== 'internal_finalize') {
            return response()->json(['success' => false, 'error' => 'Invalid token purpose'], 401);
        }

        $roomId = (string) $request->input('roomId', '');
        if ($roomId === '') {
            return response()->json(['success' => false, 'error' => 'roomId required'], 400);
        }

        $match = LeagueTeamMatch::where('room_id', $roomId)
            ->orWhere('lobby_code', $roomId)
            ->first();

        if (!$match) {
            return response()->json(['success' => false, 'error' => 'Match not found'], 404);
        }

        if ($match->status === 'finished') {
            return response()->json(['success' => true, 'already_finished' => true]);
        }

        // Distributed dedupe lock — same pattern as DuoController::applyFinalizationFromRedis.
        // Prevents concurrent finalization from REST flow racing the Node-side
        // safety-net call. SETNX with 30s TTL acts as critical-section mutex.
        $lockKey = 'finalize:league_team:' . $match->id;
        $acquired = Redis::connection('game_server')->set($lockKey, '1', 'EX', 30, 'NX');
        if (!$acquired) {
            return response()->json([
                'success' => true,
                'in_progress' => true,
                'message' => 'Finalisation déjà en cours.',
            ]);
        }

        try {
            // Re-read under lock to close TOCTOU window.
            $match->refresh();
            if ($match->status === 'finished') {
                return response()->json(['success' => true, 'already_finished' => true]);
            }

            // Phase B (future): when LT buzz/answer is migrated to the orchestrator,
            // 'gs:match:{roomId}:result' will be the authoritative source the same
            // way Duo uses it today. We read it here speculatively so the contract
            // is in place; for Phase A this key is expected to be empty (orchestrator
            // never advances LT rooms) and we fall through to the REST-populated
            // game_state below.
            $redisKey = 'gs:match:' . ($match->room_id ?? $roomId) . ':result';
            $rawResult = Redis::connection('game_server')->get($redisKey);

            $gameState = $match->game_state ?? [];
            $hasRedisResult = is_string($rawResult) && $rawResult !== '';
            $hasRestState   = isset($gameState['players']) && is_array($gameState['players']) && count($gameState['players']) > 0;

            if (!$hasRedisResult && !$hasRestState) {
                // Defensive: neither the orchestrator nor the REST loop has produced
                // anything score-worthy yet. Do NOT silently close the match as a
                // draw — that would clobber a legitimate in-progress match if this
                // safety-net call lands before the REST flow has had a chance to
                // populate scores. Tell the caller to retry; the lock TTL will expire
                // naturally so a later attempt can finalize once data is present.
                Log::info('LeagueTeamController.internalFinalize: no authoritative result available, asking caller to retry', [
                    'match_id' => $match->id,
                    'room_id' => $roomId,
                ]);
                return response()->json([
                    'success' => false,
                    'pending' => true,
                    'message' => 'Résultat pas encore disponible, réessayez.',
                ], 202);
            }

            // Phase A: REST-authoritative game_state is the only data we have.
            // Phase B will additionally enrich $gameState from $rawResult before
            // calling finalizeMatch (mirrors DuoController::applyFinalizationFromRedis).
            $this->leagueTeamService->finalizeMatch($match, $gameState);
            $this->firestoreService->deleteMatchSession($match->id);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('LeagueTeamController.internalFinalize: finalization failed', [
                'match_id' => $match->id,
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Finalization failed'], 500);
        }
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

    public function toggleRecruitingById($teamId)
    {
        $user = Auth::user();
        $team = Team::findOrFail($teamId);

        if ($team->captain_id !== $user->id) {
            return response()->json(['success' => false, 'error' => __('Seul le capitaine peut modifier ce paramètre.')], 403);
        }

        $team->update(['is_recruiting' => !$team->is_recruiting]);

        return response()->json([
            'success' => true,
            'is_recruiting' => $team->is_recruiting
        ]);
    }

    public function findOpponents(Request $request)
    {
        $user = Auth::user();
        $teamId = $request->team_id;
        $level = $request->level ?? 'normal';

        $team = Team::withCount('members')->findOrFail($teamId);

        if (!$team->isMember($user->id)) {
            return response()->json(['success' => false, 'error' => __('Vous n\'êtes pas membre de cette équipe.')], 403);
        }

        if ($team->members_count < 5) {
            return response()->json([
                'success' => false,
                'message' => __('Votre équipe doit avoir 5 joueurs pour participer.')
            ]);
        }

        $opponents = Team::where('id', '!=', $team->id)
            ->where('division', $team->division)
            ->withCount('members')
            ->get()
            ->filter(fn($t) => $t->members_count >= 5)
            ->sortBy(fn($t) => abs($t->points - $team->points))
            ->take(3);

        if ($opponents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('Aucune équipe disponible dans votre division.')
            ]);
        }

        $opponentData = $opponents->map(function ($opp) {
            $winRate = $opp->matches_played > 0
                ? round(($opp->matches_won / $opp->matches_played) * 100, 1)
                : 0;

            $emblems = Team::EMBLEM_CATEGORIES;
            $category = $opp->emblem_category ?? 'animals';
            $index = ($opp->emblem_index ?? 1) - 1;
            $emblem = $emblems[$category][$index] ?? '🛡️';

            return [
                'id' => $opp->id,
                'name' => $opp->name,
                'tag' => $opp->tag,
                'emblem' => $emblem,
                'points' => $opp->points,
                'wins' => $opp->matches_won,
                'losses' => $opp->matches_lost,
                'win_rate' => $winRate,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'opponents' => $opponentData
        ]);
    }

    public function startMatch(Request $request)
    {
        $user = Auth::user();
        $teamId = $request->team_id;
        $opponentId = $request->opponent_id;
        $level = $request->level ?? 'normal';
        $gameMode = $request->game_mode ?? 'classique';
        $rawPlayerOrder = $request->player_order ?? null;
        $duelPairings = $request->duel_pairings ?? null;

        $team = Team::withCount('members')->findOrFail($teamId);
        $opponent = Team::withCount('members')->findOrFail($opponentId);

        $playerOrder = null;
        if ($gameMode === 'relais') {
            if ($rawPlayerOrder && isset($rawPlayerOrder['team1']) && isset($rawPlayerOrder['team2'])) {
                $playerOrder = [
                    'team1' => array_values(array_map('intval', $rawPlayerOrder['team1'])),
                    'team2' => array_values(array_map('intval', $rawPlayerOrder['team2'])),
                ];
            } else {
                $team1Members = $team->teamMembers()->pluck('user_id')->toArray();
                $team2Members = $opponent->teamMembers()->pluck('user_id')->toArray();
                $playerOrder = [
                    'team1' => $team1Members,
                    'team2' => $team2Members,
                ];
            }
        }

        if ($team->captain_id !== $user->id) {
            return response()->json(['success' => false, 'error' => __('Seul le capitaine peut lancer un match.')], 403);
        }

        if ($team->members_count < 5) {
            return response()->json(['success' => false, 'error' => __('Votre équipe doit avoir 5 joueurs.')], 400);
        }

        if ($opponent->members_count < 5) {
            return response()->json(['success' => false, 'error' => __('L\'équipe adverse n\'a pas 5 joueurs.')], 400);
        }

        if ($team->division !== $opponent->division) {
            return response()->json(['success' => false, 'error' => __('Les équipes doivent être dans la même division.')], 400);
        }

        $divisions = [
            'bronze' => 0,
            'argent' => 1, 'silver' => 1,
            'or' => 2, 'gold' => 2,
            'platine' => 3, 'platinum' => 3,
            'diamant' => 4, 'diamond' => 4
        ];

        $divisionCoins = [
            'bronze' => 10,
            'argent' => 20, 'silver' => 20,
            'or' => 40, 'gold' => 40,
            'platine' => 80, 'platinum' => 80,
            'diamant' => 160, 'diamond' => 160
        ];

        $teamDivision = strtolower($team->division ?? 'bronze');
        $matchDivision = strtolower($level);

        $teamIndex = $divisions[$teamDivision] ?? 0;
        $matchIndex = $divisions[$matchDivision] ?? 0;

        if ($matchIndex > $teamIndex + 2) {
            return response()->json(['success' => false, 'error' => __('Vous ne pouvez jouer que jusqu\'à 2 niveaux au-dessus de votre division.')], 400);
        }

        $accessCost = 0;
        $hasTimedAccess = false;
        $accessCacheKey = "league_team_access:{$user->id}:{$matchDivision}";

        if ($matchIndex > $teamIndex) {
            $existingAccess = \Illuminate\Support\Facades\Cache::get($accessCacheKey);
            if ($existingAccess && now()->lt($existingAccess)) {
                $hasTimedAccess = true;
            } else {
                $accessCost = ($divisionCoins[$matchDivision] ?? 10) * 2;
            }
        }

        if ($accessCost > 0 && ($user->competence_coins ?? 0) < $accessCost) {
            return response()->json([
                'success' => false,
                'error' => __('Vous n\'avez pas assez de pièces de compétence. Coût: ') . $accessCost
            ], 400);
        }

        try {
            \DB::beginTransaction();

            $shouldGrantTimedAccess = $accessCost > 0;

            if ($accessCost > 0) {
                $paid = $this->leagueTeamService->deductAccessCost($user, $matchDivision, $teamDivision);

                if (!$paid) {
                    throw new \Exception(__('Paiement d\'accès temporaire impossible.'));
                }
            }

            $relayIndices = null;
            $gameState = [
                'round' => 1,
                'question_number' => 0,
                'team1_score' => 0,
                'team2_score' => 0,
                'game_mode' => $gameMode,
                'skills_free_for_all' => ($gameMode === 'classique'),
            ];

            if ($gameMode === 'relais' && $playerOrder) {
                $relayIndices = ['team1' => 0, 'team2' => 0];
                $gameState['active_player'] = [
                    'team1' => $playerOrder['team1'][0] ?? null,
                    'team2' => $playerOrder['team2'][0] ?? null,
                ];
            }

            $match = LeagueTeamMatch::create([
                'team1_id' => $team->id,
                'team2_id' => $opponent->id,
                'team1_level' => $teamIndex,
                'team2_level' => $divisions[strtolower($opponent->division ?? 'bronze')] ?? 0,
                'status' => 'pending',
                'game_mode' => $gameMode,
                'match_division' => $matchDivision,
                'player_order' => $playerOrder,
                'duel_pairings' => $duelPairings,
                'relay_indices' => $relayIndices,
                'game_state' => $gameState,
            ]);

            // Task #50 (Phase A) — allocate Node game-server room so the WS pipeline
            // (live stats, presence, voice readiness, internal-finalize) is wired.
            // The legacy REST gameplay loop (getQuestion / buzz / submitAnswer)
            // continues to drive question/buzz/answer state for now; migrating that
            // loop to GameOrchestrator events is the next phase. Failure here is
            // non-fatal — the match still works on the legacy REST path.
            try {
                $roomResult = $this->gameServerService->createRoom('LEAGUE_TEAM', (int) $user->id, [
                    'theme' => 'general',
                    'niveau' => 5,
                    'language' => app()->getLocale() ?: 'fr',
                    'hasBot' => false,
                ]);

                if (!empty($roomResult['success']) && !empty($roomResult['roomId'])) {
                    $match->update([
                        'room_id' => $roomResult['roomId'],
                        'lobby_code' => $roomResult['lobbyCode'] ?? null,
                    ]);

                    Log::info('LeagueTeamController: Node room allocated for match', [
                        'match_id' => $match->id,
                        'room_id' => $roomResult['roomId'],
                        'lobby_code' => $roomResult['lobbyCode'] ?? null,
                    ]);
                } else {
                    Log::warning('LeagueTeamController: Node room allocation returned no roomId', [
                        'match_id' => $match->id,
                        'roomResult' => $roomResult,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('LeagueTeamController: Node room allocation failed (non-fatal, falling back to REST)', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
            }

            \DB::commit();

            \Illuminate\Support\Facades\Cache::forget('team_active_gathering:' . $team->id);

            if ($shouldGrantTimedAccess) {
                \Illuminate\Support\Facades\Cache::put($accessCacheKey, now()->addHours(6), now()->addHours(6));
            }

            return response()->json([
                'success' => true,
                'match_id' => $match->id
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function gatherTeam(int $teamId)
    {
        $user = Auth::user();
        $team = Team::with('members')->find($teamId);

        if (!$team) {
            return response()->json(['success' => false, 'error' => __('Équipe introuvable')], 404);
        }

        if ($team->captain_id !== $user->id) {
            return response()->json(['success' => false, 'error' => __('Seul le capitaine peut rassembler l\'équipe')], 403);
        }

        if ($team->members->count() < 5) {
            return response()->json(['success' => false, 'error' => __('L\'équipe doit avoir 5 joueurs')], 400);
        }

        $sessionId = 'gather_' . $teamId . '_' . time();

        \Illuminate\Support\Facades\Cache::put('team_gathering:' . $sessionId, [
            'team_id' => $teamId,
            'captain_id' => $user->id,
            'created_at' => now()->toISOString(),
            'members' => $team->members->pluck('id')->toArray(),
            'connected' => [$user->id],
        ], now()->addHours(2));

        \Illuminate\Support\Facades\Cache::put('team_active_gathering:' . $teamId, $sessionId, now()->addHours(2));

        return response()->json([
            'success' => true,
            'redirect_url' => route('league.team.gathering', ['teamId' => $teamId, 'sessionId' => $sessionId]),
        ]);
    }

    public function showGathering(int $teamId, string $sessionId)
    {
        $user = Auth::user();
        $team = Team::with(['members', 'captain'])->find($teamId);

        if (!$team) {
            return redirect()->route('league.team.management')->with('error', __('Équipe introuvable'));
        }

        $isMember = $team->members->contains('id', $user->id);
        if (!$isMember) {
            return redirect()->route('league.team.management')->with('error', __('Vous n\'êtes pas membre de cette équipe'));
        }

        $gatheringData = \Illuminate\Support\Facades\Cache::get('team_gathering:' . $sessionId);
        if (!$gatheringData) {
            return redirect()->route('league.team.management', $teamId)->with('error', __('Session de rassemblement expirée'));
        }

        if (!in_array($user->id, $gatheringData['connected'])) {
            $gatheringData['connected'][] = $user->id;
            \Illuminate\Support\Facades\Cache::put('team_gathering:' . $sessionId, $gatheringData, now()->addHours(2));
        }

        $membersWithStats = $team->members->map(function ($member) use ($team) {
            $stats = \App\Models\PlayerDuoStat::where('user_id', $member->id)->first();

            $last10Matches = \DB::table('league_team_match_participants')
                ->join('league_team_matches', 'league_team_matches.id', '=', 'league_team_match_participants.match_id')
                ->where('league_team_match_participants.user_id', $member->id)
                ->where('league_team_matches.status', 'completed')
                ->orderByDesc('league_team_matches.completed_at')
                ->limit(10)
                ->select([
                    'league_team_match_participants.team_id',
                    'league_team_matches.winner_team_id'
                ])
                ->get();

            $wins = $last10Matches->filter(fn($m) => $m->team_id === $m->winner_team_id)->count();
            $losses = $last10Matches->count() - $wins;
            $last10WinRate = $last10Matches->count() > 0 ? ($wins / $last10Matches->count()) : 0;

            $efficiency = 0;
            if ($stats) {
                $totalAnswers = $stats->total_answers ?? 0;
                $correctAnswers = $stats->correct_answers ?? 0;
                $efficiency = $totalAnswers > 0 ? ($correctAnswers / $totalAnswers) : 0;
            }

            $skillScore = ($efficiency * 0.6) + ($last10WinRate * 0.4);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'avatar_url' => $member->avatar_url,
                'is_captain' => $member->id === $team->captain_id,
                'efficiency' => round($efficiency * 100, 1),
                'last_10_wins' => $wins,
                'last_10_losses' => $losses,
                'skill_score' => $skillScore,
            ];
        })->sortByDesc('skill_score')->values();

        $isCaptain = $team->captain_id === $user->id;

        return view('league_team_gathering', compact('team', 'sessionId', 'membersWithStats', 'isCaptain', 'gatheringData'));
    }

    public function getGatheringMembers(string $sessionId)
    {
        $user = Auth::user();
        $gatheringData = \Illuminate\Support\Facades\Cache::get('team_gathering:' . $sessionId);

        if (!$gatheringData) {
            return response()->json(['success' => false, 'error' => __('Session expirée')], 404);
        }

        if (!in_array($user->id, $gatheringData['members'])) {
            return response()->json(['success' => false, 'error' => __('Non autorisé')], 403);
        }

        if (!in_array($user->id, $gatheringData['connected'])) {
            $gatheringData['connected'][] = $user->id;
            \Illuminate\Support\Facades\Cache::put('team_gathering:' . $sessionId, $gatheringData, now()->addHours(2));
        }

        $team = Team::with('members')->find($gatheringData['team_id']);

        $membersWithStats = $team->members->map(function ($member) use ($gatheringData) {
            $stats = \App\Models\PlayerDuoStat::where('user_id', $member->id)->first();

            $last10Matches = \DB::table('league_team_match_participants')
                ->join('league_team_matches', 'league_team_matches.id', '=', 'league_team_match_participants.match_id')
                ->where('league_team_match_participants.user_id', $member->id)
                ->where('league_team_matches.status', 'completed')
                ->orderByDesc('league_team_matches.completed_at')
                ->limit(10)
                ->select([
                    'league_team_match_participants.team_id',
                    'league_team_matches.winner_team_id'
                ])
                ->get();

            $wins = $last10Matches->filter(fn($m) => $m->team_id === $m->winner_team_id)->count();
            $losses = $last10Matches->count() - $wins;
            $last10WinRate = $last10Matches->count() > 0 ? ($wins / $last10Matches->count()) : 0;

            $efficiency = 0;
            if ($stats) {
                $totalAnswers = $stats->total_answers ?? 0;
                $correctAnswers = $stats->correct_answers ?? 0;
                $efficiency = $totalAnswers > 0 ? ($correctAnswers / $totalAnswers) : 0;
            }

            $skillScore = ($efficiency * 0.6) + ($last10WinRate * 0.4);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'avatar_url' => $member->avatar_url,
                'is_connected' => in_array($member->id, $gatheringData['connected']),
                'efficiency' => round($efficiency * 100, 1),
                'last_10_wins' => $wins,
                'last_10_losses' => $losses,
                'skill_score' => $skillScore,
            ];
        })->sortByDesc('skill_score')->values();

        return response()->json([
            'success' => true,
            'members' => $membersWithStats,
            'all_connected' => count($gatheringData['connected']) >= count($gatheringData['members']),
        ]);
    }

    public function getTimedAccess()
    {
        $user = Auth::user();
        $divisions = ['bronze', 'argent', 'or', 'platine', 'diamant'];
        $timedAccess = [];

        foreach ($divisions as $division) {
            $cacheKey = "league_team_access:{$user->id}:{$division}";
            $expiresAt = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if ($expiresAt && now()->lt($expiresAt)) {
                $remainingMinutes = now()->diffInMinutes($expiresAt, false);
                $hours = floor($remainingMinutes / 60);
                $minutes = $remainingMinutes % 60;
                $timedAccess[$division] = [
                    'expires_at' => $expiresAt->toISOString(),
                    'remaining' => $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min",
                    'remaining_minutes' => $remainingMinutes,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'timed_access' => $timedAccess,
            'competence_coins' => $user->competence_coins ?? 0,
        ]);
    }
}
