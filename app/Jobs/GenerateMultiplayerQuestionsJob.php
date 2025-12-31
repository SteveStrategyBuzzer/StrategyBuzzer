<?php

namespace App\Jobs;

use App\Services\QuestionService;
use App\Services\DuoFirestoreService;
use App\Services\LeagueIndividualFirestoreService;
use App\Services\LeagueTeamFirestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMultiplayerQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public int $maxExceptions = 2;

    protected string $matchId;
    protected string $mode;
    protected string $theme;
    protected int $niveau;
    protected string $language;
    protected int $totalQuestions;
    protected int $startIndex;
    protected int $blocSize;
    protected array $usedQuestionIds;
    protected array $usedAnswers;

    public function __construct(
        string $matchId,
        string $mode,
        string $theme,
        int $niveau,
        string $language,
        int $totalQuestions,
        int $startIndex,
        int $blocSize = 4,
        array $usedQuestionIds = [],
        array $usedAnswers = []
    ) {
        $this->matchId = $matchId;
        $this->mode = $mode;
        $this->theme = $theme;
        $this->niveau = $niveau;
        $this->language = $language;
        $this->totalQuestions = $totalQuestions;
        $this->startIndex = $startIndex;
        $this->blocSize = $blocSize;
        $this->usedQuestionIds = $usedQuestionIds;
        $this->usedAnswers = $usedAnswers;
    }

    public function handle(): void
    {
        Log::info('[GenerateMultiplayerQuestionsJob] Starting bloc generation', [
            'match_id' => $this->matchId,
            'mode' => $this->mode,
            'theme' => $this->theme,
            'niveau' => $this->niveau,
            'language' => $this->language,
            'total_questions' => $this->totalQuestions,
            'start_index' => $this->startIndex,
            'bloc_size' => $this->blocSize,
        ]);

        $questionService = new QuestionService();
        $firestoreService = $this->getFirestoreService();
        
        $endIndex = min($this->startIndex + $this->blocSize - 1, $this->totalQuestions);
        $generatedCount = 0;
        $usedQuestionIds = $this->usedQuestionIds;
        $usedAnswers = $this->usedAnswers;
        $sessionUsedQuestionTexts = [];

        for ($questionNumber = $this->startIndex; $questionNumber <= $endIndex; $questionNumber++) {
            try {
                $generatedQuestion = $questionService->generateQuestion(
                    $this->theme,
                    $this->niveau,
                    $questionNumber,
                    $usedQuestionIds,
                    $usedAnswers,
                    [],
                    $sessionUsedQuestionTexts,
                    null,
                    false,
                    $this->language,
                    true
                );

                if ($generatedQuestion) {
                    $question = [
                        'id' => $generatedQuestion['id'] ?? uniqid('mpq_'),
                        'text' => $generatedQuestion['question_text'] ?? $generatedQuestion['text'] ?? '',
                        'answers' => $generatedQuestion['answers'] ?? [],
                        'correct_index' => $generatedQuestion['correct_id'] ?? $generatedQuestion['correct_index'] ?? 0,
                        'sub_theme' => $generatedQuestion['sub_theme'] ?? '',
                    ];

                    $stored = $firestoreService->storePreGeneratedQuestion(
                        $this->matchId,
                        $questionNumber,
                        $question
                    );

                    if ($stored) {
                        $generatedCount++;
                        $usedQuestionIds[] = $question['id'];
                        $sessionUsedQuestionTexts[] = $question['text'];
                        
                        foreach ($question['answers'] as $answer) {
                            $answerText = is_array($answer) ? ($answer['text'] ?? '') : $answer;
                            if ($answerText) {
                                $usedAnswers[] = $answerText;
                            }
                        }
                        
                        Log::info("[GenerateMultiplayerQuestionsJob] Stored question {$questionNumber} for match {$this->matchId}");
                    }
                }

                usleep(100000);
            } catch (\Exception $e) {
                Log::error('[GenerateMultiplayerQuestionsJob] Failed to generate question', [
                    'match_id' => $this->matchId,
                    'question_number' => $questionNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[GenerateMultiplayerQuestionsJob] Bloc completed', [
            'match_id' => $this->matchId,
            'generated' => $generatedCount,
            'start_index' => $this->startIndex,
            'end_index' => $endIndex,
        ]);

        $nextStartIndex = $endIndex + 1;
        if ($nextStartIndex <= $this->totalQuestions) {
            Log::info('[GenerateMultiplayerQuestionsJob] Dispatching next bloc', [
                'match_id' => $this->matchId,
                'next_start_index' => $nextStartIndex,
            ]);

            self::dispatch(
                $this->matchId,
                $this->mode,
                $this->theme,
                $this->niveau,
                $this->language,
                $this->totalQuestions,
                $nextStartIndex,
                $this->blocSize,
                $usedQuestionIds,
                $usedAnswers
            );
        } else {
            Log::info('[GenerateMultiplayerQuestionsJob] All questions generated for match', [
                'match_id' => $this->matchId,
                'total_questions' => $this->totalQuestions,
            ]);

            $firestoreService->updateGameState($this->matchId, [
                'allQuestionsPreGenerated' => true,
                'preGeneratedCount' => $this->totalQuestions,
            ]);
        }
    }

    protected function getFirestoreService()
    {
        switch ($this->mode) {
            case 'league_individual':
                return new LeagueIndividualFirestoreService();
            case 'league_team':
                return new LeagueTeamFirestoreService();
            case 'duo':
            default:
                return new DuoFirestoreService();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[GenerateMultiplayerQuestionsJob] Job failed', [
            'match_id' => $this->matchId,
            'mode' => $this->mode,
            'theme' => $this->theme,
            'start_index' => $this->startIndex,
            'error' => $exception->getMessage(),
        ]);
    }
}
