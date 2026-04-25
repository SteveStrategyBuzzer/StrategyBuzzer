import { test, expect } from '@playwright/test';
import {
  loginWithEmail,
  TEST_USER_ID,
  BOT_USER_ID,
  BOT_PLAYER_CODE,
} from './helpers/auth';
import {
  readStatSlots,
  hasAnyNonDefault,
  formatSnapshot,
} from './helpers/stats';
import {
  getLatestDuoMatchForUser,
  pollForFinalizedDuoMatch,
} from './helpers/db';

/**
 * Task #46 — Live stats engine, Duo mode (PRIMARY test).
 *
 * Verifies:
 *  - The Node game server emits player_stats_updated during the match.
 *  - GameplayRuntime patches the [data-stat][data-player] DOM slots for
 *    BOTH "self" (the human user) and "opponent" (the dev bot).
 *  - At match end, DuoController::internalFinalize writes the duo_matches
 *    row with status='finished' and finished_at set.
 *
 * Requires: Backend (php -S :5000), Game Server (:3001), Redis up.
 * The dev-only bot (user id=11, player_code=BT-0001) must exist and APP_ENV
 * must NOT be 'production' so the bot endpoint is allowed.
 *
 * Duo lobby flow (important): the lobby does NOT render "#start-btn" for
 * Duo mode (lobby.blade.php gates it on `$mode !== 'duo'`). Instead both
 * players click "#ready-btn" (toggleReady()), and the server auto-launches
 * the match when both are ready. The dev bot is auto-marked ready when it
 * joins the lobby.
 */
const DUO_GAMEPLAY_URL_RE = /\/game\/(duo|match)|\/duo\/(game|question|answer|result)/;
const DUO_RESULT_URL_RE = /match[-_/]?result|duo[-_/]?result|finished|result_final/i;

test.describe('Duo live stats + finalize', () => {
  test('bot match drives [data-stat] slots for self+opponent and finalizes DB row', async ({
    page,
  }) => {
    test.setTimeout(8 * 60 * 1000);

    // Capture browser console to surface stats / socket errors on failure.
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    // 1) Login (email/password Laravel route, not Firebase OAuth).
    await loginWithEmail(page);

    // 2) Snapshot the previous latest match id so we can wait for OUR match.
    const before = await getLatestDuoMatchForUser(TEST_USER_ID);
    const minNewMatchId = (before?.id ?? 0) + 1;

    // 3) Open the matchmaking lobby and invite the bot by player_code.
    await page.goto('/duo/lobby');
    await page.waitForLoadState('domcontentloaded');

    // Submit invite via fetch to bypass invite UI variability.
    const inviteResp = await page.evaluate(async (code) => {
      const csrf =
        document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute('content') || '';
      const r = await fetch('/duo/invite', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ player_code: code }),
      });
      return { ok: r.ok, status: r.status, body: await r.json().catch(() => null) };
    }, BOT_PLAYER_CODE);

    expect(inviteResp.ok, `invite POST: ${JSON.stringify(inviteResp)}`).toBe(true);
    const redirectUrl =
      (inviteResp.body && (inviteResp.body.redirect_url || inviteResp.body.url)) ||
      null;
    expect(redirectUrl, 'invite response missing redirect_url').toBeTruthy();

    // 4) Open the in-match lobby.
    await page.goto(redirectUrl as string);
    await page.waitForLoadState('domcontentloaded');

    // 5) Mark self ready. Duo has no #start-btn; the server auto-launches
    //    once all players (the bot is auto-ready on join) are ready.
    const readyBtn = page.locator('#ready-btn');
    await expect(readyBtn).toBeVisible({ timeout: 15_000 });
    await readyBtn.click();

    // Some installs do render #start-btn (non-duo modes); click it if it appears.
    const startBtn = page.locator('#start-btn');
    if (await startBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await expect
        .poll(
          async () =>
            (await startBtn.getAttribute('data-backend-can-start')) === 'true' ||
            !(await startBtn.isDisabled()),
          { timeout: 15_000, intervals: [500, 1000] },
        )
        .toBe(true);
      await startBtn.click().catch(() => {});
    }

    // 6) Wait for a gameplay URL.
    await page.waitForURL(DUO_GAMEPLAY_URL_RE, { timeout: 60_000 });

    // 7) Drive the first few questions.
    const playOneQuestion = async () => {
      // Wait for QUESTION_ACTIVE: a buzz button must become enabled.
      const buzz = page.locator(
        '#buzzButton, button.buzz-button, [data-action="buzz"]',
      );
      await buzz
        .first()
        .waitFor({ state: 'visible', timeout: 45_000 })
        .catch(() => {});
      const ok = await buzz
        .first()
        .isVisible()
        .catch(() => false);
      if (ok) {
        await expect
          .poll(async () => !(await buzz.first().isDisabled()), {
            timeout: 30_000,
            intervals: [500, 1000],
          })
          .toBe(true);
        await buzz.first().click().catch(() => {});
      }

      // Try to click an answer choice if it appears (we may not always win the buzz).
      const answer = page
        .locator(
          'button.answer-button, button.answer-choice, button.answer-btn, button.answer-option, [data-action="answer"]',
        )
        .first();
      await answer
        .waitFor({ state: 'visible', timeout: 8_000 })
        .then(() => answer.click().catch(() => {}))
        .catch(() => {});

      // Let the answer/result phase elapse.
      await page.waitForTimeout(8_000);
    };

    await playOneQuestion();

    // 8) ASSERT: at least one self stat slot is non-default after question 1.
    const selfAfterQ1 = await readStatSlots(page, 'self');
    expect(
      hasAnyNonDefault(selfAfterQ1),
      `self stats are still all defaults after Q1: ${formatSnapshot(selfAfterQ1)}`,
    ).toBe(true);

    // 9) Play another question or two so the bot also has time to score.
    await playOneQuestion();
    await playOneQuestion();

    // 10) ASSERT: opponent slots updated too (bot has buzzed/answered).
    const opponentSnap = await readStatSlots(page, 'opponent');
    expect(
      hasAnyNonDefault(opponentSnap),
      `opponent stats are still all defaults after 3 questions: ${formatSnapshot(
        opponentSnap,
      )}`,
    ).toBe(true);

    // 11) Let the rest of the match finish naturally (best effort).
    const matchEndDeadline = Date.now() + 6 * 60 * 1000;
    while (Date.now() < matchEndDeadline) {
      const url = page.url();
      if (DUO_RESULT_URL_RE.test(url)) break;
      await playOneQuestion().catch(() => {});
      // Quick check whether DB already saw finalization for OUR match.
      const cur = await getLatestDuoMatchForUser(TEST_USER_ID);
      if (
        cur &&
        cur.id >= minNewMatchId &&
        cur.is_finalized &&
        ((cur.player1_id === TEST_USER_ID && cur.player2_id === BOT_USER_ID) ||
          (cur.player2_id === TEST_USER_ID && cur.player1_id === BOT_USER_ID))
      ) {
        break;
      }
    }

    // 12) Poll the DB for finalization of THIS test's match.
    const finalRow = await pollForFinalizedDuoMatch(TEST_USER_ID, {
      timeoutMs: 90_000,
      minId: minNewMatchId,
    });
    expect(
      finalRow,
      `no duo_matches row at all for user ${TEST_USER_ID}; console errors: ${consoleErrors
        .slice(0, 5)
        .join(' | ')}`,
    ).not.toBeNull();
    expect(
      finalRow!.id >= minNewMatchId,
      `latest duo_matches row (id=${finalRow!.id}) predates this test (expected >= ${minNewMatchId})`,
    ).toBe(true);

    // Correlate with the (test user, bot) pair to rule out an unrelated row.
    const isOurPair =
      (finalRow!.player1_id === TEST_USER_ID && finalRow!.player2_id === BOT_USER_ID) ||
      (finalRow!.player2_id === TEST_USER_ID && finalRow!.player1_id === BOT_USER_ID);
    expect(
      isOurPair,
      `latest duo_matches row is not the test↔bot pair: ${JSON.stringify({
        id: finalRow!.id,
        p1: finalRow!.player1_id,
        p2: finalRow!.player2_id,
      })}`,
    ).toBe(true);

    expect(
      finalRow!.status,
      `match not finalized: row=${JSON.stringify(finalRow)}`,
    ).toBe('finished');
    expect(finalRow!.is_finalized).toBe(true);
  });
});
