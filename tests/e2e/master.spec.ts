import { test, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

/**
 * Task #46 — Live stats engine, Master mode.
 *
 * SCOPE LIMIT: Master mode does NOT have a dev-only bot service (unlike Duo,
 * which has BotPlayerService). Driving a real Master game from a single
 * browser would require provisioning 3-40 bot socket clients, which is out
 * of scope for #46.
 *
 * Per the user-approved test scope ("Master live stats SI runtime WS chargé"),
 * this test only verifies the static contract — that the Master game-question
 * view loads the live-stats runtime AND exposes the [data-stat][data-player]
 * slots that GameplayRuntime targets. A future task can add a multi-bot
 * Master driver.
 *
 * IMPORTANT: master/game-question.blade.php extends layouts.app (NOT
 * layouts.game), so it loads Socket.IO + DuoSocketClient + GameplayRuntime
 * via its OWN script tags at the bottom of the template. We therefore assert
 * against the Master template directly, not the central layout.
 */

const REQUIRED_MASTER_STATS = [
  'score',
  'efficiencyPercent',
  'currentStreak',
  'averageResponseMs',
  'buzzCount',
  'correctAnswers',
  'wrongAnswers',
];

const MASTER_TEMPLATE = path.resolve(
  process.cwd(),
  'resources/views/master/game-question.blade.php',
);

test.describe('Master mode live stats DOM/runtime contract', () => {
  test('master/game-question.blade.php declares all live-stats slots', async () => {
    expect(
      fs.existsSync(MASTER_TEMPLATE),
      `template missing: ${MASTER_TEMPLATE}`,
    ).toBe(true);
    const src = fs.readFileSync(MASTER_TEMPLATE, 'utf8');

    for (const stat of REQUIRED_MASTER_STATS) {
      const re = new RegExp(`data-stat="${stat}"[^>]*data-player=`);
      expect(
        re.test(src),
        `Master view is missing [data-stat="${stat}"][data-player=...] slot`,
      ).toBe(true);
    }
  });

  test('master/game-question.blade.php loads Socket.IO + DuoSocketClient + GameplayRuntime', async () => {
    expect(fs.existsSync(MASTER_TEMPLATE)).toBe(true);
    const src = fs.readFileSync(MASTER_TEMPLATE, 'utf8');

    // Socket.IO client must be present (CDN or local).
    expect(
      /socket\.io(\.min)?\.js/.test(src),
      'Master template must load the Socket.IO client',
    ).toBe(true);

    // DuoSocketClient.js bridges socket events to the page.
    expect(
      /DuoSocketClient\.js/.test(src),
      'Master template must include DuoSocketClient.js',
    ).toBe(true);

    // GameplayRuntime.js is THE live-stats DOM patcher; without it,
    // [data-stat] slots never tick.
    expect(
      /GameplayRuntime\.js/.test(src),
      'Master template must include GameplayRuntime.js (the live-stats patcher)',
    ).toBe(true);
  });
});
