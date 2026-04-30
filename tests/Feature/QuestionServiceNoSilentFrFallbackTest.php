<?php

namespace Tests\Feature;

use App\Services\QuestionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * #93 — QuestionService gameplay path no-silent-FR-fallback contract.
 *
 * QuestionService::getFallbackQuestion() previously had its own private loader
 * that fell back to French when the requested language file was missing. This
 * bypassed the SeedQuestionPoolService no-silent-fallback rule and meant that a
 * Spanish/Russian/etc player could be silently served French questions.
 *
 * After #93 the loader logs a warning and returns null instead. The wrapper
 * generateQuestion() then throws so BankDryDetector + ops surfaces see the
 * real lacuna instead of pretending the seed pool covered the request.
 */
class QuestionServiceNoSilentFrFallbackTest extends TestCase
{
    public function test_unsupported_language_does_not_silently_serve_french(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Log::spy();

        $service = new QuestionService();

        // 'xx' has no resources/seed/fallback-questions-xx.json. Pre-#93 this
        // would silently fall back to fallback-questions-fr.json and return a
        // French question. Post-#93 it must surface the gap loudly.
        $thrown = null;
        try {
            $service->generateQuestion(
                'general',           // theme
                5,                    // niveau
                1,                    // questionNumber
                [],                   // usedIds
                [],                   // usedAnswers
                [],                   // sessionUsedAnswers
                [],                   // usedTexts
                null,                 // opponentAge
                false,                // isBoss
                'xx',                 // language ← no seed file exists
                true,                 // skipCache (force seed pool path)
                'solo'                // context
            );
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull(
            $thrown,
            'Missing-language seed must surface as RuntimeException, not a silent FR fallback.'
        );
        $this->assertStringContainsString(
            'bank+cache+seed all empty',
            $thrown->getMessage(),
            'The dry-detector message must mention all three layers being empty.'
        );

        Log::shouldHaveReceived('warning')
            ->withArgs(function ($msg) {
                return is_string($msg) && str_contains($msg, 'silent FR fallback DISABLED');
            })
            ->atLeast()->once();
    }

    public function test_supported_language_still_returns_seed_question(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $service = new QuestionService();

        $q = $service->generateQuestion(
            'general',
            5,
            1,
            [],
            [],
            [],
            [],
            null,
            false,
            'es',                 // Spanish has a seed file post-#93
            true,
            'solo'
        );

        $this->assertIsArray($q);
        $this->assertNotEmpty($q['question_text']);
        $this->assertNotEmpty($q['answers']);
    }
}
