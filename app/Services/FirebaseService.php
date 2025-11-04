<?php

namespace App\Services;

use GuzzleHttp\Client;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;

class FirebaseService
{
    private static ?FirebaseService $instance = null;
    private ?Client $httpClient = null;
    private ?string $projectId = null;
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;
    private bool $initialized = false;
    private $credentials = null;

    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 100;
    private const TOKEN_REFRESH_BUFFER_SECONDS = 300;

    private function __construct()
    {
        $this->initialize();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initialize(): void
    {
        try {
            $this->projectId = env('FIREBASE_PROJECT_ID');
            $credentialsPath = env('FIREBASE_CREDENTIALS_PATH');

            if (empty($this->projectId)) {
                Log::warning('FIREBASE_PROJECT_ID not configured');
                return;
            }

            if (empty($credentialsPath) || !file_exists(base_path($credentialsPath))) {
                Log::warning('Firebase credentials file not found at: ' . $credentialsPath);
                return;
            }

            $credentialsJson = file_get_contents(base_path($credentialsPath));
            $credentialsArray = json_decode($credentialsJson, true);

            if (!isset($credentialsArray['project_id']) || $credentialsArray['project_id'] !== $this->projectId) {
                Log::error('Firebase credentials project_id mismatch. Expected: ' . $this->projectId . ', Got: ' . ($credentialsArray['project_id'] ?? 'none'));
                return;
            }

            $this->credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/cloud-platform',
                $credentialsArray
            );

            $this->httpClient = new Client([
                'base_uri' => 'https://firestore.googleapis.com/v1/',
                'timeout' => 10,
            ]);

            $this->refreshAccessToken();
            $this->initialized = true;

            Log::info('Firebase initialized successfully with project: ' . $this->projectId);
        } catch (\Exception $e) {
            Log::error('Firebase initialization failed: ' . $e->getMessage());
            $this->initialized = false;
        }
    }

    private function refreshAccessToken(): void
    {
        if ($this->credentials) {
            try {
                $authToken = $this->credentials->fetchAuthToken();
                $this->accessToken = $authToken['access_token'] ?? null;
                $this->tokenExpiry = time() + ($authToken['expires_in'] ?? 3600);
                
                Log::info('Firebase access token refreshed, expires at: ' . date('Y-m-d H:i:s', $this->tokenExpiry));
            } catch (\Exception $e) {
                Log::error('Failed to refresh Firebase access token: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    private function ensureValidToken(): void
    {
        if (!$this->tokenExpiry || time() >= ($this->tokenExpiry - self::TOKEN_REFRESH_BUFFER_SECONDS)) {
            Log::info('Token expired or about to expire, refreshing...');
            $this->refreshAccessToken();
        }
    }

    private function getHeaders(): array
    {
        $this->ensureValidToken();
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function makeRequestWithRetry(string $method, string $uri, array $options = []): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->ensureValidToken();
                $options['headers'] = $this->getHeaders();
                
                $response = $this->httpClient->request($method, $uri, $options);
                $body = $response->getBody()->getContents();
                
                return json_decode($body, true) ?? [];
            } catch (RequestException $e) {
                $lastException = $e;
                $attempt++;
                
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
                
                if ($statusCode === 401) {
                    Log::warning('401 Unauthorized, refreshing token...');
                    $this->refreshAccessToken();
                    continue;
                }
                
                if ($statusCode >= 500 || $statusCode === 429 || $statusCode === 0) {
                    if ($attempt < self::MAX_RETRIES) {
                        $delayMs = self::RETRY_DELAY_MS * pow(2, $attempt - 1);
                        Log::warning("Request failed (status $statusCode), retrying in {$delayMs}ms... (attempt $attempt/" . self::MAX_RETRIES . ")");
                        usleep($delayMs * 1000);
                        continue;
                    }
                }
                
                Log::error('Firebase request failed: ' . $e->getMessage());
                throw $e;
            }
        }

        throw $lastException ?? new \Exception('Request failed after ' . self::MAX_RETRIES . ' attempts');
    }

    private function getCurrentTimestamp(): array
    {
        $microtime = microtime(true);
        $datetime = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $microtime));
        return [
            'timestampValue' => $datetime->format('Y-m-d\TH:i:s.u\Z')
        ];
    }
    
    private function floatToFirestoreTimestamp(float $timestamp): array
    {
        $datetime = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $timestamp));
        return [
            'timestampValue' => $datetime->format('Y-m-d\TH:i:s.u\Z')
        ];
    }

    private function convertToFirestoreFormat(array $data): array
    {
        $result = ['fields' => []];
        foreach ($data as $key => $value) {
            $result['fields'][$key] = $this->convertValue($value, $key);
        }
        return $result;
    }

    private function convertValue($value, string $fieldName = ''): array
    {
        if ($value === '__SERVER_TIMESTAMP__') {
            return $this->getCurrentTimestamp();
        } elseif (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string)$value];
        } elseif (is_float($value)) {
            if ($fieldName === 'timestamp' || $fieldName === 'createdAt' || $fieldName === 'updatedAt') {
                return $this->floatToFirestoreTimestamp($value);
            }
            return ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) {
                return ['arrayValue' => [
                    'values' => array_map(function($v) { return $this->convertValue($v); }, $value)
                ]];
            } else {
                return ['mapValue' => $this->convertToFirestoreFormat($value)];
            }
        } elseif (is_null($value)) {
            return ['nullValue' => null];
        }
        return ['stringValue' => (string)$value];
    }

    private function convertFromFirestoreFormat(array $firestoreDoc): array
    {
        $result = [];
        if (!isset($firestoreDoc['fields'])) {
            return $result;
        }

        foreach ($firestoreDoc['fields'] as $key => $value) {
            $result[$key] = $this->extractValue($value);
        }
        return $result;
    }

    private function extractValue(array $value)
    {
        if (isset($value['stringValue'])) {
            return $value['stringValue'];
        } elseif (isset($value['integerValue'])) {
            return (int)$value['integerValue'];
        } elseif (isset($value['doubleValue'])) {
            return (float)$value['doubleValue'];
        } elseif (isset($value['booleanValue'])) {
            return $value['booleanValue'];
        } elseif (isset($value['timestampValue'])) {
            $datetime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $value['timestampValue']);
            if (!$datetime) {
                $datetime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $value['timestampValue']);
            }
            if (!$datetime) {
                $datetime = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $value['timestampValue']);
            }
            return $datetime ? (float)$datetime->format('U.u') : (float)strtotime($value['timestampValue']);
        } elseif (isset($value['arrayValue']['values'])) {
            return array_map([$this, 'extractValue'], $value['arrayValue']['values']);
        } elseif (isset($value['mapValue'])) {
            return $this->convertFromFirestoreFormat($value['mapValue']);
        } elseif (isset($value['nullValue'])) {
            return null;
        }
        return null;
    }

    public function createGameSession(string $gameId, array $gameData): bool
    {
        if (!$this->initialized) {
            Log::error('Firebase not initialized');
            return false;
        }

        try {
            $documentPath = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            
            $gameData['createdAt'] = '__SERVER_TIMESTAMP__';
            $gameData['updatedAt'] = '__SERVER_TIMESTAMP__';
            
            $firestoreData = $this->convertToFirestoreFormat($gameData);

            $this->makeRequestWithRetry('PATCH', $documentPath, [
                'json' => $firestoreData,
            ]);

            Log::info("Game session created: {$gameId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create game session: " . $e->getMessage());
            return false;
        }
    }

    public function gameSessionExists(string $gameId): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $documentPath = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            $response = $this->makeRequestWithRetry('GET', $documentPath);
            
            return isset($response['name']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateGameState(string $gameId, array $updates): bool
    {
        if (!$this->initialized) {
            Log::error('Firebase not initialized');
            return false;
        }

        if (!$this->gameSessionExists($gameId)) {
            Log::error("Cannot update non-existent game: {$gameId}");
            return false;
        }

        try {
            $documentPath = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            
            $updates['updatedAt'] = '__SERVER_TIMESTAMP__';
            $firestoreData = $this->convertToFirestoreFormat($updates);
            
            $updateMaskParams = [];
            foreach (array_keys($updates) as $field) {
                $updateMaskParams[] = 'updateMask.fieldPaths=' . urlencode($field);
            }
            $queryString = implode('&', $updateMaskParams);

            $this->makeRequestWithRetry('PATCH', $documentPath . '?' . $queryString, [
                'json' => $firestoreData,
            ]);

            Log::info("Game state updated: {$gameId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update game state: " . $e->getMessage());
            return false;
        }
    }

    public function getGameState(string $gameId): ?array
    {
        if (!$this->initialized) {
            return null;
        }

        try {
            $documentPath = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            $response = $this->makeRequestWithRetry('GET', $documentPath);

            if (isset($response['fields'])) {
                return $this->convertFromFirestoreFormat($response);
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get game state: " . $e->getMessage());
            return null;
        }
    }

    public function recordBuzz(string $gameId, string $playerId, float $timestamp): bool
    {
        if (!$this->initialized) {
            Log::error('Firebase not initialized');
            return false;
        }

        if (!$this->gameSessionExists($gameId)) {
            Log::error("Cannot record buzz for non-existent game: {$gameId}");
            return false;
        }

        try {
            $buzzId = uniqid('buzz_', true);
            $documentPath = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}/buzzes/{$buzzId}";

            $buzzData = [
                'playerId' => $playerId,
                'timestamp' => $timestamp,
                'createdAt' => '__SERVER_TIMESTAMP__',
            ];

            $firestoreData = $this->convertToFirestoreFormat($buzzData);

            $this->makeRequestWithRetry('PATCH', $documentPath, [
                'json' => $firestoreData,
            ]);

            Log::info("Buzz recorded: {$playerId} at {$timestamp}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to record buzz: " . $e->getMessage());
            return false;
        }
    }

    public function getBuzzes(string $gameId): array
    {
        if (!$this->initialized) {
            return [];
        }

        try {
            $collectionPath = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}/buzzes";
            $response = $this->makeRequestWithRetry('GET', $collectionPath);

            $buzzes = [];
            if (isset($response['documents'])) {
                foreach ($response['documents'] as $doc) {
                    if (isset($doc['fields'])) {
                        $buzzes[] = $this->convertFromFirestoreFormat($doc);
                    }
                }
            }

            usort($buzzes, fn($a, $b) => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0));

            return $buzzes;
        } catch (\Exception $e) {
            Log::error("Failed to get buzzes: " . $e->getMessage());
            return [];
        }
    }

    public function deleteGameSession(string $gameId): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $documentPath = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            $this->makeRequestWithRetry('DELETE', $documentPath);
            
            Log::info("Game session deleted: {$gameId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete game session: " . $e->getMessage());
            return false;
        }
    }
}
