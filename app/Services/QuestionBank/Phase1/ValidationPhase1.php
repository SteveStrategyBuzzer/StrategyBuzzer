<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Independent, two-level ValidationPhase1 engine.
 *
 * Level one reviews every slot independently. Level two is intentionally
 * narrow: it can only report repetitions and mechanical transformations.
 * Neither level receives creation_evidence or self_checks.
 */
class ValidationPhase1
{
    public const SCHEMA_VERSION = 'validation-phase1.v1';
    public const MAX_TECHNICAL_ATTEMPTS = 3;

    /** @var array<int, string> */
    private const DEEP_REASON_CODES = [
        'SOURCE_MULTIPLE_CORRECT_ANSWERS',
        'SOURCE_CHOICE_NOT_CONCISE',
        'SOURCE_CHOICE_MULTIPLE_IDEAS',
        'SOURCE_CHOICES_HETEROGENEOUS',
        'SOURCE_FACTUAL_SUSPICION',
        'SOURCE_ANSWER_INCOHERENT',
        'SOURCE_DISTRACTOR_INVALID',
        'SOURCE_AMBIGUOUS',
        'SOURCE_SV_INVALID',
        'SOURCE_CONTEXT_MISMATCH',
        'SOURCE_COGNITIVE_MECHANISM_MISMATCH',
        'SOURCE_TRAP_UNFAIR',
    ];

    /** @var array<int, string> */
    private const CROSS_REASON_CODES = [
        'SOURCE_CROSS_SLOT_DUPLICATE',
        'SOURCE_MECHANICAL_QCM_TF_CONVERSION',
        'SOURCE_MECHANICAL_TRUE_FALSE_NEGATION',
    ];

    private const COMMON_RULES = [
        'question_and_correct_answer_are_factually_accurate',
        'correct_answer_answers_the_question_exactly',
        'no_second_correct_answer',
        'no_ambiguity',
        'distractors_are_plausible_and_not_correct',
        'qcm_choices_are_concise_single_ideas',
        'qcm_choices_have_comparable_semantic_category_grammar_and_concision',
        'no_artificial_filler',
        'question_answer_choices_and_sv_match_the_full_identity_context',
        'cognitive_mechanism_matches_cognitive_type',
        'trap_is_fair_content_based_and_not_typographical',
    ];

    private const SV_RULES = [
        'explains_why_the_correct_answer_is_correct',
        'provides_useful_pedagogical_justification',
        'is_factually_accurate',
        'respects_subject_dominant_idea_and_depth',
        'explains_the_decisive_error_for_false_statements',
        'demonstrates_the_reasoning_for_reasoning_slots',
        'explains_the_plausible_confusion_for_qcm_trap',
        'allows_the_learner_to_understand_the_error',
        'does_not_contradict_the_question_or_correct_answer',
    ];

    private const COGNITIVE_RULES = [
        'QCM_RECOGNITION' => 'direct_factual_recall_without_inference',
        'QCM_REASONING' => 'causal_comparative_consequential_or_deductive_link',
        'QCM_TRAP' => 'plausible_content_confusion_without_wording_or_typographical_trick',
        'TRUE_FALSE_RECOGNITION_TRUE' => 'atomic_entirely_true_directly_recognizable_fact',
        'TRUE_FALSE_RECOGNITION_FALSE' => 'plausible_false_atomic_fact_with_one_clear_decisive_error',
        'TRUE_FALSE_REASONING_TRUE' => 'true_logical_relation_requiring_reasoning',
        'TRUE_FALSE_REASONING_FALSE' => 'plausible_but_incorrect_logical_link_not_simple_fact_replacement',
    ];

    public function __construct(
        private readonly ValidationPhase1Reviewer $reviewer,
        private readonly KernelBlueprintCognitiveSlotRepository $slots =
            new KernelBlueprintCognitiveSlotRepository(),
        private readonly ValidationPhase1EntryBoundary $entry =
            new ValidationPhase1EntryBoundary(),
        private readonly Phase1ExecutionRepository $executions =
            new Phase1ExecutionRepository(),
    ) {}

    /**
     * The phase boundary carries only blueprint_id in and out.
     */
    public function validate(string $blueprintId): string
    {
        $phase1Terminal = $this->executions->validationPrerequisites($blueprintId);
        $blueprint = $this->entry->receive($blueprintId);
        $kernelCode = $this->persistedKernelCode($blueprintId, $blueprint);
        $persistedSlots = $this->slots->allForBlueprint($blueprintId);
        $this->assertCompleteCreatedSlots($persistedSlots, $blueprintId);
        if ($this->isExactTerminalReplay($persistedSlots, $blueprintId)) {
            return $blueprintId;
        }

        $projections = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $projections[$type] = $this->sourceProjection($persistedSlots[$type]['source'], $type);
        }

        $findings = array_fill_keys(KernelBlueprint::COGNITIVE_TYPES, []);
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $input = [
                'schema_version' => self::SCHEMA_VERSION,
                'review_level' => 'deep',
                'blueprint_id' => $blueprintId,
                'kernel_code' => $kernelCode,
                'identity_context' => $this->context($blueprint),
                'slot' => $projections[$type],
                'validation_rules' => [
                    'common' => self::COMMON_RULES,
                    'cognitive_type' => self::COGNITIVE_RULES[$type],
                    'sv' => self::SV_RULES,
                    'creation_evidence_is_not_proof' => true,
                    'self_checks_are_not_proof' => true,
                ],
                'output_contract' => $this->deepOutputContract(),
            ];
            $response = $this->reviewWithRetries(
                $input,
                fn(array $candidate): array => $this->deepReview(
                    $candidate,
                    $type,
                    $blueprintId,
                    $kernelCode
                )
            );
            if ($response === null) {
                $findings[$type][] = $this->technicalFailureFinding("slot.{$type}");
                continue;
            }
            $review = $this->deepReview($response, $type, $blueprintId, $kernelCode);
            $findings[$type] = $review['findings'];
        }

        $crossInput = [
            'schema_version' => self::SCHEMA_VERSION,
            'review_level' => 'cross_slot',
            'focus' => [
                'repetitions',
                'mechanical_transformations',
            ],
            'blueprint_id' => $blueprintId,
            'kernel_code' => $kernelCode,
            'identity_context' => $this->context($blueprint),
            'slots' => $projections,
            'output_contract' => $this->crossOutputContract(),
        ];
        $crossResponse = $this->reviewWithRetries(
            $crossInput,
            fn(array $candidate): array => $this->crossReview(
                $candidate,
                $blueprintId,
                $kernelCode
            )
        );
        if ($crossResponse === null) {
            foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
                $findings[$type][] = $this->technicalFailureFinding('slots');
            }
        } else {
            foreach ($this->crossReview(
                $crossResponse,
                $blueprintId,
                $kernelCode
            ) as $type => $crossFindings) {
                $findings[$type] = $this->dedupeFindings(array_merge($findings[$type], $crossFindings));
            }
        }

        $decisions = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $slotFindings = $this->dedupeFindings($findings[$type]);
            $decisions[$type] = [
                'validation_status' => $slotFindings === [] ? 'PASS' : 'SUSPICION',
                'validation_findings' => $slotFindings,
            ];
        }
        $writeResults = fn(): mixed => $this->slots->writeValidationResults(
            $blueprintId,
            $decisions,
        );
        $this->executions->withCompletedPhase1(
            $blueprintId,
            $phase1Terminal['identity_revision'],
            $phase1Terminal['slots_revision'],
            $writeResults,
        );

        return $blueprintId;
    }

    /**
     * Invalid envelopes and reviewer transport exceptions are technical
     * failures. They are retried three times, never treated as PASS.
     *
     * @param array<string, mixed> $input
     * @param callable(array<string, mixed>): mixed|null $validator
     * @return array<string, mixed>|null
     */
    private function reviewWithRetries(array $input, ?callable $validator = null): ?array
    {
        for ($attempt = 1; $attempt <= self::MAX_TECHNICAL_ATTEMPTS; $attempt++) {
            try {
                $response = $this->reviewer->review($input);
                if (! is_array($response)) {
                    throw new LogicException('[ValidationPhase1] Réponse reviewer non tableau.');
                }

                if ($validator !== null) {
                    $validator($response);
                }

                return $response;
            } catch (\Throwable) {
                // Retry invalid envelopes and transport failures alike.
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function technicalFailureFinding(string $fieldPath): array
    {
        return [
            'reason_code' => 'SOURCE_VALIDATION_TECHNICAL_FAILURE',
            'field_paths' => [$fieldPath],
            'explanation' => 'La revue indépendante a épuisé ses trois tentatives techniques.',
            'evidence' => ['max_attempts' => self::MAX_TECHNICAL_ATTEMPTS],
        ];
    }

    private function persistedKernelCode(string $blueprintId, KernelBlueprint $blueprint): string
    {
        $kernelCode = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)
            ->value('kernel_code');

        if (! is_string($kernelCode)
            || $kernelCode === ''
            || $kernelCode !== $blueprint->kernel_code) {
            throw new LogicException(
                "[ValidationPhase1] kernel_code persistant divergent: {$blueprintId}."
            );
        }

        return $kernelCode;
    }

    /** @return array<string, mixed> */
    private function deepOutputContract(): array
    {
        return [
            'decision_values' => ['PASS', 'SUSPICION'],
            'pass_requires_zero_findings' => true,
            'suspicion_requires_findings' => true,
            'allowed_reason_codes' => self::DEEP_REASON_CODES,
            'finding_fields' => [
                'reason_code',
                'field_paths',
                'explanation',
                'evidence',
            ],
            'sv_finding' => [
                'reason_code' => 'SOURCE_SV_INVALID',
                'field_path' => 'source.sv',
                'applies_to_every_sv_rule' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function crossOutputContract(): array
    {
        return [
            'allowed_reason_codes' => self::CROSS_REASON_CODES,
            'finding_fields' => [
                'reason_code',
                'field_paths',
                'explanation',
                'evidence',
                'related_cognitive_types',
            ],
            'related_cognitive_types_required' => true,
            'no_individual_quality_reassessment' => true,
        ];
    }

    public function run(string $blueprintId): string
    {
        return $this->validate($blueprintId);
    }

    /**
     * @param array<string, array<string, mixed>> $persistedSlots
     */
    private function assertCompleteCreatedSlots(array $persistedSlots, string $blueprintId): void
    {
        $actual = array_keys($persistedSlots);
        sort($actual);
        $expected = KernelBlueprint::COGNITIVE_TYPES;
        sort($expected);
        if ($actual !== $expected) {
            throw new LogicException("[ValidationPhase1] Sept slots requis: {$blueprintId}.");
        }
        foreach ($expected as $type) {
            if (($persistedSlots[$type]['creation_status'] ?? null) !== 'CREATED') {
                throw new LogicException("[ValidationPhase1] Slot non CREATED: {$type}.");
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $persistedSlots
     */
    private function isExactTerminalReplay(array $persistedSlots, string $blueprintId): bool
    {
        $terminal = 0;
        foreach ($persistedSlots as $slot) {
            $status = $slot['validation_status'] ?? null;
            $findings = $slot['validation_findings'] ?? null;
            if ($status === 'NOT_VALIDATED' && $findings === []) {
                continue;
            }
            if (($status === 'PASS' && $findings === [])
                || ($status === 'SUSPICION' && is_array($findings) && $findings !== [])) {
                $terminal++;
                continue;
            }
            throw new LogicException(
                "[ValidationPhase1] État de validation persistant incohérent: {$blueprintId}."
            );
        }

        if ($terminal !== 0 && $terminal !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new LogicException(
                "[ValidationPhase1] Persistance terminale partielle interdite: {$blueprintId}."
            );
        }

        return $terminal === count(KernelBlueprint::COGNITIVE_TYPES);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function sourceProjection(array $source, string $type): array
    {
        // Explicit fields make it impossible for evidence to cross the
        // reviewer boundary, even if a future source schema gains metadata.
        return [
            'cognitive_type' => $type,
            'schema_version' => $source['schema_version'] ?? null,
            'source_language' => $source['source_language'] ?? null,
            'question' => $source['question'] ?? null,
            'choices' => $source['choices'] ?? null,
            'correct_answer_key' => $source['correct_answer_key'] ?? null,
            'sv' => $source['sv'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function context(KernelBlueprint $blueprint): array
    {
        return [
            'depth' => $blueprint->depth,
            'domain' => $blueprint->domain,
            'subdomain_active' => $blueprint->subdomain_active,
            'subject_active' => $blueprint->subject_active,
            'dominant_idea_active' => $blueprint->dominant_idea_active,
            'source_language' => 'en',
        ];
    }

    /**
     * @return array{findings: array<int, array<string, mixed>>}
     */
    private function deepReview(
        array $response,
        string $expectedType,
        string $blueprintId,
        mixed $kernelCode
    ): array {
        $this->assertReviewIdentity($response, $blueprintId, $kernelCode);
        if (! is_array($response['slots'] ?? null) || count($response['slots']) !== 1) {
            throw new LogicException('[ValidationPhase1] Revue profonde doit cibler un slot.');
        }
        $item = array_values($response['slots'])[0];
        if (! is_array($item)
            || ! is_string($item['cognitive_type'] ?? null)
            || ! in_array($item['cognitive_type'], KernelBlueprint::COGNITIVE_TYPES, true)
            || $item['cognitive_type'] !== $expectedType) {
            throw new LogicException('[ValidationPhase1] Type cognitif reviewer divergent.');
        }
        $findings = $this->normalizeFindings(
            $item['findings'] ?? null,
            self::DEEP_REASON_CODES
        );
        $this->assertDecision($item['decision'] ?? null, $findings);

        return ['findings' => $findings];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function crossReview(array $response, string $blueprintId, mixed $kernelCode): array
    {
        $this->assertReviewIdentity($response, $blueprintId, $kernelCode);
        $byType = array_fill_keys(KernelBlueprint::COGNITIVE_TYPES, []);
        $raw = $response['cross_slot_findings'] ?? null;
        if (! is_array($raw)) {
            throw new LogicException('[ValidationPhase1] Sortie cross-slot invalide.');
        }

        // Also accept the envelope's per-slot form, while keeping the scope
        // of every finding limited to the two allowed cross-review reasons.
        foreach ($raw as $finding) {
            if (! is_array($finding)) {
                throw new LogicException('[ValidationPhase1] Finding cross-slot invalide.');
            }
            $related = $finding['related_cognitive_types'] ?? null;
            if (! is_array($related) || $related === []) {
                throw new LogicException('[ValidationPhase1] Finding cross-slot sans types liés.');
            }
            $normalized = $this->normalizeFindings([$finding], self::CROSS_REASON_CODES)[0];
            foreach ($related as $type) {
                if (! in_array($type, KernelBlueprint::COGNITIVE_TYPES, true)) {
                    throw new LogicException('[ValidationPhase1] Type lié non officiel.');
                }
                $byType[$type][] = $normalized;
            }
        }
        return $byType;
    }

    private function assertReviewIdentity(array $response, string $blueprintId, mixed $kernelCode): void
    {
        if (($response['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ($response['blueprint_id'] ?? null) !== $blueprintId
            || ! array_key_exists('kernel_code', $response)
            || $response['kernel_code'] !== $kernelCode) {
            throw new LogicException('[ValidationPhase1] schema_version reviewer invalide.');
        }
    }

    /**
     * @param mixed $raw
     * @param array<int, string>|null $allowedCodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFindings(mixed $raw, ?array $allowedCodes = null): array
    {
        if (! is_array($raw)) {
            throw new LogicException('[ValidationPhase1] findings reviewer invalides.');
        }
        $normalized = [];
        foreach ($raw as $finding) {
            if (! is_array($finding)
                || ! is_string($finding['reason_code'] ?? null)
                || ! is_array($allowedCodes)
                || ! in_array($finding['reason_code'], $allowedCodes, true)
                || ! is_array($finding['field_paths'] ?? null)
                || ! is_string($finding['explanation'] ?? null)
                || trim($finding['explanation']) === ''
                || ! array_key_exists('evidence', $finding)) {
                throw new LogicException('[ValidationPhase1] Finding reviewer non conforme.');
            }
            foreach ($finding['field_paths'] as $path) {
                if (! is_string($path) || trim($path) === '') {
                    throw new LogicException('[ValidationPhase1] field_paths reviewer invalide.');
                }
            }
            $targetsSv = (bool) array_filter(
                $finding['field_paths'],
                static fn(string $path): bool => preg_match('/(?:^|[.\[])sv(?:$|[.\]])/i', $path) === 1
            );
            if ($targetsSv && $finding['reason_code'] !== 'SOURCE_SV_INVALID') {
                throw new LogicException('[ValidationPhase1] Tout défaut SV doit utiliser SOURCE_SV_INVALID.');
            }
            if ($finding['reason_code'] === 'SOURCE_SV_INVALID' && ! $targetsSv) {
                throw new LogicException('[ValidationPhase1] SOURCE_SV_INVALID doit cibler sv.');
            }
            $item = [
                'reason_code' => $finding['reason_code'],
                'field_paths' => array_values(array_unique($finding['field_paths'])),
                'explanation' => trim($finding['explanation']),
                'evidence' => $finding['evidence'],
            ];
            sort($item['field_paths']);
            if (array_key_exists('related_cognitive_types', $finding)) {
                if (! is_array($finding['related_cognitive_types'])) {
                    throw new LogicException('[ValidationPhase1] Types liés reviewer invalides.');
                }
                $related = array_values(array_unique($finding['related_cognitive_types']));
                sort($related);
                foreach ($related as $type) {
                    if (! is_string($type) || ! in_array($type, KernelBlueprint::COGNITIVE_TYPES, true)) {
                        throw new LogicException('[ValidationPhase1] Type lié non officiel.');
                    }
                }
                $item['related_cognitive_types'] = $related;
            }
            $normalized[] = $item;
        }
        return $this->dedupeFindings($normalized);
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function dedupeFindings(array $findings): array
    {
        $unique = [];
        foreach ($findings as $finding) {
            $key = json_encode($finding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($key)) {
                $unique[$key] = $finding;
            }
        }
        return array_values($unique);
    }

    /** @param mixed $decision @param array<int, array<string, mixed>> $findings */
    private function assertDecision(mixed $decision, array $findings): void
    {
        if (! in_array($decision, ['PASS', 'SUSPICION'], true)
            || ($decision === 'PASS') !== ($findings === [])) {
            throw new LogicException('[ValidationPhase1] Décision reviewer incohérente avec findings.');
        }
    }
}
