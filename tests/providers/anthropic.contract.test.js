#!/usr/bin/env node
/**
 * Conformance test (#89) — Anthropic provider must produce output that
 * passes the SAME `validateRichContract()` used by Gemini and OpenAI.
 *
 * The test stubs the Anthropic SDK transport (no network call) so the
 * adapter `providers/anthropic.js` is exercised end-to-end (params shape,
 * `system` / `messages` wiring, `content[]` text extraction) and the
 * extracted text is then handed to the SHARED validator at
 * `providers/validation.js`.
 *
 * Cases covered:
 *   1. Nominal multilingual QCM (3+ languages) — must pass
 *   2. cognitive_type = "deceptive_trap"           — must pass
 *   3. difficulty_depth at both extremes (1 and 10) — must pass
 *   4. saviez_vous omitted                         — MUST be rejected
 *
 * Usage (from project root):
 *   node tests/providers/anthropic.contract.test.js
 *
 * Exit codes:
 *   0 = all assertions hold
 *   1 = at least one assertion failed
 */

'use strict';

const assert = require('node:assert/strict');

/* --------------------------- SDK stub installation ---------------------- */
//
// Replace `@anthropic-ai/sdk` in the require cache BEFORE loading
// `providers/anthropic.js`. The fake mirrors the real SDK shape:
//   - `new Anthropic({ apiKey })`
//   - `client.messages.create(params)` → { content: [{ type: 'text', text }] }

let nextResponseText = null;
let lastCallParams = null;

class FakeAnthropic {
  constructor({ apiKey } = {}) {
    this.apiKey = apiKey;
    this.messages = {
      create: async (params) => {
        lastCallParams = params;
        return { content: [{ type: 'text', text: nextResponseText }] };
      },
    };
  }
}

const sdkPath = require.resolve('@anthropic-ai/sdk');
require.cache[sdkPath] = {
  id: sdkPath,
  filename: sdkPath,
  loaded: true,
  exports: FakeAnthropic,
  children: [],
  paths: [],
};

const anthropicProvider = require('../../providers/anthropic');
const { validateRichContract } = require('../../providers/validation');

/* ------------------------------- Fixtures ------------------------------- */

function nominalMultilingualPayload() {
  // QCM with 3 non-empty answers + answer_d, translations in 3 languages,
  // cognitive_type = recognition, difficulty_depth in mid-range.
  return {
    question_text: 'Quelle est la capitale de la France ?',
    answer_a: 'Paris',
    answer_b: 'Lyon',
    answer_c: 'Marseille',
    answer_d: 'Bordeaux',
    correct_answer_key: 'A',
    explanation: 'Paris est la capitale de la France depuis le Xe siècle.',
    saviez_vous: 'Paris compte plus de 2 millions d\'habitants intra-muros.',
    domain: 'geographie',
    sub_domain: 'capitales',
    question_type: 'qcm',
    cognitive_type: 'recognition',
    difficulty_depth: 4,
    concept_id: 'geo.capitale.france',
    concept_family: 'capitales_europe',
    translations: {
      fr: {
        question_text: 'Quelle est la capitale de la France ?',
        answer_a: 'Paris',
        answer_b: 'Lyon',
        answer_c: 'Marseille',
        answer_d: 'Bordeaux',
        correct_answer_key: 'A',
        explanation: 'Paris est la capitale de la France depuis le Xe siècle.',
        saviez_vous: 'Paris compte plus de 2 millions d\'habitants intra-muros.',
      },
      en: {
        question_text: 'What is the capital of France?',
        answer_a: 'Paris',
        answer_b: 'Lyon',
        answer_c: 'Marseille',
        answer_d: 'Bordeaux',
        correct_answer_key: 'A',
        explanation: 'Paris has been the capital of France since the 10th century.',
        saviez_vous: 'Paris has more than 2 million residents intra-muros.',
      },
      es: {
        question_text: '¿Cuál es la capital de Francia?',
        answer_a: 'París',
        answer_b: 'Lyon',
        answer_c: 'Marsella',
        answer_d: 'Burdeos',
        correct_answer_key: 'A',
        explanation: 'París es la capital de Francia desde el siglo X.',
        saviez_vous: 'París tiene más de 2 millones de habitantes intra-muros.',
      },
    },
  };
}

function deceptiveTrapPayload() {
  const p = nominalMultilingualPayload();
  p.cognitive_type = 'deceptive_trap';
  p.question_text = 'Le Soleil tourne-t-il autour de la Terre ?';
  p.answer_a = 'Oui';
  p.answer_b = 'Non';
  p.answer_c = 'Parfois';
  p.answer_d = 'Cela dépend de la saison';
  p.correct_answer_key = 'B';
  p.explanation = 'C\'est la Terre qui tourne autour du Soleil (héliocentrisme).';
  p.saviez_vous = 'Le modèle héliocentrique a été démontré par Galilée et Copernic.';
  p.domain = 'sciences';
  p.sub_domain = 'astronomie';
  p.concept_id = 'sci.astro.heliocentrisme';
  p.concept_family = 'astronomie_systeme_solaire';
  p.difficulty_depth = 7;
  for (const lang of Object.keys(p.translations)) {
    const tr = p.translations[lang];
    tr.correct_answer_key = 'B';
    tr.answer_a = 'Yes';
    tr.answer_b = 'No';
    tr.answer_c = 'Sometimes';
    tr.answer_d = 'It depends on the season';
  }
  // FR override
  p.translations.fr.answer_a = 'Oui';
  p.translations.fr.answer_b = 'Non';
  p.translations.fr.answer_c = 'Parfois';
  p.translations.fr.answer_d = 'Cela dépend de la saison';
  // ES override
  p.translations.es.answer_a = 'Sí';
  p.translations.es.answer_b = 'No';
  p.translations.es.answer_c = 'A veces';
  p.translations.es.answer_d = 'Depende de la estación';
  return p;
}

function depthExtremePayload(depth) {
  const p = nominalMultilingualPayload();
  p.difficulty_depth = depth;
  p.concept_id = `geo.capitale.france.depth_${depth}`;
  return p;
}

function missingSaviezVousPayload() {
  const p = nominalMultilingualPayload();
  delete p.saviez_vous;
  return p;
}

/* ------------------------------- Helpers -------------------------------- */

async function runAnthropicAndValidate(payload) {
  nextResponseText = JSON.stringify(payload);
  const text = await anthropicProvider.call({
    apiKey: 'sk-ant-test',
    systemPrompt: 'You are a quiz generator. Respond with JSON only.',
    userPrompt: 'Generate one bank-refill question.',
    temperature: 0.7,
    maxOutputTokens: 1200,
    responseMimeType: 'application/json',
  });
  // Confirm the adapter forwarded the params correctly
  assert.equal(typeof lastCallParams.system, 'string', 'system prompt must be forwarded');
  assert.ok(Array.isArray(lastCallParams.messages), 'messages must be an array');
  assert.equal(lastCallParams.messages[0].role, 'user', 'user role must be set');
  assert.equal(lastCallParams.max_tokens, 1200, 'max_tokens must be forwarded');
  assert.equal(typeof lastCallParams.model, 'string', 'model must be set');
  // Now run the SHARED validator on the extracted text
  const parsed = JSON.parse(text);
  return validateRichContract(parsed);
}

/* -------------------------------- Tests --------------------------------- */

const tests = [
  {
    name: 'nominal multilingual QCM (3 languages) passes shared validator',
    run: async () => {
      const result = await runAnthropicAndValidate(nominalMultilingualPayload());
      assert.equal(result.ok, true, `expected ok, got reason: ${result.reason}`);
      assert.ok(result.payload, 'validator must return the payload on success');
      assert.ok(
        Object.keys(result.payload.translations).length >= 3,
        'at least 3 translations expected'
      );
    },
  },
  {
    name: 'cognitive_type=deceptive_trap is accepted by the shared validator',
    run: async () => {
      const result = await runAnthropicAndValidate(deceptiveTrapPayload());
      assert.equal(result.ok, true, `expected ok, got reason: ${result.reason}`);
      assert.equal(result.payload.cognitive_type, 'deceptive_trap');
    },
  },
  {
    name: 'difficulty_depth=1 is accepted (lower bound)',
    run: async () => {
      const result = await runAnthropicAndValidate(depthExtremePayload(1));
      assert.equal(result.ok, true, `expected ok, got reason: ${result.reason}`);
      assert.equal(result.payload.difficulty_depth, 1);
    },
  },
  {
    name: 'difficulty_depth=10 is accepted (upper bound)',
    run: async () => {
      const result = await runAnthropicAndValidate(depthExtremePayload(10));
      assert.equal(result.ok, true, `expected ok, got reason: ${result.reason}`);
      assert.equal(result.payload.difficulty_depth, 10);
    },
  },
  {
    name: 'omitting saviez_vous is REJECTED — no permissive Anthropic path',
    run: async () => {
      const result = await runAnthropicAndValidate(missingSaviezVousPayload());
      assert.equal(result.ok, false, 'missing saviez_vous must be rejected');
      assert.match(
        result.reason,
        /saviez_vous/i,
        `rejection reason must mention saviez_vous, got: ${result.reason}`
      );
    },
  },
];

(async () => {
  let passed = 0;
  let failed = 0;
  for (const t of tests) {
    try {
      await t.run();
      console.log(`  ok  — ${t.name}`);
      passed++;
    } catch (err) {
      console.log(`  FAIL — ${t.name}`);
      console.log(`         ${err.message}`);
      failed++;
    }
  }
  console.log(`\n${passed} passed, ${failed} failed`);
  process.exit(failed === 0 ? 0 : 1);
})();
