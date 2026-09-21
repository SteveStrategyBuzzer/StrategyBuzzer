<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Quarantine;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Quarantine\KernelCurrentKernelReceivedRouter;
use App\Services\QuestionBank\Quarantine\KernelQuarantineAdminService;
use App\Services\QuestionBank\Quarantine\KernelQuarantineWorkCopyRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class QuarantineBackendPostgresTest extends TestCase
{
    private string $blueprintId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blueprintId = (string) Str::orderedUuid();
        $suffix = strtoupper(substr(str_replace('-', '', $this->blueprintId), -4));
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $this->blueprintId, 'execution_state' => 'READY_BANK_RECEIVED',
            'depth' => 6, 'domain_code' => 'SCI', 'subdomain_active' => 'Physics',
            'subject_active' => 'Light', 'dominant_idea_active' => 'Refraction',
            'kernel_code_dd' => '06', 'kernel_code_do' => 'SCI', 'kernel_code_sub' => 'PHY',
            'kernel_code_suj' => 'LIG', 'kernel_code_ide' => 'REF', 'kernel_code_vvvv' => $suffix,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            DB::table('kernel_blueprint_cognitive_slots')->insert([
                'blueprint_id' => $this->blueprintId, 'cognitive_type' => $type,
                'source' => json_encode($this->source($type), JSON_THROW_ON_ERROR),
                'translations' => json_encode($this->translations($type), JSON_THROW_ON_ERROR),
                'creation_status' => 'CREATED', 'validation_status' => 'PASS',
                'validation_findings' => '[]', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $this->blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['validation_status' => 'SUSPICION', 'validation_findings' => '["MEANING_DRIFT"]']);
    }

    protected function tearDown(): void
    {
        DB::statement(
            'TRUNCATE TABLE kernel_quarantine_requests, kernel_quarantine_resume_intents, '
            . 'kernel_current_kernel_dispatches, kernel_quarantine_slot_resumptions, '
            . 'kernel_quarantine_work_copy_slots, kernel_quarantine_work_copies, '
            . 'kernel_blueprint_cognitive_slots, kernel_blueprint_runs CASCADE'
        );
        parent::tearDown();
    }

    public function test_complete_quarantine_copy_contains_all_seven_slots_and_nine_translations(): void
    {
        $copy = $this->copy();
        $slots = DB::table('kernel_quarantine_work_copy_slots')->where('copy_id', $copy['copy_id'])->get();
        self::assertCount(7, $slots);
        foreach ($slots as $slot) {
            self::assertCount(9, json_decode((string) $slot->translations, true, 512, JSON_THROW_ON_ERROR));
        }
        self::assertSame('SUSPICION', DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('cause_code'));
    }

    public function test_structured_findings_and_evidence_are_durable_on_request_and_copy(): void
    {
        $evidence = ['rule_code' => 'LANGUAGE_DRIFT', 'evidence' => ['token' => 'answer']];
        $copy = (new KernelQuarantineAdminService())->createCopyFromRequest($this->blueprintId, 'VALIDATION_PHASE1', 'evidence-1', 'SUSPICION', $evidence);
        self::assertJsonStringEqualsJsonString(json_encode($evidence, JSON_THROW_ON_ERROR), (string) DB::table('kernel_quarantine_requests')->where('request_id', $copy['request_id'])->value('cause_payload'));
        self::assertJsonStringEqualsJsonString(json_encode($evidence, JSON_THROW_ON_ERROR), (string) DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('cause_payload'));
    }

    public function test_technical_incident_without_content_cause_is_rejected(): void
    {
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $this->blueprintId)
            ->update(['validation_status' => 'PASS', 'validation_findings' => '[]']);
        $this->expectException(LogicException::class);
        $this->service()->createCopyFromRequest($this->blueprintId, 'VALIDATION_PHASE1', 'technical-1', 'PERMANENT_FAILURE', ['exception' => 'timeout']);
    }

    public function test_copy_and_request_survive_a_new_service_instance(): void
    {
        $copy = $this->copy('persist-1');
        $replay = (new KernelQuarantineAdminService())->createCopyFromRequest($this->blueprintId, 'VALIDATION_PHASE1', 'persist-1', 'SUSPICION', ['finding' => 'MEANING_DRIFT']);
        self::assertSame($copy['copy_id'], $replay['copy_id']);
    }

    public function test_source_correction_before_claim_keeps_version_and_expires_nine_old_translations(): void
    {
        $copy = $this->copy();
        $before = $this->slot($copy['copy_id'])->translations;
        $source = $this->source('QCM_RECOGNITION');
        $source['question'] = 'Which primary color is shown?';
        $result = $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $source, 1);
        self::assertSame(1, $result['copy_version']);
        $after = $this->slot($copy['copy_id'])->translations;
        self::assertCount(9, $after);
        foreach ($after as $language => $translation) {
            self::assertSame($before[$language]['source_revision'], $translation['source_revision']);
            self::assertSame('STALE', $translation['validation_status']);
        }
        self::assertNotSame($before['fr']['source_revision'], $this->slot($copy['copy_id'])->source_revision);
    }

    public function test_translation_correction_changes_only_target_language_and_increments_its_revision(): void
    {
        $copy = $this->copy();
        $before = $this->slot($copy['copy_id'])->translations;
        $translation = $before['fr'];
        $translation['question'] = 'Quelle couleur primaire est montrée ?';
        $this->service()->prepareTranslationCorrection($copy['copy_id'], 'QCM_RECOGNITION', 'fr', $translation, 1);
        $after = $this->slot($copy['copy_id'])->translations;
        self::assertSame($before['fr']['translation_revision'] + 1, $after['fr']['translation_revision']);
        self::assertSame('Quelle couleur primaire est montrée ?', $after['fr']['question']);
        self::assertSame($before['fr']['source_revision'], $after['fr']['source_revision']);
        self::assertSame('NOT_VALIDATED', $after['fr']['validation_status']);
        self::assertSame(array_diff_key($before, ['fr' => true]), array_diff_key($after, ['fr' => true]));
    }

    public function test_qcm_payload_requires_exactly_four_choices(): void
    {
        $copy = $this->copy();
        $bad = $this->source('QCM_RECOGNITION');
        unset($bad['choices']['d']);
        $this->expectException(LogicException::class);
        $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $bad, 1);
    }

    public function test_true_false_payload_requires_two_choices_and_authoritative_polarity(): void
    {
        $copy = $this->copy();
        $bad = $this->source('TRUE_FALSE_RECOGNITION_TRUE');
        $bad['correct_answer_key'] = 'b';
        $this->expectException(LogicException::class);
        $this->service()->prepareSourceCorrection($copy['copy_id'], 'TRUE_FALSE_RECOGNITION_TRUE', $bad, 1);
    }

    public function test_answer_key_is_immutable_for_source_and_translation_corrections(): void
    {
        $copy = $this->copy();
        $source = $this->source('QCM_RECOGNITION');
        $source['correct_answer_key'] = 'b';
        try {
            $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $source, 1);
            self::fail('A source answer-key change must be rejected.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        $translation = $this->translations('QCM_RECOGNITION')['fr'];
        $translation['correct_answer_key'] = 'b';
        try {
            $this->service()->prepareTranslationCorrection(
                $copy['copy_id'],
                'QCM_RECOGNITION',
                'fr',
                $translation,
                1,
            );
            self::fail('A translation answer-key change must be rejected.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
    }

    public function test_manual_correction_is_yellow_and_resumption_index_is_exact(): void
    {
        $copy = $this->copy();
        $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $this->source('QCM_RECOGNITION'), 1);
        self::assertSame('YELLOW', $this->slot($copy['copy_id'])->color);
        self::assertSame(1, DB::table('kernel_quarantine_slot_resumptions')->where('copy_id', $copy['copy_id'])
            ->where('cognitive_type', 'QCM_RECOGNITION')->where('copy_version', 1)->count());
    }

    public function test_enqueue_and_claim_are_deterministic_fifo(): void
    {
        $first = $this->copy('fifo-1');
        $this->service()->enqueue($first['copy_id'], 1, 'fifo-event-1');
        $claimed = (new KernelQuarantineWorkCopyRepository())->claimOldestReady('fifo-claim');
        self::assertSame($first['copy_id'], $claimed->copy_id);
    }

    public function test_router_decision_is_durable_and_prioritizes_quarantine(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'route-1');
        $decision = (new KernelCurrentKernelReceivedRouter())->decide('receipt-1', $this->blueprintId);
        self::assertSame(KernelCurrentKernelReceivedRouter::QUARANTINE, $decision['direction']);
        self::assertSame(1, DB::table('kernel_current_kernel_dispatches')->where('event_id', 'receipt-1')->count());
        $blocked = (new KernelCurrentKernelReceivedRouter())->decide('receipt-2', $this->blueprintId);
        self::assertSame(KernelCurrentKernelReceivedRouter::BLOCKED, $blocked['direction']);
        self::assertNull($blocked['claim_token']);
        self::assertSame($blocked, (new KernelCurrentKernelReceivedRouter())->decide('receipt-2', $this->blueprintId));
        self::assertSame(1, DB::table('kernel_current_kernel_dispatches')->where('event_id', 'receipt-2')->count());
        try {
            $this->service()->editSlot(
                $copy['copy_id'],
                'QCM_RECOGNITION',
                ['source' => $this->source('QCM_RECOGNITION')],
                1,
                0,
                null,
                $blocked['claim_token'],
            );
            self::fail('A blocked event must not receive claim authority.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
    }

    public function test_divergent_intake_replay_is_rejected(): void
    {
        $this->copy('replay-1');
        $this->expectException(LogicException::class);
        $this->service()->createCopyFromRequest($this->blueprintId, 'PHASE1', 'replay-1', 'SUSPICION', ['different' => true]);
    }

    public function test_concurrent_identical_intake_replay_returns_one_durable_copy(): void
    {
        $results = $this->forkRace(fn (): array => $this->service()->createCopyFromRequest(
            $this->blueprintId,
            'VALIDATION_PHASE1',
            'concurrent-intake',
            'SUSPICION',
            ['finding' => 'MEANING_DRIFT'],
        ));
        self::assertCount(2, array_filter($results, static fn (array $result): bool => isset($result['value'])));
        self::assertSame($results[0]['value']['copy_id'], $results[1]['value']['copy_id']);
        self::assertSame(1, DB::table('kernel_quarantine_requests')->where('idempotency_key', 'concurrent-intake')->count());
        self::assertSame(1, DB::table('kernel_quarantine_work_copies')->count());
    }

    public function test_divergent_intent_replay_is_rejected(): void
    {
        $copy = $this->copy();
        $service = $this->service();
        $service->requestRegeneration($copy['copy_id'], 1, 'PHASE1', 'RESUME_PHASE1', null, null, 'intent-1', ['x' => 1]);
        $this->expectException(LogicException::class);
        $service->requestRegeneration($copy['copy_id'], 1, 'PHASE2', 'RESUME_PHASE2', null, null, 'intent-1', ['x' => 2]);
    }

    public function test_double_correction_same_revision_has_one_successful_child(): void
    {
        $copy = $this->copy();
        $results = $this->forkRace(function () use ($copy): array {
            $source = $this->source('QCM_RECOGNITION');
            $source['question'] = 'Concurrent correction';
            return $this->service()->prepareSourceCorrection(
                $copy['copy_id'],
                'QCM_RECOGNITION',
                $source,
                1,
                0,
            );
        });
        self::assertSame(1, count(array_filter($results, static fn (array $r): bool => isset($r['value']))));
        self::assertSame(1, count(array_filter($results, static fn (array $r): bool => isset($r['exception']))));
    }

    public function test_double_claim_has_one_successful_child(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'claim-race-event');
        $results = $this->forkRace(fn (): bool => (new KernelQuarantineWorkCopyRepository())->claim($copy['copy_id'], 1, (string) Str::uuid()));
        self::assertSame(1, count(array_filter($results, static fn (array $r): bool => ($r['value'] ?? false) === true)));
    }

    public function test_stale_correction_race_rejects_old_revision_after_newer_version(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'stale-race-event');
        $claim = (new KernelQuarantineWorkCopyRepository())->claimOldestReady('stale-claim');
        $results = $this->forkRace(function () use ($copy, $claim): array {
            $source = $this->source('QCM_RECOGNITION');
            $source['question'] = 'Race revision';
            return $this->service()->editSlot($copy['copy_id'], 'QCM_RECOGNITION', ['source' => $source], 1, 0, null, $claim->claim_token);
        });
        self::assertSame(1, count(array_filter($results, static fn (array $r): bool => isset($r['value']))));
        self::assertSame(1, count(array_filter($results, static fn (array $r): bool => isset($r['exception']))));
        self::assertSame(2, (int) DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('copy_version'));
    }

    public function test_expired_claim_restores_copy_dispatch_and_gate_atomically(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'expiry-event');
        $repo = new KernelQuarantineWorkCopyRepository();
        $repo->claimOldestReady('expiry-claim', 1);
        DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->update(['claim_expires_at' => now()->subSecond()]);
        self::assertTrue($repo->claim($copy['copy_id'], 1, 'reclaimed'));
        self::assertSame('IN_FLIGHT', DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('state'));
        self::assertSame('IN_FLIGHT', DB::table('kernel_current_kernel_dispatches')->where('copy_id', $copy['copy_id'])->value('state'));
    }

    public function test_concurrent_same_event_router_replay_returns_one_durable_decision(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'same-event-ready');
        $results = $this->forkRace(
            fn (): array => (new KernelCurrentKernelReceivedRouter())->decide('same-current-event', $this->blueprintId),
        );
        self::assertCount(
            2,
            array_filter($results, static fn (array $result): bool => isset($result['value'])),
            json_encode($results, JSON_THROW_ON_ERROR),
        );
        self::assertSame($results[0]['value'], $results[1]['value']);
        self::assertSame(KernelCurrentKernelReceivedRouter::QUARANTINE, $results[0]['value']['direction']);
        self::assertSame(1, DB::table('kernel_current_kernel_dispatches')
            ->where('event_id', 'same-current-event')->count());
    }

    public function test_late_transaction_failure_rolls_back_intent_and_claim_state(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'rollback-ready');
        $claim = (new KernelQuarantineWorkCopyRepository())->claimOldestReady('rollback-claim');
        DB::statement("CREATE FUNCTION quarantine_reject_return_owner() RETURNS trigger
            LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.reason_code = 'RETURN_OWNER' THEN
                    RAISE EXCEPTION 'late owner boundary failure';
                END IF;
                RETURN NEW;
            END;
            \$\$");
        DB::statement("CREATE TRIGGER quarantine_reject_return_owner
            BEFORE INSERT ON kernel_quarantine_transition_history
            FOR EACH ROW EXECUTE FUNCTION quarantine_reject_return_owner()");
        try {
            $this->service()->returnToOwner(
                $copy['copy_id'],
                1,
                (string) $claim->claim_token,
                'PHASE1',
                'RESUME_PHASE1',
                'rollback-intent',
            );
            self::fail('The late transition failure must abort the owner return.');
        } catch (\Throwable) {
            self::assertTrue(true);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS quarantine_reject_return_owner ON kernel_quarantine_transition_history');
            DB::statement('DROP FUNCTION IF EXISTS quarantine_reject_return_owner()');
        }
        self::assertSame(0, DB::table('kernel_quarantine_resume_intents')->where('idempotency_key', 'rollback-intent')->count());
        $current = DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->first();
        self::assertSame('IN_FLIGHT', $current->state);
        self::assertSame('rollback-claim', $current->claim_token);
    }

    public function test_correction_does_not_mutate_another_slot(): void
    {
        $copy = $this->copy();
        $before = DB::table('kernel_quarantine_work_copy_slots')->where('copy_id', $copy['copy_id'])
            ->where('cognitive_type', 'QCM_REASONING')->first();
        $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $this->source('QCM_RECOGNITION'), 1);
        $after = DB::table('kernel_quarantine_work_copy_slots')->where('copy_id', $copy['copy_id'])
            ->where('cognitive_type', 'QCM_REASONING')->first();
        self::assertSame((array) $before, (array) $after);
    }

    public function test_corrections_never_write_canonical_publication(): void
    {
        $copy = $this->copy();
        $before = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $this->blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')->value('source');
        $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $this->source('QCM_RECOGNITION'), 1);
        self::assertSame($before, DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $this->blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')->value('source'));
    }

    public function test_owner_return_exposes_references_only_and_keeps_in_flight_fence(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'owner-event');
        $decision = (new KernelCurrentKernelReceivedRouter())->decide('owner-receipt', $this->blueprintId);
        $return = $this->service()->returnToOwner($copy['copy_id'], 1, $decision['claim_token'], 'PHASE1', 'RESUME_PHASE1', 'owner-intent');
        self::assertSame(['intent_id','blueprint_id','copy_id','copy_version','owner_phase','operation'], array_keys($return));
        self::assertSame('IN_FLIGHT', DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('state'));
    }

    public function test_no_copy_version_history_table_exists(): void
    {
        self::assertFalse(Schema::hasTable('kernel_quarantine_copy_versions'));
    }

    public function test_invalid_owner_phase_and_operation_are_rejected(): void
    {
        $copy = $this->copy();
        $this->expectException(LogicException::class);
        $this->service()->requestRegeneration($copy['copy_id'], 1, 'READYBANK', 'PUBLISH', null, null, 'closed-1');
    }

    public function test_quarantine_corrections_never_write_pass_validation(): void
    {
        $copy = $this->copy();
        $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $this->source('QCM_RECOGNITION'), 1);
        self::assertNotSame('PASS', DB::table('kernel_quarantine_work_copy_slots')->where('copy_id', $copy['copy_id'])
            ->where('cognitive_type', 'QCM_RECOGNITION')->value('validation_status'));
    }

    public function test_ready_dispatch_version_is_updated_only_for_current_copy(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'version-event');
        $this->service()->prepareSourceCorrection($copy['copy_id'], 'QCM_RECOGNITION', $this->source('QCM_RECOGNITION'), 1);
        self::assertSame(1, DB::table('kernel_current_kernel_dispatches')->where('copy_id', $copy['copy_id'])->value('copy_version'));
    }

    public function test_transition_history_is_immutable_and_only_audit_not_copy_versions(): void
    {
        $copy = $this->copy();
        self::assertGreaterThan(0, DB::table('kernel_quarantine_transition_history')->where('copy_id', $copy['copy_id'])->count());
        $this->expectException(\Throwable::class);
        DB::table('kernel_quarantine_transition_history')->where('copy_id', $copy['copy_id'])->delete();
    }

    public function test_source_revision_is_fenced_by_claim_token_after_claim(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'fence-event');
        $claim = (new KernelQuarantineWorkCopyRepository())->claimOldestReady('fence-claim');
        $source = $this->source('QCM_RECOGNITION');
        $source['question'] = 'Claim-authorized source correction';
        $result = $this->service()->prepareSourceCorrection(
            $copy['copy_id'],
            'QCM_RECOGNITION',
            $source,
            1,
            0,
            null,
            (string) $claim->claim_token,
        );
        self::assertSame(2, $result['copy_version']);
        self::assertSame('IN_FLIGHT', DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('state'));
        foreach ($this->slot($copy['copy_id'])->translations as $translation) {
            self::assertSame('STALE', $translation['validation_status']);
        }
    }

    public function test_post_claim_translation_wrapper_changes_only_its_authorized_target(): void
    {
        $copy = $this->copy();
        $before = $this->slot($copy['copy_id'])->translations;
        $this->service()->enqueue($copy['copy_id'], 1, 'translation-claim-ready');
        $claim = (new KernelQuarantineWorkCopyRepository())->claimOldestReady('translation-claim');
        $translation = $before['fr'];
        $translation['question'] = 'Correction française après claim';
        $result = $this->service()->prepareTranslationCorrection(
            $copy['copy_id'],
            'QCM_RECOGNITION',
            'fr',
            $translation,
            1,
            0,
            null,
            (string) $claim->claim_token,
        );

        $after = $this->slot($copy['copy_id'])->translations;
        self::assertSame(2, $result['copy_version']);
        self::assertSame($before['fr']['translation_revision'] + 1, $after['fr']['translation_revision']);
        self::assertSame(array_diff_key($before, ['fr' => true]), array_diff_key($after, ['fr' => true]));
        self::assertSame('IN_FLIGHT', DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copy['copy_id'])->value('state'));
    }

    public function test_post_claim_version_advance_rebases_all_active_yellow_indices(): void
    {
        $copy = $this->copy();
        $first = $this->source('QCM_RECOGNITION');
        $first['question'] = 'First yellow slot';
        $this->service()->prepareSourceCorrection(
            $copy['copy_id'],
            'QCM_RECOGNITION',
            $first,
            1,
            0,
        );
        $second = $this->source('QCM_REASONING');
        $second['question'] = 'Second yellow slot';
        $this->service()->prepareSourceCorrection(
            $copy['copy_id'],
            'QCM_REASONING',
            $second,
            1,
            0,
        );
        $this->service()->enqueue($copy['copy_id'], 1, 'rebase-ready');
        $claim = (new KernelQuarantineWorkCopyRepository())->claimOldestReady('rebase-claim');

        $first['question'] = 'First yellow slot after claim';
        $this->service()->prepareSourceCorrection(
            $copy['copy_id'],
            'QCM_RECOGNITION',
            $first,
            1,
            1,
            null,
            (string) $claim->claim_token,
        );

        $indices = DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', $copy['copy_id'])
            ->where('state', 'ACTIVE')
            ->orderBy('cognitive_type')
            ->get();
        self::assertCount(2, $indices);
        self::assertSame([2, 2], $indices->pluck('copy_version')->map(static fn ($value): int => (int) $value)->all());
        self::assertSame(1, (int) $indices->firstWhere('cognitive_type', 'QCM_REASONING')->manual_revision);
    }

    public function test_claim_and_dispatch_references_are_persisted(): void
    {
        $copy = $this->copy();
        $this->service()->enqueue($copy['copy_id'], 1, 'persist-dispatch');
        $claimed = (new KernelQuarantineWorkCopyRepository())->claimOldestReady('persist-claim');
        self::assertSame($claimed->claim_token, DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('claim_token'));
        self::assertSame('IN_FLIGHT', DB::table('kernel_current_kernel_dispatches')->where('copy_id', $copy['copy_id'])->value('state'));
    }

    public function test_child_results_have_exact_pid_value_or_exception_and_zero_exit(): void
    {
        $results = $this->forkRace(fn (): bool => true);
        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertIsInt($result['pid']);
            self::assertArrayNotHasKey('exception', $result);
        }
    }

    private function service(): KernelQuarantineAdminService
    {
        return new KernelQuarantineAdminService();
    }

    private function copy(string $key = 'copy-' . 'default'): array
    {
        return $this->service()->createCopyFromRequest($this->blueprintId, 'VALIDATION_PHASE1', $key, 'SUSPICION', ['finding' => 'MEANING_DRIFT']);
    }

    private function slot(string $copyId): object
    {
        $row = DB::table('kernel_quarantine_work_copy_slots')->where('copy_id', $copyId)
            ->where('cognitive_type', 'QCM_RECOGNITION')->first();
        $row->translations = json_decode((string) $row->translations, true);
        return $row;
    }

    private function source(string $type): array
    {
        $qcm = str_starts_with($type, 'QCM_');
        return [
            'schema_version' => 'phase1.source.v1', 'source_language' => 'en',
            'cognitive_type' => $type, 'question' => 'Which color?',
            'choices' => $qcm ? ['a' => 'Blue', 'b' => 'Red', 'c' => 'Green', 'd' => 'Yellow'] : ['a' => 'True', 'b' => 'False'],
            'correct_answer_key' => str_ends_with($type, '_FALSE') ? 'b' : 'a',
            'sv' => 'The answer is stable.', 'creation_evidence' => ['provider' => 'fixture'],
        ];
    }

    private function translations(string $type): array
    {
        $result = [];
        $source = $this->source($type);
        $sourceRevision = hash('sha256', json_encode([
            'question' => $source['question'],
            'choices' => $source['choices'],
            'correct_answer_key' => $source['correct_answer_key'],
            'sv' => $source['sv'],
        ], JSON_THROW_ON_ERROR));
        foreach (['fr','es','de','it','pt','ru','zh','ar','el'] as $language) {
            $value = $this->source($type);
            unset($value['source_language'], $value['cognitive_type']);
            $value['schema_version'] = 'phase2.translation.v1';
            $value['translation_language'] = $language;
            $value['translation_revision'] = 1;
            $value['source_revision'] = $sourceRevision;
            $value['validation_status'] = 'VALIDATED';
            $value['validation_findings'] = [];
            $result[$language] = $value;
        }
        return $result;
    }

    private function forkRace(callable $operation): array
    {
        $directory = sys_get_temp_dir() . '/quarantine-test-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $pids = [];
        for ($index = 0; $index < 2; $index++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge('pgsql');
                    $value = $operation();
                    file_put_contents($directory . '/' . $index, json_encode(['pid' => getmypid(), 'value' => $value], JSON_THROW_ON_ERROR));
                    exit(0);
                } catch (\Throwable $exception) {
                    file_put_contents($directory . '/' . $index, json_encode(['pid' => getmypid(), 'exception' => $exception::class . ': ' . $exception->getMessage()], JSON_THROW_ON_ERROR));
                    exit(1);
                }
            }
            $pids[] = $pid;
        }
        $exitCodes = [];
        foreach ($pids as $index => $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            $exitCodes[$index] = pcntl_wexitstatus($status);
        }
        $results = [];
        foreach ([0, 1] as $index) {
            self::assertFileExists($directory . '/' . $index);
            $result = json_decode((string) file_get_contents($directory . '/' . $index), true, 512, JSON_THROW_ON_ERROR)
                + ['exit_code' => $exitCodes[$index]];
            self::assertIsInt($result['pid']);
            if (array_key_exists('exception', $result)) {
                self::assertArrayNotHasKey('value', $result);
                self::assertSame(1, $result['exit_code']);
            } else {
                self::assertArrayHasKey('value', $result);
                self::assertSame(0, $result['exit_code']);
            }
            $results[] = $result;
            unlink($directory . '/' . $index);
        }
        rmdir($directory);
        return $results;
    }
}