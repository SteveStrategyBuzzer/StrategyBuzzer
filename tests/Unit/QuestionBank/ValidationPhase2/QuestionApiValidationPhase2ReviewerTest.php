<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\ValidationPhase2;

use App\Services\QuestionApi\QuestionApiClient;
use App\Services\QuestionBank\ValidationPhase2\QuestionApiValidationPhase2Reviewer;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Request;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2Reviewer;
use App\Services\QuestionBank\ValidationPhase2\ValidationPhase2TechnicalFailure;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

final class QuestionApiValidationPhase2ReviewerTest extends TestCase
{
    private function request(): ValidationPhase2Request
    {
        return new ValidationPhase2Request('opaque-ref', 'opaque-key', 'en', 'fr', 'QCM_RECOGNITION', [
            'question' => 'Which color?', 'choices' => ['a' => 'Blue', 'b' => 'Red', 'c' => 'Green', 'd' => 'Yellow'],
            'correct_answer_key' => 'a', 'sv' => 'Blue is a color.',
        ], [
            'question' => 'Quelle couleur ?', 'choices' => ['a' => 'Bleu', 'b' => 'Rouge', 'c' => 'Vert', 'd' => 'Jaune'],
            'correct_answer_key' => 'a', 'sv' => 'Le bleu est une couleur.',
        ], [], [], []);
    }

    public function test_pass_and_suspicion_are_strictly_decoded(): void
    {
        $client = new class extends QuestionApiClient {
            public string $endpoint = '';
            public function __construct() {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                $this->endpoint = $endpoint;
                return new Response(new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(['ok' => true, 'result' => [
                    'validation_request_reference' => 'opaque-ref', 'validator_request_id' => 'validator-1',
                    'decision' => 'PASS', 'findings' => [],
                    'external_validation_idempotency_key' => 'opaque-key',
                ]], JSON_THROW_ON_ERROR)));
            }
        };
        $reviewer = new QuestionApiValidationPhase2Reviewer($client);
        self::assertSame('PASS', $reviewer->review($this->request())->decision);
        self::assertSame(QuestionApiClient::ENDPOINT_VALIDATE_KERNEL_PHASE2, $client->endpoint);
    }

    public function test_missing_configuration_is_non_retryable(): void
    {
        $client = new class extends QuestionApiClient {
            public function __construct() {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                throw new \RuntimeException('missing secret');
            }
        };
        $this->expectException(ValidationPhase2TechnicalFailure::class);
        try {
            (new QuestionApiValidationPhase2Reviewer($client))->review($this->request());
        } catch (ValidationPhase2TechnicalFailure $failure) {
            self::assertFalse($failure->retryable);
            self::assertSame('VALIDATOR_NOT_CONFIGURED', $failure->reasonCode);
            throw $failure;
        }
    }

    public function test_transport_runtime_failure_is_retryable_not_configuration(): void
    {
        $client = new class extends QuestionApiClient {
            public function __construct() {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                throw new \RuntimeException('connection reset by peer');
            }
        };
        try {
            (new QuestionApiValidationPhase2Reviewer($client))->review($this->request());
            self::fail('Expected technical failure.');
        } catch (ValidationPhase2TechnicalFailure $failure) {
            self::assertTrue($failure->retryable);
            self::assertSame('VALIDATOR_TRANSPORT_FAILURE', $failure->reasonCode);
        }
    }

    public function test_container_resolves_independent_reviewer_and_phase2_endpoint(): void
    {
        $reviewer = app(ValidationPhase2Reviewer::class);
        self::assertInstanceOf(QuestionApiValidationPhase2Reviewer::class, $reviewer);
        self::assertSame(
            QuestionApiClient::ENDPOINT_VALIDATE_KERNEL_PHASE2,
            QuestionApiClient::ENDPOINT_VALIDATE_KERNEL_PHASE2,
        );
        self::assertNotSame(
            QuestionApiClient::ENDPOINT_VALIDATE_KERNEL_PHASE1_SOURCE,
            QuestionApiClient::ENDPOINT_VALIDATE_KERNEL_PHASE2,
        );
    }

    public function test_http_5xx_is_retryable_and_preserves_retry_after(): void
    {
        $client = new class extends QuestionApiClient {
            public function __construct() {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                return new Response(new Psr7Response(503, ['Content-Type' => 'application/json', 'Retry-After' => '17'], json_encode(['error' => 'down'], JSON_THROW_ON_ERROR)));
            }
        };
        try {
            (new QuestionApiValidationPhase2Reviewer($client))->review($this->request());
            self::fail('Expected technical failure.');
        } catch (ValidationPhase2TechnicalFailure $failure) {
            self::assertTrue($failure->retryable);
            self::assertSame(17, $failure->retryAfterSeconds);
        }
    }

    /** @dataProvider retryableRateLimitCases */
    public function test_http_429_is_retryable_and_preserves_reliable_retry_after(int $retryAfter): void
    {
        $client = new class($retryAfter) extends QuestionApiClient {
            public function __construct(private readonly int $retryAfter) {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                return new Response(new Psr7Response(429, [
                    'Content-Type' => 'application/json',
                    'Retry-After' => (string) $this->retryAfter,
                ], json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR)));
            }
        };
        try {
            (new QuestionApiValidationPhase2Reviewer($client))->review($this->request());
            self::fail('Expected retryable rate-limit failure.');
        } catch (ValidationPhase2TechnicalFailure $failure) {
            self::assertTrue($failure->retryable);
            self::assertSame($retryAfter, $failure->retryAfterSeconds);
            self::assertSame('VALIDATOR_HTTP_FAILURE', $failure->reasonCode);
        }
    }

    public static function retryableRateLimitCases(): array
    {
        return ['below floor' => [17], 'above floor' => [1200]];
    }

    public function test_endpoint_invalid_contract_is_retryable_invalid_response(): void
    {
        $client = new class extends QuestionApiClient {
            public function __construct() {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                return new Response(new Psr7Response(502, ['Content-Type' => 'application/json'],
                    json_encode(['ok' => false, 'error' => 'invalid_validation_response'], JSON_THROW_ON_ERROR)));
            }
        };
        try {
            (new QuestionApiValidationPhase2Reviewer($client))->review($this->request());
            self::fail('Expected invalid response failure.');
        } catch (ValidationPhase2TechnicalFailure $failure) {
            self::assertTrue($failure->retryable);
            self::assertSame('INVALID_VALIDATION_RESPONSE', $failure->reasonCode);
        }
    }

    public function test_no_provider_503_is_non_retryable_configuration_failure(): void
    {
        $client = new class extends QuestionApiClient {
            public function __construct() {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                return new Response(new Psr7Response(
                    503,
                    ['Content-Type' => 'application/json'],
                    json_encode(['ok' => false, 'error' => 'no_providers_configured'], JSON_THROW_ON_ERROR),
                ));
            }
        };
        try {
            (new QuestionApiValidationPhase2Reviewer($client))->review($this->request());
            self::fail('Expected configuration failure.');
        } catch (ValidationPhase2TechnicalFailure $failure) {
            self::assertFalse($failure->retryable);
            self::assertSame('VALIDATOR_NOT_CONFIGURED', $failure->reasonCode);
        }
    }

    public function test_malformed_response_and_unexpected_keys_are_retryable(): void
    {
        $client = new class extends QuestionApiClient {
            public function __construct() {}
            public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
            {
                return new Response(new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(['ok' => true, 'result' => [
                    'validation_request_reference' => 'opaque-ref', 'validator_request_id' => 'validator-1',
                    'decision' => 'PASS', 'findings' => [], 'unexpected_internal_key' => 'leak',
                ]], JSON_THROW_ON_ERROR)));
            }
        };
        try {
            (new QuestionApiValidationPhase2Reviewer($client))->review($this->request());
            self::fail('Expected malformed response failure.');
        } catch (ValidationPhase2TechnicalFailure $failure) {
            self::assertTrue($failure->retryable);
            self::assertSame('INVALID_VALIDATION_RESPONSE', $failure->reasonCode);
        }
    }
}