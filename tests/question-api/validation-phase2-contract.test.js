const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const jwt = require('jsonwebtoken');

process.env.QUESTION_API_JWT_SECRET = 'validation-phase2-test-secret';
const providers = require('../../providers');
const questionApi = require('../../question-api').__test;
const { validateKernelPhase2Response, app, setAdminJwtRedisClient } = questionApi;

const input = {
  validation_request_reference: 'opaque-ref',
  target_language: 'fr',
  rule_registry: {
    MEANING_DRIFT: 'BLOCKING',
    CONTENT_UNTRANSLATABLE: 'BLOCKING',
  },
  finding_schema: {
    field_path: ['question', 'correct_answer_key', 'correct_answer_text', 'sv', 'translation'],
    choice_field_path_pattern: 'choices.[a-d].text',
    severity: ['BLOCKING'],
  },
};

function response(finding) {
  return JSON.stringify({
    validation_request_reference: 'opaque-ref',
    validator_request_id: 'validator-1',
    decision: 'SUSPICION',
    findings: [finding],
  });
}

const comparative = {
  field_path: 'question',
  rule_code: 'MEANING_DRIFT',
  severity: 'BLOCKING',
  evidence: {
    expected_rule: 'Preserve meaning',
    observed_result: 'Meaning changed',
    source_excerpt: 'Which color?',
    target_excerpt: 'Quel animal ?',
  },
};

test('rejects invented rule codes and field paths', () => {
  assert.equal(validateKernelPhase2Response(input, response({ ...comparative, rule_code: 'INVENTED' })).ok, false);
  assert.equal(validateKernelPhase2Response(input, response({ ...comparative, field_path: 'internal.claim' })).ok, false);
});

test('rejects comparative evidence without source or target excerpts', () => {
  const missingTarget = structuredClone(comparative);
  delete missingTarget.evidence.target_excerpt;
  assert.equal(validateKernelPhase2Response(input, response(missingTarget)).ok, false);
  const missingSource = structuredClone(comparative);
  delete missingSource.evidence.source_excerpt;
  assert.equal(validateKernelPhase2Response(input, response(missingSource)).ok, false);
});

test('rejects incomplete CONTENT_UNTRANSLATABLE evidence', () => {
  const finding = {
    field_path: 'translation',
    rule_code: 'CONTENT_UNTRANSLATABLE',
    severity: 'BLOCKING',
    evidence: {
      language_code: 'fr',
      source_revision: 'a'.repeat(64),
      translation_revision: 1,
      components_concerned: ['question'],
      contract_rules_in_conflict: ['TARGET_LANGUAGE_INCORRECT'],
      source_excerpts: ['English'],
      target_excerpts: ['Français'],
      expected_rule: 'Translate directly',
      observed_result: 'Impossible',
      explanation: 'The rules conflict.',
    },
  };
  assert.equal(validateKernelPhase2Response(input, response(finding)).ok, true);
  delete finding.evidence.explanation;
  assert.equal(validateKernelPhase2Response(input, response(finding)).ok, false);
});

test('real endpoint reports strict contract rejection as invalid validation response', async (t) => {
  setAdminJwtRedisClient({ set: async () => 'OK' });
  const originalGenerate = providers.router.generate;
  const invalidOutputs = [
    JSON.parse(response({ ...comparative, rule_code: 'INVENTED' })),
    JSON.parse(response({ ...comparative, field_path: 'internal.claim' })),
    JSON.parse(response({
      ...comparative,
      evidence: { ...comparative.evidence, target_excerpt: undefined },
    })),
  ];
  let outputIndex = 0;
  providers.router.generate = async ({ validate }) => {
    const validation = validate(JSON.stringify(invalidOutputs[outputIndex++]));
    if (!validation.ok) {
      const error = new Error(JSON.stringify([{ status: 'invalid_contract', message: validation.reason }]));
      error.name = 'AllProvidersExhaustedError';
      throw error;
    }
    return { validated: validation.value, provider: 'test', latencyMs: 1 };
  };
  const server = app.listen(0);
  t.after(() => {
    providers.router.generate = originalGenerate;
    server.close();
  });
  await new Promise((resolve) => server.once('listening', resolve));
  const address = server.address();
  const payload = {
    ...input,
    external_validation_idempotency_key: 'opaque-key',
    source_language: 'en',
    cognitive_type: 'QCM_RECOGNITION',
    source: {
      question: 'Which color?',
      choices: { a: 'Blue', b: 'Red', c: 'Green', d: 'Yellow' },
      correct_answer_key: 'a',
      sv: 'Blue is a color.',
    },
    target: {
      question: 'Quelle couleur ?',
      choices: { a: 'Bleu', b: 'Rouge', c: 'Vert', d: 'Jaune' },
      correct_answer_key: 'a',
      sv: 'Le bleu est une couleur.',
    },
    context: {},
  };
  for (let index = 0; index < invalidOutputs.length; index += 1) {
    const body = JSON.stringify(payload);
    const now = Math.floor(Date.now() / 1000);
    const token = jwt.sign({
      aud: 'question-api',
      purpose: 'qapi_admin',
      endpoint: '/validate-kernel-phase2',
      payload_hash: crypto.createHash('sha256').update(body).digest('hex'),
      sub: 'validation-phase2-test',
      jti: crypto.randomUUID(),
      iat: now,
      exp: now + 30,
    }, process.env.QUESTION_API_JWT_SECRET, { algorithm: 'HS256' });
    const httpResponse = await fetch(`http://127.0.0.1:${address.port}/validate-kernel-phase2`, {
      method: 'POST',
      headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json' },
      body,
    });
    assert.equal(httpResponse.status, 502);
    assert.equal((await httpResponse.json()).error, 'invalid_validation_response');
  }
});