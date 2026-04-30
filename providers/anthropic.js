const Anthropic = require('@anthropic-ai/sdk');

const NAME = 'anthropic';
const DEFAULT_MODEL = 'claude-haiku-4-5';

const clientCache = new Map();

function getClient(apiKey) {
  if (!clientCache.has(apiKey)) {
    clientCache.set(apiKey, new Anthropic({ apiKey }));
  }
  return clientCache.get(apiKey);
}

function classifyError(err) {
  const msg = err?.message || String(err);
  const status =
    err?.status ||
    err?.statusCode ||
    err?.response?.status ||
    (msg.match(/\b(429|503|504|500|401|403)\b/) ? Number(msg.match(/\b(429|503|504|500|401|403)\b/)[1]) : null);
  return { status, message: msg };
}

function extractText(response) {
  const blocks = response?.content;
  if (!Array.isArray(blocks)) return '';
  return blocks
    .filter(b => b && b.type === 'text')
    .map(b => b.text || '')
    .join('');
}

async function call({ apiKey, systemPrompt, userPrompt, temperature, maxOutputTokens, responseMimeType, model }) {
  const client = getClient(apiKey);

  let sysPrompt = systemPrompt || '';
  if (responseMimeType === 'application/json') {
    if (!/json/i.test(sysPrompt)) {
      const guard =
        'You MUST reply with a single JSON object. No prose, no markdown fences. Output valid JSON only.';
      sysPrompt = sysPrompt ? `${sysPrompt}\n\n${guard}` : guard;
    }
  }

  const params = {
    model: model || DEFAULT_MODEL,
    max_tokens: maxOutputTokens || 800,
    temperature: typeof temperature === 'number' ? temperature : 1.0,
    messages: [{ role: 'user', content: userPrompt }],
  };
  if (sysPrompt) params.system = sysPrompt;

  const response = await client.messages.create(params);
  const text = extractText(response);
  if (!text) {
    const err = new Error('anthropic: empty response');
    err.status = 502;
    throw err;
  }
  return text;
}

module.exports = {
  name: NAME,
  envKeysVar: 'ANTHROPIC_API_KEYS',
  envKeyVar: 'ANTHROPIC_API_KEY',
  call,
  classifyError,
  defaultModel: DEFAULT_MODEL,
};
