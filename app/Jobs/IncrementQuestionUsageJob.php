<?php

namespace App\Jobs;

use App\Models\QuestionGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async marker that bumps usage_count and last_used_at for one or more
 * QuestionGroups after they have been served. Kept off the gameplay critical
 * path so a slow DB write never delays a match.
 */
class IncrementQuestionUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int> */
    public array $groupIds;

    /**
     * @param  array<int>  $groupIds
     */
    public function __construct(array $groupIds)
    {
        $this->groupIds = array_values(array_unique(array_map('intval', $groupIds)));
    }

    public function handle(): void
    {
        if (empty($this->groupIds)) {
            return;
        }

        try {
            QuestionGroup::whereIn('id', $this->groupIds)
                ->update([
                    'usage_count' => \DB::raw('usage_count + 1'),
                    'last_used_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('[IncrementQuestionUsageJob] failed to mark usage', [
                'group_ids' => $this->groupIds,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
