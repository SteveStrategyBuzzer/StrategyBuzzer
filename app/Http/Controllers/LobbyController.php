<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\LobbyService;
use App\Services\AvatarCatalog;
use App\Models\DuoMatch;
use App\Models\User;

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

        // Always reset human player's ready state on page load so the button starts green
        $lobbyPlayers = $lobbyState['lobby']['players'] ?? [];
        if (isset($lobbyPlayers[$user->id]) && !($lobbyPlayers[$user->id]['is_bot'] ?? false)) {
            $this->lobbyService->setPlayerReady($code, $user, false);
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

        if (
            !$roomId ||
            empty($lobby['game_server']['lobbyCode']) ||
            $lobby['game_server']['lobbyCode'] !== $code
        ) {
            \Log::warning('[LobbyController] Room mismatch or missing → forcing reset', [
                'lobby_code' => $code,
                'roomId' => $roomId,
                'gsLobbyCode' => $lobby['game_server']['lobbyCode'] ?? null,
            ]);

            $roomId = null;
        }

        $playerToken = $roomId ? $gameServerService->generatePlayerToken($user->id, $roomId) : null;

        $settings = $user->profile_settings ?? [];
        $catalog = AvatarCatalog::getStrategiques();
        $userUnlocked = (array) data_get($settings, 'unlocked_avatars', []);
        $unlockedStrategicAvatars = [];
        foreach ($catalog as $slug => $avatar) {
            if (in_array($slug, $userUnlocked)) {
                $unlockedStrategicAvatars[$slug] = $avatar;
            }
        }
        $activeStrategicAvatar = data_get($settings, 'strategic_avatar.id') ?: null;

        return view('lobby', [
            'lobby'                    => $lobby,
            'colors'                   => $colors,
            'isHost'                   => $isHost,
            'currentPlayerId'          => $user->id,
            'allReady'                 => $allReady,
            'canStart'                 => $canStart,
            'matchId'                  => $duoMatch?->id,
            'match'                    => $duoMatch,
            'playerToken'              => $playerToken,
            'gameServerUrl'            => $gameServerUrl,
            'unlockedStrategicAvatars' => $unlockedStrategicAvatars,
            'activeStrategicAvatar'    => $activeStrategicAvatar,
            'currentUser'              => $user,
            'userCompetenceCoins'      => $user->competence_coins ?? 0,
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
        $authUser = Auth::user();
        $player = User::find($playerId);

        if (!$player) {
            return response()->json(['success' => false, 'error' => __('Joueur introuvable')]);
        }

        $playerSettings = $player->profile_settings ?? [];
        $level = data_get($playerSettings, 'level', 0);
        $division = $player->temp_access_division ?? data_get($playerSettings, 'division', __('Bronze'));

        $wins   = DuoMatch::where('winner_id', $player->id)->count();
        $total  = DuoMatch::where(function ($q) use ($player) {
            $q->where('player1_id', $player->id)->orWhere('player2_id', $player->id);
        })->whereNotNull('winner_id')->count();
        $losses = max(0, $total - $wins);
        $winRate = $total > 0 ? round(($wins / $total) * 100) : 0;
        $efficiency = $total > 0 ? min(100, round(($wins / max(1, $total)) * 100)) : 0;

        $history = ['matches_together' => 0, 'wins_against' => 0, 'losses_against' => 0, 'last_played' => '—'];
        if ($authUser && $authUser->id !== $player->id) {
            $together = DuoMatch::where(function ($q) use ($authUser, $player) {
                $q->where(function ($q2) use ($authUser, $player) {
                    $q2->where('player1_id', $authUser->id)->where('player2_id', $player->id);
                })->orWhere(function ($q2) use ($authUser, $player) {
                    $q2->where('player1_id', $player->id)->where('player2_id', $authUser->id);
                });
            })->whereNotNull('winner_id')->get();

            $history['matches_together'] = $together->count();
            $history['wins_against']     = $together->where('winner_id', $authUser->id)->count();
            $history['losses_against']   = $together->where('winner_id', $player->id)->count();
            $last = $together->sortByDesc('created_at')->first();
            $history['last_played']      = $last ? $last->created_at->diffForHumans() : '—';
        }

        $avatarSrc = $this->lobbyService->getUserAvatarPublic($player);

        return response()->json([
            'success' => true,
            'player' => [
                'id'          => $player->id,
                'name'        => $player->name,
                'player_code' => $player->player_code,
                'avatarUrl'   => $avatarSrc,
                'coins'       => $player->coins ?? 0,
                'competence_coins' => $player->competence_coins ?? 0,
            ],
            'stats' => [
                'level'      => $level,
                'division'   => $division ?: __('Bronze'),
                'wins'       => $wins,
                'losses'     => $losses,
                'win_rate'   => $winRate,
                'efficiency' => $efficiency,
            ],
            'history'    => $history,
            'radar_data' => null,
        ]);
    }

    public function setStrategicAvatar(Request $request)
    {
        $user = Auth::user();
        $slug = $request->input('avatar_slug');

        if (!$slug) {
            return response()->json(['success' => false, 'error' => __('Avatar invalide')]);
        }

        $settings = $user->profile_settings ?? [];
        $catalog  = AvatarCatalog::getStrategiques();
        $userUnlocked = (array) data_get($settings, 'unlocked_avatars', []);

        if (!isset($catalog[$slug]) || !in_array($slug, $userUnlocked)) {
            return response()->json(['success' => false, 'error' => __('Avatar non débloqué')]);
        }

        $avatar = $catalog[$slug];
        data_set($settings, 'strategic_avatar', [
            'id'   => $slug,
            'name' => $avatar['name'] ?? $slug,
            'url'  => $avatar['path'] ?? null,
        ]);
        $user->profile_settings = $settings;
        $user->save();

        return response()->json(['success' => true, 'active' => $slug]);
    }

    public function updateSettings(Request $request, string $code)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'theme'        => 'sometimes|string',
            'nb_questions' => 'sometimes|integer|min:3|max:30',
            'bet_amount'   => 'sometimes|integer|min:0',
        ]);
        return response()->json(
            $this->lobbyService->updateLobbySettings($code, $user, $validated)
        );
    }

    public function proposeBet(Request $request, string $code)
    {
        $user = Auth::user();
        $validated = $request->validate(['amount' => 'required|integer|min:0']);
        return response()->json(
            $this->lobbyService->proposeBet($code, $user, $validated['amount'])
        );
    }

    public function respondToBet(Request $request, string $code)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'action' => 'required|in:accept,refuse,raise',
            'amount' => 'sometimes|integer|min:1',
        ]);
        return response()->json(
            $this->lobbyService->respondToBet($code, $user, $validated['action'], $validated['amount'] ?? null)
        );
    }

    public function cancelBet(string $code)
    {
        $user = Auth::user();
        return response()->json(
            $this->lobbyService->cancelBet($code, $user)
        );
    }

    public function refundBets(string $code)
    {
        $user = Auth::user();
        $lobby = $this->lobbyService->getLobby($code);
        if (!$lobby || ($lobby['host_id'] ?? null) !== $user->id) {
            return response()->json(['success' => false, 'error' => __('Non autorisé')], 403);
        }
        return response()->json(
            $this->lobbyService->refundBets($code, 'host_request')
        );
    }

    public function matchPlayersByLevel(string $code)
    {
        $user = Auth::user();
        $lobby = $this->lobbyService->getLobby($code);
        if (!$lobby || ($lobby['host_id'] ?? null) !== $user->id) {
            return response()->json(['success' => false, 'error' => __('Non autorisé')], 403);
        }
        $players = collect($lobby['players'] ?? [])
            ->filter(fn($p) => !($p['is_bot'] ?? false))
            ->sortBy(fn($p) => data_get(
                User::find($p['id'])?->profile_settings ?? [],
                'level', 0
            ))
            ->values();

        $pairings = [];
        for ($i = 0; $i + 1 < $players->count(); $i += 2) {
            $pairings[] = [
                'player1' => $players[$i],
                'player2' => $players[$i + 1],
            ];
        }
        return response()->json(['success' => true, 'pairings' => $pairings]);
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
