<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

final readonly class ValidationPhase2Request
{
    public function __construct(
        public string $validationRequestReference,
        public string $externalValidationIdempotencyKey,
        public string $sourceLanguage,
        public string $targetLanguage,
        public string $cognitiveType,
        public array $source,
        public array $target,
        public array $context,
        public array $ruleRegistry,
        public array $findingSchema,
    ) {}

    /** Deliberately excludes claims, storage, yellow state and translator evidence. */
    public function toExternalArray(): array
    {
        return [
            'validation_request_reference' => $this->validationRequestReference,
            'external_validation_idempotency_key' => $this->externalValidationIdempotencyKey,
            'source_language' => $this->sourceLanguage,
            'target_language' => $this->targetLanguage,
            'cognitive_type' => $this->cognitiveType,
            'source' => $this->source,
            'target' => $this->target,
            'context' => $this->context,
            'rule_registry' => $this->ruleRegistry,
            'finding_schema' => $this->findingSchema,
        ];
    }
}