<?php

namespace App\Jobs;

use App\Models\QuestionGroup;
use App\Services\PlayerMemoryService;
use App\Services\QuestionBankRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job léger qui :
 *   1. Incrémente usage_count + last_used_at d'un question_group (existant).
 *   2. [Phase A] Enregistre le group_id dans la mémoire Redis cross-match
 *      du joueur (PlayerMemoryService) si user_id est fourni.
 *
 * Tous les chemins Phase A sont dans try/catch → fail-open : une erreur
 * Redis n'impacte jamais le gameplay.
 */
class MarkQuestionGroupUsedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    /**
     * @param int      $groupId  question_group.id (toujours requis)
     * @param int|null $userId   Laravel user.id — null = invité/anonyme → skip mémoire
     * @param string   $mode     Mode de jeu pour calibrer les TTL Redis
     */
    public function __construct(
        public int     $groupId,
        public ?int    $userId = null,
        public string  $mode   = 'unknown'
    ) {}

    public function handle(QuestionBankRepository $repo, PlayerMemoryService $memory): void
    {
        // --- 1. Usage global (inchangé) -------------------------------------
        try {
            $repo->markUsed($this->groupId);
        } catch (\Throwable $e) {
            Log::warning('[MarkQuestionGroupUsedJob] markUsed failed', [
                'group_id' => $this->groupId,
                'error'    => $e->getMessage(),
            ]);
        }

        // --- 2. Phase A — mémoire joueur Redis (fail-open) ------------------
        if ($this->userId === null) {
            return;
        }

        try {
            $conceptFamily = QuestionGroup::where('id', $this->groupId)
                ->value('concept_family');

            $memory->recordGroupSeen(
                $this->userId,
                $this->groupId,
                $conceptFamily ?: null,
                $this->mode
            );
        } catch (\Throwable $e) {
            Log::warning('[MarkQuestionGroupUsedJob] player memory write failed (fail-open)', [
                'user_id'  => $this->userId,
                'group_id' => $this->groupId,
                'mode'     => $this->mode,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
