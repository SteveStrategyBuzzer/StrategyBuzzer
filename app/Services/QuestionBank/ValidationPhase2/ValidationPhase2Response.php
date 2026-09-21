<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

final readonly class ValidationPhase2Response
{
    public function __construct(
        public string $validationRequestReference,
        public string $validatorRequestId,
        public string $decision,
        public array $findings = [],
        public ?string $externalValidationIdempotencyKey = null,
    ) {}
}