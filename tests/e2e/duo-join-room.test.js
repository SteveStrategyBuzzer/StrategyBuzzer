#!/usr/bin/env node
/**
 * E2E regression test for the multiplayer (Duo) join_room flow.
 *
 * This test guards against the "VALIDATION_ERROR: Invalid join_room payload"
 * regression that broke multiplayer in production. It runs entirely
 * server-side (no browser required) and is therefore CI-friendly.
 *
 * Flow:
 *   1. POST /__test/login                      -> log in fixture user E2E-0001
 *   2. POST /__test/duo/setup-bot-match        -> create Duo match vs bot
 *      Response: { match_id, lobby_code, room_id, jwt_token, ... }
 *   3. Connect to the Game Server with that JWT via socket.io-client
 *   4. Emit `join_room` with the SAME payload shape the browser sends
 *      (the regression was caused by a payload-shape mismatch)
 *   5. Assert:
 *        - We receive a room/state event from the server
 *        - We receive ZERO `error` events containing
 *          VALIDATION_ERROR / Invalid join_room / Cannot join_room
 *
 * Usage (from project root):
 *   node tests/e2e/duo-join-room.test.js
 *
 * Required services (must be running):
 *   - Backend         (php :5000) — Laravel app with __test/* routes enabled
 *   - Game Server     (node :3001)
 *   - Redis Server    (:6379)
 *
 * Exit codes:
 *   0 = pass (no regression)
 *   1 = fail (regression detected, or setup failed)
 *
 * NOTE: APP_ENV must NOT be 'production' — the /__test/* routes are gated.
 */

const http = require('http');
const { io } = require('socket.io-client');

const BACKEND_URL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:5000';
const GAME_SERVER_URL = process.env.E2E_GAME_SERVER_URL || 'http://127.0.0.1:3001';
const TIMEOUT_MS = parseInt(process.env.E2E_TIMEOUT_MS || '15000', 10);

const FORBIDDEN_PATTERNS = [
  /VALIDATION_ERROR/i,
  /Invalid join_room/i,
  /Cannot join_room/i,
];

let cookieJar = '';

function fail(msg, detail) {
  console.error('\n[FAIL]', msg);
  if (detail !== undefined) console.error(detail);
  process.exit(1);
}

function pass(msg) {
  console.log('[PASS]', msg);
}

function info(msg) {
  console.log('[INFO]', msg);
}

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
          // capture ALL Set-Cookie values (Laravel sends session + XSRF)
          const setCookie = res.headers['set-cookie'];
          if (setCookie && setCookie.length) {
            const parsed = setCookie
              .map((c) => c.split(';')[0])
              .join('; ');
            cookieJar = cookieJar
              ? `${cookieJar}; ${parsed}`
              : parsed;
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
  info(`Backend     = ${BACKEND_URL}`);
  info(`Game Server = ${GAME_SERVER_URL}`);

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
  const { room_id: roomId, jwt_token: jwt, match_id: matchId, lobby_code: lobbyCode } = setup.body;
  if (!roomId || !jwt) {
    fail('Setup response missing room_id or jwt_token', setup.body);
  }
  pass(`Match #${matchId} created — lobby=${lobbyCode} room=${roomId}`);

  // ---------- step 3: connect socket + join_room ----------
  info('Connecting socket.io-client to Game Server ...');
  const socket = io(GAME_SERVER_URL, {
    transports: ['websocket', 'polling'],
    auth: { token: jwt },
    reconnection: false,
    timeout: TIMEOUT_MS,
  });

  const errorMessages = [];
  let joined = false;
  let stateReceived = false;

  socket.on('error', (payload) => {
    const text = typeof payload === 'string' ? payload : JSON.stringify(payload);
    errorMessages.push({ event: 'error', text });
  });
  socket.on('error_message', (payload) => {
    const text = typeof payload === 'string' ? payload : JSON.stringify(payload);
    errorMessages.push({ event: 'error_message', text });
  });
  socket.on('room_error', (payload) => {
    const text = typeof payload === 'string' ? payload : JSON.stringify(payload);
    errorMessages.push({ event: 'room_error', text });
  });
  socket.on('disconnect', (reason) => {
    info(`socket disconnect: ${reason}`);
  });

  // Match the exact payload shape the browser GameplayRuntime sends.
  // The historical regression was a shape mismatch here.
  const joinPayload = {
    roomId: roomId,
    playerId: String(login.body.user_id),
    playerName: login.body.player_code || 'E2E Test User',
  };

  await new Promise((resolve, reject) => {
    const t = setTimeout(
      () => reject(new Error(`Timed out waiting for connect (${TIMEOUT_MS}ms)`)),
      TIMEOUT_MS
    );
    socket.once('connect', () => { clearTimeout(t); resolve(); });
    socket.once('connect_error', (err) => { clearTimeout(t); reject(err); });
  });
  pass(`Socket connected (id=${socket.id})`);

  // any server "state" / "room_state" / "joined" event counts as success
  ['state', 'room_state', 'joined', 'room_joined', 'phase'].forEach((evt) => {
    socket.on(evt, () => {
      if (!stateReceived) {
        stateReceived = true;
        joined = true;
        info(`received "${evt}" event from server`);
      }
    });
  });

  info(`Emitting join_room with payload ${JSON.stringify(joinPayload)} ...`);
  socket.emit('join_room', joinPayload);

  // wait until either we get a state event OR an error event OR timeout
  const deadline = Date.now() + TIMEOUT_MS;
  while (Date.now() < deadline && !stateReceived && errorMessages.length === 0) {
    await new Promise((r) => setTimeout(r, 100));
  }

  socket.disconnect();

  // ---------- step 4: assert no regression ----------
  const offending = errorMessages.filter((m) =>
    FORBIDDEN_PATTERNS.some((re) => re.test(m.text))
  );
  if (offending.length > 0) {
    fail(
      `REGRESSION DETECTED — ${offending.length} forbidden error message(s) from server:`,
      offending
    );
  }

  if (errorMessages.length > 0) {
    info(`(non-fatal) server emitted ${errorMessages.length} other error event(s):`);
    errorMessages.forEach((m) => info(`  - ${m.event}: ${m.text}`));
  }

  if (!joined) {
    fail('join_room did not produce a state/joined event within the timeout', {
      roomId,
      payload: joinPayload,
    });
  }

  pass('join_room contract upheld — no VALIDATION_ERROR / Invalid join_room / Cannot join_room');
  console.log('\n[OK] tests/e2e/duo-join-room.test.js — all assertions passed');
  process.exit(0);
}

main().catch((err) => {
  fail('Uncaught error in test runner', err && (err.stack || err.message || err));
});
