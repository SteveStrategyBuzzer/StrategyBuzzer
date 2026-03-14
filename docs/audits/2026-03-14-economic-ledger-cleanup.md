# Audit — Economic ledger cleanup

**Date:** 2026-03-14  
**Project:** StrategyBuzzer  
**Environment audited:** VM production  
**Path:** `/home/stevegroupe/StrategyBuzzer`

## Objective

Audit economy-related services and controllers to ensure that coin mutations use the ledger instead of direct balance writes.

## Modules audited

### 1. `app/Services/QuestService.php`
**Status:** Corrected

#### Problem found
Quest rewards were crediting `competence_coins` directly.

#### Correction applied
Quest rewards were rerouted through:

- `CoinLedgerService::credit(...)`

#### Ledger reason used
- `quest_reward`

#### Notes
Quest progression locking logic already existed and was kept.  
Quest retroactive completion was also updated to use ledger.

---

### 2. `app/Services/LeagueTeamService.php`
**Status:** Corrected

#### Problems found
- team rewards credited directly with `increment('competence_coins')`
- temporary access debited directly with `decrement('competence_coins')`

#### Corrections applied
Both flows were migrated to ledger calls.

#### Ledger reasons used
- `league_team_reward`
- `league_team_temp_access`

---

### 3. `app/Http/Controllers/LeagueTeamController.php`
**Status:** Corrected

#### Problem found
Controller still debited temporary access cost directly.

#### Correction applied
Controller now delegates the economic debit through service logic instead of mutating balance directly.

---

### 4. `app/Services/LobbyService.php`
**Status:** Corrected

#### Problems found
- Duo bet stake used direct `decrement('competence_coins')`
- Refunds used direct `increment('competence_coins')`

#### Corrections applied
Both were migrated to ledger calls.

#### Ledger reasons used
- `duo_bet_stake`
- `duo_bet_refund`

#### Notes
This service already had better anti-duplication structure than average:
- cache lock
- DB transaction
- start record
- refund marker

---

## Previously aligned modules already using ledger

These modules were already considered aligned or had already been corrected before this audit phase:

- `AvatarController`
- `DivisionService`
- `DuoMatchmakingService`
- `LeagueIndividualService`

## Current corrected ledger-driven flows

- avatar unlock
- division temporary access
- duo match reward
- duo bet pot win
- duo bet stake
- duo bet refund
- quest reward
- league individual reward
- league team reward
- league team temporary access

## Files changed during this cleanup

- `app/Services/QuestService.php`
- `app/Services/LeagueTeamService.php`
- `app/Http/Controllers/LeagueTeamController.php`
- `app/Services/LobbyService.php`

## Additional repository cleanup performed

Temporary garbage files created during terminal/copy operations were identified and removed.

Examples included:
- `,`
- `-`
- `1,`
- `10,`
- `3,`
- `=`
- `division`
- `level`
- `points`
- similar empty root-level artifacts

These files were confirmed empty and unrelated to project logic.

## Risks still remaining

This audit improved the project significantly, but the economy is not yet fully hardened.

### Remaining risks
1. `CoinLedgerService` still mutates balances internally without `lockForUpdate()`
2. direct balance writes are not yet blocked at model level
3. official ledger reason catalog is not yet frozen
4. some balance checks still read raw balances before debit
5. Replit / GitHub / VM still need alignment after VM-side corrections

## Recommended next steps

1. harden `CoinLedgerService`
2. define allowed ledger reasons formally
3. add model-level protection against direct balance writes
4. commit VM changes cleanly
5. push to GitHub
6. pull into Replit so source-of-truth regains alignment
7. then continue quest/accomplishment integration on Replit from the cleaned base
