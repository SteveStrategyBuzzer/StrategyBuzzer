<?php

namespace App\Services;

use App\Jobs\GenerateGameServerQuestionsJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GameServerQuestionPipeline
{
    private const CACHE_TTL = 7200; // 2 hours in seconds
    private const QUESTIONS_PER_ROUND = 10;
    private const BONUS_SKILL_QUESTIONS = 5;
    private const TIEBREAKER_QUESTIONS = 5;
    private const DEFAULT_BLOCK_SIZE = 4;

    private QuestionService $questionService;

    public function __construct()
    {
        $this->questionService = new QuestionService();
    }

    private function getCacheKey(string $roomId, string $suffix): string
    {
        return "game_server_match:{$roomId}:{$suffix}";
    }

    public function getTotalNeeded(int $maxRounds): int
    {
        return ($maxRounds * self::QUESTIONS_PER_ROUND) 
            + self::BONUS_SKILL_QUESTIONS 
            + self::TIEBREAKER_QUESTIONS;
    }

    public function initMatch(string $roomId, string $theme, int $niveau, string $language, int $maxRounds): ?array
    {
        Log::info('[GameServerQuestionPipeline] Initializing match', [
            'room_id' => $roomId,
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => $language,
            'max_rounds' => $maxRounds,
        ]);

        $totalNeeded = $this->getTotalNeeded($maxRounds);

        Cache::put($this->getCacheKey($roomId, 'used_ids'), [], self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'used_texts'), [], self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'questions'), [], self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'next_index'), 2, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'config'), [
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => $language,
            'max_rounds' => $maxRounds,
            'total_needed' => $totalNeeded,
        ], self::CACHE_TTL);

        $firstQuestion = $this->questionService->generateQuestion(
            $theme,
            $niveau,
            1,
            [],
            [],
            [],
            [],
            null,
            false,
            $language,
            true
        );

        if (!$firstQuestion) {
            Log::error('[GameServerQuestionPipeline] Failed to generate first question', [
                'room_id' => $roomId,
            ]);
            return null;
        }

        $formattedQuestion = $this->formatQuestion($firstQuestion, 1);
        
        $this->addUsedQuestion($roomId, $formattedQuestion['id'], $formattedQuestion['text']);
        
        Cache::put($this->getCacheKey($roomId, 'questions'), [$formattedQuestion], self::CACHE_TTL);

        Log::info('[GameServerQuestionPipeline] First question generated, dispatching block job', [
            'room_id' => $roomId,
            'question_id' => $formattedQuestion['id'],
        ]);

        GenerateGameServerQuestionsJob::dispatch(
            $roomId,
            $theme,
            $niveau,
            $language,
            $totalNeeded,
            2,
            self::DEFAULT_BLOCK_SIZE
        );

        return $formattedQuestion;
    }

    public function generateNextBlock(string $roomId, int $blockSize = 4): int
    {
        $config = Cache::get($this->getCacheKey($roomId, 'config'));
        if (!$config) {
            Log::warning('[GameServerQuestionPipeline] No config found for room', ['room_id' => $roomId]);
            return 0;
        }

        $nextIndex = Cache::get($this->getCacheKey($roomId, 'next_index'), 1);
        $usedIds = Cache::get($this->getCacheKey($roomId, 'used_ids'), []);
        $usedTexts = Cache::get($this->getCacheKey($roomId, 'used_texts'), []);
        $questions = Cache::get($this->getCacheKey($roomId, 'questions'), []);

        $totalNeeded = $config['total_needed'];
        $endIndex = min($nextIndex + $blockSize - 1, $totalNeeded);
        $generatedCount = 0;

        Log::info('[GameServerQuestionPipeline] Generating block', [
            'room_id' => $roomId,
            'start_index' => $nextIndex,
            'end_index' => $endIndex,
            'block_size' => $blockSize,
        ]);

        for ($questionNumber = $nextIndex; $questionNumber <= $endIndex; $questionNumber++) {
            try {
                $question = $this->questionService->generateQuestion(
                    $config['theme'],
                    $config['niveau'],
                    $questionNumber,
                    $usedIds,
                    [],
                    [],
                    $usedTexts,
                    null,
                    false,
                    $config['language'],
                    true
                );

                if ($question) {
                    $formattedQuestion = $this->formatQuestion($question, $questionNumber);
                    
                    $usedIds[] = $formattedQuestion['id'];
                    $usedTexts[] = md5($formattedQuestion['text']);
                    $questions[] = $formattedQuestion;
                    $generatedCount++;

                    Log::debug('[GameServerQuestionPipeline] Generated question', [
                        'room_id' => $roomId,
                        'question_number' => $questionNumber,
                        'question_id' => $formattedQuestion['id'],
                    ]);
                }

                usleep(50000);
            } catch (\Exception $e) {
                Log::error('[GameServerQuestionPipeline] Failed to generate question', [
                    'room_id' => $roomId,
                    'question_number' => $questionNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Cache::put($this->getCacheKey($roomId, 'used_ids'), $usedIds, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'used_texts'), $usedTexts, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'questions'), $questions, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'next_index'), $endIndex + 1, self::CACHE_TTL);

        Log::info('[GameServerQuestionPipeline] Block generation completed', [
            'room_id' => $roomId,
            'generated_count' => $generatedCount,
            'total_ready' => count($questions),
            'next_index' => $endIndex + 1,
        ]);

        return $generatedCount;
    }

    public function getNextQuestions(string $roomId, int $count = 4): array
    {
        $questions = Cache::get($this->getCacheKey($roomId, 'questions'), []);
        
        return array_slice($questions, 0, $count);
    }

    public function getQuestionByIndex(string $roomId, int $index): ?array
    {
        $questions = Cache::get($this->getCacheKey($roomId, 'questions'), []);
        
        return $questions[$index] ?? null;
    }

    public function getQuestionCount(string $roomId): int
    {
        $questions = Cache::get($this->getCacheKey($roomId, 'questions'), []);
        
        return count($questions);
    }

    public function addUsedQuestion(string $roomId, string $questionId, string $questionText): void
    {
        $usedIds = Cache::get($this->getCacheKey($roomId, 'used_ids'), []);
        $usedTexts = Cache::get($this->getCacheKey($roomId, 'used_texts'), []);

        if (!in_array($questionId, $usedIds)) {
            $usedIds[] = $questionId;
        }

        $textHash = md5($questionText);
        if (!in_array($textHash, $usedTexts)) {
            $usedTexts[] = $textHash;
        }

        Cache::put($this->getCacheKey($roomId, 'used_ids'), $usedIds, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'used_texts'), $usedTexts, self::CACHE_TTL);
    }

    public function getUsedQuestionIds(string $roomId): array
    {
        return Cache::get($this->getCacheKey($roomId, 'used_ids'), []);
    }

    public function cleanup(string $roomId): void
    {
        Log::info('[GameServerQuestionPipeline] Cleaning up match cache', ['room_id' => $roomId]);

        Cache::forget($this->getCacheKey($roomId, 'used_ids'));
        Cache::forget($this->getCacheKey($roomId, 'used_texts'));
        Cache::forget($this->getCacheKey($roomId, 'questions'));
        Cache::forget($this->getCacheKey($roomId, 'next_index'));
        Cache::forget($this->getCacheKey($roomId, 'config'));
    }

    public function getMatchConfig(string $roomId): ?array
    {
        return Cache::get($this->getCacheKey($roomId, 'config'));
    }

    public function shouldGenerateMore(string $roomId): bool
    {
        $config = Cache::get($this->getCacheKey($roomId, 'config'));
        if (!$config) {
            return false;
        }

        $nextIndex = Cache::get($this->getCacheKey($roomId, 'next_index'), 1);
        return $nextIndex <= $config['total_needed'];
    }

    private function formatQuestion(array $question, int $questionNumber): array
    {
        return [
            'id' => $question['id'] ?? uniqid('gsq_'),
            'number' => $questionNumber,
            'text' => $question['question_text'] ?? $question['text'] ?? '',
            'answers' => $question['answers'] ?? [],
            'correct_index' => $question['correct_id'] ?? $question['correct_index'] ?? 0,
            'sub_theme' => $question['sub_theme'] ?? '',
            'theme' => $question['theme'] ?? '',
            'type' => $question['type'] ?? 'multiple',
        ];
    }
}
