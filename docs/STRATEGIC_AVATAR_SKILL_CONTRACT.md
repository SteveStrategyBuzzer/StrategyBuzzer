# Strategic Avatar Skill Contract — CANONICAL

> **Authority:** This document is the source of truth for how strategic avatar
> skills are designed, wired, and validated across the entire StrategyBuzzer
> codebase. Any work that touches a skill — addition, fix, refactor, or new
> mode wiring — MUST satisfy every rule below before being marked done.
> Violations are regressions and will be rejected in code review.

This file is referenced from `replit.md` (`Avatar System` section). Keep this
file detailed; the `replit.md` Avatar System bullet is intentionally a one-line
pointer to here so that the project memory file stays compact and the contract
itself is never auto-summarized.

---

## 1. Universal visibility rule

Every skill MUST be **visible on the Question page of every mode** in which the
player owns it (Solo, Duo, Master, League Individual, League Team), with one of
exactly four explicit states:

| State | Meaning |
|---|---|
| `disponible` | Skill is owned, charged, and may be activated when its activation page is reached. |
| `actif` | Skill is currently producing an effect (timer running, contre-attaque armed, etc.). |
| `consommé` | Single-use or per-match counter exhausted; skill is locked for the remainder of the match. |
| `bloqué (non applicable)` | Skill exists for the player but cannot be used in this mode / phase / context. Must be visually distinct from `disponible`. |

The Question page is the player's **skill dashboard**. A skill that exists for
a mode but is invisible on that mode's Question page is a contract violation —
even if it cannot be activated from that page.

---

## 2. Activation page rule

Each skill has exactly one "activation surface" (Question / Réponse / Résultat,
or a defensive `Question OR Réponse` for reactive skills like Défenseur). See
the catalogue in §4. A skill activated on the wrong page is a contract
violation.

---

## 3. Architecture (strict)

| Layer | Responsibility | Forbidden |
|---|---|---|
| **Node** (`apps/game-server/src/services/GameOrchestrator.ts`) | Sole authority on skill eligibility, target selection, effect resolution, `activeEffects` mutation, use counters. | Never trust the client's claim that a skill is available. |
| **Socket.IO** | Transport only. Canonical events: `skill_used`, `skill_activated`, `phase_changed`, `state`, `game_state`. | No skill effect logic on the wire layer. |
| **Runtime commun** (`public/js/GameplayRuntime.js`, `public/js/DuoSocketClient.js`, `GameEffectsRuntime` when present) | Single source for `activeEffects` rendering and `restoreState` after reconnect / cold-load. | No mode-specific or skill-specific branches duplicated across views. |
| **Blade** | DOM hooks + passive visual state ONLY. | **Forbidden:** cabling skill logic directly into a single Blade if that skill exists in more than one mode. Reusable logic belongs in Node (truth) or runtime (display). |

If you find yourself writing the same `if (skillId === 'X')` branch in two
different Blade files, stop — extract it to the runtime layer.

---

## 4. Catalogue — 12 avatars × 3 tiers

### Rare 🎯 (1 competence each)

| Avatar | Competence | Visible | Activable |
|---|---|---|---|
| 🧠 **Mathématicien** | Illuminate a correct answer if it contains a digit. | Question | Réponse |
| 🧪 **Scientifique** | Acidify 1 wrong answer before choosing. | Question | Réponse |
| 🧭 **Explorateur** | Illuminate opponent's choice (Duo) / most-clicked answer (Master). | Question | Réponse |
| 🛡️ **Défenseur** | Cancel any avatar's incoming attack. | Question | **Question OR Réponse** (whichever page the attack lands on) |

### Épique ⭐ (2 competences each)

| Avatar | Competences |
|---|---|
| 🎭 **Comédien** | (1) **Master only:** display lower score till end of match — visible Question / activable **Résultat**; (2) trick by displaying correct→wrong — visible Question / activable **Réponse** / effect visible Résultat. |
| 🧙 **Magicien** | (1) Bonus question 1×/match — visible Question / activable **Résultat of Q10**; (2) cancel 1 wrong answer without buzz 1×/match — visible Question / effect Résultat. |
| 🔥 **Challenger** | (1) Shuffle other players' answers every 2 s — Question + Réponse; (2) reduce opponents' timer by **−2 s in Question / −3 s in Réponse**. Visible: Question. Activable: Question or Réponse. |
| 📚 **Historien** | (1) Text hint shown earlier than others — visible Question / activable **Résultat**; (2) +2 s extra answer time 1× — visible Question / activable **Résultat**. |

### Légendaire 👑 (3 competences each)

| Avatar | Competences |
|---|---|
| 🤖 **IA Junior** | (1) AI suggestion illuminates correct answer 1× — Résultat; (2) eliminate 2 wrong answers out of 4 — **Réponse**; (3) retake 1 wrong answer 1× — **Réponse**. |
| 🏆 **Stratège** | (1) +20 % intelligence + competence coins on victorious matches (passive end-of-match); (2) add 1 Rare-tier skill to a team in any mode; (3) boutique cost reduction: Rare −40 %, Épique −30 %, Légendaire −20 %. |
| ⚡ **Sprinteur** | (1) Automatic over 5 questions: if buzz lands 0.01–0.75 s after opponent → Sprinteur effect; (2) +3 s extra reflection 1× — **Réponse**; (3) **auto-reactivation** of skills after each round. |
| 🌟 **Visionnaire** | (1) Preview 5 future questions, 5×/match — Résultat; (2) counter 1 Challenger attack/match (effect on the page where the attack hits); (3) when potential gain = 2 pts in a round, only the correct answer remains selectable — **Résultat**. |

---

## 5. Solo special rule (NOT a separate world)

Solo MAY execute locally for UX latency, but MUST honor the **same central
contract**:

- Same skill name
- Same allowed phase(s)
- Same use counter
- Same logical effect
- Same final stats (when applicable)

### Worked examples

| Skill | Solo execution | Duo / League execution | Master execution |
|---|---|---|---|
| Scientifique | Eliminates a wrong answer locally. | Node decides which wrong answer is eliminated and broadcasts the effect. | Same as Duo. |
| Explorateur | Shows a system bot's choice. | Shows the real opponent's choice. | Shows the most-clicked answer. |
| Challenger −3 s | Decrements the local Solo bot's timer in the local store. | Node decrements opponent timers and emits `phase_changed` with the new `phaseEndsAtMs`. | Same as Duo (broadcasts to all targeted players). |

Solo writing its own divergent rules is **forbidden** — it recreates the
"one game per mode" problem this contract exists to prevent.

---

## 6. Per-skill validation checklist (10 points)

Every skill change must pass all that apply. Use this as a code-review
checklist; if a skill ships failing any of these, it ships broken.

1. **Page where it is visible** — confirmed on the Question page of every mode the player owns it in, with correct state badge.
2. **Page where it is activable** — matches the catalogue in §4.
3. **Socket event used** — `skill_used` and/or `skill_activated`, payload conforms to the canonical shape.
4. **Node effect handler** — implemented in `GameOrchestrator` or a service it owns; client never decides eligibility.
5. **`activeEffects` updated correctly** — both addition (on activation) and removal (on expiry / consumption).
6. **Display restored after reconnect / `restoreState`** — verified via cold-load on the relevant page; the runtime rehydrates the skill's state badge and any active visual effect.
7. **State `disponible` / `actif` / `consommé` correctly transitioned** — observable in the UI and consistent with Node's `activeEffects` snapshot.
8. **Compatible with Duo** — works in both 1v1 Duo and any Duo sub-mode.
9. **Compatible with Solo** — same contract, possibly local execution (see §5).
10. **Compatible with Master + League** — both League Individual (1v1 career) and League Team (5v5).

---

## 7. Where this is implemented today

| Concern | Path |
|---|---|
| Skill catalogue (PHP source of truth) | `app/Services/AvatarSkillService.php` |
| Node gameplay authority | `apps/game-server/src/services/GameOrchestrator.ts` |
| WebSocket handlers | `apps/game-server/src/ws/handlers/*` |
| Common client runtime | `public/js/GameplayRuntime.js`, `public/js/DuoSocketClient.js` |
| Effects runtime (when present) | `public/js/GameEffectsRuntime.js` |
| Per-mode Blade views | `resources/views/{solo,duo,master,league}_*.blade.php` |
| Shared question layout | `resources/views/game_question.blade.php` |
| Translations (10 languages) | `resources/lang/{ar,de,el,en,es,fr,it,pt,ru,zh}.json` |

Any new user-facing skill string MUST be wrapped with `{{ __('text') }}` in
Blade and added to all 10 language JSON files.
