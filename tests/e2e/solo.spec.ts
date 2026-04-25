import { test, expect } from '@playwright/test';
import { loginWithEmail } from './helpers/auth';
import {
  readStatSlots,
  hasAnyNonDefault,
  formatSnapshot,
} from './helpers/stats';

/**
 * Task #46 — Live stats engine, Solo mode.
 *
 * Solo runs entirely in the browser via SoloStatsEngine (no Node game
 * server). After answering at least one question the [data-stat]
 * [data-player="self"] slots in solo_gameplay.blade.php must update.
 *
 * Solo entry flow (resources/views/solo.blade.php):
 *   1. GET /solo renders a form (id="soloForm") posting to /solo/start.
 *   2. The form requires selecting `select#nb_questions` AND clicking one of
 *      the `button.btn-theme[type="submit"]` (e.g. value="general"), which
 *      submits the form with the chosen theme.
 *   3. Solo gameplay then renders solo_gameplay.blade.php whose answer
 *      buttons are `button.reponse-btn` (form submits, not click handlers).
 */
test.describe('Solo SoloStatsEngine local stats', () => {
  test('answering questions updates self stat slots', async ({ page }) => {
    test.setTimeout(4 * 60 * 1000);

    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    await loginWithEmail(page);

    // 1) Solo entry form.
    await page.goto('/solo', { waitUntil: 'domcontentloaded' });

    // 2) Select 10 questions (smallest option) and submit with theme=general.
    await page.selectOption('select#nb_questions', '10');
    const themeBtn = page.locator('button.btn-theme[value="general"]').first();
    await expect(themeBtn).toBeVisible({ timeout: 10_000 });

    await Promise.all([
      page.waitForURL(
        (url) => !url.pathname.endsWith('/solo'),
        { timeout: 60_000 },
      ),
      themeBtn.click(),
    ]);

    // 3) Wait for solo_gameplay slots to mount.
    await expect
      .poll(
        async () =>
          await page.locator('[data-stat][data-player="self"]').count(),
        { timeout: 60_000, intervals: [1000, 2000] },
      )
      .toBeGreaterThan(0);

    // 4) Initial snapshot (everything should be defaults).
    const initial = await readStatSlots(page, 'self');

    // 5) Answer a few questions. solo_gameplay uses .reponse-btn (form submits).
    const playOne = async () => {
      // Solo may have a buzz step in some flows; try it if visible.
      const buzz = page
        .locator('#buzzButton, button.buzz-button, [data-action="buzz"]')
        .first();
      if (await buzz.isVisible().catch(() => false)) {
        const enabled = await expect
          .poll(async () => !(await buzz.isDisabled()), {
            timeout: 15_000,
            intervals: [500, 1000],
          })
          .toBe(true)
          .then(() => true)
          .catch(() => false);
        if (enabled) await buzz.click().catch(() => {});
      }

      const answer = page.locator('button.reponse-btn').first();
      await answer.waitFor({ state: 'visible', timeout: 30_000 });
      // Each .reponse-btn is a form submit that triggers a navigation.
      await Promise.all([
        page
          .waitForLoadState('domcontentloaded', { timeout: 30_000 })
          .catch(() => {}),
        answer.click(),
      ]);
      // Wait for the next solo gameplay page to mount its stat slots again.
      await expect
        .poll(
          async () =>
            await page.locator('[data-stat][data-player="self"]').count(),
          { timeout: 30_000, intervals: [500, 1000] },
        )
        .toBeGreaterThan(0);
    };

    await playOne();
    await playOne();
    await playOne();

    // 6) Stats must have changed from the initial defaults.
    const after = await readStatSlots(page, 'self');
    const changed = Object.keys(after).some(
      (k) => after[k] !== (initial[k] ?? ''),
    );
    expect(
      changed && hasAnyNonDefault(after),
      `solo stats did not update after 3 questions.\ninitial=${formatSnapshot(
        initial,
      )}\nafter=${formatSnapshot(after)}\nconsole errors=${consoleErrors
        .slice(0, 5)
        .join(' | ')}`,
    ).toBe(true);
  });
});
