<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Phase2;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Phase1\Phase1ExecutionRepository;
use App\Services\QuestionBank\Phase2\Phase2ExecutionOrchestrator;
use App\Services\QuestionBank\Phase2\Phase2Provider;
use App\Services\QuestionBank\Phase2\Phase2ProviderRequest;
use App\Services\QuestionBank\Phase2\Phase2ProviderResponse;
use App\Services\QuestionBank\Phase2\Phase2ProviderTechnicalFailure;
use App\Services\QuestionBank\Phase2\Phase2TranslationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Closure;
use LogicException;
use Throwable;
use Tests\TestCase;

/**
 * These tests are intended exclusively for scripts/run-isolated-postgres-tests.php.
 * The provider is wholly simulated; no HTTP client or real database is used.
 */
final class Phase2TranslationPostgresTest extends TestCase
{
    private const BLUEPRINT = 'bp-phase2-postgres-test';
    private Phase2TranslationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new Phase2TranslationRepository();
        DB::table('kernel_phase2_resolution_events')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase2_operation_signals')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase2_translation_attempts')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase1_executions')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT)->delete();

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => self::BLUEPRINT, 'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth' => 4, 'domain_code' => 'SCI', 'subdomain_active' => 'Physics',
            'subject_active' => 'Light', 'dominant_idea_active' => 'Refraction',
            'kernel_code_dd' => '04', 'kernel_code_do' => 'SCI', 'kernel_code_sub' => 'PHY',
            'kernel_code_suj' => 'LIG', 'kernel_code_ide' => 'REF', 'kernel_code_vvvv' => '0000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('kernel_phase1_executions')->insert([
            'execution_id' => (string) Str::uuid(), 'blueprint_id' => self::BLUEPRINT,
            'identity_revision' => $this->identityRevision(), 'state' => 'COMPLETED',
            'lease_token' => (string) Str::uuid(), 'result' => json_encode([
                'phase1_terminal' => Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED,
                'creation_status' => 'CREATED',
            ], JSON_THROW_ON_ERROR), 'started_at' => now(), 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $rows = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $rows[] = [
                'blueprint_id' => self::BLUEPRINT, 'cognitive_type' => $type,
                'source' => json_encode($this->source($type), JSON_THROW_ON_ERROR),
                'creation_failure' => null, 'translations' => '{}', 'creation_status' => 'CREATED',
                'validation_status' => 'PASS', 'validation_findings' => '[]',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('kernel_blueprint_cognitive_slots')->insert($rows);
    }

    protected function tearDown(): void
    {
        DB::table('kernel_phase2_resolution_events')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase2_operation_signals')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase2_translation_attempts')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_phase1_executions')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)->delete();
        DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT)->delete();
        parent::tearDown();
    }

    public function test_complete_run_creates_exactly_nine_languages_and_63_targets_from_en(): void
    {
        $provider = new Phase2FakeProvider();
        $this->assertSame(
            self::BLUEPRINT,
            (new Phase2ExecutionOrchestrator($this->repository, $provider))->run(self::BLUEPRINT)
        );
        $units = DB::table('kernel_phase2_translation_units')->get();
        $this->assertCount(63, $units);
        $languages = DB::table('kernel_phase2_translation_units')
            ->where('cognitive_type', 'QCM_RECOGNITION')->orderBy('language_code')->pluck('language_code')->all();
        $expectedLanguages = Phase2TranslationRepository::LANGUAGES;
        sort($expectedLanguages);
        $this->assertSame($expectedLanguages, $languages);
        $this->assertSame(63, $units->where('creation_status', 'CREATED')->count());
        $this->assertSame(63, $units->where('validation_status', 'NOT_VALIDATED')->count());
        $this->assertSame(63, $units->where('translation_revision', 1)->count());
        $this->assertNotEmpty($provider->requests);
        foreach ($provider->requests as $request) {
            $payload = $request->externalPayload();
            $this->assertSame('en', $payload['source_language']);
            foreach (['blueprint_id','source_revision','retry_cycle','attempt_number','claim_token','yellow_revision'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $payload);
            }
        }
    }

    public function test_blocked_source_does_not_create_unit_attempt_or_call_provider(): void
    {
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)
            ->where('cognitive_type', 'QCM_TRAP')->update([
                'validation_status' => 'SUSPICION',
                'validation_findings' => json_encode([['reason_code' => 'SOURCE_AMBIGUOUS']]),
            ]);
        $provider = new Phase2FakeProvider();
        (new Phase2ExecutionOrchestrator($this->repository, $provider))->run(self::BLUEPRINT);
        $this->assertSame(0, DB::table('kernel_phase2_translation_units')
            ->where('cognitive_type', 'QCM_TRAP')->count());
        $this->assertSame(0, DB::table('kernel_phase2_translation_attempts')
            ->where('cognitive_type', 'QCM_TRAP')->count());
        $this->assertCount(54, $provider->requests);
    }

    public function test_qcm_and_true_false_shapes_and_immutable_keys_are_enforced(): void
    {
        $provider = new Phase2FakeProvider();
        (new Phase2ExecutionOrchestrator($this->repository, $provider))->run(self::BLUEPRINT);
        foreach (DB::table('kernel_phase2_translation_units')->get() as $unit) {
            $translation = json_decode((string) $unit->translation, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(
                str_starts_with($unit->cognitive_type, 'QCM_') ? ['a','b','c','d'] : ['a','b'],
                array_keys($translation['choices'])
            );
            $this->assertSame(
                str_ends_with($unit->cognitive_type, '_FALSE') ? 'b' : 'a',
                $translation['correct_answer_key']
            );
            $this->assertNotSame('TRUE', $translation['choices']['a']);
            $this->assertNotSame('FALSE', $translation['choices']['b']);
        }
    }

    public function test_network_replay_is_noop_and_different_content_under_same_operation_is_stale(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $claim = $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr',
            $preflight['source_revision']['QCM_RECOGNITION'], $preflight['blueprint'],
            $preflight['slots']['QCM_RECOGNITION']);
        $response = (new Phase2FakeProvider())->translate($claim['request']);
        $this->assertSame('CREATED', $this->repository->apply($claim['operation_id'], $response));
        $this->assertSame(Phase2TranslationRepository::NO_OP, $this->repository->apply($claim['operation_id'], $response));
        $different = new Phase2ProviderResponse($response->providerRequestReference, 'different',
            'fr', array_replace($response->translation, ['question' => 'different']));
        $this->assertSame(Phase2TranslationRepository::STALE_RESULT, $this->repository->apply($claim['operation_id'], $different));
    }

    public function test_failures_are_isolated_and_fourth_attempt_is_permanent_with_only_one_signal(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_RECOGNITION'; $revision = $preflight['source_revision'][$type];
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $claim = $this->repository->claim(self::BLUEPRINT, $type, 'fr', $revision,
                $preflight['blueprint'], $preflight['slots'][$type]);
            $this->assertSame(
                $attempt === 4 ? Phase2TranslationRepository::NON_RETRYABLE_TECHNICAL_FAILURE
                    : Phase2TranslationRepository::RETRYABLE_TECHNICAL_FAILURE,
                $this->repository->fail($claim['operation_id'], new Phase2ProviderTechnicalFailure('NETWORK'))
            );
            if ($attempt < 4) {
                $delay = now()->diffInSeconds(DB::table('kernel_phase2_translation_units')
                    ->where('cognitive_type', $type)->where('language_code', 'fr')->value('next_attempt_at'), false);
                $this->assertGreaterThanOrEqual([1 => 50, 2 => 290, 3 => 890][$attempt], $delay);
            }
            if ($attempt < 4) DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', self::BLUEPRINT)->where('cognitive_type', $type)
                ->where('language_code', 'fr')->update(['next_attempt_at' => now()->subMinute()]);
        }
        $unit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'fr')->first();
        $this->assertSame('PERMANENT_FAILURE', $unit->state);
        $this->assertSame(1, DB::table('kernel_phase2_operation_signals')->where('language_code', 'fr')->count());
        $this->assertSame(0, DB::table('kernel_phase2_operation_signals')->where('language_code', 'es')->count());
    }

    public function test_yellow_correction_increments_only_one_language_and_stales_old_result(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_RECOGNITION'; $revision = $preflight['source_revision'][$type];
        $claim = $this->repository->claim(self::BLUEPRINT, $type, 'fr', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $response = (new Phase2FakeProvider())->translate($claim['request']);
        $this->assertSame('CREATED', $this->repository->apply($claim['operation_id'], $response));
        $refresh = $this->repository->openRefreshClaim(self::BLUEPRINT, $type, 'fr', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $corrected = array_replace($response->translation, ['question' => 'Correction jaune']);
        $this->assertSame('CORRECTED', $this->repository->applyYellowCorrection(
            self::BLUEPRINT, $type, 'fr', $revision, 1, null, $corrected
        ));
        $this->assertSame(Phase2TranslationRepository::STALE_RESULT,
            $this->repository->apply($refresh['operation_id'], $response));
        $unit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'fr')->first();
        $this->assertSame(2, $unit->translation_revision);
        $this->assertSame('NOT_VALIDATED', $unit->validation_status);
    }

    public function test_independent_process_claims_and_resolution_event_are_single_winner(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for PostgreSQL race proof.');
        }
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_RECOGNITION'; $revision = $preflight['source_revision'][$type];
        $dir = sys_get_temp_dir() . '/phase2-race-' . bin2hex(random_bytes(5));
        mkdir($dir);
        $results = $this->runTwoChildRace($dir, function (): array {
            DB::purge('pgsql');
            $preflight = $this->repository->preflight(self::BLUEPRINT);
            return $this->repository->claim(self::BLUEPRINT, 'QCM_RECOGNITION', 'zh',
                $preflight['source_revision']['QCM_RECOGNITION'],
                $preflight['blueprint'], $preflight['slots']['QCM_RECOGNITION']);
        });
        $values = array_map(static fn (array $r): mixed => $r['value'], $results);
        $applicable = array_values(array_filter(
            $values,
            static fn (mixed $value): bool => is_array($value) && isset($value['operation_id'])
        ));
        $this->assertCount(1, $applicable);
        $this->assertSame(1, DB::table('kernel_phase2_translation_attempts')
            ->where('cognitive_type', $type)->where('language_code', 'zh')->count());
        $this->removeRaceDirectory($dir);
        $this->markPermanent('it');
        $event = (string) Str::uuid();
        $dir2 = sys_get_temp_dir() . '/phase2-resolution-' . bin2hex(random_bytes(5));
        mkdir($dir2);
        $outcomes = $this->runTwoChildRace($dir2, function () use ($type, $revision, $event): bool {
                DB::purge('pgsql');
                return $this->repository->authorizeRetryCycle(
                    self::BLUEPRINT, $type, 'it', $revision, $event, 'SYSTEM_RECOVERY', 'RACE_TEST'
                );
        });
        $outcomes = array_map(static fn (array $r): string => $r['value'] ? '1' : '0', $outcomes);
        sort($outcomes);
        $this->assertSame(['0', '1'], $outcomes);
        $this->assertSame(1, DB::table('kernel_phase2_resolution_events')
            ->where('resolution_event_id', $event)->count());
        $this->removeRaceDirectory($dir2);
        $this->removeRaceDirectory($dir);
    }

    public function test_independent_process_duplicate_apply_and_correction_are_fenced(): void
    {
        if (!function_exists('pcntl_fork')) $this->markTestSkipped('pcntl required.');
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_RECOGNITION'; $revision = $preflight['source_revision'][$type];
        $claim = $this->repository->claim(self::BLUEPRINT, $type, 'el', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $response = (new Phase2FakeProvider())->translate($claim['request']);
        $dir = sys_get_temp_dir() . '/phase2-apply-' . bin2hex(random_bytes(5)); mkdir($dir);
        $outcomes = $this->runTwoChildRace($dir, function () use ($claim, $response): string {
            DB::purge('pgsql');
            return $this->repository->apply($claim['operation_id'], $response);
        });
        $outcomes = array_map(static fn (array $r): string => (string) $r['value'], $outcomes);
        sort($outcomes);
        $this->assertSame(['CREATED', Phase2TranslationRepository::NO_OP], $outcomes);
        $attempt = DB::table('kernel_phase2_translation_attempts')
            ->where('operation_id', $claim['operation_id'])->first();
        $this->assertSame('APPLIED', $attempt->outcome);
        $this->assertSame(Phase2TranslationRepository::STALE_RESULT,
            $this->repository->apply($claim['operation_id'], new Phase2ProviderResponse(
                $response->providerRequestReference . '-wrong', $response->providerRequestId,
                'el', $response->translation
            )));
        $this->assertSame(Phase2TranslationRepository::STALE_RESULT,
            $this->repository->apply($claim['operation_id'], new Phase2ProviderResponse(
                $response->providerRequestReference, $response->providerRequestId,
                'fr', $response->translation
            )));
        $this->removeRaceDirectory($dir);

        $unit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'el')->first();
        $correction = $response->translation;
        $correction['question'] = 'simultaneous correction';
        $dir = sys_get_temp_dir() . '/phase2-correction-' . bin2hex(random_bytes(5)); mkdir($dir);
        $outcomes = $this->runTwoChildRace($dir, function () use ($unit, $correction, $revision, $type): string {
                DB::purge('pgsql');
                return $this->repository->applyYellowCorrection(
                    self::BLUEPRINT, $type, 'el', $revision, (int) $unit->translation_revision,
                    $unit->yellow_revision, $correction
                );
        });
        $outcomes = array_map(static fn (array $r): string => (string) $r['value'], $outcomes);
        sort($outcomes);
        $this->assertSame(['CORRECTED', Phase2TranslationRepository::STALE_RESULT], $outcomes);
        $this->removeRaceDirectory($dir);
        $this->assertSame(2, DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'el')->value('translation_revision'));
    }

    public function test_canonical_answer_key_is_fenced_for_qcm_and_all_true_false_forms(): void
    {
        $languages = ['QCM_RECOGNITION' => 'fr', 'QCM_REASONING' => 'es',
            'QCM_TRAP' => 'de', 'TRUE_FALSE_RECOGNITION_TRUE' => 'it',
            'TRUE_FALSE_RECOGNITION_FALSE' => 'pt', 'TRUE_FALSE_REASONING_TRUE' => 'ru',
            'TRUE_FALSE_REASONING_FALSE' => 'zh'];
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        foreach ($languages as $type => $language) {
            $claim = $this->repository->claim(self::BLUEPRINT, $type, $language,
                $preflight['source_revision'][$type], $preflight['blueprint'], $preflight['slots'][$type]);
            $response = (new Phase2FakeProvider())->translate($claim['request']);
            $this->assertSame('CREATED', $this->repository->apply($claim['operation_id'], $response));
            $unit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
                ->where('language_code', $language)->first();
            $bad = $response->translation;
            $bad['correct_answer_key'] = $bad['correct_answer_key'] === 'a' ? 'b' : 'a';
            $this->assertSame(Phase2TranslationRepository::STALE_RESULT,
                $this->repository->applyYellowCorrection(self::BLUEPRINT, $type, $language,
                    $preflight['source_revision'][$type], (int) $unit->translation_revision,
                    $unit->yellow_revision, $bad));
            $after = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
                ->where('language_code', $language)->first();
            $this->assertSame($unit->translation_revision, $after->translation_revision);
        }
    }

    public function test_second_concurrent_opening_is_not_applicable_and_retry_gets_new_opaque_key(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_RECOGNITION'; $revision = $preflight['source_revision'][$type];
        $first = $this->repository->claim(self::BLUEPRINT, $type, 'de', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $second = $this->repository->claim(self::BLUEPRINT, $type, 'de', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $this->assertSame(Phase2TranslationRepository::NO_OP, $second['outcome']);
        $this->repository->fail($first['operation_id'], new Phase2ProviderTechnicalFailure('TIMEOUT', true, 120));
        $row = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'de')->first();
        $this->assertGreaterThanOrEqual(100, now()->diffInSeconds($row->next_attempt_at, false));
        DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'de')->update(['next_attempt_at' => now()->subSecond()]);
        $next = $this->repository->claim(self::BLUEPRINT, $type, 'de', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $oldAttempt = DB::table('kernel_phase2_translation_attempts')
            ->where('operation_id', $first['operation_id'])->first();
        $newAttempt = DB::table('kernel_phase2_translation_attempts')
            ->where('operation_id', $next['operation_id'])->first();
        $this->assertNotSame($oldAttempt->external_idempotency_key, $newAttempt->external_idempotency_key);
    }

    public function test_expired_claim_reclaims_and_old_result_is_stale(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_TRAP'; $revision = $preflight['source_revision'][$type];
        $old = $this->repository->claim(self::BLUEPRINT, $type, 'ru', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'ru')->update(['claim_expires_at' => now()->subSecond()]);
        $new = $this->repository->claim(self::BLUEPRINT, $type, 'ru', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $response = (new Phase2FakeProvider())->translate($old['request']);
        $this->assertSame(Phase2TranslationRepository::STALE_RESULT,
            $this->repository->apply($old['operation_id'], $response));
        $this->assertNotSame($old['operation_id'], $new['operation_id']);
    }

    public function test_expired_claim_failure_is_stale_without_any_mutation(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_TRAP'; $revision = $preflight['source_revision'][$type];
        $claim = $this->repository->claim(self::BLUEPRINT, $type, 'ar', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'ar')->update(['claim_expires_at' => now()->subSecond()]);
        $beforeUnit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'ar')->first();
        $beforeAttempt = DB::table('kernel_phase2_translation_attempts')
            ->where('operation_id', $claim['operation_id'])->first();
        $beforeSignals = DB::table('kernel_phase2_operation_signals')->count();
        $this->assertSame(Phase2TranslationRepository::STALE_RESULT,
            $this->repository->fail($claim['operation_id'], new Phase2ProviderTechnicalFailure('TIMEOUT', true)));
        $afterUnit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'ar')->first();
        $afterAttempt = DB::table('kernel_phase2_translation_attempts')
            ->where('operation_id', $claim['operation_id'])->first();
        $this->assertSame($beforeUnit->state, $afterUnit->state);
        $this->assertSame($beforeUnit->claim_token, $afterUnit->claim_token);
        $this->assertSame($beforeAttempt->outcome, $afterAttempt->outcome);
        $this->assertSame($beforeSignals, DB::table('kernel_phase2_operation_signals')->count());
    }

    public function test_source_change_creates_new_identity_and_stale_result_is_atomic_noop(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_REASONING'; $oldRevision = $preflight['source_revision'][$type];
        $claim = $this->repository->claim(self::BLUEPRINT, $type, 'fr', $oldRevision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $source = $this->source($type);
        $source['question'] = 'Changed English source';
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', self::BLUEPRINT)
            ->where('cognitive_type', $type)->update(['source' => json_encode($source, JSON_THROW_ON_ERROR)]);
        $response = (new Phase2FakeProvider())->translate($claim['request']);
        $before = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)->first();
        $this->assertSame(Phase2TranslationRepository::STALE_RESULT, $this->repository->apply($claim['operation_id'], $response));
        $after = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)->first();
        $this->assertSame($before->state, $after->state);
        $this->assertSame($oldRevision, $before->source_revision);
        $fresh = $this->repository->preflight(self::BLUEPRINT);
        $this->assertNotSame($oldRevision, $fresh['source_revision'][$type]);
        $new = $this->repository->claim(self::BLUEPRINT, $type, 'fr', $fresh['source_revision'][$type],
            $fresh['blueprint'], $fresh['slots'][$type]);
        $this->assertSame('CREATED', $this->repository->apply(
            $new['operation_id'], (new Phase2FakeProvider())->translate($new['request'])
        ));
        $this->assertSame(1, DB::table('kernel_phase2_translation_units')
            ->where('cognitive_type', $type)->where('source_revision', $fresh['source_revision'][$type])
            ->value('translation_revision'));
    }

    public function test_nonretryable_failure_is_persisted_without_content_or_validation_effect(): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $type = 'QCM_TRAP'; $revision = $preflight['source_revision'][$type];
        $claim = $this->repository->claim(self::BLUEPRINT, $type, 'it', $revision,
            $preflight['blueprint'], $preflight['slots'][$type]);
        $this->assertSame(
            Phase2TranslationRepository::NON_RETRYABLE_TECHNICAL_FAILURE,
            $this->repository->fail($claim['operation_id'], new Phase2ProviderTechnicalFailure('INVALID_CONFIGURATION', false))
        );
        $unit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'it')->first();
        $this->assertSame('PERMANENT_FAILURE', $unit->state);
        $this->assertSame('PENDING', $unit->creation_status);
        $this->assertSame('NOT_VALIDATED', $unit->validation_status);
        $this->assertNull($unit->translation);
        $this->assertSame(1, DB::table('kernel_phase2_operation_signals')
            ->where('cognitive_type', $type)->where('language_code', 'it')->count());
    }

    public function test_resolution_event_reopens_only_authorized_permanent_cycle(): void
    {
        $this->markPermanent('fr');
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $revision = $preflight['source_revision']['QCM_RECOGNITION'];
        $event = (string) Str::uuid();
        $this->assertTrue($this->repository->authorizeRetryCycle(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr', $revision, $event, 'SYSTEM_RECOVERY', 'NETWORK_RECOVERY'));
        $this->assertFalse($this->repository->authorizeRetryCycle(self::BLUEPRINT, 'QCM_RECOGNITION', 'fr', $revision, $event, 'SYSTEM_RECOVERY', 'NETWORK_RECOVERY'));
    }

    public function test_same_global_event_cannot_open_two_different_units(): void
    {
        if (!function_exists('pcntl_fork')) $this->markTestSkipped('pcntl required.');
        $this->markPermanent('fr', 'QCM_RECOGNITION');
        $this->markPermanent('es', 'QCM_REASONING');
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $unitKeys = [
            ['type' => 'QCM_RECOGNITION', 'language' => 'fr'],
            ['type' => 'QCM_REASONING', 'language' => 'es'],
        ];
        $before = [];
        foreach ($unitKeys as $key) {
            $before[$key['type'] . ':' . $key['language']] = DB::table('kernel_phase2_translation_units')
                ->where('cognitive_type', $key['type'])->where('language_code', $key['language'])->first();
        }
        $event = (string) Str::uuid();
        $dir = sys_get_temp_dir() . '/phase2-global-event-' . bin2hex(random_bytes(5)); mkdir($dir);
        $results = $this->runTwoChildRace($dir, function (int $index) use ($preflight, $event): bool {
            DB::purge('pgsql');
            $type = $index === 0 ? 'QCM_RECOGNITION' : 'QCM_REASONING';
            $language = $index === 0 ? 'fr' : 'es';
            return $this->repository->authorizeRetryCycle(
                self::BLUEPRINT, $type, $language, $preflight['source_revision'][$type],
                $event, 'SYSTEM_RECOVERY', 'GLOBAL_EVENT_RACE'
            );
        });
        $values = array_map(static fn (array $r): bool => (bool) $r['value'], $results);
        sort($values);
        $this->assertSame([false, true], $values);
        $this->assertSame(1, DB::table('kernel_phase2_resolution_events')
            ->where('resolution_event_id', $event)->count());
        $units = DB::table('kernel_phase2_translation_units')
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->where('cognitive_type', 'QCM_RECOGNITION')->where('language_code', 'fr');
                })->orWhere(function ($q): void {
                    $q->where('cognitive_type', 'QCM_REASONING')->where('language_code', 'es');
                });
            })->get();
        $this->assertCount(2, $units);
        $winner = $units->filter(static fn (object $unit): bool =>
            $unit->state === 'PENDING' && (int) $unit->retry_cycle === 1);
        $this->assertCount(1, $winner);
        $winner = $winner->first();
        $this->assertSame(0, (int) $winner->attempt_number);
        $loser = $units->first(static fn (object $unit): bool =>
            !($unit->cognitive_type === $winner->cognitive_type
                && $unit->language_code === $winner->language_code));
        $this->assertNotNull($loser);
        $loserKey = $loser->cognitive_type . ':' . $loser->language_code;
        $this->assertSame('PERMANENT_FAILURE', $loser->state);
        $this->assertSame(0, (int) $loser->retry_cycle);
        $this->assertSame((int) $before[$loserKey]->attempt_number, (int) $loser->attempt_number);
        $this->assertCount(1, $units->filter(static fn (object $unit): bool =>
            $unit->state === 'PERMANENT_FAILURE' && (int) $unit->retry_cycle === 0));
        $this->removeRaceDirectory($dir);
    }

    public function test_permanent_failure_without_translation_can_be_corrected_at_revision_one(): void
    {
        $type = 'QCM_REASONING';
        $this->markPermanent('fr', $type);
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $source = $this->source($type);
        $translation = $source;
        $translation['question'] = 'fr ' . $source['question'];
        foreach ($translation['choices'] as $key => $choice) {
            $translation['choices'][$key] = 'fr ' . (is_array($choice) ? $choice['text'] : $choice);
        }
        $this->assertSame('CORRECTED', $this->repository->applyYellowCorrection(
            self::BLUEPRINT, $type, 'fr', $preflight['source_revision'][$type], null, null, $translation
        ));
        $unit = DB::table('kernel_phase2_translation_units')->where('cognitive_type', $type)
            ->where('language_code', 'fr')->first();
        $this->assertSame(1, $unit->translation_revision);
        $this->assertSame(1, $unit->yellow_revision);
        $this->assertSame('CREATED', $unit->state);
        $this->assertNull($unit->last_technical_reason_code);
    }

    private function markPermanent(string $language, string $type = 'QCM_RECOGNITION'): void
    {
        $preflight = $this->repository->preflight(self::BLUEPRINT);
        $revision = $preflight['source_revision'][$type];
        for ($i = 0; $i < 4; $i++) {
            $claim = $this->repository->claim(self::BLUEPRINT, $type, $language, $revision,
                $preflight['blueprint'], $preflight['slots'][$type]);
            $this->repository->fail($claim['operation_id'], new Phase2ProviderTechnicalFailure('NETWORK'));
            DB::table('kernel_phase2_translation_units')->where('blueprint_id', self::BLUEPRINT)
                ->where('cognitive_type', $type)->where('language_code', $language)
                ->update(['next_attempt_at' => now()->subMinute()]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function runTwoChildRace(string $directory, Closure $action): array
    {
        $pids = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new LogicException('pcntl_fork failed.');
            }
            if ($pid === 0) {
                $path = $directory . '/' . $i . '.json';
                try {
                    $value = $action($i);
                    $payload = ['ok' => true, 'value' => $value];
                    $json = json_encode($payload, JSON_THROW_ON_ERROR);
                    if (file_put_contents($path, $json) === false) exit(2);
                    exit(0);
                } catch (Throwable $error) {
                    try {
                        $json = json_encode([
                            'ok' => false, 'error_type' => $error::class,
                            'error_message' => $error->getMessage(),
                        ], JSON_THROW_ON_ERROR);
                        if (file_put_contents($path, $json) === false) exit(2);
                    } catch (Throwable) {
                        exit(2);
                    }
                    exit(1);
                }
            }
            $pids[$i] = $pid;
        }
        $results = [];
        for ($i = 0; $i < 2; $i++) {
            $waited = pcntl_waitpid($pids[$i], $status);
            $this->assertSame($pids[$i], $waited, "Did not reap child {$i}.");
            $this->assertTrue(pcntl_wifexited($status), "Child {$i} did not exit normally.");
            if (pcntl_wexitstatus($status) !== 0) {
                $detail = @file_get_contents($directory . '/' . $i . '.json');
                $this->fail("Child {$i} failed: " . (string) $detail);
            }
        }
        $files = scandir($directory);
        $files = array_values(array_filter($files ?: [], static fn (string $f): bool => $f !== '.' && $f !== '..'));
        $this->assertSame(['0.json', '1.json'], $files, 'Race must produce exactly two result files.');
        for ($i = 0; $i < 2; $i++) {
            $contents = file_get_contents($directory . '/' . $i . '.json');
            $this->assertNotFalse($contents, "Result {$i} is unreadable.");
            $decoded = json_decode((string) $contents, true);
            $this->assertIsArray($decoded, "Result {$i} is invalid JSON.");
            $this->assertArrayHasKey('ok', $decoded, "Result {$i} is incomplete.");
            $this->assertTrue($decoded['ok'], "Child {$i} reported an exception.");
            $this->assertArrayHasKey('value', $decoded, "Result {$i} lacks value.");
            $results[] = $decoded;
        }
        return $results;
    }

    private function removeRaceDirectory(string $directory): void
    {
        foreach (['0.json', '1.json'] as $file) {
            if (is_file($directory . '/' . $file)) unlink($directory . '/' . $file);
        }
        if (is_dir($directory)) rmdir($directory);
    }

    private function identityRevision(): string
    {
        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BLUEPRINT)->first();
        $identity = [];
        foreach ([
            'depth', 'domain_code', 'subdomain_active', 'subject_active',
            'dominant_idea_active', 'kernel_code_dd', 'kernel_code_do',
            'kernel_code_sub', 'kernel_code_suj', 'kernel_code_ide',
            'kernel_code_vvvv', 'kernel_code',
        ] as $column) {
            $identity[$column] = (string) $run->{$column};
        }
        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    private function source(string $type): array
    {
        $qcm = str_starts_with($type, 'QCM_');
        return [
            'schema_version' => 'phase1.source.v1', 'source_language' => 'en',
            'cognitive_type' => $type, 'question' => "Question {$type}",
            'choices' => $qcm ? ['a' => 'Correct fact', 'b' => 'Distractor one', 'c' => 'Distractor two', 'd' => 'Distractor three']
                : ['a' => 'TRUE', 'b' => 'FALSE'],
            'correct_answer_key' => str_ends_with($type, '_FALSE') ? 'b' : 'a',
            'sv' => 'Source explanation.',
        ];
    }
}

final class Phase2FakeProvider implements Phase2Provider
{
    /** @var array<int,Phase2ProviderRequest> */
    public array $requests = [];

    public function translate(Phase2ProviderRequest $request): Phase2ProviderResponse
    {
        $this->requests[] = $request;
        $source = $request->sourcePayload;
        $choices = [];
        foreach ($source['choices'] as $key => $value) {
            $choices[$key] = $request->targetLanguage . ' ' . $value;
        }
        if (!str_starts_with($request->cognitiveType, 'QCM_')) {
            $labels = Phase2TranslationRepository::TARGET_TRUE_FALSE_LABELS[$request->targetLanguage];
            $choices = ['a' => $labels[0], 'b' => $labels[1]];
        }
        return new Phase2ProviderResponse(
            $request->providerRequestReference, 'provider-' . count($this->requests),
            $request->targetLanguage, [
                'question' => $request->targetLanguage . ' ' . $source['question'],
                'choices' => $choices, 'correct_answer_key' => $source['correct_answer_key'],
                'sv' => $request->targetLanguage . ' ' . $source['sv'],
            ]
        );
    }
}