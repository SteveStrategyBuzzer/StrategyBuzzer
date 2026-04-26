# Decision — Duo: client-initiated navigation to /duo/result after answer submit

**Date:** 2026-04-26
**Status:** Accepted
**Scope:** Duo mode (only). Master, League Individual, League Team are **out of scope** for this derogation.
**Related:** Bug #1 (cancelled task #62) — "the player lingers on the Answer page after submitting until phase_changed RESULT arrives, which can take several seconds when the opponent is still answering."

---

## Context

The architecture rule is **"Node = sole phase authority"**: navigation between Question → Answer → Result → Round Scoreboard is supposed to be driven *exclusively* by `phase_changed` events emitted by `apps/game-server/src/services/GameOrchestrator.ts`. A previous regression (legacy `_onAnswerRoundEnded` / `_onAnswerMatchEnded`) was removed precisely because client-initiated navs raced with `phase_changed` and the `isRedirecting` guard silently dropped one of the two arrivals (see commit history of `resources/views/duo_answer.blade.php`).

However, in Duo the buzz-winner who has already submitted their answer must wait for:
1. The opponent to also submit (or for `ANSWER_COLLECTION` grace to expire), AND
2. Node to transition `REVEAL → RESULT`.

The total wait can exceed 5 seconds. The user perceives the Answer page as "frozen with a waiting overlay", then is suddenly teleported to Result.

## Decision

Allow a **bounded, idempotent client-initiated navigation** from Answer → Result *immediately after the player submits their answer*, paired with a fully passive Result page that hydrates from socket events.

### Bounds

1. **Triggered only after `DuoSocketClient.answer()` has been emitted.** Never on page load, reconnect, or any other path.
2. **Only the buzz-winner can land on /duo/result early.** Enforced server-side in `DuoController::validatePhaseAccess` for the `result` case: `lockedAnswerPlayerId === user.id` is required when `currentPhase ∈ {ANSWER_SELECTION, BUZZ_WINNER_ANSWERING, ANSWER_COLLECTION}`.
3. **Single-shot.** A shared `isRedirecting` flag in `duo_answer.blade.php` ensures the early-nav branch and the `phase === 'RESULT'` branch in `_onAnswerPhaseChanged` are mutually exclusive — only the first to fire navigates.
4. **Idempotent on arrival.** When the buzz-winner reaches /duo/result early, the controller renders in **pending mode** (`$resultPending = true`). The view shows an "En attente du résultat…" overlay and hides ✓/✗/points/header/answer until `answer_revealed` arrives via socket. A new handler `_onResultAnswerRevealed` (filtered by `playerId`) hydrates these fields and removes the overlay. `_onResultPhaseChanged` already treats RESULT as a no-op when the page is already loaded — no second navigation.

### What we explicitly do NOT do

- We do **not** weaken `phase_changed` as the canonical authority for navigation in any other transition (Result → ROUND_SCOREBOARD, ROUND_SCOREBOARD → Question, MATCH_END, etc.). Those remain Node-driven.
- We do **not** allow non-buzz-winners to land on /duo/result early. They stay on Question (no-buzz path) or Answer (still buzzing) until Node moves them.
- We do **not** apply this to Master or League. The Result-page UX in those modes can be revisited separately if the same complaint surfaces.

## Implementation

| Layer | File | Change |
|---|---|---|
| Client (Answer page) | `resources/views/duo_answer.blade.php` | After `DuoSocketClient.answer(...)`, schedule a 250 ms `setTimeout` that navigates to `/game/duo/result?match_id=…` if `isRedirecting` is still `false`. The 250 ms grace lets the socket flush. |
| Controller (phase guard) | `app/Http/Controllers/DuoController.php::validatePhaseAccess` | The `result` case allows ANSWER_SELECTION / BUZZ_WINNER_ANSWERING / ANSWER_COLLECTION when `lockedAnswerPlayerId === user.id`. |
| Controller (render) | `app/Http/Controllers/DuoController.php::renderResultView` | Reads `$currentPhase` from Redis room state. Computes `$resultPending = !in_array($currentPhase, ['RESULT','REVEAL','ROUND_SCOREBOARD','MATCH_END','FINISHED'])` and passes it to the view. |
| View | `resources/views/duo_result.blade.php` | Pending overlay element shown when `@if($resultPending)`; new `_onResultAnswerRevealed` handler (filtered by `playerId` against `SB_GAME_CONTEXT.currentUserId`) updates `.result-title`, `.points-earned`, `.answer-display`, hides the overlay. Score nodes (`[data-stat="score"]`) are hydrated automatically by `GameplayRuntime.js` via `score_update` / `match_stats` / `round_stats`. |

## Risks accepted

- **Stale DB scores at first paint.** When the buzz-winner arrives early, the controller's `match.game_state.player_scores_map` may not yet reflect the just-submitted answer. The pending overlay hides this until socket hydration. Worst case if hydration fails: the page stays in pending mode for `RESULT.timer` seconds, then `phase_changed RESULT` arrives anyway and the page transitions normally.
- **Reconnect on /duo/result during pending mode.** The `state` event emitted by the server on join carries `phase` + `phaseEndsAtMs`. `_onResultState` (existing) already refreshes the countdown deadline from this. If reconnect lands during ANSWER_*, the pending overlay stays until the next `phase_changed`.

## Why not the alternative

We considered keeping the player on the Answer page and just enhancing the in-page result feedback. Rejected because:
- The Answer page is conceptually the buzzing-and-answering surface; lingering there with result feedback conflates two stages.
- The Result page already owns the "between rounds" UI (Parchemin skill, score battle, fun fact, ready-to-continue button). Replicating those on the Answer page would duplicate ~500 lines of Blade.
- The user's mental model is "I answered, take me to the next screen". Server-driven nav broke that model.
