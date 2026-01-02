<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use App\Models\User;

class GameServerService
{
    private string $gameServerUrl;
    private string $jwtSecret;

    public function __construct()
    {
        $this->gameServerUrl = env('GAME_SERVER_URL', 'http://localhost:3001');
        $this->jwtSecret = env('GAME_SERVER_JWT_SECRET', env('APP_KEY', ''));
    }

    public function createRoom(string $mode, int $hostPlayerId, array $config = []): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->gameServerUrl}/rooms", [
                'mode' => $mode,
                'hostPlayerId' => $hostPlayerId,
                'config' => $config,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('GameServerService: Failed to create room', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create room on game server',
            ];
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception creating room', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getRoom(string $roomId): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->gameServerUrl}/rooms/{$roomId}");

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 404) {
                return null;
            }

            Log::error('GameServerService: Failed to get room', [
                'roomId' => $roomId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception getting room', [
                'roomId' => $roomId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function generatePlayerToken(int $playerId, string $roomId): string
    {
        $user = User::find($playerId);
        
        $playerName = $user->name ?? $user->player_code ?? 'Player';
        $avatarId = null;
        
        if ($user && $user->profile_settings) {
            $settings = is_string($user->profile_settings) 
                ? json_decode($user->profile_settings, true) 
                : $user->profile_settings;
            $avatarId = $settings['avatar']['id'] ?? $settings['avatar']['url'] ?? null;
        }

        $payload = [
            'playerId' => $playerId,
            'playerName' => $playerName,
            'avatarId' => $avatarId,
            'roomId' => $roomId,
            'exp' => time() + (5 * 60),
            'iat' => time(),
        ];

        $secret = $this->getJwtSecret();
        
        return JWT::encode($payload, $secret, 'HS256');
    }

    public function startGame(string $roomId): bool
    {
        try {
            $response = Http::timeout(10)->post("{$this->gameServerUrl}/rooms/{$roomId}/start");

            if ($response->successful()) {
                return true;
            }

            Log::error('GameServerService: Failed to start game', [
                'roomId' => $roomId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception starting game', [
                'roomId' => $roomId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyMatchEnd(string $roomId, array $results): void
    {
        try {
            Log::info('GameServerService: Match ended', [
                'roomId' => $roomId,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('GameServerService: Exception handling match end', [
                'roomId' => $roomId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getSocketUrl(): string
    {
        return $this->gameServerUrl;
    }

    private function getJwtSecret(): string
    {
        $key = $this->jwtSecret;
        
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        
        return $key;
    }
}
