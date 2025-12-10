# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for an immersive and competitive experience. It features a Laravel backend and a React frontend, offering interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay cycles (Solo, Duo, League, Master). The project aims to provide a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### UI/UX Decisions
The frontend uses React 19 with Vite, employing a component-based architecture designed for competitiveness with energetic chronometers and realistic buzz buttons. It features a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. A multi-language UI with automatic browser detection and manual selection across 10 languages is integrated. The boutique system has been refactored to a menu-style card navigation, displaying 7 categories with dedicated detail pages and orientation-aware responsive layouts. This includes purchasable game modes: Duo, League, and Master, with corresponding user flags for ownership.

### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting. Key services include:
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
-   **Game Modes**: Solo (90 opponents, 10 boss battles), Duo (division-based, player code invites), League Individual (1v1 career), League Team (5v5), and Master (real-time hosting for 3-40 players).
-   **Avatar System**: User-specific avatars with 12 avatars across 3 rarity tiers, offering 25 unique skills (Passive, Visual, Active_Pre, Active_Post) triggered by various game events.
-   **Progression**: Includes a Quest/Achievement System with 35 Standard quests, event-driven detection, and atomic transaction-based reward distribution.
-   **Real-time Features**: Utilizes Firebase Firestore for real-time game state synchronization, including microsecond-precision buzz systems and score updates.
-   **Authentication**: Firebase Authentication (with social providers) and Laravel Sanctum for API token management, supporting a unique Player Code System.
-   **Multi-language Support**: Full integration for 10 languages with automatic browser detection, manual selection, and language preference flowing through question generation and spelling verification.
-   **Player-to-Player Chat System**: Complete messaging system with PostgreSQL table, `PlayerMessageService`, `ChatController`, and UI integration in Duo lobby and match result pages, featuring real-time conversation loading and XSS protection.
-   **Contact Book (Carnet)**: Automatic bidirectional contact creation via `PlayerContactService`, displaying player stats, win/loss records, and chat integration.
-   **Multiplayer Lobby System**: Complete waiting room implementation for Duo/League/Master modes via `LobbyService` and `LobbyController`. Features include 12 team colors, host controls, ready states, cache persistence, and a revised Duo invitation flow.
-   **WebRTC Voice Chat System**: Real-time voice communication for Duo, League Individual, and League Team modes using peer-to-peer WebRTC with Firebase Firestore signaling, including voice activity detection, speaking indicators, and mute/unmute controls.

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