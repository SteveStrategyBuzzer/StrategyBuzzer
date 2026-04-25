<?php

namespace App\Http\Controllers;

use App\Models\DuoMatch;
use App\Models\LeagueIndividualMatch;
use App\Models\MasterGame;
use App\Models\MasterGamePlayer;
use App\Models\MasterGameQuestion;
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

    /**
     * POST /__test/master/setup-bot-match
     *
     * Spins up everything needed for the fixture user (acting as host) to land
     * directly on /game/master/question:
     *   1. Cancels any leftover Master games for the host.
     *   2. Creates a fresh MasterGame in 'playing' status with one stub
     *      question (so the view can render).
     *   3. Adds the host as a MasterGamePlayer.
     *   4. Allocates a Game Server room (mode='master') and persists
     *      room_id + lobby_code on the game.
     *   5. Issues a JWT for the host bound to that room.
     *   6. Seeds session('master_game_state') so showQuestionPage() and the
     *      other gameplay routes pass their guards.
     *
     * Returns the redirect URLs for the gameplay pages.
     */
    public function setupMasterBotMatch(Request $request): JsonResponse
    {
        $this->abortIfProduction();

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error'   => 'Not authenticated. Call POST /__test/login first.',
            ], 401);
        }

        // Make sure the bot fixture exists (joined as a stand-in player below).
        $bot = $this->ensureBotUser();

        // Cancel any stale Master games for this host.
        // master_games.status check constraint allows: draft, lobby, running, ended.
        MasterGame::query()
            ->where('host_user_id', $user->id)
            ->whereIn('status', ['draft', 'lobby', 'running'])
            ->update(['status' => 'ended']);

        $gameCode = strtoupper(Str::random(6));

        $game = MasterGame::create([
            'game_code'                 => $gameCode,
            'host_user_id'              => $user->id,
            'name'                      => 'E2E Master Test Game',
            'languages'                 => ['FR'],
            'participants_expected'     => 3,
            'mode'                      => 'podium',
            'total_questions'           => 10,
            'question_types'            => ['multiple_choice'],
            'domain_type'               => 'theme',
            'theme'                     => 'Culture générale',
            'creation_mode'             => 'automatique',
            'ai_images_count'           => 0,
            'tiebreaker_mode'           => 'bonus',
            'gameplay_ambiance_enabled' => false,
            'strategic_avatars_enabled' => false,
            'strategic_avatars_tiers'   => [],
            // 'lobby' is sufficient — showQuestionPage()/renderQuestionView()
            // do not gate on $game->status, only host/player membership.
            'status'                    => 'lobby',
            'current_question'          => 1,
            'started_at'                => now(),
            'quiz_validated'            => true,
        ]);

        // Stub host as a player too (some queries iterate $game->players).
        MasterGamePlayer::firstOrCreate(
            ['master_game_id' => $game->id, 'user_id' => $user->id],
            ['score' => 0, 'answered' => [], 'status' => 'playing']
        );

        // Stub bot as a second player so leaderboard queries return >= 1 row.
        MasterGamePlayer::firstOrCreate(
            ['master_game_id' => $game->id, 'user_id' => $bot->id],
            ['score' => 0, 'answered' => [], 'status' => 'playing']
        );

        // One stub question — master.game-question dereferences $question->media_url
        // and $question->choices, so we need question_number=1 to actually exist.
        MasterGameQuestion::firstOrCreate(
            ['master_game_id' => $game->id, 'question_number' => 1],
            [
                'type'            => 'multiple_choice',
                'text'            => 'E2E test question',
                'choices'         => ['A', 'B', 'C', 'D'],
                'correct_indexes' => [0],
                'media_url'       => null,
                'is_tiebreaker'   => false,
            ]
        );

        $roomResult = $this->gameServerService->createRoom('master', (string) $user->id, [
            'theme'        => 'Culture générale',
            'nb_questions' => 10,
            'playerCount'  => 3,
            'language'     => 'FR',
            // RoomManager requires a lobbyCode from Laravel.
            'lobby_code'   => $gameCode,
        ]);

        $roomId = $roomResult['roomId'] ?? $roomResult['room_id'] ?? null;
        if (! $roomId) {
            return response()->json([
                'success' => false,
                'error'   => 'Game Server did not return a roomId. '
                          . 'Check the Game Server workflow is running and reachable.',
                'detail'  => $roomResult,
            ], 500);
        }
        $lobbyCode = $roomResult['lobbyCode'] ?? $roomResult['lobby_code'] ?? null;

        $game->room_id = $roomId;
        if ($lobbyCode) {
            $game->lobby_code = $lobbyCode;
        }
        $game->save();

        $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);

        $request->session()->put('master_game_state', [
            'game_id'    => $game->id,
            'started'    => true,
            'started_at' => now()->timestamp,
            'room_id'    => $roomId,
            'jwt_token'  => $jwtToken,
        ]);

        // Contract: mirror Duo's setup-bot-match response shape so all three
        // browser specs can share the same client logic. Master has no
        // dedicated intro page — the host lands directly on the question
        // route — so `intro_url` is null by design (kept in the payload for
        // schema symmetry, not omitted).
        return response()->json([
            'success'      => true,
            'game_id'      => $game->id,
            'game_code'    => $game->game_code,
            'lobby_code'   => $lobbyCode,
            'room_id'      => $roomId,
            'jwt_token'    => $jwtToken,
            'intro_url'    => null,
            'question_url' => route('game.master.question'),
        ]);
    }

    /**
     * POST /__test/league/individual/setup-bot-match
     *
     * Spins up everything needed for the fixture user to land directly on
     * /game/league/question against the seeded bot:
     *   1. Cancels any leftover League Individual matches for the user.
     *   2. Creates a fresh LeagueIndividualMatch in 'playing' status.
     *   3. Allocates a Game Server room (mode='league_individual') and
     *      persists room_id + lobby_code on the match.
     *   4. Issues a JWT for the player bound to that room.
     *   5. Seeds session('game_state') so showQuestion() passes its guards.
     */
    public function setupLeagueIndividualBotMatch(Request $request): JsonResponse
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

        // Sanitize fixture profile_settings so league_question.blade.php does not
        // crash on `htmlspecialchars(array)` for the strategic_avatar slot.
        // The default user observer seeds profile_settings.strategic_avatar as
        // {id:null,name:null,url:null} (an array), but the view does
        // `<img src="{{ $strategic_avatar }}">` after `@if(!empty(...))` — which
        // passes for a non-empty assoc array and then blows up on render.
        // Forcing the sub-key to the string 'Aucun' avoids that crash without
        // changing any production controller/view logic.
        $this->normalizeFixtureProfileForLeagueIndividual($user);
        $user->refresh();

        LeagueIndividualMatch::query()
            ->where(function ($q) use ($user) {
                $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
            })
            ->whereIn('status', ['waiting', 'pending', 'accepted', 'starting', 'playing'])
            ->update(['status' => 'cancelled']);

        $generatedLobbyCode = strtoupper(Str::random(6));

        $match = LeagueIndividualMatch::create([
            'player1_id'    => $user->id,
            'player2_id'    => $bot->id,
            'player1_level' => 1,
            'player2_level' => 1,
            'status'        => 'playing',
            'game_state'    => [
                'mode'         => 'league_individual',
                'theme'        => 'culture',
                'nb_questions' => 10,
                'nb_rounds'    => 3,
                'niveau'       => 1,
                'players'      => [
                    ['id' => 'player'],
                    ['id' => 'opponent'],
                ],
            ],
        ]);

        $roomResult = $this->gameServerService->createRoom('league_individual', (string) $user->id, [
            'theme'        => 'Culture générale',
            'nb_questions' => 10,
            'playerCount'  => 2,
            'language'     => 'FR',
            'hasBot'       => true,
            // RoomManager requires a lobbyCode from Laravel.
            'lobby_code'   => $generatedLobbyCode,
        ]);

        $roomId = $roomResult['roomId'] ?? $roomResult['room_id'] ?? null;
        if (! $roomId) {
            return response()->json([
                'success' => false,
                'error'   => 'Game Server did not return a roomId. '
                          . 'Check the Game Server workflow is running and reachable.',
                'detail'  => $roomResult,
            ], 500);
        }
        $lobbyCode = $roomResult['lobbyCode'] ?? $roomResult['lobby_code'] ?? null;

        $match->room_id = $roomId;
        if ($lobbyCode) {
            $match->lobby_code = $lobbyCode;
        }
        $match->save();

        $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);

        $request->session()->put('game_state', [
            'match_id'      => $match->id,
            'lobby_code'    => $lobbyCode,
            'theme'         => 'Culture générale',
            'nb_questions'  => 10,
            'current_round' => 1,
            'room_id'       => $roomId,
            'jwt_token'     => $jwtToken,
        ]);

        // Contract: mirror Duo's setup-bot-match response shape so all three
        // browser specs can share the same client logic. League Individual
        // has no dedicated intro page — the player lands directly on the
        // question route — so `intro_url` is null by design (kept in the
        // payload for schema symmetry, not omitted).
        return response()->json([
            'success'      => true,
            'match_id'     => $match->id,
            'lobby_code'   => $lobbyCode,
            'room_id'      => $roomId,
            'jwt_token'    => $jwtToken,
            'intro_url'    => null,
            'question_url' => route('game.league.question'),
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

    /**
     * Normalize the fixture user's profile_settings.strategic_avatar to a
     * scalar 'Aucun' string for both the user and the bot, so the
     * League Individual question view (which currently passes the raw
     * profile_settings sub-tree to a `{{ }}` interpolation) does not crash.
     */
    private function normalizeFixtureProfileForLeagueIndividual(User $user): void
    {
        User::withoutEvents(function () use ($user) {
            $settings = $user->profile_settings;
            if (is_string($settings)) {
                $settings = json_decode($settings, true) ?: [];
            }
            if (!is_array($settings)) {
                $settings = [];
            }
            $settings['strategic_avatar'] = 'Aucun';
            $user->profile_settings = $settings;
            $user->save();
        });
    }

    private function abortIfProduction(): void
    {
        if (app()->environment('production')) {
            abort(404);
        }
    }
}
