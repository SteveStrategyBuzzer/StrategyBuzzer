<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase2;

final readonly class Phase2ProviderRequest
{
    public function __construct(
        public string $providerRequestReference,
        public string $externalIdempotencyKey,
        public string $sourceLanguage,
        public string $targetLanguage,
        public string $cognitiveType,
        public int $depth,
        /** @var array<string,mixed> */
        public array $context,
        /** @var array<string,mixed> */
        public array $sourcePayload,
        /** @var array<string,mixed> */
        public array $responseSchema,
    ) {}

    /** @return array<string,mixed> */
    public function externalPayload(): array
    {
        return [
            'provider_request_reference' => $this->providerRequestReference,
            'external_idempotency_key' => $this->externalIdempotencyKey,
            'source_language' => $this->sourceLanguage,
            'target_language' => $this->targetLanguage,
            'cognitive_type' => $this->cognitiveType,
            'depth' => $this->depth,
            'context' => $this->context,
            'source_payload' => $this->sourcePayload,
            'response_schema' => $this->responseSchema,
        ];
    }
}