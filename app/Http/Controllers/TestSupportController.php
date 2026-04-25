<?php

namespace App\Http\Controllers;

use App\Models\DuoMatch;
use App\Models\User;
use App\Services\DuoMatchmakingService;
use App\Services\GameServerService;
use App\Services\LobbyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dev/test-only support endpoints for end-to-end browser testing.
 *
 * All routes in this controller MUST be gated by `app()->environment('production')`
 * checks at the route level AND inside each method (defense in depth).
 *
 * These endpoints exist so the testing skill (Playwright) can bypass the
 * Firebase OAuth-only login flow and exercise the full multiplayer happy path
 * (lobby -> intro -> question), guarding against regressions like the recent
 * "VALIDATION_ERROR: Invalid join_room payload" incident.
 */
class TestSupportController extends Controller
{
    public const FIXTURE_USER_PLAYER_CODE = 'E2E-0001';
    public const FIXTURE_USER_EMAIL       = 'e2e-fixture@strategybuzzer.local';
    public const FIXTURE_USER_NAME        = 'E2E Test User';

    public const FIXTURE_BOT_PLAYER_CODE  = 'BT-0001';
    public const FIXTURE_BOT_EMAIL        = 'bot@strategybuzzer.local';
    public const FIXTURE_BOT_NAME         = 'Bot Duo';

    public function __construct(
        private DuoMatchmakingService $matchmaking,
        private LobbyService $lobbyService,
        private GameServerService $gameServerService,
    ) {
    }

    /**
     * POST /__test/login
     *
     * Logs in a deterministic fixture user (creating it if it does not yet
     * exist) and returns its identifiers. Refuses to run in production.
     */
    public function login(Request $request): JsonResponse
    {
        $this->abortIfProduction();

        $user = $this->ensureFixtureUser();

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'success'      => true,
            'user_id'      => $user->id,
            'player_code'  => $user->player_code,
            'name'         => $user->name,
            'email'        => $user->email,
        ]);
    }

    /**
     * POST /__test/duo/setup-bot-match
     *
     * Spins up everything needed for the fixture user to land directly on
     * /game/duo/intro against the dev bot:
     *   1. Cancels any leftover Duo matches for the user.
     *   2. Creates a fresh Duo match (user vs bot).
     *   3. Creates the lobby cache entry with hasBot=true.
     *   4. Auto-accepts and joins the bot into the lobby.
     *   5. Allocates a real Game Server room (so Socket.IO can connect/join).
     *   6. Seeds session('game_state') so showIntro() / showQuestion() pass
     *      their auth & match guards.
     *
     * Returns the redirect URL for the gameplay intro page.
     */
    public function setupDuoBotMatch(Request $request): JsonResponse
    {
        $this->abortIfProduction();

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error'   => 'Not authenticated. Call POST /__test/login first.',
            ], 401);
        }

        $bot = $this->ensureBotUser();

        DuoMatch::query()
            ->where(function ($q) use ($user) {
                $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
            })
            ->whereIn('status', ['waiting', 'lobby', 'pending', 'accepted', 'starting', 'in_progress', 'playing'])
            ->update(['status' => 'cancelled']);

        $match = $this->matchmaking->createInvitation($user, $bot->id);

        $lobby = $this->lobbyService->createLobby($user, 'duo', [
            'theme'        => 'Culture générale',
            'nb_questions' => 5,
            'match_id'     => $match->id,
            'hasBot'       => true,
        ]);

        $match->lobby_code = $lobby['code'];
        $match->save();

        $this->matchmaking->acceptMatch($match);
        $this->lobbyService->joinLobby($lobby['code'], $bot);

        // LobbyService::createLobby() already allocates a Game Server room and
        // stashes its id in $lobby['game_server']['roomId']. Reuse that single
        // room — creating a second one would orphan state and make the test
        // setup flaky (two roomIds, only one referenced by the lobby cache).
        // Re-read the lobby from cache so we get the post-bot-join snapshot.
        $cachedLobby = $this->lobbyService->getLobby($lobby['code']) ?? $lobby;
        $roomId = $cachedLobby['game_server']['roomId'] ?? null;

        if (! $roomId) {
            return response()->json([
                'success' => false,
                'error'   => 'LobbyService did not allocate a Game Server room. '
                          . 'Check the Game Server workflow is running and reachable.',
                'lobby_code' => $lobby['code'],
            ], 500);
        }

        $match->room_id = $roomId;
        $match->save();

        $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);

        $request->session()->put('game_state', [
            'match_id'      => $match->id,
            'lobby_code'    => $lobby['code'],
            'theme'         => 'Culture générale',
            'nb_questions'  => 5,
            'current_round' => 1,
            'room_id'       => $roomId,
            'jwt_token'     => $jwtToken,
        ]);

        return response()->json([
            'success'      => true,
            'match_id'     => $match->id,
            'lobby_code'   => $lobby['code'],
            'room_id'      => $roomId,
            'jwt_token'    => $jwtToken,
            'intro_url'    => route('game.duo.intro'),
            'question_url' => route('game.duo.question'),
        ]);
    }

    private function ensureFixtureUser(): User
    {
        return User::withoutEvents(function () {
            return User::firstOrCreate(
                ['player_code' => self::FIXTURE_USER_PLAYER_CODE],
                [
                    'name'                => self::FIXTURE_USER_NAME,
                    'email'               => self::FIXTURE_USER_EMAIL,
                    'password'            => Hash::make(Str::random(32)),
                    'is_bot'              => false,
                    'email_verified_at'   => now(),
                    'competence_coins'    => 1000,
                    'intelligence_pieces' => 1000,
                ]
            );
        });
    }

    private function ensureBotUser(): User
    {
        return User::withoutEvents(function () {
            return User::firstOrCreate(
                ['player_code' => self::FIXTURE_BOT_PLAYER_CODE],
                [
                    'name'                => self::FIXTURE_BOT_NAME,
                    'email'               => self::FIXTURE_BOT_EMAIL,
                    'password'            => Hash::make(Str::random(32)),
                    'is_bot'              => true,
                    'email_verified_at'   => now(),
                    'competence_coins'    => 0,
                    'intelligence_pieces' => 0,
                ]
            );
        });
    }

    private function abortIfProduction(): void
    {
        if (app()->environment('production')) {
            abort(404);
        }
    }
}
