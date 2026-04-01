<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\LobbyService;
use App\Models\DuoMatch;

class LobbyController extends Controller
{
    protected LobbyService $lobbyService;

    public function __construct(LobbyService $lobbyService)
    {
        $this->lobbyService = $lobbyService;
    }

    public function show(string $code)
    {
        $user = Auth::user();

        $lobbyState = $this->lobbyService->getPlayerLobbyState($code, $user->id);

        if (!($lobbyState['exists'] ?? false)) {
            return redirect()->route('home')->with('error', __('Salon introuvable'));
        }

        if (!($lobbyState['in_lobby'] ?? false)) {
            $this->lobbyService->joinLobby($code, $user);
            $lobbyState = $this->lobbyService->getPlayerLobbyState($code, $user->id);
        }

        $duoMatch = DuoMatch::where('lobby_code', $code)->first();

        $gameServerService = app(\App\Services\GameServerService::class);
        $gameServerUrl = $gameServerService->getSocketUrl();

        $lobby = $lobbyState['lobby'] ?? [];
        $colors = $lobbyState['colors'] ?? [];
        $isHost = $lobbyState['is_host'] ?? false;
        $allReady = $lobbyState['all_ready'] ?? false;
        $canStart = $lobbyState['can_start'] ?? false;

        $roomId = $lobby['game_server']['roomId'] ?? null;
        $playerToken = $roomId ? $gameServerService->generatePlayerToken($user->id, $roomId) : null;

        return view('lobby', [
            'lobby' => $lobby,
            'colors' => $colors,
            'isHost' => $isHost,
            'currentPlayerId' => $user->id,
            'allReady' => $allReady,
            'canStart' => $canStart,
            'matchId' => $duoMatch?->id,
            'match' => $duoMatch,
            'playerToken' => $playerToken,
            'gameServerUrl' => $gameServerUrl,
        ]);
    }

    public function getState(string $code)
    {
        $user = Auth::user();

        $lobbyState = $this->lobbyService->getPlayerLobbyState($code, $user->id);

        return response()->json([
            'success' => true,
            'exists' => $lobbyState['exists'] ?? false,
            'lobby' => $lobbyState['lobby'] ?? [],
            'colors' => $lobbyState['colors'] ?? [],
            'isHost' => $lobbyState['is_host'] ?? false,
            'allReady' => $lobbyState['all_ready'] ?? false,
            'canStart' => $lobbyState['can_start'] ?? false,
            'inLobby' => $lobbyState['in_lobby'] ?? false,
        ]);
    }

    public function getOpenLobbies()
    {
        $user = Auth::user();

        $lobbies = $this->lobbyService->getPlayerOpenLobbies($user->id);

        return response()->json([
            'success' => true,
            'lobbies' => $lobbies,
        ]);
    }

    public function setReady(Request $request, string $code)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'ready' => 'required|boolean',
        ]);

        $result = $this->lobbyService->setPlayerReady($code, $user, $validated['ready']);

        return response()->json($result);
    }

    public function setColor(Request $request, string $code)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'color' => 'required|string',
        ]);

        $result = $this->lobbyService->setPlayerColor($code, $user, $validated['color']);

        return response()->json($result);
    }

    public function setTeam(Request $request, string $code)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'team_id' => 'nullable|string',
        ]);

        $result = $this->lobbyService->setPlayerTeam($code, $user, $validated['team_id']);

        return response()->json($result);
    }

    public function createTeam(Request $request, string $code)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:30',
            'color' => 'required|string',
        ]);

        $result = $this->lobbyService->createTeam($code, $user, $validated['name'], $validated['color']);

        return response()->json($result);
    }

    public function closeLobby(string $code)
    {
        $user = Auth::user();

        return response()->json(
            $this->lobbyService->closeLobbyForPlayer($code, $user->id)
        );
    }

    public function getPlayerStats(int $playerId)
    {
        return response()->json([
            'success' => true,
            'playerId' => $playerId,
            'stats' => null,
        ]);
    }

    public function leave(string $code)
    {
        $user = Auth::user();

        return response()->json(
            $this->lobbyService->leaveLobby($code, $user)
        );
    }

    public function removePlayer(Request $request, string $code)
    {
        $validated = $request->validate([
            'player_id' => 'required|integer',
        ]);

        return response()->json(
            $this->lobbyService->removePlayerFromLobby($code, $validated['player_id'])
        );
    }

    public function start(string $code)
    {
        $user = Auth::user();

        return response()->json(
            $this->lobbyService->startGame($code, $user)
        );
    }
}
