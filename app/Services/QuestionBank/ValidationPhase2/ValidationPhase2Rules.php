<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

final class ValidationPhase2Rules
{
    public const LANGUAGES = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];
    public const TYPES = [
        'QCM_RECOGNITION', 'QCM_REASONING', 'QCM_TRAP',
        'TRUE_FALSE_RECOGNITION_TRUE', 'TRUE_FALSE_RECOGNITION_FALSE',
        'TRUE_FALSE_REASONING_TRUE', 'TRUE_FALSE_REASONING_FALSE',
    ];
    public const BLOCKING_CODES = [
        'TARGET_LANGUAGE_INCORRECT', 'UNTRANSLATED_SOURCE_FRAGMENT',
        'REQUIRED_COMPONENT_MISSING', 'TARGET_FORMAT_INVALID',
        'TARGET_CONCISION_INVALID', 'TARGET_READING_TIME_INVALID',
        'MEANING_DRIFT', 'FACTUAL_ACCURACY_DRIFT', 'DEPTH_LEVEL_DRIFT',
        'SUBJECT_ALIGNMENT_DRIFT', 'DOMINANT_IDEA_DRIFT', 'COGNITIVE_FUNCTION_DRIFT',
        'AMBIGUITY_INTRODUCED', 'QUESTION_ANSWER_MISMATCH', 'ANSWER_KEY_CHANGED',
        'ANSWER_TEXT_KEY_MISMATCH', 'CHOICE_COUNT_CHANGED',
        'MULTIPLE_CORRECT_ANSWERS_INTRODUCED', 'DISTRACTOR_BECAME_TRUE',
        'DISTRACTOR_PLAUSIBILITY_LOST', 'CHOICE_SEMANTIC_CATEGORY_DRIFT',
        'CHOICE_GRAMMATICAL_COHERENCE_DRIFT', 'REASONING_RELATION_DRIFT',
        'TRAP_CONFUSION_DRIFT', 'TRUE_FALSE_POLARITY_CHANGED',
        'FALSE_STATEMENT_ERROR_COUNT_DRIFT', 'SV_CONTRADICTS_SOURCE',
        'SV_CONTRADICTS_ANSWER', 'SV_PEDAGOGICAL_VALUE_LOST', 'SV_MERE_REPETITION',
        'CONTENT_UNTRANSLATABLE',
    ];
    private const COMPARATIVE_CODES = [
        'MEANING_DRIFT', 'FACTUAL_ACCURACY_DRIFT', 'DEPTH_LEVEL_DRIFT', 'SUBJECT_ALIGNMENT_DRIFT',
        'DOMINANT_IDEA_DRIFT', 'COGNITIVE_FUNCTION_DRIFT', 'QUESTION_ANSWER_MISMATCH',
        'REASONING_RELATION_DRIFT', 'TRAP_CONFUSION_DRIFT', 'SV_CONTRADICTS_SOURCE',
        'SV_CONTRADICTS_ANSWER', 'ANSWER_KEY_CHANGED', 'ANSWER_TEXT_KEY_MISMATCH',
        'CHOICE_COUNT_CHANGED', 'TRUE_FALSE_POLARITY_CHANGED', 'DISTRACTOR_BECAME_TRUE',
        'DISTRACTOR_PLAUSIBILITY_LOST', 'CHOICE_SEMANTIC_CATEGORY_DRIFT',
        'CHOICE_GRAMMATICAL_COHERENCE_DRIFT', 'FALSE_STATEMENT_ERROR_COUNT_DRIFT',
    ];
    public const FIELD_PATHS = [
        'question', 'correct_answer_key', 'correct_answer_text', 'sv', 'translation',
    ];

    public static function registry(): array
    {
        return array_fill_keys(self::BLOCKING_CODES, 'BLOCKING');
    }

    public static function schema(string $cognitiveType = 'QCM_RECOGNITION'): array
    {
        $pattern = str_starts_with($cognitiveType, 'QCM_') ? 'choices.[a-d].text' : 'choices.[a-b].text';
        return ['field_path' => self::FIELD_PATHS, 'choice_field_path_pattern' => $pattern, 'severity' => ['BLOCKING']];
    }

    public static function validateFinding(array $finding, string $cognitiveType, string $language, string $sourceRevision, ?int $translationRevision): bool
    {
        if (!isset($finding['field_path'], $finding['rule_code'], $finding['severity'], $finding['evidence'])
            || !self::validFieldPath((string) $finding['field_path'], $cognitiveType)
            || !isset(self::registry()[$finding['rule_code']])
            || $finding['severity'] !== 'BLOCKING'
            || !is_array($finding['evidence'])) return false;
        $e = $finding['evidence'];
        if (array_key_exists('field_path', $e) || !isset($e['expected_rule'], $e['observed_result'])
            || (!isset($e['source_excerpt']) && !isset($e['source_excerpts']) && !isset($e['details']))
            || ($finding['rule_code'] === 'CONTENT_UNTRANSLATABLE'
                && (!isset($e['language_code'], $e['source_revision'], $e['components_concerned'],
                    $e['contract_rules_in_conflict'], $e['source_excerpts'], $e['expected_rule'],
                    $e['observed_result'], $e['explanation'])
                || $e['language_code'] !== $language || $e['source_revision'] !== $sourceRevision
                || ($translationRevision !== null && ($e['translation_revision'] ?? null) !== $translationRevision)))) return false;
        if (!self::text($e['expected_rule']) || !self::text($e['observed_result'])) return false;
        foreach (['details', 'explanation'] as $textField) {
            if (array_key_exists($textField, $e) && !self::text($e[$textField])) return false;
        }
        if ((!isset($e['details']) || !self::nonEmpty($e['details']))
            && (!isset($e['source_excerpt']) || !self::nonEmpty($e['source_excerpt']))
            && (!isset($e['source_excerpts']) || !self::nonEmpty($e['source_excerpts']))) return false;
        if ($finding['rule_code'] === 'CONTENT_UNTRANSLATABLE') {
            if (!is_array($e['source_excerpts']) || !self::nonEmptyList($e['source_excerpts'])
                || (isset($e['target_excerpts']) && !is_array($e['target_excerpts']))) return false;
            if (!self::nonEmptyList($e['components_concerned']) || !self::nonEmptyList($e['contract_rules_in_conflict'])
                || !self::text($e['explanation'])
                || ($translationRevision !== null && (!array_key_exists('target_excerpts', $e) || !self::nonEmptyList($e['target_excerpts'])))) return false;
        }
        if (in_array($finding['rule_code'], self::COMPARATIVE_CODES, true)) {
            $source = $e['source_excerpt'] ?? $e['source_excerpts'] ?? [];
            $target = $e['target_excerpt'] ?? $e['target_excerpts'] ?? [];
            if (!self::nonEmpty($source) || !self::nonEmpty($target)) return false;
            if (!isset($e['target_excerpt']) && !isset($e['target_excerpts'])) return false;
        }
        if (isset($e['source_excerpt']) && !is_string($e['source_excerpt'])) return false;
        if (isset($e['target_excerpt']) && !is_string($e['target_excerpt'])) return false;
        return true;
    }

    private static function validFieldPath(string $path, string $cognitiveType): bool
    {
        if (!in_array($cognitiveType, self::TYPES, true)) return false;
        if (!str_starts_with($cognitiveType, 'QCM_') && preg_match('/\Achoices\.[cd]\.text\z/', $path) === 1) return false;
        return in_array($path, self::FIELD_PATHS, true)
            || preg_match('/\Achoices\.[a-d]\.text\z/', $path) === 1;
    }

    private static function nonEmpty(mixed $value): bool
    {
        if (is_string($value)) return trim($value) !== '';
        if (is_array($value)) return self::nonEmptyList($value);
        return $value !== null;
    }

    private static function text(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function nonEmptyList(mixed $value): bool
    {
        if (!is_array($value) || $value === []) return false;
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') return false;
        }
        return true;
    }
}