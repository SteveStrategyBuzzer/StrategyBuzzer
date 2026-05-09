<?php

namespace App\Http\Controllers;

use App\Models\QuestionGroup;
use App\Services\GameServerService;
use App\Services\PlayerMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PlayerMemoryController extends Controller
{
    public function __construct(
        private readonly GameServerService  $gameServerService,
        private readonly PlayerMemoryService $memory,
    ) {}

    /**
     * POST /internal/player-memory/record
     *
     * Server-to-server endpoint: Node game server notifies Laravel that a
     * match has ended so per-player question-diversity memory can be updated.
     *
     * Auth  : short-lived JWT (same secret / purpose='internal_finalize').
     * Body  : { roomId: string, mode: string }
     * Return: always 200 — fail-open, never blocks gameplay.
     *
     * Laravel resolves player user_ids and group_ids from its own data:
     *   - gs_room_users:{roomId} → [user_id, ...]  (written by LobbyService::startGame)
     *   - game_server_match:{roomId}:config         → ordered_group_ids
     */
    public function record(Request $request): JsonResponse
    {
        // ── JWT auth (identical to InternalMatchController) ──────────────────
        $authHeader = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
            return response()->json(['success' => false, 'error' => 'Missing bearer token'], 401);
        }

        try {
            $secret  = $this->gameServerService->getJwtSecret();
            $decoded = \Firebase\JWT\JWT::decode($m[1], new \Firebase\JWT\Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            Log::warning('[PlayerMemory] JWT verify failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Invalid token'], 401);
        }

        if (($decoded->purpose ?? null) !== 'internal_finalize') {
            return response()->json(['success' => false, 'error' => 'Invalid token purpose'], 401);
        }

        // ── Input ─────────────────────────────────────────────────────────────
        $roomId = (string) $request->input('roomId', '');
        if ($roomId === '') {
            return response()->json(['success' => false, 'error' => 'roomId required'], 400);
        }

        $rawMode = strtolower((string) $request->input('mode', 'duo'));
        // Normalise Node mode strings → PlayerMemoryService keys
        $mode = match (true) {
            str_starts_with($rawMode, 'league') => 'league',
            $rawMode === 'master'               => 'master',
            $rawMode === 'duo'                  => 'duo',
            default                             => 'duo',
        };

        // ── Fail-open: everything below silently skips on any error ───────────
        try {
            // 1. Resolve user_ids from the room index written at game start
            $rawUserIds = Redis::get("gs_room_users:{$roomId}");
            if (!$rawUserIds) {
                Log::warning('[PlayerMemory] gs_room_users missing — skipping', ['roomId' => $roomId]);
                return response()->json(['success' => true, 'skipped' => 'no_room_users']);
            }

            $userIds = array_filter(
                (array) json_decode($rawUserIds, true),
                fn ($uid) => is_numeric($uid) && (int) $uid > 0
            );
            if (empty($userIds)) {
                return response()->json(['success' => true, 'skipped' => 'empty_user_ids']);
            }

            // 2. Resolve ordered_group_ids from the pipeline cache (TTL 24h)
            $config   = Cache::get("game_server_match:{$roomId}:config");
            $groupIds = array_filter(
                (array) ($config['ordered_group_ids'] ?? []),
                fn ($gid) => is_numeric($gid) && (int) $gid > 0
            );
            if (empty($groupIds)) {
                Log::warning('[PlayerMemory] pipeline config cache miss — skipping', ['roomId' => $roomId]);
                return response()->json(['success' => true, 'skipped' => 'no_group_ids']);
            }

            // 3. Batch-load concept_family for each group (single query)
            $families = QuestionGroup::whereIn('id', $groupIds)
                ->pluck('concept_family', 'id')
                ->toArray();

            // 4. Write memory for every (user, group) pair
            $written = 0;
            foreach ($userIds as $userId) {
                $uid = (int) $userId;
                foreach ($groupIds as $groupId) {
                    $gid    = (int) $groupId;
                    $family = $families[$gid] ?? null;
                    $this->memory->recordGroupSeen($uid, $gid, $family, $mode);
                    $written++;
                }
            }

            Log::info('[PlayerMemory] recorded', [
                'roomId'  => $roomId,
                'mode'    => $mode,
                'users'   => count($userIds),
                'groups'  => count($groupIds),
                'written' => $written,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[PlayerMemory] record threw — skipping', [
                'roomId' => $roomId,
                'error'  => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
