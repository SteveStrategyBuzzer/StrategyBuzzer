<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

use RuntimeException;

final class ValidationPhase2TechnicalFailure extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $validationRequestReference,
        public readonly string $externalValidationIdempotencyKey,
        public readonly bool $retryable = true,
        public readonly ?int $retryAfterSeconds = null,
        public readonly string $reasonCode = 'VALIDATOR_TECHNICAL_FAILURE',
    ) {
        parent::__construct($message);
    }
}