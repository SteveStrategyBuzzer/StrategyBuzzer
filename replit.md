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
The frontend uses React 19 with Vite, employing a component-based architecture, a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. It includes a multi-language UI with automatic browser detection and manual selection across 10 languages. The boutique system uses a menu-style card navigation with 7 categories and orientation-aware responsive layouts. Duo and League modes maintain visual parity with Solo mode.

#### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting. A shared `game_question.blade.php` template is used for Solo and Duo modes, and all multiplayer gameplay views extend `layouts/game.blade.php`.

**Real-Time Multiplayer Synchronization:** Multiplayer modes use Socket.IO for low-latency communication via a Node.js Game Server, with JWT tokens for authentication and Redis for game state persistence. Solo mode features AI opponents.

**Avatar System:** User-specific avatars (12 across 3 tiers) offer 25+ unique skills managed by `AvatarSkillService.php`.

**Gameplay Flow & Scoring:** Multiplayer games follow a 3-page-per-round structure (Question, Answer, Result), with a universal scoring system across all modes.

**Question Management:** A file-based question cache and `QuestionService` manage AI-ready, theme-based question generation with adaptive difficulty. A Node-side AI Router (`gemini.js`, `openai.js`, `anthropic.js`, `router.js`, `validation.js`) handles provider keys, rate limiting, and cross-provider failover for text generation. The persistent question bank in PostgreSQL stores canonical multilingual questions across `question_groups`, `question_translations`, and `match_question_plans` tables. `MatchQuestionPlanner::buildMatchPlan()` produces ordered match plans with deterministic largest-remainder allocation, enforcing cognitive-mix and sub-domain quotas. An embedded seed pool at `resources/seed/fallback-questions-{lang}.json` provides last-resort static fallbacks. The continuous bank worker (`App\Services\QuestionBank\Worker\*`) keeps the Postgres bank refilled in the background using `BankNeedsCalculator`, `WorkerRateLimiter`, `BankAIGenerator`, and `QualityGuards`. Live AI generation is explicitly removed from gameplay.

**Plan-Driven Match Flow:** The persistent bank is integrated into the question pipeline for Master mode and Node-initiated matches. Duo mode pre-generation of questions is removed, ensuring a single, persistent plan. League mode currently uses a separate REST loop for questions.

**Admin Audit-Log Page:** `App\Http\Controllers\Admin\QuestionBankAuditLogController` provides a paginated, filterable view of `admin_question_audit_log` records, secured by a shared secret.

**Test Isolation:** Tests are configured to run against in-memory SQLite to prevent interaction with the live PostgreSQL database, with explicit runtime checks for connection safety.

**Lobby Management:** `LobbyPresenceManager` handles player registration in Firebase sessions.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection with security rules.

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Master Quiz Page:** Allows selection and launch of quizzes, displaying pedigree, and managing history.

**Bot Profile System:** Each player has a bot twin with strategic avatars, built from their real stats, capable of staking intelligence coins.

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time peer-to-peer voice communication for Duo, League Individual, and League Team modes using Firebase Firestore signaling.

**Monorepo Architecture:** An npm workspaces monorepo is used for the Node.js Game Server, comprising `packages/shared`, `packages/game-engine`, and `apps/game-server`.

**Scalable Architecture:** Redis is used for real-time state, Firestore for truth, accelerated by Laravel Cache. Event-sourcing logs to Redis, and Socket.IO Redis adapter enables horizontal scaling.

**Advertising System:** Ads are forbidden in gameplay pages, allowed in non-gameplay pages, and rewarded ads offer competence coins. Purchasing "Maître du Jeu" disables all ads.

**Season Reward System:** Players meet win-count thresholds for seasonal prizes, with rankings determining tiers.

**Currency System:** Two types: Pièces d'Intelligence (multiplayer earnings) and Pièces de Compétence (Solo/Quest earnings, used for all boutique purchases).

**Multi-Currency Stripe Pricing:** `CurrencyDetectionService.php` detects country via IP geolocation for Stripe Checkout sessions.

### Phase 2 — Data Security (2026-04-30, COMPLETE)

**T-A — CoinLedger idempotency:**
- `CoinLedgerService`: `lockForUpdate()` on user row in `credit()`/`debit()`; new `creditOnce()`/`debitOnce()` idempotent methods.
- Migration: Postgres partial UNIQUE index on `coin_ledger(user_id, ref_type, ref_id, reason, coin_type) WHERE ref_type IS NOT NULL`.

**T-B — Match-end coin credits hardened:**
- `DuoMatchmakingService`, `LeagueTeamService`, `LeagueIndividualService`: all match-end credits use `creditOnce()`.
- `DailyQuestService`: injects `CoinLedgerService`, uses `creditOnce()` for quest rewards.
- `AuthController` registration: `DB::transaction` + `creditOnce()` for welcome bonus (30 intelligence + 250 competence).

**T-C — Critical action audit log:**
- Migration: `critical_actions_log` table.
- Model: `App\Models\CriticalActionLog`.
- Trait: `App\Traits\LogsCriticalAction` (instance `logAction()` + static `writeLog()`; both catch Throwable silently).
- Controllers wired: `BoutiqueController` (purchase), `AvatarController` (avatar_select), `DailyQuestController` (daily_quest_action, daily_quest_completed), `AuthController` (user_login, user_login_failed, user_registered).

**T-D — Match snapshot checkpoints:**
- Migration: `match_snapshots` table (UUID PK on `match_id`, jsonb columns for player_scores/rounds_won/player_stats).
- Model: `App\Models\MatchSnapshot`.
- Laravel endpoint: `POST /internal/match/snapshot` → `InternalMatchController::storeSnapshot` (same JWT auth as finalize).
- Node Game Server: `saveMatchSnapshot()` in `InternalLaravelClient.ts`; called fire-and-forget after every `ROUND_ENDED` event in `GameOrchestrator.ts`.

**T-E — Hardening:**
- Rate limiting: `throttle:5,1` on `POST /auth/email/login` and `POST /auth/email/register`; `throttle:10,1` on `POST /boutique/purchase`.
- Stripe idempotency: `Session::create()` now receives `['idempotency_key' => ...]` (keyed on `purchaseIntentId` when available).
- Security: `Cloud_SQL_Export_2025-09-05 (09:06:02).sql` (full DB dump) removed from repo root; `.gitignore` updated with `Cloud_SQL_Export*.sql`, `*_export.sql`, `*.dump.sql` patterns.

### Future Feature — Anti Back Navigation (Gameplay)

**Objectif :** empêcher qu'un joueur puisse revenir sur une ancienne question/réponse, revoir des informations expirées, casser la synchro gameplay, ou exploiter la navigation Back/Forward du navigateur. Fonctionne sur ordinateur, mobile (Android, iPhone), tablette, swipe navigation et gestures navigateur.

**Pages concernées :** `duo_question`, `duo_answer`, `duo_result`, `gameplay_solo`, `reponses_solo`, et futures pages MJ/Ligue.

**Architecture en 5 couches :**

1. **Protection UX navigateur** — `history.pushState()` sur chaque page gameplay + interception `popstate` + blocage swipe-back mobile + remplacement immédiat par la phase réelle. Objectif : éviter l'effet visuel de retour arrière.

2. **Protection runtime serveur (vraie sécurité)** — chaque page gameplay valide la phase réelle Node, le `questionIndex` réel, le `round` réel et l'état réel du joueur. Si le joueur tente d'accéder à `/duo/question?question=2` mais que Node est déjà en `RESULT question=3` → refuser la page ou rediriger vers la phase réelle. Le client ne peut jamais rejouer une ancienne phase, revoir une ancienne réponse, ni réactiver un ancien timer.

3. **Cache navigateur** — Headers obligatoires sur toutes les pages gameplay : `Cache-Control: no-store, no-cache, must-revalidate`. Empêche le cache back-forward et la restauration automatique de page mobile.

4. **Reconnexion propre** — Si rechargement ou retour : `GameplayRuntime` demande l'état réel Node et restaure la vraie phase, la vraie question, le vrai timer. Jamais depuis une ancienne page locale.

5. **Règle fondamentale** — Le blocage Back seul n'est PAS suffisant. Même si le navigateur revient visuellement ou si le cache mobile restaure la page, le runtime doit immédiatement corriger vers la vraie phase/timer/question. **Node reste toujours l'unique autorité** : phase active, question active, temps restant, état joueur, navigation réelle.

### Future Feature — Bot Player Style Profile (Duo)

**Principe directeur :** l'efficacité réelle du joueur est la référence principale du bot Duo. Le bot ne connaît jamais la bonne réponse — il transforme l'efficacité observée en probabilité de réussite.

**Séparation des profils :**

1. **Profil global joueur** — tendances générales, domaines forts/faibles, difficulté moyenne, historique long terme.
2. **Profil Solo** — utile pour apprendre les connaissances générales. Ne contrôle PAS le comportement Duo. Utilisable uniquement comme fallback avant 10 matchs Duo complets.
3. **Profil Duo** — référence principale après minimum 10 matchs Duo complets. Prend en compte : efficacité Duo, buzz Duo, timing Duo, agressivité Duo, réussite par domaine/depth, comportement quand il mène/perd, réactions après erreur ou série.

**Pondération recommandée :**

| Phase | Profil Duo | Profil global | Tempérament bot | Variance naturelle | Profil Solo |
|---|---|---|---|---|---|
| Avant 10 matchs Duo | — | 20% | — | — | 10% + 70% bot générique |
| Après 10 matchs Duo | 60% | 25% | 10% | 5% | — |
| Après 20–30 matchs Duo | 75% | 15% | 5% | 5% | — |

**Règle clé :** si le joueur a 62% d'efficacité Duo en Géographie depth 4, le bot ne joue pas comme s'il avait 85% parce qu'il est fort en Solo.

**Formule bot :** `efficacité_réelle × contexte_Duo × domaine/depth × tempérament × variance_humaine_contrôlée`

**Interdiction :** le bot ne doit jamais connaître la bonne réponse à l'avance.

### External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore, Firebase Authentication.
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP.
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.
-   **Real-time Communication**: Socket.IO, Redis.