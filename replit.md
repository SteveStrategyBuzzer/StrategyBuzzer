# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application that combines a Laravel backend with a React frontend. It offers interactive quiz sessions across various themes, allowing players to buzz in, answer questions, track scores, and compete in a game show-style environment. The application uses Firebase for authentication and real-time features, serving both educational and entertainment purposes. It features a strategic avatar system with boss battles, a comprehensive gameplay cycle, and a detailed results page with progression data, aiming to provide an immersive and competitive quiz experience.

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **React 19** with modern hooks and functional components, using **Vite** for fast hot module replacement.
- **Component-based architecture** with reusable UI elements and a dynamic theming system.
- **Real-time UI updates** for game state changes and player interactions.
- **Competitive UI Redesign** for gameplay screens, including energetic chronometers, realistic buzz buttons, and score battle displays.
- **Viewport-Optimized Gameplay Screens** designed for 100% visibility without scrolling, adapting seamlessly to various mobile and tablet orientations.
- Features a 3-second concentration countdown screen before games.
- Visual improvements to the game question page, including strategic avatar alignment, balanced 1v1 opponent display, and a 3-column layout when a strategic avatar is selected.
- Skill activation no longer uses popup alerts.
- Persistent visual feedback for strategic avatar skills (golden pulsating glow) until used.

### Backend Architecture
- **Laravel 10** as the primary web framework, following an MVC pattern.
- **Inertia.js** for a seamless SPA-like experience between Laravel and React.
- **API-first design** with web and API routes, and a service-oriented architecture for game logic, scoring, and player management.
- **Event-driven system** for real-time game state broadcasting.
- **QuestionService** for AI-ready, theme-based question generation with difficulty scaling and answer randomization for multiple-choice questions.
- **Advanced AI Opponent System** with a three-layer behavioral simulation (buzz decision, speed competition, answer accuracy) offering a progressive difficulty curve. This includes strategic buzz timing adjustments based on player buzz time.
- Implementation of functional strategic avatar skills, such as "Calcul Rapide" to reveal correct answers.
- Dual opponent display formats: boss opponents (levels 10, 20, etc.) show circular avatar and full details; regular opponents show simplified textual layout.

#### Gameplay Services Architecture (October 2025)
**Universal gameplay services for all modes** (Solo, Duo, Ligue, Maître du jeu):

- **GameStateService**: Centralized game state management
  - Best of 3 rounds system with round isolation (scores reset between rounds)
  - Dual-track scoring: legacy scalaires (player/opponent) + generalized maps (_map suffix)
  - Tiebreaker by total score when max rounds reached without 2 clear victories
  - Support for 1-40 players with per-player tracking (player_scores_map, player_rounds_won_map, player_stats_map)
  - Penalty-aware totals (includes -2 for wrong answers)
  
- **BuzzManagerService**: Fair multi-player buzz management
  - Server-side timestamps (microtime) for guaranteed fairness
  - Scoring: +2 first correct, +1 second correct, -2 wrong answer, 0 no buzz
  - Anti-cheat: buzz validation (min/max delay), rate limiting (5 buzz/sec max)
  - Support for answers without buzz (0 points but counted)
  
- **RoomService**: Abstract session/room management
  - Unified room system for all game modes
  - Dynamic player limits: Solo (1), Duo (2), Ligue (10), Maître du jeu (40)
  - Robust host management with automatic reassignment
  - Array reindexing to prevent sparse indexes
  - Status tracking: waiting → playing → finished

### Database and Storage
- **PostgreSQL (Replit Neon)** as the primary relational database for user data, game progress, and transactions.
- **Firebase Firestore** for real-time data synchronization during gameplay.
- **Laravel's Eloquent ORM** for database abstraction.

### Authentication and Authorization
- **Firebase Authentication** with social providers.
- **Laravel Sanctum** for API token management and session handling.
- **Multi-provider authentication** supporting Firebase and Laravel's native auth (Email/Password, Apple, Phone).
- **Role-based access control**.

### Real-time Features
- **Firebase real-time database** for live game state synchronization.
- **WebSocket-like functionality** through Firebase listeners and event broadcasting for buzz notifications, score updates, and game transitions.

### Gameplay and Progression

### Solo Mode
- **Complete Gameplay System Implementation** with Question, Answer, and Result screens, managed by **SoloController** for game state and session tracking.
- **Best of 3 Match System** where winning 2 out of 3 matches progresses the player to the next level.
- **Strategic Avatar System with Boss Battles** for level-based progression and avatar unlocking. Strategic avatars persist across sessions.
- **Advanced Scoring System** with points for correct answers (+2 for first, +1 for second) and penalties for wrong buzzes (-2 points).
- **Expanded Question Database** with 50 unique questions per theme (350 total questions).
- **Sound system** for audio feedback on game events.
- Allows players to answer questions for 0 points even if they didn't buzz in time.
- **Life Management System**: Players have 3 lives (configurable), losing one per defeat. A 1-hour cooldown activates when lives reach 0, with continuous life regeneration until max lives are restored.
- **Level Progression System**: Players unlock the next level after winning 2 rounds out of 3 (max 100 levels). XP is awarded per victory. Features 90 unique opponents and boss opponents at specific levels (10, 20, ... 100).
- **Comprehensive Round Result Page**: Displays detailed statistics including theme, level, round scores, points, efficiency, global statistics, and remaining lives.
- **Simplified Question Result Page**: Shows essential information after each question (correct/incorrect, score, lives, progression).
- **Global Statistics System**: Tracks question results (correct/incorrect/unanswered) across all rounds.

### Duo Mode (October 2025)
- **Complete Backend Implementation** with DuoController, DuoMatchmakingService, DivisionService.
- **Division-Based Progression**: Point-based system (0-99 Bronze, 100-199 Argent, 200-299 Or, 300-399 Platine, 400-499 Diamant, 500+ Légende).
- **Intelligent Scoring**: +1 vs weaker opponent (lower level), +2 vs equal level, +5 vs stronger opponent (higher level), -2 for loss.
- **Matchmaking System**: Invite specific player by name or random matchmaking within same division.
- **Best-of-3 System**: Draws replay the same round without consuming a round slot; match ends when a player wins 2 rounds or 3 decisive rounds are played (tiebreaker by total score).
- **Real-time Gameplay**: Server-side buzz timestamps, fair multi-player buzz validation, anti-cheat measures.
- **Database Schema**: duo_matches, player_duo_stats, player_divisions tables with proper relations.
- **Unlock Requirement**: 100 Solo matches played (tracked as defeats-victories/100).
- **Complete Frontend**: 5 pages (Lobby, Matchmaking, Game, Results, Rankings) with responsive design and real-time updates.
- **Features**: Player invitations, pending invitations display, division-based rankings, detailed match statistics, accuracy tracking.

## External Dependencies

### Core Framework Dependencies
- **Laravel Framework** (^10.10)
- **React** (^19.1.1)
- **Inertia.js** (^0.6.8)

### Firebase Integration
- **Firebase PHP SDK** (^7.18)
- **Firebase JavaScript SDK** (v10.12.2)
- **Firebase Authentication**
- **Firebase Firestore**

### Authentication Services
- **Laravel Sanctum** (^3.2)
- **Laravel Socialite** (^5.21)

### Development and Build Tools
- **Vite** (^6.3.5)
- **Laravel Vite Plugin** (^1.3.0)
- **Tightenco Ziggy** (^2.0)

### HTTP and API Libraries
- **Guzzle HTTP** (^7.2)

### Payment and E-commerce
- **Stripe PHP SDK** (latest)
- **Stripe Webhooks**