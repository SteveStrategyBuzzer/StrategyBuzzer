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

    public function sendQuestions(string $roomId, array $questions): array
    {
        try {
            $formattedQuestions = $this->formatQuestionsForGameServer($questions);
            
            Log::info('GameServerService: Sending questions to Game Server', [
                'roomId' => $roomId,
                'questionCount' => count($formattedQuestions),
            ]);

            $response = Http::timeout(30)->post("{$this->gameServerUrl}/rooms/{$roomId}/questions", [
                'questions' => $formattedQuestions,
            ]);

            if ($response->successful()) {
                Log::info('GameServerService: Questions sent successfully', [
                    'roomId' => $roomId,
                    'questionCount' => count($formattedQuestions),
                ]);
                
                return [
                    'success' => true,
                    'questionCount' => count($formattedQuestions),
                ];
            }

            Log::error('GameServerService: Failed to send questions', [
                'roomId' => $roomId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to send questions to game server',
            ];
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception sending questions', [
                'roomId' => $roomId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function startGame(string $roomId, ?string $hostId = null): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->gameServerUrl}/rooms/{$roomId}/start", [
                'hostId' => $hostId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('GameServerService: Game started successfully', [
                    'roomId' => $roomId,
                ]);
                
                return [
                    'success' => true,
                    'state' => $data['state'] ?? null,
                ];
            }

            $errorBody = $response->json();
            Log::error('GameServerService: Failed to start game', [
                'roomId' => $roomId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $errorBody['error'] ?? 'Failed to start game on game server',
            ];
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception starting game', [
                'roomId' => $roomId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function formatQuestionsForGameServer(array $questions): array
    {
        return array_map(function ($q, $index) {
            $choices = [];
            $answers = $q['answers'] ?? [];
            
            foreach ($answers as $i => $answer) {
                $answerText = is_array($answer) ? ($answer['text'] ?? $answer['label'] ?? '') : $answer;
                $choices[] = $answerText;
            }
            
            return [
                'id' => $q['id'] ?? 'q_' . ($index + 1),
                'type' => 'MCQ',
                'text' => $q['text'] ?? $q['question_text'] ?? '',
                'choices' => $choices,
                'correctIndex' => (int) ($q['correct_index'] ?? $q['correct_id'] ?? 0),
                'category' => $q['theme'] ?? $q['category'] ?? 'General',
                'subCategory' => $q['sub_theme'] ?? $q['subCategory'] ?? '',
                'difficulty' => (int) ($q['difficulty'] ?? $q['niveau'] ?? 1),
                'timeLimitMs' => 8000,
                'funFact' => $q['fun_fact'] ?? $q['funFact'] ?? null,
            ];
        }, $questions, array_keys($questions));
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
