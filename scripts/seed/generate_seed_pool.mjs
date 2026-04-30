#!/usr/bin/env node
/**
 * Generate the embedded seed pool used by QuestionService and SeedQuestionPoolService
 * when both the persistent bank (Postgres) and Redis cache are empty
 * (fresh install, post-deploy, brand-new (theme, niveau) segment, worker outage).
 *
 * Live matches NEVER call any AI provider (#88). This script runs at build/dev time
 * only — it produces static JSON files committed to resources/seed/.
 *
 * Output: resources/seed/fallback-questions-{lang}.json
 *
 * Coverage target (#93): for each (language, theme, depth_band) at least N questions
 *   - languages: 10 (fr, en, es, it, de, pt, ru, zh, ar, el)
 *   - themes (canonical domains): 9 (general + 8 sub-domains)
 *   - depth_bands: 4 (3-4, 5-6, 7-8, 9-10)
 *   - N (default): 10
 *
 * Usage:
 *   node scripts/seed/generate_seed_pool.mjs                         # generate everything missing
 *   node scripts/seed/generate_seed_pool.mjs --langs fr,en           # restrict languages
 *   node scripts/seed/generate_seed_pool.mjs --domains general,sport # restrict domains
 *   node scripts/seed/generate_seed_pool.mjs --force                 # regenerate even if a segment is already covered
 *   node scripts/seed/generate_seed_pool.mjs --target 12             # change N
 *   node scripts/seed/generate_seed_pool.mjs --concurrency 8         # parallel OpenAI calls
 *   node scripts/seed/generate_seed_pool.mjs --model gpt-4o-mini     # override model
 */

import fs from 'node:fs';
import path from 'node:path';
import OpenAI from 'openai';

const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..', '..');
const OUT_DIR = path.join(ROOT, 'resources', 'seed');

const LANGUAGES = {
  fr: 'French',
  en: 'English',
  es: 'Spanish',
  it: 'Italian',
  de: 'German',
  pt: 'Portuguese',
  ru: 'Russian',
  zh: 'Mandarin Chinese (Simplified)',
  ar: 'Modern Standard Arabic',
  el: 'Modern Greek',
};

const DOMAINS = ['general', 'histoire', 'sport', 'geographie', 'art', 'cuisine', 'science', 'cinema', 'faune'];

const DOMAIN_DESCRIPTIONS = {
  general: 'general knowledge spanning the eight sub-themes histoire, sport, geographie, art, cuisine, science, cinema, faune (distribute roughly evenly across them; tag each question with its sub_theme)',
  histoire: 'history (events, civilisations, figures, eras across the world). Tag every question sub_theme="histoire".',
  sport: 'sports (rules, athletes, teams, championships, Olympic Games). Tag every question sub_theme="sport".',
  geographie: 'geography (countries, capitals, mountains, rivers, oceans, landmarks). Tag every question sub_theme="geographie".',
  art: 'art and literature (paintings, sculptures, novels, poets, art movements). Tag every question sub_theme="art".',
  cuisine: 'cuisine and gastronomy (dishes, ingredients, chefs, food origins). Tag every question sub_theme="cuisine".',
  science: 'science (physics, chemistry, biology, astronomy, mathematics, technology). Tag every question sub_theme="science".',
  cinema: 'cinema (films, directors, actors, awards, animated features). Tag every question sub_theme="cinema".',
  faune: 'wildlife and fauna (animals, habitats, behaviour, biology of species). Tag every question sub_theme="faune".',
};

const BANDS = [
  { id: '3-4', desc: 'EASY (depth 3-4) — accessible for children aged 8-12; well-known facts; plausible but not tricky distractors' },
  { id: '5-6', desc: 'INTERMEDIATE (depth 5-6) — solid general knowledge or simple deduction; not obvious yet not obscure' },
  { id: '7-8', desc: 'ADVANCED (depth 7-8) — requires comparison, elimination or solid reasoning; subtle distractors; for adult enthusiasts' },
  { id: '9-10', desc: 'EXPERT (depth 9-10) — very hard but answerable; advanced specialised knowledge; credible traps; nothing obvious' },
];

const PER_BAND_DEFAULT = 10;

// --- arg parsing -----------------------------------------------------------

function parseArgs(argv) {
  const out = { force: false, concurrency: 4, model: process.env.SEED_MODEL || 'gpt-4o-mini', target: PER_BAND_DEFAULT };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--force') out.force = true;
    else if (a === '--langs') out.langs = argv[++i].split(',').map(s => s.trim()).filter(Boolean);
    else if (a === '--domains') out.domains = argv[++i].split(',').map(s => s.trim()).filter(Boolean);
    else if (a === '--target') out.target = Number(argv[++i]);
    else if (a === '--concurrency') out.concurrency = Number(argv[++i]);
    else if (a === '--model') out.model = argv[++i];
    else if (a === '--help' || a === '-h') {
      console.log(fs.readFileSync(new URL(import.meta.url), 'utf-8').split('\n').slice(0, 30).join('\n'));
      process.exit(0);
    }
  }
  out.langs = out.langs ?? Object.keys(LANGUAGES);
  out.domains = out.domains ?? DOMAINS;
  return out;
}

// --- IO helpers ------------------------------------------------------------

function seedPath(lang) {
  return path.join(OUT_DIR, `fallback-questions-${lang}.json`);
}

function loadExisting(lang) {
  const p = seedPath(lang);
  if (!fs.existsSync(p)) return { _meta: {}, questions: [] };
  try {
    const data = JSON.parse(fs.readFileSync(p, 'utf-8'));
    if (!data || !Array.isArray(data.questions)) return { _meta: data?._meta ?? {}, questions: [] };
    return data;
  } catch (e) {
    console.warn(`[seed] could not parse existing ${p}: ${e.message}; starting from empty`);
    return { _meta: {}, questions: [] };
  }
}

function coverageMap(questions) {
  // counts[domain][band] => N
  const counts = {};
  for (const q of questions) {
    const d = q.theme || 'general';
    const b = q.depth_band || 'unknown';
    counts[d] = counts[d] || {};
    counts[d][b] = (counts[d][b] || 0) + 1;
  }
  return counts;
}

function dedupeQuestions(questions) {
  const seen = new Set();
  const out = [];
  for (const q of questions) {
    const key = (q.question_text || '').trim().toLowerCase();
    if (!key) continue;
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(q);
  }
  return out;
}

// --- prompt + OpenAI -------------------------------------------------------

function buildSystemPrompt() {
  return [
    'You are a senior trivia author. You produce factually accurate multiple-choice quiz questions',
    'tailored to a specific language, knowledge domain and difficulty band.',
    'Strict rules:',
    '- Every question is a four-option QCM. Exactly one answer is unambiguously correct.',
    '- The three distractors must be plausible and same-category, never silly.',
    '- Questions must be self-contained, culturally neutral when possible, and never reveal the answer in their wording.',
    '- Write the question, the four answers and the explanation in the requested target language ONLY.',
    '- Answer strings contain ONLY the answer itself — never prefix them with "A.", "B)", "1)", bullet marks or any letter/number labels.',
    '- The explanation is a single concise sentence (a "did you know"-style fact, < 200 chars).',
    '- Output strict JSON, no markdown, no commentary.',
  ].join(' ');
}

function buildUserPrompt({ language, languageName, domain, band, count }) {
  const domainDesc = DOMAIN_DESCRIPTIONS[domain] ?? domain;
  const bandDef = BANDS.find(b => b.id === band);
  const subThemeRule = domain === 'general'
    ? 'Distribute the questions roughly evenly across the eight sub-themes (histoire, sport, geographie, art, cuisine, science, cinema, faune) and tag each one in "sub_theme".'
    : `Always set "sub_theme": "${domain}".`;
  return [
    `TARGET LANGUAGE: ${languageName} (ISO code "${language}"). All natural language MUST be in ${languageName}.`,
    `DOMAIN: "${domain}" — ${domainDesc}`,
    `DIFFICULTY BAND: "${band}" — ${bandDef.desc}`,
    '',
    `Produce EXACTLY ${count} questions, all at difficulty band "${band}".`,
    subThemeRule,
    '',
    'JSON SHAPE (return strict JSON):',
    '{',
    '  "questions": [',
    '    {',
    '      "question_text": "…",',
    '      "answers": ["plain answer 1", "plain answer 2", "plain answer 3", "plain answer 4"],',
    '      "correct_id": 0,',
    '      "explanation": "…",',
    `      "theme": "${domain}",`,
    `      "sub_theme": "<sub-theme>",`,
    `      "depth_band": "${band}"`,
    '    }',
    '  ]',
    '}',
    '',
    'NEVER repeat the same fact, person, place or year across two questions in this batch.',
    'Vary topics widely. The "correct_id" is the 0-based index (0, 1, 2 or 3) of the right answer in the "answers" array.',
    'Distribute the correct_id roughly evenly across the four positions (do not always pick 0).',
  ].join('\n');
}

const ANSWER_PREFIX_RE = /^\s*(?:[A-Da-d1-4]\s*[\.\):\-]\s*|[•\-\*]\s+)/;

function cleanAnswer(s) {
  return String(s).replace(ANSWER_PREFIX_RE, '').trim();
}

async function generateBatch(client, model, language, domain, band, count) {
  const languageName = LANGUAGES[language];
  const messages = [
    { role: 'system', content: buildSystemPrompt() },
    { role: 'user', content: buildUserPrompt({ language, languageName, domain, band, count }) },
  ];

  const resp = await client.chat.completions.create({
    model,
    messages,
    temperature: 0.7,
    response_format: { type: 'json_object' },
  });

  const raw = resp.choices?.[0]?.message?.content ?? '{}';
  let parsed;
  try { parsed = JSON.parse(raw); } catch (e) {
    throw new Error(`invalid JSON from model: ${e.message}`);
  }
  const questions = Array.isArray(parsed.questions) ? parsed.questions : [];
  const cleaned = [];
  for (const q of questions) {
    if (!q || typeof q !== 'object') continue;
    if (typeof q.question_text !== 'string' || !q.question_text.trim()) continue;
    if (!Array.isArray(q.answers) || q.answers.length !== 4) continue;
    const ans = q.answers.map(cleanAnswer);
    if (ans.some(a => !a)) continue;
    if (new Set(ans.map(a => a.toLowerCase())).size !== 4) continue; // distinct answers
    const correctId = Number(q.correct_id);
    if (!Number.isInteger(correctId) || correctId < 0 || correctId > 3) continue;
    cleaned.push({
      type: 'multiple',
      question_text: q.question_text.trim(),
      answers: ans,
      correct_id: correctId,
      explanation: typeof q.explanation === 'string' ? q.explanation.trim() : '',
      theme: domain,
      sub_theme: typeof q.sub_theme === 'string' ? q.sub_theme.trim().toLowerCase() : null,
      depth_band: band,
    });
  }
  return { ok: cleaned.length, expected: count, questions: cleaned };
}

// --- queue runner ----------------------------------------------------------

async function runWithConcurrency(items, concurrency, worker) {
  const results = new Array(items.length);
  let i = 0;
  async function next() {
    while (true) {
      const myIndex = i++;
      if (myIndex >= items.length) return;
      try {
        results[myIndex] = await worker(items[myIndex], myIndex);
      } catch (e) {
        results[myIndex] = { error: e };
      }
    }
  }
  const runners = Array.from({ length: Math.max(1, concurrency) }, () => next());
  await Promise.all(runners);
  return results;
}

// --- main ------------------------------------------------------------------

async function main() {
  const opts = parseArgs(process.argv);
  if (!process.env.OPENAI_API_KEY && !process.env.AI_INTEGRATIONS_OPENAI_BASE_URL) {
    console.error('Missing both OPENAI_API_KEY and AI_INTEGRATIONS_OPENAI_BASE_URL — set one before running');
    process.exit(2);
  }
  // Prefer Replit's ModelFarm passthrough (managed billing, dummy key) when configured.
  // The Question API worker uses the same router; this keeps build-time generation
  // on the same paid path so we don't burn the user's personal OpenAI quota.
  const baseURL = process.env.AI_INTEGRATIONS_OPENAI_BASE_URL || undefined;
  const apiKey = baseURL ? (process.env.AI_INTEGRATIONS_OPENAI_API_KEY || '_DUMMY_API_KEY_') : process.env.OPENAI_API_KEY;
  const client = new OpenAI({ apiKey, baseURL });
  console.log(`[seed] using OpenAI baseURL=${baseURL || 'default'}`);

  fs.mkdirSync(OUT_DIR, { recursive: true });

  // Build the list of (lang, domain, band) batches that still need work.
  // Per-band batches give the model a much easier target than asking for one
  // mixed batch (40 questions across 4 bands), which gpt-4o-mini collapses
  // to a single band most of the time.
  const batches = [];
  for (const lang of opts.langs) {
    if (!LANGUAGES[lang]) {
      console.warn(`[seed] unknown language ${lang}, skipping`);
      continue;
    }
    const existing = loadExisting(lang);
    const counts = coverageMap(existing.questions);
    for (const domain of opts.domains) {
      if (!DOMAINS.includes(domain)) {
        console.warn(`[seed] unknown domain ${domain}, skipping`);
        continue;
      }
      for (const b of BANDS) {
        const have = counts[domain]?.[b.id] ?? 0;
        const need = opts.force ? opts.target : Math.max(0, opts.target - have);
        if (need > 0) batches.push({ lang, domain, band: b.id, count: Math.max(need, opts.target) });
      }
    }
  }

  console.log(`[seed] ${batches.length} batches to generate (concurrency=${opts.concurrency}, model=${opts.model}, target=${opts.target})`);
  if (batches.length === 0) {
    console.log('[seed] nothing to do — coverage already met. Use --force to regenerate.');
    return;
  }

  // Group results per-language so we write atomically once per language.
  const perLang = {};
  for (const lang of opts.langs) perLang[lang] = loadExisting(lang);

  let done = 0;
  const writeLocks = {}; // serialise per-lang writes
  await runWithConcurrency(batches, opts.concurrency, async ({ lang, domain, band, count }) => {
    const t0 = Date.now();
    let attempt = 0;
    let lastError = null;
    while (attempt < 3) {
      attempt++;
      try {
        const res = await generateBatch(client, opts.model, lang, domain, band, count);
        // Merge & dedupe within the language file. Serialise writes per-lang.
        await (writeLocks[lang] = (writeLocks[lang] || Promise.resolve()).then(() => {
          perLang[lang].questions = dedupeQuestions([...(perLang[lang].questions || []), ...res.questions]);
          writeLang(lang, perLang[lang]);
        }));
        const dt = ((Date.now() - t0) / 1000).toFixed(1);
        done++;
        console.log(`[seed] (${done}/${batches.length}) ${lang}/${domain}/${band} → kept ${res.ok}/${res.expected} in ${dt}s`);
        return res;
      } catch (e) {
        lastError = e;
        const wait = 1000 * Math.pow(2, attempt);
        console.warn(`[seed] ${lang}/${domain}/${band} attempt ${attempt} failed: ${e.message}; retrying in ${wait}ms`);
        await new Promise(r => setTimeout(r, wait));
      }
    }
    done++;
    console.error(`[seed] (${done}/${batches.length}) ${lang}/${domain}/${band} GAVE UP: ${lastError?.message}`);
  });

  // Final write + meta.
  for (const lang of opts.langs) writeLang(lang, perLang[lang], { final: true });
  console.log('[seed] done.');
}

function writeLang(lang, data, { final = false } = {}) {
  const counts = coverageMap(data.questions);
  data._meta = {
    purpose: 'Embedded seed pool consumed by QuestionService and SeedQuestionPoolService when the persistent question bank and Redis cache are both empty. Live matches NEVER call any AI provider (#88) — these seed entries guarantee a match can always start.',
    language: lang,
    schema_version: 2,
    bands: BANDS.map(b => b.id),
    domains: DOMAINS,
    coverage: counts,
    total: data.questions.length,
    generated_at: new Date().toISOString(),
    generator: 'scripts/seed/generate_seed_pool.mjs',
  };
  const out = JSON.stringify(data, null, 2) + '\n';
  fs.writeFileSync(seedPath(lang), out);
  if (final) console.log(`[seed] wrote ${seedPath(lang)} — ${data.questions.length} total`);
}

main().catch(e => { console.error(e); process.exit(1); });
