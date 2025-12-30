# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application offering an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project aims to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

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

**Real-Time Multiplayer Synchronization - OPTION C Architecture (Updated Dec 2025):**
All multiplayer modes now use an **Authoritative Backend Architecture** for synchronized gameplay, designed to scale up to 10 players (League Team) and 40 players (Master mode):

1. **Game Start Flow**: When host clicks "Start" in lobby, `LobbyService` sends a `gameStarted` signal via Firebase. All players' lobby pages listen for this signal and navigate to the game page simultaneously.

2. **Question Synchronization (OPTION C)**:
   - Host calls `GameplayEngine.nextQuestion()` → `provider.fetchQuestion()` → backend API
   - Backend generates question via AI (Gemini)
   - Backend publishes directly to Firebase via `DuoFirestoreService.publishQuestion()` with:
     - `questionSequence`: Unique microsecond timestamp for deduplication
     - `publishedBy: 'backend'`: Flag to identify authoritative server publishes
   - ALL clients (including host) receive question via `listenForQuestions()` callback
   - Both players call `startQuestion()` simultaneously - perfect sync

   **CRITICAL - Backend Authoritative (Dec 2025):** 
   - Client-side `publishQuestion()` and `fetchAndPublishQuestion()` are DEPRECATED
   - Only backend publishes are accepted by listeners (`publishedBy === 'backend'`)
   - Solo mode is completely isolated and uses traditional page redirects

3. **Firestore Document Structure**: All multiplayer modes use unified `games/{mode}-match-{normalizedId}` documents where `normalizedId` is computed using CRC32 normalization (matching PHP and JS implementations).

   **Path Consistency:** The `normalizedId` MUST always be derived from `lobby_code` (the 6-character code like "ABC123"), NOT from the numeric `match_id`.

4. **Security**: `correct_index` and `is_correct` are **never** transmitted to clients via Firebase. Answer validation is strictly server-side.

5. **Scalability**: This architecture supports 2-10 players (Duo/League) with future support for 40 players (Master mode) planned.

**Note**: League modes use `DuoFirestoreService` for question publishing. Master mode (40 players) will use manual tempo control by the game master.

**GameplayEngine.js:**
A unified client-side module manages all game modes (Solo, Duo, League, Master), ensuring consistent gameplay behavior. It supports both local (Solo) and Firestore (multiplayer) providers for managing game state actions.
-   **Event Delegation Pattern:** Uses document-level event delegation with singleton guard (`_eventsBound` flag) to prevent duplicate listener registrations when `init()` runs multiple times during SPA-style navigation.
-   **Score Update Resilience:** MultiplayerFirestoreProvider includes retry logic with `setDoc(merge: true)` fallback for robust score synchronization.

**Question Management:**
-   **Question Cache System:** Utilizes a file-based cache for pre-generated questions, keyed by theme, level, and language. An asynchronous `GenerateQuestionsJob` handles background question generation.
-   **QuestionService:** Manages AI-ready, theme-based question generation with adaptive difficulty, anti-duplication, progressive block-based generation, and language-specific strict spelling verification using Google Gemini 2.0 Flash.
-   **SubthemeRotationSystem:** Implements deterministic sub-theme rotation across 8 main themes and 120 sub-themes.
-   **Progressive Block Generation:** Questions are pre-generated in blocks during gameplay to eliminate wait times.

**Firestore Structure & Authentication:**
All game modes use a unified `/gameSessions/{sessionId}` Firestore collection. Firebase Firestore security rules validate against `request.auth.uid` (Firebase anonymous UID) for all operations, with Laravel user IDs stored separately for display.

**Multiplayer Systems:**
-   **League Individual Matchmaking:** Firebase-based queue system (`leagueIndividualQueue`) for real-time opponent discovery, displaying up to 3 available opponents with stats.
-   **Duo Real-Time Matchmaking:** Firebase-based queue system (`duoQueue`) for real-time opponent discovery, including division selection, entry fees, and level-based matching.
-   **Multiplayer Resume Page:** Includes Chat, Mic toggle, and synchronized "GO" button for Duo/League modes, with Firebase auth reuse and cleanup for listeners.
-   **League Team Sub-Modes:** Supports "Classique," "Bataille de Niveaux," and "Queue Leu Leu (Relay)" modes with specific database schema for player order and duel pairings.
-   **League Team Gathering System:** Pre-match team assembly page with real-time connection status, skill-based player sorting, integrated voice chat, and captain-controlled lobby transitions.

**Core Services:**
-   **AnswerNormalizationService:** Normalizes answers to prevent duplicates.
-   **Advanced AI Opponent System:** Features a three-layer behavioral simulation for buzz decisions, speed, and accuracy, including boss battles.
-   **GameStateService:** Manages centralized game state, supporting best-of-3 rounds, dual-track scoring, and scalability.
-   **BuzzManagerService:** Ensures fair multi-player buzz management with server-side timestamps and anti-cheat measures.
-   **RoomService:** Provides abstract session/room management for various game modes.
-   **PlayerMessageService / ChatController:** Manages a complete player-to-player chat system.
-   **PlayerContactService:** Handles automatic bidirectional contact creation for player stats and chat integration.
-   **LobbyService / LobbyController:** Implements a complete waiting room system for multiplayer modes.

### Feature Specifications
-   **Game Modes**: Solo (90 opponents, 10 boss battles), Duo (division-based, player code invites), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players).

**Master Mode Game Structures (Dec 2025):**
The Master mode now supports 4 distinct game structures with full configuration:
1. **Chacun pour soi (free_for_all)**: Individual competition with up to 40 players
2. **Face à Face Multiple (team_open_skills)**: 2 teams up to 20 players each, ALL players can use avatar skills
3. **Face à Face Simple (team_buzzer_only)**: 2 teams, only the buzzer can answer and use skills
4. **Multi-Équipes (multi_team)**: 3-8 teams playing the same quiz simultaneously

Database schema includes `structure_type`, `team_count`, `team_size_cap`, `skill_policy`, and `buzz_rule` columns. Teams are managed via `master_game_teams` table with player assignments in `master_game_players.team_id`.
-   **Avatar System**: User-specific avatars with 12 avatars across 3 rarity tiers, offering 25 unique skills (Passive, Visual, Active_Pre, Active_Post).
-   **Progression**: Quest/Achievement System with 35 Standard quests, event-driven detection, and atomic transaction-based reward distribution.
-   **Real-time Features**: Utilizes Firebase Firestore for real-time game state synchronization.
-   **Authentication**: Firebase Authentication (with social providers) and Laravel Sanctum for API token management, supporting a unique Player Code System.
-   **Multi-language Support**: Full integration for 10 languages with automatic browser detection and language preference flow.
-   **WebRTC Voice Chat System**: Real-time voice communication for Duo, League Individual, and League Team modes using peer-to-peer WebRTC with Firebase Firestore signaling.

## External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore (for real-time data), Firebase Authentication (for user auth).
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP, openai-php/laravel (for AI question generation).
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.

### Firebase Configuration
Firebase Firestore security rules (as documented in `firebase-rules.txt`) are critical and must be deployed to the Firebase Console, granting necessary read/write permissions for game-related paths.