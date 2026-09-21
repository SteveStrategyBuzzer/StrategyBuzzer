<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Phase1\Phase1ExecutionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * ValidationPhase2 owns only the validation tables.  In particular, it never
 * updates kernel_phase2_translation_units or the Phase1 slot.
 */
final class ValidationPhase2Repository
{
    public const PRECONDITION_BLOCKED = 'PRECONDITION_BLOCKED';
    public const CLAIMED = 'CLAIMED';
    public const STALE_RESULT = 'STALE_RESULT';
    public const NO_OP = 'NO_OP';
    public const RETRYABLE_TECHNICAL_FAILURE = 'RETRYABLE_TECHNICAL_FAILURE';
    public const NON_RETRYABLE_TECHNICAL_FAILURE = 'NON_RETRYABLE_TECHNICAL_FAILURE';
    public const IDEMPOTENCY_VIOLATION = 'IDEMPOTENCY_VIOLATION';
    private const CLAIM_SECONDS = 300;

    /** @return array{source:array,target:array,unit:object,run:object,source_revision:string}|null */
    public function target(string $blueprintId, string $type, string $language): ?array
    {
        if (!in_array($type, ValidationPhase2Rules::TYPES, true)
            || !in_array($language, ValidationPhase2Rules::LANGUAGES, true)) return null;
        $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $blueprintId)
            ->where('cognitive_type', $type)->first();
        $blueprint = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->first();
        if (!$slot || !$blueprint || !$this->currentBlueprint($blueprint, $blueprintId)
            || $slot->creation_status !== 'CREATED' || $slot->validation_status !== 'PASS') return null;
        $identity = $this->identityRevision($blueprint);
        $phase1 = DB::table('kernel_phase1_executions')->where('blueprint_id', $blueprintId)
            ->where('identity_revision', $identity)
            ->where('state', 'COMPLETED')->first();
        if (!$phase1 || !$this->phase1Terminal($phase1)) return null;
        $source = $this->json($slot->source);
        if (($source['source_language'] ?? null) !== 'en'
            || ($source['cognitive_type'] ?? null) !== $type) return null;
        $sourceRevision = $this->sourceRevision($source);
        $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', $blueprintId)
            ->where('cognitive_type', $type)->where('language_code', $language)
            ->where('source_revision', $sourceRevision)->first();
        if (!$unit || $unit->state !== 'CREATED' || $unit->creation_status !== 'CREATED'
            || !$unit->translation_revision || !$unit->translation) return null;
        $target = $this->json($unit->translation);
        if (!$this->completeShape($type, $source, true) || !$this->completeShape($type, $target, false, $language)
            || ($target['correct_answer_key'] ?? null) !== ($source['correct_answer_key'] ?? null)) return null;
        return ['source' => $source, 'target' => $target, 'unit' => $unit, 'run' => $blueprint, 'source_revision' => $sourceRevision];
    }

    /** Primary admissibility predicate: one CognitiveSlot, one PostgreSQL snapshot. */
    public function isSlotAdmissible(string $blueprintId, string $type): bool
    {
        return DB::transaction(function () use ($blueprintId, $type): bool {
            $blueprint = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $blueprintId)
                ->where('cognitive_type', $type)->lockForUpdate()->first();
            if (!$blueprint || !$this->currentBlueprint($blueprint, $blueprintId)
                || !$slot || $slot->creation_status !== 'CREATED' || $slot->validation_status !== 'PASS') return false;
            $phase1 = DB::table('kernel_phase1_executions')->where('blueprint_id', $blueprintId)
                ->where('identity_revision', $this->identityRevision($blueprint))->where('state', 'COMPLETED')->first();
            if (!$phase1 || !$this->phase1Terminal($phase1)) return false;
            $source = $this->json($slot->source);
            $revision = $this->sourceRevision($source);
            if (!$this->completeShape($type, $source, true)) return false;
            foreach (ValidationPhase2Rules::LANGUAGES as $language) {
                $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', $blueprintId)
                    ->where('cognitive_type', $type)->where('language_code', $language)->where('source_revision', $revision)
                    ->lockForUpdate()->first();
                if (!$unit || $unit->state !== 'CREATED' || $unit->creation_status !== 'CREATED'
                    || $unit->validation_status !== 'NOT_VALIDATED' || !$unit->translation_revision || !$unit->translation) return false;
                $target = $this->json($unit->translation);
                if (!$this->completeShape($type, $target, false, $language) || ($target['correct_answer_key'] ?? null) !== ($source['correct_answer_key'] ?? null)) return false;
                $run = DB::table('kernel_validation_phase2_runs')->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                    ->where('language_code', $language)->where('source_revision', $revision)->where('translation_revision', $unit->translation_revision)
                    ->lockForUpdate()->first();
                // Historical attempts do not affect a current PASS.
                if (!$run || $run->status !== 'PASS' || $run->claim_token !== null || $run->next_attempt_at !== null) return false;
                if (DB::table('kernel_validation_phase2_findings')->where('validation_run_id', $run->validation_run_id)->exists()) return false;
            }
            return true;
        });
    }

    /** Blueprint aggregation is deliberately a separate convenience predicate. */
    public function isAdmissible(string $blueprintId): bool
    {
        foreach (ValidationPhase2Rules::TYPES as $type) {
            if (!$this->isSlotAdmissible($blueprintId, $type)) return false;
        }
        return true;
    }

    /** @return array{outcome:string,request:ValidationPhase2Request|null,run:object|null} */
    public function claim(string $blueprintId, string $type, string $language): array
    {
        return DB::transaction(function () use ($blueprintId, $type, $language): array {
            // Global lock order: blueprint -> slot -> translation unit -> run -> attempt.
            $initial = $this->target($blueprintId, $type, $language);
            if (!$initial) return $this->result(self::PRECONDITION_BLOCKED);
            // The canonical blueprint row is the serialization point before a
            // validation run exists (and prevents two first-run inserts).
            $blueprintLock = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->lockForUpdate()->first();
            if (!$blueprintLock) return $this->result(self::PRECONDITION_BLOCKED);
            $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $blueprintId)
                ->where('cognitive_type', $type)->lockForUpdate()->first();
            $unit = DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $initial['source_revision'])
                ->lockForUpdate()->first();
            $run = DB::table('kernel_validation_phase2_runs')->where('blueprint_id', $blueprintId)
                ->where('cognitive_type', $type)->where('language_code', $language)
                ->where('source_revision', $initial['source_revision'])
                ->where('translation_revision', $initial['unit']->translation_revision)->lockForUpdate()->first();
            if (!$slot || !$this->currentBlueprint($blueprintLock, $blueprintId)
                || !$unit || $unit->state !== 'CREATED' || $unit->creation_status !== 'CREATED'
                || $slot->creation_status !== 'CREATED' || $slot->validation_status !== 'PASS'
                || ($this->json($slot->source)['source_language'] ?? null) !== 'en'
                || ($this->json($slot->source)['cognitive_type'] ?? null) !== $type
                || $this->sourceRevision($this->json($slot->source)) !== $initial['source_revision']
                || !$unit->translation_revision || !$unit->translation) {
                return $this->result(self::PRECONDITION_BLOCKED);
            }
            $phase1 = DB::table('kernel_phase1_executions')->where('blueprint_id', $blueprintId)
                ->where('identity_revision', $this->identityRevision($blueprintLock))
                ->where('state', 'COMPLETED')->first();
            if (!$phase1 || !$this->phase1Terminal($phase1)) return $this->result(self::PRECONDITION_BLOCKED, $run);
            if ((!$run || !in_array($run->status, ['PASS', 'SUSPICION'], true))
                && $unit->validation_status !== 'NOT_VALIDATED') {
                return $this->result(self::PRECONDITION_BLOCKED, $run);
            }
            $data = ['source' => $this->json($slot->source), 'target' => $this->json($unit->translation),
                'unit' => $unit, 'run' => $initial['run'], 'source_revision' => $initial['source_revision']];
            if (!$this->completeShape($type, $data['source'], true)
                || !$this->completeShape($type, $data['target'], false, $language)
                || $data['target']['correct_answer_key'] !== $data['source']['correct_answer_key']) return $this->result(self::PRECONDITION_BLOCKED, $run);
            if ($run && in_array($run->status, ['PASS', 'SUSPICION'], true)) return $this->result(self::NO_OP, $run);
            if ($run && $run->status === 'PERMANENT_FAILURE') return $this->result(self::PRECONDITION_BLOCKED, $run);
            if ($run && $run->status === 'IN_PROGRESS' && $run->claim_expires_at && now()->lt($run->claim_expires_at)) {
                return $this->result(self::NO_OP, $run);
            }
            if ($run && $run->status === 'RETRYABLE_FAILURE'
                && $run->next_attempt_at && now()->lt($run->next_attempt_at)) return $this->result(self::NO_OP, $run);
            if ($run && $run->status === 'IN_PROGRESS' && $run->claim_expires_at && now()->gte($run->claim_expires_at)) {
                $expired = DB::table('kernel_validation_phase2_attempts')->where('attempt_id', $run->current_attempt_id)->lockForUpdate()->first();
                if (!$expired || $expired->outcome !== 'OPEN') return $this->result(self::STALE_RESULT, $run);
                DB::table('kernel_validation_phase2_attempts')->where('attempt_id', $expired->attempt_id)->update([
                    'outcome' => 'RETRYABLE_TECHNICAL_FAILURE', 'finished_at' => now(), 'updated_at' => now(),
                ]);
                if ((int) $run->attempt_number >= 4) {
                    DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->update([
                        'status' => 'PERMANENT_FAILURE', 'claim_token' => null, 'claim_expires_at' => null,
                        'next_attempt_at' => null, 'finished_at' => now(), 'updated_at' => now(),
                    ]);
                    return $this->result(self::PRECONDITION_BLOCKED, $run);
                }
                $delay = match ((int) $run->attempt_number) { 1 => 60, 2 => 300, default => 900 };
                DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->update([
                    'status' => 'RETRYABLE_FAILURE', 'next_attempt_at' => now()->addSeconds($delay),
                    'claim_token' => null, 'claim_expires_at' => null, 'finished_at' => now(), 'updated_at' => now(),
                ]);
                $run = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->first();
                return $this->result(self::NO_OP, $run);
            }
            $cycle = (int) ($run->retry_cycle ?? 0);
            $expiredClaim = $run && $run->status === 'IN_PROGRESS' && $run->claim_expires_at && now()->gte($run->claim_expires_at);
            $attemptNumber = $run && ($run->status === 'RETRYABLE_FAILURE' || $expiredClaim) ? ((int) $run->attempt_number + 1) : 1;
            if ($attemptNumber > 4) return $this->result(self::PRECONDITION_BLOCKED, $run);
            $attemptId = (string) Str::uuid();
            $claim = (string) Str::uuid();
            $requestReference = (string) Str::uuid();
            $idempotencyKey = (string) Str::uuid();
            $envelope = [
                'attempt_id' => $attemptId, 'claim_token' => $claim, 'retry_cycle' => $cycle,
                'attempt_number' => $attemptNumber, 'source_revision' => $data['source_revision'],
                'translation_revision' => (int) $unit->translation_revision, 'yellow_revision' => $unit->yellow_revision,
            ];
            $values = [
                'blueprint_id' => $blueprintId, 'cognitive_type' => $type, 'language_code' => $language,
                'source_revision' => $data['source_revision'], 'translation_revision' => $unit->translation_revision,
                'retry_cycle' => $cycle, 'attempt_number' => $attemptNumber, 'yellow_revision' => $unit->yellow_revision,
                'status' => 'IN_PROGRESS', 'claim_token' => $claim, 'claim_expires_at' => now()->addSeconds(self::CLAIM_SECONDS),
                'validation_request_reference' => $requestReference, 'external_idempotency_key' => $idempotencyKey,
                'current_attempt_id' => $attemptId, 'next_attempt_at' => null, 'finished_at' => null,
                'technical_reason_code' => null, 'updated_at' => now(),
            ];
            if ($run) DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->update($values);
            else {
                $values['validation_run_id'] = (string) Str::uuid();
                $values['created_at'] = now();
                DB::table('kernel_validation_phase2_runs')->insert($values);
            }
            $run = DB::table('kernel_validation_phase2_runs')->where('blueprint_id', $blueprintId)
                ->where('cognitive_type', $type)->where('language_code', $language)
                ->where('source_revision', $data['source_revision'])->where('translation_revision', $unit->translation_revision)->first();
            DB::table('kernel_validation_phase2_attempts')->insert([
                'attempt_id' => $attemptId, 'validation_run_id' => $run->validation_run_id,
                'retry_cycle' => $cycle, 'attempt_number' => $attemptNumber, 'claim_token' => $claim,
                'validation_request_reference' => $requestReference, 'external_idempotency_key' => $idempotencyKey,
                'source_payload_hash' => hash('sha256', json_encode($data['source'], JSON_THROW_ON_ERROR)),
                'target_payload_hash' => hash('sha256', json_encode($data['target'], JSON_THROW_ON_ERROR)),
                'expected_translation_revision' => $unit->translation_revision, 'yellow_revision' => $unit->yellow_revision,
                'internal_envelope' => json_encode($envelope, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $context = array_filter(['domain' => $data['run']->domain_code ?? null, 'subdomain' => $data['run']->subdomain_active ?? null,
                'subject' => $data['run']->subject_active ?? null, 'dominant_idea' => $data['run']->dominant_idea_active ?? null]);
            $request = new ValidationPhase2Request($requestReference, $idempotencyKey, 'en', $language, $type,
                $this->externalPayload($data['source'], $type), $this->externalPayload($data['target'], $type),
                $context, ValidationPhase2Rules::registry(), ValidationPhase2Rules::schema($type));
            return ['outcome' => 'CLAIMED', 'request' => $request, 'run' => $run];
        });
    }

    public function apply(object $run, ValidationPhase2Response $response): string
    {
        return DB::transaction(function () use ($run, $response): string {
            $identity = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->first();
            if (!$identity) return self::STALE_RESULT;
            $blueprint = DB::table('kernel_blueprint_runs')->where('blueprint_id', $identity->blueprint_id)->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $identity->blueprint_id)
                ->where('cognitive_type', $identity->cognitive_type)->lockForUpdate()->first();
            $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', $identity->blueprint_id)
                ->where('cognitive_type', $identity->cognitive_type)->where('language_code', $identity->language_code)
                ->where('source_revision', $identity->source_revision)->lockForUpdate()->first();
            $current = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->lockForUpdate()->first();
            if (!$blueprint || !$current) return self::STALE_RESULT;
            $phase1 = DB::table('kernel_phase1_executions')->where('blueprint_id', $identity->blueprint_id)
                ->where('identity_revision', $this->identityRevision($blueprint))->where('state', 'COMPLETED')->first();
            if (!$this->currentBlueprint($blueprint, $identity->blueprint_id)
                || !$slot || $slot->creation_status !== 'CREATED' || $slot->validation_status !== 'PASS'
                || !$unit || $unit->state !== 'CREATED' || $unit->creation_status !== 'CREATED'
                || ($this->json($slot->source)['source_language'] ?? null) !== 'en'
                || ($this->json($slot->source)['cognitive_type'] ?? null) !== $identity->cognitive_type
                || !$phase1 || !$this->phase1Terminal($phase1)) return self::STALE_RESULT;
            $attempt = DB::table('kernel_validation_phase2_attempts')
                ->where('validation_request_reference', $response->validationRequestReference)->lockForUpdate()->first();
            if (!$attempt || $attempt->validation_run_id !== $current->validation_run_id) return self::STALE_RESULT;
            $hash = hash('sha256', json_encode(['decision' => $response->decision, 'findings' => $response->findings], JSON_THROW_ON_ERROR));
            if ($response->externalValidationIdempotencyKey !== null
                && $response->externalValidationIdempotencyKey !== $attempt->external_idempotency_key) return self::STALE_RESULT;
            if (!$this->currentAttemptIdentity($current, $attempt, $slot, $unit)) return self::STALE_RESULT;
            if ($attempt->outcome !== 'OPEN' || $current->current_attempt_id !== $attempt->attempt_id) {
                if ($attempt->response_hash === null) return self::STALE_RESULT;
                return $attempt->response_hash === $hash ? self::NO_OP : self::IDEMPOTENCY_VIOLATION;
            }
            if (!$this->fenced($current, $attempt, $slot, $unit)) return self::STALE_RESULT;
            if (!in_array($response->decision, ['PASS', 'SUSPICION'], true)) return $this->invalid($current, $attempt);
            foreach ($response->findings as $finding) {
                if (!ValidationPhase2Rules::validateFinding($finding, $current->cognitive_type, $current->language_code, $current->source_revision, (int) $current->translation_revision)) {
                    return $this->invalid($current, $attempt);
                }
            }
            $hasBlocking = $response->findings !== [];
            if (($response->decision === 'PASS' && $hasBlocking) || ($response->decision === 'SUSPICION' && !$hasBlocking)) return $this->invalid($current, $attempt);
            foreach ($response->findings as $finding) {
                DB::table('kernel_validation_phase2_findings')->insert([
                    'finding_id' => (string) Str::uuid(), 'validation_run_id' => $current->validation_run_id,
                    'blueprint_id' => $current->blueprint_id, 'cognitive_type' => $current->cognitive_type,
                    'language_code' => $current->language_code, 'source_revision' => $current->source_revision,
                    'translation_revision' => $current->translation_revision, 'field_path' => $finding['field_path'],
                    'rule_code' => $finding['rule_code'], 'severity' => $finding['severity'],
                    'evidence' => json_encode($finding['evidence'], JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);
            }
            DB::table('kernel_validation_phase2_attempts')->where('attempt_id', $attempt->attempt_id)->update([
                'outcome' => $response->decision === 'PASS' ? 'APPLIED' : 'APPLIED',
                'response_hash' => $hash, 'validator_request_id' => $response->validatorRequestId,
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $current->validation_run_id)->update([
                'status' => $response->decision, 'validator_request_id' => $response->validatorRequestId,
                'response_hash' => $hash, 'claim_token' => null, 'claim_expires_at' => null,
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            return $response->decision;
        });
    }

    public function fail(object $run, ValidationPhase2TechnicalFailure $failure): string
    {
        return DB::transaction(function () use ($run, $failure): string {
            $identity = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->first();
            if (!$identity) return self::STALE_RESULT;
            $blueprint = DB::table('kernel_blueprint_runs')->where('blueprint_id', $identity->blueprint_id)->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $identity->blueprint_id)
                ->where('cognitive_type', $identity->cognitive_type)->lockForUpdate()->first();
            $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', $identity->blueprint_id)
                ->where('cognitive_type', $identity->cognitive_type)->where('language_code', $identity->language_code)
                ->where('source_revision', $identity->source_revision)->lockForUpdate()->first();
            $current = DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->lockForUpdate()->first();
            if (!$blueprint || !$current || $current->status !== 'IN_PROGRESS') return self::STALE_RESULT;
            $phase1 = DB::table('kernel_phase1_executions')->where('blueprint_id', $identity->blueprint_id)
                ->where('identity_revision', $this->identityRevision($blueprint))->where('state', 'COMPLETED')->first();
            if (!$this->currentBlueprint($blueprint, $identity->blueprint_id)
                || !$slot || $slot->creation_status !== 'CREATED' || $slot->validation_status !== 'PASS'
                || !$unit || $unit->state !== 'CREATED' || $unit->creation_status !== 'CREATED'
                || ($this->json($slot->source)['source_language'] ?? null) !== 'en'
                || ($this->json($slot->source)['cognitive_type'] ?? null) !== $identity->cognitive_type
                || !$phase1 || !$this->phase1Terminal($phase1)) return self::STALE_RESULT;
            $attempt = DB::table('kernel_validation_phase2_attempts')->where('attempt_id', $current->current_attempt_id)->lockForUpdate()->first();
            if (!$attempt || !$this->fenced($current, $attempt, $slot, $unit)
                || $failure->validationRequestReference !== $attempt->validation_request_reference
                || $failure->externalValidationIdempotencyKey !== $attempt->external_idempotency_key) return self::STALE_RESULT;
            $terminal = !$failure->retryable || (int) $attempt->attempt_number >= 4;
            $delay = match ((int) $attempt->attempt_number) { 1 => 60, 2 => 300, 3 => 900, default => 0 };
            $delay = max($delay, $failure->retryAfterSeconds ?? 0);
            $outcome = $terminal ? 'NON_RETRYABLE_TECHNICAL_FAILURE' : 'RETRYABLE_TECHNICAL_FAILURE';
            DB::table('kernel_validation_phase2_attempts')->where('attempt_id', $attempt->attempt_id)->update([
                'outcome' => $outcome, 'finished_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $current->validation_run_id)->update([
                'status' => $terminal ? 'PERMANENT_FAILURE' : 'RETRYABLE_FAILURE',
                'technical_reason_code' => $failure->reasonCode,
                'next_attempt_at' => $terminal ? null : now()->addSeconds($delay),
                'claim_token' => null, 'claim_expires_at' => null, 'finished_at' => now(), 'updated_at' => now(),
            ]);
            return $terminal ? self::NON_RETRYABLE_TECHNICAL_FAILURE : self::RETRYABLE_TECHNICAL_FAILURE;
        });
    }

    public function authorizeRetryCycle(string $blueprintId, string $type, string $language, string $sourceRevision, int $translationRevision, ?int $yellowRevision, string $resolutionEventId, string $authorizedBy, string $reasonCode): bool
    {
        if (!in_array($authorizedBy, ['ADMIN', 'SYSTEM_RECOVERY'], true)) throw new LogicException('Unauthorized resolution actor.');
        return DB::transaction(function () use ($blueprintId, $type, $language, $sourceRevision, $translationRevision, $yellowRevision, $resolutionEventId, $authorizedBy, $reasonCode): bool {
            $blueprint = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $blueprintId)
                ->where('cognitive_type', $type)->lockForUpdate()->first();
            $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', $blueprintId)
                ->where('cognitive_type', $type)->where('language_code', $language)
                ->where('source_revision', $sourceRevision)->lockForUpdate()->first();
            if (!$blueprint || !$slot || !$unit || $this->sourceRevision($this->json($slot->source)) !== $sourceRevision
                || (int) $unit->translation_revision !== $translationRevision || $unit->yellow_revision != $yellowRevision) return false;
            $run = DB::table('kernel_validation_phase2_runs')->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $sourceRevision)
                ->where('translation_revision', $translationRevision)->where('yellow_revision', $yellowRevision)->lockForUpdate()->first();
            if (!$run || $run->status !== 'PERMANENT_FAILURE') return false;
            $event = DB::selectOne(
                'INSERT INTO kernel_validation_phase2_resolution_events '
                . '(resolution_event_id, blueprint_id, cognitive_type, language_code, source_revision, translation_revision, '
                . 'yellow_revision, retry_cycle, authorized_by, reason_code, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
                . 'ON CONFLICT (resolution_event_id) DO NOTHING RETURNING resolution_event_id',
                [$resolutionEventId, $blueprintId, $type, $language, $sourceRevision, $translationRevision,
                    $yellowRevision, ((int) $run->retry_cycle) + 1, $authorizedBy, $reasonCode, now()],
            );
            if (!$event) return false;
            DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->update([
                'retry_cycle' => ((int) $run->retry_cycle) + 1, 'attempt_number' => 0, 'status' => 'NOT_VALIDATED',
                'current_attempt_id' => null, 'next_attempt_at' => null, 'technical_reason_code' => null,
                'updated_at' => now(),
            ]);
            return true;
        });
    }

    private function fenced(object $run, object $attempt, ?object $slot, ?object $unit): bool
    {
        if (!$this->currentAttemptIdentity($run, $attempt, $slot, $unit)
            || $run->claim_token !== $attempt->claim_token || !$run->claim_expires_at
            || now()->gte($run->claim_expires_at)) return false;
        return true;
    }

    private function currentAttemptIdentity(object $run, object $attempt, ?object $slot, ?object $unit): bool
    {
        if (!$slot || !$unit || $run->current_attempt_id !== $attempt->attempt_id
            || $run->retry_cycle !== $attempt->retry_cycle || $run->attempt_number !== $attempt->attempt_number
            || $unit->state !== 'CREATED' || $unit->creation_status !== 'CREATED'
            || (int) $unit->translation_revision !== (int) $run->translation_revision
            || (int) $attempt->expected_translation_revision !== (int) $run->translation_revision
            || $unit->yellow_revision != $run->yellow_revision || $attempt->yellow_revision != $run->yellow_revision
            || $this->sourceRevision($this->json($slot->source)) !== $run->source_revision) return false;
        return hash('sha256', json_encode($this->json($slot->source), JSON_THROW_ON_ERROR)) === $attempt->source_payload_hash
            && hash('sha256', json_encode($this->json($unit->translation), JSON_THROW_ON_ERROR)) === $attempt->target_payload_hash;
    }

    private function invalid(object $run, object $attempt): string
    {
        $terminal = (int) $attempt->attempt_number >= 4;
        DB::table('kernel_validation_phase2_attempts')->where('attempt_id', $attempt->attempt_id)->update([
            'outcome' => $terminal ? 'NON_RETRYABLE_TECHNICAL_FAILURE' : 'RETRYABLE_TECHNICAL_FAILURE',
            'finished_at' => now(), 'updated_at' => now(),
        ]);
        $delay = match ((int) $attempt->attempt_number) { 1 => 60, 2 => 300, 3 => 900, default => 0 };
        DB::table('kernel_validation_phase2_runs')->where('validation_run_id', $run->validation_run_id)->update([
            'status' => $terminal ? 'PERMANENT_FAILURE' : 'RETRYABLE_FAILURE', 'technical_reason_code' => 'INVALID_VALIDATION_RESPONSE',
            'next_attempt_at' => $terminal ? null : now()->addSeconds($delay), 'claim_token' => null, 'claim_expires_at' => null,
            'finished_at' => now(), 'updated_at' => now(),
        ]);
        return $terminal ? self::NON_RETRYABLE_TECHNICAL_FAILURE : self::RETRYABLE_TECHNICAL_FAILURE;
    }

    private function result(string $outcome, ?object $run = null): array { return ['outcome' => $outcome, 'request' => null, 'run' => $run]; }
    private function externalPayload(array $value, string $type): array
    {
        $choices = [];
        foreach ((array) ($value['choices'] ?? []) as $key => $choice) {
            $key = (string) (is_array($choice) ? ($choice['key'] ?? $key) : $key);
            $choices[$key] = is_array($choice) ? ($choice['text'] ?? null) : $choice;
        }
        ksort($choices);
        return [
            'question' => (string) ($value['question'] ?? ''),
            'choices' => $choices,
            'correct_answer_key' => (string) ($value['correct_answer_key'] ?? ''),
            'sv' => (string) ($value['sv'] ?? ''),
        ];
    }
    private function sourceRevision(array $source): string { return hash('sha256', json_encode(['question' => $source['question'] ?? null, 'choices' => $source['choices'] ?? null, 'correct_answer_key' => $source['correct_answer_key'] ?? null, 'sv' => $source['sv'] ?? null], JSON_THROW_ON_ERROR)); }
    private function json(mixed $value): array { return is_array($value) ? $value : (json_decode((string) $value, true) ?: []); }

    private function completeShape(string $type, array $payload, bool $source, ?string $language = null): bool
    {
        if (!is_string($payload['question'] ?? null) || trim($payload['question']) === '' || !is_string($payload['sv'] ?? null) || trim($payload['sv']) === '') return false;
        $choices = $this->choiceMap($payload['choices'] ?? null);
        $expected = str_starts_with($type, 'QCM_') ? ['a', 'b', 'c', 'd'] : ['a', 'b'];
        if (array_keys($choices) !== $expected || !in_array($payload['correct_answer_key'] ?? null, $expected, true)) return false;
        foreach ($choices as $text) if (!is_string($text) || trim($text) === '') return false;
        if (!$source && !str_starts_with($type, 'QCM_')) {
            $labels = \App\Services\QuestionBank\Phase2\Phase2TranslationRepository::TARGET_TRUE_FALSE_LABELS[$language] ?? null;
            if (!$labels || $choices['a'] !== $labels[0] || $choices['b'] !== $labels[1]) return false;
        }
        if ($source && ($payload['source_language'] ?? 'en') !== 'en') return false;
        if (str_ends_with($type, '_TRUE') && ($payload['correct_answer_key'] ?? null) !== 'a') return false;
        if (str_ends_with($type, '_FALSE') && ($payload['correct_answer_key'] ?? null) !== 'b') return false;
        if ($source && !str_starts_with($type, 'QCM_')
            && ($choices['a'] ?? null) !== 'TRUE' || $source && !str_starts_with($type, 'QCM_')
            && ($choices['b'] ?? null) !== 'FALSE') return false;
        return true;
    }
    private function choiceMap(mixed $choices): array
    {
        if (!is_array($choices)) return [];
        $out = [];
        foreach ($choices as $key => $choice) $out[(string) (is_array($choice) ? ($choice['key'] ?? $key) : $key)] = is_array($choice) ? ($choice['text'] ?? null) : $choice;
        ksort($out);
        return $out;
    }

    private function currentBlueprint(object $blueprint, string $blueprintId): bool
    {
        if ((string) ($blueprint->blueprint_id ?? '') !== $blueprintId) return false;
        $revision = $this->identityRevision($blueprint);
        if ($revision === '') return false;
        if (property_exists($blueprint, 'identity_revision')
            && (string) $blueprint->identity_revision !== '') {
            return hash_equals($revision, (string) $blueprint->identity_revision);
        }
        return true;
    }

    private function identityRevision(object $blueprint): string
    {
        $identity = [];
        foreach (['depth', 'domain_code', 'subdomain_active', 'subject_active', 'dominant_idea_active',
            'kernel_code_dd', 'kernel_code_do', 'kernel_code_sub', 'kernel_code_suj', 'kernel_code_ide',
            'kernel_code_vvvv', 'kernel_code'] as $column) {
            if (!isset($blueprint->{$column}) || trim((string) $blueprint->{$column}) === '') return '';
            $identity[$column] = (string) $blueprint->{$column};
        }
        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    private function phase1Terminal(?object $execution): bool
    {
        if (!$execution) return false;
        $result = $this->json($execution->result ?? []);
        return ($result['phase1_terminal'] ?? null) === Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED
            && ($result['creation_status'] ?? null) === 'CREATED';
    }
}