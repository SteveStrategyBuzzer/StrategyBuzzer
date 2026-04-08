<?php

namespace App\Console\Commands;

use App\Models\DuoMatch;
use App\Services\DuoMatchmakingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RecoverStuckMatches extends Command
{
    protected $signature   = 'matches:recover {--dry-run : Simulate without writing changes}';
    protected $description = 'Finalize or abandon Duo matches stuck in "playing" state for over 30 minutes';

    public function __construct(private readonly DuoMatchmakingService $matchmaking)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $cutoff  = now()->subMinutes(30);

        $stuck = DuoMatch::where('status', 'playing')
            ->where('updated_at', '<', $cutoff)
            ->with(['player1', 'player2'])
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck matches found.');
            return self::SUCCESS;
        }

        $this->info("Found {$stuck->count()} stuck match(es).");

        foreach ($stuck as $match) {
            $this->processMatch($match, $dryRun);
        }

        return self::SUCCESS;
    }

    private function processMatch(DuoMatch $match, bool $dryRun): void
    {
        $roomId = $match->room_id;
        $label  = "Match #{$match->id} (room: {$roomId})";

        // --- Try to recover from Redis result ---
        $raw = $roomId ? Redis::connection('game_server')->get("gs:match:{$roomId}:result") : null;

        if ($raw) {
            $result      = json_decode($raw, true);
            $winnerId    = $result['winnerId']    ?? null;
            $finalScores = $result['finalScores'] ?? [];
            $isTie       = $result['isTie']       ?? false;
            $decidedBy   = $result['decidedBy']   ?? ($isTie ? 'total_score' : 'rounds');
            $roundsWon   = $result['roundsWon']   ?? [];
            $duration    = $result['duration']    ?? 0;

            $p1Id = (string) $match->player1_id;
            $p2Id = (string) $match->player2_id;

            if (isset($finalScores[$p1Id]) && isset($finalScores[$p2Id]) && $finalScores[$p1Id] !== $finalScores[$p2Id]) {
                $p1Score = $finalScores[$p1Id];
                $p2Score = $finalScores[$p2Id];
            } elseif ($winnerId) {
                $p1Score = (string) $winnerId === $p1Id ? 1 : 0;
                $p2Score = (string) $winnerId === $p1Id ? 0 : 1;
            } else {
                $p1Score = 0;
                $p2Score = 0;
            }

            $enrichedGameState = [
                'source'         => 'recovery',
                'mode'           => 'duo',
                'final_scores'   => $finalScores,
                'rounds_won'     => $roundsWon,
                'duration_ms'    => $duration,
                'is_tie'         => $isTie,
                'decided_by'     => $decidedBy,
                'player1_rounds' => (int) ($roundsWon[$p1Id] ?? 0),
                'player2_rounds' => (int) ($roundsWon[$p2Id] ?? 0),
            ];

            $this->info("{$label} — Redis result found. Finalizing (winner: {$winnerId}).");

            if (!$dryRun) {
                try {
                    $this->matchmaking->finishMatch($match, $p1Score, $p2Score, $enrichedGameState, $isTie);
                    Log::info("[RecoverStuckMatches] Finalized match #{$match->id} from Redis result.");
                } catch (\Throwable $e) {
                    $this->error("{$label} — finishMatch failed: {$e->getMessage()}");
                    Log::error("[RecoverStuckMatches] finishMatch failed for match #{$match->id}: {$e->getMessage()}");
                }
            }

            return;
        }

        // --- No Redis result — match is truly lost, mark abandoned ---
        $stuckMinutes = now()->diffInMinutes($match->updated_at);
        $this->warn("{$label} — No Redis result. Stuck {$stuckMinutes} min. Marking abandoned.");

        if (!$dryRun) {
            try {
                $match->update([
                    'status'     => 'abandoned',
                    'game_state' => array_merge($match->game_state ?? [], [
                        'abandoned_at'  => now()->toISOString(),
                        'abandon_reason' => 'no_redis_result_after_30min',
                    ]),
                ]);
                Log::warning("[RecoverStuckMatches] Match #{$match->id} marked abandoned (no Redis result).");
            } catch (\Throwable $e) {
                $this->error("{$label} — Could not mark abandoned: {$e->getMessage()}");
                Log::error("[RecoverStuckMatches] abandon failed for match #{$match->id}: {$e->getMessage()}");
            }
        }
    }
}
