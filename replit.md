# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application offering an immersive and competitive quiz experience for both educational and entertainment purposes. It features a Laravel backend and a React frontend, enabling interactive quiz sessions across various themes and game modes. Key capabilities include a strategic avatar system with boss battles, comprehensive gameplay cycles (Solo, Duo, League, Master), and detailed progression tracking. The project aims to provide a dynamic platform where players can buzz in, answer questions, track scores, and compete in a game show-style environment.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes

### November 19, 2025 - Proactive Question Generation & Bonus Points System

**Feature: Zero-Delay Proactive Question Generation**
- **Objective**: Eliminate all waiting delays between questions during gameplay
- **Implementation**: 
  - New endpoint `/solo/generate-batch` generates 10-11 questions (11 for Magicienne avatar)
  - AJAX call during 9-second countdown pre-generates all questions for the round
  - AJAX call during round statistics screen pre-generates questions for next round
  - Questions stored in session: `pregenerated_questions_round_X`
  - Fallback to on-demand generation if batch fails
- **Result**: Players experience instant question transitions with zero API delays
- **Files Modified**: `routes/web.php`, `app/Http/Controllers/SoloController.php`, `resources/views/game_preparation.blade.php`, `resources/views/round_result.blade.php`

**Feature: Strategic Bonus Question Mechanics**
- **Objective**: Bonus question skill ("Question bonus") usable only after question 10
- **Implementation**:
  - Button disabled with CSS (grayscale, opacity 0.4) until question 10
  - Popup alert "✨ Ce skill est utilisable après la question 10" if clicked early
  - Separate tracking: `bonus_points_total` in session
  - Display format: "Points Gagnés: X +2 / 20" (base + bonus shown separately)
  - Bonus points excluded from efficiency calculation but included in total score
- **Files Modified**: `resources/views/game_question.blade.php`, `app/Http/Controllers/SoloController.php`, `resources/views/defeat.blade.php`, `resources/views/victory.blade.php`, `resources/views/round_result.blade.php`

### November 18, 2025 - Critical Bug Fixes

**Fixed: Question 10 Skip Bug (Race Condition)**
- **Problem**: During AI question generation (2-3 second delay), two rapid clicks on "GO" would increment `current_question_number` twice, skipping question 10
- **Root Cause**: OpenAI API latency created a vulnerability window allowing concurrent `/solo/next` requests
- **Solution Implemented**: 
  - **Server-side reentrancy guard**: Session flag `question_generation_pending` prevents concurrent `nextQuestion()` calls
  - **Comprehensive flag cleanup**: Flag reset in ALL exit paths (game() entry, victory/defeat/round-result redirects, normal flow)
  - **Frontend double-click protection**: GO button disabled after first click, countdown timer cleared
- **Files Modified**: `app/Http/Controllers/SoloController.php` (lines 350, 767-779, 863, 897, 951, 979), `resources/views/game_result.blade.php` (lines 975-997)

**Fixed: Skill Notification Display**
- **Problem**: "+2 points" skill bubble required manual dismissal
- **Solution**: Auto-close after 2 seconds for success notifications
- **Files Modified**: `resources/views/game_result.blade.php` (lines 965-971)

## System Architecture

### Frontend Architecture
The frontend utilizes **React 19** with Vite, built on a component-based architecture for real-time UI updates. It features a competitive UI redesign with energetic chronometers, realistic buzz buttons, score displays, and a 3-column game question layout optimized for various mobile and tablet orientations. Strategic avatar skills are visually persistent.

### Backend Architecture
The backend is built with **Laravel 10** following an MVC pattern, integrated with **Inertia.js** for an SPA-like experience. It uses an API-first, service-oriented design for game logic, scoring, and player management, incorporating an event-driven system for real-time game state broadcasting.
- **QuestionService**: Manages AI-ready, theme-based question generation with difficulty scaling and anti-duplication.
- **Advanced AI Opponent System**: Features a three-layer behavioral simulation for buzz decisions, speed, and answer accuracy.
- **Gameplay Services**:
    - **GameStateService**: Manages centralized game state, supporting best-of-3 rounds, dual-track scoring, tiebreakers, and 1-40 players.
    - **BuzzManagerService**: Ensures fair multi-player buzz management with server-side timestamps, scoring rules, and anti-cheat measures.
    - **RoomService**: Provides abstract session/room management for Solo, Duo, League, and Master game modes with dynamic player limits and robust host management.

### Database and Storage
- **PostgreSQL (Replit Neon)**: Primary relational database for permanent data (users, scores, quests).
- **Firebase Firestore**: Used for real-time data synchronization during multiplayer gameplay (Duo, League, Master modes).
- **Laravel Eloquent ORM**: For database abstraction.

### Authentication and Authorization
**Firebase Authentication** (with social providers) and **Laravel Sanctum** (for API token management) handle authentication. The system supports multi-provider authentication and includes a unique Player Code System (SB-XXXX) for invitations. Role-based access control is implemented, and profile completion is enforced.

### Real-time Features
Utilizes **Firebase Firestore** for real-time game state synchronization across multiplayer modes (Duo, League, Master). This includes microsecond-precision buzz systems, real-time score updates, and scalable session management.

### Gameplay and Progression
- **Solo Mode**: Best-of-3 matches, strategic avatar progression with 90 unique opponents (10 boss battles across 100 levels), advanced scoring, and life management.
- **Duo Mode**: Division-based progression, intelligent scoring, player code invitations, and best-of-3 matches.
- **League Individual Mode**: Permanent 1v1 career system with division progression and random matchmaking.
- **League Team Mode (5v5)**: Team management, division-based progression, and automatic matchmaking.
- **Avatar System**: Per-user avatar isolation with themed packs, unlock requirements, and in-game skills.
- **Master Mode (Maître du Jeu)**: Real-time quiz hosting for 3-40 participants, with mobile-optimized interface and **AI-Powered Question Generation** via OpenAI API (gpt-3.5-turbo) for various question types.
- **Quest/Achievement System**: Comprehensive system with 35 Standard quests, event-driven detection, atomic transaction-based reward distribution, and a minimalist UI.
- **Dual Audio System**: Ambient navigation and gameplay background music with continuity and synchronized events.
- **Opponents Gallery System**: Responsive gallery for 100 levels of opponents.

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