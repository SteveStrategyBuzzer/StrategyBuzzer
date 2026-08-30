<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Phase1\KernelPhase1SourceValidator;
use App\Services\QuestionBank\Phase1\Phase1TechnicalException;
use PHPUnit\Framework\TestCase;

class KernelPhase1SourceValidatorTest extends TestCase
{
    private KernelPhase1SourceValidator $validator;
    private KernelBlueprint $blueprint;

    protected function setUp(): void
    {
        $this->validator = new KernelPhase1SourceValidator();
        $this->blueprint = new KernelBlueprint();
        $this->blueprint->initializeBlueprintId('bp-phase1');
        $this->blueprint->fillRotation(4, 'science');
        $this->blueprint->fillTaxonomy('Physique', 'Lumière', 'Réfraction');
        $this->blueprint->fillKernelCode($this->blueprint->kernelCodePrefix() . '-0000');
    }

    public function test_validates_exactly_seven_slots_with_canonical_qcm_answers(): void
    {
        $result = $this->validator->validate($this->blueprint, $this->payload());

        $this->assertCount(7, $result['valid']);
        $this->assertSame([], $result['invalid']);
        foreach (['QCM_RECOGNITION', 'QCM_REASONING', 'QCM_TRAP'] as $type) {
            $this->assertSame('a', $result['valid'][$type]['correct_answer_key']);
            $this->assertSame(['a', 'b', 'c', 'd'], array_keys($result['valid'][$type]['choices']));
        }
    }

    public function test_enforces_true_false_polarities_and_labels(): void
    {
        $result = $this->validator->validate($this->blueprint, $this->payload());

        $this->assertSame('a', $result['valid']['TRUE_FALSE_RECOGNITION_TRUE']['correct_answer_key']);
        $this->assertSame('b', $result['valid']['TRUE_FALSE_RECOGNITION_FALSE']['correct_answer_key']);
        $this->assertSame('a', $result['valid']['TRUE_FALSE_REASONING_TRUE']['correct_answer_key']);
        $this->assertSame('b', $result['valid']['TRUE_FALSE_REASONING_FALSE']['correct_answer_key']);
        $this->assertSame(
            ['a' => 'VRAI', 'b' => 'FAUX'],
            $result['valid']['TRUE_FALSE_REASONING_FALSE']['choices']
        );
    }

    public function test_rejects_identity_mismatch(): void
    {
        $payload = $this->payload();
        $payload['kernel_code'] = '04-SCI-BAD-BAD-BAD-0000';

        $this->expectException(Phase1TechnicalException::class);
        $this->expectExceptionMessageMatches('/diverge/');
        $this->validator->validate($this->blueprint, $payload);
    }

    public function test_marks_question_over_eight_seconds_invalid(): void
    {
        $payload = $this->payload();
        $payload['slots'][0]['question'] = implode(' ', array_fill(0, 21, 'mot'));

        $result = $this->validator->validate($this->blueprint, $payload);

        $this->assertArrayHasKey('QCM_RECOGNITION', $result['invalid']);
        $this->assertStringContainsString('huit secondes', $result['invalid']['QCM_RECOGNITION']);
    }

    public function test_marks_sv_over_thirty_seconds_invalid(): void
    {
        $payload = $this->payload();
        $payload['slots'][1]['sv'] = implode(' ', array_fill(0, 76, 'mot'));

        $result = $this->validator->validate($this->blueprint, $payload);

        $this->assertArrayHasKey('QCM_REASONING', $result['invalid']);
        $this->assertStringContainsString('trente secondes', $result['invalid']['QCM_REASONING']);
    }

    public function test_rejects_long_or_heterogeneous_qcm_choices(): void
    {
        $payload = $this->payload();
        $payload['slots'][2]['choices'][1]['text'] =
            'Une phrase beaucoup trop longue qui combine plusieurs idées incompatibles';

        $result = $this->validator->validate($this->blueprint, $payload);

        $this->assertArrayHasKey('QCM_TRAP', $result['invalid']);
        $this->assertStringContainsString('unité courte', $result['invalid']['QCM_TRAP']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $slots = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $index => $type) {
            $isQcm = str_starts_with($type, 'QCM_');
            $slots[] = [
                'cognitive_type' => $type,
                'question' => "Question cognitive distincte numéro {$index}?",
                'choices' => $isQcm
                    ? [
                        ['key' => 'a', 'text' => 'Paris'],
                        ['key' => 'b', 'text' => 'Rome'],
                        ['key' => 'c', 'text' => 'Madrid'],
                        ['key' => 'd', 'text' => 'Berlin'],
                    ]
                    : [
                        ['key' => 'a', 'text' => 'VRAI'],
                        ['key' => 'b', 'text' => 'FAUX'],
                    ],
                'correct_answer_key' => $isQcm || str_ends_with($type, '_TRUE') ? 'a' : 'b',
                'sv' => 'Cette explication courte relie clairement la réponse au contexte scientifique.',
                'creation_evidence' => [
                    'cognitive_operation' => 'Opération distincte',
                    'cognitive_justification' => 'Justification déterministe',
                    'difference_from_other_slots' => 'Proposition intellectuelle indépendante',
                    'truth_basis' => 'Fondement factuel vérifiable',
                    'trap_basis' => $type === 'QCM_TRAP' ? 'Confusion plausible documentée' : null,
                    'self_checks' => [
                        'question_readable_under_8_seconds' => true,
                        'sv_readable_under_30_seconds' => true,
                        'correct_answer_explained_by_sv' => true,
                        'cognitive_type_respected' => true,
                        'one_correct_answer_only' => true,
                        'choices_are_plausible' => true,
                        'distinct_from_other_slots' => true,
                        'same_subject_and_dominant_idea' => true,
                        'question_answer_choices_sv_coherent_with_subdomain' => true,
                    ],
                ],
            ];
        }

        return [
            'schema_version' => 'phase1.source.v1',
            'blueprint_id' => $this->blueprint->blueprint_id,
            'kernel_code' => $this->blueprint->kernel_code,
            'source_language' => 'fr',
            'slots' => $slots,
        ];
    }
}