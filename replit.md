# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application that combines a Laravel backend with a React frontend. It offers interactive quiz sessions across various themes, allowing players to buzz in, answer questions, track scores, and compete in a game show-style environment. The application uses Firebase for authentication and real-time features, serving both educational and entertainment purposes. It features a strategic avatar system with boss battles, a comprehensive gameplay cycle, and a detailed results page with progression data, aiming to provide an immersive and competitive quiz experience.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes
- **Dual Audio System Implemented** (October 24, 2025):
  - **Ambient Navigation Music**: Starts automatically on menu.blade.php after login with default "StrategyBuzzer" track, continues during navigation (menu/solo/duo/boutique), pauses during gameplay, resumes after, resets on logout. Players can customize/disable via profile settings.
  - **Gameplay Background Music**: Continuous at -6 dB (volume 0.5) across all gameplay pages (game_question → game_answer → game_result → victory/defeat) with localStorage position tracking and autoplay fallback mechanisms.
  - **Audio Synchronization**: Countdown synchronized with "ladies and gentlemen are you ready.mp3" (6.5s) using audio.currentTime for perfect sync.
- Removed obsolete stat.blade.php intermediate page; game redirects directly to comprehensive results (game_result/victory/defeat)

## System Architecture

### Frontend Architecture
- **React 19** with Vite for fast development and a component-based architecture.
- **Real-time UI updates** for game state and player interactions.
- **Competitive UI Redesign** with energetic chronometers, realistic buzz buttons, and score battle displays.
- **Viewport-Optimized Gameplay Screens** for seamless adaptation to various mobile and tablet orientations, without scrolling.
- **3-Column Game Question Layout**: LEFT (player + opponent circles), CENTER (large chrono), RIGHT (strategic avatar + 3 skill circles), Strategy Buzzer button centered at bottom.
- Persistent visual feedback for strategic avatar skills (golden pulsating glow).

### Backend Architecture
- **Laravel 10** as the primary web framework, using an MVC pattern.
- **Inertia.js** for a seamless SPA-like experience.
- **API-first design** with a service-oriented architecture for game logic, scoring, and player management.
- **Event-driven system** for real-time game state broadcasting.
- **QuestionService** for AI-ready, theme-based question generation with difficulty scaling.
- **Advanced AI Opponent System** with a three-layer behavioral simulation (buzz decision, speed competition, answer accuracy).
- Functional strategic avatar skills (e.g., "Calcul Rapide").

#### Gameplay Services Architecture
- **GameStateService**: Centralized game state management supporting best of 3 rounds, dual-track scoring, tiebreakers, and 1-40 players with per-player tracking.
- **BuzzManagerService**: Fair multi-player buzz management with server-side timestamps, scoring rules (+2 first correct, +1 second, -2 wrong), and anti-cheat measures.
- **RoomService**: Abstract session/room management for all game modes (Solo, Duo, Ligue, Maître du jeu) with dynamic player limits and robust host management.

### Database and Storage
- **PostgreSQL (Replit Neon)** as the primary relational database.
- **Firebase Firestore** for real-time data synchronization during gameplay.
- **Laravel Eloquent ORM** for database abstraction.

### Authentication and Authorization
- **Firebase Authentication** with social providers and **Laravel Sanctum** for API token management.
- **Multi-provider authentication** (Firebase, Laravel native Email/Password, Apple, Phone).
- **Player Code System**: Unique alphanumeric codes (SB-XXXX) for all users, used for invitations in Duo mode.
- **Role-based access control**.
- **Profile Completion Enforcement**: Users must complete their profile before accessing the main menu.

### Real-time Features
- **Firebase real-time database** for live game state synchronization.
- **WebSocket-like functionality** through Firebase listeners and event broadcasting.

### Gameplay and Progression
- **Solo Mode**: Complete gameplay system with best of 3 matches, strategic avatar progression with boss battles, advanced scoring, life management system (3 lives, 1-hour cooldown), and level progression (90 unique opponents, 100 levels).
- **Duo Mode**: Backend and frontend implementation with division-based progression (Bronze to Légende), intelligent scoring, player code system for invitations, and a best-of-3 match system with real-time gameplay. Unlock requirement: 100 Solo matches played.
- **League Individual Mode**: Permanent 1v1 career system with division progression, random matchmaking, and identical scoring to Duo mode. Reuses universal gameplay services.
- **League Team Mode (5v5)**: Team management system (create, invite, manage 5-player rosters), division-based progression, automatic team matchmaking, and 5v5 team gameplay.
- **Avatar System**: Per-user avatar isolation with themed avatar packs, unlock requirements, and a mandatory avatar selection before menu access. Strategic avatars with in-game skills persist across sessions.
- **Master Mode (Maître du Jeu)**: Real-time quiz hosting platform for 3-40 participants with mobile-optimized interface. Integrates **AI-Powered Question Generation** via OpenAI API (gpt-3.5-turbo) supporting multiple choice, true/false, and image-based observation questions. Features interactive question editing and image upload.

## External Dependencies

### Core Framework Dependencies
- **Laravel Framework** (^10.10)
- **React** (^19.1.1)
- **Inertia.js** (^0.6.8)

### Firebase Integration
- **Firebase PHP SDK** (^7.18)
- **Firebase JavaScript SDK** (v10.12.2)

### Authentication Services
- **Laravel Sanctum** (^3.2)
- **Laravel Socialite** (^5.21)

### Development and Build Tools
- **Vite** (^6.3.5)
- **Laravel Vite Plugin** (^1.3.0)
- **Tightenco Ziggy** (^2.0)

### HTTP and API Libraries
- **Guzzle HTTP** (^7.2)
- **openai-php/laravel** (v0.11.0)

### Payment and E-commerce
- **Stripe PHP SDK** (latest)