<?php

namespace App\Http\Controllers;

use App\Models\MatchSnapshot;
use App\Services\GameServerService;
use App\Services\QuestionBank\Gameplay\KernelConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InternalMatchController extends Controller
{
    public function __construct(
        private GameServerService $gameServerService,
        private KernelConsumptionService $kernelConsumption,
    ) {}

    /**
     * Vérifie le JWT interne (même secret / purpose='internal_finalize').
     * Retourne une JsonResponse 401 en cas d'échec, sinon null.
     */
    private function verifyInternalJwt(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $authHeader = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
            return response()->json(['success' => false, 'error' => 'Missing bearer token'], 401);
        }

        try {
            $secret  = $this->gameServerService->getJwtSecret();
            $decoded = \Firebase\JWT\JWT::decode($m[1], new \Firebase\JWT\Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            Log::warning('InternalMatchController JWT verify failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Invalid token'], 401);
        }

        if (($decoded->purpose ?? null) !== 'internal_finalize') {
            return response()->json(['success' => false, 'error' => 'Invalid token purpose'], 401);
        }

        return null;
    }

    /**
     * POST /internal/match/consume
     * Server-to-server : Node signale la consommation cognitive d'un noyau
     * APRÈS exposition complète (question + bonne réponse + Saviez-Vous) à la
     * fin de la phase RESULT, pour chaque joueur ACTIF exposé.
     *
     * Auth : même JWT court (purpose='internal_finalize').
     * Body : { roomId, mode, kernelCode, depth, domain, cognitiveFamily,
     *          cognitiveForm, questionIntentId?, exposedUserIds[] }
     *   — variante : { variantKey } à la place de cognitiveFamily+cognitiveForm.
     * Idempotent — 1 ligne par (user, kernel, famille, forme), match_ref=roomId.
     *
     * NOTE (S1) : endpoint DORMANT — pas encore appelé par Node (branché en S4).
     */
    public function recordConsumption(Request $request)
    {
        if ($resp = $this->verifyInternalJwt($request)) {
            return $resp;
        }

        $roomId     = (string) $request->input('roomId', '');
        $kernelCode = (string) $request->input('kernelCode', '');
        $depth      = (int) $request->input('depth', 0);
        $domain     = (string) $request->input('domain', '');
        $mode       = (string) $request->input('mode', '');
        $intentId   = $request->input('questionIntentId');
        $userIds    = (array) $request->input('exposedUserIds', []);

        // Couple cognitif : variant_key OU (cognitiveFamily + cognitiveForm).
        $variantKey = (string) $request->input('variantKey', '');
        if ($variantKey !== '') {
            $pair = KernelConsumptionService::pairFromVariantKey($variantKey);
            if ($pair === null) {
                return response()->json(['success' => false, 'error' => "Unknown variantKey: {$variantKey}"], 422);
            }
            [$family, $form] = $pair;
        } else {
            $family = (string) $request->input('cognitiveFamily', '');
            $form   = (string) $request->input('cognitiveForm', '');
        }

        if ($roomId === '' || $kernelCode === '' || $domain === '' || $depth < 1) {
            return response()->json(['success' => false, 'error' => 'roomId, kernelCode, domain, depth required'], 400);
        }

        try {
            $result = $this->kernelConsumption->consume([
                'kernelCode'       => $kernelCode,
                'cognitiveFamily'  => $family,
                'cognitiveForm'    => $form,
                'depth'            => $depth,
                'domain'           => $domain,
                'matchRef'         => $roomId,
                'mode'             => $mode,
                'questionIntentId' => $intentId !== null ? (int) $intentId : null,
                'exposedUserIds'   => $userIds,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('InternalMatchController::recordConsumption DB error', [
                'roomId' => $roomId,
                'error'  => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Storage error'], 500);
        }

        return response()->json(['success' => true] + $result);
    }

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
