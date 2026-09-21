<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

use App\Services\QuestionBank\KernelBlueprint;
use RuntimeException;

final class ValidationPhase2EntryBoundary
{
    public function __construct(
        private readonly ValidationPhase2Repository $repository = new ValidationPhase2Repository(),
        private readonly ?ValidationPhase2Reviewer $reviewer = null,
    ) {}

    /** Public boundary: the only caller input is blueprint_id. */
    public function receive(string $blueprintId): array
    {
        if ($blueprintId === '') throw new RuntimeException('[ValidationPhase2] blueprint_id requis.');
        $reviewer = $this->reviewer;
        if ($reviewer === null) {
            try {
                $reviewer = app(ValidationPhase2Reviewer::class);
            } catch (\Throwable $e) {
                $reviewer = new class implements ValidationPhase2Reviewer {
                    public function review(ValidationPhase2Request $request): ValidationPhase2Response
                    {
                        throw new ValidationPhase2TechnicalFailure(
                            'Validation Phase 2 validator is not configured.',
                            $request->validationRequestReference,
                            $request->externalValidationIdempotencyKey,
                            false,
                            null,
                            'VALIDATOR_NOT_CONFIGURED',
                        );
                    }
                };
            }
        }
        $results = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            foreach (ValidationPhase2Rules::LANGUAGES as $language) {
                $claim = $this->repository->claim($blueprintId, $type, $language);
                if ($claim['outcome'] !== 'CLAIMED') { $results[$type][$language] = $claim['outcome']; continue; }
                try {
                    $response = $reviewer->review($claim['request']);
                    $results[$type][$language] = $this->repository->apply($claim['run'], $response);
                } catch (ValidationPhase2TechnicalFailure $failure) {
                    $results[$type][$language] = $this->repository->fail($claim['run'], $failure);
                }
            }
        }
        return $results;
    }
}