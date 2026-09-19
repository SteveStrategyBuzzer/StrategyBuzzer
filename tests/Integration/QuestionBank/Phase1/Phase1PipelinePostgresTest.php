<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Phase1;

use App\Services\QuestionApi\QuestionApiClient;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Phase1\KernelPhase1Generator;
use App\Services\QuestionBank\Phase1\KernelPhase1SourceValidator;
use App\Services\QuestionBank\Phase1\Phase1ExecutionOrchestrator;
use App\Services\QuestionBank\Phase1\Phase1ExecutionRepository;
use App\Services\QuestionBank\Phase1\ValidationPhase1;
use App\Services\QuestionBank\Phase1\ValidationPhase1Reviewer;
use App\Services\QuestionBank\QuestionIntentBlueprintIdReceiver;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class Phase1PipelinePostgresTest extends TestCase
{
    private const BLUEPRINT_ID = 'bp-phase1-pipeline-test';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('kernel_phase1_executions')->where('blueprint_id', self::BLUEPRINT_ID)->delete();
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT_ID)->delete();
        DB::table('kernel_blueprint_request_refs')->where('blueprint_id', self::BLUEPRINT_ID)->delete();
        DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT_ID)->delete();

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => self::BLUEPRINT_ID,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth' => 4,
            'domain_code' => 'SCI',
            'subdomain_active' => 'Physics',
            'subject_active' => 'Light',
            'dominant_idea_active' => 'Refraction',
            'kernel_code_dd' => '04',
            'kernel_code_do' => 'SCI',
            'kernel_code_sub' => 'PHY',
            'kernel_code_suj' => 'LIG',
            'kernel_code_ide' => 'REF',
            'kernel_code_vvvv' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('kernel_code_sequences')->updateOrInsert(
            ['depth' => 4, 'domain_code' => 'SCI'],
            ['next_value' => 0, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('kernel_blueprint_request_refs')->insert([
            'request_reference' => 'phase1:pipeline:test',
            'blueprint_id' => self::BLUEPRINT_ID,
        ]);
        (new KernelBlueprintCognitiveSlotRepository())->initializeEmptySlots(self::BLUEPRINT_ID);
    }

    protected function tearDown(): void
    {
        DB::table('kernel_phase1_executions')->where('blueprint_id', self::BLUEPRINT_ID)->delete();
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT_ID)->delete();
        DB::table('kernel_blueprint_request_refs')->where('blueprint_id', self::BLUEPRINT_ID)->delete();
        DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT_ID)->delete();
        DB::table('kernel_code_sequences')->where('depth', 4)->where('domain_code', 'SCI')->delete();
        parent::tearDown();
    }

    public function test_question_intent_hands_off_to_phase1_and_replays_without_provider_calls(): void
    {
        $client = new Phase1PipelineFakeQuestionApiClient($this->sourceSlots());
        $reviewer = new Phase1PipelineFakeReviewer();
        $orchestrator = new Phase1ExecutionOrchestrator(
            new Phase1ExecutionRepository(),
            new KernelPhase1Generator(
                $client,
                new KernelBlueprintCognitiveSlotRepository(),
                new KernelPhase1SourceValidator(),
            ),
            new ValidationPhase1($reviewer),
        );
        $receiver = new QuestionIntentBlueprintIdReceiver(
            new KernelCodeEngine(),
            $orchestrator,
        );

        $this->assertSame(self::BLUEPRINT_ID, $receiver->process(self::BLUEPRINT_ID));
        $this->assertSame('0000', DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->value('kernel_code_vvvv'));
        $before = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->orderBy('cognitive_type')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        $this->assertCount(7, $before);
        foreach ($before as $slot) {
            $this->assertSame('CREATED', $slot['creation_status']);
            $this->assertContains($slot['validation_status'], ['PASS', 'SUSPICION']);
            $source = json_decode($slot['source'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('en', $source['source_language']);
            if (str_starts_with($slot['cognitive_type'], 'QCM_')) {
                $this->assertSame(['a', 'b', 'c', 'd'], array_keys($source['choices']));
            } else {
                $this->assertSame(['a' => 'TRUE', 'b' => 'FALSE'], $source['choices']);
            }
        }
        $this->assertSame(7, $reviewer->deepCalls);
        $this->assertSame(1, $reviewer->crossCalls);
        $this->assertSame(7, $reviewer->projectionCount);
        $this->assertTrue($reviewer->terminalObservedBeforeValidation);
        $this->assertSame(1, DB::table('kernel_phase1_executions')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->where('state', 'COMPLETED')
            ->count());

        $receiver->process(self::BLUEPRINT_ID);
        $this->assertSame(1, $client->calls);
        $this->assertSame(7, $reviewer->deepCalls);
        $this->assertSame(1, $reviewer->crossCalls);
        $this->assertSame($before, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->orderBy('cognitive_type')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all());
    }

    public function test_validation_refuses_before_phase1_terminal(): void
    {
        (new KernelCodeEngine())->assignKernelCode(self::BLUEPRINT_ID);
        $reviewer = new Phase1PipelineFakeReviewer();
        $validation = new ValidationPhase1($reviewer);

        try {
            $validation->validate(self::BLUEPRINT_ID);
            $this->fail('Validation Phase 1 must refuse before the Phase 1 terminal.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Phase 1 non terminale', $exception->getMessage());
        }
        $this->assertSame(0, $reviewer->deepCalls);
        $this->assertSame(0, $reviewer->crossCalls);
    }

    public function test_validation_failure_does_not_replace_phase1_terminal(): void
    {
        $client = new Phase1PipelineFakeQuestionApiClient($this->sourceSlots());
        $orchestrator = new Phase1ExecutionOrchestrator(
            new Phase1ExecutionRepository(),
            new KernelPhase1Generator(
                $client,
                new KernelBlueprintCognitiveSlotRepository(),
                new KernelPhase1SourceValidator(),
            ),
            new ValidationPhase1(
                new Phase1PipelineFakeReviewer(),
                new Phase1PipelineFailingValidationRepository(),
            ),
        );
        $receiver = new QuestionIntentBlueprintIdReceiver(
            new KernelCodeEngine(),
            $orchestrator,
        );

        try {
            $receiver->process(self::BLUEPRINT_ID);
            $this->fail('Expected the injected Validation Phase 1 write failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced Validation Phase 1 failure', $exception->getMessage());
        }

        $execution = DB::table('kernel_phase1_executions')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->first();
        $this->assertNotNull($execution);
        $this->assertSame('COMPLETED', $execution->state);
        $result = json_decode((string) $execution->result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED,
            $result['phase1_terminal']
        );
        $this->assertSame('CREATED', $result['creation_status']);
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->where('creation_status', 'CREATED')
            ->count());
    }

    public function test_validation_rejects_slots_changed_after_persisted_reload(): void
    {
        $client = new Phase1PipelineFakeQuestionApiClient($this->sourceSlots());
        $initialReviewer = new Phase1PipelineFakeReviewer();
        $orchestrator = new Phase1ExecutionOrchestrator(
            new Phase1ExecutionRepository(),
            new KernelPhase1Generator(
                $client,
                new KernelBlueprintCognitiveSlotRepository(),
                new KernelPhase1SourceValidator(),
            ),
            new ValidationPhase1($initialReviewer),
        );
        $receiver = new QuestionIntentBlueprintIdReceiver(
            new KernelCodeEngine(),
            $orchestrator,
        );
        $receiver->process(self::BLUEPRINT_ID);

        DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->update([
                'validation_status' => 'NOT_VALIDATED',
                'validation_findings' => '[]',
            ]);
        $mutatingReviewer = new Phase1PipelineMutatingReviewer();
        try {
            (new ValidationPhase1($mutatingReviewer))->validate(self::BLUEPRINT_ID);
            $this->fail('A changed persisted source must stale the validation result.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('CognitiveSlots Phase 1 périmés', $exception->getMessage());
        }

        $this->assertTrue($mutatingReviewer->mutated);
        $this->assertSame(
            0,
            DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', self::BLUEPRINT_ID)
                ->whereIn('validation_status', ['PASS', 'SUSPICION'])
                ->count(),
        );
        $execution = DB::table('kernel_phase1_executions')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->first();
        $this->assertSame('COMPLETED', $execution->state);
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceSlots(): array
    {
        $slots = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $index => $type) {
            $qcm = str_starts_with($type, 'QCM_');
            $slots[] = [
                'cognitive_type' => $type,
                'question' => "Which fact about light is correct {$index}?",
                'choices' => $qcm
                    ? [
                        ['key' => 'a', 'text' => 'A concise correct fact'],
                        ['key' => 'b', 'text' => 'A concise distractor'],
                        ['key' => 'c', 'text' => 'Another concise distractor'],
                        ['key' => 'd', 'text' => 'A final concise distractor'],
                    ]
                    : [
                        ['key' => 'a', 'text' => 'TRUE'],
                        ['key' => 'b', 'text' => 'FALSE'],
                    ],
                'correct_answer_key' => $qcm || str_ends_with($type, '_TRUE') ? 'a' : 'b',
                'sv' => 'This explanation connects the answer to refraction and light.',
                'creation_evidence' => [
                    'cognitive_operation' => 'independent operation',
                    'cognitive_justification' => 'independent justification',
                    'difference_from_other_slots' => "different proposition {$index}",
                    'truth_basis' => 'stable scientific fact',
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
                    ], true),
                ],
            ];
        }
        return $slots;
    }
}

final class Phase1PipelineFakeQuestionApiClient extends QuestionApiClient
{
    public int $calls = 0;

    public function __construct(private readonly array $slots) {}

    public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
    {
        $this->calls++;
        $result = [
            'schema_version' => 'phase1.source.v1',
            'blueprint_id' => $payload['blueprint_id'],
            'kernel_code' => $payload['kernel_code'],
            'source_language' => 'en',
            'slots' => $this->slots,
        ];
        return new Response(new PsrResponse(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR),
        ));
    }
}

final class Phase1PipelineFakeReviewer implements ValidationPhase1Reviewer
{
    public int $deepCalls = 0;
    public int $crossCalls = 0;
    public int $projectionCount = 0;
    public bool $terminalObservedBeforeValidation = false;

    public function review(array $input): array
    {
        $execution = DB::table('kernel_phase1_executions')
            ->where('blueprint_id', $input['blueprint_id'])
            ->first();
        $result = $execution === null
            ? null
            : json_decode((string) $execution->result, true);
        if ($execution === null
            || $execution->state !== 'COMPLETED'
            || ($result['phase1_terminal'] ?? null)
                !== Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED
            || DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $input['blueprint_id'])
                ->where('creation_status', 'CREATED')
                ->count() !== 7) {
            throw new LogicException('Validation entered before committed Phase 1 terminal.');
        }
        $this->terminalObservedBeforeValidation = true;
        $this->assertArrayNotHasKey('creation_evidence', $input);
        $this->assertArrayNotHasKey('self_checks', $input);
        if ($input['review_level'] === 'deep') {
            $this->deepCalls++;
            $this->projectionCount++;
            $this->assertArrayNotHasKey('creation_evidence', $input['slot']);
            $this->assertArrayNotHasKey('self_checks', $input['slot']);
            return [
                'schema_version' => ValidationPhase1::SCHEMA_VERSION,
                'blueprint_id' => $input['blueprint_id'],
                'kernel_code' => $input['kernel_code'],
                'slots' => [[
                    'cognitive_type' => $input['slot']['cognitive_type'],
                    'decision' => 'PASS',
                    'findings' => [],
                ]],
            ];
        }
        $this->crossCalls++;
        return [
            'schema_version' => ValidationPhase1::SCHEMA_VERSION,
            'blueprint_id' => $input['blueprint_id'],
            'kernel_code' => $input['kernel_code'],
            'cross_slot_findings' => [],
        ];
    }

    private function assertArrayNotHasKey(string $key, array $input): void
    {
        if (array_key_exists($key, $input)) {
            throw new \LogicException("Reviewer projection leaked {$key}.");
        }
    }
}

final class Phase1PipelineFailingValidationRepository extends KernelBlueprintCognitiveSlotRepository
{
    public function writeValidationResults(string $blueprintId, array $decisions): void
    {
        throw new RuntimeException('forced Validation Phase 1 failure');
    }
}

final class Phase1PipelineMutatingReviewer implements ValidationPhase1Reviewer
{
    public bool $mutated = false;

    public function review(array $input): array
    {
        if (! $this->mutated) {
            $slot = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $input['blueprint_id'])
                ->where('cognitive_type', 'QCM_RECOGNITION')
                ->first();
            $source = json_decode((string) $slot->source, true, 512, JSON_THROW_ON_ERROR);
            $source['question'] = 'A concurrently committed replacement question?';
            DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $input['blueprint_id'])
                ->where('cognitive_type', 'QCM_RECOGNITION')
                ->update([
                    'source' => json_encode($source, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            $this->mutated = true;
        }

        if ($input['review_level'] === 'deep') {
            return [
                'schema_version' => ValidationPhase1::SCHEMA_VERSION,
                'blueprint_id' => $input['blueprint_id'],
                'kernel_code' => $input['kernel_code'],
                'slots' => [[
                    'cognitive_type' => $input['slot']['cognitive_type'],
                    'decision' => 'PASS',
                    'findings' => [],
                ]],
            ];
        }

        return [
            'schema_version' => ValidationPhase1::SCHEMA_VERSION,
            'blueprint_id' => $input['blueprint_id'],
            'kernel_code' => $input['kernel_code'],
            'cross_slot_findings' => [],
        ];
    }
}