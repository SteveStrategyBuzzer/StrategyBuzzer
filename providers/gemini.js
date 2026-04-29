const { GoogleGenAI } = require('@google/genai');

const NAME = 'gemini';
const DEFAULT_MODEL = 'gemini-2.0-flash';

const clientCache = new Map();

function getClient(apiKey) {
  if (!clientCache.has(apiKey)) {
    clientCache.set(apiKey, new GoogleGenAI({ apiKey }));
  }
  return clientCache.get(apiKey);
}

function extractText(response) {
  if (response?.candidates?.[0]?.content?.parts) {
    return response.candidates[0].content.parts.map(p => p.text || '').join('');
  }
  if (response?.text) return response.text;
  if (typeof response === 'string') return response;
  return '';
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

async function call({ apiKey, systemPrompt, userPrompt, temperature, maxOutputTokens, responseMimeType, model }) {
  const client = getClient(apiKey);
  const fullPrompt = systemPrompt ? `${systemPrompt}\n\n${userPrompt}` : userPrompt;
  const config = {
    temperature: typeof temperature === 'number' ? temperature : 1.0,
    maxOutputTokens: maxOutputTokens || 800,
  };
  if (responseMimeType) config.responseMimeType = responseMimeType;

  const response = await client.models.generateContent({
    model: model || DEFAULT_MODEL,
    contents: [{ role: 'user', parts: [{ text: fullPrompt }] }],
    config,
  });

  const text = extractText(response);
  if (!text) {
    const err = new Error('gemini: empty response');
    err.status = 502;
    throw err;
  }
  return text;
}

module.exports = {
  name: NAME,
  envKeysVar: 'GEMINI_API_KEYS',
  envKeyVar: 'GEMINI_API_KEY',
  call,
  classifyError,
  defaultModel: DEFAULT_MODEL,
};
