<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\ValidationPhase2;

use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Request;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Rules;
use PHPUnit\Framework\TestCase;

final class ValidationPhase2RulesTest extends TestCase
{
    public function test_registry_is_closed_and_all_contract_codes_are_blocking(): void
    {
        self::assertCount(31, ValidationPhase2Rules::registry());
        self::assertSame('BLOCKING', ValidationPhase2Rules::registry()['CONTENT_UNTRANSLATABLE']);
        self::assertSame(['question', 'correct_answer_key', 'correct_answer_text', 'sv', 'translation'], ValidationPhase2Rules::FIELD_PATHS);
    }

    public function test_external_request_does_not_expose_internal_envelope_fields(): void
    {
        $request = new ValidationPhase2Request('opaque-request', 'opaque-key', 'en', 'fr', 'QCM_RECOGNITION',
            ['question' => 'Q'], ['question' => 'Q'], [], ValidationPhase2Rules::registry(), ValidationPhase2Rules::schema());
        $payload = $request->toExternalArray();
        self::assertArrayNotHasKey('claim_token', $payload);
        self::assertArrayNotHasKey('storage_reference', $payload);
        self::assertSame('opaque-request', $payload['validation_request_reference']);
    }

    public function test_content_untranslatable_requires_decision_three_evidence(): void
    {
        $finding = [
            'field_path' => 'translation', 'rule_code' => 'CONTENT_UNTRANSLATABLE', 'severity' => 'BLOCKING',
            'evidence' => [
                'language_code' => 'fr', 'source_revision' => str_repeat('a', 64),
                'translation_revision' => 1, 'components_concerned' => ['question'],
                'contract_rules_in_conflict' => ['TARGET_LANGUAGE_INCORRECT'],
                'source_excerpts' => ['English'], 'target_excerpts' => ['Français'],
                'expected_rule' => 'Direct French translation', 'observed_result' => 'Impossible',
                'explanation' => 'The contract cannot be satisfied.',
            ],
        ];
        self::assertTrue(ValidationPhase2Rules::validateFinding($finding, 'QCM_RECOGNITION', 'fr', str_repeat('a', 64), 1));
        unset($finding['evidence']['target_excerpts']);
        self::assertFalse(ValidationPhase2Rules::validateFinding($finding, 'QCM_RECOGNITION', 'fr', str_repeat('a', 64), 1));
    }

    public function test_empty_proof_and_qcm_only_choice_paths_are_rejected_for_true_false(): void
    {
        $base = ['field_path' => 'choices.c.text', 'rule_code' => 'MEANING_DRIFT', 'severity' => 'BLOCKING',
            'evidence' => ['expected_rule' => ' ', 'observed_result' => '', 'details' => '']];
        self::assertFalse(ValidationPhase2Rules::validateFinding($base, 'TRUE_FALSE_REASONING_TRUE', 'fr', str_repeat('a', 64), 1));
        $base['field_path'] = 'choices.a.text';
        $base['evidence'] = ['expected_rule' => 'Preserve meaning', 'observed_result' => 'Drift', 'source_excerpt' => 'English', 'target_excerpt' => 'Français'];
        self::assertTrue(ValidationPhase2Rules::validateFinding($base, 'TRUE_FALSE_REASONING_TRUE', 'fr', str_repeat('a', 64), 1));
    }

    public function test_schema_is_typed_and_proof_metadata_is_text(): void
    {
        self::assertSame('choices.[a-b].text', ValidationPhase2Rules::schema('TRUE_FALSE_REASONING_TRUE')['choice_field_path_pattern']);
        self::assertSame('choices.[a-d].text', ValidationPhase2Rules::schema('QCM_TRAP')['choice_field_path_pattern']);
        $finding = ['field_path' => 'question', 'rule_code' => 'ANSWER_KEY_CHANGED', 'severity' => 'BLOCKING',
            'evidence' => ['expected_rule' => true, 'observed_result' => 'drift', 'details' => 'x', 'source_excerpt' => 'a', 'target_excerpt' => 'b']];
        self::assertFalse(ValidationPhase2Rules::validateFinding($finding, 'QCM_TRAP', 'fr', str_repeat('a', 64), 1));
        $finding['evidence']['expected_rule'] = 'preserve key';
        self::assertTrue(ValidationPhase2Rules::validateFinding($finding, 'QCM_TRAP', 'fr', str_repeat('a', 64), 1));
        $finding['rule_code'] = 'DISTRACTOR_BECAME_TRUE';
        unset($finding['evidence']['target_excerpt']);
        self::assertFalse(ValidationPhase2Rules::validateFinding($finding, 'QCM_TRAP', 'fr', str_repeat('a', 64), 1));
        $finding['rule_code'] = 'CONTENT_UNTRANSLATABLE';
        $finding['field_path'] = 'translation';
        $finding['evidence'] = [
            'language_code' => 'fr',
            'source_revision' => str_repeat('a', 64),
            'translation_revision' => 1,
            'components_concerned' => [1],
            'contract_rules_in_conflict' => ['TARGET_LANGUAGE_INCORRECT'],
            'source_excerpts' => ['English'],
            'target_excerpts' => ['Français'],
            'expected_rule' => 'Direct translation',
            'observed_result' => 'Impossible',
            'explanation' => 'The contract cannot be satisfied.',
        ];
        self::assertFalse(ValidationPhase2Rules::validateFinding($finding, 'QCM_TRAP', 'fr', str_repeat('a', 64), 1));
    }
}