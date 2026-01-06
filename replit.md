# StrategyBuzzer

### Overview
StrategyBuzzer is a real-time quiz buzzer game application offering an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project aims to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

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

**Real-Time Multiplayer Synchronization:** All multiplayer modes use an Authoritative Backend Architecture for synchronized gameplay, designed to scale up to 40 players. The backend publishes questions to Firestore, ensuring synchronization and preventing client-side manipulation. Answer validation is strictly server-side. Solo mode is isolated, using traditional page redirects. Multiplayer modes use unified `games/{mode}-match-{normalizedId}` Firestore documents where `normalizedId` is derived from the `lobby_code`.

**Gameplay Engine:** A unified client-side `GameplayEngine.js` manages all game modes, supporting both local (Solo) and Firestore (multiplayer) providers for game state actions. Solo mode dictates the strict sequence of game phases (intro, question, buzz, reveal, scoreboard) and question flow, which multiplayer implementations must adhere to.

**Scoring System:** A unified scoring system awards 2 pts (>3s remaining), 1 pt (1-3s remaining), 0 pt (<1s remaining) for correct answers. Wrong answers incur a -2 pt penalty in Solo, Duo, League, and 0 pt in Master. Timeout is 0 pts.

**Question Management:** A question cache uses file-based caching. `QuestionService` manages AI-ready, theme-based question generation with adaptive difficulty, anti-duplication, and language-specific spelling verification using Google Gemini 2.0 Flash. A `SubthemeRotationSystem` ensures deterministic theme rotation. Multiplayer questions are generated in progressive blocks of 4 by `GenerateMultiplayerQuestionsJob`, using a `QuestionPlanBuilder` for dynamic needs calculation, anti-duplication, and retry logic.

**Multiplayer Lobby Synchronization:** `LobbyPresenceManager` handles player registration in Firebase sessions, with a "Synchronisé" indicator confirming connection before game start.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection. Firebase Firestore security rules validate operations against `request.auth.uid` (Firebase anonymous UID).

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Avatar System:** User-specific avatars (12 across 3 tiers) offering 25 unique skills (Passive, Visual, Active_Pre, Active_Post).

**Progression:** Quest/Achievement System with 35 Standard quests.

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time voice communication for Duo, League Individual, and League Team modes using peer-to-peer WebRTC with Firebase Firestore signaling.

#### Monorepo Architecture (Node.js Game Server)
The project uses a monorepo with `shared`, `game-engine`, and `game-server` packages. The Game Server (Node.js/TypeScript) uses Socket.IO for real-time communication and Express for REST API. Game phases (INTRO, BUZZ_WINDOW, ANSWER_SELECTION, REVEAL, ROUND_SCOREBOARD, TIEBREAKER_*, MATCH_END) are aligned with Solo mode.

**Scalable Architecture (Production):**
- **Redis:** Real-time state (buzzer, timers, room state, event log) with 2-hour TTL.
- **Firestore:** Source of truth for questions.
- **Laravel Cache:** Accelerator for Firestore reads with 30-minute TTL.
- **PostgreSQL Queue:** Used exclusively for AI question generation jobs.
- **Event-Sourcing:** All canonical events are logged to Redis for crash recovery.
- **Multi-Instance Synchronization:** Socket.IO Redis adapter for horizontal scaling, sharing room state across instances.
- **Security (Anti-Cheat):** Correct answer metadata is never sent before reveal.
- **Production Features:** Includes unit tests (Vitest), Zod schemas for input validation, metrics endpoint, and rate limiting.

**Laravel ↔ Game Server Integration:** `GameServerService.php` manages JWT token generation and room creation. JWT tokens include player and room data, secured by `GAME_SERVER_JWT_SECRET`.

**Frontend Socket.IO Client:** `DuoSocketClient.js` is a singleton for Socket.IO communication, handling room join, ready status, buzz, answer, skill activation, and WebRTC voice chat signaling. Duo mode pages (`duo_question.blade.php`, `duo_answer.blade.php`, `duo_waiting.blade.php`, `duo_result.blade.php`) now use Socket.IO for low-latency communication.

#### Game Phases (TypeScript)
```typescript
export type Phase =
  | "INTRO"
  | "BUZZ_WINDOW"
  | "QUESTION_DISPLAY"
  | "ANSWER_SELECTION"
  | "REVEAL"
  | "ROUND_SCOREBOARD"
  | "TIEBREAKER_CHOICE"
  | "TIEBREAKER_QUESTION"
  | "MATCH_END";
```

### External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore (real-time data), Firebase Authentication (user auth).
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP, openai-php/laravel (AI question generation).
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.
-   **Real-time Communication**: Socket.IO, Redis.

**Firebase Configuration:** Firebase Firestore security rules (from `firebase-rules.txt`) must be deployed to the Firebase Console.