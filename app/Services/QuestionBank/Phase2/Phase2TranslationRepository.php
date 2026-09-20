<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase2;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Phase1\Phase1ExecutionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class Phase2TranslationRepository
{
    public const PRECONDITION_BLOCKED = 'PRECONDITION_BLOCKED';
    public const STALE_RESULT = 'STALE_RESULT';
    public const RETRYABLE_TECHNICAL_FAILURE = 'RETRYABLE_TECHNICAL_FAILURE';
    public const NON_RETRYABLE_TECHNICAL_FAILURE = 'NON_RETRYABLE_TECHNICAL_FAILURE';
    public const NO_OP = 'NO_OP';

    public const LANGUAGES = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];
    public const TARGET_TRUE_FALSE_LABELS = [
        'fr' => ['VRAI', 'FAUX'], 'es' => ['VERDADERO', 'FALSO'],
        'de' => ['WAHR', 'FALSCH'], 'it' => ['VERO', 'FALSO'],
        'pt' => ['VERDADEIRO', 'FALSO'], 'ru' => ['ИСТИНА', 'ЛОЖЬ'],
        'zh' => ['正确', '错误'], 'ar' => ['صحيح', 'خطأ'],
        'el' => ['ΑΛΗΘΕΣ', 'ΨΕΥΔΕΣ'],
    ];
    private const CLAIM_SECONDS = 300;

    public function __construct(
        private readonly Phase1ExecutionRepository $phase1 = new Phase1ExecutionRepository(),
    ) {}

    /**
     * Reloads all upstream state and creates no attempt while checking it.
     *
     * @return array{blueprint:object, slots:array<string,object>, blocked:array<string,string>, source_revision:array<string,string>}
     */
    public function preflight(string $blueprintId): array
    {
        $this->phase1->validationPrerequisites($blueprintId);
        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->first();
        if ($run === null) {
            throw new LogicException("Phase2 blueprint introuvable: {$blueprintId}.");
        }

        $rows = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $blueprintId)->orderBy('cognitive_type')->get()->keyBy('cognitive_type');
        $blocked = [];
        $revisions = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $slot = $rows->get($type);
            if ($slot === null || (string) $slot->creation_status !== 'CREATED'
                || (string) $slot->validation_status !== 'PASS') {
                $blocked[$type] = self::PRECONDITION_BLOCKED;
                continue;
            }
            $source = $this->json($slot->source);
            if (($source['source_language'] ?? null) !== 'en'
                || ($source['cognitive_type'] ?? null) !== $type
                || ! is_string($source['question'] ?? null)
                || ! is_array($source['choices'] ?? null)
                || ! is_string($source['correct_answer_key'] ?? null)
                || ! is_string($source['sv'] ?? null)
                || ! $this->validShape($type, $source, true)) {
                $blocked[$type] = self::PRECONDITION_BLOCKED;
                continue;
            }
            $revisions[$type] = $this->sourceRevision($source);
        }

        return [
            'blueprint' => $run,
            'slots' => $rows->all(),
            'blocked' => $blocked,
            'source_revision' => $revisions,
        ];
    }

    /** @return array{operation_id:string, request:Phase2ProviderRequest, envelope:array<string,mixed>}|array{outcome:string} */
    public function claim(
        string $blueprintId,
        string $type,
        string $language,
        string $sourceRevision,
        object $run,
        object $slot,
        bool $refresh = false,
    ): array {
        return DB::transaction(function () use ($blueprintId, $type, $language, $sourceRevision, $run, $slot, $refresh): array {
            $currentRun = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)->lockForUpdate()->first();
            $currentIdentityRevision = $this->identityRevision($currentRun);
            $currentExecution = DB::table('kernel_phase1_executions')
                ->where('blueprint_id', $blueprintId)
                ->where('identity_revision', $currentIdentityRevision)->lockForUpdate()->first();
            $currentSlot = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->lockForUpdate()->first();
            if ($currentRun === null || $currentExecution === null
                || !$this->isPhase1Terminal($currentExecution) || $currentSlot === null
                || !$this->validSource($type, $currentSlot)) {
                return ['outcome' => self::PRECONDITION_BLOCKED];
            }
            $currentSource = $this->json($currentSlot->source);
            if ($this->sourceRevision($currentSource) !== $sourceRevision) {
                return ['outcome' => self::STALE_RESULT];
            }
            $run = $currentRun;
            $slot = $currentSlot;
            $unit = DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $sourceRevision)
                ->lockForUpdate()->first();
            if ($unit === null) {
                DB::table('kernel_phase2_translation_units')->insert([
                    'blueprint_id' => $blueprintId, 'cognitive_type' => $type,
                    'language_code' => $language, 'source_revision' => $sourceRevision,
                    'state' => 'PENDING', 'creation_status' => 'PENDING', 'validation_status' => 'NOT_VALIDATED',
                    'source_payload_hash' => hash('sha256', (string) $slot->source),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $unit = DB::table('kernel_phase2_translation_units')
                    ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                    ->where('language_code', $language)->where('source_revision', $sourceRevision)
                    ->lockForUpdate()->first();
            }
            if (($unit->state === 'CREATED' && !$refresh) || $unit->state === 'PERMANENT_FAILURE'
                || ($unit->state === 'RETRYABLE_FAILURE'
                    && $unit->next_attempt_at !== null && now()->lt($unit->next_attempt_at))) {
                return ['outcome' => self::NO_OP];
            }
            if ($unit->state === 'IN_PROGRESS'
                && $unit->claim_expires_at !== null && now()->lt($unit->claim_expires_at)) {
                return ['outcome' => self::NO_OP];
            }

            $claim = (string) Str::uuid();
            $operation = (string) Str::uuid();
            $externalKey = (string) Str::uuid();
            $internal = [
                'operation_id' => $operation, 'blueprint_id' => $blueprintId,
                'source_revision' => $sourceRevision,
                'expected_translation_revision' => $unit->translation_revision,
                'retry_cycle' => (int) $unit->retry_cycle,
                'attempt_number' => ((int) $unit->attempt_number) + 1,
                'claim_token' => $claim, 'source_payload_hash' => $unit->source_payload_hash,
                'yellow_revision' => $unit->yellow_revision,
                'expected_correct_answer_key' => $this->json($slot->source)['correct_answer_key'],
                'expected_choice_keys' => $this->choiceKeys($this->json($slot->source)['choices']),
            ];
            DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $sourceRevision)
                ->update([
                    'state' => 'IN_PROGRESS', 'attempt_number' => $internal['attempt_number'],
                    'claim_token' => $claim, 'claimed_at' => now(),
                    'claim_expires_at' => now()->addSeconds(self::CLAIM_SECONDS), 'updated_at' => now(),
                ]);
            DB::table('kernel_phase2_translation_attempts')->insert([
                'operation_id' => $operation, 'blueprint_id' => $blueprintId,
                'cognitive_type' => $type, 'language_code' => $language, 'source_revision' => $sourceRevision,
                'expected_translation_revision' => $unit->translation_revision,
                'retry_cycle' => $internal['retry_cycle'], 'attempt_number' => $internal['attempt_number'],
                'claim_token' => $claim, 'provider_request_reference' => $operation,
                'external_idempotency_key' => $externalKey,
                'internal_envelope' => json_encode($internal, JSON_THROW_ON_ERROR),
                'outcome' => 'OPEN',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $source = $this->json($slot->source);
            $request = new Phase2ProviderRequest(
                $operation, $externalKey, 'en', $language, $type, (int) $run->depth,
                array_filter([
                    'domain' => $run->domain_code ?? null, 'subdomain' => $run->subdomain_active ?? null,
                    'subject' => $run->subject_active ?? null, 'dominant_idea' => $run->dominant_idea_active ?? null,
                ]),
                $this->providerSource($source, $type),
                $this->responseSchema($type),
            );
            return ['operation_id' => $operation, 'request' => $request, 'envelope' => $internal];
        });
    }

    /** Internal operation seam for a provider refresh of an accepted target. */
    public function openRefreshClaim(
        string $blueprintId,
        string $type,
        string $language,
        string $sourceRevision,
        object $run,
        object $slot,
    ): array {
        return $this->claim($blueprintId, $type, $language, $sourceRevision, $run, $slot, true);
    }

    /** True only when a response belongs to the currently fenced operation. */
    public function isCurrentProviderResponse(string $operationId, Phase2ProviderResponse $response): bool
    {
        $attempt = DB::table('kernel_phase2_translation_attempts')->where('operation_id', $operationId)->first();
        if ($attempt === null || $attempt->outcome !== 'OPEN'
            || $response->providerRequestReference !== (string) $attempt->provider_request_reference
            || $response->targetLanguage !== (string) $attempt->language_code) return false;
        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $attempt->blueprint_id)->first();
        $identity = $run === null ? '' : $this->identityRevision($run);
        $execution = DB::table('kernel_phase1_executions')->where('blueprint_id', $attempt->blueprint_id)
            ->where('identity_revision', $identity)->first();
        $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $attempt->blueprint_id)
            ->where('cognitive_type', $attempt->cognitive_type)->first();
        $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', $attempt->blueprint_id)
            ->where('cognitive_type', $attempt->cognitive_type)->where('language_code', $attempt->language_code)
            ->where('source_revision', $attempt->source_revision)->first();
        if (!$this->isPhase1Terminal($execution) || !$this->validSource((string) $attempt->cognitive_type, $slot)
            || $unit === null || $this->sourceRevision($this->json($slot->source)) !== (string) $attempt->source_revision
            || $unit->state !== 'IN_PROGRESS' || (string) $unit->claim_token !== (string) $attempt->claim_token
            || $unit->claim_expires_at === null || now()->gte($unit->claim_expires_at)
            || (int) $unit->retry_cycle !== (int) $attempt->retry_cycle
            || (int) $unit->attempt_number !== (int) $attempt->attempt_number
            || $unit->translation_revision !== $attempt->expected_translation_revision
            || $unit->yellow_revision !== (json_decode((string) $attempt->internal_envelope, true)['yellow_revision'] ?? null)) {
            return false;
        }
        return true;
    }

    public function apply(string $operationId, Phase2ProviderResponse $response): string
    {
        return DB::transaction(function () use ($operationId, $response): string {
            $attempt = DB::table('kernel_phase2_translation_attempts')->where('operation_id', $operationId)->first();
            if ($attempt === null) return self::STALE_RESULT;
            $responseHash = hash('sha256', json_encode($response->translation, JSON_THROW_ON_ERROR));
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $attempt->blueprint_id)->lockForUpdate()->first();
            $identityRevision = $run === null ? null : $this->identityRevision($run);
            $execution = DB::table('kernel_phase1_executions')
                ->where('blueprint_id', $attempt->blueprint_id)
                ->when($identityRevision !== null, fn ($q) => $q->where('identity_revision', $identityRevision))
                ->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $attempt->blueprint_id)
                ->where('cognitive_type', $attempt->cognitive_type)->lockForUpdate()->first();
            $unit = DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $attempt->blueprint_id)->where('cognitive_type', $attempt->cognitive_type)
                ->where('language_code', $attempt->language_code)->where('source_revision', $attempt->source_revision)
                ->lockForUpdate()->first();
            $attempt = DB::table('kernel_phase2_translation_attempts')
                ->where('operation_id', $operationId)->lockForUpdate()->first();
            $internal = json_decode((string) $attempt->internal_envelope, true, 512, JSON_THROW_ON_ERROR);
            $identityMatches = $response->providerRequestReference === (string) $attempt->provider_request_reference
                && $response->targetLanguage === (string) $attempt->language_code;
            if (!$identityMatches) return self::STALE_RESULT;
            if ($attempt->outcome !== 'OPEN') {
                return $attempt->response_hash === $responseHash ? self::NO_OP : self::STALE_RESULT;
            }
            if ($unit !== null && $unit->state === 'CREATED'
                && $unit->translation_hash !== null
                && hash_equals((string) $unit->translation_hash, $responseHash)) {
                return self::NO_OP;
            }
            $responseChoices = array_keys($response->translation['choices'] ?? []);
            sort($responseChoices);
            $expectedChoices = $internal['expected_choice_keys'] ?? [];
            sort($expectedChoices);
            $source = $slot === null ? null : $this->json($slot->source);
            $currentRevision = ($run !== null && $execution !== null && $slot !== null && $source !== null)
                ? $this->sourceRevision($source) : null;
            if ($run === null || $execution === null || !$this->isPhase1Terminal($execution)
                || $slot === null || !$this->validSource((string) $attempt->cognitive_type, $slot)
                || $currentRevision !== (string) $attempt->source_revision
                || $unit === null || $unit->state !== 'IN_PROGRESS'
                || (string) $unit->claim_token !== (string) $attempt->claim_token
                || ($unit->claim_expires_at !== null && now()->gte($unit->claim_expires_at))
                || (int) $unit->retry_cycle !== (int) $attempt->retry_cycle
                || (int) $unit->attempt_number !== (int) $attempt->attempt_number
                || (string) ($unit->source_payload_hash ?? '') !== hash('sha256', (string) $slot->source)
                || $unit->translation_revision !== $attempt->expected_translation_revision
                || $unit->yellow_revision !== ($internal['yellow_revision'] ?? null)
                || $response->providerRequestReference !== (string) $attempt->provider_request_reference
                || $response->targetLanguage !== (string) $attempt->language_code
                || ($response->translation['correct_answer_key'] ?? null)
                    !== ($internal['expected_correct_answer_key'] ?? null)
                || $responseChoices !== $expectedChoices
                || !$this->validShape((string) $attempt->cognitive_type, $response->translation, false, (string) $attempt->language_code)) {
                return self::STALE_RESULT;
            }
            $hash = $responseHash;
            if ($unit->translation_hash !== null && hash_equals((string) $unit->translation_hash, $hash)) {
                DB::table('kernel_phase2_translation_units')->where('blueprint_id', $attempt->blueprint_id)
                    ->where('cognitive_type', $attempt->cognitive_type)->where('language_code', $attempt->language_code)
                    ->where('source_revision', $attempt->source_revision)
                    ->update([
                        'state' => 'CREATED', 'creation_status' => 'CREATED',
                        'validation_status' => 'NOT_VALIDATED', 'claim_token' => null,
                        'claimed_at' => null, 'claim_expires_at' => null, 'updated_at' => now(),
                    ]);
                DB::table('kernel_phase2_translation_attempts')->where('operation_id', $operationId)->update([
                    'outcome' => 'NO_OP', 'applied_at' => now(), 'provider_request_id' => $response->providerRequestId,
                    'response_hash' => $hash,
                ]);
                return self::NO_OP;
            }
            $revision = ((int) ($unit->translation_revision ?? 0)) + 1;
            DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $attempt->blueprint_id)->where('cognitive_type', $attempt->cognitive_type)
                ->where('language_code', $attempt->language_code)->where('source_revision', $attempt->source_revision)
                ->update([
                    'translation_revision' => $revision, 'translation' => json_encode($response->translation, JSON_THROW_ON_ERROR),
                    'translation_hash' => $hash, 'state' => 'CREATED', 'creation_status' => 'CREATED',
                    'validation_status' => 'NOT_VALIDATED', 'claim_token' => null,
                    'claimed_at' => null, 'claim_expires_at' => null,
                    'provider_metadata' => json_encode(['provider_request_id' => $response->providerRequestId], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            DB::table('kernel_phase2_translation_attempts')->where('operation_id', $operationId)->update([
                'outcome' => 'APPLIED', 'applied_at' => now(),
                'provider_request_id' => $response->providerRequestId, 'response_hash' => $hash,
            ]);
            return 'CREATED';
        });
    }

    public function fail(string $operationId, Phase2ProviderTechnicalFailure $failure): string
    {
        return DB::transaction(function () use ($operationId, $failure): string {
            $attempt = DB::table('kernel_phase2_translation_attempts')->where('operation_id', $operationId)->first();
            if ($attempt === null) return self::STALE_RESULT;
            if ($attempt->outcome !== 'OPEN') {
                return $attempt->outcome === 'RETRYABLE_TECHNICAL_FAILURE'
                    ? self::RETRYABLE_TECHNICAL_FAILURE
                    : ($attempt->outcome === 'NON_RETRYABLE_TECHNICAL_FAILURE'
                        ? self::NON_RETRYABLE_TECHNICAL_FAILURE : self::STALE_RESULT);
            }
            $run = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $attempt->blueprint_id)->lockForUpdate()->first();
            $identityRevision = $run === null ? null : $this->identityRevision($run);
            $execution = DB::table('kernel_phase1_executions')
                ->where('blueprint_id', $attempt->blueprint_id)
                ->when($identityRevision !== null, fn ($q) => $q->where('identity_revision', $identityRevision))
                ->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $attempt->blueprint_id)
                ->where('cognitive_type', $attempt->cognitive_type)->lockForUpdate()->first();
            $unit = DB::table('kernel_phase2_translation_units')->where('blueprint_id', $attempt->blueprint_id)
                ->where('cognitive_type', $attempt->cognitive_type)->where('language_code', $attempt->language_code)
                ->where('source_revision', $attempt->source_revision)->lockForUpdate()->first();
            $attempt = DB::table('kernel_phase2_translation_attempts')->where('operation_id', $operationId)->lockForUpdate()->first();
            $internal = json_decode((string) $attempt->internal_envelope, true, 512, JSON_THROW_ON_ERROR);
            $source = $slot === null ? null : $this->json($slot->source);
            $currentRevision = ($source !== null && $slot !== null)
                ? $this->sourceRevision($source) : null;
            if ($run === null || $execution === null || !$this->isPhase1Terminal($execution)
                || $slot === null || !$this->validSource((string) $attempt->cognitive_type, $slot)
                || $currentRevision !== (string) $attempt->source_revision
                || $unit === null || $unit->state !== 'IN_PROGRESS'
                || (string) $unit->claim_token !== (string) $attempt->claim_token
                || (int) $unit->retry_cycle !== (int) $attempt->retry_cycle
                || (int) $unit->attempt_number !== (int) $attempt->attempt_number
                || $unit->claim_expires_at === null || now()->gte($unit->claim_expires_at)
                || $unit->translation_revision !== $attempt->expected_translation_revision
                || $unit->yellow_revision !== ($internal['yellow_revision'] ?? null)) return self::STALE_RESULT;
            $permanent = !$failure->retryable || (int) $attempt->attempt_number >= 4;
            $delay = match ((int) $attempt->attempt_number) { 1 => 60, 2 => 300, 3 => 900, default => 0 };
            if ($failure->retryAfterSeconds !== null) $delay = max($delay, $failure->retryAfterSeconds);
            $state = $permanent ? 'PERMANENT_FAILURE' : 'RETRYABLE_FAILURE';
            DB::table('kernel_phase2_translation_units')->where('blueprint_id', $attempt->blueprint_id)
                ->where('cognitive_type', $attempt->cognitive_type)->where('language_code', $attempt->language_code)
                ->where('source_revision', $attempt->source_revision)
                ->update([
                    'state' => $state, 'next_attempt_at' => $permanent ? null : now()->addSeconds($delay),
                    'claim_token' => null, 'claimed_at' => null, 'claim_expires_at' => null,
                    'last_technical_reason_code' => $failure->reasonCode,
                    'permanent_failed_at' => $permanent ? now() : null, 'updated_at' => now(),
                ]);
            $outcome = $permanent ? 'NON_RETRYABLE_TECHNICAL_FAILURE' : 'RETRYABLE_TECHNICAL_FAILURE';
            DB::table('kernel_phase2_translation_attempts')->where('operation_id', $operationId)->update([
                'outcome' => $outcome, 'applied_at' => now(),
            ]);
            if ($permanent) {
                DB::table('kernel_phase2_operation_signals')->insert([
                    'blueprint_id' => $attempt->blueprint_id, 'cognitive_type' => $attempt->cognitive_type,
                    'language_code' => $attempt->language_code, 'source_revision' => $attempt->source_revision,
                    'translation_revision' => $unit->translation_revision, 'retry_cycle' => $attempt->retry_cycle,
                    'technical_reason_code' => $failure->reasonCode, 'owner_phase' => 'PHASE2', 'blocked_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $permanent ? self::NON_RETRYABLE_TECHNICAL_FAILURE : self::RETRYABLE_TECHNICAL_FAILURE;
        });
    }

    /**
     * Explicit recovery only: a resolution event is the sole way to open a
     * fresh technical cycle after PERMANENT_FAILURE. Replaying the same event
     * is idempotent and never creates a second cycle.
     */
    public function authorizeRetryCycle(
        string $blueprintId,
        string $type,
        string $language,
        string $sourceRevision,
        string $resolutionEventId,
        string $authorizedBy,
        string $reasonCode,
    ): bool {
        if (!in_array($authorizedBy, ['ADMIN', 'SYSTEM_RECOVERY'], true)) {
            throw new LogicException('Phase2 resolution actor is not authorized.');
        }
        return DB::transaction(function () use (
            $blueprintId, $type, $language, $sourceRevision, $resolutionEventId,
            $authorizedBy, $reasonCode,
        ): bool {
            $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)
                ->lockForUpdate()->first();
            $identityRevision = $run === null ? null : $this->identityRevision($run);
            $execution = DB::table('kernel_phase1_executions')->where('blueprint_id', $blueprintId)
                ->when($identityRevision !== null, fn ($q) => $q->where('identity_revision', $identityRevision))
                ->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $blueprintId)
                ->where('cognitive_type', $type)->lockForUpdate()->first();
            if ($run === null || !$this->isPhase1Terminal($execution) || $slot === null
                || !$this->validSource($type, $slot)
                || $this->sourceRevision($this->json($slot->source)) !== $sourceRevision) {
                return false;
            }
            $unit = DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $sourceRevision)
                ->lockForUpdate()->first();
            if ($unit === null || $unit->state !== 'PERMANENT_FAILURE') {
                return false;
            }
            $nextCycle = ((int) $unit->retry_cycle) + 1;
            $acquired = DB::selectOne(
                'INSERT INTO kernel_phase2_resolution_events
                 (resolution_event_id, blueprint_id, cognitive_type, language_code,
                  source_revision, authorized_by, authorization_reason_code, retry_cycle, authorized_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON CONFLICT (resolution_event_id) DO NOTHING
                 RETURNING resolution_event_id',
                [$resolutionEventId, $blueprintId, $type, $language, $sourceRevision,
                    $authorizedBy, $reasonCode, $nextCycle, now()]
            );
            if ($acquired === null) return false;
            DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $sourceRevision)
                ->update([
                    'state' => 'PENDING', 'retry_cycle' => $nextCycle,
                    'attempt_number' => 0, 'next_attempt_at' => null,
                    'permanent_failed_at' => null, 'last_technical_reason_code' => null,
                    'updated_at' => now(),
                ]);
            return true;
        });
    }

    /**
     * Internal correction seam used by Quarantine/Phase2 integration tests.
     * It deliberately has no UI or Quarantine dependency.
     *
     * @param array<string,mixed> $translation
     */
    public function applyYellowCorrection(
        string $blueprintId,
        string $type,
        string $language,
        string $sourceRevision,
        ?int $expectedTranslationRevision,
        ?int $expectedYellowRevision,
        array $translation,
    ): string {
        return DB::transaction(function () use (
            $blueprintId, $type, $language, $sourceRevision,
            $expectedTranslationRevision, $expectedYellowRevision, $translation,
        ): string {
            $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->lockForUpdate()->first();
            $identityRevision = $run === null ? null : $this->identityRevision($run);
            $execution = DB::table('kernel_phase1_executions')->where('blueprint_id', $blueprintId)
                ->when($identityRevision !== null, fn ($q) => $q->where('identity_revision', $identityRevision))
                ->lockForUpdate()->first();
            $slot = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->lockForUpdate()->first();
            $unit = DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $sourceRevision)
                ->lockForUpdate()->first();
            if ($run === null || !$this->isPhase1Terminal($execution) || $slot === null
                || !$this->validSource($type, $slot)
                || $unit === null
                || ($unit->state !== 'PERMANENT_FAILURE' && !in_array($unit->state, ['CREATED', 'IN_PROGRESS'], true))
                || (($unit->state === 'PERMANENT_FAILURE' && $unit->translation_revision === null
                        && $expectedTranslationRevision !== null)
                    || ($unit->state === 'PERMANENT_FAILURE' && $unit->translation_revision !== null
                        && (int) $unit->translation_revision !== $expectedTranslationRevision)
                    || ($unit->state !== 'PERMANENT_FAILURE'
                        && (int) $unit->translation_revision !== $expectedTranslationRevision)
                    || $unit->yellow_revision !== $expectedYellowRevision)
                || $this->sourceRevision($this->json($slot->source)) !== $sourceRevision
                 || ($translation['correct_answer_key'] ?? null)
                     !== ($this->json($slot->source)['correct_answer_key'] ?? null)
                || !$this->validShape($type, $translation, false, $language)) {
                return self::STALE_RESULT;
            }
            $revision = $unit->translation_revision === null
                ? 1 : ((int) $unit->translation_revision) + 1;
            $hash = hash('sha256', json_encode($translation, JSON_THROW_ON_ERROR));
            if ((string) $unit->translation_hash === $hash) return self::NO_OP;
            DB::table('kernel_phase2_translation_units')
                ->where('blueprint_id', $blueprintId)->where('cognitive_type', $type)
                ->where('language_code', $language)->where('source_revision', $sourceRevision)
                ->update([
                    'translation' => json_encode($translation, JSON_THROW_ON_ERROR),
                    'translation_hash' => $hash, 'translation_revision' => $revision,
                    'yellow_revision' => $revision, 'state' => 'CREATED',
                    'creation_status' => 'CREATED', 'validation_status' => 'NOT_VALIDATED',
                    'claim_token' => null, 'claimed_at' => null, 'claim_expires_at' => null,
                    'next_attempt_at' => null, 'last_technical_reason_code' => null,
                    'permanent_failed_at' => null,
                    'provider_metadata' => json_encode(['origin' => 'AUTHORIZED_CORRECTION'], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            return 'CORRECTED';
        });
    }

    /** @return array<string,mixed> */
    private function providerSource(array $source, string $type): array
    {
        $choices = [];
        foreach ($source['choices'] as $key => $value) {
            $choices[(string) ($value['key'] ?? $key)] = is_array($value) ? ($value['text'] ?? null) : $value;
        }
        return ['question' => $source['question'], 'choices' => $choices,
            'correct_answer_key' => $source['correct_answer_key'], 'sv' => $source['sv']];
    }

    /** @return array<string,mixed> */
    private function responseSchema(string $type): array
    {
        return ['question' => 'string', 'choices' => str_starts_with($type, 'QCM_') ? ['a','b','c','d'] : ['a','b'],
            'correct_answer_key' => 'unchanged', 'sv' => 'string'];
    }

    private function validShape(
        string $type,
        array $payload,
        bool $allowEnglishTrueFalse = false,
        ?string $targetLanguage = null,
    ): bool
    {
        $choices = $payload['choices'] ?? null;
        if (!is_string($payload['question'] ?? null) || trim($payload['question']) === ''
            || !is_string($payload['sv'] ?? null) || trim($payload['sv']) === ''
            || !is_array($choices) || ($payload['correct_answer_key'] ?? null) === null) return false;
        $keys = array_keys($choices);
        if (array_is_list($choices)) $keys = array_map(static fn($v) => (string) ($v['key'] ?? ''), $choices);
        foreach ($choices as $choice) {
            $value = is_array($choice) ? ($choice['text'] ?? null) : $choice;
            if (!is_string($value) || trim($value) === '') return false;
        }
        sort($keys);
        $expected = str_starts_with($type, 'QCM_') ? ['a','b','c','d'] : ['a','b'];
        if ($keys !== $expected || !in_array($payload['correct_answer_key'], $expected, true)) return false;
        if (!$allowEnglishTrueFalse && !str_starts_with($type, 'QCM_')) {
            $labels = self::TARGET_TRUE_FALSE_LABELS[$targetLanguage ?? ''] ?? null;
            if ($labels === null || ($payload['choices']['a'] ?? null) !== $labels[0]
                || ($payload['choices']['b'] ?? null) !== $labels[1]) return false;
        }
        return true;
    }

    private function validSource(string $type, ?object $slot): bool
    {
        if ($slot === null || (string) $slot->creation_status !== 'CREATED'
            || (string) $slot->validation_status !== 'PASS') return false;
        $source = $this->json($slot->source);
        return ($source['source_language'] ?? null) === 'en'
            && ($source['cognitive_type'] ?? null) === $type
            && $this->validShape($type, $source, true)
            && (!str_ends_with($type, '_TRUE') || ($source['correct_answer_key'] ?? null) === 'a')
            && (!str_ends_with($type, '_FALSE') || ($source['correct_answer_key'] ?? null) === 'b')
            && (!str_starts_with($type, 'TRUE_FALSE_')
                || ($this->choiceValue($source['choices'] ?? [], 'a') === 'TRUE'
                    && $this->choiceValue($source['choices'] ?? [], 'b') === 'FALSE'));
    }

    private function choiceValue(array $choices, string $key): mixed
    {
        if (array_key_exists($key, $choices)) {
            $choice = $choices[$key];
            return is_array($choice) ? ($choice['text'] ?? null) : $choice;
        }
        foreach ($choices as $choice) {
            if (is_array($choice) && (string) ($choice['key'] ?? '') === $key) {
                return $choice['text'] ?? null;
            }
        }
        return null;
    }

    /** @param array<int|string,mixed> $choices @return array<int,string> */
    private function choiceKeys(array $choices): array
    {
        if (! array_is_list($choices)) {
            return array_map('strval', array_keys($choices));
        }
        return array_map(
            static fn (mixed $choice): string => is_array($choice) ? (string) ($choice['key'] ?? '') : '',
            $choices,
        );
    }

    private function json(mixed $value): array
    {
        return is_array($value) ? $value : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function identityRevision(object $run): string
    {
        $required = [
            'depth', 'domain_code', 'subdomain_active', 'subject_active',
            'dominant_idea_active', 'kernel_code_dd', 'kernel_code_do',
            'kernel_code_sub', 'kernel_code_suj', 'kernel_code_ide',
            'kernel_code_vvvv', 'kernel_code',
        ];
        $identity = [];
        foreach ($required as $column) {
            if (!isset($run->{$column}) || trim((string) $run->{$column}) === '') {
                return '';
            }
            $identity[$column] = (string) $run->{$column};
        }
        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    private function isPhase1Terminal(?object $execution): bool
    {
        if ($execution === null || (string) $execution->state !== 'COMPLETED') return false;
        $result = is_array($execution->result ?? null)
            ? $execution->result
            : json_decode((string) ($execution->result ?? ''), true);
        return is_array($result)
            && ($result['phase1_terminal'] ?? null) === Phase1ExecutionRepository::PHASE1_CREATION_COMPLETED
            && ($result['creation_status'] ?? null) === 'CREATED';
    }

    /** @param array<string,mixed> $source */
    private function sourceRevision(array $source): string
    {
        return hash('sha256', json_encode([
            'question' => $source['question'] ?? null,
            'choices' => $source['choices'] ?? null,
            'correct_answer_key' => $source['correct_answer_key'] ?? null,
            'sv' => $source['sv'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }
}