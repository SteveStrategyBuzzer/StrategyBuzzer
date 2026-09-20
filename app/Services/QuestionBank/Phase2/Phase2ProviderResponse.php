<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase2;

final readonly class Phase2ProviderResponse
{
    /** @param array<string,mixed> $translation */
    public function __construct(
        public string $providerRequestReference,
        public string $providerRequestId,
        public string $targetLanguage,
        public array $translation,
    ) {}
}