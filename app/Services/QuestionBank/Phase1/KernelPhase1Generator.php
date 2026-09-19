<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionApi\QuestionApiClient;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use App\Services\QuestionBank\Rotation\KernelBlueprintProvisionedLoader;
use LogicException;
use Throwable;

class KernelPhase1Generator
{
    public const GENERATION_CONTRACT_VERSION = '06-phase1.v1.0';
    public const MAX_TECHNICAL_ATTEMPTS = 3;

    public function __construct(
        private readonly QuestionApiClient $questionApi,
        private readonly KernelBlueprintCognitiveSlotRepository $slots,
        private readonly KernelPhase1SourceValidator $validator,
        private readonly KernelBlueprintProvisionedLoader $loader =
            new KernelBlueprintProvisionedLoader(),
        private readonly Phase1ExecutionRepository $executions =
            new Phase1ExecutionRepository(),
    ) {}

    public function generate(
        string $blueprintId,
        ?string $expectedIdentityRevision = null,
        ?string $executionId = null,
        ?string $leaseToken = null,
    ): string {
        $blueprint = $this->loader->loadEngaged($blueprintId);
        // The provider payload is always derived from the persisted aggregate,
        // never from an object supplied by the previous phase.
        $this->executions->currentIdentityRevision($blueprintId);
        if ($expectedIdentityRevision !== null) {
            $actual = $this->executions->currentIdentityRevision($blueprintId);
            if (! hash_equals($expectedIdentityRevision, $actual)) {
                throw new LogicException("[Phase1] Identité persistante obsolète: {$blueprintId}.");
            }
        }
        $result = $this->generateSlots(
            $blueprint,
            $expectedIdentityRevision,
            $executionId,
            $leaseToken,
        );

        if ($result['failed'] !== []
            || count($result['created']) !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new Phase1TechnicalException(
                'CREATION_FAILED',
                "[Phase1] Les sept CognitiveSlots ne sont pas CREATED: {$blueprintId}."
            );
        }

        return $blueprintId;
    }

    /**
     * @return array{status: string, attempts: int, created: string[], failed: string[]}
     */
    private function generateSlots(
        KernelBlueprint $blueprint,
        ?string $expectedIdentityRevision = null,
        ?string $executionId = null,
        ?string $leaseToken = null,
    ): array {
        if (! $blueprint->isComplete()) {
            throw new Phase1TechnicalException(
                'IDENTITY_MISMATCH',
                'Phase 1 exige un KernelBlueprint complet avec kernel_code.'
            );
        }

        $existing = $this->slots->allForBlueprint((string) $blueprint->blueprint_id);
        if (count($existing) !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                'Le KernelBlueprint ne possède pas ses sept conteneurs cognitifs.'
            );
        }

        $pending = array_keys(array_filter(
            $existing,
            static fn(array $slot): bool => $slot['creation_status'] === 'EMPTY'
        ));

        if ($pending === []) {
            $blueprint->synchronizeCognitiveSlots($existing);

            return [
                'status' => 'IDEMPOTENT',
                'attempts' => 0,
                'created' => $this->typesWithStatus($existing, 'CREATED'),
                'failed' => $this->typesWithStatus($existing, 'CREATION_FAILED'),
            ];
        }

        $lastFailureType = 'TRANSPORT';
        $lastMessage = 'Aucune réponse Phase 1 valide.';

        for ($attempt = 1; $attempt <= self::MAX_TECHNICAL_ATTEMPTS; $attempt++) {
            try {
                $response = $this->questionApi->postAdmin(
                    QuestionApiClient::ENDPOINT_KERNEL_PHASE1_SOURCE,
                    $this->requestPayload($blueprint),
                    [
                        'source' => 'kernel_phase1',
                        'timeout' => 120,
                    ]
                );

                if (! $response->successful()) {
                    throw new Phase1TechnicalException(
                        'TRANSPORT',
                        "Question API HTTP {$response->status()}."
                    );
                }

                $responsePayload = $response->json();
                if (! is_array($responsePayload)) {
                    throw new Phase1TechnicalException(
                        'INVALID_JSON',
                        'La réponse Question API n’est pas un objet JSON.'
                    );
                }

                $sourcePayload = $responsePayload['result'] ?? null;
                if (($responsePayload['ok'] ?? false) !== true || ! is_array($sourcePayload)) {
                    throw new Phase1TechnicalException(
                        'INVALID_SCHEMA',
                        'L’enveloppe Question API Phase 1 est invalide.'
                    );
                }

                $validated = $this->validator->validate($blueprint, $sourcePayload);

                $writeCreated = function () use ($blueprint, $validated): void {
                    foreach ($validated['valid'] as $cognitiveType => $source) {
                        $this->slots->writeCreated(
                            (string) $blueprint->blueprint_id,
                            $cognitiveType,
                            $source
                        );
                    }
                };
                if ($expectedIdentityRevision !== null
                    && $executionId !== null
                    && $leaseToken !== null) {
                    $this->executions->withActiveLease(
                        $executionId,
                        $leaseToken,
                        (string) $blueprint->blueprint_id,
                        $expectedIdentityRevision,
                        $writeCreated,
                    );
                } elseif ($expectedIdentityRevision !== null) {
                    $this->executions->withCurrentIdentity(
                        (string) $blueprint->blueprint_id,
                        $expectedIdentityRevision,
                        $writeCreated,
                    );
                } else {
                    $writeCreated();
                }

                if ($validated['invalid'] === []) {
                    $fresh = $this->slots->allForBlueprint((string) $blueprint->blueprint_id);
                    $blueprint->synchronizeCognitiveSlots($fresh);

                    return [
                        'status' => 'CREATED',
                        'attempts' => $attempt,
                        'created' => $this->typesWithStatus($fresh, 'CREATED'),
                        'failed' => [],
                    ];
                }

                $lastFailureType = 'INVALID_SCHEMA';
                $lastMessage = implode(' | ', $validated['invalid']);
                $pending = array_keys($validated['invalid']);
            } catch (Phase1TechnicalException $exception) {
                $lastFailureType = $exception->failureType;
                $lastMessage = $exception->getMessage();
            } catch (LogicException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $lastFailureType = 'TRANSPORT';
                $lastMessage = $exception->getMessage();
            }
        }

        $writeFailures = function () use (
            $blueprint,
            $pending,
            $lastFailureType,
            $lastMessage,
        ): void {
            $fresh = $this->slots->allForBlueprint((string) $blueprint->blueprint_id);
            foreach ($pending as $cognitiveType) {
                if (($fresh[$cognitiveType]['creation_status'] ?? null) !== 'EMPTY') {
                    continue;
                }
                $this->slots->writeCreationFailure(
                    (string) $blueprint->blueprint_id,
                    $cognitiveType,
                    [
                        'reason_code' => 'PHASE1_TECHNICAL_FAILURE',
                        'attempt_count' => self::MAX_TECHNICAL_ATTEMPTS,
                        'last_failure_type' => $lastFailureType,
                        'message' => $lastMessage,
                        'occurred_at' => now()->toIso8601String(),
                    ]
                );
            }
        };
        if ($expectedIdentityRevision !== null
            && $executionId !== null
            && $leaseToken !== null) {
            $this->executions->withActiveLease(
                $executionId,
                $leaseToken,
                (string) $blueprint->blueprint_id,
                $expectedIdentityRevision,
                $writeFailures,
            );
        } elseif ($expectedIdentityRevision !== null) {
            $this->executions->withCurrentIdentity(
                (string) $blueprint->blueprint_id,
                $expectedIdentityRevision,
                $writeFailures,
            );
        } else {
            $writeFailures();
        }

        $fresh = $this->slots->allForBlueprint((string) $blueprint->blueprint_id);
        $blueprint->synchronizeCognitiveSlots($fresh);

        return [
            'status' => 'CREATION_FAILED',
            'attempts' => self::MAX_TECHNICAL_ATTEMPTS,
            'created' => $this->typesWithStatus($fresh, 'CREATED'),
            'failed' => $this->typesWithStatus($fresh, 'CREATION_FAILED'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(KernelBlueprint $blueprint): array
    {
        return [
            'schema_version' => KernelPhase1SourceValidator::SCHEMA_VERSION,
            'generation_contract_version' => self::GENERATION_CONTRACT_VERSION,
            'blueprint_id' => $blueprint->blueprint_id,
            'kernel_code' => $blueprint->kernel_code,
            'depth' => $blueprint->depth,
            'domain_code' => $blueprint->domain,
            'kernel_code_dd' => $blueprint->kernel_code_dd,
            'kernel_code_do' => $blueprint->kernel_code_do,
            'kernel_code_sub' => $blueprint->kernel_code_sub,
            'kernel_code_suj' => $blueprint->kernel_code_suj,
            'kernel_code_ide' => $blueprint->kernel_code_ide,
            'kernel_code_vvvv' => $blueprint->kernel_code_vvvv,
            'domain' => $blueprint->domain,
            'subdomain_active' => $blueprint->subdomain_active,
            'subject_active' => $blueprint->subject_active,
            'dominant_idea_active' => $blueprint->dominant_idea_active,
            'source_language' => KernelPhase1SourceValidator::SOURCE_LANGUAGE,
            'cognitive_rules' => [
                'types' => KernelBlueprint::COGNITIVE_TYPES,
                'qcm_correct_answer_key' => 'a',
                'true_false_choices' => ['a' => 'TRUE', 'b' => 'FALSE'],
                'independent_slots' => true,
                'no_master_slot' => true,
            ],
            'reading_rules' => [
                'reading_speed_wpm' => 150,
                'question_max_seconds' => 8,
                'sv_max_seconds' => 30,
            ],
            'output_schema' => KernelPhase1SourceValidator::SCHEMA_VERSION,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $slots
     * @return string[]
     */
    private function typesWithStatus(array $slots, string $status): array
    {
        return array_values(array_keys(array_filter(
            $slots,
            static fn(array $slot): bool => $slot['creation_status'] === $status
        )));
    }
}