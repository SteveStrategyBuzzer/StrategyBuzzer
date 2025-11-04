# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for both educational and entertainment purposes. It features a Laravel backend and a React frontend, offering interactive quiz sessions with various themes. Players can buzz in, answer questions, track scores, and compete in a game show-style environment. Key capabilities include a strategic avatar system with boss battles, a comprehensive gameplay cycle across multiple modes (Solo, Duo, League, Master), and a detailed results page with progression data, aiming to provide an immersive and competitive quiz experience. The project leverages Firebase for authentication and real-time features.

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **React 19** with Vite for a component-based architecture and fast development.
- **Real-time UI updates** for game state and player interactions.
- **Competitive UI Redesign** with energetic chronometers, realistic buzz buttons, and score battle displays.
- **Viewport-Optimized Gameplay Screens** adaptable to various mobile and tablet orientations without scrolling.
- **3-Column Game Question Layout**: Features player/opponent circles, a large chronometer, strategic avatar with skill circles, and a centered Strategy Buzzer button.
- Persistent visual feedback for strategic avatar skills.

### Backend Architecture
- **Laravel 10** as the primary web framework, utilizing an MVC pattern.
- **Inertia.js** for a seamless Single Page Application (SPA)-like experience.
- **API-first design** with a service-oriented architecture for game logic, scoring, and player management.
- **Event-driven system** for real-time game state broadcasting.
- **QuestionService** for AI-ready, theme-based question generation with difficulty scaling and an anti-duplication system ensuring no player sees the same question twice.
- **Advanced AI Opponent System** with a three-layer behavioral simulation (buzz decision, speed competition, answer accuracy).

#### Gameplay Services Architecture
- **GameStateService**: Manages centralized game state, supporting best-of-3 rounds, dual-track scoring, tiebreakers, and 1-40 players with per-player tracking.
- **BuzzManagerService**: Ensures fair multi-player buzz management with server-side timestamps, scoring rules, and anti-cheat measures.
- **RoomService**: Provides abstract session/room management for all game modes (Solo, Duo, Ligue, Maître du jeu) with dynamic player limits and robust host management.

### Database and Storage
- **PostgreSQL (Replit Neon)** serves as the primary relational database for permanent data (users, scores, quests).
- **Firebase Firestore** is used for real-time data synchronization during multiplayer gameplay (Duo, League, Master modes).
- **Laravel Eloquent ORM** for database abstraction.

### Authentication and Authorization
- **Firebase Authentication** with social providers and **Laravel Sanctum** for API token management.
- Supports multi-provider authentication (Firebase, Laravel native Email/Password, Apple, Phone).
- **Player Code System**: Unique alphanumeric codes (SB-XXXX) for all users, used for invitations in Duo mode.
- Role-based access control.
- Enforces profile completion before accessing the main menu.

### Real-time Features
- **Firebase real-time database** for live game state synchronization.
- **WebSocket-like functionality** through Firebase listeners and event broadcasting.

### Gameplay and Progression
- **Solo Mode**: Features best-of-3 matches, strategic avatar progression with boss battles (90 unique opponents across 100 levels), advanced scoring, and a life management system.
- **Duo Mode**: Backend and frontend implementation with division-based progression (Bronze to Légende), intelligent scoring, player code invitations, and a best-of-3 match system. Requires 100 Solo matches played.
- **League Individual Mode**: Permanent 1v1 career system with division progression and random matchmaking.
- **League Team Mode (5v5)**: Team management, division-based progression, automatic team matchmaking, and 5v5 team gameplay.
- **Avatar System**: Per-user avatar isolation with themed packs, unlock requirements, and mandatory selection. Strategic avatars have in-game skills that persist across sessions.
- **Master Mode (Maître du Jeu)**: Real-time quiz hosting platform for 3-40 participants with a mobile-optimized interface, integrating **AI-Powered Question Generation** via OpenAI API (gpt-3.5-turbo) for multiple choice, true/false, and image-based observation questions.
- **Quest/Achievement System**: Comprehensive system with 35 Standard quests, automatic event-driven detection, atomic transaction-based reward distribution, retroactive unlock scanning, and a minimalist badge-grid UI with detailed modal popups.
- **Dual Audio System**: Implements ambient navigation music and separate gameplay background music with continuity features and synchronized audio events.
- **Opponents Gallery System**: Dual-layout (portrait/landscape) responsive gallery for 100 levels of opponents, including 10 Boss battles, with clear progression and visual feedback for current/locked levels.

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