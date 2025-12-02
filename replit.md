# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for an immersive and competitive experience, suitable for both educational and entertainment purposes. It features a Laravel backend and a React frontend. The project offers interactive quiz sessions across various themes and game modes, including a strategic avatar system with boss battles, comprehensive gameplay cycles (Solo, Duo, League, Master), and detailed progression tracking. Its core purpose is to provide a dynamic platform where players can compete, answer questions, track scores, and participate in a game show-style environment, with a vision for international expansion through multi-language support.

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### UI/UX Decisions
The frontend utilizes React 19 with Vite, employing a component-based architecture. The UI is designed for competitiveness with energetic chronometers, realistic buzz buttons, and score displays. It features a 3-column game question layout optimized for various mobile and tablet orientations, and strategic avatar skills are visually persistent. Recent UI enhancements include a symmetric resume screen layout, simplified boss presentations, dynamic question headers, and mobile responsiveness optimizations. The system integrates a multi-language UI with automatic browser detection and manual selection across 10 languages.

**Boutique System**: Refactored from tab-based interface to menu-style card navigation. The main boutique page displays 7 category cards in a responsive grid (4 columns landscape, 2 columns portrait). Each category (Packs, Musiques, Buzzers, Strategiques, Master, Coins, Vies) has a dedicated detail page accessible via /boutique/{category}. Features include orientation-aware responsive layouts and seamless back-navigation between pages.

### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It uses an API-first, service-oriented design with an event-driven system for real-time game state broadcasting. Key services include:
- **QuestionService**: Manages AI-ready, theme-based question generation with adaptive difficulty scaling based on opponent characteristics (age for students, expert level for bosses), **reinforced 3-layer anti-duplication system** (persistent storage of ALL answers including distractors, cumulative session tracking across blocks, and explicit OpenAI prompt instructions), a progressive block-based question generation system, and language-specific strict spelling verification.
- **SubthemeRotationSystem**: Deterministic sub-theme rotation using game ID as seed (Fisher-Yates shuffle). Restructured catalog with exactly **8 main themes × 15 sub-themes = 120 total sub-themes** (Geography, History, Sports, Sciences, Cinema, Art, Animals, Cuisine). **Culture générale** dynamically pulls from all 120 sub-themes for maximum diversity. AI temperature lowered to 0.3 for stricter instruction adherence with 5 mandatory rules (coherence, originality, no repetition, avant-garde approach, theme adherence).
- **Progressive Block Generation**: Questions are pre-generated in blocks during gameplay to eliminate wait times. Trigger timing optimized: Block 2 at Q1, Block 3 at Q4, Block 4 at Q7 (1 question earlier than consumption) ensuring questions are ready before needed. Includes performance timing logs for monitoring.
- **AnswerNormalizationService**: Normalizes all answers (correct + distractors) to prevent duplicates even when written differently (lowercase, accents removed, special chars normalized).
- **Advanced AI Opponent System**: Features a three-layer behavioral simulation for buzz decisions, speed, and answer accuracy. Boss battles include a radar competency diagram system to display boss strengths/weaknesses.
- **Gameplay Services**:
    - **GameStateService**: Manages centralized game state, supporting best-of-3 rounds, dual-track scoring, tiebreakers, and scalability for 1-40 players.
    - **BuzzManagerService**: Ensures fair multi-player buzz management with server-side timestamps, scoring rules, and anti-cheat measures.
    - **RoomService**: Provides abstract session/room management for Solo, Duo, League, and Master game modes with dynamic player limits and robust host management.
- **Unified Game Interface Architecture**:
    - **GameModeProvider**: Abstract base class defining the contract for all game modes. Methods: getOpponentType(), handleBuzz(), submitAnswer(), calculatePoints(), getScoring(), getMatchResult(), finishRound().
    - **SoloGameProvider**: Handles AI opponent simulation with buzz timing based on difficulty level (1-100), answer accuracy scaling for students/bosses, and progression-based scoring (+15/-5 points).
    - **DuoGameProvider**: Manages real player synchronization via Firebase Firestore, ELO/division calculations, and uses DuoFirestoreService for real-time state updates.
    - **LeagueGameProvider**: Handles ranked competitive matches with ELO K-factor 32, season points tracking (+50 win/-30 loss), and LeagueIndividualFirestoreService integration.
    - **MasterGameProvider**: Supports quiz master mode with 1 presenter + 3-40 players, ranking-based scoring (1st=10pts, 2nd=7pts, 3rd=5pts), and MasterFirestoreService for room management.
    - **UnifiedGameController**: Routes game logic to correct provider based on mode parameter. Routes: /game/{mode}/start, /game/{mode}/question, /game/{mode}/buzz, /game/{mode}/answer, /game/{mode}/round-result.
    - **game_unified.blade.php**: Universal game interface that adapts visually to any mode. Same UI components (chrono, avatars, answers, skills) with mode-specific opponent source (AI vs Firebase real-time).

### Feature Specifications
- **Game Modes**: Solo (best-of-3, 90 opponents, 10 boss battles), Duo (division-based, player code invites), League Individual (1v1 career, random matchmaking), League Team (5v5, team management), and Master (real-time hosting for 3-40 players with AI-powered question generation).
- **Avatar System**: User-specific avatars with themed packs, unlock requirements, and in-game skills. **12 avatars across 3 rarity tiers with 25 unique skills**:
  - **Rare (1 skill each)**: Mathématicien (illuminate numbers), Scientifique (acidify wrong answers), Explorateur (show popular answer), Défenseur (block attacks)
  - **Épique (2 skills each)**: Magicienne (cancel error + bonus question), Comédien (fake score + invert answers), Challenger (shuffle + reduce timer), Historien (hint + extra time)
  - **Légendaire (3 skills each)**: IA Junior (80% AI suggestion + eliminate 2 + replay), Stratège (coin bonus + team mode + unlock discount), Sprinteur (buzz rewind + extra reflection + auto-reset), Visionnaire (preview 5 questions + anti-challenger + lock correct)
  - **Skill Types**: PASSIVE (auto-active), VISUAL (modify display), ACTIVE_PRE (before answer), ACTIVE_POST (after answer/result)
  - **Skill Triggers**: ON_VICTORY, ON_QUESTION, ON_ANSWER, ON_ERROR, ALWAYS, MATCH_START, RESULT
  - **Session Tracking**: `used_skills` array persists throughout match to prevent duplicate skill usage
- **Progression**: Includes a Quest/Achievement System with 35 Standard quests, event-driven detection, and atomic transaction-based reward distribution. Player progression levels are displayed dynamically.
- **Real-time Features**: Utilizes Firebase Firestore for real-time game state synchronization in multiplayer modes, including microsecond-precision buzz systems and score updates.
- **Authentication**: Firebase Authentication (with social providers) and Laravel Sanctum for API token management, supporting multi-provider authentication and a unique Player Code System (SB-XXXX).
- **Multi-language Support**: Full integration for 10 languages (French, English, Spanish, Italian, Greek, German, Portuguese, Russian, Arabic, Chinese) with automatic browser detection, manual selection in user profiles, and language preference flowing through the entire question generation and spelling verification system. **413 translation keys per language** with zero hardcoded text tolerance.
- **Player-to-Player Chat System**: Complete messaging system with `player_messages` PostgreSQL table, `PlayerMessageService` for message handling, `ChatController` with REST API endpoints (/chat/send, /chat/conversation/{userId}, /chat/unread). Chat UI integrated in Duo lobby (invitations and contact book) and match result page with unread message badges. Features include real-time conversation loading, XSS protection via escapeHtml, and auto-refresh of unread counts.
- **Contact Book (Carnet)**: Automatic bidirectional contact creation via `PlayerContactService->ensureContactExists()` when Duo invitations are accepted. Contacts include player stats, win/loss records against the user, efficiency metrics, and chat button with unread indicator.
- **Multiplayer Lobby System**: Complete waiting room implementation for Duo/League/Master modes via `LobbyService` and `LobbyController`. Features include:
  - **12 Team Colors**: Red, blue, green, orange, purple, cyan, pink, yellow, teal, indigo, lime, brown with hex values and light variants
  - **Host Controls**: Only host can start game, modify settings, create teams; auto-reassignment when host leaves
  - **Ready States**: All non-host players must mark ready before game start; minimum player requirements enforced (2 for Duo/League, 3-40 for Master)
  - **Cache Persistence**: Lobby state stored via Laravel Cache with 1-hour TTL, 6-character alphanumeric codes
  - **Routes**: `/lobby/create`, `/lobby/{code}`, `/lobby/{code}/join`, `/lobby/{code}/ready`, `/lobby/{code}/color`, `/lobby/{code}/leave`, `/lobby/{code}/start`
  - **Translation Compliance**: All strings use `__()` helper for zero hardcoded text tolerance
  - **Duo Invitation Flow (Updated)**: Inviter creates lobby immediately and is redirected to wait for invitee. Invitee accepts and joins existing lobby via `redirect_url`. Decline mechanism updates Firestore with `status=declined` for real-time notification.
  - **Vertical Player Layout**: Players displayed in vertical list format with clickable cards for statistics
  - **Communication Buttons**: Chat (💬) and microphone (🎤) buttons on each player card
  - **Host-Only Settings**: Theme and question count controls visible only to host player
  - **Firestore Real-time Listener**: Lobby listens for invitation decline and player2 joining via Firebase Firestore

## External Dependencies

### Core Framework Dependencies
- Laravel Framework
- React
- Inertia.js

### Firebase Integration
- Firebase PHP SDK
- Firebase JavaScript SDK

### Authentication Services
- Laravel Sanctum
- Laravel Socialite

### Development and Build Tools
- Vite
- Laravel Vite Plugin
- Tightenco Ziggy

### HTTP and API Libraries
- Guzzle HTTP
- openai-php/laravel

### Payment and E-commerce
- Stripe PHP SDK

### Databases
- PostgreSQL (Replit Neon)
- Firebase Firestore