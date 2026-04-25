# StrategyBuzzer e2e tests (Task #46 — live stats engine)

Playwright + TypeScript. These specs validate the `[data-stat][data-player]`
DOM contract that `public/js/GameplayRuntime.js` and
`public/js/SoloStatsEngine.js` populate from the Node game-server's
`player_stats_updated` / `round_stats` / `match_stats` events, plus the
`duo_matches` finalization pipeline.

## Scope (user-approved for #46)

| Mode             | Spec                          | Status                                                                                  |
| ---------------- | ----------------------------- | --------------------------------------------------------------------------------------- |
| **Duo**          | `duo.spec.ts`                 | Live stats (self+opponent) + DB finalize. Uses dev-only `BotPlayerService`.             |
| **Solo**         | `solo.spec.ts`                | Local `SoloStatsEngine` self stats.                                                     |
| **Master**       | `master.spec.ts`              | DOM/runtime contract only — no Master bot service exists yet.                           |
| **League Team**  | `league-team.skip.md`         | **SKIPPED (blocked).** League Team is still REST-polling; awaits Node-engine migration. |

## Prerequisites

- Workflows running: **Backend** (`php -S 0.0.0.0:5000`), **Game Server**
  (port 3001), **Redis**, **Queue Worker**, **Question API**.
- `APP_ENV` ≠ `production` (the Duo dev bot endpoint is gated to non-prod).
- Database has the standard test fixtures:
  - User `test@strategybuzzer.com` / `test123456` (id `1`)
  - Bot user `bot@strategybuzzer.local` (id `11`, `player_code = BT-0001`,
    `is_bot = true`)
- Node ≥ 18.

## Install

Playwright is **not** part of the default monorepo deps because the test
agent normally drives the same Playwright runtime. Install on demand:

```bash
npm install --no-save -D @playwright/test pg
npx playwright install chromium
```

## Run

```bash
# all specs
npx playwright test --config tests/e2e/playwright.config.ts

# just the Duo live-stats e2e
npx playwright test --config tests/e2e/playwright.config.ts duo

# point at a non-default base URL (e.g. when running against $REPLIT_DEV_DOMAIN)
E2E_BASE_URL="https://$REPLIT_DEV_DOMAIN" \
  npx playwright test --config tests/e2e/playwright.config.ts
```

HTML report:

```bash
npx playwright show-report tests/e2e/playwright-report
```

## Environment overrides

| Variable               | Default                   |
| ---------------------- | ------------------------- |
| `E2E_BASE_URL`         | `http://localhost:5000`   |
| `E2E_TEST_EMAIL`       | `test@strategybuzzer.com` |
| `E2E_TEST_PASSWORD`    | `test123456`              |
| `E2E_TEST_USER_ID`     | `1`                       |
| `E2E_BOT_PLAYER_CODE`  | `BT-0001`                 |
| `E2E_BOT_USER_ID`      | `11`                      |

DB connection uses `DATABASE_URL` if present, otherwise the standard
`PG{HOST,PORT,USER,PASSWORD,DATABASE}` env vars.

## Why these specs are not run automatically by the platform's test agent

At the time #46 was implemented, the workspace's test agent was reporting
a hard, persistent block (cached from a prior session: it had decided this
app was "Firebase OAuth only" and refused to retry). The specs are therefore
written as standalone Playwright files that you can run with the `npx`
commands above as soon as the test agent un-sticks, or directly from CI.
