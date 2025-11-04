<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;

class FirebaseService
{
    private static ?FirebaseService $instance = null;
    private $firestore = null;
    private $database = null;
    private bool $initialized = false;

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
            $projectId = env('FIREBASE_PROJECT_ID');
            $credentialsPath = env('FIREBASE_CREDENTIALS_PATH');

            if (empty($projectId)) {
                \Log::warning('FIREBASE_PROJECT_ID not configured');
                return;
            }

            if (empty($credentialsPath) || !file_exists(base_path($credentialsPath))) {
                \Log::warning('Firebase credentials file not found at: ' . $credentialsPath);
                return;
            }

            $factory = (new Factory)
                ->withServiceAccount(base_path($credentialsPath))
                ->withProjectId($projectId);

            $this->firestore = $factory->createFirestore();
            $this->database = $factory->createFirestore()->database();
            $this->initialized = true;

            \Log::info('Firebase initialized successfully with project: ' . $projectId);
        } catch (\Exception $e) {
            \Log::error('Firebase initialization failed: ' . $e->getMessage());
            $this->initialized = false;
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function getFirestore()
    {
        if (!$this->initialized) {
            \Log::warning('Firestore requested but Firebase not initialized');
            return null;
        }
        return $this->firestore;
    }

    public function getDatabase()
    {
        if (!$this->initialized) {
            \Log::warning('Firebase Database requested but not initialized');
            return null;
        }
        return $this->database;
    }

    public function createGameSession(string $gameId, array $gameData): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $this->database
                ->collection('games')
                ->document($gameId)
                ->set($gameData);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to create game session: ' . $e->getMessage());
            return false;
        }
    }

    public function updateGameState(string $gameId, array $updates): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $this->database
                ->collection('games')
                ->document($gameId)
                ->update($updates);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to update game state: ' . $e->getMessage());
            return false;
        }
    }

    public function getGameState(string $gameId): ?array
    {
        if (!$this->initialized) {
            return null;
        }

        try {
            $snapshot = $this->database
                ->collection('games')
                ->document($gameId)
                ->snapshot();
            
            return $snapshot->exists() ? $snapshot->data() : null;
        } catch (\Exception $e) {
            \Log::error('Failed to get game state: ' . $e->getMessage());
            return null;
        }
    }

    public function deleteGameSession(string $gameId): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $this->database
                ->collection('games')
                ->document($gameId)
                ->delete();
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete game session: ' . $e->getMessage());
            return false;
        }
    }

    public function recordBuzz(string $gameId, int $playerId, float $timestamp): bool
    {
        if (!$this->initialized) {
            return false;
        }

        try {
            $buzzData = [
                'player_id' => $playerId,
                'timestamp' => $timestamp,
                'server_timestamp' => microtime(true),
            ];

            $this->database
                ->collection('games')
                ->document($gameId)
                ->collection('buzzes')
                ->add($buzzData);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to record buzz: ' . $e->getMessage());
            return false;
        }
    }

    public function getBuzzes(string $gameId): array
    {
        if (!$this->initialized) {
            return [];
        }

        try {
            $snapshots = $this->database
                ->collection('games')
                ->document($gameId)
                ->collection('buzzes')
                ->orderBy('server_timestamp')
                ->documents();
            
            $buzzes = [];
            foreach ($snapshots as $snapshot) {
                $buzzes[] = array_merge(
                    ['id' => $snapshot->id()],
                    $snapshot->data()
                );
            }
            
            return $buzzes;
        } catch (\Exception $e) {
            \Log::error('Failed to get buzzes: ' . $e->getMessage());
            return [];
        }
    }
}
