<?php

namespace App\Http\Controllers;

use App\Models\MatchSnapshot;
use App\Services\GameServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InternalMatchController extends Controller
{
    public function __construct(private GameServerService $gameServerService) {}

    /**
     * POST /internal/match/snapshot
     * Server-to-server endpoint: Node game server periodically checkpoints
     * mid-match state so it can be recovered if Redis is lost.
     *
     * Auth: short-lived JWT (same secret / purpose='internal_finalize').
     * Body: { roomId, mode, roundNumber, playerScores, roundsWon, playerStats }
     * Idempotent — upserts by match_id.
     */
    public function storeSnapshot(Request $request)
    {
        $authHeader = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
            return response()->json(['success' => false, 'error' => 'Missing bearer token'], 401);
        }
        $token = $m[1];

        try {
            $secret  = $this->gameServerService->getJwtSecret();
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            Log::warning('InternalMatchController::storeSnapshot JWT verify failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Invalid token'], 401);
        }

        $purpose = $decoded->purpose ?? null;
        if ($purpose !== 'internal_finalize') {
            return response()->json(['success' => false, 'error' => 'Invalid token purpose'], 401);
        }

        $roomId = (string) $request->input('roomId', '');
        if ($roomId === '') {
            return response()->json(['success' => false, 'error' => 'roomId required'], 400);
        }

        $roundNumber  = (int) $request->input('roundNumber', 0);
        $mode         = (string) $request->input('mode', 'DUO');
        $playerScores = $request->input('playerScores', []);
        $roundsWon    = $request->input('roundsWon', []);
        $playerStats  = $request->input('playerStats', []);

        try {
            MatchSnapshot::updateOrCreate(
                ['match_id' => $roomId],
                [
                    'mode'           => $mode,
                    'round_number'   => $roundNumber,
                    'player_scores'  => $playerScores,
                    'rounds_won'     => $roundsWon,
                    'player_stats'   => $playerStats,
                    'snapshotted_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('InternalMatchController::storeSnapshot DB error', [
                'roomId' => $roomId,
                'error'  => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Storage error'], 500);
        }

        return response()->json(['success' => true]);
    }
}
