# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application that combines a Laravel backend with a React frontend. It offers interactive quiz sessions across various themes, allowing players to buzz in, answer questions, track scores, and compete in a game show-style environment. The application uses Firebase for authentication and real-time features, serving both educational and entertainment purposes. It features a strategic avatar system with boss battles, a comprehensive gameplay cycle, and a detailed results page with progression data, aiming to provide an immersive and competitive quiz experience.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes (October 11, 2025)
- **Persistent Skill Glow System**: Implemented complete visual feedback system for strategic avatar skills. Skills now continuously shine with golden pulsating glow (via @keyframes skillShine animation) until used, then stop glowing permanently for remainder of game. Persistence managed via session 'used_skills' array and new /solo/use-skill API endpoint. Skills marked with "used" class on page load based on session data, ensuring consistent state across all questions throughout entire match.
- **Strategic Avatar Skills Implementation**: Implemented functional skill system for strategic avatars. "Calcul Rapide" skill (Mathématicien avatar) now reveals the correct answer by illuminating it with a glowing effect in game_answer.blade.php. Skills display as circular icons on the right side of the answer page, can be used once per game (persistent), and provide visual feedback. System uses data-index selector to correctly handle both multiple-choice and true/false question types.
- **Dual Opponent Display Format**: Implemented two distinct visual formats for opponents in game_question.blade.php. Boss opponents (levels 10, 20, 30...100) display with circular avatar image + score + name + level. Regular opponents (levels 1-9, 11-19, etc.) display with simplified textual layout showing only name + level + score without any avatar circle, creating clear visual distinction between boss battles and regular matches.
- **Life System with 1-Hour Cooldown**: Implemented complete life management system for authenticated users. Players lose 1 life per defeat (protected against page refresh/double submission with session flag 'match_result_processed'). When lives reach 0, automatic 1-hour cooldown activates (tracked via next_life_regen timestamp). Defeat page displays remaining lives, cooldown timer, and disables retry button when no lives available. start() method blocks game start for users at 0 lives. Guest players unaffected by life system. Managed by LifeService with deductLife() and hasLivesAvailable() methods.
- **Buzz Timing Competition System**: Fixed buzz timing logic to create real competition with AI opponent. Early buzz (0-25% of chrono time) gives player -40% chance for AI to be faster, medium buzz (25-75%) uses normal AI speed chance, late buzz (75-100%) gives AI +30% bonus to be faster. Makes timing strategic and rewards quick reflexes.
- **Boss Display Fix**: Corrected getBossForLevel() to only show boss opponents at exact boss levels (10, 20, 30...100) using config/opponents.php. Regular levels (1-9, 11-19, etc.) now properly show regular opponent names instead of boss avatars.
- **Victory/Defeat Flow Fix**: Replaced obsolete stat page redirect with proper victory/defeat routing. When match ends (best of 3), players now see victory.blade.php (if won 2 rounds) or defeat.blade.php (if lost), with level progression and challenge prompts.
- **Level Progression System with 90 Unique Opponents**: Implemented comprehensive opponent naming system with 89 unique names across 90 regular levels (1-9, 11-19, 21-29, etc., excluding boss levels). Organized by geographical regions: Europe (Léa, Hugo, Enzo, etc.), Italy/Mediterranean (Luca, Tazio, Giada), Anglo-Saxon (Jack, Liam, Alice), Spanish/Latino (Diego, Rosa, Carmen), Japanese (Yumi, Akira, Sora), East Asian (Lin, Mei, Hana), African/Middle Eastern (Omar, Aziz, Leila), Americas/Polynesia (Maya, Tane, Lilo, Venetia, Kanoa). Boss opponents at levels 10, 20, 30...100 (Le Stratège, Le Prodige, Le Maître...Cerveau Ultime).
- **Automatic Level Unlock on Victory**: When player wins 2 out of 3 rounds, choix_niveau increments automatically and saves to user profile_settings for authenticated users. Victory page displays prompt "Voulez-vous challenger [Next Opponent Name at Level X]?" with Yes/No buttons. Defeat page shows "Vous avez perdu contre [Opponent Name]" with retry/menu options.
- **Profile Page Level Display**: Fixed solo_level and league_level to display minimum level 1 (instead of 0) and properly synchronize with session choix_niveau.
- **Progressive AI Difficulty System**: Fully implemented three-layer AI behavior scaling from level 1-100: (1) Buzz chance increases 65%→100%, (2) Speed chance increases 20%→90%, (3) Success rate increases 60%→100%. Chronometer time decreases from 8→4 seconds. Difficulty curve managed by QuestionService with getOpponentBuzzChance(), getOpponentSpeedChance(), getOpponentSuccessRate().
- **Best of 3 Match System Implementation**: Complete overhaul of gameplay mechanics. Changed from 5-round system to "best of 3 matches" where one match = all selected questions (e.g., 30 questions = 1 match). Players must win 2 matches out of 3 to win the game. Added round_result.blade.php transition page showing match score (X-0, 1-1, etc.). Upon winning, player advances to next level with stronger opponents, progressing toward boss battles at level 10, 20, etc., and the ultimate "Cerveau Ultime" boss at level 100.
- **Comprehensive Round Result Page**: Consolidated all statistics into round_result.blade.php (end of round page). Displays: theme, level, round scores (manches gagnées X-X), round points (player-opponent), round efficiency %, global statistics across all rounds (Réussi X/total, Échec X/total, Sans réponse X/total), global efficiency %, and remaining lives. This is the single comprehensive stats page shown at end of each round.
- **Simplified Question Result Page**: Streamlined game_result.blade.php (after each question) to show only essential info: correct/incorrect answer, score, lives, and progression. All detailed statistics are now shown only at end of round.
- **Global Statistics System**: Implemented comprehensive statistics tracking across all matches. Added global_stats session variable to accumulate question results (correct/incorrect/unanswered) across all rounds.
- **Player Name Display Fix**: Fixed empty player name bubble in game_question.blade.php. Changed CSS class from 'opponent-info' to 'player-name' to properly display player name and level.
- **Player Avatar Display Fix**: Fixed player avatar not displaying when starting a game. Added synchronization logic in start(), resume(), and game() methods to restore player avatar from profile_settings. Normalized legacy 'default' values to use 'images/avatars/standard/standard1.png' for both authenticated users and guests. Player avatars now display correctly on all pages.
- **Game Question Page Complete Redesign**: Major restructure with 3-column layout: (1) LEFT: Player avatar + score + random player name (Jade, Hugo, etc.) with level, PLUS opponent avatar + score + name with level displayed below player, (2) CENTER: Chronometer, (3) RIGHT: Strategic avatar + skill icons displayed as circular buttons (icon only, no text) vertically stacked. Buzzer replaced with realistic image (buzzer.png with "STRATEGY BUZZ BUZZER" text). All responsive breakpoints updated for new layout.
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