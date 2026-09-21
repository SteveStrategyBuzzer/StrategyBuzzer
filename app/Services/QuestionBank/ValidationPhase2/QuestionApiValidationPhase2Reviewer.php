<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

use App\Services\QuestionApi\QuestionApiClient;
use Illuminate\Http\Client\ConnectionException;

final class QuestionApiValidationPhase2Reviewer implements ValidationPhase2Reviewer
{
    public function __construct(private readonly QuestionApiClient $client) {}

    public function review(ValidationPhase2Request $request): ValidationPhase2Response
    {
        try {
            $response = $this->client->postAdmin(
                QuestionApiClient::ENDPOINT_VALIDATE_KERNEL_PHASE2,
                $request->toExternalArray(),
                ['source' => 'validation_phase2', 'timeout' => 120],
            );
        } catch (ConnectionException $e) {
            throw new ValidationPhase2TechnicalFailure(
                'Validation Phase 2 transport failure.',
                $request->validationRequestReference,
                $request->externalValidationIdempotencyKey,
                true,
                null,
                'VALIDATOR_TRANSPORT_FAILURE',
            );
        } catch (\RuntimeException $e) {
            $message = strtolower($e->getMessage());
            $notConfigured = str_contains($message, 'missing or weak')
                || str_contains($message, 'missing secret')
                || str_contains($message, 'not configured')
                || str_contains($message, 'question_api_url');
            throw new ValidationPhase2TechnicalFailure(
                $notConfigured ? 'Validation Phase 2 validator is not configured.' : 'Validation Phase 2 transport failure.',
                $request->validationRequestReference,
                $request->externalValidationIdempotencyKey,
                !$notConfigured,
                null,
                $notConfigured ? 'VALIDATOR_NOT_CONFIGURED' : 'VALIDATOR_TRANSPORT_FAILURE',
            );
        } catch (\Throwable $e) {
            throw new ValidationPhase2TechnicalFailure(
                'Validation Phase 2 transport failure.',
                $request->validationRequestReference,
                $request->externalValidationIdempotencyKey,
                true,
                null,
                'VALIDATOR_TRANSPORT_FAILURE',
            );
        }
        if (!$response->successful()) {
            $errorPayload = $response->json();
            if ($response->status() === 503 && is_array($errorPayload)
                && ($errorPayload['ok'] ?? null) === false
                && ($errorPayload['error'] ?? null) === 'no_providers_configured') {
                throw new ValidationPhase2TechnicalFailure(
                    'Validation Phase 2 validator is not configured.',
                    $request->validationRequestReference,
                    $request->externalValidationIdempotencyKey,
                    false,
                    null,
                    'VALIDATOR_NOT_CONFIGURED',
                );
            }
            if (is_array($errorPayload) && ($errorPayload['error'] ?? null) === 'invalid_validation_response') {
                throw new ValidationPhase2TechnicalFailure(
                    'Validation Phase 2 response envelope invalide.',
                    $request->validationRequestReference,
                    $request->externalValidationIdempotencyKey,
                    true,
                    null,
                    'INVALID_VALIDATION_RESPONSE',
                );
            }
            throw new ValidationPhase2TechnicalFailure(
                'Validation Phase 2 Question API failure.',
                $request->validationRequestReference,
                $request->externalValidationIdempotencyKey,
                $response->status() === 429 || $response->status() >= 500,
                (int) ($response->header('Retry-After') ?: 0) ?: null,
                'VALIDATOR_HTTP_FAILURE',
            );
        }
        $payload = $response->json();
        $result = is_array($payload) ? ($payload['result'] ?? null) : null;
        $allowedResultKeys = ['validation_request_reference', 'validator_request_id', 'decision', 'findings', 'external_validation_idempotency_key'];
        if (($payload['ok'] ?? false) !== true || !is_array($result)
            || array_diff(array_keys($result), $allowedResultKeys)
            || ($result['validation_request_reference'] ?? null) !== $request->validationRequestReference
            || !is_string($result['validator_request_id'] ?? null)
            || !in_array($result['decision'] ?? null, ['PASS', 'SUSPICION'], true)
            || !is_array($result['findings'] ?? null)
            || (($result['external_validation_idempotency_key'] ?? null) !== $request->externalValidationIdempotencyKey)) {
            throw new ValidationPhase2TechnicalFailure(
                'Validation Phase 2 response envelope invalide.',
                $request->validationRequestReference,
                $request->externalValidationIdempotencyKey,
                true,
                null,
                'INVALID_VALIDATION_RESPONSE',
            );
        }
        foreach ($result['findings'] as $finding) {
            if (!is_array($finding)
                || array_diff(array_keys($finding), ['field_path', 'rule_code', 'severity', 'evidence'])
                || !isset($finding['field_path'], $finding['rule_code'], $finding['severity'])
                || $finding['severity'] !== 'BLOCKING'
                || !is_array($finding['evidence'])
                || array_key_exists('field_path', $finding['evidence'])
                || !isset($finding['evidence']['expected_rule'], $finding['evidence']['observed_result'])) {
                throw new ValidationPhase2TechnicalFailure(
                    'Validation Phase 2 finding envelope invalide.',
                    $request->validationRequestReference,
                    $request->externalValidationIdempotencyKey,
                    true,
                    null,
                    'INVALID_VALIDATION_RESPONSE',
                );
            }
        }
        return new ValidationPhase2Response(
            $result['validation_request_reference'],
            $result['validator_request_id'],
            $result['decision'],
            $result['findings'],
            $result['external_validation_idempotency_key'] ?? null,
        );
    }
}