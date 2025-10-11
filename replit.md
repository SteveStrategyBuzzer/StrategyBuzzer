# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application that combines a Laravel backend with a React frontend. It offers interactive quiz sessions across various themes, allowing players to buzz in, answer questions, track scores, and compete in a game show-style environment. The application uses Firebase for authentication and real-time features, serving both educational and entertainment purposes. It features a strategic avatar system with boss battles, a comprehensive gameplay cycle, and a detailed results page with progression data, aiming to provide an immersive and competitive quiz experience.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes (October 11, 2025)
- **Game Question Page Complete Redesign**: Major restructure with 3-column layout: (1) LEFT: Player avatar + score + random player name (Jade, Hugo, etc.) with level, (2) CENTER: Chronometer, (3) RIGHT: Strategic avatar + skill icons displayed as circular buttons (icon only, no text) vertically stacked. Removed opponent from main display. Buzzer replaced with realistic image (buzzer.png with "STRATEGY BUZZ BUZZER" text). All responsive breakpoints updated for new layout.
- **Strategic Avatar Session Sync**: Fixed strategic avatar persistence across sessions. Added auto-restore logic in SoloController (start, resume, game methods) that checks profile_settings when session is empty or 'Aucun'. Strategic avatars now persist correctly after session expiration.

## System Architecture

### Frontend Architecture
- **React 19** with modern hooks and functional components, using **Vite** for fast hot module replacement.
- **Component-based architecture** with reusable UI elements and a dynamic theming system.
- **Real-time UI updates** for game state changes and player interactions.
- **Competitive UI Redesign** for gameplay screens, including energetic chronometers, realistic buzz buttons, and score battle displays.
- **Viewport-Optimized Gameplay Screens** designed for 100% visibility without scrolling, adapting seamlessly to various mobile and tablet orientations.
- Features a 3-second concentration countdown screen before games.

### Backend Architecture
- **Laravel 10** as the primary web framework, following an MVC pattern.
- **Inertia.js** for a seamless SPA-like experience between Laravel and React.
- **API-first design** with web and API routes, and a service-oriented architecture for game logic, scoring, and player management.
- **Event-driven system** for real-time game state broadcasting.
- **QuestionService** for AI-ready, theme-based question generation with difficulty scaling and answer randomization for multiple-choice questions.
- **Advanced AI Opponent System** with a three-layer behavioral simulation (buzz decision, speed competition, answer accuracy) offering a progressive difficulty curve.

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
- **Complete Gameplay System Implementation** with Question, Answer, and Result screens, managed by **SoloController** for game state and session tracking.
- **5-Round Match System** with intelligent question distribution and automatic round skipping for games with fewer than 5 questions.
- **Strategic Avatar System with Boss Battles** for level-based progression and avatar unlocking.
- **Advanced Scoring System** with points for correct answers (+2 for first, +1 for second) and penalties for wrong buzzes (-2 points).
- **Expanded Question Database** with 50 unique questions per theme (350 total questions).
- **Sound system** for audio feedback on game events.
- Allows players to answer questions for 0 points even if they didn't buzz in time.
- Strategic avatars persist across sessions and pack avatars display correctly.

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