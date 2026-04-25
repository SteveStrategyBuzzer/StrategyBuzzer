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
- **Test-Support Endpoints:** Dedicated routes for E2E regression testing of multiplayer modes, allowing scripted login and game setup. These are production-gated and CSRF-exempt.

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