# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application designed for an immersive and competitive experience, suitable for both educational and entertainment purposes. It features a Laravel backend and a React frontend. The project offers interactive quiz sessions across various themes and game modes, including a strategic avatar system with boss battles, comprehensive gameplay cycles (Solo, Duo, League, Master), and detailed progression tracking. Its core purpose is to provide a dynamic platform where players can compete, answer questions, track scores, and participate in a game show-style environment.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes

### November 23, 2025 - Multi-Language Support & Spelling Verification System

**Objective**: Enable StrategyBuzzer to generate questions in multiple languages with strict spelling verification
- **Multi-Language Architecture**: System prepared for 10 languages (French, English, Spanish, Italian, Greek, German, Portuguese, Russian, Arabic, Chinese)
- **Adaptive Spelling Rules**: Language-specific orthography verification in AI prompts
- **Default Language**: French (fr) - ready for expansion

**Implementation**:
- **QuestionService.php**: Added `$language = 'fr'` parameter to `generateQuestion()` method
- **AIQuestionGeneratorService.php**: Added `$language = 'fr'` parameter and transmits to Node.js API
- **question-api.js**: 
  - Created `LANGUAGES` mapping (fr→Français, en→English, es→Español, it→Italiano, el→Ελληνικά, de→Deutsch, pt→Português, ru→Русский, ar→العربية, zh→中文)
  - Dynamic prompt adaptation: all "en français" references replaced with `en ${languageName}` variables
  - Added comprehensive "RÈGLES D'ORTHOGRAPHE STRICTE" section in both multiple-choice and true/false prompts
- **Spelling Verification Rules** (applied to all languages):
  1. Verify EVERY word in language-specific dictionary before generation
  2. Language-specific spelling examples (e.g., French: panthère ✓ vs phantère ✗)
  3. Double-check animal names, place names, proper nouns, technical terms
  4. Mandatory final review before sending question to player
  5. If spelling doubt → use synonym with confirmed spelling
  6. Spelling correctness = same priority as factual accuracy

**Design Rationale**:
- Spelling errors (like "phantère") break immersion and educational value
- Multi-language readiness enables future international expansion without major refactoring
- Language parameter defaults to 'fr' for backward compatibility
- Architecture supports adding new languages by simply extending LANGUAGES mapping

**Future-Ready**: Adding a new language requires:
  1. Add entry to `LANGUAGES` object in question-api.js
  2. (Optional) Create language-specific theme translations  
  3. **Note**: Currently, True/False answers remain in French ("Vrai"/"Faux") for frontend/backend compatibility. Full localization would require updating UI buttons and answer validation logic

**Files Modified**: `app/Services/QuestionService.php`, `app/Services/AIQuestionGeneratorService.php`, `question-api.js`, `replit.md`

### November 23, 2025 - Adaptive Question Difficulty System

**Objective**: Adapt question difficulty to opponent characteristics for fair and balanced gameplay
- **Étudiants (Levels 1-9, 11-19, etc.)**: Questions adapted to student's age (8, 10, 12, 14, 16, 18, 20, 22, 24, 26 years)
- **Boss (Levels 10, 20, 30, etc.)**: University-level/expert questions for challenging battles

**Implementation**:
- **QuestionService.php**: Added `$opponentAge` (8-26 years) and `$isBoss` (boolean) parameters to `generateQuestion()`
- **AIQuestionGeneratorService.php**: Transmits opponent info to Node.js API for prompt adaptation
- **question-api.js**: Adaptive prompt logic:
  - `isBoss=true` → "niveau universitaire / expert"
  - `opponentAge` present → "niveau X ans" (ex: "niveau 8 ans", "niveau 24 ans")
  - Fallback → classic game level difficulty
- **SoloController.php**: All question generation calls updated (4 locations: `game()`, `bonusQuestion()`, `generateBlock()`, `generateBatch()`)
- **config/opponents.php**: All 90 students have `age` field; all 10 Boss have `is_boss=true` flag

**Design Rationale**:
- Young students (8 years) get simple vocabulary and basic concepts accessible to children
- Older students (24-26 years) get adult-level questions with more sophisticated content  
- Boss battles use expert-level questions with precise details, exact dates, and technical terminology
- This system ensures fair difficulty progression aligned with opponent characteristics, preventing situations where children face overly complex questions or adults receive trivial ones

**Affected Modes**: Solo mode (Duo/League use Firestore, not AI generation system)

**Files Modified**: `app/Services/QuestionService.php`, `app/Services/AIQuestionGeneratorService.php`, `question-api.js`, `app/Http/Controllers/SoloController.php`, `replit.md`

### November 22, 2025 - Resume Screen UI Refinements

**Mobile Responsive Optimization**
- **Mobile Portrait Fix**: Added comprehensive @media (max-width: 400px) query to prevent horizontal overflow
- **2-Column Layout Preserved**: All grids maintain 1fr 1fr layout on small screens
- **Size Reductions**: Avatars 180px→100px, gaps 20px→8-10px, radar 350px→160px
- **Typography Scaled**: Font sizes reduced proportionally (avatar-name 1.8rem→1rem, labels 0.9rem→0.7rem)
- **Rationale**: Ensures clean 2-column display without scrolling on mobile portrait (320-400px width devices)
- **Files Modified**: `resources/views/resume.blade.php`

**UI Enhancement: ID Player Management & Adversary Display**
- **ID Player Length Limit**: Added `maxlength="10"` constraint to player ID input (pseudonym)
- **ID Label Clickable**: Made "ID joueur" label clickable in profile page - clicking focuses on input field for quick editing
- **Removed Adversary Emoji**: Removed 🤖 emoji from adversary names in resume screen (both boss and student opponents)
- **Rationale**: Better ID management (prevents overly long names), cleaner adversary display without decorative emojis
- **Files Modified**: `resources/views/profile.blade.php`, `resources/views/resume.blade.php`

**UI Enhancement: Player Progression Display & Strategic Avatar Clarity**
- **"Questions Par Manche" Label**: Changed info-card label from "Questions" to "Questions Par Manche" for clarity
- **Player Card Redesign**: 
  - Removed emoji 👤 from "Vous" title
  - Title now shows player ID/name directly (ex: "Antoine" instead of "👤 Vous")
  - Displays unlocked progression level (ex: "Niv: 78") instead of current game level
  - Correctly shows progression even when replaying lower levels (player level 78 playing level 6 → displays "Niv: 78")
- **Strategic Avatar Section Redesign**:
  - Removed avatar portrait image
  - Simplified title: "⚔️ Magicienne" in gold (emoji + name only)
  - Skills displayed vertically with full descriptions
  - Format: Icon + skill name (bold gold) + description below
  - Example Magicienne: "✨ Cancel une mauvaise réponse / Elle vous la transforme en sans réponse"
- **Backend Enhancement**: Added `niveau_progression` parameter in both SoloController::start() and ::resume() methods
- **Files Modified**: `resources/views/resume.blade.php`, `app/Http/Controllers/SoloController.php`

**UI Enhancement: Resume Screen Simplification & Clarity**
- **Removed Redundant Card**: Deleted "Adversaire Igor (Niveau 8)" info card from top section - now shows only Theme and Questions
- **Opponent Title Correction**: Changed opponent card title from boss/teacher name to actual opponent name (ex: "Igor" instead of "Le Stratège")
- **Player Name Simplification**: Simplified player avatar caption from "Joueur    Niv: 8" to just player ID/name (ex: "Antoine")
- **Strategic Avatar Renaming**: Changed "⚔️ Avatar Stratégique" to "⚔️ A.S [Avatar Name]" format (ex: "⚔️ A.S Magicienne")
- **Rationale**: Cleaner layout, reduced redundancy, more intuitive labeling for competitive gameplay
- **Files Modified**: `resources/views/resume.blade.php`

**UI Redesign: Symmetric Resume Screen Layout**
- **Objective**: Complete redesign of `resume.blade.php` with symmetric player vs opponent presentation
- **Dynamic Title**: "Boss Challenge" for boss battles (levels 10/20/30/etc.), "Descriptif de la Partie" for regular matches
- **Symmetric Avatar Display**: Forced 2-column grid layout (1fr 1fr) maintained even on mobile portrait
- **Skills Section Below**: Strategic avatar card (left) vs Boss radar diagram Chart.js (right)
- **Boss Radar Integration**: Chart.js radar showing boss competencies across 9 themes using `config('opponents.boss_opponents')` data
- **Enhanced Mobile Responsiveness**: Added @media (max-width: 400px) breakpoint to prevent overflow on 320-360px screens
- **Technical Fixes**: Removed DEBUG echo block, corrected boss radar data access, unified avatar sizing
- **Files Modified**: `resources/views/resume.blade.php`

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