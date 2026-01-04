<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class AntiDuplicationCacheService
{
    private const TTL = 7200;

    private function getKey(string $roomId, string $type): string
    {
        return "match:{$roomId}:{$type}";
    }

    public function initialize(string $roomId, array $firstQuestion): void
    {
        $this->cleanup($roomId);
        $this->addQuestion($roomId, $firstQuestion);
        
        Log::info('[AntiDuplicationCacheService] Cache initialized', [
            'room_id' => $roomId,
            'question_id' => $firstQuestion['id'] ?? 'unknown',
        ]);
    }

    public function addQuestion(string $roomId, array $question): void
    {
        $questionId = $question['id'] ?? null;
        $questionText = $question['text'] ?? $question['question_text'] ?? null;
        $answers = $question['answers'] ?? [];

        if ($questionId) {
            Redis::sadd($this->getKey($roomId, 'usedQuestionIds'), $questionId);
            Redis::expire($this->getKey($roomId, 'usedQuestionIds'), self::TTL);
        }

        if ($questionText) {
            Redis::sadd($this->getKey($roomId, 'usedQuestionTexts'), $questionText);
            Redis::expire($this->getKey($roomId, 'usedQuestionTexts'), self::TTL);

            $topics = $this->extractTopics($questionText);
            if (!empty($topics)) {
                Redis::sadd($this->getKey($roomId, 'usedTopics'), ...$topics);
                Redis::expire($this->getKey($roomId, 'usedTopics'), self::TTL);
            }
        }

        if (!empty($answers)) {
            $answerTexts = [];
            foreach ($answers as $answer) {
                $answerText = is_array($answer) ? ($answer['text'] ?? '') : $answer;
                if ($answerText) {
                    $answerTexts[] = $answerText;
                }
            }
            if (!empty($answerTexts)) {
                Redis::sadd($this->getKey($roomId, 'usedAnswers'), ...$answerTexts);
                Redis::expire($this->getKey($roomId, 'usedAnswers'), self::TTL);
            }
        }
    }

    public function getUsedTopics(string $roomId, int $limit = 50): array
    {
        $topics = Redis::smembers($this->getKey($roomId, 'usedTopics'));
        
        if (empty($topics)) {
            return [];
        }

        $topics = array_slice($topics, 0, $limit);
        
        return $topics;
    }

    public function getUsedQuestionIds(string $roomId): array
    {
        $ids = Redis::smembers($this->getKey($roomId, 'usedQuestionIds'));
        return $ids ?: [];
    }

    public function getUsedQuestionTexts(string $roomId): array
    {
        $texts = Redis::smembers($this->getKey($roomId, 'usedQuestionTexts'));
        return $texts ?: [];
    }

    public function getUsedAnswers(string $roomId): array
    {
        $answers = Redis::smembers($this->getKey($roomId, 'usedAnswers'));
        return $answers ?: [];
    }

    public function cleanup(string $roomId): void
    {
        Redis::del([
            $this->getKey($roomId, 'usedQuestionIds'),
            $this->getKey($roomId, 'usedQuestionTexts'),
            $this->getKey($roomId, 'usedAnswers'),
            $this->getKey($roomId, 'usedTopics'),
        ]);

        Log::info('[AntiDuplicationCacheService] Cache cleaned up', [
            'room_id' => $roomId,
        ]);
    }

    public function exists(string $roomId): bool
    {
        return Redis::exists($this->getKey($roomId, 'usedQuestionIds')) > 0;
    }

    private function extractTopics(string $questionText): array
    {
        $topics = [];
        
        $text = strip_tags($questionText);
        $text = preg_replace('/[?!.,;:«»""\'"\(\)\[\]{}]/', ' ', $text);
        
        preg_match_all('/\b[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸŒ][a-zàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿœ]+(?:\s+[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸŒ][a-zàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿœ]+)*/u', $text, $properNouns);
        
        if (!empty($properNouns[0])) {
            foreach ($properNouns[0] as $noun) {
                $noun = trim($noun);
                if (mb_strlen($noun) >= 3) {
                    $topics[] = $noun;
                }
            }
        }

        $stopWords = [
            'qui', 'que', 'quoi', 'quel', 'quelle', 'quels', 'quelles',
            'comment', 'pourquoi', 'combien', 'lequel', 'laquelle', 'lesquels', 'lesquelles',
            'dans', 'pour', 'avec', 'sans', 'sous', 'sur', 'entre', 'vers', 'chez', 'par',
            'est', 'sont', 'était', 'ont', 'fait', 'avoir', 'être', 'faire',
            'une', 'les', 'des', 'aux', 'ces', 'cette', 'cet',
            'plus', 'moins', 'très', 'bien', 'aussi', 'encore', 'toujours', 'jamais',
            'tout', 'tous', 'toute', 'toutes', 'autre', 'autres', 'même', 'mêmes',
            'premier', 'première', 'dernier', 'dernière', 'grand', 'grande', 'petit', 'petite',
            'the', 'and', 'for', 'with', 'from', 'that', 'this', 'what', 'which', 'when', 'where', 'who', 'how', 'why',
        ];

        $words = preg_split('/\s+/', $text);
        
        foreach ($words as $word) {
            $word = trim($word);
            $wordLower = mb_strtolower($word);
            
            if (mb_strlen($word) >= 4 && 
                !in_array($wordLower, $stopWords) && 
                !preg_match('/^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸŒ]/', $word)) {
                
                if (preg_match('/^\d{4}$/', $word)) {
                    $topics[] = $word;
                }
            }
        }

        return array_unique($topics);
    }
}
