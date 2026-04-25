import { Page, expect } from '@playwright/test';

export const TEST_USER_EMAIL =
  process.env.E2E_TEST_EMAIL || 'test@strategybuzzer.com';
export const TEST_USER_PASSWORD =
  process.env.E2E_TEST_PASSWORD || 'test123456';
export const TEST_USER_ID = Number(process.env.E2E_TEST_USER_ID || 1);

export const BOT_PLAYER_CODE =
  process.env.E2E_BOT_PLAYER_CODE || 'BT-0001';
export const BOT_USER_ID = Number(process.env.E2E_BOT_USER_ID || 11);

export async function loginWithEmail(page: Page): Promise<void> {
  await page.goto('/auth/email/login', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('input[name="email"], #email')).toBeVisible({
    timeout: 15_000,
  });
  await page.fill('input[name="email"], #email', TEST_USER_EMAIL);
  await page.fill('input[name="password"], #password', TEST_USER_PASSWORD);

  await Promise.all([
    page.waitForURL(
      (url) =>
        !url.pathname.includes('/auth/email/login') &&
        !url.pathname.endsWith('/login'),
      { timeout: 30_000 },
    ),
    page.locator('form button[type="submit"], form input[type="submit"]').first().click(),
  ]);
}
