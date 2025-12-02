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
    
    public function create(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'mode' => 'required|string|in:duo,league_individual,league_team,master',
            'theme' => 'nullable|string',
            'nb_questions' => 'nullable|integer|min:5|max:20',
            'teams_enabled' => 'nullable|boolean',
        ]);
        
        $settings = [
            'theme' => $validated['theme'] ?? __('Culture générale'),
            'nb_questions' => $validated['nb_questions'] ?? 10,
        ];
        
        if (isset($validated['teams_enabled'])) {
            $settings['teams_enabled'] = $validated['teams_enabled'];
        }
        
        $lobby = $this->lobbyService->createLobby($user, $validated['mode'], $settings);
        
        session(['current_lobby_code' => $lobby['code']]);
        
        return redirect()->route('lobby.show', ['code' => $lobby['code']]);
    }
    
    public function show(string $code)
    {
        $user = Auth::user();
        
        $lobbyState = $this->lobbyService->getPlayerLobbyState($code, $user->id);
        
        if (!$lobbyState['exists']) {
            return redirect()->route('home')->with('error', __('Salon introuvable'));
        }
        
        if (!$lobbyState['in_lobby']) {
            $result = $this->lobbyService->joinLobby($code, $user);
            
            if (!$result['success']) {
                return redirect()->route('home')->with('error', $result['error']);
            }
            
            $lobbyState = $this->lobbyService->getPlayerLobbyState($code, $user->id);
        }
        
        $duoMatch = DuoMatch::where('lobby_code', $code)
            ->whereIn('status', ['pending', 'waiting', 'lobby'])
            ->first();
        
        return view('lobby', [
            'lobby' => $lobbyState['lobby'],
            'colors' => $lobbyState['colors'],
            'isHost' => $lobbyState['is_host'],
            'currentPlayerId' => $user->id,
            'allReady' => $lobbyState['all_ready'],
            'canStart' => $lobbyState['can_start'],
            'matchId' => $duoMatch?->id,
        ]);
    }
    
    public function join(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);
        
        $code = strtoupper($validated['code']);
        
        $result = $this->lobbyService->joinLobby($code, $user);
        
        if (!$result['success']) {
            if ($request->expectsJson()) {
                return response()->json($result, 400);
            }
            return back()->with('error', $result['error']);
        }
        
        session(['current_lobby_code' => $code]);
        
        if ($request->expectsJson()) {
            return response()->json($result);
        }
        
        return redirect()->route('lobby.show', ['code' => $code]);
    }
    
    public function leave(Request $request, string $code)
    {
        $user = Auth::user();
        
        $result = $this->lobbyService->leaveLobby($code, $user);
        
        session()->forget('current_lobby_code');
        
        if ($request->expectsJson()) {
            return response()->json($result);
        }
        
        if ($result['lobby_closed'] ?? false) {
            return redirect()->route('home')->with('info', __('Le salon a été fermé'));
        }
        
        return redirect()->route('home');
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
    
    public function updateSettings(Request $request, string $code)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'theme' => 'nullable|string',
            'nb_questions' => 'nullable|integer|min:5|max:20',
            'teams_enabled' => 'nullable|boolean',
        ]);
        
        $result = $this->lobbyService->updateLobbySettings($code, $user, $validated);
        
        return response()->json($result);
    }
    
    public function start(Request $request, string $code)
    {
        $user = Auth::user();
        
        $result = $this->lobbyService->startGame($code, $user);
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }
        
        return response()->json($result);
    }
    
    public function getState(string $code)
    {
        $user = Auth::user();
        
        $lobbyState = $this->lobbyService->getPlayerLobbyState($code, $user->id);
        
        return response()->json($lobbyState);
    }
}
