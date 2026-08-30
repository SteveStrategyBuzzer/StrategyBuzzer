<?php

namespace App\Services\QuestionApi;

use App\Models\AdminQuestionAuditLog;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Task #94 — Authenticated client for the question-api admin endpoints.
 *
 * Replaces the static MASTER_API_ADMIN_TOKEN shared secret with a per-call,
 * short-lived HS256 JWT signed with QUESTION_API_JWT_SECRET. Each call:
 *
 *   1. Hashes the JSON-serialised request body (sha256 hex) so the question-api
 *      can prove the body it received is the body the JWT was minted for.
 *   2. Mints a JWT with claims: aud=question-api, purpose=qapi_admin,
 *      sub=<caller user id or "system">, endpoint=<request path>,
 *      payload_hash=<hex>, jti=<uuid>, iat=<now>, exp=<now+TTL>.
 *   3. Writes a row to admin_question_audit_log capturing who triggered the
 *      call, against which endpoint, with what payload hash, at what time.
 *   4. Sends the request as `Authorization: Bearer <jwt>` and re-uses the
 *      raw JSON body (so the hash on the wire matches the hash in the JWT).
 *   5. Updates the audit row with the HTTP status / accepted flag / error.
 *
 * The caller never has to handle a static secret directly. The PHP secret
 * (QUESTION_API_JWT_SECRET) is the only thing both ends share, and it can
 * be rotated by re-deploying the question-api + Laravel together; revocation
 * happens naturally because tokens live for 60 seconds.
 */
class QuestionApiClient
{
    public const ENDPOINT_MASTER_QUESTION = '/generate-master-question';
    public const ENDPOINT_IMAGE_QUESTION = '/generate-image-question';
    public const ENDPOINT_KERNEL_PHASE1_SOURCE = '/generate-kernel-phase1-source';

    private const TOKEN_TTL_SECONDS = 60;
    private const JWT_ALGO = 'HS256';
    private const JWT_AUDIENCE = 'question-api';
    private const JWT_PURPOSE = 'qapi_admin';
    private const JWT_ISSUER = 'laravel-master-api';

    private string $baseUrl;
    private string $jwtSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('QUESTION_API_URL', 'http://localhost:3000'), '/');
        $this->jwtSecret = $this->resolveSecret();
    }

    /**
     * POST to a question-api admin endpoint with a short-lived signed token,
     * an audit row, and raw-body payload-hash binding.
     *
     * @param  string  $endpoint   One of the ENDPOINT_* constants.
     * @param  array   $payload    JSON-serialisable request body.
     * @param  array   $opts       caller_user_id, source, timeout.
     */
    public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
    {
        if (! in_array($endpoint, [
            self::ENDPOINT_MASTER_QUESTION,
            self::ENDPOINT_IMAGE_QUESTION,
            self::ENDPOINT_KERNEL_PHASE1_SOURCE,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported question-api admin endpoint: {$endpoint}");
        }

        $callerUserId = $opts['caller_user_id'] ?? Auth::id();
        $source = (string) ($opts['source'] ?? 'unknown');
        $timeout = (int) ($opts['timeout'] ?? 30);

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($rawBody === false) {
            throw new \RuntimeException('QuestionApiClient: failed to encode payload to JSON');
        }
        $payloadHash = hash('sha256', $rawBody);

        $jti = (string) Str::uuid();
        $issuedAt = time();
        $expiresAt = $issuedAt + self::TOKEN_TTL_SECONDS;

        $jwt = JWT::encode([
            'iss' => self::JWT_ISSUER,
            'aud' => self::JWT_AUDIENCE,
            'sub' => $callerUserId !== null ? (string) $callerUserId : 'system',
            'purpose' => self::JWT_PURPOSE,
            'endpoint' => $endpoint,
            'payload_hash' => $payloadHash,
            'jti' => $jti,
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
        ], $this->jwtSecret, self::JWT_ALGO);

        $auditRow = $this->writeAuditRow([
            'jti' => $jti,
            'caller_user_id' => $callerUserId,
            'endpoint' => $endpoint,
            'payload_hash' => $payloadHash,
            'source' => $source,
        ]);

        $response = null;
        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($rawBody, 'application/json')
                ->post($this->baseUrl . $endpoint);

            $this->updateAuditRow($auditRow, [
                'accepted' => $response->successful(),
                'http_status' => $response->status(),
                'error' => $response->successful() ? null : $this->summariseError($response),
                'responded_at' => now(),
            ]);

            return $response;
        } catch (\Throwable $e) {
            $this->updateAuditRow($auditRow, [
                'accepted' => false,
                'http_status' => null,
                'error' => Str::limit($e->getMessage(), 240, ''),
                'responded_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * Reads the shared secret. Falls back to GAME_SERVER_JWT_SECRET so a
     * brand-new deploy that already has a strong secret for the game server
     * keeps working (single secret, lower ops burden), but ops can rotate
     * the question-api secret independently by setting QUESTION_API_JWT_SECRET.
     *
     * Fails fast if neither is configured or the secret is too weak — the
     * question-api side enforces the same rule and refuses every request.
     */
    private function resolveSecret(): string
    {
        $candidates = [
            (string) env('QUESTION_API_JWT_SECRET', ''),
            (string) config('app.game_server_jwt_secret', ''),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $resolved = $this->decodeBase64IfNeeded($candidate);
            if (strlen(trim($resolved)) >= 16) {
                return $resolved;
            }
        }

        throw new \RuntimeException(
            'QuestionApiClient: missing or weak QUESTION_API_JWT_SECRET (>= 16 chars required)'
        );
    }

    private function decodeBase64IfNeeded(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        return $key;
    }

    private function writeAuditRow(array $data): ?AdminQuestionAuditLog
    {
        try {
            return AdminQuestionAuditLog::create([
                'jti' => $data['jti'],
                'caller_user_id' => $data['caller_user_id'],
                'endpoint' => $data['endpoint'],
                'payload_hash' => $data['payload_hash'],
                'source' => $data['source'],
                'accepted' => false,
                'http_status' => null,
                'error' => null,
                'created_at' => now(),
                'responded_at' => null,
            ]);
        } catch (\Throwable $e) {
            // Audit failure must NEVER block the call. Log loudly and continue.
            Log::warning('QuestionApiClient: audit row write failed', [
                'error' => $e->getMessage(),
                'jti' => $data['jti'] ?? null,
                'endpoint' => $data['endpoint'] ?? null,
            ]);
            return null;
        }
    }

    private function updateAuditRow(?AdminQuestionAuditLog $row, array $patch): void
    {
        if (!$row) {
            return;
        }
        try {
            $row->forceFill($patch)->save();
        } catch (\Throwable $e) {
            Log::warning('QuestionApiClient: audit row update failed', [
                'error' => $e->getMessage(),
                'jti' => $row->jti ?? null,
            ]);
        }
    }

    private function summariseError(Response $response): string
    {
        $body = $response->body();
        if (strlen($body) > 240) {
            $body = substr($body, 0, 240);
        }
        return 'http ' . $response->status() . ': ' . $body;
    }
}
