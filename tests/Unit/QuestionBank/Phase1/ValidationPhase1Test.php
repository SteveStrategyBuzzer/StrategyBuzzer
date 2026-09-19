<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use App\Services\QuestionBank\Phase1\Phase1ExecutionRepository;
use App\Services\QuestionBank\Phase1\ValidationPhase1;
use App\Services\QuestionBank\Phase1\ValidationPhase1Reviewer;
use App\Services\QuestionBank\Phase1\ValidationPhase1EntryBoundary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ValidationPhase1Test extends TestCase
{
    private KernelBlueprint $blueprint;
    private KernelBlueprintCognitiveSlotRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64);
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('subdomain_active')->nullable();
            $table->string('subject_active')->nullable();
            $table->text('dominant_idea_active')->nullable();
            $table->string('kernel_code_dd', 2)->nullable();
            $table->string('kernel_code_do', 3)->nullable();
            $table->string('kernel_code_sub', 3)->nullable();
            $table->string('kernel_code_suj', 3)->nullable();
            $table->string('kernel_code_ide', 3)->nullable();
            $table->string('kernel_code_vvvv', 4)->nullable();
            $table->string('kernel_code', 23)->nullable();
            $table->timestamps();
        });
        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table): void {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);
        });
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->json('source')->nullable();
            $table->json('creation_failure')->nullable();
            $table->json('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
            $table->json('validation_findings')->default('[]');
            $table->timestamps();
            $table->primary(['blueprint_id', 'cognitive_type']);
        });
        Schema::create('kernel_phase1_executions', function (Blueprint $table): void {
            $table->string('execution_id', 36)->primary();
            $table->string('blueprint_id', 36);
            $table->string('identity_revision', 64);
            $table->string('state', 16);
            $table->string('lease_token', 36);
            $table->json('result')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['blueprint_id', 'identity_revision']);
        });

        $this->blueprint = new KernelBlueprint();
        $this->blueprint->initializeBlueprintId('bp-validation-1');
        $this->blueprint->fillRotation(4, 'science');
        $this->blueprint->fillTaxonomy('Physique', 'Lumière', 'Réfraction');
        $this->blueprint->fillVvvv('0000');

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $this->blueprint->blueprint_id,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth' => $this->blueprint->depth,
            'domain_code' => $this->blueprint->domain,
            'subdomain_active' => $this->blueprint->subdomain_active,
            'subject_active' => $this->blueprint->subject_active,
            'dominant_idea_active' => $this->blueprint->dominant_idea_active,
            'kernel_code_dd' => $this->blueprint->kernel_code_dd,
            'kernel_code_do' => $this->blueprint->kernel_code_do,
            'kernel_code_sub' => $this->blueprint->kernel_code_sub,
            'kernel_code_suj' => $this->blueprint->kernel_code_suj,
            'kernel_code_ide' => $this->blueprint->kernel_code_ide,
            'kernel_code_vvvv' => $this->blueprint->kernel_code_vvvv,
            'kernel_code' => $this->blueprint->kernel_code,
        ]);
        DB::table('kernel_blueprint_request_refs')->insert([
            'request_reference' => 'validation-phase1:test',
            'blueprint_id' => $this->blueprint->blueprint_id,
        ]);

        $this->repository = new KernelBlueprintCognitiveSlotRepository();
        $this->repository->initializeEmptySlots($this->blueprint->blueprint_id);
        foreach (KernelBlueprint::COGNITIVE_TYPES as $index => $type) {
            $this->repository->writeCreated(
                $this->blueprint->blueprint_id,
                $type,
                $this->source($type, $index)
            );
        }
        $revision = (new Phase1ExecutionRepository())->currentIdentityRevision(
            $this->blueprint->blueprint_id
        );
        DB::table('kernel_phase1_executions')->insert([
            'execution_id' => 'phase1-validation-terminal',
            'blueprint_id' => $this->blueprint->blueprint_id,
            'identity_revision' => $revision,
            'state' => 'COMPLETED',
            'lease_token' => 'phase1-validation-lease',
            'result' => json_encode([
                'phase1_terminal' => Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED,
                'creation_status' => 'CREATED',
            ], JSON_THROW_ON_ERROR),
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_phase1_executions');
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
        Schema::dropIfExists('kernel_blueprint_request_refs');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_public_validation_boundary_accepts_only_blueprint_id(): void
    {
        $method = new \ReflectionMethod(ValidationPhase1::class, 'validate');

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('blueprintId', $method->getParameters()[0]->getName());
    }

    public function test_runs_seven_independent_reviews_then_one_limited_cross_review(): void
    {
        $reviewer = new RecordingReviewer($this->blueprint->kernel_code);
        $before = $this->repository->allForBlueprint($this->blueprint->blueprint_id);

        $result = $this->service($reviewer)->validate($this->blueprint->blueprint_id);

        $this->assertSame($this->blueprint->blueprint_id, $result);
        $this->assertCount(8, $reviewer->calls);
        $deep = array_values(array_filter(
            $reviewer->calls,
            static fn(array $call): bool => $call['review_level'] === 'deep'
        ));
        $cross = array_values(array_filter(
            $reviewer->calls,
            static fn(array $call): bool => $call['review_level'] === 'cross_slot'
        ));
        $this->assertCount(7, $deep);
        $this->assertCount(1, $cross);
        $this->assertSame(
            KernelBlueprint::COGNITIVE_TYPES,
            array_map(static fn(array $call): string => $call['slot']['cognitive_type'], $deep)
        );
        foreach ($deep as $call) {
            $this->assertSame(['depth', 'domain', 'subdomain_active', 'subject_active', 'dominant_idea_active', 'source_language'], array_keys($call['identity_context']));
            $this->assertArrayNotHasKey('slots', $call);
            $this->assertSame(
                ['cognitive_type', 'schema_version', 'source_language', 'question', 'choices', 'correct_answer_key', 'sv'],
                array_keys($call['slot'])
            );
            $this->assertTrue($call['validation_rules']['creation_evidence_is_not_proof']);
            $this->assertTrue($call['validation_rules']['self_checks_are_not_proof']);
            $this->assertCount(11, $call['validation_rules']['common']);
            $this->assertCount(9, $call['validation_rules']['sv']);
            $this->assertNotSame('', $call['validation_rules']['cognitive_type']);
            $this->assertSame(
                'SOURCE_SV_INVALID',
                $call['output_contract']['sv_finding']['reason_code']
            );
            $this->assertSame(
                'source.sv',
                $call['output_contract']['sv_finding']['field_path']
            );
            $this->assertTrue(
                $call['output_contract']['sv_finding']['applies_to_every_sv_rule']
            );
            $this->assertFalse($this->containsForbiddenEvidence($call));
        }
        $this->assertSame(['repetitions', 'mechanical_transformations'], $cross[0]['focus']);
        $this->assertTrue($cross[0]['output_contract']['no_individual_quality_reassessment']);
        $this->assertSame(
            [
                'SOURCE_CROSS_SLOT_DUPLICATE',
                'SOURCE_MECHANICAL_QCM_TF_CONVERSION',
                'SOURCE_MECHANICAL_TRUE_FALSE_NEGATION',
            ],
            $cross[0]['output_contract']['allowed_reason_codes']
        );
        $this->assertCount(7, $cross[0]['slots']);
        $this->assertSame(KernelBlueprint::COGNITIVE_TYPES, array_keys($cross[0]['slots']));
        $this->assertFalse($this->containsForbiddenEvidence($cross[0]));

        foreach ($this->repository->allForBlueprint($this->blueprint->blueprint_id) as $type => $slot) {
            $this->assertSame('PASS', $slot['validation_status'], $type);
            $this->assertSame([], $slot['validation_findings'], $type);
            $this->assertSame($before[$type]['source'], $slot['source']);
        }
    }

    /**
     * @dataProvider svDefectProvider
     */
    public function test_each_sv_defect_uses_only_source_sv_invalid(string $explanation): void
    {
        $reviewer = new RecordingReviewer(
            $this->blueprint->kernel_code,
            deepFindings: [
                'QCM_RECOGNITION' => [$this->finding(
                    'SOURCE_SV_INVALID',
                    ['source.sv'],
                    $explanation
                )],
            ]
        );

        $this->service($reviewer)->validate($this->blueprint->blueprint_id);
        $slot = $this->repository->find($this->blueprint->blueprint_id, 'QCM_RECOGNITION');

        $this->assertSame('SUSPICION', $slot['validation_status']);
        $this->assertSame('SOURCE_SV_INVALID', $slot['validation_findings'][0]['reason_code']);
        $this->assertSame(['source.sv'], $slot['validation_findings'][0]['field_paths']);
    }

    public function test_sv_defect_with_another_reason_code_is_rejected_and_never_passes(): void
    {
        $reviewer = new RecordingReviewer(
            $this->blueprint->kernel_code,
            deepFindings: [
                'QCM_RECOGNITION' => [$this->finding(
                    'SOURCE_CONTEXT_MISMATCH',
                    ['source.sv'],
                    'SV leaves the required context.'
                )],
            ]
        );

        $this->service($reviewer)->validate($this->blueprint->blueprint_id);
        $slot = $this->repository->find($this->blueprint->blueprint_id, 'QCM_RECOGNITION');
        $calls = array_filter(
            $reviewer->calls,
            static fn(array $call): bool => $call['review_level'] === 'deep'
                && $call['slot']['cognitive_type'] === 'QCM_RECOGNITION'
        );

        $this->assertCount(ValidationPhase1::MAX_TECHNICAL_ATTEMPTS, $calls);
        $this->assertSame('SUSPICION', $slot['validation_status']);
        $this->assertSame(
            'SOURCE_VALIDATION_TECHNICAL_FAILURE',
            $slot['validation_findings'][0]['reason_code']
        );
    }

    public static function svDefectProvider(): array
    {
        return [
            'no correctness explanation' => ['SV does not explain correctness.'],
            'no pedagogical justification' => ['SV has no useful pedagogical justification.'],
            'factual error' => ['SV contains a factual error.'],
            'context mismatch' => ['SV leaves subject, dominant idea and depth context.'],
            'false decisive error' => ['SV omits the decisive error in the false statement.'],
            'reasoning demonstration' => ['SV does not demonstrate the required reasoning.'],
            'trap confusion' => ['SV does not explain the plausible trap confusion.'],
            'understanding error' => ['SV permits an understanding error.'],
            'contradiction' => ['SV contradicts the question or answer.'],
        ];
    }

    public function test_cross_findings_are_limited_related_and_deduplicated(): void
    {
        $finding = $this->finding(
            'SOURCE_CROSS_SLOT_DUPLICATE',
            ['slots.question'],
            'The two questions repeat the same proposition.',
            ['QCM_RECOGNITION', 'QCM_REASONING']
        );
        $reviewer = new RecordingReviewer(
            $this->blueprint->kernel_code,
            crossFindings: [$finding, $finding]
        );

        $this->service($reviewer)->validate($this->blueprint->blueprint_id);
        $slots = $this->repository->allForBlueprint($this->blueprint->blueprint_id);

        $this->assertSame('SUSPICION', $slots['QCM_RECOGNITION']['validation_status']);
        $this->assertSame('SUSPICION', $slots['QCM_REASONING']['validation_status']);
        $this->assertSame('PASS', $slots['QCM_TRAP']['validation_status']);
        $this->assertCount(1, $slots['QCM_RECOGNITION']['validation_findings']);
        $this->assertCount(1, $slots['QCM_REASONING']['validation_findings']);
    }

    public function test_each_individual_finding_controls_only_its_slot_decision(): void
    {
        $deepFindings = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $deepFindings[$type] = [$this->finding(
                'SOURCE_FACTUAL_SUSPICION',
                ['source.question'],
                "Independent finding for {$type}."
            )];
        }
        $reviewer = new RecordingReviewer($this->blueprint->kernel_code, deepFindings: $deepFindings);

        $this->service($reviewer)->validate($this->blueprint->blueprint_id);
        foreach ($this->repository->allForBlueprint($this->blueprint->blueprint_id) as $type => $slot) {
            $this->assertSame('SUSPICION', $slot['validation_status'], $type);
            $this->assertCount(1, $slot['validation_findings'], $type);
        }
    }

    public function test_weak_choices_can_be_persisted_then_marked_suspicion(): void
    {
        $source = $this->repository->find($this->blueprint->blueprint_id, 'QCM_RECOGNITION')['source'];
        $source['choices']['b'] = 'A very long, punctuated comparative answer with poor concision; and extra wording.';
        DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprint->blueprint_id)
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['source' => json_encode($source, JSON_THROW_ON_ERROR)]);

        $reviewer = new RecordingReviewer(
            $this->blueprint->kernel_code,
            deepFindings: [
                'QCM_RECOGNITION' => [$this->finding(
                    'SOURCE_CHOICE_NOT_CONCISE',
                    ['source.choices.b'],
                    'Choice is technically persistable but intellectually weak.'
                )],
            ]
        );
        $this->service($reviewer)->validate($this->blueprint->blueprint_id);

        $this->assertSame(
            'SUSPICION',
            $this->repository->find($this->blueprint->blueprint_id, 'QCM_RECOGNITION')['validation_status']
        );
    }

    public function test_divergent_identity_is_never_quality_context_and_does_not_mutate_source(): void
    {
        $beforeRuns = DB::table('kernel_blueprint_runs')->where('blueprint_id', $this->blueprint->blueprint_id)->first();
        $beforeSlots = $this->repository->allForBlueprint($this->blueprint->blueprint_id);
        $reviewer = new RecordingReviewer('different-kernel-code');

        $result = $this->service($reviewer)->validate($this->blueprint->blueprint_id);

        $this->assertSame($this->blueprint->blueprint_id, $result);
        $this->assertSame(ValidationPhase1::MAX_TECHNICAL_ATTEMPTS * 8, count($reviewer->calls));
        $this->assertEquals($beforeRuns, DB::table('kernel_blueprint_runs')->where('blueprint_id', $this->blueprint->blueprint_id)->first());
        foreach ($this->repository->allForBlueprint($this->blueprint->blueprint_id) as $type => $slot) {
            $this->assertSame($beforeSlots[$type]['source'], $slot['source']);
            $this->assertSame($beforeSlots[$type]['translations'], $slot['translations']);
            $this->assertSame('SUSPICION', $slot['validation_status']);
            $this->assertSame('SOURCE_VALIDATION_TECHNICAL_FAILURE', $slot['validation_findings'][0]['reason_code']);
        }
    }

    public function test_divergent_persisted_kernel_code_stales_phase1_terminal_before_review(): void
    {
        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $this->blueprint->blueprint_id)
            ->update(['kernel_code' => '04-SCI-BAD-BAD-BAD-0000']);
        $reviewer = new RecordingReviewer($this->blueprint->kernel_code);

        try {
            $this->service($reviewer)->validate($this->blueprint->blueprint_id);
            $this->fail('A divergent persisted kernel_code must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Phase 1 non terminale', $exception->getMessage());
        }
        $this->assertCount(0, $reviewer->calls);
    }

    public function test_validation_changes_only_status_and_findings_columns(): void
    {
        $before = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprint->blueprint_id)
            ->orderBy('cognitive_type')
            ->get()
            ->map(static fn(object $row): array => (array) $row)
            ->all();

        $this->service(new RecordingReviewer($this->blueprint->kernel_code))
            ->validate($this->blueprint->blueprint_id);

        $after = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprint->blueprint_id)
            ->orderBy('cognitive_type')
            ->get()
            ->map(static fn(object $row): array => (array) $row)
            ->all();

        foreach ($before as $index => $row) {
            unset($row['validation_status'], $row['validation_findings']);
            $afterRow = $after[$index];
            unset($afterRow['validation_status'], $afterRow['validation_findings']);
            $this->assertSame($row, $afterRow);
        }
    }

    public function test_reviewer_technical_failure_retries_three_times_and_never_defaults_pass(): void
    {
        $reviewer = new RecordingReviewer(
            $this->blueprint->kernel_code,
            invalidDeepTypes: ['QCM_TRAP']
        );

        $this->service($reviewer)->validate($this->blueprint->blueprint_id);
        $trapCalls = array_filter(
            $reviewer->calls,
            static fn(array $call): bool => $call['review_level'] === 'deep'
                && $call['slot']['cognitive_type'] === 'QCM_TRAP'
        );
        $this->assertCount(ValidationPhase1::MAX_TECHNICAL_ATTEMPTS, $trapCalls);
        $trap = $this->repository->find($this->blueprint->blueprint_id, 'QCM_TRAP');
        $this->assertSame('SUSPICION', $trap['validation_status']);
        $this->assertSame('SOURCE_VALIDATION_TECHNICAL_FAILURE', $trap['validation_findings'][0]['reason_code']);
    }

    public function test_cross_review_exhaustion_preserves_individual_reviews_and_marks_all_slots(): void
    {
        $reviewer = new RecordingReviewer($this->blueprint->kernel_code, crossFailure: true);

        $this->service($reviewer)->validate($this->blueprint->blueprint_id);
        foreach ($this->repository->allForBlueprint($this->blueprint->blueprint_id) as $slot) {
            $this->assertSame('SUSPICION', $slot['validation_status']);
            $this->assertSame('SOURCE_VALIDATION_TECHNICAL_FAILURE', $slot['validation_findings'][0]['reason_code']);
        }
        $this->assertCount(ValidationPhase1::MAX_TECHNICAL_ATTEMPTS, array_filter(
            $reviewer->calls,
            static fn(array $call): bool => $call['review_level'] === 'cross_slot'
        ));
    }

    public function test_repository_validation_write_is_atomic_idempotent_and_rejects_divergent_replay(): void
    {
        $decisions = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $decisions[$type] = [
                'validation_status' => 'PASS',
                'validation_findings' => [],
            ];
        }
        $this->repository->writeValidationResults($this->blueprint->blueprint_id, $decisions);
        $afterFirst = DB::table('kernel_blueprint_cognitive_slots')->get()->keyBy('cognitive_type')->all();

        $this->repository->writeValidationResults($this->blueprint->blueprint_id, $decisions);
        $afterReplay = DB::table('kernel_blueprint_cognitive_slots')->get()->keyBy('cognitive_type')->all();
        $this->assertEquals($afterFirst, $afterReplay);

        $divergent = $decisions;
        $divergent['QCM_TRAP'] = [
            'validation_status' => 'SUSPICION',
            'validation_findings' => [$this->finding('SOURCE_FACTUAL_SUSPICION', ['question'], 'Different decision.')],
        ];
        $this->expectException(LogicException::class);
        $this->repository->writeValidationResults($this->blueprint->blueprint_id, $divergent);
    }

    public function test_service_terminal_replay_returns_same_id_without_reviewer_calls(): void
    {
        $reviewer = new RecordingReviewer($this->blueprint->kernel_code);
        $service = $this->service($reviewer);
        $this->assertSame(
            $this->blueprint->blueprint_id,
            $service->validate($this->blueprint->blueprint_id)
        );
        $this->assertCount(8, $reviewer->calls);

        $reviewer->calls = [];
        $this->assertSame(
            $this->blueprint->blueprint_id,
            $service->validate($this->blueprint->blueprint_id)
        );
        $this->assertCount(0, $reviewer->calls);
    }

    public function test_repository_rolls_back_if_a_slot_update_fails_midway(): void
    {
        $repository = new ThrowingValidationRepository(2);
        $decisions = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $decisions[$type] = ['validation_status' => 'PASS', 'validation_findings' => []];
        }

        try {
            $repository->writeValidationResults($this->blueprint->blueprint_id, $decisions);
            $this->fail('Expected the injected second-slot failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced validation write failure', $exception->getMessage());
        }

        foreach ($this->repository->allForBlueprint($this->blueprint->blueprint_id) as $slot) {
            $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
            $this->assertSame([], $slot['validation_findings']);
        }
    }

    private function service(RecordingReviewer $reviewer, ?KernelBlueprintCognitiveSlotRepository $repository = null): ValidationPhase1
    {
        return new ValidationPhase1(
            $reviewer,
            $repository ?? $this->repository,
            new ValidationPhase1EntryBoundary()
        );
    }

    /** @return array<string, mixed> */
    private function source(string $type, int $index): array
    {
        $isQcm = str_starts_with($type, 'QCM_');
        return [
            'schema_version' => 'phase1.source.v1',
            'source_language' => 'en',
            'cognitive_type' => $type,
            'question' => "Question source distincte {$index} ?",
            'choices' => $isQcm
                ? ['a' => 'Paris', 'b' => 'Rome', 'c' => 'Madrid', 'd' => 'Berlin']
                : ['a' => 'TRUE', 'b' => 'FALSE'],
            'correct_answer_key' => $isQcm || str_ends_with($type, '_TRUE') ? 'a' : 'b',
            'sv' => 'Cette explication relie la réponse au contexte.',
            'creation_evidence' => [
                'cognitive_operation' => 'operation',
                'cognitive_justification' => 'justification',
                'difference_from_other_slots' => 'difference',
                'truth_basis' => 'basis',
                'trap_basis' => $type === 'QCM_TRAP' ? 'plausible confusion' : null,
                'self_checks' => array_fill_keys([
                    'question_readable_under_8_seconds',
                    'sv_readable_under_30_seconds',
                    'correct_answer_explained_by_sv',
                    'cognitive_type_respected',
                    'one_correct_answer_only',
                    'choices_are_plausible',
                    'distinct_from_other_slots',
                    'same_subject_and_dominant_idea',
                    'question_answer_choices_sv_coherent_with_subdomain',
                ], false),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function finding(string $reason, array $paths, string $explanation, ?array $related = null): array
    {
        $finding = [
            'reason_code' => $reason,
            'field_paths' => $paths,
            'explanation' => $explanation,
            'evidence' => ['test' => true],
        ];
        if ($related !== null) {
            $finding['related_cognitive_types'] = $related;
        }
        return $finding;
    }

    private function containsForbiddenEvidence(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                if (in_array((string) $key, ['creation_evidence', 'self_checks'], true)) {
                    return true;
                }
                if ($this->containsForbiddenEvidence($child)) {
                    return true;
                }
            }
        }
        return false;
    }
}

final class RecordingReviewer implements ValidationPhase1Reviewer
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function __construct(
        private readonly string $kernelCode,
        private readonly array $deepFindings = [],
        private readonly array $crossFindings = [],
        private readonly array $invalidDeepTypes = [],
        private readonly bool $crossFailure = false,
    ) {}

    public function review(array $input): array
    {
        $this->calls[] = $input;
        if ($input['review_level'] === 'cross_slot') {
            if ($this->crossFailure) {
                throw new RuntimeException('cross transport failure');
            }
            return [
                'schema_version' => ValidationPhase1::SCHEMA_VERSION,
                'blueprint_id' => $input['blueprint_id'],
                'kernel_code' => $this->kernelCode,
                'cross_slot_findings' => $this->crossFindings,
            ];
        }

        $type = $input['slot']['cognitive_type'];
        if (in_array($type, $this->invalidDeepTypes, true)) {
            return ['not' => 'a valid reviewer envelope'];
        }
        $findings = $this->deepFindings[$type] ?? [];
        return [
            'schema_version' => ValidationPhase1::SCHEMA_VERSION,
            'blueprint_id' => $input['blueprint_id'],
            'kernel_code' => $this->kernelCode,
            'slots' => [[
                'cognitive_type' => $type,
                'decision' => $findings === [] ? 'PASS' : 'SUSPICION',
                'findings' => $findings,
            ]],
        ];
    }
}

final class ThrowingValidationRepository extends KernelBlueprintCognitiveSlotRepository
{
    private int $calls = 0;

    public function __construct(private readonly int $failOnCall)
    {
    }

    protected function beforeValidationSlotUpdate(string $blueprintId, string $cognitiveType): void
    {
        $this->calls++;
        if ($this->calls === $this->failOnCall) {
            throw new RuntimeException('forced validation write failure');
        }
    }
}