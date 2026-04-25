#!/usr/bin/env node
/**
 * Browser-driven E2E regression test for the Duo multiplayer join flow.
 *
 * GUARDS AGAINST: the recent "VALIDATION_ERROR: Invalid join_room payload"
 * regression — i.e. any future change that causes `public/js/GameplayRuntime.js`
 * to send a malformed `join_room` payload to the Game Server.
 *
 * Strategy:
 *   1. POST /__test/login                 — authenticate the fixture user
 *      (cookies survive into the Playwright browser context)
 *   2. POST /__test/duo/setup-bot-match   — create a fresh Duo match vs the
 *      seeded bot opponent (BT-0001), allocate a Game Server room, seed the
 *      Laravel session('game_state'). This skips the Firebase OAuth lobby
 *      pairing flow that we cannot drive headlessly, but we still exercise
 *      the full /game/duo/intro -> /game/duo/question UI path that the
 *      regression actually broke.
 *   3. Open Playwright (headless Chromium), inject the auth cookies, and
 *      navigate to /game/duo/intro then /game/duo/question.
 *   4. Capture EVERY browser console message + page error.
 *   5. Assert: zero console messages match the forbidden patterns
 *        - VALIDATION_ERROR
 *        - Invalid join_room
 *        - Cannot join_room
 *      AND we observed at least one `[GameplayRuntime] Joined room:` message
 *      (positive signal that join_room actually succeeded round-trip).
 *
 * Usage (from project root):
 *   npm run test:e2e:duo-join-browser
 *   # or directly:
 *   node tests/e2e/duo-join-room.browser.spec.js
 *
 * Required services:
 *   - Backend         (php :5000)
 *   - Game Server     (node :3001)
 *   - Redis Server    (:6379)
 *
 * Required env: APP_ENV must NOT be 'production' (the /__test/* routes are
 * gated by `app()->environment('production')` in routes/web.php).
 *
 * Exit codes: 0 = pass, 1 = regression detected / setup failure.
 */

const http = require('http');
const { execSync } = require('child_process');
const { chromium } = require('playwright');

const BACKEND_URL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:5000';
const TIMEOUT_MS = parseInt(process.env.E2E_TIMEOUT_MS || '20000', 10);

// Resolve a Chromium binary. Replit's NixOS environment doesn't ship the
// shared libs Playwright's bundled chrome-headless-shell needs, but the
// system `chromium` package (installed via the package-management skill)
// works and Playwright supports `executablePath` for exactly this case.
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
const collectedCookies = []; // [{ name, value, domain, path }]

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

  // ---------- step 2: setup bot match ----------
  info('Creating Duo bot match via /__test/duo/setup-bot-match ...');
  const setup = await postJson(`${BACKEND_URL}/__test/duo/setup-bot-match`, {});
  if (setup.status !== 200 || !setup.body || setup.body.success !== true) {
    fail('Setup bot match failed', setup);
  }
  const introUrl = setup.body.intro_url;
  const questionUrl = setup.body.question_url;
  const lobbyCode = setup.body.lobby_code;
  if (!introUrl || !questionUrl || !lobbyCode) {
    fail('Setup response missing intro_url / question_url / lobby_code', setup.body);
  }
  const lobbyUrl = `/duo/lobby/${lobbyCode}`;
  pass(`Match #${setup.body.match_id} ready — lobby=${lobbyCode} room=${setup.body.room_id}`);

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

  // Inject the Laravel session cookies we collected from the HTTP login.
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

  // ---------- step 4: navigate the actual UI flow ----------
  // Path the user actually walks: /game/duo/intro is what the lobby redirects
  // into once both players are ready (the bot in our seeded match is already
  // ready). Both pages instantiate GameplayRuntime, which is the surface that
  // emits join_room. This is exactly where the regression manifested.
  const toAbs = (u) => (/^https?:\/\//i.test(u) ? u : `${BACKEND_URL}${u.startsWith('/') ? '' : '/'}${u}`);

  // NOTE: we cannot wait for `networkidle` — socket.io keeps a persistent
  // connection so the network is never idle. Use `load` and then sleep to
  // give the lobby/GameplayRuntime time to handshake + join_room + receive
  // state.

  // First exercise the lobby page itself — it is the entry point users
  // actually walk through before /game/duo/intro and it also instantiates
  // socket.io. Catching a join_room regression here too widens our coverage
  // of the user journey beyond the gameplay pages alone.
  info(`Navigating to ${toAbs(lobbyUrl)} ...`);
  await page.goto(toAbs(lobbyUrl), { waitUntil: 'load', timeout: TIMEOUT_MS });
  await page.waitForTimeout(3000);

  info(`Navigating to ${toAbs(introUrl)} ...`);
  await page.goto(toAbs(introUrl), { waitUntil: 'load', timeout: TIMEOUT_MS });
  await page.waitForTimeout(5000);

  info(`Navigating to ${toAbs(questionUrl)} ...`);
  await page.goto(toAbs(questionUrl), { waitUntil: 'load', timeout: TIMEOUT_MS });
  await page.waitForTimeout(5000);

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

  // Surface page errors as failures too — those would also indicate a broken
  // GameplayRuntime even if no specific forbidden text was logged.
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
      + 'the join_room round-trip never completed. This is the regression.',
      { totalConsoleMessages: consoleMessages.length, sample: consoleMessages.slice(-15) }
    );
  }

  pass(`Observed positive signal: "${joined.text}" on ${joined.at}`);
  pass(`Captured ${consoleMessages.length} console messages — none matched forbidden patterns`);
  console.log('\n[OK] tests/e2e/duo-join-room.browser.spec.js — all assertions passed');
  process.exit(0);
}

main().catch((err) => {
  fail('Uncaught error in test runner', err && (err.stack || err.message || err));
});
