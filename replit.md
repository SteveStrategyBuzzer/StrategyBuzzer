# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application combining a Laravel backend with a React frontend. It offers interactive quiz sessions with various themes, allowing players to buzz in, answer questions, track scores, and compete in a game show-style environment. The application uses Firebase for authentication and real-time features, serving both educational and entertainment purposes. It features a strategic avatar system with boss battles, a comprehensive gameplay cycle, and a complete result page with progression data. The project aims to provide an immersive and competitive quiz experience.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes (October 08, 2025)
- **Avatar Selection Enhanced**: Modified avatars.blade.php to allow users to click on unlocked avatar packs, opening a modal with all pack images in a grid layout. Users can now select any avatar from an unlocked pack as their active player avatar, matching the preview functionality from the shop page.
- **Responsive Design Implementation**: Added comprehensive media queries for portrait and landscape orientations across all main pages (menu, avatars, boutique, game screens). Application now adapts seamlessly to mobile portrait, mobile landscape, tablet portrait, and tablet landscape orientations with optimized layouts and element sizing for each viewport.
- **Gameplay Mobile Optimization**: Redesigned game question screen for mobile to ensure BUZZ button is always visible without scrolling. Reduced padding, margins, and element sizes specifically for portrait mobile orientation.
- **Answer Without Buzz Feature**: Players can now answer questions even if they didn't buzz in time. New flow: timeout redirects to answer page (instead of result), players can select an answer for 0 points (no penalty/gain). Visual indicator shows "⚠️ Pas buzzé - Vous pouvez quand même répondre (0 point)" in red when player didn't buzz.
- **Question Removed from Answer Screen**: Answer page no longer displays the question text, keeping only the timer and answer choices for cleaner UI.
- **External Browser Fix**: Added GET fallback route for `/solo/start` to prevent "Method Not Allowed" errors when opening app in new tab/external browser. Browser attempts to restore last URL with GET request, now safely redirects to menu instead of crashing.

## System Architecture

### Frontend Architecture
- **React 19** with modern hooks and functional components.
- **Vite** for fast hot module replacement.
- **Component-based architecture** with reusable UI elements.
- **Dynamic theming system** supporting multiple visual atmospheres.
- **Real-time UI updates** for game state changes and player interactions.
- **Competitive UI Redesign** for gameplay screens with energetic chronometers, realistic buzz buttons, and score battle displays.
- **Viewport-Optimized Gameplay Screens** designed for 100% visibility without scrolling.

### Backend Architecture
- **Laravel 10** as the primary web framework following MVC pattern.
- **Inertia.js** for seamless SPA-like experience between Laravel and React.
- **API-first design** with web and API routes.
- **Service-oriented architecture** for game logic, scoring, and player management.
- **Event-driven system** for real-time game state broadcasting.
- **QuestionService** for AI-ready, theme-based question generation with difficulty scaling.
- **Advanced AI Opponent System** with three-layer behavioral simulation:
  - **Buzz Decision Layer**: 65-100% chance to buzz (scales with level)
  - **Speed Competition Layer**: 20-90% chance to be faster than player (scales with level)
  - **Answer Accuracy Layer**: 60-100% success rate (scales with level)
  - Progressive difficulty curve from beginner (level 1) to expert (level 100)

### Database and Storage
- **PostgreSQL (Replit Neon)** as the primary relational database for user data, game progress, and transactions.
- **Firebase Firestore** for real-time data synchronization during gameplay.
- **Firebase Authentication** for user management and social login.
- **Laravel's Eloquent ORM** for database abstraction.

### Authentication and Authorization
- **Firebase Authentication** with social providers.
- **Laravel Sanctum** for API token management and session handling.
- **Multi-provider authentication** supporting Firebase and Laravel's native auth (Email/Password, Apple, Phone).
- **Role-based access control**.

### Real-time Features
- **Firebase real-time database** for live game state synchronization.
- **WebSocket-like functionality** through Firebase listeners.
- **Event broadcasting** for buzz notifications, score updates, and game transitions.

### Gameplay and Progression
- **Complete Gameplay System Implementation** with Question, Answer, and Result screens.
- **SoloController** for comprehensive game state management and session tracking.
- **5-Round Match System**:
  - Each game consists of exactly 5 rounds
  - Intelligent question distribution: questions are distributed equitably across rounds
  - Handles all edge cases: 1-30+ questions with automatic remainder allocation to early rounds
  - Round progression tracking with visual indicators on all game screens
  - Zero-quota rounds are automatically skipped for games with fewer than 5 questions
  - Enforces total question count regardless of round boundaries
- **Strategic Avatar System with Boss Battles**: Level-based boss progression and avatar unlocking.
- **Advanced Scoring System**:
  - **+2 points** for being first to answer correctly
  - **+1 point** for being second to answer correctly
  - **-2 points** for buzzing with wrong answer
  - **0 points** for not buzzing
  - Detailed round feedback showing buzz order, speed, and points awarded
- **Expanded Question Database**: 50 unique questions per theme (7 themes × 50 = 350 total questions) eliminating repetition issues.
- **Sound system** for audio feedback on game events.

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
- **Stripe PHP SDK** (latest) - for coin purchasing.
- **Stripe Webhooks** - for secure payment confirmation.