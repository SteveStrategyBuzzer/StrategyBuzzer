#!/usr/bin/env node
/**
 * Browser-driven E2E regression test for the Master mode join_room flow.
 *
 * Mirrors tests/e2e/duo-join-room.browser.spec.js but for Master mode.
 *
 * GUARDS AGAINST: any future change that causes the Master gameplay view
 * (`master.game-question`, served by /game/master/question) to send a
 * malformed `join_room` payload to the Game Server. The view loads
 * `partials.game-context` + `GameplayRuntime.js`, which is the surface
 * the original Duo regression broke.
 *
 * Strategy:
 *   1. POST /__test/login                     — authenticate fixture user
 *   2. POST /__test/master/setup-bot-match    — create a fresh MasterGame in
 *      'playing' status, allocate a Game Server room (mode=master), persist
 *      room_id + lobby_code on the game, seed session('master_game_state').
 *   3. Open Playwright (headless Chromium) with the auth cookies and
 *      navigate to /game/master/question.
 *   4. Capture EVERY browser console message + page error.
 *   5. Assert: zero forbidden patterns AND ≥1 `[GameplayRuntime] Joined room:`
 *      (positive signal that join_room succeeded round-trip).
 *
 * Usage:    npm run test:e2e:master-join-browser
 * Exit:     0 = pass / 1 = regression / setup failure.
 */

const http = require('http');
const { execSync } = require('child_process');
const { chromium } = require('playwright');

const BACKEND_URL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:5000';
const TIMEOUT_MS = parseInt(process.env.E2E_TIMEOUT_MS || '20000', 10);

function resolveChromiumPath() {
  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
    return process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  }
  try {
    return execSync('which chromium', { stdio: ['ignore', 'pipe', 'ignore'] })
      .toString()
      .trim() || undefined;
  } catch (_e) {
    return undefined;
  }
}

const FORBIDDEN_PATTERNS = [
  /VALIDATION_ERROR/i,
  /Invalid join_room/i,
  /Cannot join_room/i,
];
const SUCCESS_PATTERN = /\[GameplayRuntime\]\s+Joined room:/i;

function info(msg) { console.log('[INFO]', msg); }
function pass(msg) { console.log('[PASS]', msg); }
function fail(msg, detail) {
  console.error('\n[FAIL]', msg);
  if (detail !== undefined) console.error(detail);
  process.exit(1);
}

let cookieJar = '';
const collectedCookies = [];

function postJson(urlString, body) {
  return new Promise((resolve, reject) => {
    const url = new URL(urlString);
    const data = JSON.stringify(body || {});
    const req = http.request(
      {
        hostname: url.hostname,
        port: url.port || 80,
        path: url.pathname,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Content-Length': Buffer.byteLength(data),
          ...(cookieJar ? { Cookie: cookieJar } : {}),
        },
      },
      (res) => {
        let chunks = '';
        res.on('data', (c) => (chunks += c));
        res.on('end', () => {
          const setCookie = res.headers['set-cookie'];
          if (setCookie && setCookie.length) {
            for (const raw of setCookie) {
              const [pair, ...attrs] = raw.split(';').map((s) => s.trim());
              const eq = pair.indexOf('=');
              if (eq < 0) continue;
              const name = pair.slice(0, eq);
              const value = pair.slice(eq + 1);
              const pathAttr = attrs.find((a) => /^path=/i.test(a));
              const path = pathAttr ? pathAttr.split('=')[1] : '/';
              const existing = collectedCookies.findIndex((c) => c.name === name);
              const entry = { name, value, domain: url.hostname, path };
              if (existing >= 0) collectedCookies[existing] = entry;
              else collectedCookies.push(entry);
            }
            cookieJar = collectedCookies.map((c) => `${c.name}=${c.value}`).join('; ');
          }
          let parsed = null;
          try { parsed = JSON.parse(chunks); } catch (e) { /* leave null */ }
          resolve({ status: res.statusCode, body: parsed, raw: chunks });
        });
      }
    );
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

async function main() {
  info(`Backend = ${BACKEND_URL}`);

  // ---------- step 1: login ----------
  info('Logging in fixture user via /__test/login ...');
  const login = await postJson(`${BACKEND_URL}/__test/login`, {});
  if (login.status !== 200 || !login.body || login.body.success !== true) {
    fail('Login failed', login);
  }
  pass(`Logged in as user_id=${login.body.user_id} player_code=${login.body.player_code}`);

  // ---------- step 2: setup master bot match ----------
  info('Creating Master bot game via /__test/master/setup-bot-match ...');
  const setup = await postJson(`${BACKEND_URL}/__test/master/setup-bot-match`, {});
  if (setup.status !== 200 || !setup.body || setup.body.success !== true) {
    fail('Setup master bot match failed', setup);
  }
  const questionUrl = setup.body.question_url;
  if (!questionUrl) {
    fail('Setup response missing question_url', setup.body);
  }
  pass(`Master game #${setup.body.game_id} ready — code=${setup.body.game_code} room=${setup.body.room_id}`);

  // ---------- step 3: launch headless browser ----------
  const chromiumPath = resolveChromiumPath();
  info(`Launching headless Chromium ${chromiumPath ? `(${chromiumPath})` : '(playwright bundled)'} ...`);
  const browser = await chromium.launch({
    headless: true,
    ...(chromiumPath ? { executablePath: chromiumPath } : {}),
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const context = await browser.newContext({
    viewport: { width: 1280, height: 720 },
    ignoreHTTPSErrors: true,
  });

  if (collectedCookies.length === 0) {
    await browser.close();
    fail('No cookies were captured from /__test/login — auth will not survive into the browser');
  }
  await context.addCookies(
    collectedCookies.map((c) => ({
      name: c.name,
      value: c.value,
      domain: c.domain,
      path: c.path || '/',
      httpOnly: false,
      secure: false,
      sameSite: 'Lax',
    }))
  );

  const consoleMessages = [];
  const pageErrors = [];

  const page = await context.newPage();
  page.on('console', (msg) => {
    consoleMessages.push({ type: msg.type(), text: msg.text(), at: page.url() });
  });
  page.on('pageerror', (err) => {
    pageErrors.push({ message: err.message, stack: err.stack, at: page.url() });
  });

  // ---------- step 4: navigate the gameplay UI ----------
  const toAbs = (u) => (/^https?:\/\//i.test(u) ? u : `${BACKEND_URL}${u.startsWith('/') ? '' : '/'}${u}`);

  info(`Navigating to ${toAbs(questionUrl)} ...`);
  await page.goto(toAbs(questionUrl), { waitUntil: 'load', timeout: TIMEOUT_MS });
  // Socket.IO keeps the connection open — wait by time, not network idle.
  await page.waitForTimeout(7000);

  await context.close();
  await browser.close();

  // ---------- step 5: assertions ----------
  const offending = consoleMessages.filter((m) =>
    FORBIDDEN_PATTERNS.some((re) => re.test(m.text))
  );
  if (offending.length > 0) {
    fail(
      `REGRESSION DETECTED — ${offending.length} forbidden console message(s):`,
      offending
    );
  }

  const fatalPageErrors = pageErrors.filter((e) =>
    FORBIDDEN_PATTERNS.some((re) => re.test(e.message))
  );
  if (fatalPageErrors.length > 0) {
    fail(`REGRESSION DETECTED — page errors matched forbidden patterns:`, fatalPageErrors);
  }

  const joined = consoleMessages.find((m) => SUCCESS_PATTERN.test(m.text));
  if (!joined) {
    fail(
      'Did not observe "[GameplayRuntime] Joined room:" in browser console — '
      + 'the join_room round-trip never completed for Master mode.',
      { totalConsoleMessages: consoleMessages.length, sample: consoleMessages.slice(-15) }
    );
  }

  pass(`Observed positive signal: "${joined.text}" on ${joined.at}`);
  pass(`Captured ${consoleMessages.length} console messages — none matched forbidden patterns`);
  console.log('\n[OK] tests/e2e/master-join-room.browser.spec.js — all assertions passed');
  process.exit(0);
}

main().catch((err) => {
  fail('Uncaught error in test runner', err && (err.stack || err.message || err));
});
