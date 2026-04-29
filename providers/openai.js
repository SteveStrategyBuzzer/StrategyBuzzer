const OpenAI = require('openai');

const NAME = 'openai';
const DEFAULT_MODEL = 'gpt-4o-mini';

const clientCache = new Map();

function getClient(apiKey) {
  if (!clientCache.has(apiKey)) {
    clientCache.set(apiKey, new OpenAI({ apiKey }));
  }
  return clientCache.get(apiKey);
}

function classifyError(err) {
  const status =
    err?.status ||
    err?.statusCode ||
    err?.response?.status ||
    null;
  return { status, message: err?.message || String(err) };
}

async function call({ apiKey, systemPrompt, userPrompt, temperature, maxOutputTokens, responseMimeType, model }) {
  const client = getClient(apiKey);

  const messages = [];
  if (systemPrompt) messages.push({ role: 'system', content: systemPrompt });
  messages.push({ role: 'user', content: userPrompt });

  const params = {
    model: model || DEFAULT_MODEL,
    messages,
    temperature: typeof temperature === 'number' ? temperature : 1.0,
    max_tokens: maxOutputTokens || 800,
  };

  if (responseMimeType === 'application/json') {
    params.response_format = { type: 'json_object' };
    if (!systemPrompt || !/json/i.test(systemPrompt)) {
      const guard =
        'You MUST reply with a single JSON object. No prose, no markdown fences. Output valid JSON only.';
      params.messages = [
        { role: 'system', content: systemPrompt ? `${systemPrompt}\n\n${guard}` : guard },
        { role: 'user', content: userPrompt },
      ];
    }
  }

  const completion = await client.chat.completions.create(params);
  const text = completion?.choices?.[0]?.message?.content || '';
  if (!text) {
    const err = new Error('openai: empty response');
    err.status = 502;
    throw err;
  }
  return text;
}

module.exports = {
  name: NAME,
  envKeysVar: 'OPENAI_API_KEYS',
  envKeyVar: 'OPENAI_API_KEY',
  call,
  classifyError,
  defaultModel: DEFAULT_MODEL,
};
