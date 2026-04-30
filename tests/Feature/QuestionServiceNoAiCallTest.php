<?php

namespace Tests\Feature;

use App\Services\GameServerQuestionPipeline;
use App\Services\QuestionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * #88 — Hard-gate proof at runtime.
 *
 * No matter the context (solo, multiplayer, league, master), QuestionService
 * must never make an outgoing HTTP request to the AI question/queue/fun-fact
 * endpoints during gameplay. This test fakes the HTTP layer with
 * Http::preventStrayRequests() + Http::fake(), runs a full match-worth of
 * generateQuestion() calls, and asserts ZERO requests were sent.
 *
 * If a regression re-introduces a call to /generate-question, /generate-queue,
 * /generate-fun-fact, or any external HTTP endpoint, this test breaks
 * immediately.
 */
class QuestionServiceNoAiCallTest extends TestCase
{
    public function test_solo_match_generates_questions_without_calling_ai(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $service = new QuestionService();

        $usedIds = [];
        $usedAnswers = [];
        $usedTexts = [];

        // 11 questions = a full Solo round (worst case Magicienne avatar).
        for ($i = 1; $i <= 11; $i++) {
            try {
                $q = $service->generateQuestion(
                    'general',           // theme
                    5,                    // niveau
                    $i,                   // questionNumber
                    $usedIds,
                    $usedAnswers,
                    [],                   // sessionUsedAnswers
                    $usedTexts,
                    null,                 // opponentAge
                    false,                // isBoss
                    'fr',                 // language
                    true,                 // skipCache (force seed pool path)
                    'solo'
                );
            } catch (\RuntimeException $e) {
                // The bank-empty exception is acceptable — what we test is
                // the absence of HTTP calls, not data presence.
                $this->assertStringContainsString('Live AI is disabled', $e->getMessage());
                continue;
            }

            $this->assertIsArray($q, "generateQuestion #{$i} must return an array");
            $this->assertArrayHasKey('id', $q);
            $this->assertArrayHasKey('answers', $q);

            if (!empty($q['id'])) {
                $usedIds[] = (string) $q['id'];
            }
            if (isset($q['answers'][$q['correct_index'] ?? 0])) {
                $usedAnswers[] = $q['answers'][$q['correct_index'] ?? 0];
            }
            if (!empty($q['question_text'])) {
                $usedTexts[] = $q['question_text'];
            }
        }

        // ZERO outgoing HTTP requests during the entire match.
        Http::assertNothingSent();
    }

    public function test_multiplayer_context_also_makes_no_ai_call(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $service = new QuestionService();

        for ($i = 1; $i <= 5; $i++) {
            try {
                $service->generateQuestion(
                    'general', 5, $i, [], [], [], [], null, false, 'fr', true, 'multiplayer'
                );
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Live AI is disabled', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_game_server_pipeline_off_plan_fallback_makes_no_ai_call(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $pipeline = new GameServerQuestionPipeline();

        // Reach into the private off-plan branch of renderFromPlanOrFallback.
        // With $orderedSlots = [] the index is always "off plan", which is
        // the exact branch the reviewer flagged at line ~831 (now refactored
        // to use bank picker → stub, no QuestionService::generateQuestion).
        $ref = new \ReflectionClass($pipeline);
        $method = $ref->getMethod('renderFromPlanOrFallback');
        $method->setAccessible(true);

        for ($i = 1; $i <= 5; $i++) {
            $result = $method->invoke($pipeline, [], $i, 'fr', 'general', 5, [], []);
            $this->assertIsArray($result, "off-plan slot #{$i} must yield a payload (bank pick or stub)");
            $this->assertArrayHasKey('id', $result);
        }

        Http::assertNothingSent();
    }
}
