<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionApi\QuestionApiClient;

final class QuestionApiValidationPhase1Reviewer implements ValidationPhase1Reviewer
{
    public function __construct(private readonly QuestionApiClient $client) {}

    public function review(array $input): array
    {
        $response = $this->client->postAdmin(
            QuestionApiClient::ENDPOINT_VALIDATE_KERNEL_PHASE1_SOURCE,
            $input,
            ['source' => 'validation_phase1', 'timeout' => 120],
        );
        if (! $response->successful()) {
            throw new \RuntimeException('Validation Phase 1 Question API failure.');
        }
        $payload = $response->json();
        if (! is_array($payload) || ($payload['ok'] ?? false) !== true
            || ! is_array($payload['result'] ?? null)) {
            throw new \RuntimeException('Validation Phase 1 reviewer envelope invalide.');
        }
        return $payload['result'];
    }
}