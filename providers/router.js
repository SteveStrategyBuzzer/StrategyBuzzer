/**
 * Multi-provider, multi-key AI router (bank refill only).
 *
 * - Each provider is configured with one or more API keys.
 * - Round-robin between non-quarantined keys of the active provider.
 * - On 429 (rate limit) → quarantine the offending key locally for
 *   AI_QUARANTINE_SECONDS (default 60s), then try the next key.
 * - When every key of the active provider is exhausted, failover to
 *   the next provider in AI_PROVIDER_ORDER (default: gemini,openai).
 * - When all providers fail, generate() throws so the caller (worker)
 *   can apply its own back-off.
 *
 * Per-process state. Each Question API instance manages its own
 * quarantine and rotation counters.
 */

const geminiProvider = require('./gemini');
const openaiProvider = require('./openai');

const PROVIDERS = {
  [geminiProvider.name]: geminiProvider,
  [openaiProvider.name]: openaiProvider,
};

function parseKeys(provider) {
  const list = process.env[provider.envKeysVar];
  if (list && list.trim()) {
    return list
      .split(',')
      .map(k => k.trim())
      .filter(Boolean);
  }
  const single = process.env[provider.envKeyVar];
  if (single && single.trim()) {
    return [single.trim()];
  }
  return [];
}

function parseOrder() {
  const raw = (process.env.AI_PROVIDER_ORDER || 'gemini,openai').toLowerCase();
  return raw
    .split(',')
    .map(s => s.trim())
    .filter(name => PROVIDERS[name]);
}

const QUARANTINE_MS = (() => {
  const seconds = Number(process.env.AI_QUARANTINE_SECONDS);
  return Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : 60_000;
})();

/* ----------------------------- Internal state ---------------------------- */

const state = {
  providers: {},        // name → { keys, rrIndex, quarantine: Map<keyIndex, expiresAt>, stats: [] }
  order: [],
  lastFailover: null,   // { from, to, reason, ts }
  lastReject: null,     // { provider, keyIndex, reason, sample, ts }
};

function init() {
  const order = parseOrder();
  state.order = order;
  for (const name of Object.keys(PROVIDERS)) {
    const keys = parseKeys(PROVIDERS[name]);
    state.providers[name] = {
      keys,
      rrIndex: 0,
      quarantine: new Map(),
      events: [], // { ts, ok, latencyMs, status }
    };
  }
}

init();

/* --------------------------------- Stats --------------------------------- */

function recordEvent(providerName, ok, latencyMs, status) {
  const slot = state.providers[providerName];
  if (!slot) return;
  const now = Date.now();
  slot.events.push({ ts: now, ok: !!ok, latencyMs: latencyMs || 0, status: status || null });
  // Keep at most last 1h.
  const cutoff = now - 60 * 60 * 1000;
  while (slot.events.length && slot.events[0].ts < cutoff) {
    slot.events.shift();
  }
}

function summariseStats(slot) {
  const now = Date.now();
  const cutoff = now - 60 * 60 * 1000;
  const events = slot.events.filter(e => e.ts >= cutoff);
  const ok = events.filter(e => e.ok).length;
  const fail = events.length - ok;
  const totalLatency = events.reduce((acc, e) => acc + (e.latencyMs || 0), 0);
  const avgLatency = events.length ? Math.round(totalLatency / events.length) : 0;
  return { calls_1h: events.length, ok_1h: ok, fail_1h: fail, avg_latency_ms_1h: avgLatency };
}

/* ------------------------------ Quarantine ------------------------------- */

function isQuarantined(providerName, keyIndex) {
  const slot = state.providers[providerName];
  if (!slot) return true;
  const expiry = slot.quarantine.get(keyIndex);
  if (!expiry) return false;
  if (Date.now() >= expiry) {
    slot.quarantine.delete(keyIndex);
    return false;
  }
  return true;
}

function quarantine(providerName, keyIndex, reason = '429') {
  const slot = state.providers[providerName];
  if (!slot) return;
  const expiresAt = Date.now() + QUARANTINE_MS;
  slot.quarantine.set(keyIndex, expiresAt);
  console.log(
    `[provider:${providerName} key:#${keyIndex + 1}] ${reason} quarantined for ${Math.round(QUARANTINE_MS / 1000)}s`
  );
}

/* ----------------------------- Key selection ----------------------------- */

function pickAvailableKey(providerName) {
  const slot = state.providers[providerName];
  if (!slot || slot.keys.length === 0) return null;
  const total = slot.keys.length;
  for (let attempt = 0; attempt < total; attempt++) {
    const idx = (slot.rrIndex + attempt) % total;
    if (!isQuarantined(providerName, idx)) {
      slot.rrIndex = (idx + 1) % total;
      return idx;
    }
  }
  return null;
}

function activeProviderOrder() {
  return state.order.filter(name => {
    const slot = state.providers[name];
    return slot && slot.keys.length > 0;
  });
}

/* ------------------------------ Public API ------------------------------- */

class AllProvidersExhaustedError extends Error {
  constructor(detail) {
    super(`AI router: all providers exhausted (${detail})`);
    this.name = 'AllProvidersExhaustedError';
  }
}

class NoProvidersConfiguredError extends Error {
  constructor() {
    super('AI router: no providers configured (set GEMINI_API_KEY or OPENAI_API_KEY)');
    this.name = 'NoProvidersConfiguredError';
  }
}

/**
 * Generate text using the router. Returns:
 *   { text, provider, keyIndex, latencyMs, validated? }
 * Throws NoProvidersConfiguredError or AllProvidersExhaustedError.
 *
 * options:
 *   - systemPrompt (string)
 *   - userPrompt (string, required)
 *   - temperature, maxOutputTokens, responseMimeType
 *   - model (per-provider; if not set, provider default is used)
 *   - validate(text, ctx) — optional callback. If provided, MUST return
 *     either { ok: true, value? } or { ok: false, reason: string }. A
 *     failed validation is treated as a provider error (router records
 *     a reject and continues to the next key/provider). On success, the
 *     returned `value` is attached as `validated` on the result.
 */
async function generate(options) {
  const order = activeProviderOrder();
  if (order.length === 0) {
    throw new NoProvidersConfiguredError();
  }

  const userPrompt = options.userPrompt || options.prompt;
  if (!userPrompt) {
    throw new Error('AI router: userPrompt is required');
  }

  const errors = [];
  let prevProvider = null;

  for (const providerName of order) {
    const provider = PROVIDERS[providerName];
    const slot = state.providers[providerName];
    if (!provider || !slot || slot.keys.length === 0) continue;

    if (prevProvider && prevProvider !== providerName) {
      const failover = { from: prevProvider, to: providerName, reason: 'all keys exhausted', ts: Date.now() };
      state.lastFailover = failover;
      console.log(`[router] failover ${failover.from} → ${failover.to} (${failover.reason})`);
    }

    let attempts = 0;
    const maxAttempts = slot.keys.length;
    while (attempts < maxAttempts) {
      attempts++;
      const keyIndex = pickAvailableKey(providerName);
      if (keyIndex === null) {
        errors.push({ provider: providerName, error: 'all keys quarantined or unavailable' });
        break;
      }

      const startedAt = Date.now();
      let text;
      try {
        text = await provider.call({
          apiKey: slot.keys[keyIndex],
          systemPrompt: options.systemPrompt,
          userPrompt,
          temperature: options.temperature,
          maxOutputTokens: options.maxOutputTokens,
          responseMimeType: options.responseMimeType,
          model: options.model,
        });
      } catch (err) {
        const latencyMs = Date.now() - startedAt;
        const { status, message } = provider.classifyError(err);
        recordEvent(providerName, false, latencyMs, status);
        const tag = `[provider:${providerName} key:#${keyIndex + 1}]`;
        if (status === 429) {
          console.log(`${tag} 429 ${message}`);
          quarantine(providerName, keyIndex, '429');
          errors.push({ provider: providerName, keyIndex, status, message });
          continue;
        }
        if (status === 401 || status === 403) {
          console.log(`${tag} ${status} auth failure → quarantined for ${Math.round(QUARANTINE_MS / 1000)}s`);
          quarantine(providerName, keyIndex, String(status));
          errors.push({ provider: providerName, keyIndex, status, message });
          continue;
        }
        // 5xx / transport / unknown → log & try another key, but don't quarantine
        console.log(`${tag} error status=${status || 'n/a'} message=${message}`);
        errors.push({ provider: providerName, keyIndex, status, message });
        continue;
      }

      const latencyMs = Date.now() - startedAt;

      // Optional caller-provided validation. A failed validation is a
      // provider error: record a reject and try the next key/provider.
      // This lets the bank-refill endpoint enforce its rich JSON contract
      // inside the router's retry loop instead of after it.
      if (typeof options.validate === 'function') {
        let validation;
        try {
          validation = options.validate(text, { provider: providerName, keyIndex });
        } catch (e) {
          validation = { ok: false, reason: `validate threw: ${e.message}` };
        }
        if (!validation || validation.ok !== true) {
          recordEvent(providerName, false, latencyMs, 'invalid_contract');
          recordReject({
            provider: providerName,
            keyIndex,
            reason: (validation && validation.reason) || 'validation failed',
            sample: text,
          });
          errors.push({
            provider: providerName,
            keyIndex,
            status: 'invalid_contract',
            message: (validation && validation.reason) || 'validation failed',
          });
          continue; // try next key / provider
        }
        recordEvent(providerName, true, latencyMs, 200);
        console.log(`[provider:${providerName} key:#${keyIndex + 1}] success in ${(latencyMs / 1000).toFixed(2)}s`);
        return {
          text,
          provider: providerName,
          keyIndex,
          latencyMs,
          validated: validation.value !== undefined ? validation.value : true,
        };
      }

      recordEvent(providerName, true, latencyMs, 200);
      console.log(`[provider:${providerName} key:#${keyIndex + 1}] success in ${(latencyMs / 1000).toFixed(2)}s`);
      return { text, provider: providerName, keyIndex, latencyMs };
    }

    prevProvider = providerName;
  }

  throw new AllProvidersExhaustedError(JSON.stringify(errors));
}

/**
 * Record that a provider's response failed validation (rich JSON
 * contract). Surfaced via /health and is treated as a provider error
 * by the worker.
 */
function recordReject({ provider, keyIndex, reason, sample }) {
  const truncated = sample ? String(sample).slice(0, 200) : null;
  state.lastReject = {
    provider: provider || 'unknown',
    keyIndex: typeof keyIndex === 'number' ? keyIndex : null,
    reason: String(reason || 'unknown'),
    sample: truncated,
    ts: Date.now(),
  };
  console.log(
    `[router] rejected: ${state.lastReject.reason} (provider=${state.lastReject.provider}, key=#${(state.lastReject.keyIndex ?? 0) + 1})`
  );
}

function getHealth() {
  const order = activeProviderOrder();
  const providersOut = {};
  for (const name of Object.keys(state.providers)) {
    const slot = state.providers[name];
    const quarantined = [];
    for (const [idx, expiresAt] of slot.quarantine.entries()) {
      const ttlMs = expiresAt - Date.now();
      if (ttlMs > 0) {
        quarantined.push({ key_index: idx + 1, ttl_seconds: Math.ceil(ttlMs / 1000) });
      } else {
        slot.quarantine.delete(idx);
      }
    }
    providersOut[name] = {
      configured: slot.keys.length > 0,
      active: order.includes(name),
      keys_total: slot.keys.length,
      keys_available: slot.keys.length - quarantined.length,
      quarantined,
      stats_1h: summariseStats(slot),
    };
  }
  return {
    provider_order: order,
    quarantine_seconds: Math.round(QUARANTINE_MS / 1000),
    providers: providersOut,
    last_failover: state.lastFailover,
    last_reject: state.lastReject,
  };
}

/* --------------------------- Test/admin helpers -------------------------- */

function _resetForTests() {
  init();
  state.lastFailover = null;
  state.lastReject = null;
}

module.exports = {
  generate,
  recordReject,
  getHealth,
  AllProvidersExhaustedError,
  NoProvidersConfiguredError,
  _resetForTests,
};
