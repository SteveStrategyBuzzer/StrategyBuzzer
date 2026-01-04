<?php

namespace App\Jobs;

use App\Services\QuestionService;
use App\Services\GameServerService;
use App\Services\QuestionPlanBuilder;
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
    public int $timeout = 300;
    public int $maxExceptions = 2;

    protected const MAX_GENERATION_RETRIES = 3;
    protected const MAX_APPEND_RETRIES = 3;
    protected const APPEND_BACKOFF_MS = [500, 1000, 2000];

    protected string $roomId;
    protected string $mode;
    protected string $theme;
    protected int $niveau;
    protected string $language;
    protected int $totalQuestions;
    protected int $startIndex;
    protected int $blocSize;
    protected array $usedQuestionIds;
    protected array $usedAnswers;
    protected array $usedQuestionTexts;
    protected bool $includeSkillBonus;
    protected bool $includeTiebreaker;
    protected int $mainQuestions;
    protected int $skillBonusQuestions;

    public function __construct(
        string $roomId,
        string $mode,
        string $theme,
        int $niveau,
        string $language,
        int $totalQuestions,
        int $startIndex,
        int $blocSize = 4,
        array $usedQuestionIds = [],
        array $usedAnswers = [],
        array $usedQuestionTexts = [],
        bool $includeSkillBonus = false,
        bool $includeTiebreaker = true,
        int $mainQuestions = 0,
        int $skillBonusQuestions = 0
    ) {
        $this->roomId = $roomId;
        $this->mode = $mode;
        $this->theme = $theme;
        $this->niveau = $niveau;
        $this->language = $language;
        $this->totalQuestions = $totalQuestions;
        $this->startIndex = $startIndex;
        $this->blocSize = $blocSize;
        $this->usedQuestionIds = $usedQuestionIds;
        $this->usedAnswers = $usedAnswers;
        $this->usedQuestionTexts = $usedQuestionTexts;
        $this->includeSkillBonus = $includeSkillBonus;
        $this->includeTiebreaker = $includeTiebreaker;
        $this->mainQuestions = $mainQuestions > 0 ? $mainQuestions : $totalQuestions;
        $this->skillBonusQuestions = $skillBonusQuestions;
    }

    public function handle(): void
    {
        Log::info('[GenerateMultiplayerQuestionsJob] Starting bloc generation', [
            'room_id' => $this->roomId,
            'mode' => $this->mode,
            'theme' => $this->theme,
            'niveau' => $this->niveau,
            'language' => $this->language,
            'total_questions' => $this->totalQuestions,
            'start_index' => $this->startIndex,
            'bloc_size' => $this->blocSize,
            'main_questions' => $this->mainQuestions,
            'skill_bonus_questions' => $this->skillBonusQuestions,
            'include_skill_bonus' => $this->includeSkillBonus,
            'include_tiebreaker' => $this->includeTiebreaker,
        ]);

        $questionService = new QuestionService();
        $gameServerService = app(GameServerService::class);
        
        $endIndex = min($this->startIndex + $this->blocSize - 1, $this->totalQuestions);
        $expectedCount = $endIndex - $this->startIndex + 1;
        $generatedQuestions = [];
        $usedQuestionIds = $this->usedQuestionIds;
        $usedAnswers = $this->usedAnswers;
        $usedQuestionTexts = $this->usedQuestionTexts;

        for ($questionNumber = $this->startIndex; $questionNumber <= $endIndex; $questionNumber++) {
            $questionType = QuestionPlanBuilder::getQuestionTypeByIndex(
                $questionNumber,
                $this->mainQuestions,
                $this->skillBonusQuestions
            );
            
            $metadata = QuestionPlanBuilder::getQuestionMetadataByType($questionType);
            
            if ($questionType === 'skill_bonus' && !$this->includeSkillBonus) {
                Log::info('[GenerateMultiplayerQuestionsJob] Skipping skill bonus question (disabled)', [
                    'question_number' => $questionNumber,
                ]);
                continue;
            }
            
            if ($questionType === 'tiebreaker' && !$this->includeTiebreaker) {
                Log::info('[GenerateMultiplayerQuestionsJob] Skipping tiebreaker question (disabled)', [
                    'question_number' => $questionNumber,
                ]);
                continue;
            }

            $question = $this->generateQuestionWithRetry(
                $questionService,
                $questionNumber,
                $questionType,
                $metadata,
                $usedQuestionIds,
                $usedAnswers,
                $usedQuestionTexts
            );
            
            if ($question) {
                $generatedQuestions[] = $question;
                $usedQuestionIds[] = $question['id'];
                $usedQuestionTexts[] = $question['text'];
                
                foreach ($question['answers'] as $answer) {
                    $answerText = is_array($answer) ? ($answer['text'] ?? '') : $answer;
                    if ($answerText) {
                        $usedAnswers[] = $answerText;
                    }
                }
                
                Log::info("[GenerateMultiplayerQuestionsJob] Generated question {$questionNumber}", [
                    'type' => $questionType,
                ]);
            }

            usleep(100000);
        }

        $deficit = $expectedCount - count($generatedQuestions);
        $extraAttempts = 0;
        $maxExtraAttempts = 10;
        
        while ($deficit > 0 && $extraAttempts < $maxExtraAttempts) {
            $extraAttempts++;
            
            Log::info('[GenerateMultiplayerQuestionsJob] Attempting to fill deficit', [
                'room_id' => $this->roomId,
                'deficit' => $deficit,
                'extra_attempt' => $extraAttempts,
                'max_extra_attempts' => $maxExtraAttempts,
            ]);
            
            $replacementQuestionNumber = $this->startIndex + count($generatedQuestions);
            $questionType = 'main';
            $metadata = QuestionPlanBuilder::getQuestionMetadataByType($questionType);
            
            $question = $this->generateQuestionWithRetry(
                $questionService,
                $replacementQuestionNumber,
                $questionType,
                $metadata,
                $usedQuestionIds,
                $usedAnswers,
                $usedQuestionTexts
            );
            
            if ($question) {
                $generatedQuestions[] = $question;
                $usedQuestionIds[] = $question['id'];
                $usedQuestionTexts[] = $question['text'];
                
                foreach ($question['answers'] as $answer) {
                    $answerText = is_array($answer) ? ($answer['text'] ?? '') : $answer;
                    if ($answerText) {
                        $usedAnswers[] = $answerText;
                    }
                }
                
                $deficit--;
                
                Log::info('[GenerateMultiplayerQuestionsJob] Replacement question generated', [
                    'room_id' => $this->roomId,
                    'question_number' => $replacementQuestionNumber,
                    'remaining_deficit' => $deficit,
                ]);
            }
            
            usleep(100000);
        }
        
        if ($deficit > 0) {
            Log::warning('[GenerateMultiplayerQuestionsJob] Deficit could not be fully filled - continuing with partial bloc', [
                'room_id' => $this->roomId,
                'expected' => $expectedCount,
                'generated' => count($generatedQuestions),
                'deficit' => $deficit,
                'extra_attempts' => $extraAttempts,
            ]);
        }

        $generatedCount = count($generatedQuestions);
        
        Log::info('[GenerateMultiplayerQuestionsJob] Bloc generation summary', [
            'room_id' => $this->roomId,
            'expected' => $expectedCount,
            'generated' => $generatedCount,
            'start_index' => $this->startIndex,
            'end_index' => $endIndex,
        ]);

        if ($generatedCount === 0) {
            Log::error('[GenerateMultiplayerQuestionsJob] No questions generated for bloc', [
                'room_id' => $this->roomId,
                'start_index' => $this->startIndex,
                'end_index' => $endIndex,
            ]);
            return;
        }

        $appendSuccess = $this->appendQuestionsWithRetry($gameServerService, $generatedQuestions);
        
        if (!$appendSuccess) {
            Log::error('[GenerateMultiplayerQuestionsJob] Failed to append questions after all retries - continuing to next bloc anyway', [
                'room_id' => $this->roomId,
                'bloc_count' => $generatedCount,
            ]);
        }

        Log::info('[GenerateMultiplayerQuestionsJob] Bloc completed successfully', [
            'room_id' => $this->roomId,
            'generated' => $generatedCount,
            'start_index' => $this->startIndex,
            'end_index' => $endIndex,
        ]);

        $nextStartIndex = $endIndex + 1;
        if ($nextStartIndex <= $this->totalQuestions) {
            Log::info('[GenerateMultiplayerQuestionsJob] Dispatching next bloc', [
                'room_id' => $this->roomId,
                'next_start_index' => $nextStartIndex,
            ]);

            self::dispatch(
                $this->roomId,
                $this->mode,
                $this->theme,
                $this->niveau,
                $this->language,
                $this->totalQuestions,
                $nextStartIndex,
                $this->blocSize,
                $usedQuestionIds,
                $usedAnswers,
                $usedQuestionTexts,
                $this->includeSkillBonus,
                $this->includeTiebreaker,
                $this->mainQuestions,
                $this->skillBonusQuestions
            );
        } else {
            Log::info('[GenerateMultiplayerQuestionsJob] All questions generated for room', [
                'room_id' => $this->roomId,
                'total_questions' => $this->totalQuestions,
            ]);
        }
    }

    protected function generateQuestionWithRetry(
        QuestionService $questionService,
        int $questionNumber,
        string $questionType,
        array $metadata,
        array $usedQuestionIds,
        array $usedAnswers,
        array $usedQuestionTexts
    ): ?array {
        $adjustedNiveau = (int) round($this->niveau * $metadata['difficulty_modifier']);
        $adjustedNiveau = min(max($adjustedNiveau, 1), 10);

        for ($attempt = 1; $attempt <= self::MAX_GENERATION_RETRIES; $attempt++) {
            try {
                $generatedQuestion = $questionService->generateQuestion(
                    $this->theme,
                    $adjustedNiveau,
                    $questionNumber,
                    $usedQuestionIds,
                    $usedAnswers,
                    [],
                    $usedQuestionTexts,
                    null,
                    false,
                    $this->language,
                    true
                );

                if ($generatedQuestion) {
                    return [
                        'id' => $generatedQuestion['id'] ?? uniqid('mpq_'),
                        'text' => $generatedQuestion['question_text'] ?? $generatedQuestion['text'] ?? '',
                        'answers' => $generatedQuestion['answers'] ?? [],
                        'correct_index' => $generatedQuestion['correct_id'] ?? $generatedQuestion['correct_index'] ?? 0,
                        'sub_theme' => $generatedQuestion['sub_theme'] ?? '',
                        'theme' => $this->theme,
                        'type' => $questionType,
                        'is_skill_bonus' => $metadata['is_skill_bonus'],
                        'is_tiebreaker' => $metadata['is_tiebreaker'],
                    ];
                }
                
                Log::warning('[GenerateMultiplayerQuestionsJob] Question generation returned null', [
                    'room_id' => $this->roomId,
                    'question_number' => $questionNumber,
                    'attempt' => $attempt,
                ]);
            } catch (\Exception $e) {
                Log::error('[GenerateMultiplayerQuestionsJob] Question generation failed', [
                    'room_id' => $this->roomId,
                    'question_number' => $questionNumber,
                    'attempt' => $attempt,
                    'max_attempts' => self::MAX_GENERATION_RETRIES,
                    'error' => $e->getMessage(),
                ]);
            }
            
            if ($attempt < self::MAX_GENERATION_RETRIES) {
                usleep(200000 * $attempt);
            }
        }

        Log::error('[GenerateMultiplayerQuestionsJob] Failed to generate question after all retries', [
            'room_id' => $this->roomId,
            'question_number' => $questionNumber,
            'question_type' => $questionType,
        ]);

        return null;
    }

    protected function appendQuestionsWithRetry(GameServerService $gameServerService, array $questions): bool
    {
        for ($attempt = 0; $attempt < self::MAX_APPEND_RETRIES; $attempt++) {
            try {
                $appendResult = $gameServerService->appendQuestions($this->roomId, $questions);
                
                if ($appendResult['success'] ?? false) {
                    Log::info('[GenerateMultiplayerQuestionsJob] Bloc sent to Game Server', [
                        'room_id' => $this->roomId,
                        'bloc_count' => count($questions),
                        'total_count' => $appendResult['totalCount'] ?? 0,
                        'attempt' => $attempt + 1,
                    ]);
                    return true;
                }
                
                $error = $appendResult['error'] ?? 'Unknown error';
                Log::warning('[GenerateMultiplayerQuestionsJob] Append failed', [
                    'room_id' => $this->roomId,
                    'attempt' => $attempt + 1,
                    'max_attempts' => self::MAX_APPEND_RETRIES,
                    'error' => $error,
                ]);

                if (str_contains($error, 'room not found') || str_contains($error, 'invalid room')) {
                    Log::error('[GenerateMultiplayerQuestionsJob] Room rejected by Game Server - not retrying', [
                        'room_id' => $this->roomId,
                        'error' => $error,
                    ]);
                    return false;
                }
            } catch (\Exception $e) {
                Log::error('[GenerateMultiplayerQuestionsJob] Append exception', [
                    'room_id' => $this->roomId,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }
            
            if ($attempt < self::MAX_APPEND_RETRIES - 1) {
                $backoffMs = self::APPEND_BACKOFF_MS[$attempt] ?? 2000;
                usleep($backoffMs * 1000);
            }
        }

        return false;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[GenerateMultiplayerQuestionsJob] Job failed', [
            'room_id' => $this->roomId,
            'mode' => $this->mode,
            'theme' => $this->theme,
            'start_index' => $this->startIndex,
            'error' => $exception->getMessage(),
        ]);
    }
}
