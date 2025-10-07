# StrategyBuzzer

## Overview

StrategyBuzzer is a real-time quiz buzzer game application that combines Laravel backend with React frontend. The game allows players to participate in interactive quiz sessions with different themed atmospheres (Fun, Party, Intello, Punchy). Players can buzz in to answer questions, track scores, and compete in a game show-style environment. The application integrates with Firebase for authentication and real-time features, making it suitable for both educational and entertainment purposes.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **React 19** with modern hooks and functional components
- **Vite** as the build tool and development server for fast hot module replacement
- **Component-based architecture** with reusable UI elements (BoutonBuzz, CarteQuestion, AvatarDisplay, ScoreDisplay)
- **Dynamic theming system** supporting multiple visual atmospheres through CSS switching
- **Real-time UI updates** for game state changes and player interactions

### Backend Architecture
- **Laravel 10** as the primary web framework following MVC pattern
- **Inertia.js** for seamless SPA-like experience between Laravel and React
- **API-first design** with both web and API routes for different client types
- **Service-oriented architecture** with dedicated services for game logic, scoring, and player management
- **Event-driven system** for real-time game state broadcasting

### Database and Storage
- **PostgreSQL (Replit Neon)** as the primary relational database for user data, game progress, and transactions
- **Firebase Firestore** for real-time data synchronization during gameplay
- **Firebase Authentication** for user management and social login capabilities
- **Laravel's Eloquent ORM** for database abstraction and query building
- **File-based configuration** for game structure and question sets

### Authentication and Authorization
- **Firebase Authentication** with social providers (Google, etc.)
- **Laravel Sanctum** for API token management and session handling
- **Multi-provider authentication** supporting both Firebase and Laravel's native auth
- **Role-based access control** for different user types (players, moderators)

### Real-time Features
- **Firebase real-time database** for live game state synchronization
- **WebSocket-like functionality** through Firebase listeners
- **Event broadcasting** for buzz notifications, score updates, and game transitions
- **Live player status tracking** and session management

## External Dependencies

### Core Framework Dependencies
- **Laravel Framework** (^10.10) - Primary PHP web framework
- **React** (^19.1.1) - Frontend JavaScript library
- **Inertia.js** (^0.6.8) - Full-stack framework bridge

### Firebase Integration
- **Firebase PHP SDK** (^7.18) - Server-side Firebase integration
- **Firebase JavaScript SDK** (v10.12.2) - Client-side Firebase services
- **Firebase Authentication** - User authentication and social login
- **Firebase Firestore** - Real-time NoSQL database
- **Firebase Storage** - File and media storage

### Authentication Services
- **Laravel Sanctum** (^3.2) - API authentication
- **Laravel Socialite** (^5.21) - Social media authentication providers

### Development and Build Tools
- **Vite** (^6.3.5) - Frontend build tool and development server
- **Laravel Vite Plugin** (^1.3.0) - Laravel-Vite integration
- **Tightenco Ziggy** (^2.0) - Laravel route generation for JavaScript

### HTTP and API Libraries
- **Guzzle HTTP** (^7.2) - HTTP client for external API calls
- **Symfony Intl** (^7.3) - Internationalization support

### Payment and E-commerce
- **Stripe PHP SDK** (latest) - Payment processing and checkout sessions
- **Stripe Webhooks** - Secure payment confirmation and event handling

### Testing and Quality Assurance
- **PHPUnit** (^10.1) - PHP testing framework
- **Laravel Breeze** - Authentication scaffolding
- **FakerPHP** (^1.9.1) - Test data generation
- **ESLint** - JavaScript code linting
- **React Testing utilities** - Frontend component testing

## Recent Changes

### October 07, 2025
- **Database Migration to PostgreSQL**: Migrated from SQLite to Replit's PostgreSQL (Neon) for improved stability
  - Configured automatic connection using environment variables (PGHOST, PGPORT, PGUSER, PGPASSWORD, PGDATABASE)
  - Updated `config/database.php` to prioritize PostgreSQL connection
  - All migrations executed successfully on PostgreSQL
  - Test account migrated: `test@strategybuzzer.com` / `password` (10,000 coins)
- **Fixed PostgreSQL Connection in Web Context**: Resolved environment variable access issue
  - **Problem**: `php artisan serve` creates child process that loses Replit's PG* environment variables
  - **Solution**: Changed workflow from `php artisan serve` to `php -S 0.0.0.0:5000 -t public` for direct environment inheritance
  - **Result**: PostgreSQL variables (PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD) now accessible via `getenv()` in web requests
  - Removed unnecessary `bootstrap/database-config.php` workaround
  - Web server now correctly connects to Replit PostgreSQL instead of falling back to SQLite
  - Configuration in `config/database.php` uses `getenv()` to read Replit environment variables
- **Enhanced Authentication Options**: Added multiple login methods to improve user accessibility
  - **Email/Password authentication**: Complete login and registration system with secure password hashing
  - **Apple authentication**: Placeholder for future Apple Sign-In integration
  - **Phone authentication**: Placeholder for future SMS-based login
  - UI improvements: Modern button design with color-coded providers, responsive layout, divider between email and social options
  - Routes: `/auth/email`, `/auth/email/login`, `/auth/email/register`, `/auth/apple`, `/auth/phone`
  - New users receive 1000 welcome coins upon registration
  - Test account available: `test@strategybuzzer.com` / `password` (10,000 coins)
- **Global Menu Navigation**: Added fixed "🏠 Menu" button in top-right corner
  - Only appears after user authentication (not on login/register pages)
  - Hidden on menu page itself to avoid redundancy
  - Provides quick access to main menu from any authenticated page
- **Strategic Avatar System with Boss Battles**: Complete boss battle system implementation
  - **No boss for levels 1-9** (training levels), first boss appears at level 10
  - Boss progression: Épiques (⭐) for levels 10-40, Légendaires (👑) for levels 50-100
  - Level-based bosses: Comédien (Lv10) → Magicien (Lv20) → Challenger (Lv30) → Historien (Lv40) → IA Junior (Lv50) → Stratège (Lv60) → Sprinteur (Lv70) → Visionnaire (Lv80) → Stratège Maître (Lv90) → Visionnaire Suprême (Lv100)
  - Each boss has authentic skills from strategic avatar categories (Rare, Épique, Légendaire)
  - Player cannot select same strategic avatar as current boss (conflict detection and reset with alert)
  - Resume page displays: Player avatar (left) with strategic skills, Boss avatar (right) with boss skills (or training message for levels 1-9)
  - Strategic avatars unlock upon defeating corresponding boss
  - Real skills system: Mathématicien, Scientifique, Explorateur, Défenseur (Rare 🎯), Comédien, Magicien, Challenger, Historien (Épique ⭐), IA Junior, Stratège, Sprinteur, Visionnaire (Légendaire 👑)
  - Game progression: 5 manches per level, must succeed 3/5 to advance

### October 02, 2025
- **Implemented Stripe Payment Integration**: Complete real-money coin purchasing system
  - Created `config/coins.php` with 5 coin packs (Starter $0.99 to Ultimate $29.99)
  - Built secure payment flow: checkout → Stripe → webhook → coin crediting
  - Database schema: `payments` table for transaction tracking, `coin_ledger` table for audit trail
  - Services: `StripeService` for checkout sessions, `CoinLedgerService` for coin management
  - Controllers: `CoinsController` for checkout flow, `StripeWebhookController` for payment verification
  - Security: Webhook signature validation, idempotent payment processing, CSRF exemption
  - UI: Added "💎 Pièces d'or" tab to boutique with visual coin pack cards
  - Enhanced `BoutiqueController` with DB transactions for atomic purchases
  - Routes: `/coins/checkout`, `/stripe/webhook`, `/coins/success`, `/coins/cancel`
  - Models: `Payment` and `CoinLedger` with relationships and status tracking
  - Note: Requires `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` environment variables

### September 28, 2025
- **Fixed main navigation flow**: Eliminated React intermediate page, direct access to Laravel application
- **Corrected StrategyBuzzer logo display**: Login page now shows proper logo (strategybuzzer-logo.png)
- **Fixed connection button**: Home page button now correctly redirects to login page (/login) 
- **Port configuration**: Changed Laravel server from port 8080 to port 5000 (required for Replit webviews)
- **URL configuration**: Updated APP_URL and route helpers for proper navigation
- **OAuth flow ready**: Direct path from home page → login → Google/Facebook authentication