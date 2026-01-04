# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project aims to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

## User Preferences
Preferred communication style: Simple, everyday language.

**Multi-language Requirement (CRITICAL):**
- ALL user-facing text MUST be wrapped with `{{ __('text') }}` in Blade templates
- ALL new text MUST be translated in all 10 language files: `resources/lang/{ar,de,el,en,es,fr,it,pt,ru,zh}.json`
- Never add French-only text - always add translations for all languages

## System Architecture

### UI/UX Decisions
The frontend uses React 19 with Vite, employing a component-based architecture designed for competitiveness with energetic chronometers and realistic buzz buttons. It features a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. A multi-language UI with automatic browser detection and manual selection across 10 languages is integrated. The boutique system uses a menu-style card navigation with 7 categories and orientation-aware responsive layouts.

### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting.

**Real-Time Multiplayer Synchronization (Authoritative Backend Architecture):**
All multiplayer modes use an Authoritative Backend Architecture for synchronized gameplay, designed to scale up to 10 players (League Team) and 40 players (Master mode). The backend is solely responsible for publishing questions to Firebase, ensuring perfect synchronization and preventing client-side manipulation. `correct_index` and `is_correct` are never transmitted to clients; answer validation is strictly server-side. Solo mode is completely isolated and uses traditional page redirects.

**Firestore Document Structure:**
All multiplayer modes use unified `games/{mode}-match-{normalizedId}` Firestore documents. The `normalizedId` is computed using CRC32 normalization and must always be derived from the `lobby_code` (e.g., "ABC123"), not the numeric `match_id`.

**Gameplay Engine:**
A unified client-side `GameplayEngine.js` module manages all game modes, ensuring consistent gameplay. It supports both local (Solo) and Firestore (multiplayer) providers for game state actions, using event delegation and retry logic for robust score synchronization.

**Solo Mode Reference Structure:**
Solo mode serves as the critical reference implementation for all multiplayer modes, dictating the strict sequence of game phases (intro, question, buzz, reveal, scoreboard) and the question flow. Multiplayer implementations must adhere to this structure, ensuring synchronized overlays, timers, answer submissions, and score displays across all players.

**Scoring System:**
A unified scoring system applies across all modes:
-   **Correct Answer Points:** 2 pts (>3s remaining), 1 pt (1-3s remaining), 0 pt (<1s remaining).
-   **Wrong Answer Penalty:** -2 pts (Solo, Duo, League); 0 pts (Master).
-   **Timeout:** 0 pts.

**Question Management:**
A question cache system uses file-based caching for pre-generated questions. `QuestionService` manages AI-ready, theme-based question generation with adaptive difficulty, anti-duplication, progressive block-based generation, and language-specific spelling verification using Google Gemini 2.0 Flash. A `SubthemeRotationSystem` ensures deterministic theme rotation.

**Multiplayer Question Batching (Progressive Pipeline):**
`GenerateMultiplayerQuestionsJob` generates questions in blocks of 4 for Duo/League modes with the following architecture:
- **Q1 Synchronous**: First question generated immediately in LobbyService when host clicks "Je Suis Prêt"
- **Progressive Blocks**: Subsequent questions generated in background job, 4 at a time
- **QuestionPlanBuilder**: Calculates total needs dynamically: (3 rounds × N questions/round) + skill bonus + tiebreaker
- **Immediate Append**: Each block sent immediately to Game Server via `appendQuestions()` endpoint
- **Anti-Duplication**: Global tracking of usedQuestionIds, usedAnswers, usedQuestionTexts across all blocks
- **Retry Logic**: Up to 3 retries per question, 3 retries for append with exponential backoff
- **Quota Enforcement**: If deficit cannot be filled after 10 extra attempts, pipeline stops (no partial delivery)
- **Question Types**: main, skill_bonus (1.2x difficulty), tiebreaker (1.5x difficulty) with metadata flags

**Multiplayer Lobby Synchronization:**
`LobbyPresenceManager` handles automatic player registration in Firebase sessions. A "Synchronisé" indicator confirms both players are connected before the host can start the game. Multiplayer games proceed directly to Question 1 after a lobby countdown, with subsequent questions displaying a "Question X/10 + THÈME" indicator.

**Firestore Structure & Authentication:**
All game modes use a unified `/gameSessions/{sessionId}` Firestore collection. Firebase Firestore security rules validate operations against `request.auth.uid` (Firebase anonymous UID).

**Multiplayer Systems:**
Includes Firebase-based queue systems for League Individual and Duo matchmaking, a multiplayer resume page with chat/mic toggles, and various League Team sub-modes (Classique, Bataille de Niveaux, Queue Leu Leu). A League Team gathering system provides real-time team assembly with voice chat.

**Core Services:**
Key services include `AnswerNormalizationService`, `Advanced AI Opponent System`, `GameStateService`, `BuzzManagerService` (for fair buzz management), `RoomService`, `PlayerMessageService/ChatController`, `PlayerContactService`, and `LobbyService/LobbyController`.

**Feature Specifications:**
-   **Game Modes**: Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures: free-for-all, team_open_skills, team_buzzer_only, multi_team).
-   **Avatar System**: User-specific avatars (12 across 3 tiers) offering 25 unique skills (Passive, Visual, Active_Pre, Active_Post).
-   **Progression**: Quest/Achievement System with 35 Standard quests.
-   **Real-time Features**: Firebase Firestore for real-time game state synchronization.
-   **Authentication**: Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.
-   **Multi-language Support**: Full integration for 10 languages with automatic browser detection.
-   **WebRTC Voice Chat System**: Real-time voice communication for Duo, League Individual, and League Team modes using peer-to-peer WebRTC with Firebase Firestore signaling.

## Monorepo Architecture (Node.js Game Server)

### Directory Structure
```
packages/
  shared/src/           # Types partagés (GameState, GameEvent, Phase, etc.)
  game-engine/src/      # Reducer, state-machine, scoring
apps/
  game-server/src/      # Serveur de jeu Node.js/TypeScript
    services/           # RoomManager, QuestionService
    http/               # Routes HTTP (POST /rooms, GET /rooms/:id)
    ws/                 # WebSocket handlers (join_room, buzz, answer, skill)
    middleware/         # JWT auth middleware
```

### Game Server
- **Port**: 3001 (WebSocket + HTTP)
- **Architecture**: Socket.IO pour temps réel, Express pour API REST
- **Phases alignées sur Solo**: INTRO → QUESTION_ACTIVE → ANSWER_SELECTION → REVEAL → ROUND_SCOREBOARD → TIEBREAKER_* → MATCH_END
- **Timers**: intro 9s, question 8s, answer 10s
- **Scoring**: +2 (>3s), +1 (1-3s), 0 (<1s), -2 (mauvaise réponse)

### Scalable Architecture (Option A - Production-Ready)
**Data Flow:**
- **Redis** = Real-time state (buzzer, timers, room state, event log) - 2h TTL
- **Firestore** = Source of truth (questions in `questionPools/{roomId}/items/{index}`)
- **Laravel Cache** = Accelerator for Firestore reads (30 min TTL)
- **PostgreSQL Queue** = AI question generation jobs only

**Event-Sourcing for Crash Recovery:**
- All canonical events logged to Redis: GAME_STARTED, PHASE_CHANGED, QUESTION_PUBLISHED, BUZZ_RECEIVED, ANSWER_SUBMITTED, ANSWER_REVEALED, ROUND_ENDED, MATCH_ENDED
- `RoomRecovery.ts`: rehydrateRoom() (fast path: cached state, slow path: event replay)
- Automatic recovery on WebSocket reconnect if room not in memory

**Multi-Instance Synchronization:**
- Socket.IO Redis adapter for horizontal scaling
- Room state shared via Redis across all Game Server instances
- Supports millions of concurrent players

**Security (Anti-Cheat):**
- `correctIndex`, `isCorrect`, `correctBool`, `correctText` NEVER sent before reveal
- `sanitizeChoices()` strips all correctness metadata from `question_published` event
- `answer_revealed` event is the ONLY place where correct answer is exposed

**Question Pipeline:**
- Progressive generation: Q1 at match start, then blocks of 4 during WAITING phases
- Anti-duplication: usedIds + usedTextHashes stored per match (not reset between rounds)
- Includes bonus skill questions and tiebreaker questions

**Production-Ready Features:**
- **Unit Tests**: 80 tests (reducer, scoring, state-machine) with Vitest
- **Input Validation**: Zod schemas for all Socket.IO events
- **Metrics Endpoint**: GET /metrics (latency, rooms, errors, events)
- **Rate Limiting**: Per-player (1 buzz/answer per question) and per-room (100 events/sec)

### Laravel ↔ Game Server Integration
- **GameServerService.php**: Manages JWT token generation, room creation via HTTP, player authentication
- **JWT Token Payload**: camelCase fields (`playerId`, `playerName`, `avatarId`, `roomId`)
- **JWT Secret**: Uses `GAME_SERVER_JWT_SECRET` env var (falls back to `APP_KEY` decoded from base64)
- **Security**: Strict JWT validation - invalid tokens are rejected (no anonymous connections allowed)
- **Room Creation**: Laravel creates rooms via POST `/rooms`, Game Server returns `{roomId, lobbyCode}`

### Frontend Socket.IO Client
- **DuoSocketClient.js**: Singleton module for Socket.IO communication
- **Features**: Room join, ready status, buzz, answer, skill activation, voice chat signaling
- **Events**: Matches server events (`join_room`, `buzz`, `answer`, `skill`, `ready`, `voice_*`)
- **Voice Chat**: WebRTC signaling integrated into Socket.IO channel (no separate connection)
- **Latency**: Built-in ping measurement via `ping_check` / `pong_check` events

### Imports Cross-Package
Les imports utilisent des chemins relatifs (par exemple `../../../../packages/shared/src/types.js`).

## External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore (real-time data), Firebase Authentication (user auth).
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP, openai-php/laravel (AI question generation).
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.

### Firebase Configuration
Firebase Firestore security rules (from `firebase-rules.txt`) must be deployed to the Firebase Console to manage read/write permissions for game-related paths.