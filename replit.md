# StrategyBuzzer

### Overview
StrategyBuzzer is a real-time quiz buzzer game application offering an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project's main purpose is to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

### User Preferences
Preferred communication style: Simple, everyday language.

**Multi-language Requirement (CRITICAL):**
- ALL user-facing text MUST be wrapped with `{{ __('text') }}` in Blade templates
- ALL new text MUST be translated in all 10 language files: `resources/lang/{ar,de,el,en,es,fr,it,pt,ru,zh}.json`
- Never add French-only text - always add translations for all languages

### System Architecture

#### UI/UX Decisions
The frontend uses React 19 with Vite, employing a component-based architecture for competitiveness with energetic chronometers and realistic buzz buttons. It features a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. A multi-language UI with automatic browser detection and manual selection across 10 languages is integrated. The boutique system uses a menu-style card navigation with 7 categories and orientation-aware responsive layouts. Duo and League modes must have identical visual layouts to Solo mode.

#### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting.

**Unified Game Layout:** The `game_question.blade.php` template is shared between Solo and Duo modes using an `$isMultiplayer` flag.

**Real-Time Multiplayer Synchronization:** Multiplayer modes (Duo, League, Master) use Socket.IO for low-latency communication via a Socket.IO Game Server. `DuoSocketClient.js` handles frontend communication, JWT tokens for authentication, and Redis for game state persistence. Solo mode uses AI opponents.

**Avatar Skills Service:** `AvatarSkillService.php` centrally manages 12 avatars and 30+ skills across all game modes.

**Gameplay Flow (Socket.IO):** Multiplayer games follow a 3-page-per-round structure: Question, Answer, and Result.

**Scoring System (Universal - All Modes):**
- 1st to buzz + correct answer: +2 pts
- 1st to buzz + wrong answer/timeout: -2 pts
- 2nd+ to buzz + correct answer: +1 pt
- 2nd+ to buzz + wrong answer/timeout: -2 pts
- Didn't buzz: 0 pt

**Question Management:** A file-based question cache is used. `QuestionService` manages AI-ready, theme-based question generation with adaptive difficulty and anti-duplication, using Google Gemini 2.0 Flash. Image-memory questions use Google Imagen (imagen-4.0-generate-001) via `@google/genai SDK`. Multiplayer questions are generated in progressive blocks by `GenerateMultiplayerQuestionsJob`.

**Multiplayer Lobby Synchronization:** `LobbyPresenceManager` handles player registration in Firebase sessions.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection with security rules validating `request.auth.uid`.

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Avatar System:** User-specific avatars (12 across 3 tiers) offer 25+ unique skills.

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time peer-to-peer voice communication for Duo, League Individual, and League Team modes using Firebase Firestore signaling.

#### Multiplayer Routing Map & Token Strategy

**Token Pre-Generation (CRITICAL):** JWT tokens are generated at lobby creation time and stored in a lobby cache, eliminating synchronization delays.

| Mode | Players | Tokens | Lobby Route | Gameplay Prefix | Controller |
|------|---------|--------|-------------|-----------------|------------|
| **Duo** | 2 | 2 | `/duo/lobby` | `/game/duo/*` | `DuoController` |
| **League Individual** | 2 | 2 | `/league/individual/lobby` | `/game/league/*` | `LeagueIndividualController` |
| **League Team** | 10 (5v5) | 10 | `/league/team/lobby/{teamId}` | (pending) | `LeagueTeamController` |
| **Master** | 3-40 | variable | Via Firebase | `/game/master/*` | `MasterGameController` |

**Monorepo Architecture (Node.js Game Server):** An npm workspaces monorepo is used with `packages/shared`, `packages/game-engine`, and `apps/game-server`. The Game Server (Node.js/TypeScript) uses Socket.IO for real-time communication and Express for REST API.

**Scalable Architecture (Production):** Redis is used for real-time state with a 2-hour TTL. Firestore is the source of truth for questions, accelerated by Laravel Cache (30-minute TTL). PostgreSQL Queue handles AI question generation jobs. Event-sourcing logs canonical events to Redis. Socket.IO Redis adapter enables horizontal scaling. Correct answer metadata is never sent before reveal for security.

**Challenger Skills (Socket.IO Implementation):** `reduce_time` reduces the target's Question page timer for specific questions. `shuffle_answers` (pending) shuffles target's answer options.

**Season Reward System:** A two-layer end-of-season reward system for League and Duo modes. Layer 1 grants Intelligence coins based on division-specific point thresholds. Layer 2 promotes top players to the next division.

**Currency System (Two Types of Coins):**
- **Pièces d'Intelligence (Intelligence Coins):** Earned in Multiplayer modes.
- **Pièces de Compétence (Skill Coins):** Earned in Solo mode and Quests, used for ALL boutique purchases.

**Multi-Currency Stripe Pricing (IP-Based):** `CurrencyDetectionService.php` detects country via IP geolocation to set currency (USD, CAD, GBP, EUR) for Stripe Checkout sessions.

### External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore, Firebase Authentication.
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP.
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.
-   **Real-time Communication**: Socket.IO, Redis.