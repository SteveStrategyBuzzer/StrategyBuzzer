#!/usr/bin/env node
/**
 * Task #111 — Direct unit coverage for `requireAdminJwt` in question-api.js.
 *
 * The PHP side (tests/Feature/QuestionApiClientAdminAuthTest.php) already
 * proves the Laravel client mints contract-compliant tokens. This suite
 * locks down the *Node* middleware: every rejection branch is exercised so
 * a future change that loosens any check (audience, purpose, endpoint,
 * payload_hash, lifetime, jti replay, algorithm, missing header, expired
 * token) is caught before it ships.
 *
 * Run with:
 *   node --test tests/question-api/admin-jwt.test.js
 *
 * The middleware reads its HMAC secret once at module load, so we install
 * the env vars BEFORE requiring question-api.js. require.main !== module
 * keeps the express app from binding port 3000 during the test.
 */

'use strict';

process.env.QUESTION_API_JWT_SECRET = 'unit-test-secret-that-is-long-enough-32+';
delete process.env.GAME_SERVER_JWT_SECRET;

// `question-api.js` transitively requires `@anthropic-ai/sdk`, which is an
// optional dependency that is not always installed. Stub it so the require
// chain loads even on environments without the SDK — the middleware never
// calls into any provider.
const Module = require('node:module');
const _origResolve = Module._resolveFilename;
Module._resolveFilename = function (request, parent, ...rest) {
  if (request === '@anthropic-ai/sdk') {
    return require.resolve('../security/_stub_anthropic.js');
  }
  return _origResolve.call(this, request, parent, ...rest);
};

const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const jwt = require('jsonwebtoken');

const {
  requireAdminJwt,
  ADMIN_JWT_AUDIENCE,
  ADMIN_JWT_PURPOSE,
  ADMIN_JWT_MAX_LIFETIME_SECONDS,
  setAdminJwtRedisClient,
} = require('../../question-api').__test;

// #110 — replay protection now lives in Redis. Inject a tiny in-memory
// fake so this suite stays self-contained and does not need a real
// Redis daemon. The fake implements just `SET key val NX EX <ttl>` —
// 'OK' on first claim, null on every subsequent claim — which is all
// the middleware uses.
class FakeRedis {
  constructor() {
    this.store = new Map();
  }
  async set(key, value, ...modifiers) {
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
    const now = Date.now();
    const existing = this.store.get(key);
    if (existing && existing.expiresAt > now) {
      if (nx) return null;
    }
    const expiresAt = exSeconds ? now + exSeconds * 1000 : Number.POSITIVE_INFINITY;
    this.store.set(key, { value, expiresAt });
    return 'OK';
  }
}
setAdminJwtRedisClient(new FakeRedis());

const SECRET = process.env.QUESTION_API_JWT_SECRET;
const DEFAULT_PATH = '/generate-master-question';

function sha256Hex(input) {
  return crypto.createHash('sha256').update(input).digest('hex');
}

/**
 * Build a fully-valid claim set so individual tests can mutate exactly
 * one field to verify a single rejection path.
 */
function baseClaims({
  path = DEFAULT_PATH,
  body = '',
  now = Math.floor(Date.now() / 1000),
  lifetime = ADMIN_JWT_MAX_LIFETIME_SECONDS,
  jti = crypto.randomUUID(),
} = {}) {
  return {
    aud: ADMIN_JWT_AUDIENCE,
    purpose: ADMIN_JWT_PURPOSE,
    endpoint: path,
    payload_hash: sha256Hex(body),
    sub: 'unit-test-user',
    jti,
    iat: now,
    exp: now + lifetime,
  };
}

function signClaims(claims, { algorithm = 'HS256', secret = SECRET } = {}) {
  // jsonwebtoken preserves explicit iat/exp in the payload when noTimestamp
  // is left at its default (false). Using noTimestamp:true would *strip*
  // iat, which would falsely trip the lifetime check in the middleware.
  return jwt.sign(claims, secret, { algorithm });
}

function makeReq({ token, path = DEFAULT_PATH, body = '' } = {}) {
  const headers = {};
  if (token !== undefined) {
    headers.authorization = `Bearer ${token}`;
  }
  const rawBody = Buffer.from(body, 'utf8');
  return {
    path,
    originalUrl: path,
    rawBody: rawBody.length ? rawBody : undefined,
    header(name) {
      return headers[String(name).toLowerCase()];
    },
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

/**
 * Run the middleware once and return { res, nextCalled }. Tests assert on
 * those rather than calling the middleware directly so we never risk
 * silently swallowing a thrown error.
 */
async function runMiddleware(req) {
  const res = makeRes();
  let nextCalled = false;
  await requireAdminJwt(req, res, () => { nextCalled = true; });
  return { res, nextCalled };
}

test('module loads with a configured secret', async () => {
  assert.equal(typeof requireAdminJwt, 'function');
  assert.equal(ADMIN_JWT_AUDIENCE, 'question-api');
  assert.equal(ADMIN_JWT_PURPOSE, 'qapi_admin');
  assert.equal(ADMIN_JWT_MAX_LIFETIME_SECONDS, 60);
});

test('accepts a fully valid token and calls next()', async () => {
  const body = JSON.stringify({ theme: 'Histoire', language: 'fr' });
  const token = signClaims(baseClaims({ body }));
  const req = makeReq({ token, body });

  const { res, nextCalled } = await runMiddleware(req);

  assert.equal(nextCalled, true, 'next() must be invoked on success');
  assert.equal(res.statusCode, null, 'res.status must not be set on success');
  assert.equal(req.adminCaller.sub, 'unit-test-user');
  assert.equal(req.adminCaller.endpoint, DEFAULT_PATH);
});

test('rejects a request with no Authorization header', async () => {
  const { res, nextCalled } = await runMiddleware(makeReq());

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 401);
  assert.equal(res.body.error, 'missing_admin_jwt');
});

test('rejects a request whose Authorization header is not Bearer', async () => {
  const req = makeReq();
  req.header = (name) =>
    name.toLowerCase() === 'authorization' ? 'Basic Zm9vOmJhcg==' : undefined;

  const { res, nextCalled } = await runMiddleware(req);

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 401);
  assert.equal(res.body.error, 'missing_admin_jwt');
});

test('rejects a token signed with a non-HS256 algorithm', async () => {
  const body = '';
  // HS512 still verifies as a valid HMAC, but the middleware pins
  // algorithms: ['HS256'] so verify() must throw.
  const token = signClaims(baseClaims({ body }), { algorithm: 'HS512' });

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
});

test('rejects a token with the wrong audience', async () => {
  const body = '';
  const claims = baseClaims({ body });
  claims.aud = 'some-other-service';
  const token = signClaims(claims);

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
  assert.match(res.body.details, /audience|aud/i);
});

test('rejects a token with the wrong purpose', async () => {
  const body = '';
  const claims = baseClaims({ body });
  claims.purpose = 'qapi_user';
  const token = signClaims(claims);

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
  assert.match(res.body.details, /purpose/);
});

test('rejects a token whose endpoint claim does not match the request path', async () => {
  const body = '';
  const claims = baseClaims({ body, path: '/some-other-endpoint' });
  const token = signClaims(claims);

  const { res, nextCalled } = await runMiddleware(
    makeReq({ token, body, path: DEFAULT_PATH })
  );

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
  assert.match(res.body.details, /endpoint/);
});

test('rejects a token whose payload_hash does not match sha256(body)', async () => {
  const body = JSON.stringify({ theme: 'Histoire' });
  const claims = baseClaims({ body });
  // Sign with a hash for a different body — token is otherwise pristine.
  claims.payload_hash = sha256Hex('a-different-body');
  const token = signClaims(claims);

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
  assert.match(res.body.details, /payload_hash/);
});

test('rejects a token whose exp is in the past', async () => {
  const body = '';
  const now = Math.floor(Date.now() / 1000);
  // 5 minutes ago, well outside the 5s clock-skew tolerance.
  const claims = baseClaims({ body, now: now - 600, lifetime: 60 });
  const token = signClaims(claims);

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
  assert.match(res.body.details, /jwt expired|expired/i);
});

test('rejects a token whose lifetime exceeds 60s + skew', async () => {
  const body = '';
  // Lifetime well past 60 + 5 — but iat/exp are both in the future-ish
  // range so jwt.verify itself does not flag it; the middleware's own
  // ceiling is what must fire.
  const claims = baseClaims({ body, lifetime: 600 });
  const token = signClaims(claims);

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
  assert.match(res.body.details, /lifetime/);
});

test('rejects a replayed jti', async () => {
  const body = '';
  const claims = baseClaims({ body });
  const token = signClaims(claims);
  const req1 = makeReq({ token, body });
  const req2 = makeReq({ token, body });

  const first = await runMiddleware(req1);
  assert.equal(first.nextCalled, true, 'first use of token must succeed');

  const second = await runMiddleware(req2);
  assert.equal(second.nextCalled, false);
  assert.equal(second.res.statusCode, 403);
  assert.equal(second.res.body.error, 'invalid_admin_jwt');
  assert.match(second.res.body.details, /jti|replay/i);
});

test('rejects a token signed with a different secret', async () => {
  const body = '';
  const claims = baseClaims({ body });
  const token = signClaims(claims, { secret: 'a-completely-different-secret-32+' });

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
});

test('rejects a token missing the jti claim', async () => {
  const body = '';
  const claims = baseClaims({ body });
  delete claims.jti;
  const token = signClaims(claims);

  const { res, nextCalled } = await runMiddleware(makeReq({ token, body }));

  assert.equal(nextCalled, false);
  assert.equal(res.statusCode, 403);
  assert.equal(res.body.error, 'invalid_admin_jwt');
  assert.match(res.body.details, /jti/);
});
