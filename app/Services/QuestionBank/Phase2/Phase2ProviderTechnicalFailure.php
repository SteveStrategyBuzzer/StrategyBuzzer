<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase2;

final readonly class Phase2ProviderTechnicalFailure
{
    public function __construct(
        public string $reasonCode,
        public bool $retryable = true,
        public ?int $retryAfterSeconds = null,
    ) {}
}