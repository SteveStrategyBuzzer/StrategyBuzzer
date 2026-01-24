# StrategyBuzzer

### Overview
StrategyBuzzer is a real-time quiz buzzer game application offering an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project aims to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

### User Preferences
Preferred communication style: Simple, everyday language.

**Pending Features (TO IMPLEMENT LATER):**
- **Quit penalty**: If a player quits a game in progress, they should lose 1 life. Currently DISABLED for testing purposes.

**Multi-language Requirement (CRITICAL):**
- ALL user-facing text MUST be wrapped with `{{ __('text') }}` in Blade templates
- ALL new text MUST be translated in all 10 language files: `resources/lang/{ar,de,el,en,es,fr,it,pt,ru,zh}.json`
- Never add French-only text - always add translations for all languages

### System Architecture

#### UI/UX Decisions
The frontend uses React 19 with Vite, employing a component-based architecture for competitiveness with energetic chronometers and realistic buzz buttons. It features a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. A multi-language UI with automatic browser detection and manual selection across 10 languages is integrated. The boutique system uses a menu-style card navigation with 7 categories and orientation-aware responsive layouts. Duo and League modes must have identical visual layouts to Solo mode.

#### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting.

**Real-Time Multiplayer Synchronization (Socket.IO Migration):** Multiplayer modes (Duo, League, Master) are migrating from Firestore to Socket.IO for lower latency. The architecture uses:
- **Socket.IO Game Server** (apps/game-server/) for real-time events (buzz, answers, phase changes)
- **DuoSocketClient.js** for frontend-server communication
- **JWT tokens** for player authentication in rooms
- **Redis** for game state persistence with 2-hour TTL

**Current Migration Status (COMPLETED):**
- Duo mode: Socket.IO routes active (`/game/duo/*`), fully migrated
- League mode: Socket.IO routes active (`/game/league/*`), fully migrated
- Master mode: Socket.IO routes active (`/game/master/*`), supports up to 40 players
- Matchmaking queue: Uses Redis Cache polling instead of Firestore
- Firestore: Removed from gameplay controllers (DuoController, LeagueIndividualController)
- Firebase: Retained only for chat/voice features and MasterFirestoreService (host lobby management)

Solo mode remains isolated using AI opponents with traditional page redirects.

**Gameplay Flow (Socket.IO):** Multiplayer games follow a 3-page-per-round structure:
1. **Question page** (`duo_question.blade.php`): 60s timer, buzz system, 3-column layout
2. **Answer page** (`duo_answer.blade.php`): 4 choices, 10s timer
3. **Result page** (`duo_result.blade.php`): Skills, stats, "Le saviez-vous?", GO button

Solo mode dictates the strict sequence of game phases (intro, question, buzz, reveal, scoreboard) and question flow.

**Scoring System (Universal - All Modes):**

| Situation | Result |
|-----------|--------|
| 1st to buzz + correct answer | +2 pts |
| 1st to buzz + wrong answer | -2 pts |
| 1st to buzz + no choice (timeout) | -2 pts |
| 2nd+ to buzz + correct answer | +1 pt |
| 2nd+ to buzz + wrong answer | -2 pts |
| 2nd+ to buzz + no choice (timeout) | -2 pts |
| Didn't buzz + correct answer | 0 pt |
| Didn't buzz + wrong answer | 0 pt |
| Didn't buzz + no choice (timeout) | 0 pt |

**Summary:** Buzz = commitment (play for +2 or +1, but any error or timeout = -2 pts). No buzz = safe (0 pt max, never penalized).

**Question Management:** A question cache uses file-based caching. `QuestionService` manages AI-ready, theme-based question generation with adaptive difficulty, anti-duplication, and language-specific spelling verification using Google Gemini 2.0 Flash. A `SubthemeRotationSystem` ensures deterministic theme rotation. Multiplayer questions are generated in progressive blocks of 4 by `GenerateMultiplayerQuestionsJob`, using a `QuestionPlanBuilder` for dynamic needs calculation, anti-duplication, and retry logic.

**Multiplayer Lobby Synchronization:** `LobbyPresenceManager` handles player registration in Firebase sessions, with a "Synchronisé" indicator confirming connection before game start.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection. Firebase Firestore security rules validate operations against `request.auth.uid` (Firebase anonymous UID).

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Avatar System:** User-specific avatars (12 across 3 tiers) offering 25 unique skills (Passive, Visual, Active_Pre, Active_Post).

#### Historien Avatar (Epic Tier)
The Historian avatar has 2 unique skills:

**🪶 Plume (Le savoir sans temps / knowledge_without_time)**
- **Trigger:** When player did NOT buzz (timeout on question page)
- **Effect:** Player can still answer the question for +1 point max
- **Consumption:** Only consumed when player clicks on an answer (not on page load or timeout)
- **Uses per match:** 1
- **Flow:**
  1. Player doesn't buzz → `/solo/timeout` route
  2. `timeout()` checks if avatar === 'Historien' && skill not used
  3. If available, renders answer page with `featherAvailable=true`
  4. Answer page shows 🪶 icon on all answers (always visible, not hover-only)
  5. Player clicks answer → `feather_skill_used=1` set in form
  6. POST to `answer()` → +1 point if correct, 0 if wrong, skill consumed

**📜 Parchemin (L'histoire corrige / history_corrects)**
- **Trigger:** On result page after player buzzed AND made an error (-2 points)
- **Effect:** Cancels the -2 penalty AND awards the points player was playing for
- **Consumption:** Player clicks on the 📜 icon next to correct answer
- **Uses per match:** 1
- **Score calculation (cumulative):**
  - 1st to buzz + error: -2 cancelled (+2) AND +2 pts awarded = **final +2 pts**
  - 2nd to buzz + error: -2 cancelled (+2) AND +1 pt awarded = **final +1 pt**
- **Flow:**
  1. Player buzzes and answers incorrectly → result page (score shows -2)
  2. If `player_buzzed=true` && `is_correct=false` && `player_points < 0` → show 📜 on correct answer
  3. Player clicks 📜 → AJAX call to `useScrollSkill()`
  4. Score updated: cancel -2 then add points played for, skill consumed
  5. Stats keep `is_correct=false` (error remains in statistics)

**Conditions for skill display:**
| Skill | Condition |
|-------|-----------|
| 🪶 Plume | `!player_buzzed` && skill not used |
| 📜 Parchemin | `player_buzzed` && `!is_correct` && `player_points < 0` && skill not used |

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

**Frontend Socket.IO Client:** `DuoSocketClient.js` is a singleton for Socket.IO communication, handling room join, ready status, buzz, answer, skill activation, and WebRTC voice chat signaling. Duo mode pages (`duo_question.blade.php`, `duo_answer.blade.php`, `duo_result.blade.php`) now use Socket.IO for low-latency communication.

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