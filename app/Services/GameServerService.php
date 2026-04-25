<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use App\Models\User;

class GameServerService
{
    private string $gameServerUrl;
    private string $publicSocketUrl;
    private string $jwtSecret;
    private int $playerTokenTtlSeconds;

    public function __construct()
    {
        $this->gameServerUrl = 'http://127.0.0.1:3001';
        $this->publicSocketUrl = 'https://strategybuzzer.com';
        $this->jwtSecret = config('app.game_server_jwt_secret');
        $this->playerTokenTtlSeconds = 4 * 60 * 60;

        if (!$this->jwtSecret || strlen(trim($this->jwtSecret)) < 16) {
            throw new \RuntimeException("Missing or weak GAME_SERVER_JWT_SECRET (mirror strict Replit)");
        }
    }

    public function createRoom(string $mode, int $hostPlayerId, array $config = []): array
    {
        try {
            $normalizedMode = strtoupper($mode);

            $theme = $config['theme']
                ?? $config['customConfig']['theme']
                ?? 'general';

            $niveau = (int) (
                $config['niveau']
                ?? $config['customConfig']['niveau']
                ?? 5
            );

            $language = $config['language']
                ?? $config['customConfig']['language']
                ?? 'fr';

            $customConfig = $config['customConfig'] ?? [];

            if (!isset($customConfig['maxRounds'])) {
                $customConfig['maxRounds'] = (int) ($config['maxRounds'] ?? 3);
            }

            if (!isset($customConfig['questionsPerRound']) && isset($config['nb_questions'])) {
                $customConfig['questionsPerRound'] = (int) $config['nb_questions'];
            }

            if (!isset($customConfig['roundsToWin']) && isset($config['roundsToWin'])) {
                $customConfig['roundsToWin'] = (int) $config['roundsToWin'];
            }

            if (!isset($customConfig['maxPlayers']) && isset($config['playerCount'])) {
                $customConfig['maxPlayers'] = (int) $config['playerCount'];
            }

            $payload = [
                'mode' => $normalizedMode,
                'hostId' => (string) $hostPlayerId,
                'lobbyCode' => $config['lobby_code'] ?? null,
                'theme' => $theme,
                'niveau' => $niveau,
                'language' => $language,
                'customConfig' => $customConfig,
            ];

            Log::info('GameServerService: Creating room', [
                'url' => "{$this->gameServerUrl}/rooms",
                'payload' => $payload,
            ]);

            $response = Http::timeout(10)->post("{$this->gameServerUrl}/rooms", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('GameServerService: Room created successfully', [
                    'roomId' => $data['roomId'] ?? null,
                    'lobbyCode' => $data['lobbyCode'] ?? null,
                ]);

                return $data;
            }

            Log::error('GameServerService: Failed to create room', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
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

            $avatarId = $settings['avatar']['url'] ?? $settings['avatar']['id'] ?? null;
        }

        $issuedAt = time();

        $payload = [
            'playerId' => $playerId,
            'playerName' => $playerName,
            'avatarId' => $avatarId,
            'roomId' => $roomId,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->playerTokenTtlSeconds,
        ];

        $secret = $this->getJwtSecret();

        Log::info('GameServerService: Generating player token', [
            'playerId' => $playerId,
            'roomId' => $roomId,
            'ttl_seconds' => $this->playerTokenTtlSeconds,
            'expires_at' => $payload['exp'],
        ]);

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

    public function appendQuestions(string $roomId, array $questions): array
    {
        try {
            $formattedQuestions = $this->formatQuestionsForGameServer($questions);

            Log::info('GameServerService: Appending questions to Game Server', [
                'roomId' => $roomId,
                'questionCount' => count($formattedQuestions),
            ]);

            $response = Http::timeout(30)->post("{$this->gameServerUrl}/rooms/{$roomId}/questions/append", [
                'questions' => $formattedQuestions,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('GameServerService: Questions appended successfully', [
                    'roomId' => $roomId,
                    'appendedCount' => count($formattedQuestions),
                    'totalCount' => $data['totalCount'] ?? 0,
                ]);

                return [
                    'success' => true,
                    'appendedCount' => count($formattedQuestions),
                    'totalCount' => $data['totalCount'] ?? 0,
                ];
            }

            Log::error('GameServerService: Failed to append questions', [
                'roomId' => $roomId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to append questions to game server',
            ];
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception appending questions', [
                'roomId' => $roomId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function syncPlayerColor(string $roomId, string $playerId, string $color): array
    {
        try {
            Log::info('GameServerService: Syncing player color', [
                'roomId' => $roomId,
                'playerId' => $playerId,
                'color' => $color,
            ]);

            $response = Http::timeout(10)->post("{$this->gameServerUrl}/rooms/{$roomId}/player-color", [
                'playerId' => $playerId,
                'color' => $color,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('GameServerService: Failed to sync player color', [
                'roomId' => $roomId,
                'playerId' => $playerId,
                'color' => $color,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to sync player color to game server',
            ];
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception syncing player color', [
                'roomId' => $roomId,
                'playerId' => $playerId,
                'color' => $color,
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
            $payload = [
                'hostId' => $hostId,
            ];

            Log::info('GameServerService: Starting game', [
                'roomId' => $roomId,
                'payload' => $payload,
            ]);

            $response = Http::timeout(10)->post("{$this->gameServerUrl}/rooms/{$roomId}/start", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('GameServerService: Game started successfully', [
                    'roomId' => $roomId,
                    'phase' => $data['state']['phase'] ?? null,
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

            foreach ($answers as $answer) {
                $answerText = is_array($answer)
                    ? ($answer['text'] ?? $answer['label'] ?? '')
                    : $answer;

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

    public function createRoomAndGenerateTokens(string $mode, int $hostPlayerId, array $playerIds, array $config = []): array
    {
        try {
            $roomResult = $this->createRoom($mode, $hostPlayerId, $config);

            $roomId = $roomResult['roomId'] ?? $roomResult['room_id'] ?? null;
            if (!$roomId) {
                Log::error('GameServerService: Room creation failed, cannot generate tokens', [
                    'mode' => $mode,
                    'hostPlayerId' => $hostPlayerId,
                    'roomResult' => $roomResult,
                ]);

                return [
                    'success' => false,
                    'error' => $roomResult['error'] ?? 'Failed to create room',
                ];
            }

            $tokens = [];
            foreach ($playerIds as $playerId) {
                $tokens[$playerId] = $this->generatePlayerToken((int) $playerId, $roomId);
            }

            Log::info('GameServerService: Room created with pre-generated tokens', [
                'roomId' => $roomId,
                'mode' => $mode,
                'tokenCount' => count($tokens),
                'playerIds' => $playerIds,
                'ttl_seconds' => $this->playerTokenTtlSeconds,
            ]);

            return [
                'success' => true,
                'roomId' => $roomId,
                'lobbyCode' => $roomResult['lobbyCode'] ?? null,
                'player_tokens' => $tokens,
            ];
        } catch (\Exception $e) {
            Log::error('GameServerService: Exception in createRoomAndGenerateTokens', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSocketUrl(): string
    {
       return $this->publicSocketUrl;
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
