<?php

namespace App\Services;

use GuzzleHttp\Client;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private static ?FirebaseService $instance = null;
    private ?Client $httpClient = null;
    private ?string $projectId = null;
    private ?string $accessToken = null;
    private bool $initialized = false;
    private $credentials = null;

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
            $authToken = $this->credentials->fetchAuthToken();
            $this->accessToken = $authToken['access_token'] ?? null;
        }
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function convertToFirestoreFormat(array $data): array
    {
        $result = ['fields' => []];
        foreach ($data as $key => $value) {
            $result['fields'][$key] = $this->convertValue($value);
        }
        return $result;
    }

    private function convertValue($value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string)$value];
        } elseif (is_float($value)) {
            return ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) {
                return ['arrayValue' => [
                    'values' => array_map([$this, 'convertValue'], $value)
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
        if (isset($value['stringValue'])) return $value['stringValue'];
        if (isset($value['integerValue'])) return (int)$value['integerValue'];
        if (isset($value['doubleValue'])) return $value['doubleValue'];
        if (isset($value['booleanValue'])) return $value['booleanValue'];
        if (isset($value['nullValue'])) return null;
        if (isset($value['arrayValue']['values'])) {
            return array_map([$this, 'extractValue'], $value['arrayValue']['values']);
        }
        if (isset($value['mapValue']['fields'])) {
            return $this->convertFromFirestoreFormat(['fields' => $value['mapValue']['fields']]);
        }
        return null;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function createGameSession(string $gameId, array $gameData): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $path = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            $firestoreData = $this->convertToFirestoreFormat($gameData);

            $response = $this->httpClient->patch($path, [
                'headers' => $this->getHeaders(),
                'json' => $firestoreData,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error('Failed to create game session: ' . $e->getMessage());
            return false;
        }
    }

    public function updateGameState(string $gameId, array $updates): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $path = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            $firestoreData = $this->convertToFirestoreFormat($updates);

            $updateMask = implode(',', array_keys($updates));
            $response = $this->httpClient->patch($path . '?updateMask.fieldPaths=' . urlencode($updateMask), [
                'headers' => $this->getHeaders(),
                'json' => $firestoreData,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error('Failed to update game state: ' . $e->getMessage());
            return false;
        }
    }

    public function getGameState(string $gameId): ?array
    {
        if (!$this->initialized) {
            return null;
        }

        try {
            $path = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            $response = $this->httpClient->get($path, [
                'headers' => $this->getHeaders(),
            ]);

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody()->getContents(), true);
                return $this->convertFromFirestoreFormat($body);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get game state: ' . $e->getMessage());
            return null;
        }
    }

    public function deleteGameSession(string $gameId): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $path = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}";
            $response = $this->httpClient->delete($path, [
                'headers' => $this->getHeaders(),
            ]);

            return in_array($response->getStatusCode(), [200, 204]);
        } catch (\Exception $e) {
            Log::error('Failed to delete game session: ' . $e->getMessage());
            return false;
        }
    }

    public function recordBuzz(string $gameId, int $playerId, float $timestamp): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $buzzId = 'buzz_' . $playerId . '_' . microtime(true);
            $path = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}/buzzes/{$buzzId}";
            
            $buzzData = [
                'player_id' => $playerId,
                'timestamp' => $timestamp,
                'server_timestamp' => microtime(true),
            ];

            $firestoreData = $this->convertToFirestoreFormat($buzzData);

            $response = $this->httpClient->patch($path, [
                'headers' => $this->getHeaders(),
                'json' => $firestoreData,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error('Failed to record buzz: ' . $e->getMessage());
            return false;
        }
    }

    public function getBuzzes(string $gameId): array
    {
        if (!$this->initialized) {
            return [];
        }

        try {
            $path = "projects/{$this->projectId}/databases/(default)/documents/games/{$gameId}/buzzes";
            $response = $this->httpClient->get($path, [
                'headers' => $this->getHeaders(),
            ]);

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody()->getContents(), true);
                
                if (!isset($body['documents'])) {
                    return [];
                }

                $buzzes = [];
                foreach ($body['documents'] as $doc) {
                    $buzzData = $this->convertFromFirestoreFormat($doc);
                    $buzzData['id'] = basename($doc['name']);
                    $buzzes[] = $buzzData;
                }

                usort($buzzes, function($a, $b) {
                    return ($a['server_timestamp'] ?? 0) <=> ($b['server_timestamp'] ?? 0);
                });

                return $buzzes;
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get buzzes: ' . $e->getMessage());
            return [];
        }
    }
}
