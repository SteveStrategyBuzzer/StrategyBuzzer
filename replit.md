# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application offering an immersive and competitive quiz experience for both educational and entertainment purposes. It features a Laravel backend and a React frontend, enabling interactive quiz sessions across various themes and game modes. Key capabilities include a strategic avatar system with boss battles, comprehensive gameplay cycles (Solo, Duo, League, Master), and detailed progression tracking. The project aims to provide a dynamic platform where players can buzz in, answer questions, track scores, and compete in a game show-style environment.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes

### November 22, 2025 - UI/UX Polish & Critical Bug Fixes

**Bug Fixed: Opponent Score Display**
- **Problem**: In-game scoreboard (`game_question.blade.php`) showed incorrect opponent score (e.g., 4/5 displayed when actual score was 5/0)
- **Root Cause**: Used derived calculation `current_question - 1 - score` instead of authoritative session data
- **Solution**: Changed to use `$params['opponent_score']` directly from session
- **Impact**: Real-time scoreboard now accurately reflects opponent's actual score throughout gameplay

**UI Enhancement: Boss Presentation Simplification**
- **Change**: Simplified `boss_presentation.blade.php` from 2-column layout to single-column centered layout
- **New Layout**: Boss avatar + name at top, radar competency diagram directly underneath
- **Rationale**: Cleaner, more focused presentation before boss battles

**UI Enhancement: "Le saviez-vous" OpenAI Prompt Improvement**
- **Change**: Modified OpenAI prompt in `game_result.blade.php` to request contextual explanations
- **New Prompt**: Now asks "POURQUOI est-ce la bonne réponse?" (Why is this the correct answer?)
- **Example**: For pink flamingos question, generates explanation about diet (algae/crustaceans with carotenoids) instead of generic facts
- **Impact**: More educational and contextually relevant fun facts after each question

**UI Enhancement: Game Question Header Redesign**
- **Old Format**: "QUESTION X/10" with separate score display
- **New Format**: "Réponse #X | Valeur de X point(s) | Actuellement -2/4"
- **Improvement**: Single-line header showing question number, point value, and live scores in compact format

**UI Enhancement: Fixed-Width Score Cards**
- **Problem**: Score cards in `game_result.blade.php` resized during gameplay causing visual jumps
- **Solution**: Changed from `max-width` to fixed `width: 150px` for VOUS and ADVERSAIRE cards
- **Impact**: Stable, consistent card sizing throughout match

**UI Fix: Removed Duplicate Efficiency Block**
- **Problem**: `round_result.blade.php` displayed isolated "Efficacité de la Manche X" block separate from per-round stats
- **Solution**: Removed redundant block since efficiency already shown in detailed round statistics
- **Impact**: Cleaner, less redundant round result screen

**UI Enhancement: Efficiency Label Clarification**
- **Change**: Renamed "Efficacité Max de la Partie" → "Efficacité du Match"
- **Rationale**: "Max" was misleading (displayed max efficiency per round instead of match average)
- **New Behavior**: Now correctly displays global match efficiency (total points earned / max possible points × 100)
- **Files Modified**: `resources/views/victory.blade.php`, `resources/views/defeat.blade.php`, `resources/views/round_result.blade.php`

### November 21, 2025 - Boss Radar Diagram System & UI Improvements

**Feature: Boss Presentation Screen with Radar Competency Diagrams**
- **Objective**: Display boss strengths/weaknesses across 9 themes before battle for strategic gameplay
- **Implementation**:
  - **New View**: `boss_presentation.blade.php` with 2-column layout "Vous vs Boss"
  - **Radar Chart**: Chart.js integration showing boss competency (0-100) across 9 themes (Général, Cinéma, Science, Géographie, Histoire, Art, Culture, Sport, Cuisine)
  - **Boss Data**: Extended `config/opponents.php` with radar profiles for all 10 boss (levels 10, 20, 30, 40, 50, 60, 70, 80, 90, 100)
  - **Flow**: Boss detection in `SoloController::start()` → redirect to `boss_presentation` → auto-continue after 10 seconds → `game_preparation`
  - **Player Side**: Displays selected strategic avatar with active skills
  - **Boss Side**: Shows boss avatar, name, level, and radar diagram
- **Files Modified**: `config/opponents.php`, `resources/views/boss_presentation.blade.php`, `app/Http/Controllers/SoloController.php`, `routes/web.php`

**Feature: Enhanced Game Result Screen**
- **Removed**: Large checkmark/X header to reduce visual bias
- **Added**: Dynamic opponent naming (Solo: boss/student name, Duo/League: player ID, Master: quiz name)
- **Unified Colors**: Changed score display from green/red to neutral purple to avoid winner/loser bias
- **2-Column Stats Grid**: Responsive CSS Grid layout for Score, Lives, Progress, Level
- **"Le saviez-vous" Section**: OpenAI-generated fun facts (2 sentences max) related to question with graceful fallbacks
- **Multiplayer "Prêt" Button**: Conditional display for Duo/League/Master modes with player counter
- **Files Modified**: `app/Http/Controllers/SoloController.php`, `resources/views/game_result.blade.php`

### November 20, 2025 - Progressive Block Generation System & Critical Bug Fixes

**Feature: Progressive Block-Based Question Generation (2→3→3→3)**
- **Objective**: Reduce initial wait time while eliminating delays between questions
- **Implementation**: 
  - **Replaced batch system** with progressive blocks: Bloc 1 (2Q) → Bloc 2 (3Q) → Bloc 3 (3Q) → Bloc 4 (3Q) → Bloc bonus (1Q for Magicienne)
  - New endpoint `/solo/generate-block` generates 2 or 3 questions per block with anti-duplication
  - **Countdown phase**: Generates Bloc 1 (2 questions) during 9-second countdown → game starts immediately
  - **During gameplay**: Proactively generates blocks 2-3-4 at questions 2, 4, and 7 while player answers
  - **Round transitions**: Generates Bloc 1 of next round during pause screen
  - **Fallback persistence**: On-demand questions now added to stock to prevent repeated slow calls
  - **Stock cleanup**: Automatic removal of stale questions at start of each round
  - Questions stored in session: `question_stock_round_{round}`
- **Architecture**: Progressive just-in-time generation maintains 2-3 question buffer without generating all 11 upfront
- **Known Limitation**: Triggers based on fixed question numbers (2,4,7) may skip during fast play or page refresh, causing stock depletion and fallback generation
- **Files Modified**: `routes/web.php`, `app/Http/Controllers/SoloController.php`, `resources/views/game_preparation.blade.php`, `resources/views/game_result.blade.php`, `resources/views/round_result.blade.php`

**Bug #1 Fixed: Missing $globalEfficiency Calculation**
- **Problem**: `roundResult()` displayed efficiency as null (line 1132)
- **Solution**: Added `$this->calculateEfficiency($globalStats)` call to compute global efficiency
- **Impact**: Round summary now displays correct efficiency percentage

**Bug #2 Fixed: "No Choice" Answer Handling**
- **Problem**: Timeout auto-selected last answer instead of allowing "no choice" penalty
- **Solution**: 
  - Frontend: `handleTimeout()` sets `answer_index = -1` explicitly
  - Backend: Detects `answerIndex === -1` and applies -2 points if player buzzed
- **Impact**: Players can now receive -2 points for not selecting an answer after buzzing

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