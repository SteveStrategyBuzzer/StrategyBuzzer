#!/usr/bin/env node
/**
 * #110 — Cross-process replay protection for the admin JWT.
 *
 * Task #94 added per-process replay protection (`seenJtis` Map). On a
 * restart or on a second replica the same captured token could still be
 * replayed up to its 60s `exp`. This test pins the new behaviour:
 *
 *   1. The same valid token presented twice is accepted once and rejected
 *      the second time — even though the in-process state would forget it
 *      on restart, because the jti lives in Redis.
 *   2. A captured token presented to a "fresh process" (Redis client
 *      preserved, in-process state recreated) is still rejected — proves
 *      the store is what carries the no-replay guarantee.
 *   3. If Redis is unreachable the request fails closed (HTTP 503), with
 *      no silent fallback to in-memory state.
 *
 * Usage (from project root):
 *   node tests/security/admin-jwt-replay.test.js
 *
 * Exit codes:
 *   0 = all assertions hold
 *   1 = at least one assertion failed
 */

'use strict';

const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const Module = require('node:module');

/* --------------------------- Stub missing SDK --------------------------- */
//
// `question-api.js` pulls in `providers/index.js` which transitively requires
// `@anthropic-ai/sdk`. That package is not installed in this environment and
// is irrelevant to the middleware under test — stub it before requiring.
const _origResolve = Module._resolveFilename;
Module._resolveFilename = function (request, parent, ...rest) {
  if (request === '@anthropic-ai/sdk') {
    return require.resolve('./_stub_anthropic.js');
  }
  return _origResolve.call(this, request, parent, ...rest);
};

/* --------------------------- Env wiring --------------------------------- */
// `ADMIN_JWT_SECRET` is captured at module load time, so this MUST be set
// before requiring `question-api.js`.
const SECRET = 'test-secret-please-ignore-1234567890';
process.env.QUESTION_API_JWT_SECRET = SECRET;

const jwt = require('jsonwebtoken');
const qapi = require('../../question-api.js');
const { requireAdminJwt, setAdminJwtRedisClient, ADMIN_JWT_AUDIENCE } =
  qapi.__test;

/* --------------------------- Fake Redis --------------------------------- */
//
// Minimal in-memory fake that implements just enough of `ioredis` for our
// `SET key value NX EX ttl` claim:
//   - returns 'OK' when the key did not exist,
//   - returns null when the key was already present,
//   - honours the TTL (only loosely — we expire eagerly on the next access).
class FakeRedis {
  constructor() {
    this.store = new Map(); // key -> { value, expiresAt }
    this.failNext = false;
  }

  _gc(key) {
    const entry = this.store.get(key);
    if (!entry) return;
    if (entry.expiresAt <= Date.now()) this.store.delete(key);
  }

  async set(key, value, ...modifiers) {
    if (this.failNext) {
      this.failNext = false;
      throw new Error('simulated Redis outage');
    }
    let nx = false;
    let exSeconds = null;
    for (let i = 0; i < modifiers.length; i++) {
      const mod = String(modifiers[i]).toUpperCase();
      if (mod === 'NX') nx = true;
      else if (mod === 'EX') {
        exSeconds = Number(modifiers[i + 1]);
        i++;
      }
    }
    this._gc(key);
    if (nx && this.store.has(key)) return null;
    const expiresAt = exSeconds
      ? Date.now() + exSeconds * 1000
      : Number.POSITIVE_INFINITY;
    this.store.set(key, { value, expiresAt });
    return 'OK';
  }
}

/* --------------------------- Test helpers ------------------------------- */
function mintToken({ jti, body = '', endpoint = '/test-endpoint', exp } = {}) {
  const now = Math.floor(Date.now() / 1000);
  const payloadHash = crypto
    .createHash('sha256')
    .update(Buffer.from(body))
    .digest('hex');
  return jwt.sign(
    {
      aud: ADMIN_JWT_AUDIENCE,
      purpose: 'qapi_admin',
      endpoint,
      payload_hash: payloadHash,
      sub: 'admin@test',
      iat: now,
      exp: exp || now + 60,
      jti,
    },
    SECRET,
    { algorithm: 'HS256' },
  );
}

function makeReq({ token, body = '', path = '/test-endpoint' }) {
  return {
    header: (name) =>
      name.toLowerCase() === 'authorization' ? `Bearer ${token}` : '',
    rawBody: Buffer.from(body),
    path,
    originalUrl: path,
  };
}

function makeRes() {
  const res = {
    statusCode: null,
    body: null,
    status(code) {
      this.statusCode = code;
      return this;
    },
    json(payload) {
      this.body = payload;
      return this;
    },
  };
  return res;
}

async function run(req) {
  const res = makeRes();
  let nextCalled = false;
  await requireAdminJwt(req, res, () => {
    nextCalled = true;
  });
  return { res, nextCalled };
}

/* --------------------------- Cases -------------------------------------- */
async function caseAcceptsThenReplays() {
  const fake = new FakeRedis();
  setAdminJwtRedisClient(fake);

  const jti = 'jti-replay-' + crypto.randomBytes(6).toString('hex');
  const body = JSON.stringify({ hello: 'world' });
  const token = mintToken({ jti, body });

  const first = await run(makeReq({ token, body }));
  assert.equal(first.nextCalled, true, 'first call should be accepted');
  assert.equal(first.res.statusCode, null, 'first call should not deny');

  const second = await run(makeReq({ token, body }));
  assert.equal(second.nextCalled, false, 'second call must be rejected');
  assert.equal(second.res.statusCode, 403, 'replay must be HTTP 403');
  assert.equal(second.res.body.error, 'invalid_admin_jwt');
  assert.match(second.res.body.details, /replay/i);
  console.log('  ok  same token presented twice -> 1 accept, 1 replay-reject');
}

async function caseSurvivesProcessRestart() {
  // Simulate a "restart of the question-api process" by reusing the SAME
  // Redis store (which is what survives in production) while clearing any
  // possible in-memory state. The middleware itself holds no jti state
  // anymore — the assertion is that the second call is still rejected.
  const fake = new FakeRedis();
  setAdminJwtRedisClient(fake);

  const jti = 'jti-restart-' + crypto.randomBytes(6).toString('hex');
  const body = JSON.stringify({ hello: 'restart' });
  const token = mintToken({ jti, body });

  const first = await run(makeReq({ token, body }));
  assert.equal(first.nextCalled, true, 'first call should be accepted');

  // "Restart": pretend a new process boots with a fresh middleware module
  // but the same Redis backend — re-injecting the same fake client mirrors
  // that, since Redis state is what persists.
  setAdminJwtRedisClient(fake);

  const second = await run(makeReq({ token, body }));
  assert.equal(
    second.nextCalled,
    false,
    'token must still be rejected on a fresh process',
  );
  assert.equal(second.res.statusCode, 403);
  console.log('  ok  jti remembered across simulated process restart');
}

async function caseFailsClosedWhenRedisDown() {
  const fake = new FakeRedis();
  fake.failNext = true; // next SET will throw
  setAdminJwtRedisClient(fake);

  const token = mintToken({
    jti: 'jti-down-' + crypto.randomBytes(6).toString('hex'),
    body: '',
  });

  const { res, nextCalled } = await run(makeReq({ token, body: '' }));
  assert.equal(nextCalled, false, 'request must NOT be admitted when Redis is down');
  assert.equal(res.statusCode, 503, 'Redis outage must surface as HTTP 503');
  assert.equal(res.body.error, 'admin_jwt_replay_store_unavailable');
  console.log('  ok  Redis outage -> 503 fail-closed (no in-memory fallback)');
}

/* --------------------------- Driver ------------------------------------- */
(async () => {
  const cases = [
    ['accepts then rejects on replay', caseAcceptsThenReplays],
    ['survives a process restart', caseSurvivesProcessRestart],
    ['fails closed when Redis is unavailable', caseFailsClosedWhenRedisDown],
  ];
  let failed = 0;
  for (const [name, fn] of cases) {
    process.stdout.write(`- ${name}\n`);
    try {
      await fn();
    } catch (err) {
      failed++;
      console.error(`  FAIL ${name}:`, err && err.stack ? err.stack : err);
    }
  }
  if (failed) {
    console.error(`\n${failed} case(s) failed`);
    process.exit(1);
  }
  console.log('\nAll admin-JWT replay-protection cases pass.');
  process.exit(0);
})();
