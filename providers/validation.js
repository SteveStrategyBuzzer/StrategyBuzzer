/**
 * Rich JSON contract validation for bank-refill questions.
 *
 * Every provider MUST emit exactly the same shape; partial output is
 * treated as a provider error (see task #83). The first failed check
 * is returned with a short reason so /health can surface the latest
 * rejection.
 */

const REQUIRED_TOP_LEVEL = [
  'question_text',
  'answer_a',
  'answer_b',
  'answer_c',
  'correct_answer_key',
  'explanation',
  'saviez_vous',
  'domain',
  'sub_domain',
  'question_type',
  'cognitive_type',
  'difficulty_depth',
  'concept_id',
  'concept_family',
  'translations',
];

const ALLOWED_COGNITIVE_TYPES = new Set(['recognition', 'reasoning', 'deceptive_trap']);
const ALLOWED_QUESTION_TYPES = new Set(['qcm', 'true_false']);
const ALLOWED_KEYS = new Set(['A', 'B', 'C', 'D']);

function fail(reason) {
  return { ok: false, reason };
}

function isNonEmptyString(v) {
  return typeof v === 'string' && v.trim().length > 0;
}

function validateAnswerForKey(payload, key) {
  const fieldName = 'answer_' + key.toLowerCase();
  const value = payload[fieldName];
  if (!isNonEmptyString(value)) {
    return `correct_answer_key=${key} but ${fieldName} is empty`;
  }
  return null;
}

function validateTranslations(translations, expectedKey, isQcm) {
  if (!translations || typeof translations !== 'object') {
    return 'translations missing';
  }
  const langs = Object.keys(translations);
  if (langs.length === 0) {
    return 'translations empty';
  }
  for (const lang of langs) {
    const tr = translations[lang];
    if (!tr || typeof tr !== 'object') {
      return `translations[${lang}] not an object`;
    }
    const required = ['question_text', 'answer_a', 'answer_b', 'answer_c', 'correct_answer_key', 'explanation', 'saviez_vous'];
    for (const field of required) {
      if (!(field in tr)) {
        return `translations[${lang}].${field} missing`;
      }
    }
    if (!isNonEmptyString(tr.question_text)) {
      return `translations[${lang}].question_text empty`;
    }
    if (!isNonEmptyString(tr.saviez_vous)) {
      return `translations[${lang}].saviez_vous empty`;
    }
    if (!isNonEmptyString(tr.explanation)) {
      return `translations[${lang}].explanation empty`;
    }
    const trKey = String(tr.correct_answer_key || '').toUpperCase();
    if (!ALLOWED_KEYS.has(trKey)) {
      return `translations[${lang}].correct_answer_key=${tr.correct_answer_key} (invalide)`;
    }
    if (trKey !== expectedKey) {
      return `translations[${lang}].correct_answer_key=${trKey} mismatch with top-level ${expectedKey}`;
    }
    // Same number of answers as top-level (qcm = 4 non-empty, true_false = 2).
    const trAnswers = ['answer_a', 'answer_b', 'answer_c', 'answer_d']
      .map(f => tr[f])
      .filter(v => isNonEmptyString(v));
    if (isQcm && trAnswers.length !== 4) {
      return `translations[${lang}] expected 4 answers, got ${trAnswers.length}`;
    }
    if (!isQcm && trAnswers.length !== 2) {
      return `translations[${lang}] expected 2 answers (true_false), got ${trAnswers.length}`;
    }
  }
  return null;
}

/**
 * Validate the rich bank-refill contract. Returns:
 *   { ok: true, payload } on success
 *   { ok: false, reason } on failure (short, suitable for logs/health)
 */
function validateRichContract(payload) {
  if (!payload || typeof payload !== 'object') {
    return fail('payload not an object');
  }

  for (const field of REQUIRED_TOP_LEVEL) {
    if (!(field in payload)) {
      return fail(`missing ${field}`);
    }
  }

  const cognitive = String(payload.cognitive_type || '').toLowerCase();
  if (!ALLOWED_COGNITIVE_TYPES.has(cognitive)) {
    return fail(`cognitive_type=${payload.cognitive_type} (interdit)`);
  }

  const questionType = String(payload.question_type || '').toLowerCase();
  if (!ALLOWED_QUESTION_TYPES.has(questionType)) {
    return fail(`question_type=${payload.question_type} (invalide)`);
  }

  const depth = Number(payload.difficulty_depth);
  if (!Number.isFinite(depth) || depth < 1 || depth > 10) {
    return fail(`difficulty_depth=${payload.difficulty_depth} (hors plage 1-10)`);
  }

  if (!isNonEmptyString(payload.concept_id)) return fail('missing concept_id');
  if (!isNonEmptyString(payload.concept_family)) return fail('missing concept_family');
  if (!isNonEmptyString(payload.domain)) return fail('missing domain');
  if (!isNonEmptyString(payload.sub_domain)) return fail('missing sub_domain');
  if (!isNonEmptyString(payload.question_text)) return fail('missing question_text');
  if (!isNonEmptyString(payload.explanation)) return fail('missing explanation');
  if (!isNonEmptyString(payload.saviez_vous)) return fail('missing saviez_vous');

  const correctKey = String(payload.correct_answer_key || '').toUpperCase();
  if (!ALLOWED_KEYS.has(correctKey)) {
    return fail(`correct_answer_key=${payload.correct_answer_key} (invalide)`);
  }

  const isQcm = questionType === 'qcm';
  if (isQcm) {
    if (!isNonEmptyString(payload.answer_a)) return fail('missing answer_a');
    if (!isNonEmptyString(payload.answer_b)) return fail('missing answer_b');
    if (!isNonEmptyString(payload.answer_c)) return fail('missing answer_c');
    if (!isNonEmptyString(payload.answer_d)) return fail('missing answer_d');
  } else {
    if (!isNonEmptyString(payload.answer_a) || !isNonEmptyString(payload.answer_b)) {
      return fail('true_false: answer_a and answer_b required');
    }
    if (!('answer_d' in payload)) {
      return fail('true_false: answer_d field required (nullable)');
    }
  }

  const correctErr = validateAnswerForKey(payload, correctKey);
  if (correctErr) return fail(correctErr);

  const trErr = validateTranslations(payload.translations, correctKey, isQcm);
  if (trErr) return fail(trErr);

  return { ok: true, payload };
}

module.exports = { validateRichContract };
