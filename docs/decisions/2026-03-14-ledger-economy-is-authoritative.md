# Decision Record — Ledger economy is authoritative

**Date:** 2026-03-14  
**Status:** Accepted  
**Project:** StrategyBuzzer

## Decision

StrategyBuzzer now treats the economic ledger as the single source of truth for coin mutations.

The authoritative ledger table is:

- `coin_ledger`

The authoritative application service for coin mutations is:

- `App\Services\CoinLedgerService`

All coin credits and debits must pass through:

- `CoinLedgerService::credit(...)`
- `CoinLedgerService::debit(...)`

Direct balance mutations are forbidden in business logic.

## Forbidden patterns

The following patterns are now considered invalid in gameplay and economy flows:

```php
$user->coins += ...
$user->coins = ...
$user->competence_coins += ...
$user->competence_coins = ...
$user->increment('coins')
$user->decrement('coins')
$user->increment('competence_coins')
$user->decrement('competence_coins')
DB::table('users')->update([... coin balances ...])
DB::table('users')->update([...])

## Consequence

Any feature affecting coins must use the ledger service.

Examples:

- quests
- avatars
- leagues
- duo bets
- rewards
- refunds
