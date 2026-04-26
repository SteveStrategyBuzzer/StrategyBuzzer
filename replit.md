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

**Avatar System:** User-specific avatars (12 across 3 tiers) offer 25+ unique skills managed by `AvatarSkillService.php`. **The full canonical contract — universal Question-page visibility (4 states: disponible/actif/consommé/bloqué), per-skill activation pages, Node-as-sole-authority architecture, the 12-avatar catalogue, the Solo "same contract, possibly local execution" rule, and the 10-point per-skill validation checklist — lives in `docs/STRATEGIC_AVATAR_SKILL_CONTRACT.md` and MUST be read and obeyed by any work that touches a strategic avatar skill in any mode.**

**Gameplay Flow & Scoring:** Multiplayer games follow a 3-page-per-round structure: Question, Answer, and Result. A universal scoring system applies across all modes.

**Question Management:** A file-based question cache and `QuestionService` manage AI-ready, theme-based question generation with adaptive difficulty using Google Gemini 2.0 Flash. Image-memory questions use Google Imagen.

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