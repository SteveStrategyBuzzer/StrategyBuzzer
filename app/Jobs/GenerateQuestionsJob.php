<?php

namespace App\Jobs;

use App\Services\QuestionCacheService;
use App\Services\QuestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $maxExceptions = 2;

    protected string $theme;
    protected int $niveau;
    protected string $language;
    protected int $count;
    protected array $usedQuestionIds;
    protected array $usedAnswers;

    public function __construct(
        string $theme,
        int $niveau,
        string $language,
        int $count = 10,
        array $usedQuestionIds = [],
        array $usedAnswers = []
    ) {
        $this->theme = $theme;
        $this->niveau = $niveau;
        $this->language = $language;
        $this->count = $count;
        $this->usedQuestionIds = $usedQuestionIds;
        $this->usedAnswers = $usedAnswers;
    }

    public function handle(): void
    {
        Log::info('[GenerateQuestionsJob] Starting question generation', [
            'theme' => $this->theme,
            'niveau' => $this->niveau,
            'language' => $this->language,
            'count' => $this->count
        ]);

        $questionService = new QuestionService();
        $cacheService = new QuestionCacheService();
        $questions = [];

        for ($i = 0; $i < $this->count; $i++) {
            try {
                $question = $questionService->generateQuestion(
                    $this->theme,
                    $this->niveau,
                    $i + 1,
                    $this->usedQuestionIds,
                    [],
                    $this->usedAnswers,
                    [],
                    null,
                    false,
                    $this->language
                );

                if ($question && isset($question['id'])) {
                    $questions[] = $question;
                    $this->usedQuestionIds[] = $question['id'];
                    
                    if (isset($question['answers'][$question['correct_index']])) {
                        $this->usedAnswers[] = $question['answers'][$question['correct_index']];
                    }
                }

                usleep(100000);
            } catch (\Exception $e) {
                Log::error('[GenerateQuestionsJob] Failed to generate question', [
                    'index' => $i,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (!empty($questions)) {
            $cacheService->addQuestions($this->theme, $this->niveau, $this->language, $questions);
        }

        Log::info('[GenerateQuestionsJob] Completed', [
            'generated' => count($questions),
            'requested' => $this->count
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[GenerateQuestionsJob] Job failed', [
            'theme' => $this->theme,
            'niveau' => $this->niveau,
            'language' => $this->language,
            'error' => $exception->getMessage()
        ]);
    }
}
