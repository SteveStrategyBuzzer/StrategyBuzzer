# StrategyBuzzer

### Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project's main purpose is to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

### User Preferences
Preferred communication style: Simple, everyday language.

**Multi-language Requirement (CRITICAL):**
- ALL user-facing text MUST be wrapped with `{{ __('text') }}` in Blade templates
- ALL new text MUST be translated in all 10 language files: `resources/lang/{ar,de,el,en,es,fr,it,pt,ru,zh}.json`
- Never add French-only text - always add translations for all languages

### System Architecture

#### UI/UX Decisions
The frontend uses React 19 with Vite, employing a component-based architecture. It features a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. A multi-language UI with automatic browser detection and manual selection across 10 languages is integrated. The boutique system uses a menu-style card navigation with 7 categories and orientation-aware responsive layouts. Duo and League modes must have identical visual layouts to Solo mode.

#### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting.

**Unified Game Layout:** A shared `game_question.blade.php` template for Solo and Duo modes. All Duo/multiplayer gameplay views extend `layouts/game.blade.php` for shared overlays, Socket.IO integration, and `GameplayRuntime.js`.

**Real-Time Multiplayer Synchronization:** Multiplayer modes (Duo, League, Master) use Socket.IO for low-latency communication via a Node.js Game Server. JWT tokens are pre-generated at lobby creation for authentication, and Redis is used for game state persistence. Solo mode uses AI opponents.

**League Team — Node game-server migration (Task #50, Phase A):** League Team matches now allocate a Node `LEAGUE_TEAM` room and issue per-user JWTs the same way Duo / Master / League Individual do. `LeagueTeamController::startMatch()` calls `GameServerService::createRoom()` and persists `room_id` + `lobby_code` on `league_team_matches` (additive migration `2026_04_25_000002_add_room_id_to_league_team_matches.php`, `Schema::hasColumn`-guarded). `LeagueTeamController::showGame()` issues a per-user token via `GameServerService::generatePlayerToken()` and surfaces `room_id` / `lobby_code` / `jwt_token` / `game_server_url` / `totalQuestions=18` to `resources/views/league_team_game.blade.php`, which feeds them into `partials.game-context` and loads the canonical socket.io / `DuoSocketClient.js` / `GameplayRuntime.js` triplet. Server-to-server finalize routes through `POST /internal/league/team/match/finalize` (CSRF-exempt, `purpose=internal_finalize` JWT) → `LeagueTeamController::internalFinalize` → `LeagueTeamService::finalizeMatch` (visibility lifted private → public). The Node `InternalLaravelClient.notifyMatchFinalized(roomId, mode?)` is now mode-aware and `GameOrchestrator` forwards `room.state.config.mode` for both `DUO` and `LEAGUE_TEAM` rooms. **Phase A scope (deviation from "full migration"):** the legacy REST gameplay loop (`/api/league/team/match/{id}/question` | `/buzz` | `/submit-answer`) intentionally remains the authoritative driver of question / buzz / answer state — Phase B will route those through the orchestrator so `player_stats_updated` / `round_stats` actually fire mid-match. Out of scope for Task #50: e2e spec, `BotPlayerService` 5v5, UX redesign, Firestore lobby migration, full REST polling removal. See `tests/e2e/league-team.skip.md` for the un-skip prerequisites.

**Avatar System:** User-specific avatars (12 across 3 tiers) offer 25+ unique skills managed by `AvatarSkillService.php`.

**Gameplay Flow & Scoring:** Multiplayer games follow a 3-page-per-round structure: Question, Answer, and Result. A universal scoring system applies across all modes (+2 for 1st buzz + correct, -2 for wrong/timeout, +1 for 2nd+ buzz + correct, -2 for 2nd+ buzz + wrong/timeout, 0 for no buzz).

**Question Management:** A file-based question cache and `QuestionService` manage AI-ready, theme-based question generation with adaptive difficulty using Google Gemini 2.0 Flash. Image-memory questions use Google Imagen. Multiplayer questions are generated in progressive blocks.

**Lobby Management:** `LobbyPresenceManager` handles player registration in Firebase sessions.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection with security rules validating `request.auth.uid`.

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Master Quiz Page (`/master/codes`):** Allows selection and launch of quizzes, displaying quiz pedigree, and managing a history of saved quizzes (promote/delete).

**Bot Profile System:** Each player has a bot twin built from their real stats. Qualification requires 10+ unique game events. Bots are assigned strategic avatars and can stake intelligence coins. `BotEngine` provides simulation parameters, and `BotQualificationService` tracks events.

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time peer-to-peer voice communication for Duo, League Individual, and League Team modes using Firebase Firestore signaling.

**Monorepo Architecture:** An npm workspaces monorepo is used for the Node.js Game Server, comprising `packages/shared`, `packages/game-engine`, and `apps/game-server`.

**Dev-Only Features:**
- **Bot Player:** A test bot for Duo mode in non-production environments, managed by `BotPlayerService.ts` and spawned via a Laravel endpoint.
- **Test-Support Endpoints + E2E Regression Suite (Tasks #41, #49):** A small set of routes in `App\Http\Controllers\TestSupportController` exists solely to unblock automated E2E coverage of the multiplayer happy path (Firebase OAuth-only login otherwise blocks scripted clients). All are gated by `app()->environment('production')` at registration time AND re-checked inside the controller via `abortIfProduction()`, and all are CSRF-exempt (`__test/*` in `VerifyCsrfToken::$except`).
  - `POST /__test/login` — creates (if missing) and logs in fixture user `E2E-0001` / `e2e-fixture@strategybuzzer.local`.
  - `POST /__test/duo/setup-bot-match` — Duo match vs seeded bot `BT-0001`, reuses the room `LobbyService::createLobby()` allocates, seeds `session('game_state')`, lands on `/game/duo/intro`.
  - `POST /__test/master/setup-bot-match` — Master game (`status='lobby'`) + stub `MasterGameQuestion #1` + host & bot `MasterGamePlayer`s, allocates Game Server room (`mode='MASTER'`, with required `lobbyCode`), persists `room_id` + `lobby_code`, seeds `session('master_game_state')`, lands on `/game/master/question`.
  - `POST /__test/league/individual/setup-bot-match` — LI match (player vs bot, `status='playing'`), normalizes fixture `profile_settings.strategic_avatar` to `'Aucun'` to dodge a pre-existing view crash (follow-up #51), allocates room (`mode='LEAGUE_INDIVIDUAL'`, with required `lobbyCode`), persists `room_id` + `lobby_code`, seeds `session('game_state')`, lands on `/game/league/question`.
  - All setup endpoints return a unified payload shape: `{success, room_id, lobby_code, jwt_token, intro_url, question_url, ...mode-specific ids}`. `intro_url` is `null` for Master / LI by design (no dedicated intro page) but kept in the payload for schema symmetry.
  - Schema: migration `2026_04_25_000001_add_room_id_to_master_games_and_league_individual_matches.php` adds nullable `room_id` + `lobby_code` columns to Master / League Individual (additive, idempotent via `Schema::hasColumn` guards). The companion migration `2026_04_25_000002_add_room_id_to_league_team_matches.php` (Task #50) extends the same shape to `league_team_matches`. League Team itself is now wired through the Node game server (Phase A — see "League Team — Node game-server migration" above) but does not yet have a dedicated `__test/league/team/setup-bot-match` fixture endpoint nor an e2e browser spec; that work lands with Phase B once the buzz/answer loop emits `player_stats_updated`.
  - Browser-driven Playwright regression specs (Playwright + system Chromium via `which chromium`):
    - `npm run test:e2e:duo-join-browser` → `tests/e2e/duo-join-room.browser.spec.js` — drives `/duo/lobby/<code>` → `/game/duo/intro` → `/game/duo/question`, asserts ≥1 `[GameplayRuntime] Joined room: <uuid>` AND zero `VALIDATION_ERROR` / `Invalid join_room` / `Cannot join_room`.
    - `npm run test:e2e:master-join-browser` → `tests/e2e/master-join-room.browser.spec.js` — drives `/game/master/question`, same assertions (Master view loads `GameplayRuntime.js` which natively emits the `Joined room` log).
    - `npm run test:e2e:league-individual-join-browser` → `tests/e2e/league-individual-join-room.browser.spec.js` — drives `/game/league/question`. Because `league_question.blade.php` does NOT load `GameplayRuntime.js` (it drives `DuoSocketClient` directly), this spec asserts on the natively-emitted `[DuoSocket] Game state received` log (post-join confirmation from the server) instead — no test-only instrumentation in the production view.
    - `npm run test:e2e:duo-join` → `tests/e2e/duo-join-room.test.js` — supplemental Node `socket.io-client` contract test; opens a raw socket with the issued JWT, emits a hand-crafted `join_room`, asserts no forbidden patterns in server `error` / `error_message` / `room_error` events. Fast contract-level regression detection without launching a browser.

**Scalable Architecture:** Redis is used for real-time state with a 2-hour TTL. Firestore is the source of truth for questions, accelerated by Laravel Cache (30-minute TTL). PostgreSQL Queue handles AI question generation jobs. Event-sourcing logs canonical events to Redis. Socket.IO Redis adapter enables horizontal scaling.

**Challenger Skills:** Implemented via Socket.IO, including `reduce_time` for question timers.

**Advertising System:** Ads are strictly forbidden in gameplay (`game.blade.php`) pages and allowed only in non-gameplay (`app.blade.php`) pages (menu, lobby, boutique, results, profile). A banner ad is fixed at the bottom. Rewarded ads offer competence coins (3x/day max). Purchasing "Maître du Jeu" globally disables all ads and grants coins.

**Season Reward System:** Players must meet win-count thresholds to be eligible for seasonal prizes, with rankings by total wins determining prize tiers. Rewards include coins and promotional advancement.

**Currency System:** Two types: Pièces d'Intelligence (multiplayer earnings) and Pièces de Compétence (Solo/Quest earnings, used for all boutique purchases).

**Multi-Currency Stripe Pricing:** `CurrencyDetectionService.php` detects country via IP geolocation to set currency for Stripe Checkout sessions.

### External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore, Firebase Authentication.
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP.
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.
-   **Real-time Communication**: Socket.IO, Redis.