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

**Real-Time Multiplayer Synchronization:** Multiplayer modes use Socket.IO for low-latency communication via a Node.js Game Server. JWT tokens are pre-generated for authentication, and Redis is used for game state persistence. Solo mode uses AI opponents. The Game Server's `GameOrchestrator.startGame()` skips its LLM question pipeline whenever the room already has questions pre-loaded (e.g. via `POST /rooms/:roomId/questions`); this lets callers that own their own question source — including deterministic E2E tests and any future host-supplied-question modes — drive matches without their seed being overwritten.

**Avatar System:** User-specific avatars (12 across 3 tiers) offer 25+ unique skills managed by `AvatarSkillService.php`. **The full canonical contract — universal Question-page visibility (4 states: disponible/actif/consommé/bloqué), per-skill activation pages, Node-as-sole-authority architecture, the 12-avatar catalogue, the Solo "same contract, possibly local execution" rule, and the 10-point per-skill validation checklist — lives in `docs/STRATEGIC_AVATAR_SKILL_CONTRACT.md` and MUST be read and obeyed by any work that touches a strategic avatar skill in any mode.**

**Gameplay Flow & Scoring:** Multiplayer games follow a 3-page-per-round structure: Question, Answer, and Result. A universal scoring system applies across all modes.

**Question Management:** A file-based question cache and `QuestionService` manage AI-ready, theme-based question generation with adaptive difficulty. The Node-side **AI Router (#83, extended in #89)** in `providers/` (`gemini.js`, `openai.js`, `anthropic.js`, `router.js`, `validation.js`) sits in front of every text-generation call from `question-api.js`: each provider supports multiple keys (`GEMINI_API_KEYS=k1,k2,k3` / `OPENAI_API_KEYS=…` / `ANTHROPIC_API_KEYS=…`, single-key vars stay supported), round-robin per provider, 60s in-process quarantine on 429/401/403 (tunable via `AI_QUARANTINE_SECONDS`), and cross-provider failover (`AI_PROVIDER_ORDER=gemini,openai` by default; append `,anthropic` for extra resilience — three independent providers mean a simultaneous outage of any two still keeps the bank-refill pipeline alive). The bank-refill entry point `POST /generate-bank-question` enforces the rich JSON contract (`concept_id`, `concept_family`, `translations`, `saviez_vous`, `cognitive_type` ∈ `recognition|reasoning|deceptive_trap`, `difficulty_depth` 1-10, …) via the single shared `providers/validation.js` — partial output = provider error for **every** provider, never a degraded success, never a per-provider permissive validator. `GET /health` returns provider status (Anthropic included when configured), key quarantines with TTL, 1h success/fail counts, average latency, last failover, and the last rejected JSON contract reason. **Bank Refill Only** — the router is never on the live-match critical path (hard-gated by #88). Image-memory questions still use Google Imagen directly via the first available Gemini key (image gen is out of scope for the text router).

**Persistent Question Bank (Postgres):** Canonical multilingual question store under `App\Services\QuestionBank\*` with 3 tables (`question_groups`, `question_translations`, `match_question_plans`). The bank is the **nominal path for every match** — `QuestionService::generateQuestion()` consults the bank first via `QuestionBankPicker` before falling back to cache→IA. `MatchQuestionPlanner::buildMatchPlan()` produces an ordered match plan in one shot using deterministic largest-remainder (Hamilton) allocation: cognitive-mix quotas are enforced ±1 globally AND ±1 per round, sub-domain quotas ±1 for `general` (composition via `QuotaAllocator` largest-remainder + greedy redistribution that guarantees exact slot count per round and ±1 cog drift). Configuration lives in `config/question_bank_profiles.php` (8 sub-domains, student bands 1-9/11-19/21-39/40/41-69/70/71-99, Boss profiles for 10/20/30/40/60/70/90/100 incl. Boss 100 = 55/30/15, Duo/MJ/Ligue mappings, depth_rubric for the worker). Plans/picks identify a canonical `question_group_id` so a French and an English match draw the **same conceptual questions**, just translated (shared `plan_id` across players regardless of language). Anti-clone (concept_id) and anti-overuse (concept_family) guards are enforced. When the bank can't fill a slot, the planner completes with the embedded seed pool — never a synchronous AI call. Usage tracking is async via `IncrementQuestionUsageJob` (or `MarkQuestionGroupUsedJob`). Dev tools: `php artisan questions:bank:stats`, `php artisan questions:plan:dryrun --mode=… --division=…`. Continuous worker (#82) and multi-provider AI router (#83) feed the bank offline so the match critical path stays IA-free.

**Continuous Bank Worker (#82):** `App\Services\QuestionBank\Worker\*` keeps the Postgres bank refilled in the background, completely off the gameplay path. `BankWorker` runs as the **Question Bank Worker** workflow (`php artisan questions:worker`); cycle = `BankNeedsCalculator` (single GROUP BY query → in-memory deficit ranking; **never** the per-segment N+1 it started as) → top deficit → Redis token-bucket `WorkerRateLimiter` → `BankAIGenerator` (direct Gemini REST, multilingual JSON, depth_rubric prompt, 429/timeout backoff) → `QualityGuards` (8 guards: dup_concept_id, concept_family_segment_share, text_similarity Jaccard 3-shingle, missing/weak saviez_vous, answer_key_misaligned, cognitive_mismatch, depth_incoherent, missing_translations) → `QuestionBankRepository::addToBank()`. Redis semaphore prevents concurrent runs (SETNX + EXPIRE — split for client compat); SIGTERM stops cleanly between segments; exponential backoff on upstream errors. **Live AI is now hard-removed from gameplay (#88):** `QuestionService::generateQuestion()` reads bank → cache → embedded seed pool only — no provider call, no reactive refill, no `triggerRefillIfNeeded`. The reactive job (`GenerateQuestionsJob`) and `AIQuestionGeneratorService` have been deleted. Solo's `/generate-queue` and `/generate-fun-fact` HTTP paths are gone; the question-api Express service no longer exposes `/generate-question`, `/generate-queue`, or `/generate-fun-fact`. The two remaining provider-touching endpoints (`/generate-master-question`, `/generate-image-question`) are admin-locked behind `MASTER_API_ADMIN_TOKEN` (constant-time `X-Admin-Token` header check, fail-closed if env var unset) AND each PHP caller in `MasterGameController` (regenerateQuestion, generateTiebreakerQuestion, generateTextQuestionWithAI) is hard-gated by `$game->started_at === null` upstream, so a host can never trigger an AI call from a live match — only during composition before the game is started. The bank worker (#82/#87) on `/generate-bank-question` is the sole legitimate AI consumer. Ops-facing surfaces: `GET /api/admin/questions/health` (rate 1h, last success/rejects, deficits, matches buildable per profile/language — gated by `Authorization: Bearer $QB_HEALTH_TOKEN`, deny-by-default if env var unset) + `php artisan questions:bank:report`. Worker tunables in `config/question_bank_profiles.php → worker` (`target_matches_per_profile`, `recycle_days`, `rate_per_minute`, `idle_sleep_seconds`, backoff bounds, preferred_languages, guards thresholds, redis_keys).

**Test Isolation (CRITICAL):** `phpunit.xml` forces `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`. Without this, `RefreshDatabase` runs `migrate:fresh` against the live Neon Postgres and **wipes everything**. Never remove these two env entries. Migrations stay sqlite-compatible (no Postgres-only types in the bank schema).

**Lobby Management:** `LobbyPresenceManager` handles player registration in Firebase sessions.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection with security rules.

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Master Quiz Page (`/master/codes`):** Allows selection and launch of quizzes, displaying quiz pedigree, and managing a history of saved quizzes.

**Bot Profile System:** Each player has a bot twin built from their real stats, assigned strategic avatars, and can stake intelligence coins. `BotEngine` provides simulation parameters, and `BotQualificationService` tracks events.

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time peer-to-peer voice communication for Duo, League Individual, and League Team modes using Firebase Firestore signaling.

**Monorepo Architecture:** An npm workspaces monorepo is used for the Node.js Game Server, comprising `packages/shared`, `packages/game-engine`, and `apps/game-server`.

**Dev-Only Features:** Test-support endpoints and a Playwright E2E regression suite exist for automated testing of multiplayer flows, gated for production environments. This includes setting up bot matches for Duo, Master, and League Individual modes.

**Scalable Architecture:** Redis is used for real-time state. Firestore is the source of truth for questions, accelerated by Laravel Cache. PostgreSQL Queue handles AI question generation jobs. Event-sourcing logs canonical events to Redis. Socket.IO Redis adapter enables horizontal scaling.

**Advertising System:** Ads are strictly forbidden in gameplay pages and allowed only in non-gameplay pages. A banner ad is fixed at the bottom. Rewarded ads offer competence coins. Purchasing "Maître du Jeu" globally disables all ads.

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