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

**Real-Time Multiplayer Synchronization (Updated Dec 2025):**
All multiplayer modes now use a **server-side Firebase publishing architecture** for synchronized gameplay:

1. **Game Start Flow**: When host clicks "Start" in lobby, `LobbyService` sends a `gameStarted` signal via Firebase. Both players' lobby pages listen for this signal and navigate to the game page simultaneously.

2. **Question Synchronization**: Host calls `fetchQuestionJson` API → backend generates question → `DuoFirestoreService.publishQuestion()` publishes to Firebase → all clients receive identical question data via Firestore listeners.

   **CRITICAL - Host-Only Question Generation (Dec 2025):** Only the host generates questions in `UnifiedGameController::startGame()`. The guest does NOT generate questions; it listens for them via `MultiplayerFirestoreProvider.listenForQuestions()`. This prevents each player from having different questions. The `host_id` from `LobbyService` is required to determine who is the host.

3. **Firestore Document Structure**: All multiplayer modes use unified `games/duo-match-{normalizedId}` documents where `normalizedId` is computed using CRC32 normalization (matching PHP and JS implementations).

   **CRITICAL - Path Consistency (Dec 2025):** The `normalizedId` MUST always be derived from `lobby_code` (the 6-character code like "ABC123"), NOT from the numeric `match_id` (database ID like 123). This ensures:
   - Lobby listener uses `normalizeMatchId(lobbyCode)`
   - Gameplay `sessionId` prioritizes `lobby_code` over `match_id`
   - Backend `DuoFirestoreService.publishQuestion()` uses `lobbyCode`

4. **Security**: `correct_index` and `is_correct` are **never** transmitted to clients via Firebase. Answer validation is strictly server-side.

**Note**: League modes currently use `DuoFirestoreService` for question publishing but may have other operations still using mode-specific services. Full migration to unified Firestore namespace pending for league modes.

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