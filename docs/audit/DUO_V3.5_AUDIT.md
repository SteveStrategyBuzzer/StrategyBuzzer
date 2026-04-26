# Audit profond — Mode Duo V3.5

> **Statut** : audit pur, **aucun patch**.
> **Périmètre** : tout le pipeline Duo V3.5 (Laravel + Node Game Server + Front).
> **Règles dures rappelées**
> 1. Node = **seule autorité de gameplay** (phases, buzz, scoring, fin de manche/partie).
> 2. Firebase = **présence + signalisation WebRTC uniquement**. Jamais de phase, jamais de score, jamais de question.
> 3. Aucune divergence Solo / Duo / Master / League : un seul contrat de phases, un seul scoring, un seul cycle de ronde.
> 4. Citations `fichier:lignes` obligatoires. Taxonomie d'évidence stricte (utilisée uniformément ci-dessous) :
>    - **[OBSERVÉ CODE]** = directement lu dans le code à la ligne citée (et, le cas échéant, recoupé entre plusieurs fichiers — la confirmation par recoupement reste du code, pas de logs).
>    - **[RISQUE POTENTIEL]** = déduction qualifiée à partir d'une lecture du code, sans preuve d'occurrence en production.
>    - **[CONFIRMÉ PROD (LOGS)]** = corroboré par des logs / traces de production. **Aucune occurrence dans cet audit** : il s'agit d'un audit statique sur le code de la branche en cours, sans accès aux logs production. Toute escalade ultérieure d'un item OBSERVÉ CODE ou RISQUE POTENTIEL vers CONFIRMÉ PROD (LOGS) doit être faite explicitement avec un pointeur vers les logs.
> 5. Tout périmètre **non lu** dans cet audit est explicitement consigné en §1.6 « Périmètre des inconnus » — aucune conclusion principale ne dépend d'un fichier non lu.

---

## Section 1 — État actuel V3.5 (factuel)

### 1.1 Architecture

- **Source d'autorité** : Node Game Server (`apps/game-server/`), un process TS lancé par le workflow `Game Server` (port 3001), `apps/game-server/src/index.ts`. Toutes les transitions de phase, l'enregistrement des buzz, le scoring et la fin de match sont exécutés dans `GameOrchestrator.ts`. **[OBSERVÉ CODE]** par `apps/game-server/src/services/GameOrchestrator.ts:190-1355`.
- **Persistance temps-réel** : Redis (`Redis Server` workflow, port 6379) sert de bus d'événements (`logEventToRedis`) et de store d'état canonique (`room:{roomId}:state`, `match:{roomId}:result`). **[OBSERVÉ CODE]** `GameOrchestrator.ts:203, 1306` (via `setMatchResult`).
- **Persistance long-terme** : PostgreSQL via Laravel (modèle `DuoMatch`), alimenté par `applyFinalizationFromRedis` (`app/Http/Controllers/DuoController.php:382-515`).
- **Front** : Blade + JS vanilla (`public/js/GameplayRuntime.js`, `public/js/DuoSocketClient.js`). Pas de SPA Inertia sur le chemin gameplay Duo : chaque page (`/duo/question`, `/duo/answer`, `/duo/result`, `/duo/round-scoreboard`, `/duo/match-result`) est rendue server-side par Laravel.
- **Firebase** : limité à la présence lobby (`LobbyPresenceManager`) et à la signalisation WebRTC voice. **[OBSERVÉ CODE]** : aucun import de `firebase/firestore` dans `GameOrchestrator.ts` ni dans le scoring (`packages/game-engine/src/scoring.ts`, `reducer.ts`).

### 1.2 Flux nominal d'une partie Duo

1. Matchmaking Laravel (`DuoController@findMatchOrQueue`) crée un `DuoMatch`, génère `roomId` + `lobbyCode`, pré-fabrique un `jwt_token` court par joueur (`generateFreshGameplayToken`, `DuoController.php:1640`).
2. Les deux fronts se connectent au Game Server via `DuoSocketClient.connect(url, jwtToken)` (`public/js/DuoSocketClient.js:87-145`).
3. `join_room` côté Node (`apps/game-server/src/ws/handlers.ts:280-298`) → reducer enregistre le joueur, broadcast `state`.
4. Quand 2 joueurs sont prêts, `GAME_STARTED` puis `INTRO` (13 s) — voir `packages/shared/src/types.ts:345-357` (`DEFAULT_DUO_TIMERS`).
5. Boucle par question : `INTRO → QUESTION_ACTIVE (8 s) → [ANSWER_SELECTION (10 s) si ≥1 buzz] → ANSWER_COLLECTION (2 s) → RESULT (60 s) → SYNC (8 s) ou ROUND_SCOREBOARD`.
6. Fin de ronde : `endRound()` → `ROUND_SCOREBOARD` (5 s) → si `roundsWon ≥ roundsToWin (2)` ou `currentRound ≥ maxRounds (3)` → `endMatch()` → `setMatchResult()` Redis → `notifyMatchFinalized()` server-to-server vers Laravel.

### 1.3 Versions / paramètres clés (V3.5)

| Paramètre | Valeur | Source |
|---|---|---|
| `DEFAULT_DUO_TIMERS` (objet) | — | `packages/shared/src/types.ts:345-355` |
| `timers.intro` | 13 000 ms | `packages/shared/src/types.ts:346` |
| `timers.questionActive` | 8 000 ms | `packages/shared/src/types.ts:347` |
| `timers.answerSelection` | 10 000 ms | `packages/shared/src/types.ts:348` |
| `timers.answerCollection` | 2 000 ms | `packages/shared/src/types.ts:349` |
| `timers.result` | 60 000 ms | `packages/shared/src/types.ts:351` |
| `timers.sync` | 8 000 ms | `packages/shared/src/types.ts:352` |
| `timers.roundScoreboard` | 5 000 ms | `packages/shared/src/types.ts:354` |
| `questionsPerRound` | 9 | `packages/shared/src/types.ts:362` |
| `roundsToWin` | 2 | `packages/shared/src/types.ts:363` |
| `maxRounds` | 3 | `packages/shared/src/types.ts:364` |
| Scoring 1ʳᵉ buzz correct | +2 | `packages/game-engine/src/scoring.ts` + `GameOrchestrator.ts:544` |
| Scoring 2ᵉ⁺ buzz correct | +1 | `GameOrchestrator.ts:548` |
| Buzz incorrect / timeout | -2 | `GameOrchestrator.ts:386, 540` |
| No buzz | 0 (jamais de pénalité) | `GameOrchestrator.ts:536-538` |

### 1.4 Pages servies par Laravel

| Route | Méthode | Vue Blade | Contrôleur |
|---|---|---|---|
| `GET /duo/question` | `renderQuestionView` | `duo_question.blade.php` | `DuoController.php:1634-1701` |
| `GET /duo/answer` | `renderAnswerView` | `duo_answer.blade.php` | `DuoController.php:1703-1884` |
| `GET /duo/result` | `renderResultView` | `duo_result.blade.php` | `DuoController.php:1886-…` |
| `GET /duo/round-scoreboard` | `renderRoundScoreboardView` | `duo_round_scoreboard.blade.php` | `DuoController.php` |
| `GET /duo/match-result` | `renderMatchResultView` | `duo_match_result.blade.php` | `DuoController.php` |

### 1.5 Anti-doublons questions (3 niveaux)

- Niveau 1 — Service IA : `usedQuestionIds` (in-memory) côté générateur.
- Niveau 2 — Pipeline Firestore : `GameServerQuestionPipeline` filtre les IDs déjà servis.
- Niveau 3 — Orchestrateur : `room.usedQuestionIds: Set<string>` filtre lors de `transitionToWaiting` (`GameOrchestrator.ts:907-913`) et `prefetchQuestionBlock` (`GameOrchestrator.ts:993-998`).

### 1.6 Périmètre des inconnus (bounded)

Les zones suivantes **n'ont pas été lues exhaustivement** dans cet audit. Aucune conclusion principale (Sections 5, 7, 8, 9, 10) ne dépend d'elles : elles sont consignées ici uniquement pour transparence et pour cadrer un audit complémentaire éventuel.

| # | Zone | Pourquoi non lue ici | Impact possible si lecture ultérieure révèle un écart |
|---|------|----------------------|------------------------------------------------------|
| U1 | `apps/game-server/src/ws/handlers.ts` (handlers Socket.IO bruts) | Hors scope « Duo gameplay » direct ; les handlers délèguent à `GameOrchestrator` / `RoomManager`. | Pourrait introduire une validation laxiste avant le handler — à auditer si un risque RISQUE POTENTIEL escalade. |
| U2 | `public/js/GameplayRuntime.js` (consommateur de `window.GR_SAVE_STATE_EXTRA`) | Hors scope back-end. Cité en §10 D1/D4 comme producteur du risque. | Pourrait déjà valider la phase contre le serveur avant de l'utiliser ; dans ce cas D1 dégradé en BAS. |
| U3 | Comportement réel de la dérogation Bug #1 en mode socket lent (`docs/decisions/2026-04-26-duo-immediate-result-nav.md`) | Le décisionnaire produit existe ; pas de logs prod consultés. | Pourrait déjà avoir des telemetry / fuites observées en prod — à corréler. |
| U4 | League Team (5v5) — règles spécifiques de fin de manche par équipe | Hors scope Duo. La validation cross-mode (§14) ne couvre League Team que par check de symétrie d'API. | Audit séparé nécessaire si on étend les patches §11 à League Team. |

Tout item escaladé hors de cette table doit déclencher un addendum signé à cet audit, jamais une réécriture silencieuse.

---

## Section 2 — Qui parle à quoi (matrice émetteurs / consommateurs)

### 2.1 Événements Socket.IO Node → Front (Game Server émet)

| Événement | Émis par | Consommé par |
|---|---|---|
| `state` | `GameOrchestrator.broadcastState` (via reducer après `join_room`) — `handlers.ts:294` | `DuoSocketClient.onState` → `GameplayRuntime.applyState` |
| `event` (envelope) | `GameOrchestrator` à chaque PHASE_CHANGED, BUZZ_RECEIVED, ANSWER_SUBMITTED, ANSWER_REVEALED, MATCH_ENDED — `GameOrchestrator.ts:202, 274, 324, 442, 1301` | `DuoSocketClient._bindSocketEvent('event')` (`DuoSocketClient.js:163-184`) — switch sur `event.type` |
| `phase_changed` | `emitPhaseChanged` (canonique) — appelé après chaque transition | `DuoSocketClient.onPhaseChanged` → navigation page |
| `question_published` | `broadcastQuestion` — par joueur (`io.to('player:{id}')`) — `GameOrchestrator.ts:724` | `DuoSocketClient.onQuestionPublished` → `GameplayRuntime` repaint question |
| `buzz_winner` | `handleBuzz` après chaque buzz (non-bloquant) — `GameOrchestrator.ts:209-213` | `DuoSocketClient.onBuzzWinner` → UI lock du buzzer côté front |
| `answer_revealed` | `scoreAllBuzzers` par buzzer — `GameOrchestrator.ts:446` | `DuoSocketClient.onAnswerRevealed` → page result |
| `score_update` | `scoreAllBuzzers` par buzzer — `GameOrchestrator.ts:463` | `DuoSocketClient.onScoreUpdate` → repaint scores en direct |
| `player_stats_updated` | `scoreAllBuzzers` (buzzers + non-buzzers) — `GameOrchestrator.ts:495, 520` | `DuoSocketClient.onPlayerStatsUpdated` → `GameplayRuntime` repaint `[data-stat][data-player]` |
| `round_stats` | `endRound` — `GameOrchestrator.ts:1168` | `DuoSocketClient.onRoundStats` → page round-scoreboard |
| `match_stats` | `endMatch` — `GameOrchestrator.ts:1327` | `DuoSocketClient.onMatchStats` |
| `round_ended` | `endRound` (avant `phase_changed`) | `DuoSocketClient.onRoundEnded` |
| `match_ended` | `endMatch` — `GameOrchestrator.ts:1340` | `DuoSocketClient.onMatchEnded` |
| `waiting_block` | `transitionToWaiting` — `GameOrchestrator.ts:897` | `DuoSocketClient.onWaitingBlock` |
| `rate_limited`, `error` | `handlers.ts` (validation, auth) | `DuoSocketClient.onError` / `onRateLimited` |
| `time_sync_pong` | `handlers.ts` | `DuoSocketClient` ajuste `_clockOffsetMs` |

### 2.2 Événements Front → Node

| Événement | Émis par | Reçu par |
|---|---|---|
| `join_room` | `DuoSocketClient.joinRoom` (`DuoSocketClient.js:325-345`) | `handlers.ts:~170-303` |
| `buzz` | `DuoSocketClient.buzz(clientTimeMs)` (`DuoSocketClient.js:362-374`) | `handlers.ts:306-342` → `gameOrchestrator.handleBuzz` |
| `answer` | `DuoSocketClient.answer(value)` (`DuoSocketClient.js:377-389`) | `handlers.ts:344-…` → `gameOrchestrator.handleAnswer` |
| `skill` / `skill_used` | `DuoSocketClient.useSkill` | `handlers.ts` → `SkillEngine` |
| `player_ready` | `DuoSocketClient.playerReady` | `handlers.ts` |
| `question_page_ready` | `DuoSocketClient.questionPageReady` (`DuoSocketClient.js:423-433`) | `handlers.ts` → `transitionToSync` early-exit |
| `time_sync_ping` | `DuoSocketClient.syncTime` | `handlers.ts` |

### 2.3 Node → Laravel (server-to-server)

- `notifyMatchFinalized(roomId, mode)` : `GameOrchestrator.ts:1335` → POST interne sur la route Laravel `duo.finish` qui invoque `applyFinalizationFromRedis` (`DuoController.php:382-515`). **[OBSERVÉ CODE]** filet de sécurité, idempotent.

### 2.4 Laravel → Node (lecture seule)

- `renderAnswerView` lit `room:{roomId}:state` directement dans Redis (`DuoController.php:1736-1737`) en **fallback** si `current_question` est vide en session. **[OBSERVÉ CODE]** — lecture seule (Node reste autorité).
- `validatePhaseAccess` (`DuoController.php:2610-…`, appelée par `showQuestion/showAnswer/showResult/showRoundScoreboard` aux lignes 1262, 1300, 1347, 1420) interroge le Game Server via **HTTP** : `$this->gameServerService->getRoom($roomId)` → `GameServerService::getRoom` (`app/Services/GameServerService.php:115-…`). Ce n'est **pas** une lecture Redis directe : Laravel passe par l'API HTTP du Node Game Server. Conséquence : la latence et la disponibilité du Node Game Server impactent le rendu HTTP des pages Duo. Si le Game Server est lent ou indisponible, le guard ne peut pas valider la phase.

### 2.5 Front ↔ Laravel (HTTP)

- Toutes les pages Blade Duo sont rendues HTTP (`renderQuestionView`, `renderAnswerView`, …). Le front appelle `window.QUESTION_URL`, `window.ANSWER_URL`, `window.RESULT_URL`, `window.ROUND_SCOREBOARD_URL`, `window.MATCH_RESULT_URL` (injectés en `@json` dans `duo_question.blade.php:23-27`) lors des transitions de page.
- `renderPageGuard` (`DuoController.php:2610-2775`) intercepte chaque GET d'une page Duo et redirige selon l'état Redis canonique → c'est le **garde anti-désync** côté Laravel.

### 2.6 Firebase (présence + WebRTC uniquement)

- `LobbyPresenceManager` : Firestore `gameSessions/{sessionId}/presence`. Aucun champ de gameplay.
- WebRTC voice : `gameSessions/{sessionId}/voiceSignals` pour SDP/ICE. Aucun impact sur le state-machine.
- **[OBSERVÉ CODE]** Aucune écriture Firebase depuis `GameOrchestrator.ts` / `RoomManager.ts` / `scoring.ts` / `reducer.ts`.

---

## Section 3 — Timeline phases (frise temporelle annotée)

```
T+0          INTRO            13 s   [Laravel sert duo_intro? → en pratique GameplayRuntime gère affichage local]
T+13s        QUESTION_ACTIVE   8 s   ── broadcastQuestion → /duo/question
              │
              ├── Joueur A buzz à T+14.2s  →  buzz_winner emit (position 1) — phase INCHANGÉE (V3 non-bloquant)
              ├── Joueur B buzz à T+15.7s  →  buzz_winner emit (position 2) — phase INCHANGÉE
              └── (timer expire à T+21s)
T+21s        handleQuestionTimeout         GameOrchestrator.ts:747-763
              ├── buzzQueue.length === 0  →  revealAnswer() direct  →  RESULT (skip ANSWER_SELECTION/COLLECTION)
              └── buzzQueue.length ≥ 1    →  transitionToAnswerSelection()
T+21s        ANSWER_SELECTION 10 s   ── /duo/answer  (les buzzers cliquent leur réponse)
              │
              └── handleAnswerSelectionTimeout → ANSWER_COLLECTION
T+31s        ANSWER_COLLECTION 2 s    ── grace period (catch les réponses en vol)
              └── handleAnswerTimeout → revealAnswer()
T+33s        RESULT           60 s   ── /duo/result  (scoreAllBuzzers émet answer_revealed + score_update + player_stats_updated)
              │
              ├── question intermédiaire → transitionToSync()
              │       └── early-exit si tous les fronts ont émis question_page_ready
              └── dernière question (questionIndex == questionsPerRound-1) → endRound()
T+93s (max)  SYNC              8 s   ── /duo/result reste, attend question_page_ready
              └── early-exit dès que tous les peers prêts
T+(<=101s)   QUESTION_ACTIVE  …      ── question suivante
              │
              … répété jusqu'à fin de ronde …
              │
fin de ronde endRound()              ── round_stats emit + ROUND_SCOREBOARD phase
T+x          ROUND_SCOREBOARD  5 s   ── /duo/round-scoreboard
              ├── max(player.roundsWon) >= roundsToWin (2)  →  endMatch()
              ├── currentRound >= maxRounds (3)             →  endMatch()
              └── sinon                                      →  INTRO ronde suivante
fin match    endMatch()              ── match_stats emit + setMatchResult Redis + notifyMatchFinalized → /duo/match-result
```

**Remarques temporelles**

- La fenêtre QUESTION_ACTIVE **ne se raccourcit pas** quand un buzz arrive. Le commentaire `GameOrchestrator.ts:217-221` documente explicitement que l'optimisation « tous-buzzés → ANSWER_SELECTION immédiat » a été **différée**. Conséquence : un buzz précoce force quand même le joueur non-buzzer à attendre la fin du timer 8 s. **[OBSERVÉ CODE]**
- `transitionToAnswerSelection` peut aussi être appelée depuis le commentaire « (b) all-buzzed early-exit » (`GameOrchestrator.ts:769-770`), mais aucun appel actif n'est branché (cf. §10 pour cohérence).
- Un buzz côté V3.5 = `lockedAnswerPlayerId` set sur le **premier** buzzer uniquement (`packages/game-engine/src/reducer.ts:170-172`). Les buzzers 2/3/… ne sont **pas** « locked » (ce drapeau ne contrôle pas le scoring, juste l'UI).

---

## Section 4 — Table des responsabilités

| Domaine | Node Game Server | Laravel | Front (GameplayRuntime + DuoSocketClient) | Firebase |
|---|---|---|---|---|
| Phases / state-machine | **AUTORITÉ** (`GameOrchestrator`, `state-machine.ts`) | Lecture Redis pour render guard (`renderPageGuard`) | Reflet UI uniquement | Aucun |
| Buzz | **AUTORITÉ** (`registerBuzz`, `handleBuzz`) | — | Émet `buzz` event, reçoit `buzz_winner` | Aucun |
| Réponse joueur | **AUTORITÉ** (`handleAnswer`, validation des phases acceptables) | — | Émet `answer` event | Aucun |
| Scoring | **AUTORITÉ** (`scoreAllBuzzers`, `calculateScore`, `applyScoreEffects`) | — | Reflet (`score_update`, `player_stats_updated`) | Aucun |
| Questions (texte, choix) | **AUTORITÉ** in-memory + `room.state.questions` | Lecture Redis fallback (`renderAnswerView:1736`) | Reçoit `question_published` | Aucun |
| Bonne réponse (correctIndex) | **AUTORITÉ + secret** : sanitisée hors `question_published`, `routes.ts:93-100` | Jamais exposée avant reveal | Découverte via `answer_revealed` | Aucun |
| Compteur ronde | **AUTORITÉ** (`currentRound`, `roundsWon` dans `room.state.players[].roundsWon`) | Persisté en DB après `notifyMatchFinalized` | Reflet | Aucun |
| Fin de match (winnerId, isTie, finalScores) | **AUTORITÉ** (`endMatch` + `setMatchResult`) | Persistance via `applyFinalizationFromRedis` | Reflet via `match_ended` / `match_stats` | Aucun |
| Présence lobby | — | — | — | **AUTORITÉ** (`LobbyPresenceManager`) |
| Voice WebRTC signal | Relais socket (`voice_offer`/`voice_answer`/`voice_ice_candidate`, `DuoSocketClient.js:196-209`) | — | Émet/reçoit signal | Bus alternatif (`gameSessions/.../voiceSignals`) |
| Stats live (efficiency, avgResponseMs, …) | **AUTORITÉ** (`updatePlayerLiveStats`, broadcast `player_stats_updated`) | Persistance finale via stats du `MATCH_ENDED` event | Reflet — interdit de recalculer | Aucun |
| Anti-doublon questions | **AUTORITÉ** (3 niveaux : IA / Pipeline / `room.usedQuestionIds`) | — | — | Aucun |
| Skills (effets sur score, timer, choix) | **AUTORITÉ** (`SkillEngine`, `applyScoreEffects`, `expireEffects`, `hasActiveEffect`) | Inventaire utilisateur stocké en DB | Émet `skill` event | Aucun |

---

## Section 5 — Audit du système de points / buzzer (+2 / +1 / 0 / -2)

### 5.1 Implémentation observée

`GameOrchestrator.calculateScore` — `apps/game-server/src/services/GameOrchestrator.ts:529-549` :

```
if (!didBuzz || buzzOrder === 0)  return 0;
if (!isCorrect)                    return -2;
if (buzzOrder === 1)               return 2;
return 1;
```

Et dans `scoreAllBuzzers` :
- **Buzz mais pas de réponse (timeout)** : `pointsEarned = -2` (`GameOrchestrator.ts:386`), sauf passive `timeout_forgiveness` (`GameOrchestrator.ts:388-395`) qui le ramène à `0`.
- **Buzz + réponse** : `calculateScore(isCorrect, true, buzzOrder)`.

> ⚠️ **Précision importante sur le « no buzz »** : `scoreAllBuzzers` retourne immédiatement (`GameOrchestrator.ts:347-350`) si `buzzQueue.length === 0`. La boucle de broadcast non-buzzers (`GameOrchestrator.ts:502-526`) **ne s'exécute donc PAS** dans le cas où **personne** n'a buzzé. Elle ne s'exécute que dans le cas où **certains** ont buzzé et d'autres non (Duo : 1 sur 2). En pratique en Duo : si aucun joueur ne buzze, **aucun** `player_stats_updated` n'est émis pour cette question — l'efficience de chaque joueur reste celle de la question précédente. Voir §10 pour le risque associé.

### 5.2 Confirmation aux 4 cas attendus

| Cas | Attendu | Observé | Statut |
|---|---|---|---|
| 1ᵉʳ à buzzer + correct | +2 | `GameOrchestrator.ts:544-545` | **[OBSERVÉ CODE]** |
| 2ᵉ⁺ à buzzer + correct | +1 | `GameOrchestrator.ts:548` | **[OBSERVÉ CODE]** |
| Aucun buzz | 0 | non-itéré, broadcast no-op `player_stats_updated:511-518` | **[OBSERVÉ CODE]** |
| Buzz + faux / Buzz + timeout | −2 | `GameOrchestrator.ts:386, 540-541` | **[OBSERVÉ CODE]** |

### 5.3 Effets de skills sur le score

- `applyScoreEffects` (`GameOrchestrator.ts:399-404`) applique `score_shield` (annule pénalité), `double_points` (multiplie gain), etc. Le serveur est **arbitre unique**.
- Suivi du dernier delta par `lastScoreDeltas[roomId][playerId] = { questionIndex, delta }` (`GameOrchestrator.ts:408-414`) pour permettre `cancel_error` rétroactif sur la **même** question uniquement.

### 5.4 Risques

- **[OBSERVÉ CODE — BAS]** Le test d'égalité MCQ se fait avec `===` (`GameOrchestrator.ts:372`) sur `buzzerAnswer.answer === question.correctIndex`. Le payload `answer` socket n'est pas typé strictement entier par le front (`DuoSocketClient.answer(answerValue)` envoie tel quel, `DuoSocketClient.js:377-389`). `AnswerSchema` côté Node accepte explicitement `z.union([z.number(), z.string(), z.boolean()])` **sans coercition** (`apps/game-server/src/validation/schemas.ts:37-40`). Conséquence : si un front envoie la string `"2"` au lieu de l'entier `2` pour une question MCQ, la validation Zod passe mais le `===` à `:372` échoue → réponse comptée fausse silencieusement (−2). **Statut : observé en code, non corroboré par logs prod.**
- **[RISQUE POTENTIEL — BAS]** Le test TEXT (`GameOrchestrator.ts:376`) compare en lowercase mais sans `trim()`. Une réponse `" Paris"` sera comptée fausse. Acceptable pour V3.5 mais à documenter.
- **[OBSERVÉ CODE]** `calculateEfficiency = correct / totalBuzzes` (cf. `packages/game-engine/src/scoring.ts`). Donc un joueur qui ne buzze jamais a `efficiency = 0` (division par 0 protégée à l'amont). Le broadcast `player_stats_updated` non-buzzers (`GameOrchestrator.ts:502-526`) garantit que les fronts n'affichent jamais une efficience stale après une question où ils n'ont pas buzzé.

---

## Section 6 — Audit des questions (génération / distribution / sécurité)

### 6.1 Pipeline de génération

- **Source** : `GameServerQuestionPipeline` (`apps/game-server/src/services/`) → Firestore `questions` collection.
- **Préchauffage** : à la création de room ou au démarrage, `prefetchQuestionBlock` charge un bloc de 4 questions (`GameOrchestrator.ts:982-1006`).
- **Anti-doublons 3 niveaux** : voir §1.5.
- **Filtrage à l'arrivée** dans `transitionToWaiting` (`GameOrchestrator.ts:907-921`) et `prefetchQuestionBlock` (`GameOrchestrator.ts:993-998`).

### 6.2 Distribution aux clients (broadcastQuestion)

`broadcastQuestion` — `GameOrchestrator.ts:669-745` :
- Lit `question = room.state.questions[room.state.questionIndex]`.
- Sanitise les choix (`sanitizeChoices` filtre `null`/`undefined`/strings vides — important pour TRUE_FALSE qui arrivent en `["Vrai", null, "Faux", null]`).
- Émet `event.QUESTION_PUBLISHED` (room-wide, sans `correctIndex` — cf. §6.3).
- Émet `question_published` **par joueur** (room `player:{playerId}`) — pour permettre une fenêtre de temps personnalisée si `reduce_time` est actif (`GameOrchestrator.ts:712-742`).
- Marque `currentQuestionPublishedAtMs.set(roomId, Date.now())` — base de calcul du `relativeBuzzMs` pour `averageResponseMs`.

### 6.3 Sécurité — non-exposition de la bonne réponse

- `apps/game-server/src/http/routes.ts:93-99` : `sanitizedState` retire explicitement `correctIndex`/`correctBool`/`correctText` du payload retourné par `GET /rooms/{roomId}` (utilisé par `GameServerService::getRoom` côté Laravel). **[OBSERVÉ CODE]** — le canal HTTP de réconciliation ne fuit pas la bonne réponse.
- `broadcastQuestion` n'inclut **jamais** `correctIndex` (`GameOrchestrator.ts:687-700`).
- Côté Laravel fallback (`renderAnswerView:1744-1750`) : explicitement `'correct_answer' => null, 'correct_index' => null` malgré le fait que `room.state.questions[idx]` les contient. **[OBSERVÉ CODE]** — bonne pratique préservée.
- La bonne réponse n'apparaît qu'au moment de `answer_revealed` (`GameOrchestrator.ts:446-461`) qui inclut `correctIndex`/`correctBool`/`correctText`.

### 6.4 Risques

- **[OBSERVÉ CODE — MOYEN]** `renderQuestionView` **harcode** `currentQuestion = 1` (`DuoController.php:1666`). Le bon numéro de question vient ensuite via `question_published` (`questionIndex`) côté front. Si le front utilise le `currentQuestion` injecté par le PHP comme valeur initiale d'affichage (« Question 1/9 »), il y aura **toujours** affichage « Question 1 » au moment du load — même au tour 5. La valeur n'est mise à jour que si le code JS écoute `question_published` et repaint le compteur. Voir §10.
- **[RISQUE POTENTIEL — MOYEN]** `room.state.questions[room.state.questionIndex]` peut être `undefined` si le pipeline est en retard. `transitionToQuestionActive` gère ce cas en appelant `endRound` (`GameOrchestrator.ts:619`), mais `broadcastQuestion` ne le gère qu'avec un `console.error` puis `return` silencieux (`GameOrchestrator.ts:674-677`). Conséquence théorique : la phase passe en QUESTION_ACTIVE avec `phaseEndsAtMs` valide, mais aucun `question_published` n'est émis → les fronts restent sur l'ancienne question pendant 8 s. **Statut : risque déduit du code, pas d'occurrence prod observée dans cet audit.**
- **[OBSERVÉ CODE — BAS]** Le filtrage TRUE_FALSE recompose le `correct_index` côté Laravel (`DuoController.php:1764-1786`) en parallèle du sanitize Node. Si jamais le front reçoit la question via `question_published` (Node, déjà sanitizé) **et** la même via le HTTP page-load (Laravel, re-filtré), le mapping d'index pourrait diverger. Risque résiduel uniquement si les deux sources sont consommées en même temps.

---

## Section 7 — Audit de la progression de ronde (transitions)

### 7.1 Transitions confirmées

```
LOBBY → INTRO        (GAME_STARTED event)
INTRO → QUESTION_ACTIVE          (timer 13s, transitionToQuestionActive)
QUESTION_ACTIVE → ANSWER_SELECTION   (handleQuestionTimeout, si ≥1 buzz)
QUESTION_ACTIVE → RESULT             (handleQuestionTimeout, si 0 buzz, via revealAnswer)
ANSWER_SELECTION → ANSWER_COLLECTION (handleAnswerSelectionTimeout, timer 10s)
ANSWER_COLLECTION → RESULT           (handleAnswerTimeout, timer 2s, via revealAnswer)
RESULT → SYNC                        (transitionAfterResult, si pas dernière question)
RESULT → ROUND_SCOREBOARD            (transitionAfterResult → endRound, si dernière question)
SYNC → QUESTION_ACTIVE / WAITING     (transitionAfterSync, ou early-exit via question_page_ready)
WAITING → QUESTION_ACTIVE            (handleWaitingTimeout)
ROUND_SCOREBOARD → INTRO             (transitionAfterRoundScoreboard, ronde suivante)
ROUND_SCOREBOARD → endMatch          (transitionAfterRoundScoreboard, victoire ou maxRounds atteint)
```

Source : `packages/game-engine/src/state-machine.ts` (`PHASE_TRANSITIONS`, `getNextPhase`, `getPhaseTimeout`) + `GameOrchestrator.ts:605-1241`.

### 7.2 Compteurs

- `room.state.questionIndex` : incrémenté à **trois** endroits :
  - `transitionToSync:1014` — incrément effectué **avant** d'entrer dans SYNC, pour que `transitionToQuestionActive` (appelé depuis `handleSyncTimeout:1058` ou via early-exit `handleQuestionPageReady:1084`) puisse lire le bon index.
  - `handleWaitingTimeout:953` — incrément après la fenêtre WAITING.
  - `transitionToWaiting:870` — uniquement dans la branche fallback (`!blockInfo`, donc `questionIndex` non bloc-boundary), avant un `transitionToQuestionActive` direct.

  Conséquence importante : entre RESULT (question N) et QUESTION_ACTIVE (question N+1), `questionIndex` est **déjà** à N+1 dès l'entrée en SYNC. Tout consommateur Redis (ex. `renderAnswerView` fallback `DuoController.php:1736-1755`) qui lit `room:{roomId}:state` pendant SYNC verra l'index futur, pas l'index actuel — risque potentiel de mauvais fallback de question si les pages /duo/answer ou /duo/result sont rechargées pendant SYNC.
- `room.state.currentRound` : incrémenté dans `transitionAfterRoundScoreboard:1205` (avec reset de `questionIndex = 0` et `roundScore = 0`).
- `room.state.players[id].roundsWon` : incrémenté dans `endRound` (lecture seule via reducer event `ROUND_ENDED`).
- `room.state.players[id].score` (total match) : modifié uniquement par `applyEvent(ANSWER_REVEALED)` via reducer. **Jamais** d'écriture directe.
- `room.state.players[id].roundScore` : reset à 0 à `transitionAfterRoundScoreboard:1208-1210`.

### 7.3 Fin de match — règles

`transitionAfterRoundScoreboard:1190-1241` :
1. Si `maxRoundsWon >= roundsToWin (2)` → `endMatch()`.
2. Sinon si `currentRound >= maxRounds (3)` → `endMatch()`.
3. Sinon → manche suivante (INTRO).

`endMatch:1243-1355` :
- Détermine `winnerId` par `roundsWon`. **Tie-break** : `topPlayers.length > 1` → `winnerId = celui avec le plus haut `finalScores[playerId]`. `decidedBy: "rounds"` ou `"total_score"`.
- Persiste via `setMatchResult(roomId, {...})` Redis **avant** d'émettre `match_ended`.
- Filet de sécurité : `notifyMatchFinalized(roomId, mode)` uniquement pour `DUO` et `LEAGUE_TEAM` (`GameOrchestrator.ts:1334`). **Solo / Master / League Individual ne le déclenchent pas** — voir §10.

### 7.4 Risques

- **[OBSERVÉ CODE — HAUT]** En cas de tie au niveau `roundsWon`, le `winnerId` est défini par `finalScores` mais `isTie = true` reste positionné (`GameOrchestrator.ts:1270-1278`). Le `decidedBy: "total_score"` est correct, mais côté Laravel `applyFinalizationFromRedis` (`DuoController.php:443-453`) prend ce winnerId comme winner réel. Si les `finalScores` sont aussi égaux, `winnerId` reste défini sur le **dernier** `topPlayers` parcouru (ordre non déterministe — dépend de l'ordre d'insertion dans `room.state.players` qui est l'ordre de `join_room`). **Tie parfait → vainqueur arbitraire**. À documenter ou décider d'un règlement (égalité officielle).
- **[OBSERVÉ CODE — MOYEN]** Si un joueur se déconnecte avant `endRound`, son `roundScore` reste comptabilisé. La logique `endRound` (`apps/game-server/src/services/GameOrchestrator.ts:1088-1188`) ne contient pas de branche « forfait / déconnexion » : elle agrège indistinctement les `roundScore` de tous les joueurs encore présents dans `room.state.players`. Conséquence : un joueur qui se déconnecte à mi-manche peut quand même remporter la manche si son `roundScore` reste le plus élevé.

---

## Section 8 — Audit stats / scores

### 8.1 Système de stats (Task #38 NOYAU STATS LIVE)

- **Computation** : `updatePlayerLiveStats(prev, { didBuzz, buzzOrder, isCorrect, buzzTimeMs, newScore, newRoundScore })` — fonction pure, source `packages/game-engine/src/`. Recalcule `totalBuzzes`, `correctBuzzes`, `firstBuzzes`, `efficiency`, `averageResponseMs`, `score`, `roundScore`.
- **Stockage runtime** : `GameOrchestrator.playerStats: Map<roomId, Map<playerId, PlayerLiveStats>>` (privé, accédé via `getOrInitPlayerStats`/`writePlayerStats`).
- **Broadcast** : `io.to(roomId).emit("player_stats_updated", { playerId, playerName, stats })` après chaque buzzer scoré (`GameOrchestrator.ts:495-499`) **et** pour tous les non-buzzers (`GameOrchestrator.ts:520-524`).
- **Source unique de vérité** : interdiction explicite de recalcul côté front (`GameOrchestrator.ts:471-472` — commentaire).

### 8.2 Snapshots

- `endRound` : `roundPlayerStats = snapshot stats roundScope`. Émet `round_stats` (`GameOrchestrator.ts:1158-1168`).
- `endMatch` : `finalPlayerStats = this.snapshotAllPlayerStats(roomId)`. Émet `match_stats` (`GameOrchestrator.ts:1316-1327`) **et** persiste via `MATCH_ENDED` event (`GameOrchestrator.ts:1295-1296`) **et** `setMatchResult({ playerStats })` (`GameOrchestrator.ts:1313`).

### 8.3 Persistance Laravel

- `applyFinalizationFromRedis` (`DuoController.php:382-515`) lit `match:{roomId}:result` Redis et écrit en DB (modèle `DuoMatch` + tables stats). C'est la **seule** voie d'écriture des stats finales — pas de POST-finish depuis le front (sécurité).

### 8.4 Risques

- **[OBSERVÉ CODE — MOYEN]** `renderAnswerView:1714-1719` et `renderResultView:1897-1902` lisent `$matchGameState['player_scores_map']` directement depuis `$match->game_state` (la colonne JSON sur la table `duo_matches`). Cette colonne **n'est mise à jour que par `applyFinalizationFromRedis` à la fin du match**. Pendant la partie, ce `player_scores_map` est donc **stale** ou absent. Conséquence : les pages `/duo/answer` et `/duo/result` affichent **0–0** au load HTTP, puis sont rectifiées par `score_update` socket. **Flash visible si la connexion socket est lente.** Voir §9.
- **[OBSERVÉ CODE — MOYEN]** `currentQuestion` vient de `$matchGameState['current_question_number']` dans `renderAnswerView:1712` et `renderResultView:1895`. Même problème : ce champ n'est jamais maintenu en DB pendant la partie. Toujours `1` à l'affichage initial. Le repaint dépend des écouteurs JS.
- **[OBSERVÉ CODE]** Le calcul `relativeBuzzMs = buzzer.atMs - publishedAt` (`GameOrchestrator.ts:478-480`) est borné à `>= 0` et ramené à 0 si `publishedAt` est manquant. `averageResponseMs` reste donc dans `[0, 8000]` pour Duo (timer questionActive). C'est correct.
- **[RISQUE POTENTIEL — BAS]** Si une room est restaurée depuis Redis après crash, `playerStats` Map (en mémoire) est vide. La reprise des stats live est cassée (les valeurs absolues `score`/`roundScore` restent bonnes via reducer, mais `efficiency`/`avgResponseMs` repartent de zéro). Pas de test de cette branche dans cet audit.

---

## Section 9 — Risques de désynchronisation

### 9.1 Catégorisation

| # | Risque | Niveau | Source |
|---|---|---|---|
| D1 | Page Blade pré-injecte `phase: 'QUESTION_ACTIVE'` au chargement → mismatch si serveur est ailleurs | HAUT | `resources/views/duo_question.blade.php:34-37` (`window.GR_SAVE_STATE_EXTRA`) |
| D2 | Page Blade pré-injecte `currentQuestion = 1` toujours | MOYEN | `DuoController.php:1666`, `duo_question.blade.php:11` (`totalQuestions ?? 10`) |
| D3 | `player_scores_map` lu depuis `$match->game_state` (stale pendant la partie) | MOYEN | `DuoController.php:1714-1719, 1897-1902` |
| D4 | `restoreState()` peut publier `phase=QUESTION_ACTIVE` alors que serveur déjà en RESULT | HAUT | `public/js/DuoSocketClient.js:443-454` + `resources/views/duo_question.blade.php:35` |
| D5 | « Bug #1 derogation » dans `validatePhaseAccess` laisse le buzz-winner aller en `/duo/result` alors que le serveur est encore en `ANSWER_SELECTION` / `BUZZ_WINNER_ANSWERING` / `ANSWER_COLLECTION` | HAUT | `DuoController.php:2721-2737` (dérogation déjà gated par `$lockedAnswerPlayerId === $playerId` à `:2733` ; voir `docs/decisions/2026-04-26-duo-immediate-result-nav.md`). La page rend en mode « pending » et hydrate via socket — le risque résiduel est : si la socket est lente, la page `/duo/result` reste vide alors que le serveur n'a pas encore émis `answer_revealed`. |
| D6 | Question manquante (`room.state.questions[idx] === undefined`) en QUESTION_ACTIVE → silence (`broadcastQuestion:674-677`) | MOYEN | `GameOrchestrator.ts:674-677` |
| D7 | Tie parfait (égalité de `roundsWon` ET `finalScores`) → winnerId arbitraire selon ordre `Object.entries(players)` | HAUT | `GameOrchestrator.ts:1267-1278` |
| D8 | Front buzz-late : envoi `buzz` avec `clientTimeMs` dans le passé alors que serveur a déjà transitionné. `registerBuzz` filtre via `phase !== "QUESTION_ACTIVE"` (RoomManager.ts:212) → buzz silencieusement rejeté, aucun feedback | BAS | `RoomManager.ts:212-215` |
| D9 | Réponse soumise pendant QUESTION_ACTIVE (avant ANSWER_SELECTION) acceptée si le joueur a buzzé (`acceptablePhases = ["QUESTION_ACTIVE", "ANSWER_COLLECTION", "ANSWER_SELECTION"]`) — V3 non-bloquant | BAS (intentionnel) | `GameOrchestrator.ts:228-233` |
| D10 | Pas de reconciliation côté front si un événement socket est manqué (reconnexion → `state` reçu mais events `buzz_winner`/`answer_revealed` perdus) | MOYEN | `DuoSocketClient.js:163-184` (handler `event` ne replay que PLAYER_JOINED/LEFT/BUZZ ; pas d'idempotence sur ANSWER_REVEALED ou PHASE_CHANGED) |
| D11 | `notifyMatchFinalized` filtré sur `mode === "DUO" \|\| "LEAGUE_TEAM"` → Master / League Individual ne déclenchent pas le filet de sécurité s2s | MOYEN | `GameOrchestrator.ts:1334-1338` (selon contrat universel, devrait s'appliquer à tous les modes Node-managed) |
| D12 | `room.usedQuestionIds` est en mémoire process. Crash du Game Server → reset → potentiel double-question au redémarrage. | BAS | `GameOrchestrator.ts:907-913, 993-998` |

### 9.2 Mitigations existantes

- `validatePhaseAccess` (`DuoController.php:2610-…`) interroge le Game Server par HTTP (`GameServerService::getRoom`, `app/Services/GameServerService.php:115-…`) et redirige vers la page correcte → atténue D1, D2, D4 au reload, **mais** dépend de la disponibilité du Node Game Server (cf. §2.4). Si le Node est lent ou KO, le guard ne peut pas valider et le risque de désync revient.
- `transitionToSync` early-exit via `question_page_ready` (`GameOrchestrator.ts:1009-1086`) → réduit la fenêtre de désync inter-question.
- `applyFinalizationFromRedis` est idempotent → corrige les double-trigger entre `notifyMatchFinalized` (s2s) et un éventuel POST front.
- `getPhaseTimeout` (state-machine.ts) borne tous les timers → impossible d'avoir une phase « infinie ».

---

## Section 10 — Incohérences par sévérité

### 🔴 BLOCAGE (gameplay cassé ou résultat non déterministe)

- **B1 — Tie parfait, winner non déterministe** *(cf. D7)*. `endMatch` choisit `winnerId` parmi des joueurs à égalité parfaite via une boucle qui écrase à chaque itération (`GameOrchestrator.ts:1271-1277`). Le gagnant dépend de l'ordre `Object.entries(room.state.players)`, qui est l'ordre d'insertion dans la Map JS, donc l'ordre de `join_room`. **Aucune règle de tie-break documentée** au-delà de `total_score`. Il faudrait soit (a) un vrai tiebreaker (rapidité moyenne, premier buzzer correct, …), soit (b) déclarer une « égalité finale » officielle (`winnerId = null, isTie = true`).

### 🟠 HAUTE (désynchronisation visible, contournée par garde mais fragilisante)

- **H1 — Hardcode `phase: 'QUESTION_ACTIVE'` dans la Blade** *(cf. D1)*. `resources/views/duo_question.blade.php:34-37` injecte `window.GR_SAVE_STATE_EXTRA = { phase: 'QUESTION_ACTIVE' }` au chargement. Si `GameplayRuntime.restoreState()` publie cette valeur sans confirmer auprès du serveur, on a une fenêtre où le front pense être en QUESTION_ACTIVE alors que le serveur est en INTRO ou RESULT.
- **H2 — Bug #1 derogation `validatePhaseAccess`** *(cf. D5)*. **[OBSERVÉ CODE — HAUT]** La dérogation est codée et **déjà gated** par `$lockedAnswerPlayerId === $playerId` (`DuoController.php:2731-2737`, gating clause à `:2733`). Le buzz-winner peut donc atterrir sur `/duo/result` pendant que le serveur est encore en `ANSWER_SELECTION` / `BUZZ_WINNER_ANSWERING` / `ANSWER_COLLECTION`. Le risque résiduel — la page rend en mode « pending » et hydrate via socket — est documenté en `docs/decisions/2026-04-26-duo-immediate-result-nav.md`. Durcissement proposé : ajouter une vérification supplémentaire `phase IN ('RESULT', 'REVEAL', 'SYNC')` une fois `applyFinalizationFromRedis` câblé pour exposer un `result_ready` Redis (cf. P5).
- **H3 — `restoreState()` sans validation serveur** *(cf. D4)*. `DuoSocketClient.restoreState` (`public/js/DuoSocketClient.js:443-454`) lit `sessionStorage` et restitue tel quel. Aucune validation que la phase restituée correspond à l'état serveur courant.
- **H4 — `notifyMatchFinalized` filtré DUO/LEAGUE_TEAM uniquement** *(cf. D11)*. Crée une asymétrie entre modes : Master et League Individual reposent uniquement sur le POST front pour finaliser, sans filet de sécurité s2s. **Viole la règle « aucune divergence entre modes »**.

### 🟡 MOYENNE (UX dégradée mais corrigée par socket)

- **M1 — `currentQuestion = 1` hardcodé en HTTP** *(cf. D2)*. Le compteur affiche initialement « 1/9 » même au tour 5. Corrigé par `question_published` socket. Flash visible.
- **M2 — `player_scores_map` stale pendant la partie** *(cf. D3)*. Pages `/duo/answer` et `/duo/result` affichent 0–0 au load HTTP. Corrigé par `score_update` socket. Flash visible.
- **M3 — Pas de reconciliation post-reconnexion** *(cf. D10)*. Si un client perd la connexion entre BUZZ_RECEIVED et ANSWER_REVEALED, il ne reçoit jamais le score de cette question (seul `state` est replay au reconnect, mais les events historiques ne sont pas re-broadcast par défaut).
- **M4 — `broadcastQuestion` silencieux si question absente** *(cf. D6)*. Aucun event d'erreur n'est émis aux fronts. Devrait au minimum émettre un `error` ou forcer `endRound`.
- **M5 — Compteur `currentQuestion_number` jamais maintenu en DB pendant la partie**. Conséquence directe de M1. Si on persistait le `questionIndex` à chaque transition, le HTTP guard pourrait afficher la bonne valeur dès le load.

### 🟢 BASSE (cosmétique, edge case)

- **L1 — Comparaison MCQ `===` strict** *(cf. §5.4 RISQUE BAS)*. Risque silencieux si le payload `answer` n'est pas typé entier.
- **L2 — Comparaison TEXT sans `trim()`** *(cf. §5.4)*. Edge case rare en quiz (peu de TEXT en V3.5).
- **L3 — `usedQuestionIds` in-memory** *(cf. D12)*. Crash → reset. Peu d'impact en pratique vu la taille du pool de questions.
- **L4 — Buzz silencieusement rejeté hors QUESTION_ACTIVE** *(cf. D8)*. UX : le bouton buzz reste cliquable même après expiration du timer.
- **L5 — Sanitisation TRUE_FALSE dupliquée Node + Laravel** *(cf. §6.4)*. Risque de mapping divergent si les deux sources sont consommées en parallèle.

---

## Section 11 — Plan de micro-patchs (ordonné, sans code)

> Ordre choisi pour minimiser les régressions et résoudre les BLOCAGE / HAUTE en priorité, en respectant la règle « Node = autorité unique ».

### P1 — [BLOCAGE] Définir et implémenter la règle officielle de tie-break finale
- **Décision produit requise** : (a) deuxième tiebreaker (ex. premier buzzer correct cumulé), (b) accepter un statut d'égalité finale officielle.
- **Patch Node uniquement**, dans `endMatch` (`GameOrchestrator.ts:1267-1278`).
- **Risque** : faible (cas rare).

### P2 — [HAUTE] Supprimer le hardcode `phase: 'QUESTION_ACTIVE'` de la Blade
- Remplacer `window.GR_SAVE_STATE_EXTRA.phase = 'QUESTION_ACTIVE'` par un `null`/absence, et faire que `GameplayRuntime` n'utilise jamais cette valeur comme source d'autorité.
- **Patch front uniquement** : `resources/views/duo_question.blade.php:34-37` (et lecteurs dans `public/js/GameplayRuntime.js`).
- **Risque** : modéré — vérifier que `restoreState()` n'a pas de chemin qui dépend de cette valeur initiale.

### P3 — [HAUTE] Encadrer la dérogation Bug #1 dans `renderPageGuard`
- Ne laisser passer la dérogation que si `Redis room state.phase IN ('RESULT', 'SYNC')`.
- **Patch Laravel uniquement** : `DuoController.php` autour de la ligne 2731.
- **Risque** : faible — c'est restreindre une dérogation existante, pas en créer une.

### P4 — [HAUTE] `restoreState()` doit demander un `state` fresh au serveur avant de publier
- Au lieu de réémettre la valeur sessionStorage, déclencher un `socket.emit('request_state')` (ou s'appuyer sur le `state` que le Game Server envoie automatiquement à `join_room`) et n'appliquer le snapshot local **qu'après** confirmation.
- **Patch front + handler Node** : `public/js/DuoSocketClient.js:443-454`, `apps/game-server/src/ws/handlers.ts`.
- **Risque** : modéré — touche au flux de reconnexion.

### P5 — [HAUTE] Étendre `notifyMatchFinalized` à tous les modes Node-managed
- Étendre le filtre `mode === "DUO" || "LEAGUE_TEAM"` à `MASTER` et `LEAGUE_INDIVIDUAL`.
- **Patch Node uniquement** : `GameOrchestrator.ts:1334-1338`.
- **Risque** : modéré — vérifier que les routes Laravel cibles existent et sont idempotentes pour Master / League Individual.

### P6 — [MOYENNE] Persister `questionIndex` et `roundScore` en DB à chaque transition de phase
- Soit via une route s2s appelée par le Game Server au moment de `transitionToQuestionActive`, soit via lecture Redis depuis `renderQuestionView`/`renderAnswerView`/`renderResultView` (équivalent à ce qui est déjà fait pour `current_question` en fallback).
- **Patch Laravel** : `DuoController.php:1666, 1712, 1714-1719, 1895, 1897-1902`.
- **Risque** : faible (lecture seule depuis Redis, pas de nouvelle écriture).

### P7 — [MOYENNE] `broadcastQuestion` doit traiter le cas question manquante explicitement
- Émettre un `error` aux fronts ou forcer `endRound` au lieu de `console.error + return`.
- **Patch Node uniquement** : `GameOrchestrator.ts:674-677`.
- **Risque** : faible.

### P8 — [MOYENNE] Replay des événements critiques au reconnect
- À la reconnexion (`join_room` sur une room déjà active), envoyer non seulement `state` mais aussi les événements depuis `lastEventId` connu par le client.
- **Patch Node + front** : `handlers.ts` (join_room) + `DuoSocketClient.js`.
- **Risque** : élevé — touche à la mécanique de reconnexion. À planifier seul, hors lot.

### P9 — [BASSE] Coercer le payload `answer` socket en type cible
- Côté Node, dans `AnswerSchema` (`apps/game-server/src/validation/schemas.ts`), forcer `z.union([z.number().int(), z.boolean(), z.string()])` selon le type de la question courante.
- **Patch Node uniquement**.
- **Risque** : faible.

### P10 — [BASSE] Trim + lower sur comparaison TEXT
- Ajouter `.trim()` côté Node dans `scoreAllBuzzers` (`GameOrchestrator.ts:376`) et `revealAnswer`.
- **Patch Node uniquement**.
- **Risque** : nul.

---

## Section 12 — Fichiers à toucher (chirurgical)

Schéma : **fichier · raison · type d'intervention attendue** (ligne par patch P1–P10 du §11).

| Patch | Fichier · Lignes ciblées | Raison (lien §10) | Type d'intervention attendue |
|---|---|---|---|
| P1 | `apps/game-server/src/services/GameOrchestrator.ts:1267-1278` (`endMatch`) | B1 — tie parfait → vainqueur arbitraire | Logique : ajouter un 2ᵉ tiebreaker déterministe **ou** statut `isTie=true` officiel. |
| P2 | `resources/views/duo_question.blade.php:34-37` ; `public/js/GameplayRuntime.js` (lecteurs de `GR_SAVE_STATE_EXTRA.phase`) | H1 / D1 — hardcode `phase: 'QUESTION_ACTIVE'` | Suppression : retirer la valeur hardcodée + s'assurer qu'aucun lecteur ne la consomme comme autorité. |
| P3 | `app/Http/Controllers/DuoController.php:2731-2737` (dérogation dans `validatePhaseAccess`) | H2 / D5 — buzz-winner sort tôt vers `/duo/result` | Durcissement : conditionner la dérogation à un check additionnel (Redis `phase IN ('RESULT','REVEAL','SYNC')` ou présence d'un flag `result_ready`). |
| P4 | `public/js/DuoSocketClient.js:443-454` ; `apps/game-server/src/ws/handlers.ts` (handler `state` ou `join_room`) | H3 / D4 — `restoreState()` republie sessionStorage sans confirmation | Refactor flux : demander un `state` frais avant publication ; n'appliquer le snapshot local qu'après confirmation serveur. |
| P5 | `apps/game-server/src/services/GameOrchestrator.ts:1334-1338` (filtre mode dans `notifyMatchFinalized`) | H4 / D11 — divergence Master & League Individual | Extension de scope : élargir le filtre `mode === "DUO" \|\| "LEAGUE_TEAM"` à `MASTER` et `LEAGUE_INDIVIDUAL` (+ vérifier idempotence des routes Laravel cibles). |
| P6 | `app/Http/Controllers/DuoController.php:1666` (renderQuestionView) ; `:1712, 1714-1719` (renderAnswerView) ; `:1895, 1897-1902` (renderResultView). Pattern Redis déjà câblé pour `questionData` à `:1736-1755`. | M1 / M2 / M5 / D2 / D3 — `currentQuestion=1` hardcodé et `player_scores_map` stale au load | Lecture seule : étendre le fallback Redis existant à `currentQuestion` et `player_scores_map` (aucune nouvelle écriture en DB). |
| P7 | `apps/game-server/src/services/GameOrchestrator.ts:674-677` (`broadcastQuestion` guard) | M / D6 — `console.error + return` silencieux si question manquante | Gestion d'erreur : émettre un événement explicite (`error` socket) ou forcer `endRound` au lieu de retourner en silence. |
| P8 | `apps/game-server/src/ws/handlers.ts` (handler `join_room` ~170-303) ; `public/js/DuoSocketClient.js` (handler `state` ~147-156) ; `apps/game-server/src/services/RoomManager.ts:272-275` (`getEvents(fromEventId)` déjà disponible) | Reconnect — risque de manquer des événements entre déconnexion et reconnexion | Ajout : sur reconnect, replayer les événements depuis `lastEventId` connu côté client (pas de nouveau code dans `RoomManager`, juste consommation). |
| P9 | `apps/game-server/src/validation/schemas.ts:37-40` (`AnswerSchema`) | L1 — `===` strict vs `z.union` non coercif | Renforcement validation : ajouter une coercition explicite (`z.coerce.number()` pour MCQ, `z.coerce.boolean()` pour TRUE_FALSE) ou normaliser dans le handler avant comparaison. |
| P10 | `apps/game-server/src/services/GameOrchestrator.ts:376` (TEXT compare) | L2 — pas de `trim()` sur la comparaison TEXT | Ajout one-liner : `.trim()` côté serveur avant `toLowerCase()`. |

**Aucun patch ne doit toucher** :
- `packages/game-engine/src/scoring.ts` (contrat scoring stable, déjà universel).
- `packages/game-engine/src/reducer.ts` (sauf P1 si la décision tie-break passe par un nouveau type d'event).
- `packages/shared/src/types.ts` constantes timers (V3.5 figées).
- Tout fichier Firebase / `LobbyPresenceManager` / WebRTC (hors-périmètre gameplay).

---

## Section 13 — Risques de régression

| Patch | Risque de régression | Modes potentiellement impactés | Mitigation |
|---|---|---|---|
| P1 | Modifier la logique winner peut affecter rapports stats / classements. | Duo, League Individual (même tie-break attendu). | Ajouter test unitaire sur `endMatch` couvrant 4 cas : winner clair, tie roundsWon → différence finalScores, tie roundsWon ET finalScores, tous joueurs à 0. |
| P2 | Si `GameplayRuntime` lisait `GR_SAVE_STATE_EXTRA.phase` comme valeur initiale, son retrait peut causer un blank UI au load. | Duo, Master, League. | Lire `GameplayRuntime.applyState` et confirmer qu'il déclenche un repaint dès le premier `state` socket. |
| P3 | Restreindre la dérogation peut rallonger le temps avant que le buzz-winner voie `/duo/result`. | Duo. | Mesurer la fenêtre QUESTION_ACTIVE → RESULT via logs Game Server. |
| P4 | Le flux reconnect change : un mauvais ordre peut produire un freeze de l'UI. | Duo, Master, League. | E2E Playwright : disconnect/reconnect en pleine partie. |
| P5 | Ajouter Master / League Individual à `notifyMatchFinalized` peut créer une double-finalisation si le front POST aussi. | Master, League Individual. | Vérifier idempotence des `applyFinalizationFromRedis` équivalents pour ces modes. |
| P6 | Lecture Redis supplémentaire dans le render path → +1 round-trip Redis par page. | Duo. | Cache court (1 s) sur `room:{roomId}:state` côté PHP, ou simplement accepter le coût. |
| P7 | Forcer `endRound` quand question manquante peut clore une partie prématurément. | Tous. | Préférer un `error` socket et un retry avec backoff. |
| P8 | Replay events au reconnect peut causer des doubles incrémentations si le front ne dédoublonne pas par `event.id`. | Tous. | Imposer dédoublonnage par `lastEventId` côté front. |
| P9 | Coercion de type `answer` peut casser des skills exotiques attendant `string`. | Tous. | Tests unitaires couvrant MCQ, TRUE_FALSE, TEXT. |
| P10 | Aucun. | — | — |

---

## Section 14 — Validation par mode

> Règle : tout patch validé en Duo doit être ré-évalué pour ne pas créer de divergence en Solo, Master, League Individual, League Team.

### 14.1 Solo
- **Contrat** : « même contrat, exécution éventuellement locale » (cf. `docs/STRATEGIC_AVATAR_SKILL_CONTRACT.md`).
- **Impact des patchs** :
  - P1 (tie-break) : Solo n'a qu'un joueur humain, donc tie-break vs IA. Vérifier que la logique `topPlayers.length > 1` se comporte correctement quand un opponent est `bot`/`ai`.
  - P5 (notifyMatchFinalized) : Solo passe-t-il par Node ? Si oui, l'étendre. Si non (exécution locale front uniquement), ne pas activer.
  - P10 (TEXT trim) : applicable à toutes les sources de scoring locales — vérifier `SoloScoring.php` (hors périmètre cet audit).

### 14.2 Duo (référence)
- **Contrat** : audité ici.
- **Validation** : suite Playwright E2E déjà existante (`tests/e2e/duo-*.spec.ts` selon la mention « bot matches Duo / Master / League Individual »).

### 14.3 Master
- **Contrat** : modes 3-40 joueurs, 4 structures. Hôte humain + joueurs.
- **Impact des patchs** :
  - P1 : tie-break sur 40 joueurs nécessite encore plus une règle officielle.
  - P5 : critique — `notifyMatchFinalized` doit être étendu (sinon Master n'a pas de filet s2s, contrairement à Duo).
  - P6 : Master a-t-il un `current_question_number` en DB ? Vérifier `MasterController` équivalent.

### 14.4 League Individual
- **Contrat** : 1v1 carrière. Règles scoring identiques à Duo.
- **Impact des patchs** :
  - P1, P5 : appliquer pareillement.
  - P9 : vérifier que `LeagueIndividualController` partage `AnswerSchema`.

### 14.5 League Team
- **Contrat** : 5v5, 3 sous-modes.
- **Impact des patchs** :
  - P1 : tie-break **par équipe** — la logique actuelle d'`endMatch` raisonne par joueur, pas par équipe. Audit additionnel requis hors périmètre.
  - P5 : déjà inclus dans le filtre actuel (`"DUO" \|\| "LEAGUE_TEAM"`), donc P5 ne change rien pour LEAGUE_TEAM.

### 14.6 Checklist universelle de validation après patch

1. ✅ Smoke E2E : matchmaking → INTRO → 1ʳᵉ question → buzz → réponse → reveal → score affiché → fin de ronde → fin de match → page result.
2. ✅ Smoke disconnect/reconnect en pleine partie.
3. ✅ Smoke timeout sans buzz : la phase doit sauter ANSWER_SELECTION et atterrir directement en RESULT.
4. ✅ Smoke tie : forcer un cas où les deux joueurs ont les mêmes `roundsWon` et les mêmes `finalScores`.
5. ✅ Smoke skill `score_shield` actif : vérifier que `applyScoreEffects` n'est pas court-circuité.
6. ✅ Smoke skill `timeout_forgiveness` : buzz sans réponse → 0 pts au lieu de −2.
7. ✅ Vérifier que `match:{roomId}:result` Redis est écrit **avant** `match_ended` socket (ordre critique pour `applyFinalizationFromRedis`).
8. ✅ Vérifier que les cas scoring **avec au moins un buzz** (+2/+1/−2) émettent tous un `player_stats_updated` (pour buzzers et non-buzzers s'il y a mixité). Cas « zéro buzz dans le room » : aucun `player_stats_updated` n'est émis (cf. §5 — early-return `GameOrchestrator.ts:347-350`). Si on veut couvrir ce cas, c'est un patch séparé.
9. ✅ Vérifier qu'aucun fichier Firebase n'a été touché.
10. ✅ Vérifier qu'aucune divergence n'est introduite : tous les modes appellent les mêmes fonctions de scoring (`calculateScore`, `applyScoreEffects`).

---


**Fin du document.** Audit pur, aucun patch appliqué.
