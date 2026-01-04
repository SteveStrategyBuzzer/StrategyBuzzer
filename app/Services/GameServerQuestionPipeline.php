<?php

namespace App\Services;

use App\Jobs\GenerateGameServerQuestionsJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GameServerQuestionPipeline
{
    private const CACHE_TTL = 1800; // 30 minutes - Cache is accelerator only
    private const QUESTIONS_PER_ROUND = 10;
    private const BONUS_SKILL_QUESTIONS = 5;
    private const TIEBREAKER_QUESTIONS = 5;
    private const DEFAULT_BLOCK_SIZE = 4;

    private QuestionService $questionService;
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->questionService = new QuestionService();
        $this->firebase = FirebaseService::getInstance();
    }

    private function getCacheKey(string $roomId, string $suffix): string
    {
        return "game_server_match:{$roomId}:{$suffix}";
    }

    private function getFirestoreDocPath(string $roomId): string
    {
        return "questionPools/{$roomId}";
    }

    private function getFirestoreItemsPath(string $roomId): string
    {
        return "questionPools/{$roomId}/items";
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

        $config = [
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => $language,
            'maxRounds' => $maxRounds,
            'totalNeeded' => $totalNeeded,
        ];

        $poolData = [
            'config' => $config,
            'usedIds' => [],
            'usedTextHashes' => [],
            'nextIndex' => 2,
            'createdAt' => microtime(true),
        ];
        
        $firestoreWriteSuccess = $this->firebase->createDocument('questionPools', $roomId, $poolData);
        
        if (!$firestoreWriteSuccess) {
            Log::warning('[GameServerQuestionPipeline] Failed to write to Firestore, continuing with cache only', [
                'room_id' => $roomId,
            ]);
        }

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
        
        $this->storeQuestionToFirestore($roomId, 1, $formattedQuestion);
        
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
        $config = $this->getMatchConfig($roomId);
        if (!$config) {
            Log::warning('[GameServerQuestionPipeline] No config found for room', ['room_id' => $roomId]);
            return 0;
        }

        $nextIndex = $this->getNextIndex($roomId);
        $usedIds = $this->getUsedQuestionIds($roomId);
        $usedTexts = $this->getUsedTextHashes($roomId);
        $questions = $this->getAllQuestionsFromStore($roomId);

        $totalNeeded = $config['total_needed'] ?? $config['totalNeeded'] ?? 0;
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
                    $config['niveau'] ?? $config['level'] ?? 1,
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
                    $textHash = md5($formattedQuestion['text']);
                    $usedTexts[] = $textHash;
                    $questions[] = $formattedQuestion;
                    $generatedCount++;

                    $this->storeQuestionToFirestore($roomId, $questionNumber, $formattedQuestion);

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

        $this->updatePoolMetadata($roomId, [
            'usedIds' => $usedIds,
            'usedTextHashes' => $usedTexts,
            'nextIndex' => $endIndex + 1,
        ]);

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
        $questions = $this->getAllQuestionsFromStore($roomId);
        
        return array_slice($questions, 0, $count);
    }

    public function getQuestionByIndex(string $roomId, int $index): ?array
    {
        $cacheKey = $this->getCacheKey($roomId, "question_{$index}");
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $questions = Cache::get($this->getCacheKey($roomId, 'questions'), []);
        if (isset($questions[$index])) {
            return $questions[$index];
        }

        $question = $this->getQuestionFromFirestore($roomId, $index + 1);
        if ($question) {
            Cache::put($cacheKey, $question, self::CACHE_TTL);
        }
        
        return $question;
    }

    public function getQuestionCount(string $roomId): int
    {
        $questions = $this->getAllQuestionsFromStore($roomId);
        
        return count($questions);
    }

    public function addUsedQuestion(string $roomId, string $questionId, string $questionText): void
    {
        $usedIds = $this->getUsedQuestionIds($roomId);
        $usedTexts = $this->getUsedTextHashes($roomId);

        if (!in_array($questionId, $usedIds)) {
            $usedIds[] = $questionId;
        }

        $textHash = md5($questionText);
        if (!in_array($textHash, $usedTexts)) {
            $usedTexts[] = $textHash;
        }

        $this->updatePoolMetadata($roomId, [
            'usedIds' => $usedIds,
            'usedTextHashes' => $usedTexts,
        ]);

        Cache::put($this->getCacheKey($roomId, 'used_ids'), $usedIds, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'used_texts'), $usedTexts, self::CACHE_TTL);
    }

    public function getUsedQuestionIds(string $roomId): array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'used_ids'));
        if ($cached !== null) {
            return $cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        $usedIds = $poolData['usedIds'] ?? [];
        
        Cache::put($this->getCacheKey($roomId, 'used_ids'), $usedIds, self::CACHE_TTL);
        
        return $usedIds;
    }

    public function cleanup(string $roomId): void
    {
        Log::info('[GameServerQuestionPipeline] Cleaning up match', ['room_id' => $roomId]);

        $this->deletePoolFromFirestore($roomId);

        Cache::forget($this->getCacheKey($roomId, 'used_ids'));
        Cache::forget($this->getCacheKey($roomId, 'used_texts'));
        Cache::forget($this->getCacheKey($roomId, 'questions'));
        Cache::forget($this->getCacheKey($roomId, 'next_index'));
        Cache::forget($this->getCacheKey($roomId, 'config'));
    }

    public function getMatchConfig(string $roomId): ?array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'config'));
        if ($cached !== null) {
            return $cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        if (!$poolData || !isset($poolData['config'])) {
            return null;
        }

        $config = $poolData['config'];
        $normalizedConfig = [
            'theme' => $config['theme'] ?? '',
            'niveau' => $config['niveau'] ?? 1,
            'language' => $config['language'] ?? 'fr',
            'max_rounds' => $config['maxRounds'] ?? $config['max_rounds'] ?? 3,
            'total_needed' => $config['totalNeeded'] ?? $config['total_needed'] ?? 0,
        ];
        
        Cache::put($this->getCacheKey($roomId, 'config'), $normalizedConfig, self::CACHE_TTL);
        
        return $normalizedConfig;
    }

    public function shouldGenerateMore(string $roomId): bool
    {
        $config = $this->getMatchConfig($roomId);
        if (!$config) {
            return false;
        }

        $nextIndex = $this->getNextIndex($roomId);
        $totalNeeded = $config['total_needed'] ?? 0;
        
        return $nextIndex <= $totalNeeded;
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

    private function getNextIndex(string $roomId): int
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'next_index'));
        if ($cached !== null) {
            return (int)$cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        $nextIndex = $poolData['nextIndex'] ?? 1;
        
        Cache::put($this->getCacheKey($roomId, 'next_index'), $nextIndex, self::CACHE_TTL);
        
        return (int)$nextIndex;
    }

    private function getUsedTextHashes(string $roomId): array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'used_texts'));
        if ($cached !== null) {
            return $cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        $usedTexts = $poolData['usedTextHashes'] ?? [];
        
        Cache::put($this->getCacheKey($roomId, 'used_texts'), $usedTexts, self::CACHE_TTL);
        
        return $usedTexts;
    }

    private function getAllQuestionsFromStore(string $roomId): array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'questions'));
        if ($cached !== null && !empty($cached)) {
            return $cached;
        }

        $questions = $this->getAllQuestionsFromFirestore($roomId);
        
        if (!empty($questions)) {
            Cache::put($this->getCacheKey($roomId, 'questions'), $questions, self::CACHE_TTL);
        }
        
        return $questions;
    }

    private function storeQuestionToFirestore(string $roomId, int $questionIndex, array $question): bool
    {
        try {
            return $this->firebase->createDocument(
                $this->getFirestoreItemsPath($roomId),
                (string)$questionIndex,
                $question
            );
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to store question to Firestore', [
                'room_id' => $roomId,
                'question_index' => $questionIndex,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getQuestionFromFirestore(string $roomId, int $questionIndex): ?array
    {
        try {
            $items = $this->firebase->getCollection($this->getFirestoreItemsPath($roomId));
            return $items[(string)$questionIndex] ?? null;
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to get question from Firestore', [
                'room_id' => $roomId,
                'question_index' => $questionIndex,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getAllQuestionsFromFirestore(string $roomId): array
    {
        try {
            $items = $this->firebase->getCollection($this->getFirestoreItemsPath($roomId));
            
            uksort($items, function($a, $b) {
                return (int)$a - (int)$b;
            });
            
            return array_values($items);
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to get questions from Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function getPoolDataFromFirestore(string $roomId): ?array
    {
        try {
            return $this->firebase->getDocument('questionPools', $roomId);
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to get pool data from Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function updatePoolMetadata(string $roomId, array $updates): bool
    {
        try {
            $poolData = $this->getPoolDataFromFirestore($roomId);
            if (!$poolData) {
                return false;
            }

            $mergedData = array_merge($poolData, $updates);
            $mergedData['updatedAt'] = microtime(true);

            return $this->firebase->createDocument('questionPools', $roomId, $mergedData);
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to update pool metadata in Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function deletePoolFromFirestore(string $roomId): bool
    {
        try {
            $items = $this->firebase->getCollection($this->getFirestoreItemsPath($roomId));
            $deletedCount = 0;
            
            foreach (array_keys($items) as $itemId) {
                $deleted = $this->firebase->deleteDocument(
                    $this->getFirestoreItemsPath($roomId),
                    (string)$itemId
                );
                if ($deleted) {
                    $deletedCount++;
                }
            }
            
            $mainDeleted = $this->firebase->deleteDocument('questionPools', $roomId);
            
            Log::info('[GameServerQuestionPipeline] Pool cleanup completed', [
                'room_id' => $roomId,
                'items_deleted' => $deletedCount,
                'main_document_deleted' => $mainDeleted,
            ]);
            
            return $mainDeleted;
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to delete pool from Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
