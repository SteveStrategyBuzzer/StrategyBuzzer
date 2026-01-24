# StrategyBuzzer

### Overview
StrategyBuzzer is a real-time quiz buzzer game application offering an immersive and competitive experience. It features interactive quiz sessions, a strategic avatar system with boss battles, and comprehensive gameplay across Solo, Duo, League, and Master modes. The project aims to be a dynamic platform for competition, question answering, and score tracking in a game show-style environment, with ambitions for international expansion through multi-language support.

### User Preferences
Preferred communication style: Simple, everyday language.

**Pending Features (TO IMPLEMENT LATER):**
- **Quit penalty**: If a player quits a game in progress, they should lose 1 life. Currently DISABLED for testing purposes.

**Multi-language Requirement (CRITICAL):**
- ALL user-facing text MUST be wrapped with `{{ __('text') }}` in Blade templates
- ALL new text MUST be translated in all 10 language files: `resources/lang/{ar,de,el,en,es,fr,it,pt,ru,zh}.json`
- Never add French-only text - always add translations for all languages

### System Architecture

#### UI/UX Decisions
The frontend uses React 19 with Vite, employing a component-based architecture for competitiveness with energetic chronometers and realistic buzz buttons. It features a 3-column game question layout, visually persistent strategic avatar skills, and mobile responsiveness. A multi-language UI with automatic browser detection and manual selection across 10 languages is integrated. The boutique system uses a menu-style card navigation with 7 categories and orientation-aware responsive layouts. Duo and League modes must have identical visual layouts to Solo mode.

#### Technical Implementations
The backend is built with Laravel 10, following an MVC pattern and integrated with Inertia.js for an SPA-like experience. It utilizes an API-first, service-oriented design with an event-driven system for real-time game state broadcasting.

**Real-Time Multiplayer Synchronization (Socket.IO Migration):** Multiplayer modes (Duo, League, Master) are migrating from Firestore to Socket.IO for lower latency. The architecture uses:
- **Socket.IO Game Server** (apps/game-server/) for real-time events (buzz, answers, phase changes)
- **DuoSocketClient.js** for frontend-server communication
- **JWT tokens** for player authentication in rooms
- **Redis** for game state persistence with 2-hour TTL

**Current Migration Status (COMPLETED):**
- Duo mode: Socket.IO routes active (`/game/duo/*`), fully migrated
- League mode: Socket.IO routes active (`/game/league/*`), fully migrated
- Master mode: Socket.IO routes active (`/game/master/*`), supports up to 40 players
- Matchmaking queue: Uses Redis Cache polling instead of Firestore
- Firestore: Removed from gameplay controllers (DuoController, LeagueIndividualController)
- Firebase: Retained only for chat/voice features and MasterFirestoreService (host lobby management)

Solo mode remains isolated using AI opponents with traditional page redirects.

**Gameplay Flow (Socket.IO):** Multiplayer games follow a 3-page-per-round structure:
1. **Question page** (`duo_question.blade.php`): 60s timer, buzz system, 3-column layout
2. **Answer page** (`duo_answer.blade.php`): 4 choices, 10s timer
3. **Result page** (`duo_result.blade.php`): Skills, stats, "Le saviez-vous?", GO button

Solo mode dictates the strict sequence of game phases (intro, question, buzz, reveal, scoreboard) and question flow.

**Scoring System (Universal - All Modes):**

| Situation | Result |
|-----------|--------|
| 1st to buzz + correct answer | +2 pts |
| 1st to buzz + wrong answer | -2 pts |
| 1st to buzz + no choice (timeout) | -2 pts |
| 2nd+ to buzz + correct answer | +1 pt |
| 2nd+ to buzz + wrong answer | -2 pts |
| 2nd+ to buzz + no choice (timeout) | -2 pts |
| Didn't buzz + correct answer | 0 pt |
| Didn't buzz + wrong answer | 0 pt |
| Didn't buzz + no choice (timeout) | 0 pt |

**Summary:** Buzz = commitment (play for +2 or +1, but any error or timeout = -2 pts). No buzz = safe (0 pt max, never penalized).

**Question Management:** A question cache uses file-based caching. `QuestionService` manages AI-ready, theme-based question generation with adaptive difficulty, anti-duplication, and language-specific spelling verification using Google Gemini 2.0 Flash. A `SubthemeRotationSystem` ensures deterministic theme rotation. Multiplayer questions are generated in progressive blocks of 4 by `GenerateMultiplayerQuestionsJob`, using a `QuestionPlanBuilder` for dynamic needs calculation, anti-duplication, and retry logic.

**Multiplayer Lobby Synchronization:** `LobbyPresenceManager` handles player registration in Firebase sessions, with a "Synchronisé" indicator confirming connection before game start.

**Firestore Structure & Authentication:** All game modes use a unified `/gameSessions/{sessionId}` Firestore collection. Firebase Firestore security rules validate operations against `request.auth.uid` (Firebase anonymous UID).

**Game Modes:** Solo (90 opponents, 10 boss battles), Duo (division-based), League Individual (1v1 career), League Team (5v5 with 3 sub-modes), and Master (real-time hosting for 3-40 players with four distinct game structures).

**Avatar System:** User-specific avatars (12 across 3 tiers) offering 25+ unique skills.

**Skill Targeting System (Attack Skills):**
All attack skills must target an opponent according to these rules:
- **Solo/Duo (1v1):** Target is always the single opponent
- **Master (multi-player):** Dynamic targeting based on score ranking:
  1. If player is NOT leader: Target the player with highest score above the player
  2. If player IS leader: Target the player closest in score below (or equal to) the player
- This creates strategic depth where attacks always go toward the most threatening competitor

---

### Avatar Skills Documentation

#### Mathématicien (Rare Tier)
**🔢 Illumine si chiffre (`illuminate_numbers`)**
- **Trigger:** Page Réponse (automatique)
- **Effect:** Met en évidence la bonne réponse si elle contient un chiffre
- **Uses per match:** Illimité (passif)

#### Scientifique (Rare Tier)
**🧪 Acidifie 2 erreurs (`acidify_error`)**
- **Trigger:** Page Réponse (manuel)
- **Effect:** Acidifie visuellement 2 mauvaises réponses avant de choisir
- **Uses per match:** 1

#### Explorateur (Rare Tier)
**👁️ Voit choix adverse (`see_opponent_choice`)**
- **Trigger:** Page Réponse (manuel)
- **Effect:** Affiche le choix de l'adversaire/IA en temps réel
- **Uses per match:** 1

#### Challenger (Rare Tier)
**⏱️ Chrono Réduit (`reduce_time`)**
- **Trigger:** Page Résultat (manuel)
- **Effect:** Réduit le chrono de l'adversaire de 2 secondes
- **Duration:** 5/3/1 questions selon la manche (1-2/3/Ultime)
- **Uses per match:** 1

**🔀 Mélange Réponses (`shuffle_answers`)**
- **Trigger:** Page Résultat (manuel)
- **Effect:** Les 4 réponses changent de position toutes les 1.5 secondes
- **Duration:** 5/3/1 questions selon la manche
- **Uses per match:** 1

#### IA Junior (Rare Tier)
**💡 Suggestion IA (`ai_suggestion`)**
- **Trigger:** Page Réponse (manuel)
- **Effect:** L'IA suggère une réponse en l'illuminant
- **Accuracy:** 90% (10% de chance d'indiquer une mauvaise réponse)
- **Uses per match:** 1

**❌ Éliminer 2 erreurs (`eliminate_two`)**
- **Trigger:** Page Réponse (manuel)
- **Effect:** Élimine 2 mauvaises réponses sur les 4, laissant 2 choix
- **Uses per match:** 1

**🔄 Reprendre réponse (`retry_answer`)**
- **Trigger:** Après erreur sur Page Réponse
- **Effect:** Après une mauvaise réponse, le son d'erreur retentit et l'emoji apparaît permettant de rechoisir parmi les 3 autres réponses
- **Uses per match:** 1
- **Flow:**
  1. Joueur clique sur une mauvaise réponse
  2. Son d'erreur retentit
  3. Emoji 🔄 apparaît (comme 🪶 pour Historien)
  4. Les 3 autres réponses deviennent sélectionnables
  5. Joueur peut choisir une autre réponse

#### Historien (Épique Tier)
**🪶 Plume (`knowledge_without_time`)**
- **Trigger:** Quand le joueur n'a PAS buzzé (timeout sur page Question)
- **Effect:** Le joueur peut quand même répondre pour +1 point max
- **Consumption:** Consommé uniquement quand le joueur clique sur une réponse
- **Uses per match:** 1
- **Flow:**
  1. Joueur ne buzze pas → route `/solo/timeout`
  2. `timeout()` vérifie si avatar === 'Historien' && skill non utilisé
  3. Si disponible, affiche page réponse avec `featherAvailable=true`
  4. Page réponse montre 🪶 sur toutes les réponses
  5. Joueur clique → +1 point si correct, 0 si faux, skill consommé

**📜 Parchemin (`history_corrects`)**
- **Trigger:** Page Résultat après erreur (-2 points)
- **Effect:** Annule la pénalité de -2 ET accorde les points joués
- **Consumption:** Joueur clique sur 📜 à côté de la bonne réponse
- **Uses per match:** 1
- **Score calculation:**
  - 1er à buzzer + erreur: -2 annulé (+2) ET +2 pts = **final +2 pts**
  - 2ème+ à buzzer + erreur: -2 annulé (+2) ET +1 pt = **final +1 pt**

#### Comédienne (Épique Tier)
**🎭 Score masqué (`fake_score`)**
- **Trigger:** Début de match (automatique)
- **Effect:** Affiche un score moins élevé à l'adversaire jusqu'à la fin
- **Uses per match:** Passif (toute la partie)

**🔄 Trompe réponse (`invert_answers`)**
- **Trigger:** Page Réponse (manuel)
- **Effect:** Chez l'adversaire, une bonne réponse apparaît comme mauvaise
- **Uses per match:** 1
- **Type:** Attaque (suit les règles de ciblage)

---

**Progression:** Quest/Achievement System with 35 Standard quests.

**Authentication:** Firebase Authentication (with social providers) and Laravel Sanctum for API token management, integrated with a Player Code System.

**WebRTC Voice Chat System:** Real-time voice communication for Duo, League Individual, and League Team modes using peer-to-peer WebRTC with Firebase Firestore signaling.

#### Monorepo Architecture (Node.js Game Server)
The project uses a monorepo with `shared`, `game-engine`, and `game-server` packages. The Game Server (Node.js/TypeScript) uses Socket.IO for real-time communication and Express for REST API. Game phases (INTRO, BUZZ_WINDOW, ANSWER_SELECTION, REVEAL, ROUND_SCOREBOARD, TIEBREAKER_*, MATCH_END) are aligned with Solo mode.

**Scalable Architecture (Production):**
- **Redis:** Real-time state (buzzer, timers, room state, event log) with 2-hour TTL.
- **Firestore:** Source of truth for questions.
- **Laravel Cache:** Accelerator for Firestore reads with 30-minute TTL.
- **PostgreSQL Queue:** Used exclusively for AI question generation jobs.
- **Event-Sourcing:** All canonical events are logged to Redis for crash recovery.
- **Multi-Instance Synchronization:** Socket.IO Redis adapter for horizontal scaling, sharing room state across instances.
- **Security (Anti-Cheat):** Correct answer metadata is never sent before reveal.
- **Production Features:** Includes unit tests (Vitest), Zod schemas for input validation, metrics endpoint, and rate limiting.

**Laravel ↔ Game Server Integration:** `GameServerService.php` manages JWT token generation and room creation. JWT tokens include player and room data, secured by `GAME_SERVER_JWT_SECRET`.

**Frontend Socket.IO Client:** `DuoSocketClient.js` is a singleton for Socket.IO communication, handling room join, ready status, buzz, answer, skill activation, and WebRTC voice chat signaling. Duo mode pages (`duo_question.blade.php`, `duo_answer.blade.php`, `duo_result.blade.php`) now use Socket.IO for low-latency communication.

#### Game Phases (TypeScript)
```typescript
export type Phase =
  | "INTRO"
  | "BUZZ_WINDOW"
  | "QUESTION_DISPLAY"
  | "ANSWER_SELECTION"
  | "REVEAL"
  | "ROUND_SCOREBOARD"
  | "TIEBREAKER_CHOICE"
  | "TIEBREAKER_QUESTION"
  | "MATCH_END";
```

### External Dependencies

-   **Core Frameworks**: Laravel Framework, React, Inertia.js
-   **Firebase**: Firebase PHP SDK, Firebase JavaScript SDK, Firebase Firestore (real-time data), Firebase Authentication (user auth).
-   **Authentication**: Laravel Sanctum, Laravel Socialite.
-   **Development Tools**: Vite, Laravel Vite Plugin, Tightenco Ziggy.
-   **HTTP/API**: Guzzle HTTP, openai-php/laravel (AI question generation).
-   **Payment**: Stripe PHP SDK.
-   **Databases**: PostgreSQL (Replit Neon), Firebase Firestore.
-   **Real-time Communication**: Socket.IO, Redis.

**Firebase Configuration:** Firebase Firestore security rules (from `firebase-rules.txt`) must be deployed to the Firebase Console.