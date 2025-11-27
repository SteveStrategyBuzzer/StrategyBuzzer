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
- **SubthemeRotationSystem**: Deterministic sub-theme rotation using game ID as seed (Fisher-Yates shuffle). Expanded catalog of 25-30 sub-themes per major theme across 14 categories (Geography, History, Sports, Sciences, Cinema, Art, Animals, Cuisine, Culture générale, Music, Technology, Literature, Mythology, Video Games). AI temperature lowered to 0.3 for stricter instruction adherence with 5 mandatory rules (coherence, originality, no repetition, avant-garde approach, theme adherence).
- **AnswerNormalizationService**: Normalizes all answers (correct + distractors) to prevent duplicates even when written differently (lowercase, accents removed, special chars normalized).
- **Advanced AI Opponent System**: Features a three-layer behavioral simulation for buzz decisions, speed, and answer accuracy. Boss battles include a radar competency diagram system to display boss strengths/weaknesses.
- **Gameplay Services**:
    - **GameStateService**: Manages centralized game state, supporting best-of-3 rounds, dual-track scoring, tiebreakers, and scalability for 1-40 players.
    - **BuzzManagerService**: Ensures fair multi-player buzz management with server-side timestamps, scoring rules, and anti-cheat measures.
    - **RoomService**: Provides abstract session/room management for Solo, Duo, League, and Master game modes with dynamic player limits and robust host management.

### Feature Specifications
- **Game Modes**: Solo (best-of-3, 90 opponents, 10 boss battles), Duo (division-based, player code invites), League Individual (1v1 career, random matchmaking), League Team (5v5, team management), and Master (real-time hosting for 3-40 players with AI-powered question generation).
- **Avatar System**: User-specific avatars with themed packs, unlock requirements, and in-game skills.
- **Progression**: Includes a Quest/Achievement System with 35 Standard quests, event-driven detection, and atomic transaction-based reward distribution. Player progression levels are displayed dynamically.
- **Real-time Features**: Utilizes Firebase Firestore for real-time game state synchronization in multiplayer modes, including microsecond-precision buzz systems and score updates.
- **Authentication**: Firebase Authentication (with social providers) and Laravel Sanctum for API token management, supporting multi-provider authentication and a unique Player Code System (SB-XXXX).
- **Multi-language Support**: Full integration for 10 languages (French, English, Spanish, Italian, Greek, German, Portuguese, Russian, Arabic, Chinese) with automatic browser detection, manual selection in user profiles, and language preference flowing through the entire question generation and spelling verification system.

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