<?php

namespace App\Jobs;

use App\Services\GameServerQuestionPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateGameServerQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public int $maxExceptions = 2;

    protected string $roomId;
    protected string $theme;
    protected int $niveau;
    protected string $language;
    protected int $totalNeeded;
    protected int $startIndex;
    protected int $blockSize;

    public function __construct(
        string $roomId,
        string $theme,
        int $niveau,
        string $language,
        int $totalNeeded,
        int $startIndex,
        int $blockSize = 4
    ) {
        $this->roomId = $roomId;
        $this->theme = $theme;
        $this->niveau = $niveau;
        $this->language = $language;
        $this->totalNeeded = $totalNeeded;
        $this->startIndex = $startIndex;
        $this->blockSize = $blockSize;
    }

    public function handle(): void
    {
        Log::info('[GenerateGameServerQuestionsJob] Starting block generation', [
            'room_id' => $this->roomId,
            'theme' => $this->theme,
            'niveau' => $this->niveau,
            'language' => $this->language,
            'total_needed' => $this->totalNeeded,
            'start_index' => $this->startIndex,
            'block_size' => $this->blockSize,
        ]);

        $pipeline = new GameServerQuestionPipeline();
        
        $config = $pipeline->getMatchConfig($this->roomId);
        if (!$config) {
            Log::warning('[GenerateGameServerQuestionsJob] Match config not found, match may have ended', [
                'room_id' => $this->roomId,
            ]);
            return;
        }

        $generatedCount = $pipeline->generateNextBlock($this->roomId, $this->blockSize);

        Log::info('[GenerateGameServerQuestionsJob] Block completed', [
            'room_id' => $this->roomId,
            'generated' => $generatedCount,
            'total_ready' => $pipeline->getQuestionCount($this->roomId),
        ]);

        if ($pipeline->shouldGenerateMore($this->roomId)) {
            $nextStartIndex = $this->startIndex + $this->blockSize;
            
            Log::info('[GenerateGameServerQuestionsJob] Dispatching next block', [
                'room_id' => $this->roomId,
                'next_start_index' => $nextStartIndex,
            ]);

            self::dispatch(
                $this->roomId,
                $this->theme,
                $this->niveau,
                $this->language,
                $this->totalNeeded,
                $nextStartIndex,
                $this->blockSize
            );
        } else {
            Log::info('[GenerateGameServerQuestionsJob] All questions generated for match', [
                'room_id' => $this->roomId,
                'total_questions' => $pipeline->getQuestionCount($this->roomId),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[GenerateGameServerQuestionsJob] Job failed', [
            'room_id' => $this->roomId,
            'theme' => $this->theme,
            'start_index' => $this->startIndex,
            'error' => $exception->getMessage(),
        ]);
    }
}
