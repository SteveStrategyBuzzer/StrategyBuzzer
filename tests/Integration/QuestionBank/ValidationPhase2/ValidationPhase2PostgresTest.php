<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\ValidationPhase2;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Phase1\Phase1ExecutionRepository;
use App\Services\QuestionBank\Phase2\Phase2TranslationRepository;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Repository;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Response;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2TechnicalFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Run only through scripts/run-isolated-postgres-tests.php.
 * This suite uses no provider or network and never touches Quarantine/ReadyBank.
 */
final class ValidationPhase2PostgresTest extends TestCase
{
    private const BLUEPRINT = 'bp-validation-phase2-postgres';
    private ValidationPhase2Repository $repository;

    public static function canonicalPreconditionCases(): array
    {
        return [
            'phase1 absent' => ['phase1_absent'],
            'phase1 identity stale' => ['phase1_identity_stale'],
            'phase1 terminal invalid' => ['phase1_terminal_invalid'],
            'source validation not pass' => ['source_not_pass'],
            'source identity stale' => ['source_identity_stale'],
            'unit state not created' => ['unit_state'],
            'unit creation not created' => ['unit_creation'],
            'source revision stale' => ['source_revision'],
        ];
    }

    /** @dataProvider canonicalPreconditionCases */
    public function test_each_canonical_precondition_blocks_without_attempt(string $case): void
    {
        $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        $execution = DB::table('kernel_phase1_executions')->where('blueprint_id', self::BLUEPRINT)->first();
        $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->first();
        $before = DB::table('kernel_validation_phase2_attempts')->count();
        if ($case === 'phase1_absent') DB::table('kernel_phase1_executions')->where('execution_id', $execution->execution_id)->delete();
        if ($case === 'phase1_identity_stale') DB::table('kernel_phase1_executions')->where('execution_id', $execution->execution_id)->update(['identity_revision' => str_repeat('f', 64)]);
        if ($case === 'phase1_terminal_invalid') DB::table('kernel_phase1_executions')->where('execution_id', $execution->execution_id)->update(['result' => json_encode(['phase1_terminal' => 'FAILED', 'creation_status' => 'CREATED'])]);
        if ($case === 'source_not_pass') DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->update(['validation_status' => 'NOT_VALIDATED']);
        if ($case === 'source_identity_stale') DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->update(['source' => json_encode(array_merge($this->source(), ['cognitive_type' => 'QCM_REASONING']))]);
        if ($case === 'unit_state') DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->update([
            'state' => 'RETRYABLE_FAILURE', 'creation_status' => 'PENDING',
        ]);
        if ($case === 'unit_creation') DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->update([
            'state' => 'PENDING', 'creation_status' => 'PENDING',
        ]);
        if ($case === 'source_revision') DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->update(['source_revision' => str_repeat('e', 64)]);
        self::assertSame(ValidationPhase2Repository::PRECONDITION_BLOCKED, $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr')['outcome']);
        self::assertSame($before, DB::table('kernel_validation_phase2_attempts')->count());
    }

    public function test_postgresql_rejects_a_translation_validation_status_other_than_not_validated(): void
    {
        $before = DB::table('kernel_validation_phase2_attempts')->count();
        try {
            DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)
                ->update(['validation_status' => 'PASS']);
            self::fail('PostgreSQL must reject a Phase 2 translation validation status other than NOT_VALIDATED.');
        } catch (\Illuminate\Database\QueryException $exception) {
            self::assertSame('23514', $exception->getCode());
        }
        self::assertSame($before, DB::table('kernel_validation_phase2_attempts')->count());
    }

    public function test_translation_revision_correction_is_fenced_and_content_is_unchanged(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $before = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->update(['translation_revision' => 2]);
        self::assertSame(ValidationPhase2Repository::STALE_RESULT, $this->repository->apply($claim['run'], new ValidationPhase2Response(
            $claim['request']->validationRequestReference, 'late', 'PASS', [], $claim['request']->externalValidationIdempotencyKey
        )));
        $after = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        self::assertSame($before->translation, $after->translation);
        self::assertSame(2, (int) $after->translation_revision);
    }

    public function test_pass_and_suspicion_are_noop_or_terminal_without_content_mutation(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $before = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        self::assertSame('PASS', $this->repository->apply($claim['run'], new ValidationPhase2Response(
            $claim['request']->validationRequestReference, 'validator-pass', 'PASS', [], $claim['request']->externalValidationIdempotencyKey
        )));
        self::assertSame(ValidationPhase2Repository::NO_OP, $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr')['outcome']);
        $afterPass = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        self::assertSame($before->translation, $afterPass->translation);
        self::assertSame((int) $before->translation_revision, (int) $afterPass->translation_revision);

        $run = DB::table('kernel_validation_phase2_runs')->where('blueprint_id', self::BLUEPRINT)->first();
        self::assertSame(ValidationPhase2Repository::STALE_RESULT, $this->repository->fail($run, new \App\Services\QuestionBank\ValidationPhase2\ValidationPhase2TechnicalFailure(
            'late', $run->validation_request_reference, $run->external_idempotency_key, true, null, 'LATE'
        )));
    }

    public function test_yellow_revision_is_fenced_and_preserved(): void
    {
        DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->update(['yellow_revision' => 2]);
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame('CLAIMED', $claim['outcome']);
        DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->update(['yellow_revision' => 3]);
        self::assertSame(ValidationPhase2Repository::STALE_RESULT, $this->repository->apply($claim['run'], new ValidationPhase2Response(
            $claim['request']->validationRequestReference, 'yellow-late', 'PASS', [], $claim['request']->externalValidationIdempotencyKey
        )));
    }

    public function test_suspicion_cannot_be_erased_by_late_technical_failure(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $finding = [[
            'field_path' => 'question', 'rule_code' => 'MEANING_DRIFT', 'severity' => 'BLOCKING',
            'evidence' => [
                'expected_rule' => 'Preserve meaning', 'observed_result' => 'Meaning drift',
                'source_excerpt' => 'Which color?', 'target_excerpt' => 'Quelle couleur ?',
            ],
        ]];
        self::assertSame('SUSPICION', $this->repository->apply($claim['run'], new ValidationPhase2Response(
            $claim['request']->validationRequestReference, 'validator-suspicion', 'SUSPICION', $finding,
            $claim['request']->externalValidationIdempotencyKey
        )));
        $run = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $claim['run']->validation_run_id)->first();
        self::assertSame(ValidationPhase2Repository::STALE_RESULT, $this->repository->fail($run, new ValidationPhase2TechnicalFailure(
            'late', $run->validation_request_reference, $run->external_idempotency_key, true, null, 'LATE'
        )));
        self::assertSame('SUSPICION', DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->value('status'));
    }

    public function test_suspicion_yellow_correction_creates_new_revision_and_fences_old_validation(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $finding = [[
            'field_path' => 'question', 'rule_code' => 'MEANING_DRIFT', 'severity' => 'BLOCKING',
            'evidence' => ['expected_rule' => 'Preserve meaning', 'observed_result' => 'Meaning drift',
                'source_excerpt' => 'Which color?', 'target_excerpt' => 'Quelle couleur ?'],
        ]];
        self::assertSame('SUSPICION', $this->repository->apply($claim['run'], new ValidationPhase2Response(
            $claim['request']->validationRequestReference, 'validator-old', 'SUSPICION', $finding,
            $claim['request']->externalValidationIdempotencyKey
        )));
        $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        $corrected = ['question' => 'Quelle couleur ?', 'choices' => ['a' => 'Bleu', 'b' => 'Rouge', 'c' => 'Vert', 'd' => 'Jaune'],
            'correct_answer_key' => 'a', 'sv' => 'Le bleu est une couleur connue.'];
        self::assertSame('CORRECTED', (new Phase2TranslationRepository())->applyYellowCorrection(
            self::BLUEPRINT, 'QCM_RECOGNITION', 'fr', $claim['run']->source_revision,
            (int) $unit->translation_revision, $unit->yellow_revision, $corrected,
        ));
        self::assertSame(ValidationPhase2Repository::STALE_RESULT, $this->repository->apply($claim['run'],
            new ValidationPhase2Response($claim['request']->validationRequestReference, 'validator-old', 'SUSPICION', $finding,
                $claim['request']->externalValidationIdempotencyKey)));
        self::assertSame(ValidationPhase2Repository::STALE_RESULT, $this->repository->apply($claim['run'],
            new ValidationPhase2Response($claim['request']->validationRequestReference, 'validator-new', 'PASS', [],
                $claim['request']->externalValidationIdempotencyKey)));
        $newClaim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame('CLAIMED', $newClaim['outcome']);
        self::assertSame('PASS', $this->repository->apply($newClaim['run'], new ValidationPhase2Response(
            $newClaim['request']->validationRequestReference, 'validator-new', 'PASS', [],
            $newClaim['request']->externalValidationIdempotencyKey
        )));
        $after = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        self::assertSame($corrected['question'], json_decode($after->translation, true)['question']);
        self::assertSame(2, (int) $after->translation_revision);
        self::assertSame('PASS', DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $newClaim['run']->validation_run_id)->value('status'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ValidationPhase2Repository();
        $this->resetFixtureTables();
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => self::BLUEPRINT, 'execution_state' => 'ENGAGED_IN_PIPELINE', 'depth' => 4,
            'domain_code' => 'SCI', 'subdomain_active' => 'Physics', 'subject_active' => 'Light',
            'dominant_idea_active' => 'Refraction', 'kernel_code_dd' => '04', 'kernel_code_do' => 'SCI',
            'kernel_code_sub' => 'PHY', 'kernel_code_suj' => 'LIG', 'kernel_code_ide' => 'REF',
            'kernel_code_vvvv' => '0000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $identity = $this->identityRevision(DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT)->first());
        if (Schema::hasColumn('kernel_blueprint_runs', 'identity_revision')) {
            DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT)->update(['identity_revision' => $identity]);
        }
        DB::table('kernel_phase1_executions')->insert([
            'execution_id' => (string) Str::uuid(), 'blueprint_id' => self::BLUEPRINT, 'identity_revision' => $identity,
            'state' => 'COMPLETED', 'lease_token' => (string) Str::uuid(),
            'result' => json_encode(['phase1_terminal' => Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED, 'creation_status' => 'CREATED'], JSON_THROW_ON_ERROR),
            'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $source = $this->source();
        DB::table('kernel_blueprint_cognitive_slots')->insert([
            'blueprint_id' => self::BLUEPRINT, 'cognitive_type' => 'QCM_RECOGNITION',
            'source' => json_encode($source, JSON_THROW_ON_ERROR), 'creation_failure' => null, 'translations' => '{}',
            'creation_status' => 'CREATED', 'validation_status' => 'PASS', 'validation_findings' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $revision = $this->sourceRevision($source);
        DB::table('kernel_phase2_translation_units')->insert([
            'blueprint_id' => self::BLUEPRINT, 'cognitive_type' => 'QCM_RECOGNITION', 'language_code' => 'fr',
            'source_revision' => $revision, 'translation_revision' => 1, 'state' => 'CREATED',
            'creation_status' => 'CREATED', 'validation_status' => 'NOT_VALIDATED', 'source_payload_hash' => hash('sha256', json_encode($source)),
            'translation' => json_encode($this->target()), 'translation_hash' => hash('sha256', json_encode($this->target())),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetFixtureTables();
        parent::tearDown();
    }

    private function resetFixtureTables(): void
    {
        DB::statement(
            'TRUNCATE TABLE kernel_validation_phase2_resolution_events, '
            . 'kernel_validation_phase2_findings, kernel_validation_phase2_attempts, '
            . 'kernel_validation_phase2_runs, kernel_phase2_translation_units, '
            . 'kernel_phase1_executions, kernel_blueprint_cognitive_slots, '
            . 'kernel_blueprint_runs CASCADE'
        );
    }

    public function test_pass_replay_and_divergent_replay_are_fenced(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame('CLAIMED', $claim['outcome']);
        $response = new ValidationPhase2Response($claim['request']->validationRequestReference, 'validator-1', 'PASS', [], $claim['request']->externalValidationIdempotencyKey);
        self::assertSame('PASS', $this->repository->apply($claim['run'], $response));
        self::assertSame(ValidationPhase2Repository::NO_OP, $this->repository->apply($claim['run'], $response));
        self::assertSame(ValidationPhase2Repository::IDEMPOTENCY_VIOLATION, $this->repository->apply($claim['run'], new ValidationPhase2Response($response->validationRequestReference, 'validator-1', 'SUSPICION', [['field_path' => 'question']], $response->externalValidationIdempotencyKey)));
    }

    public function test_expired_claim_is_consumed_then_waits_before_next_attempt(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $claim['run']->validation_run_id)
            ->update(['claim_expires_at' => now()->subSecond()]);
        $expired = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame(ValidationPhase2Repository::NO_OP, $expired['outcome']);
        self::assertSame('RETRYABLE_FAILURE', $expired['run']->status);
        self::assertNotNull($expired['run']->next_attempt_at);
        self::assertSame('RETRYABLE_TECHNICAL_FAILURE', DB::table('kernel_validation_phase2_attempts')->where('attempt_id', $claim['run']->current_attempt_id)->value('outcome'));
        $lateResponse = new ValidationPhase2Response(
            $claim['request']->validationRequestReference,
            'validator-late',
            'PASS',
            [],
            $claim['request']->externalValidationIdempotencyKey,
        );
        self::assertSame(ValidationPhase2Repository::STALE_RESULT, $this->repository->apply($claim['run'], $lateResponse));
        DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $claim['run']->validation_run_id)
            ->update(['next_attempt_at' => now()->subSecond()]);
        $next = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame('CLAIMED', $next['outcome']);
        self::assertSame(2, $next['run']->attempt_number);
    }

    public function test_invalid_response_is_retryable_and_resolution_opens_one_new_cycle(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame(ValidationPhase2Repository::RETRYABLE_TECHNICAL_FAILURE, $this->repository->apply($claim['run'], new ValidationPhase2Response($claim['request']->validationRequestReference, 'validator-1', 'SUSPICION', [])));
        DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $claim['run']->validation_run_id)->update(['next_attempt_at' => now()->subSecond()]);
        for ($i = 0; $i < 3; $i++) {
            $next = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
            self::assertSame($i === 2 ? ValidationPhase2Repository::NON_RETRYABLE_TECHNICAL_FAILURE : ValidationPhase2Repository::RETRYABLE_TECHNICAL_FAILURE, $this->repository->fail($next['run'], new ValidationPhase2TechnicalFailure('down', $next['run']->validation_request_reference, $next['request']->externalValidationIdempotencyKey, true, null, 'DOWN')));
            if ($i < 2) {
                DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $next['run']->validation_run_id)->update(['next_attempt_at' => now()->subSecond()]);
            }
        }
        $blocked = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $claim['run']->validation_run_id)->first();
        self::assertSame('PERMANENT_FAILURE', $blocked->status);
        $event = '11111111-1111-4111-8111-111111111111';
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['source' => json_encode(array_merge($this->source(), ['question' => 'changed']), JSON_THROW_ON_ERROR)]);
        self::assertFalse($this->repository->authorizeRetryCycle(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr', $blocked->source_revision, 1, null, $event, 'SYSTEM_RECOVERY', 'STALE'));
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['source' => json_encode($this->source(), JSON_THROW_ON_ERROR)]);
        self::assertTrue($this->repository->authorizeRetryCycle(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr', $blocked->source_revision, 1, null, $event, 'SYSTEM_RECOVERY', 'MANUAL_RESOLUTION'));
        self::assertFalse($this->repository->authorizeRetryCycle(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr', $blocked->source_revision, 1, null, $event, 'SYSTEM_RECOVERY', 'MANUAL_RESOLUTION'));
        self::assertSame(1, DB::table('kernel_validation_phase2_resolution_events')->where('resolution_event_id', $event)->count());
    }

    /** @dataProvider retryAfterCases */
    public function test_retry_after_uses_maximum_of_floor_and_provider_delay(int $retryAfter, int $minimumSeconds): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $before = now();
        self::assertSame(ValidationPhase2Repository::RETRYABLE_TECHNICAL_FAILURE,
            $this->repository->fail($claim['run'], new ValidationPhase2TechnicalFailure(
                'rate limited', $claim['request']->validationRequestReference,
                $claim['request']->externalValidationIdempotencyKey, true, $retryAfter, 'VALIDATOR_HTTP_FAILURE',
            )));
        $nextAttemptAt = DB::table('kernel_validation_phase2_runs')
            ->where('validation_run_id', $claim['run']->validation_run_id)->value('next_attempt_at');
        self::assertGreaterThanOrEqual($before->copy()->addSeconds($minimumSeconds)->timestamp, strtotime((string) $nextAttemptAt));
    }

    public static function retryAfterCases(): array
    {
        return ['below first floor' => [17, 60], 'above first floor' => [1200, 1200]];
    }

    public function test_same_resolution_event_across_two_blueprints_has_one_global_winner(): void
    {
        if (!function_exists('pcntl_fork')) self::markTestSkipped('pcntl required.');
        $second = 'bp-validation-phase2-resolution-race';
        $secondBlueprintValues = (array) DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT)->first();
        unset($secondBlueprintValues['kernel_code']);
        $secondBlueprintValues['blueprint_id'] = $second;
        $secondBlueprintValues['execution_state'] = 'READY_BANK_RECEIVED';
        $secondBlueprintValues['kernel_code_vvvv'] = '0001';
        DB::table('kernel_blueprint_runs')->insert($secondBlueprintValues);
        $secondBlueprint = DB::table('kernel_blueprint_runs')->where('blueprint_id', $second)->first();
        $identity = $this->identityRevision($secondBlueprint);
        if (Schema::hasColumn('kernel_blueprint_runs', 'identity_revision')) {
            DB::table('kernel_blueprint_runs')->where('blueprint_id', $second)->update(['identity_revision' => $identity]);
        }
        DB::table('kernel_phase1_executions')->insert([
            'execution_id' => (string) Str::uuid(), 'blueprint_id' => $second, 'identity_revision' => $identity,
            'state' => 'COMPLETED', 'lease_token' => (string) Str::uuid(),
            'result' => json_encode(['phase1_terminal' => Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED, 'creation_status' => 'CREATED'], JSON_THROW_ON_ERROR),
            'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->first();
        DB::table('kernel_blueprint_cognitive_slots')->insert(array_merge((array) $slot, ['blueprint_id' => $second]));
        $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        DB::table('kernel_phase2_translation_units')->insert(array_merge((array) $unit, ['blueprint_id' => $second]));
        $sourceRevision = $unit->source_revision;
        foreach ([self::BLUEPRINT, $second] as $blueprintId) {
            DB::table('kernel_validation_phase2_runs')->insert([
                'validation_run_id' => (string) Str::uuid(), 'blueprint_id' => $blueprintId,
                'cognitive_type' => 'QCM_RECOGNITION', 'language_code' => 'fr',
                'source_revision' => $sourceRevision, 'translation_revision' => 1,
                'status' => 'PERMANENT_FAILURE', 'retry_cycle' => 0, 'attempt_number' => 4,
                'validation_request_reference' => (string) Str::uuid(), 'external_idempotency_key' => (string) Str::uuid(),
                'technical_reason_code' => 'DOWN', 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $event = '22222222-2222-4222-8222-222222222222';
        $results = $this->forkValidationProcesses(static function (array $args, int $index): array {
            $blueprintId = $args[$index];
            return ['pid' => getmypid(), 'blueprint_id' => $blueprintId, 'value' => (new ValidationPhase2Repository())
                ->authorizeRetryCycle($blueprintId, 'QCM_RECOGNITION', 'fr', $args[2], 1, null, $args[3],
                    'SYSTEM_RECOVERY', 'GLOBAL_RACE')];
        }, [self::BLUEPRINT, $second, $sourceRevision, $event]);
        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertIsInt($result['pid']);
            self::assertGreaterThan(0, $result['pid']);
            self::assertArrayNotHasKey('exception', $result);
        }
        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['value'] === true));
        self::assertSame(1, DB::table('kernel_validation_phase2_resolution_events')->where('resolution_event_id', $event)->count());
        self::assertSame(1, DB::table('kernel_validation_phase2_runs')->whereIn('blueprint_id', [self::BLUEPRINT, $second])
            ->where('status', 'NOT_VALIDATED')->where('retry_cycle', 1)->count());
        self::assertSame(1, DB::table('kernel_validation_phase2_runs')->whereIn('blueprint_id', [self::BLUEPRINT, $second])
            ->where('status', 'PERMANENT_FAILURE')->where('retry_cycle', 0)->count());
    }

    public function test_current_pass_remains_admissible_despite_historical_technical_attempts(): void
    {
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame('PASS', $this->repository->apply($claim['run'], new \App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Response(
            $claim['request']->validationRequestReference, 'validator-history', 'PASS', [], $claim['request']->externalValidationIdempotencyKey
        )));
        $sourceRevision = $claim['run']->source_revision;
        foreach (['es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'] as $language) {
            DB::table('kernel_phase2_translation_units')->insert([
                'blueprint_id' => self::BLUEPRINT, 'cognitive_type' => 'QCM_RECOGNITION', 'language_code' => $language,
                'source_revision' => $sourceRevision, 'translation_revision' => 1, 'state' => 'CREATED',
                'creation_status' => 'CREATED', 'validation_status' => 'NOT_VALIDATED',
                'source_payload_hash' => str_repeat('a', 64), 'translation' => json_encode($this->target()),
                'translation_hash' => hash('sha256', json_encode($this->target())), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('kernel_validation_phase2_runs')->insert([
                'validation_run_id' => (string) Str::uuid(), 'blueprint_id' => self::BLUEPRINT,
                'cognitive_type' => 'QCM_RECOGNITION', 'language_code' => $language, 'source_revision' => $sourceRevision,
                'translation_revision' => 1, 'status' => 'PASS', 'validation_request_reference' => (string) Str::uuid(),
                'external_idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('kernel_validation_phase2_attempts')->insert([
            'attempt_id' => (string) Str::uuid(), 'validation_run_id' => $claim['run']->validation_run_id,
            'retry_cycle' => 1, 'attempt_number' => 1, 'claim_token' => (string) Str::uuid(),
            'validation_request_reference' => (string) Str::uuid(), 'external_idempotency_key' => (string) Str::uuid(),
            'source_payload_hash' => str_repeat('a', 64), 'target_payload_hash' => str_repeat('b', 64),
            'expected_translation_revision' => 1, 'outcome' => 'RETRYABLE_TECHNICAL_FAILURE',
            'internal_envelope' => '{}', 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('kernel_blueprint_cognitive_slots')->insert([
            'blueprint_id' => self::BLUEPRINT, 'cognitive_type' => 'QCM_REASONING',
            'source' => json_encode(['question' => 'incomplete'], JSON_THROW_ON_ERROR), 'creation_failure' => null,
            'translations' => '{}', 'creation_status' => 'CREATED', 'validation_status' => 'PASS',
            'validation_findings' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);
        self::assertTrue($this->repository->isSlotAdmissible(self::BLUEPRINT, 'QCM_RECOGNITION'));
        self::assertFalse($this->repository->isSlotAdmissible(self::BLUEPRINT, 'QCM_REASONING'));
    }

    public function test_preconditions_block_without_attempt_and_external_projection_is_allowlisted(): void
    {
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)
            ->update(['source' => json_encode(array_merge($this->source(), [
                'blueprint_id' => self::BLUEPRINT, 'source_revision' => str_repeat('x', 64),
                'claim_token' => 'secret', 'creation_evidence' => ['internal' => true],
                'self_checks' => ['internal' => true], 'provider_metadata' => ['internal' => true],
                'translation_revision' => 99, 'retry_cycle' => 7, 'attempt_number' => 4,
                'yellow_revision' => 3, 'storage' => ['internal' => true],
                'translator_conclusion' => 'PASS',
            ]), JSON_THROW_ON_ERROR)]);
        $blocked = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        self::assertSame(ValidationPhase2Repository::CLAIMED, $blocked['outcome']);
        $external = $blocked['request']->toExternalArray();
        self::assertSame(['question', 'choices', 'correct_answer_key', 'sv'], array_keys($external['source']));
        self::assertArrayNotHasKey('blueprint_id', $external['source']);
        self::assertArrayNotHasKey('claim_token', $external['source']);
        self::assertArrayNotHasKey('creation_evidence', $external['source']);
        foreach (['blueprint_id', 'source_revision', 'translation_revision', 'retry_cycle', 'attempt_number',
            'claim_token', 'yellow_revision', 'storage', 'self_checks', 'creation_evidence', 'translator_conclusion'] as $key) {
            $keys = [];
            $collect = static function (mixed $value) use (&$collect, &$keys): void {
                if (!is_array($value)) return;
                foreach ($value as $key => $child) {
                    $keys[] = (string) $key;
                    $collect($child);
                }
            };
            $collect($external);
            self::assertNotContains($key, $keys);
        }
        DB::table('kernel_validation_phase2_attempts')->where('validation_run_id', $blocked['run']->validation_run_id)->delete();
        DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $blocked['run']->validation_run_id)->delete();
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)
            ->update(['validation_status' => 'NOT_VALIDATED']);
        $before = DB::table('kernel_validation_phase2_attempts')->count();
        self::assertSame(ValidationPhase2Repository::PRECONDITION_BLOCKED, $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr')['outcome']);
        self::assertSame($before, DB::table('kernel_validation_phase2_attempts')->count());
    }

    public function test_two_postgresql_processes_have_one_claim_and_two_zero_exit_codes(): void
    {
        if (!function_exists('pcntl_fork')) self::markTestSkipped('pcntl required for the PostgreSQL race proof.');
        $dir = sys_get_temp_dir() . '/validation-phase2-race-' . bin2hex(random_bytes(5));
        mkdir($dir);
        $pids = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) throw new \RuntimeException('pcntl_fork failed');
            if ($pid === 0) {
                try {
                    DB::purge('pgsql');
                    $value = (new ValidationPhase2Repository())->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr')['outcome'];
                    file_put_contents($dir . '/' . $i, json_encode(['value' => $value], JSON_THROW_ON_ERROR));
                    exit(0);
                } catch (\Throwable $e) {
                    file_put_contents($dir . '/' . $i, json_encode(['exception' => $e::class . ': ' . $e->getMessage()], JSON_THROW_ON_ERROR));
                    exit(1);
                }
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status), file_get_contents($dir . '/' . array_search($pid, $pids, true)) ?: '');
        }
        $results = [];
        foreach ([0, 1] as $i) $results[] = json_decode((string) file_get_contents($dir . '/' . $i), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertIsArray($result);
            self::assertArrayHasKey('value', $result);
            self::assertArrayNotHasKey('exception', $result);
        }
        self::assertCount(1, array_filter($results, static fn (array $r): bool => $r['value'] === 'CLAIMED'));
        self::assertSame(1, DB::table('kernel_validation_phase2_attempts')->where('validation_run_id', DB::table('kernel_validation_phase2_runs')->where('blueprint_id', self::BLUEPRINT)->value('validation_run_id'))->count());
        @unlink($dir . '/0'); @unlink($dir . '/1'); @rmdir($dir);
    }

    public function test_two_processes_concurrent_apply_have_two_structured_results_one_terminal(): void
    {
        if (!function_exists('pcntl_fork')) self::markTestSkipped('pcntl required.');
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $args = [$claim['run']->validation_run_id, $claim['request']->validationRequestReference,
            $claim['request']->externalValidationIdempotencyKey];
        $results = $this->forkValidationProcesses(static function (array $args, int $index): array {
            [$runId, $reference, $key] = $args;
            $run = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $runId)->first();
            return ['operation' => 'apply', 'value' => (new ValidationPhase2Repository())->apply($run,
                new ValidationPhase2Response($reference, 'concurrent', 'PASS', [], $key))];
        }, $args);
        self::assertCount(2, $results);
        foreach ($results as $result) { self::assertIsArray($result); self::assertArrayHasKey('value', $result); self::assertArrayNotHasKey('exception', $result); }
        self::assertSame('PASS', DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $args[0])->value('status'));
        self::assertCount(1, DB::table('kernel_validation_phase2_attempts')->where('validation_run_id', $args[0])->where('outcome', 'APPLIED')->get());
    }

    public function test_two_processes_apply_and_fail_have_one_coherent_terminal_effect(): void
    {
        if (!function_exists('pcntl_fork')) self::markTestSkipped('pcntl required.');
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $args = [$claim['run']->validation_run_id, $claim['request']->validationRequestReference,
            $claim['request']->externalValidationIdempotencyKey];
        $results = $this->forkValidationProcesses(static function (array $args, int $index): array {
            [$runId, $reference, $key] = $args;
            $run = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $runId)->first();
            if ($index === 0) {
                $value = (new ValidationPhase2Repository())->apply($run, new ValidationPhase2Response($reference, 'race', 'PASS', [], $key));
                $operation = 'apply';
            } else {
                $value = (new ValidationPhase2Repository())->fail($run, new ValidationPhase2TechnicalFailure('down', $reference, $key, true, null, 'DOWN'));
                $operation = 'fail';
            }
            return ['operation' => $operation, 'value' => $value];
        }, $args);
        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertIsArray($result);
            self::assertArrayHasKey('operation', $result);
            self::assertArrayHasKey('value', $result);
            self::assertArrayNotHasKey('exception', $result);
        }
        self::assertSame(['apply', 'fail'], array_column($results, 'operation'));
        self::assertContains($results[0]['value'], ['PASS', ValidationPhase2Repository::STALE_RESULT]);
        self::assertContains($results[1]['value'], ['RETRYABLE_TECHNICAL_FAILURE', ValidationPhase2Repository::STALE_RESULT]);
        self::assertContains(DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $args[0])->value('status'), ['PASS', 'RETRYABLE_FAILURE']);
    }

    public function test_two_processes_old_result_is_fenced_after_yellow_correction_and_new_claim_is_clean(): void
    {
        if (!function_exists('pcntl_fork')) self::markTestSkipped('pcntl required.');
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr');
        $args = [$claim['run']->validation_run_id, $claim['request']->validationRequestReference,
            $claim['request']->externalValidationIdempotencyKey];
        $old = $this->forkValidationProcesses(static function (array $args, int $index): array {
            [$runId, $reference, $key] = $args;
            $run = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $runId)->first();
            if ($index === 0) {
                $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', 'bp-validation-phase2-postgres')->first();
                $value = (new Phase2TranslationRepository())->applyYellowCorrection(
                    'bp-validation-phase2-postgres', 'QCM_RECOGNITION', 'fr', $run->source_revision,
                    (int) $unit->translation_revision, $unit->yellow_revision,
                    ['question' => 'Quelle couleur corrigée ?', 'choices' => ['a' => 'Bleu', 'b' => 'Rouge', 'c' => 'Vert', 'd' => 'Jaune'],
                        'correct_answer_key' => 'a', 'sv' => 'Le bleu reste une couleur.'],
                );
                return ['operation' => 'correction', 'value' => $value];
            }
            $value = (new ValidationPhase2Repository())->apply($run,
                new ValidationPhase2Response($reference, 'old', 'PASS', [], $key));
            return ['operation' => 'old_apply', 'value' => $value];
        }, $args);
        self::assertCount(2, $old);
        foreach ($old as $result) {
            self::assertIsArray($result);
            self::assertArrayHasKey('operation', $result);
            self::assertArrayHasKey('value', $result);
            self::assertArrayNotHasKey('exception', $result);
        }
        self::assertSame(['correction', 'old_apply'], array_column($old, 'operation'));
        self::assertSame('CORRECTED', $old[0]['value']);
        self::assertContains($old[1]['value'], ['PASS', ValidationPhase2Repository::STALE_RESULT]);
        $correctedUnit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->first();
        self::assertSame(2, (int) $correctedUnit->translation_revision);
        self::assertSame('Quelle couleur corrigée ?', json_decode($correctedUnit->translation, true)['question']);
        $new = $this->forkValidationProcesses(static function (array $unused): array {
            return ['operation' => 'claim', 'value' => (new ValidationPhase2Repository())->claim('bp-validation-phase2-postgres', 'QCM_RECOGNITION', 'fr')['outcome']];
        }, []);
        self::assertCount(2, $new);
        foreach ($new as $result) { self::assertIsArray($result); self::assertArrayHasKey('operation', $result); self::assertArrayHasKey('value', $result); self::assertArrayNotHasKey('exception', $result); }
        self::assertSame(['claim', 'claim'], array_column($new, 'operation'));
        $claimOutcomes = array_values(array_unique(array_column($new, 'value')));
        sort($claimOutcomes);
        self::assertSame(['CLAIMED', ValidationPhase2Repository::NO_OP], $claimOutcomes);
    }

    private function forkValidationProcesses(\Closure $callback, array $args): array
    {
        $dir = sys_get_temp_dir() . '/validation-phase2-race-' . bin2hex(random_bytes(5));
        mkdir($dir);
        $pids = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) throw new \RuntimeException('pcntl_fork failed');
            if ($pid === 0) {
                try {
                    DB::purge('pgsql');
                    $result = $callback($args, $i);
                    $result['pid'] = getmypid();
                    file_put_contents($dir . '/' . $i, json_encode($result, JSON_THROW_ON_ERROR));
                    exit(0);
                } catch (\Throwable $e) {
                    file_put_contents($dir . '/' . $i, json_encode(['exception' => $e::class . ': ' . $e->getMessage()], JSON_THROW_ON_ERROR));
                    exit(1);
                }
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status), file_get_contents($dir . '/' . array_search($pid, $pids, true)) ?: '');
        }
        $results = [];
        foreach ([0, 1] as $i) {
            $results[] = json_decode((string) file_get_contents($dir . '/' . $i), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($pids[$i], $results[$i]['pid'] ?? null);
        }
        @unlink($dir . '/0'); @unlink($dir . '/1'); @rmdir($dir);
        return $results;
    }

    private function source(): array { return ['source_language' => 'en', 'cognitive_type' => 'QCM_RECOGNITION', 'question' => 'Which color?', 'choices' => ['a' => 'Blue', 'b' => 'Red', 'c' => 'Green', 'd' => 'Yellow'], 'correct_answer_key' => 'a', 'sv' => 'Blue is a color.']; }
    private function target(): array { return ['question' => 'Quelle couleur ?', 'choices' => ['a' => 'Bleu', 'b' => 'Rouge', 'c' => 'Vert', 'd' => 'Jaune'], 'correct_answer_key' => 'a', 'sv' => 'Le bleu est une couleur.']; }
    private function sourceRevision(array $source): string { return hash('sha256', json_encode(['question' => $source['question'], 'choices' => $source['choices'], 'correct_answer_key' => $source['correct_answer_key'], 'sv' => $source['sv']], JSON_THROW_ON_ERROR)); }
    private function identityRevision(object $run): string
    {
        $identity = [];
        foreach (['depth', 'domain_code', 'subdomain_active', 'subject_active', 'dominant_idea_active',
            'kernel_code_dd', 'kernel_code_do', 'kernel_code_sub', 'kernel_code_suj', 'kernel_code_ide',
            'kernel_code_vvvv', 'kernel_code'] as $column) $identity[$column] = (string) $run->{$column};
        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }
}