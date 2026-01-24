# StrategyBuzzer

### Overview
StrategyBuzzer is a real-time quiz buzzer game application designed to offer an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project's main purpose is to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

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

**Real-Time Multiplayer Synchronization:** Multiplayer modes (Duo, League, Master) are migrated from Firestore to Socket.IO for lower latency. The architecture uses a Socket.IO Game Server for real-time events, `DuoSocketClient.js` for frontend-server communication, JWT tokens for player authentication, and Redis for game state persistence with a 2-hour TTL. Solo mode uses AI opponents with traditional page redirects.

**Gameplay Flow (Socket.IO):** Multiplayer games follow a 3-page-per-round structure: Question page, Answer page, and Result page. Solo mode dictates the strict sequence of game phases.

**Scoring System (Universal - All Modes):**
- 1st to buzz + correct answer: +2 pts
- 1st to buzz + wrong answer/timeout: -2 pts
- 2nd+ to buzz + correct answer: +1 pt
- 2nd+ to buzz + wrong answer/timeout: -2 pts
- Didn't buzz: 0 pt

**Question Management:** A question cache uses file-based caching. `QuestionService` manages AI-ready, theme-based question generation with adaptive difficulty, anti-duplication, and language-specific spelling verification using Google Gemini 2.0 Flash. Multiplayer questions are generated in progressive blocks by `GenerateMultiplayerQuestionsJob`.

**Multiplayer Lobby Synchronization:** `LobbyPresenceManager` handles player registration in Firebase sessions with a "Synchronisé" indicator.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection. Firebase Firestore security rules validate operations against `request.auth.uid`.

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Avatar System:** User-specific avatars (12 across 3 tiers) offer 25+ unique skills. Attack skills target opponents based on their score ranking.

**Skill Specifications (Examples):**
- **Mathématicien (Rare Tier):** `illuminate_numbers` - highlights correct answer if it contains a number (passive).
- **Scientifique (Rare Tier):** `acidify_error` - visually marks 2 wrong answers (manual, 1 use).
- **Explorateur (Rare Tier):** `see_opponent_choice` - displays opponent's/AI's choice (manual, 1 use).
- **Challenger (Rare Tier):** `reduce_time` - reduces opponent's Question page chrono (8s → 6s for buzzing); `shuffle_answers` - shuffles opponent's answer options every 1.5s (attack, manual, 1 use each, affects 5/3/1 questions based on round).
- **IA Junior (Rare Tier):** `ai_suggestion` - AI suggests an answer with 90% accuracy; `eliminate_two` - removes 2 wrong answers; `retry_answer` - allows retrying an answer after an error (manual, 1 use each).
- **Historien (Epic Tier):** `knowledge_without_time` - allows answering after timeout for +1 pt; `history_corrects` - cancels -2 penalty and awards points after incorrect buzz (manual, 1 use each).
- **Comédienne (Epic Tier):** `fake_score` - displays a reduced score to opponent (passive); `invert_answers` - makes correct answer appear wrong to opponent (attack, manual, 1 use).

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time voice communication for Duo, League Individual, and League Team modes using peer-to-peer WebRTC with Firebase Firestore signaling.

#### Monorepo Architecture (Node.js Game Server)
The project uses a monorepo with `shared`, `game-engine`, and `game-server` packages. The Game Server (Node.js/TypeScript) uses Socket.IO for real-time communication and Express for REST API. Game phases (INTRO, BUZZ_WINDOW, QUESTION_DISPLAY, ANSWER_SELECTION, REVEAL, ROUND_SCOREBOARD, TIEBREAKER_*, MATCH_END) are aligned with Solo mode.

**Scalable Architecture (Production):**
- **Redis:** Real-time state (buzzer, timers, room state, event log) with 2-hour TTL.
- **Firestore:** Source of truth for questions.
- **Laravel Cache:** Accelerator for Firestore reads with 30-minute TTL.
- **PostgreSQL Queue:** Used exclusively for AI question generation jobs.
- **Event-Sourcing:** All canonical events are logged to Redis for crash recovery.
- **Multi-Instance Synchronization:** Socket.IO Redis adapter for horizontal scaling.
- **Security (Anti-Cheat):** Correct answer metadata is never sent before reveal.

**Challenger Skills (Socket.IO Implementation):**
- **reduce_time:** Reduces target's Question page timer (8s → 6s) for 5/3/1 questions based on round.
  - Activated via `skill` event with `skillId: 'reduce_time'`
  - Dynamic targeting: highest scorer above attacker, or closest below if leader
  - Per-player `question_published` events with personalized `timeLimitMs`
  - Stored in `room.skillEffects` per player with decrement on each question
- **shuffle_answers:** (Pending implementation) Shuffles target's answer options every 1.5s.

### External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore (real-time data), Firebase Authentication (user auth).
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP, openai-php/laravel (AI question generation).
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.
-   **Real-time Communication**: Socket.IO, Redis.