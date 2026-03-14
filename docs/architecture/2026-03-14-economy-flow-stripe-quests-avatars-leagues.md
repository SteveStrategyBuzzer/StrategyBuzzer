# Architecture — Economy flow (Stripe, quests, avatars, leagues)

**Date:** 2026-03-14  
**Project:** StrategyBuzzer

## Purpose

This document defines the current intended economy flow of StrategyBuzzer so future development stays coherent across:

- Laravel backend
- Node realtime stack
- quests
- avatars
- leagues
- Stripe monetization
- future shop systems

## 1. Core principle

StrategyBuzzer economy is split into:

### A. Real-money entry
Handled by Stripe.

### B. In-game economy
Handled by game systems and tracked through ledger.

## 2. Economic flow

### Stripe layer

Stripe should only be used to unlock or sell real-money items such as:

- coin packs
- accelerated access (Duo / League)
- Master mode access

### Ledger layer

Every in-game coin mutation must be recorded in:

- `coin_ledger`

through:

- `CoinLedgerService`

## 3. Current balance families

Current project balance fields:

- `coins`
- `competence_coins`

Current coin types used through ledger service calls:

- `intelligence`
- `competence`

These need to stay coherent in future refactors.

## 4. Main reward sources

### Quests

Quests are an important source of progression and economy.

They can provide:
- competence coins
- future intelligence coins
- avatar unlock progress
- access bonuses
- cosmetic / progression rewards

Quests must never bypass ledger when granting currency.

### Duo

Duo can mutate economy through:
- bet stake
- refund
- pot win
- match reward

### League individual

League individual can grant:
- performance rewards
- progression-related payouts

### League team

League team can grant:
- team victory rewards
- temporary higher-division access costs

## 5. Avatar economy

Avatars are not just cosmetic items. They are part of the progression economy.

Avatar unlock paths may include:
- direct coin purchase
- quest unlock
- rarity gating
- future premium / bundle paths

This means avatar pricing must stay coherent with:
- quest reward volume
- progression speed
- rarity value
- monetization balance

### Strategic rule

Avatars should behave as an economic sink and aspirational reward system, not as random free inflation output.

## 6. Quest economy design rule

Quests should be treated as a real economic subsystem.

Each quest reward must define:
- reward type
- reward quantity
- repeatability
- uniqueness / one-shot behavior
- daily / weekly / permanent status
- ledger usage if currency is involved

### Important

If quests generate too much currency, avatar value collapses and the free economy becomes inflationary.

## 7. Official corrected economy flows as of 2026-03-14

The following flows were explicitly aligned to ledger during cleanup:

- `quest_reward`
- `league_team_reward`
- `league_team_temp_access`
- `duo_bet_stake`
- `duo_bet_refund`

The following were already part of the broader aligned ledger direction:

- `avatar_unlock`
- `division_temp_access`
- `duo_match_reward`
- `duo_bet_pot_win`
- `league_individual_reward`

## 8. Risks to avoid going forward

### Never do this in gameplay code
- direct `increment()` / `decrement()` on balances
- direct assignment to balances
- DB updates on balances bypassing ledger

### Why

Because this creates:
- invisible drift
- race conditions
- audit loss
- refund inconsistency
- exploit vectors

## 9. Required hardening still pending

1. add row locking inside `CoinLedgerService`
2. add model-level protection on `User`
3. freeze official ledger reasons
4. standardize idempotency rules
5. align Replit with corrected VM state
6. review quest reward scale vs avatar pricing

## 10. Operational note

Because Replit is the official source of truth, VM-side economic fixes must be propagated back through:

- VM commit
- GitHub push
- Replit pull

This avoids long-term architecture divergence.
