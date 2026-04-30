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

**Unified Game Layout:** A shared `game_question.blade.php` template is used for Solo and Duo modes. All multiplayer gameplay views extend `layouts/game.blade.php`.

**Real-Time Multiplayer Synchronization:** Multiplayer modes use Socket.IO for low-latency communication via a Node.js Game Server. JWT tokens are pre-generated for authentication, and Redis is used for game state persistence. Solo mode uses AI opponents.

**Avatar System:** User-specific avatars (12 across 3 tiers) offer 25+ unique skills managed by `AvatarSkillService.php`. The full contract for avatar skills is detailed in `docs/STRATEGIC_AVATAR_SKILL_CONTRACT.md`.

**Gameplay Flow & Scoring:** Multiplayer games follow a 3-page-per-round structure: Question, Answer, and Result. A universal scoring system applies across all modes.

**Question Management:** A file-based question cache and `QuestionService` manage AI-ready, theme-based question generation with adaptive difficulty. The Node-side AI Router in `providers/` (`gemini.js`, `openai.js`, `anthropic.js`, `router.js`, `validation.js`) sits in front of every text-generation call, handling provider keys, rate limiting, and cross-provider failover. The bank-refill entry point `POST /generate-bank-question` enforces a rich JSON contract. `GET /health` returns provider status.

**Persistent Question Bank (Postgres):** Canonical multilingual question store under `App\Services\QuestionBank\*` with 3 tables (`question_groups`, `question_translations`, `match_question_plans`). The bank is the nominal path for every match. `MatchQuestionPlanner::buildMatchPlan()` produces an ordered match plan using deterministic largest-remainder allocation, enforcing cognitive-mix and sub-domain quotas. Plans/picks identify a canonical `question_group_id` for conceptual question consistency across languages. Anti-clone and anti-overuse guards are enforced. When the bank can't fill a slot, the planner completes with the embedded seed pool — never a synchronous AI call.

**Embedded Seed Pool (#93):** Last-resort static fallback consumed by `App\Services\SeedQuestionPoolService` when bank and cache are dry. Files live at `resources/seed/fallback-questions-{lang}.json` and cover all 10 supported languages (fr, en, es, it, de, pt, ru, zh, ar, el) × 9 domains (general, histoire, sport, geographie, art, cuisine, science, cinema, faune) × 4 depth bands (3-4 / 5-6 / 7-8 / 9-10) with ≥10 questions per cell (≈4,000 rows total). The planner passes `domain` + `depth_band` (derived from the slot's `depth_range`) to `SeedQuestionPoolService::pickOne()` so cold-start fallbacks land in the right segment instead of at random; the picker cascades domain → depth_band → sub_domain → cognitive_type → niveau_band, dropping any filter that would empty the candidate set. `inventoryFor($lang)` exposes a snapshot for ops/inventory tests. Build-time guard: `npm run seed:check` (or `tests/Feature/SeedPoolCoverageTest.php`) fails CI if any segment drops below 10. To grow the pool: `node scripts/seed/generate_seed_pool.mjs --langs <lang> --target <N>` (uses Replit ModelFarm proxy at `http://localhost:1106/modelfarm/openai`, batched per `(lang, domain, band)`, dedupes by question_text, writes incrementally).

**Continuous Bank Worker:** `App\Services\QuestionBank\Worker\*` keeps the Postgres bank refilled in the background, completely off the gameplay path. The worker cycles through calculating deficits, rate-limiting, AI generation, and quality guards before adding questions to the bank. Live AI is hard-removed from gameplay; `QuestionService::generateQuestion()` reads from bank → cache → embedded seed pool only.

**Test Isolation (CRITICAL):** `phpunit.xml` forces `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` to prevent accidental data wipes.

**Lobby Management:** `LobbyPresenceManager` handles player registration in Firebase sessions.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection with security rules.

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Master Quiz Page (`/master/codes`):** Allows selection and launch of quizzes, displaying quiz pedigree, and managing a history of saved quizzes.

**Bot Profile System:** Each player has a bot twin built from their real stats, assigned strategic avatars, and can stake intelligence coins.

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time peer-to-peer voice communication for Duo, League Individual, and League Team modes using Firebase Firestore signaling.

**Monorepo Architecture:** An npm workspaces monorepo is used for the Node.js Game Server, comprising `packages/shared`, `packages/game-engine`, and `apps/game-server`.

**Scalable Architecture:** Redis is used for real-time state. Firestore is the source of truth for questions, accelerated by Laravel Cache. Event-sourcing logs canonical events to Redis. Socket.IO Redis adapter enables horizontal scaling.

**Advertising System:** Ads are strictly forbidden in gameplay pages and allowed only in non-gameplay pages. Rewarded ads offer competence coins. Purchasing "Maître du Jeu" globally disables all ads.

**Season Reward System:** Players must meet win-count thresholds for seasonal prizes, with rankings determining prize tiers.

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