<?php

namespace Tests\Unit;

use App\Services\SeedQuestionPoolService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Service-level contract for the embedded seed pool (#93).
 * Verifies filter narrowing, the no-silent-FR-fallback rule, and the
 * shape of the normalised payload returned to QuestionService/Planner.
 */
class SeedQuestionPoolServiceTest extends TestCase
{
    public function test_returns_null_when_language_file_is_missing_no_silent_fr_fallback(): void
    {
        Log::spy();
        $svc = new SeedQuestionPoolService();
        $result = $svc->pickOne('xx', ['sub_domain' => 'Histoire']);
        $this->assertNull($result, 'Missing-language must return null, not silently serve French.');
        Log::shouldHaveReceived('warning')
            ->withArgs(function ($msg) {
                return is_string($msg) && str_contains($msg, 'no seed pool file for language');
            })
            ->atLeast()->once();
    }

    public function test_returns_question_for_supported_language(): void
    {
        $svc = new SeedQuestionPoolService();
        $result = $svc->pickOne('fr');
        $this->assertIsArray($result);
        $this->assertSame('fr', $result['language']);
        $this->assertTrue($result['from_seed']);
        $this->assertNotEmpty($result['question_text']);
        $this->assertNotEmpty($result['answers']);
    }

    public function test_normalises_new_enrichment_fields(): void
    {
        $svc = new SeedQuestionPoolService();
        $result = $svc->pickOne('en', ['sub_domain' => 'Histoire']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('concept_id', $result);
        $this->assertArrayHasKey('cognitive_type', $result);
        $this->assertArrayHasKey('niveau_band', $result);
        $this->assertArrayHasKey('saviez_vous', $result);
        $this->assertArrayHasKey('sub_domain', $result);
        $this->assertNotEmpty($result['concept_id']);
        $this->assertNotEmpty($result['cognitive_type']);
        $this->assertNotEmpty($result['niveau_band']);
    }

    public function test_filter_narrows_to_requested_sub_domain(): void
    {
        $svc = new SeedQuestionPoolService();
        for ($i = 0; $i < 20; $i++) {
            $result = $svc->pickOne('fr', ['sub_domain' => 'Cuisine']);
            $this->assertSame('Cuisine', $result['sub_domain'] ?? null,
                'Filter should narrow to Cuisine — got ' . ($result['sub_domain'] ?? 'NULL'));
        }
    }

    public function test_filter_narrows_to_requested_cognitive_type(): void
    {
        $svc = new SeedQuestionPoolService();
        for ($i = 0; $i < 20; $i++) {
            $result = $svc->pickOne('fr', [
                'sub_domain' => 'Histoire',
                'cognitive_type' => 'deceptive_trap',
            ]);
            $this->assertSame('deceptive_trap', $result['cognitive_type'] ?? null);
        }
    }

    public function test_filter_narrows_to_requested_niveau_band(): void
    {
        $svc = new SeedQuestionPoolService();
        for ($i = 0; $i < 20; $i++) {
            $result = $svc->pickOne('fr', [
                'sub_domain' => 'Géographie',
                'niveau_band' => 'easy',
            ]);
            $this->assertSame('easy', $result['niveau_band'] ?? null);
        }
    }

    public function test_filter_falls_back_when_combination_yields_zero_candidates(): void
    {
        $svc = new SeedQuestionPoolService();
        // (Cuisine, deceptive_trap, easy) doesn't exist as a combination
        // (deceptive_trap is always tagged hard). Service must NOT return null.
        $result = $svc->pickOne('fr', [
            'sub_domain' => 'Cuisine',
            'cognitive_type' => 'deceptive_trap',
            'niveau_band' => 'easy',
        ]);
        $this->assertIsArray($result, 'Empty narrowing must gracefully widen instead of returning null.');
        $this->assertSame('Cuisine', $result['sub_domain']);
        $this->assertSame('deceptive_trap', $result['cognitive_type']);
    }

    public function test_excludes_already_used_text_hashes(): void
    {
        $svc = new SeedQuestionPoolService();
        $first = $svc->pickOne('fr', ['sub_domain' => 'Sport']);
        $usedHash = md5($first['question_text']);
        for ($i = 0; $i < 10; $i++) {
            $next = $svc->pickOne('fr', ['sub_domain' => 'Sport'], [$usedHash]);
            $this->assertNotSame($first['question_text'], $next['question_text']);
        }
    }

    public function test_inventory_for_returns_pool_snapshot_or_empty(): void
    {
        $svc = new SeedQuestionPoolService();
        $this->assertNotEmpty($svc->inventoryFor('en'));
        $this->assertSame([], $svc->inventoryFor('xx'));
    }
}
