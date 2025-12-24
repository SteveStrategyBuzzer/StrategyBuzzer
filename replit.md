# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for an immersive and competitive experience. It features a Laravel backend and a React frontend, offering interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay cycles (Solo, Duo, League, Master). The project aims to provide a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

## User Preferences
Preferred communication style: Simple, everyday language.

**Multi-language Requirement (CRITICAL):**
- ALL user-facing text MUST be wrapped with `{{ __('text') }}` in Blade templates
- ALL new text MUST be translated in all 10 language files: `resources/lang/{ar,de,el,en,es,fr,it,pt,ru,zh}.json`
- Never add French-only text - always add translations for all languages

## System Architecture

### UI/UX Decisions
The frontend uses React 19 with Vite, employing a component-based architecture designed for competitiveness with energetic chronometers and realistic buzz buttons. It features a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. A multi-language UI with automatic browser detection and manual selection across 10 languages is integrated. The boutique system has been refactored to a menu-style card navigation, displaying 7 categories with dedicated detail pages and orientation-aware responsive layouts. This includes purchasable game modes: Duo, League, and Master, with corresponding user flags for ownership.

### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting.

**Real-Time Multiplayer Synchronization (Implemented Dec 2025)**
- Multiplayer modes (Duo, League, Master) now use SPA-style client-driven question transitions without page reloads
- Host fetches questions via secured `/game/{mode}/fetch-question` API endpoint (host-only access enforced)
- Questions are published to Firebase Firestore with `currentQuestionData` and `questionPublishedAt` timestamp
- Non-host clients receive questions via Firestore snapshot listeners, triggering instant UI updates
- Server-side answer validation: `correct_index` never exposed to clients, validated from session data
- Timer, buzz state, and answer buttons reset atomically on each new question

**GameplayEngine.js - Unified Client Module (Dec 2025)**
- Single module for ALL game modes (Solo, Duo, League, Master)
- Guarantees identical gameplay behavior across modes
- Providers: LocalProvider (Solo) and FirestoreProvider (multiplayer)
- Manages: startQuestion(), startTimer(), handleBuzz(), submitAnswer(), activateSkill(), updateScores()

**Environment Variables for Migration**
- `QUESTION_API_URL` : URL of Question API service (default: http://localhost:3000)
- `QUEUE_CONNECTION` : Laravel queue driver (database for async, sync for immediate)
- All external URLs use env() - no hardcoded localhost values

**Question Cache System (Dec 2025)**
- QuestionCacheService: File-based cache for pre-generated questions
- Cache key format: `questions:{theme}:{niveau}:{language}`
- GenerateQuestionsJob: Async job for background question generation
- Queue Worker workflow runs `php artisan queue:work` for async processing

**Unified Firestore Structure (Dec 2025)**
- All game modes now use `/gameSessions/{sessionId}` collection
- Legacy collections (duoMatches, leagueMatches, masterRooms) deprecated
- Security rules enforce member-only access with host privileges for global state

**League Individual Matchmaking System (Dec 2025)**
- Firebase-based queue system in `leagueIndividualQueue` collection for real-time opponent discovery
- Lobby displays up to 3 available opponents with avatar, efficiency %, and division emoji
- Player stats modal with Chart.js radar diagram showing per-category efficiency
- Division selector allowing play up to 2 divisions above current level with entry fee
- Game sessions stored in `games/league-individual-{matchId}` with consistent snake_case field naming
- Real-time Firestore listeners sync question data, scores, and buzz events between players
- LeagueIndividualFirestoreService uses consistent schema: player1_id, player1_score, currentQuestionNumber, currentQuestionData

**Multiplayer Resume Page Features (Dec 2025)**
- `duo_resume.blade.php` now includes Chat, Mic toggle, and synchronized GO button for Duo/League modes
- Firebase auth reuse pattern: single initialization with `firebaseInitialized` guard and `currentUser` check
- Proper cleanup: unsubscribe handles for onSnapshot listeners on page unload/pagehide
- Null-safe session handling: disables GO button and shows error when sessionId missing
- XSS protection: chat messages rendered via textContent, not innerHTML
- Graceful Firebase fallback: auto-proceeds after 10s timeout if Firebase unavailable

**Duo Mode Bug Fixes (Dec 2025)**
- Session cleanup in `startGame()` now happens AFTER validation to prevent wiping active sessions on validation failure
- Avatar path handling: detects full URLs (http/https), relative paths, and simple names to avoid double-wrapping with asset()
- Defensive validation for `$params`, `$opponentInfo`, `$scoring`, and `$avatarSkillsFull` arrays prevents crashes for invited players
- Skills array iteration guarded with `is_array()` checks including per-skillData validation
- Score initialization explicitly cast to (int) and reset to 0 at game start

Key services include:
-   **QuestionService**: Manages AI-ready, theme-based question generation with adaptive difficulty, a 3-layer anti-duplication system, progressive block-based generation, and language-specific strict spelling verification. It leverages Google Gemini 2.0 Flash.
-   **SubthemeRotationSystem**: Implements deterministic sub-theme rotation across 8 main themes and 120 sub-themes, with dynamic pulling for "Culture générale".
-   **Progressive Block Generation**: Questions are pre-generated in blocks during gameplay to eliminate wait times, with optimized trigger timings.
-   **AnswerNormalizationService**: Normalizes answers to prevent duplicates.
-   **Advanced AI Opponent System**: Features a three-layer behavioral simulation for buzz decisions, speed, and accuracy, including boss battles with radar competency diagrams.
-   **Gameplay Services**:
    -   **GameStateService**: Manages centralized game state, supporting best-of-3 rounds, dual-track scoring, and scalability for 1-40 players.
    -   **BuzzManagerService**: Ensures fair multi-player buzz management with server-side timestamps and anti-cheat measures.
    -   **RoomService**: Provides abstract session/room management for various game modes.
-   **Unified Game Interface Architecture**: Uses `GameModeProvider` as an abstract base class for different game modes (Solo, Duo, League, Master), routing game logic through a `UnifiedGameController`. Multiplayer question synchronization ensures all players in a match see identical questions, generated and stored by the host in mode-specific Firestore documents. A universal `game_unified.blade.php` adapts visually to any game mode.

### Feature Specifications
-   **Game Modes**: Solo (90 opponents, 10 boss battles), Duo (division-based, player code invites), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players).
-   **League Team Sub-Modes (Dec 2025)**:
    -   **Classique**: All 10 players see the same question. First buzz answers. All players can use skills freely at any time.
    -   **Bataille de Niveaux**: Matcher button pairs players by rank (1st vs 1st, 2nd vs 2nd, etc). 5 parallel duels. Team mic stays open, player-to-player chat with opponent.
    -   **Queue Leu Leu (Relay)**: Captain sets player order. Each player takes turns answering. Only the active player can use their skills.
    -   **Technical Implementation**:
        -   Database schema: `league_team_matches` table has `game_mode`, `player_order` (JSON), `duel_pairings` (JSON), `relay_indices` (JSON for dual-team tracking)
        -   Lobby UI: Host can select mode, configure player order (drag-drop), trigger matcher for Bataille mode
        -   LobbyController endpoints: `setGameMode`, `setPlayerOrder` (accepts team-keyed arrays), `matchPlayersByLevel`
        -   UnifiedGameController: Handles game_mode, player_order, duel_pairings from lobby settings
        -   LeagueTeamService: `advanceRelayPlayer()` rotates active player using relay_indices
        -   Skill locking: `game_unified.blade.php` checks `dataset.locked` before skill activation in Relais mode
-   **Avatar System**: User-specific avatars with 12 avatars across 3 rarity tiers, offering 25 unique skills (Passive, Visual, Active_Pre, Active_Post) triggered by various game events.
-   **Progression**: Includes a Quest/Achievement System with 35 Standard quests, event-driven detection, and atomic transaction-based reward distribution.
-   **Real-time Features**: Utilizes Firebase Firestore for real-time game state synchronization, including microsecond-precision buzz systems and score updates.
-   **Authentication**: Firebase Authentication (with social providers) and Laravel Sanctum for API token management, supporting a unique Player Code System.
-   **Multi-language Support**: Full integration for 10 languages with automatic browser detection, manual selection, and language preference flowing through question generation and spelling verification.
-   **Player-to-Player Chat System**: Complete messaging system with PostgreSQL table, `PlayerMessageService`, `ChatController`, and UI integration in Duo lobby and match result pages, featuring real-time conversation loading and XSS protection.
-   **Contact Book (Carnet)**: Automatic bidirectional contact creation via `PlayerContactService`, displaying player stats, win/loss records, and chat integration.
-   **Multiplayer Lobby System**: Complete waiting room implementation for Duo/League/Master modes via `LobbyService` and `LobbyController`. Features include 12 team colors, host controls, ready states, cache persistence, and a revised Duo invitation flow.
-   **League Team Gathering System (Dec 2025)**: Pre-match team assembly page with "Rassembler" button on team management page. Features include real-time connection status with avatar border glow effects, players sorted by skill level (60% efficiency + 40% last 10 matches win rate), integrated voice chat toggle and team chat, and captain-controlled lobby transition.
-   **WebRTC Voice Chat System (Dec 2025)**: Real-time voice communication for Duo, League Individual, and League Team modes using peer-to-peer WebRTC with Firebase Firestore signaling. Key components:
    -   `public/js/VoiceChat.js`: Shared module with RTCPeerConnection, SDP offer/answer exchange, ICE candidate handling
    -   Participants register as listeners even when muted, enabling audio reception
    -   Dynamic renegotiation when users enable/disable microphone mid-session
    -   Voice activity detection with speaking indicators
    -   STUN servers for NAT traversal (stun.l.google.com)
    -   Signaling via Firebase Firestore collections: voiceSessions/{sessionId}/participants, offers, answers, iceCandidates

## External Dependencies

-   **Core Framework Dependencies**: Laravel Framework, React, Inertia.js
-   **Firebase Integration**: Firebase PHP SDK, Firebase JavaScript SDK
-   **Authentication Services**: Laravel Sanctum, Laravel Socialite
-   **Development and Build Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy
-   **HTTP and API Libraries**: Guzzle HTTP, openai-php/laravel
-   **Payment and E-commerce**: Stripe PHP SDK
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore

### Firebase Configuration
Firebase Firestore security rules are critical for multiplayer modes and must be configured in the Firebase Console as documented in `firebase-rules.txt`. Required read/write permissions are needed for paths such as `duoMatches/{matchId}`, `lobbies/{lobbyCode}`, `leagueMatches/{matchId}`, and `masterRooms/{roomCode}`.