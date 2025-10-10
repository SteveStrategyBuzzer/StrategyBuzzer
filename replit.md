# StrategyBuzzer

## Overview
StrategyBuzzer is a real-time quiz buzzer game application combining a Laravel backend with a React frontend. It offers interactive quiz sessions with various themes, allowing players to buzz in, answer questions, track scores, and compete in a game show-style environment. The application uses Firebase for authentication and real-time features, serving both educational and entertainment purposes. It features a strategic avatar system with boss battles, a comprehensive gameplay cycle, and a complete result page with progression data. The project aims to provide an immersive and competitive quiz experience.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes (October 10, 2025)
- **Game Preparation Countdown Screen**: Added 3-second concentration countdown between "Démarrer la Partie" button and first question. New screen displays "On se Concentre" with animated gradient text and large countdown numbers (3→2→1) that start big and shrink progressively. Each number displays for 1 second with smooth scale animation before auto-redirecting to game. Slows gameplay pace and gives players time to focus. Fully responsive across all device orientations.
- **Pack Avatar Display Fix (Resume Screen)**: Fixed bug where avatars selected from packs (Animal, Cartoon, etc.) weren't displaying in the pre-game resume screen. Modified AvatarController to store full image path in session instead of just basename (e.g., "images/avatars/animal/elephant.png" instead of "elephant"). resume.blade.php now correctly displays pack avatars instead of showing empty circle. Users need to re-select their avatar once to update session with new path format.
- **Strategic Avatar Badge Redesign (Boutique)**: Redesigned strategic avatar name badges in boutique page with thinner, centered white badges. Applied white background with dark blue text/border (#003DA5), reduced padding (6px×12px), and flex-based overflow handling. Long names like "Mathématicien" now truncate with ellipsis (max-width: 70%) instead of overflowing card headers. Uses inline-flex with min-width: 0 for proper text truncation.
- **Answer Randomization for Multiple Choice**: Modified QuestionService to shuffle answer positions ONLY for multiple choice questions. True/false questions keep fixed positions (Vrai always left, Faux always right). Normalized all 119 true/false questions to consistent format with mix of true and false statements. Eliminates predictable patterns while maintaining UX consistency for binary choices.
- **Strategic Avatar Name Badge Expansion**: Enlarged profile page avatar name badge padding from 2px 8px to 4px 16px with white-space: nowrap. Accommodates longer strategic avatar names like "Mathématicien" without text overflow or wrapping.
- **Portraits Pack Auto-Unlock**: Modified AvatarController to automatically unlock "portraits" pack for all users as a free default pack. Users can now click on Portraits pack to open avatar selection modal instead of being redirected to boutique. Ensures baseline avatar access for everyone.
- **Locked Pack Visual Blur**: Added "locked" CSS class to preview-grid images for locked packs. Locked packs now display blurred preview thumbnails matching the existing blur effect on pack titles, providing consistent visual feedback.
- **Event Handler Cleanup**: Removed duplicate global document click listener that conflicted with pointerup tap detection. Prevents potential double-trigger issues when clicking on avatar packs.
- **Pack Spacing Optimization**: Reduced card inner min-height from 110px to 40px to eliminate excessive white space between pack title and preview images. Vignettes now start immediately below pack badge for optimal space utilization.
- **Animal Images Optimized**: Compressed all 10 animal avatar images from ~4MB to ~200KB each (95% reduction) using ImageMagick. Resized to 400x400px with quality 90 compression. Eliminates pixelation while maintaining visual clarity.
- **Avatar Pack Preview Grid Expansion**: Increased 2x2 preview grid height from 92px to 140px on desktop, 100px on mobile portrait, 80px on mobile landscape. Gap increased from 6px to 8px. Pack previews now use significantly more available space for better visibility.
- **Carousel Infinite Loop Removed**: Eliminated visual "gaps" in pack carousel by removing infinite loop clones. Changed from clone-based infinite carousel to normal looping carousel that wraps from end to start with smooth transition. No more duplicate/missing packs visible during autoplay.
- **Avatar Pack Click Fix**: Fixed regression where unlocked avatar packs couldn't be clicked to open selection modal. Enhanced carousel pointer handling to detect taps (≤50px movement) and manually trigger openPack() while preserving drag functionality. Now users can both swipe carousel and click packs.
- **Animal Avatar Pack Expansion**: Added 10 new animal avatar images to public/images/avatars/animal/ directory: tigre, elephant, hyene, kodiak, ours-noir, phacochère, porc-epic, rhinoceros, lion, zebre. All images properly integrated and displayed in Animal pack grid preview.
- **Integrated Menu Buttons**: Replaced floating "Return to Menu" button with integrated header buttons positioned at top-right across all main pages (profile, avatars, boutique, ligue, quetes, solo). Buttons use consistent white background with dark blue text styling, includes hover effects (lift + shadow). No more floating buttons blocking content.
- **Mobile Login Page Redesign**: Restructured the connection page (start.blade.php) for mobile devices with vertical layout: title at top, animated brain in center, connection button at bottom. Includes floating brain animation and responsive layouts for both portrait and landscape orientations. Title features colorful gradients: "STRATEGY" in cyan→purple→pink, "BUZZER" in yellow→orange→red.
- **Menu Mobile Enhancements**: 
  - Button widths set to fixed 220px for uniform sizing with compact padding - eliminates excessive whitespace around text
  - Fixed brain bounce physics with synchronized CSS/JS sizing: 54px mobile portrait, 44px mobile landscape, 64px tablets, 90px desktop - ensures accurate collision detection and tighter wall bouncing
  - Added touchstart event support for mobile brain multiplication (works with touch and click)
  - Removed unused SIZE constant to prevent future conflicts
  - Mobile overflow fix: reduced spacing (gap 8px, padding 12px) and font sizes to fit all buttons without bottom overflow
  - Enhanced touch handling with passive:false and touchend preventDefault for reliable mobile brain multiplication
  - Brain multiplication restricted: only the first (original) brain is now interactive/clickable, preventing UI clutter from excessive brain spawning
  - Fixed mobile layout: removed scroll with flex layout (align-items: flex-start) to keep title at top with space at bottom, no vertical scrolling

## Previous Changes (October 08, 2025)
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