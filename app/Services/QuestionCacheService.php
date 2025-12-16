<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QuestionCacheService
{
    private const CACHE_PREFIX = 'questions:';
    private const DEFAULT_TTL = 3600;
    private const BLOCK_SIZE = 10;

    public function getCacheKey(string $theme, int $niveau, string $language): string
    {
        $normalizedTheme = strtolower(str_replace(' ', '_', $theme));
        return self::CACHE_PREFIX . "{$normalizedTheme}:{$niveau}:{$language}";
    }

    public function getQuestion(string $theme, int $niveau, string $language): ?array
    {
        $key = $this->getCacheKey($theme, $niveau, $language);
        $questions = Cache::get($key, []);

        if (empty($questions)) {
            Log::info('[QuestionCache] Cache miss', ['key' => $key]);
            return null;
        }

        $question = array_shift($questions);
        Cache::put($key, $questions, self::DEFAULT_TTL);

        Log::info('[QuestionCache] Cache hit', [
            'key' => $key,
            'remaining' => count($questions)
        ]);

        return $question;
    }

    public function addQuestions(string $theme, int $niveau, string $language, array $questions): void
    {
        $key = $this->getCacheKey($theme, $niveau, $language);
        $existing = Cache::get($key, []);
        
        $merged = array_merge($existing, $questions);
        Cache::put($key, $merged, self::DEFAULT_TTL);

        Log::info('[QuestionCache] Added questions to cache', [
            'key' => $key,
            'added' => count($questions),
            'total' => count($merged)
        ]);
    }

    public function getAvailableCount(string $theme, int $niveau, string $language): int
    {
        $key = $this->getCacheKey($theme, $niveau, $language);
        $questions = Cache::get($key, []);
        return count($questions);
    }

    public function needsRefill(string $theme, int $niveau, string $language, int $threshold = 5): bool
    {
        return $this->getAvailableCount($theme, $niveau, $language) < $threshold;
    }

    public function clearCache(string $theme, int $niveau, string $language): void
    {
        $key = $this->getCacheKey($theme, $niveau, $language);
        Cache::forget($key);
        Log::info('[QuestionCache] Cache cleared', ['key' => $key]);
    }

    public function warmCache(string $theme, int $niveau, string $language, callable $generator, int $count = null): int
    {
        $count = $count ?? self::BLOCK_SIZE;
        $questions = [];

        for ($i = 0; $i < $count; $i++) {
            try {
                $question = $generator($theme, $niveau, $i + 1, $language);
                if ($question) {
                    $questions[] = $question;
                }
            } catch (\Exception $e) {
                Log::error('[QuestionCache] Generation failed', [
                    'error' => $e->getMessage(),
                    'index' => $i
                ]);
            }
        }

        if (!empty($questions)) {
            $this->addQuestions($theme, $niveau, $language, $questions);
        }

        return count($questions);
    }

    public function getCacheStats(): array
    {
        $themes = ['general', 'science', 'histoire', 'geographie', 'sport', 'cinema', 'musique', 'litterature'];
        $languages = ['fr', 'en', 'es', 'de'];
        $stats = [];

        foreach ($themes as $theme) {
            foreach ($languages as $lang) {
                for ($niveau = 1; $niveau <= 100; $niveau += 10) {
                    $count = $this->getAvailableCount($theme, $niveau, $lang);
                    if ($count > 0) {
                        $stats["{$theme}:{$niveau}:{$lang}"] = $count;
                    }
                }
            }
        }

        return $stats;
    }
}
