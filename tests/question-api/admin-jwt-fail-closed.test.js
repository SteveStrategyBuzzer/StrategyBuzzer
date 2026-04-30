#!/usr/bin/env node
/**
 * Task #112 — Cover the fail-closed 503 branch of `requireAdminJwt`.
 *
 * The sister suite (`tests/question-api/admin-jwt.test.js`) sets a strong
 * QUESTION_API_JWT_SECRET *before* requiring question-api.js, so it can
 * never exercise the 503 `admin_jwt_not_configured` path that fires when
 * the secret is missing or weaker than 16 chars. We must cover that path
 * because a regression there would re-open the AI router on any worker
 * deployed without auth material — exactly the failure mode the guard
 * exists to prevent.
 *
 * Two complementary checks live here:
 *
 *   1. `resolveAdminJwtSecret(env)` — the pure helper extracted from the
 *      module-load IIFE — is exercised against every "rejected" shape we
 *      care about (missing, blank, < 16 chars, base64 of < 16 chars) and
 *      every "accepted" shape (valid raw, valid base64, GAME_SERVER_JWT_SECRET
 *      fallback). This is what guarantees the secret never silently
 *      degrades to "" because of a typo in the resolver.
 *
 *   2. The middleware itself is invoked in this process. Because we
 *      delete both env vars BEFORE the first `require('../../question-api')`,
 *      the module-load IIFE produces ADMIN_JWT_SECRET === '' and
 *      `requireAdminJwt` MUST respond 503 / `admin_jwt_not_configured`.
 *
 * Node's --test runner spawns each test file in its own subprocess, so
 * the env scrub here cannot pollute the sister suite (and vice versa).
 *
 * Run with:
 *   node --test tests/question-api/admin-jwt-fail-closed.test.js
 */

'use strict';

// Scrub BEFORE require() so the module-load IIFE sees no secret material.
// Both the primary var and the fallback must be cleared, otherwise the
// fail-closed branch never fires.
delete process.env.QUESTION_API_JWT_SECRET;
delete process.env.GAME_SERVER_JWT_SECRET;

const test = require('node:test');
const assert = require('node:assert/strict');

const { resolveAdminJwtSecret, requireAdminJwt, ADMIN_JWT_SECRET } =
  require('../../question-api').__test;

function makeReq() {
  return {
    path: '/generate-master-question',
    originalUrl: '/generate-master-question',
    header() { return undefined; },
  };
}

function makeRes() {
  return {
    statusCode: null,
    body: null,
    status(code) { this.statusCode = code; return this; },
    json(payload) { this.body = payload; return this; },
  };
}

test('module-load resolver returned "" with both env vars unset', () => {
  // Sanity check: the IIFE captured at require() time saw a scrubbed env
  // and therefore stored an empty secret. If this ever flips to non-empty
  // the rest of the suite is meaningless, so fail loud here.
  assert.equal(ADMIN_JWT_SECRET, '',
    'ADMIN_JWT_SECRET must be empty when both env vars are unset at module load');
});

test('requireAdminJwt fail-closes with 503 when no secret is configured', () => {
  const res = makeRes();
  let nextCalled = false;
  requireAdminJwt(makeReq(), res, () => { nextCalled = true; });

  assert.equal(nextCalled, false,
    'next() must NOT be invoked when the admin JWT secret is missing');
  assert.equal(res.statusCode, 503,
    'response status must be 503 (Service Unavailable) on fail-closed');
  assert.equal(res.body && res.body.success, false);
  assert.equal(res.body && res.body.error, 'admin_jwt_not_configured');
  assert.match(res.body.details || '', /QUESTION_API_JWT_SECRET/);
});

test('resolveAdminJwtSecret returns "" when env is undefined', () => {
  assert.equal(resolveAdminJwtSecret(undefined), '');
  assert.equal(resolveAdminJwtSecret(null), '');
});

test('resolveAdminJwtSecret returns "" when neither env var is set', () => {
  assert.equal(resolveAdminJwtSecret({}), '');
});

test('resolveAdminJwtSecret returns "" when both vars are empty strings', () => {
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: '',
    GAME_SERVER_JWT_SECRET: '',
  }), '');
});

test('resolveAdminJwtSecret rejects a primary secret shorter than 16 chars', () => {
  // 15 chars — one shy of the floor — must NOT be accepted, and there is
  // no fallback configured, so the resolver returns "".
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: 'short-15-chars!',
  }), '');
});

test('resolveAdminJwtSecret rejects a base64 secret whose decoded value is too short', () => {
  // base64("tiny") -> "dGlueQ==" -> decodes to 4 chars, well under 16.
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: 'base64:dGlueQ==',
  }), '');
});

test('resolveAdminJwtSecret rejects a whitespace-only secret', () => {
  // 20 spaces — length passes the raw check, but `.trim().length` is 0,
  // so the resolver must reject it.
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: '                    ',
  }), '');
});

test('resolveAdminJwtSecret accepts a strong primary secret', () => {
  const strong = 'unit-test-secret-that-is-long-enough-32+';
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: strong,
  }), strong);
});

test('resolveAdminJwtSecret accepts a base64 secret that decodes to >= 16 chars', () => {
  const decoded = 'unit-test-secret-32-chars-long!!';
  const encoded = 'base64:' + Buffer.from(decoded, 'utf8').toString('base64');
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: encoded,
  }), decoded);
});

test('resolveAdminJwtSecret falls back to GAME_SERVER_JWT_SECRET when primary is unusable', () => {
  // Primary is too short -> skipped. Fallback is strong -> used.
  const fallback = 'fallback-secret-that-is-long-enough-32+';
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: 'too-short',
    GAME_SERVER_JWT_SECRET: fallback,
  }), fallback);
});

test('resolveAdminJwtSecret prefers the primary over a usable fallback', () => {
  // Both are strong; the primary must win because it appears first in
  // the candidates array.
  const primary = 'primary-secret-that-is-long-enough-32+';
  const fallback = 'fallback-secret-that-is-long-enough-32+';
  assert.equal(resolveAdminJwtSecret({
    QUESTION_API_JWT_SECRET: primary,
    GAME_SERVER_JWT_SECRET: fallback,
  }), primary);
});
