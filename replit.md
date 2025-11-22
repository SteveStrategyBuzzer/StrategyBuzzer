# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for an immersive and competitive experience, suitable for both educational and entertainment purposes. It features a Laravel backend and a React frontend. The project offers interactive quiz sessions across various themes and game modes, including a strategic avatar system with boss battles, comprehensive gameplay cycles (Solo, Duo, League, Master), and detailed progression tracking. Its core purpose is to provide a dynamic platform where players can compete, answer questions, track scores, and participate in a game show-style environment.

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### UI/UX Decisions
The frontend utilizes React 19 with Vite, employing a component-based architecture. The UI is designed for competitiveness with energetic chronometers, realistic buzz buttons, and score displays. It features a 3-column game question layout optimized for various mobile and tablet orientations, and strategic avatar skills are visually persistent. Recent UI enhancements include a symmetric resume screen layout, simplified boss presentations, and dynamic question headers.

### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It uses an API-first, service-oriented design with an event-driven system for real-time game state broadcasting. Key services include:
- **QuestionService**: Manages AI-ready, theme-based question generation with difficulty scaling and anti-duplication. A progressive block-based question generation system (2→3→3→3) is implemented to reduce wait times.
- **Advanced AI Opponent System**: Features a three-layer behavioral simulation for buzz decisions, speed, and answer accuracy. Boss battles include a radar competency diagram system to display boss strengths/weaknesses.
- **Gameplay Services**:
    - **GameStateService**: Manages centralized game state, supporting best-of-3 rounds, dual-track scoring, tiebreakers, and scalability for 1-40 players.
    - **BuzzManagerService**: Ensures fair multi-player buzz management with server-side timestamps, scoring rules, and anti-cheat measures.
    - **RoomService**: Provides abstract session/room management for Solo, Duo, League, and Master game modes with dynamic player limits and robust host management.

### Feature Specifications
- **Game Modes**: Solo (best-of-3, 90 opponents, 10 boss battles), Duo (division-based, player code invites), League Individual (1v1 career, random matchmaking), League Team (5v5, team management), and Master (real-time hosting for 3-40 players with AI-powered question generation).
- **Avatar System**: User-specific avatars with themed packs, unlock requirements, and in-game skills.
- **Progression**: Includes a Quest/Achievement System with 35 Standard quests, event-driven detection, and atomic transaction-based reward distribution.
- **Real-time Features**: Utilizes Firebase Firestore for real-time game state synchronization in multiplayer modes, including microsecond-precision buzz systems and score updates.
- **Authentication**: Firebase Authentication (with social providers) and Laravel Sanctum for API token management, supporting multi-provider authentication and a unique Player Code System (SB-XXXX).

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
```