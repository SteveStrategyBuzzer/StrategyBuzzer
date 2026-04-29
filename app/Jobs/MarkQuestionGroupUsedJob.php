<?php

namespace App\Jobs;

use App\Services\QuestionBankRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job léger qui incrémente usage_count + last_used_at d'un question_group.
 * Mis sur la queue par MatchQuestionPlanner pour éviter une écriture
 * Postgres synchrone par question pendant la construction du plan.
 */
class MarkQuestionGroupUsedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    public function __construct(public int $groupId)
    {
    }

    public function handle(QuestionBankRepository $repo): void
    {
        try {
            $repo->markUsed($this->groupId);
        } catch (\Throwable $e) {
            Log::warning('[MarkQuestionGroupUsedJob] failed', [
                'group_id' => $this->groupId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
