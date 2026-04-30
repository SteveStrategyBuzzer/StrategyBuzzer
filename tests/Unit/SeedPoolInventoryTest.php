<?php

namespace Tests\Unit;

use App\Services\SeedQuestionPoolService;
use Tests\TestCase;

/**
 * Inventory contract for the seed pool (#93).
 *
 * Guarantees that every supported language has at least one seed question
 * for every official sub_domain, with the cognitive/niveau distribution
 * the planner expects. Failure here means the planner could find an empty
 * filter result and fall back to buildShortageStub for some segments.
 */
class SeedPoolInventoryTest extends TestCase
{
    /** @var array<int, string> */
    private const REQUIRED_LANGUAGES = ['fr', 'en', 'es', 'it', 'de', 'pt', 'ru', 'zh', 'ar', 'el'];

    /** @var array<int, string> */
    private const REQUIRED_SUBDOMAINS = ['Histoire', 'Sport', 'Géographie', 'Art', 'Cuisine', 'Science', 'Cinéma', 'Faune'];

    private const MIN_QUESTIONS_PER_CELL = 4;

    public function test_every_required_language_has_a_seed_file(): void
    {
        $svc = new SeedQuestionPoolService();
        foreach (self::REQUIRED_LANGUAGES as $lang) {
            $inv = $svc->inventoryFor($lang);
            $this->assertNotEmpty($inv, "Language `{$lang}` is missing its seed pool file — the silent FR fallback was removed in #93.");
        }
    }

    public function test_every_lang_x_subdomain_cell_has_at_least_min_questions(): void
    {
        $svc = new SeedQuestionPoolService();
        $missing = [];
        foreach (self::REQUIRED_LANGUAGES as $lang) {
            $inv = $svc->inventoryFor($lang);
            $bySub = [];
            foreach ($inv as $q) {
                $sub = $q['sub_domain'] ?? $q['sub_theme'] ?? null;
                if ($sub === null) {
                    continue;
                }
                $bySub[$sub] = ($bySub[$sub] ?? 0) + 1;
            }
            foreach (self::REQUIRED_SUBDOMAINS as $sub) {
                $count = $bySub[$sub] ?? 0;
                if ($count < self::MIN_QUESTIONS_PER_CELL) {
                    $missing[] = "{$lang}/{$sub} (got {$count}, need ≥" . self::MIN_QUESTIONS_PER_CELL . ')';
                }
            }
        }
        $this->assertSame([], $missing, 'Cells under the minimum threshold: ' . implode(', ', $missing));
    }

    public function test_each_question_carries_required_enrichment_tags(): void
    {
        $svc = new SeedQuestionPoolService();
        $missingTags = [];
        foreach (self::REQUIRED_LANGUAGES as $lang) {
            foreach ($svc->inventoryFor($lang) as $idx => $q) {
                foreach (['concept_id', 'sub_domain', 'cognitive_type', 'niveau_band', 'saviez_vous'] as $tag) {
                    if (empty($q[$tag])) {
                        $missingTags[] = "{$lang}#{$idx} missing `{$tag}`";
                    }
                }
            }
        }
        $this->assertSame([], $missingTags, 'Tags missing on entries: ' . implode(', ', array_slice($missingTags, 0, 10)));
    }

    public function test_cognitive_type_values_are_within_contract_set(): void
    {
        $svc = new SeedQuestionPoolService();
        $allowed = ['recognition', 'reasoning', 'deceptive_trap'];
        foreach (self::REQUIRED_LANGUAGES as $lang) {
            foreach ($svc->inventoryFor($lang) as $idx => $q) {
                $cog = $q['cognitive_type'] ?? null;
                $this->assertContains($cog, $allowed, "Invalid cognitive_type at {$lang}#{$idx}: " . var_export($cog, true));
            }
        }
    }

    public function test_niveau_band_values_are_within_contract_set(): void
    {
        $svc = new SeedQuestionPoolService();
        $allowed = ['easy', 'medium', 'hard'];
        foreach (self::REQUIRED_LANGUAGES as $lang) {
            foreach ($svc->inventoryFor($lang) as $idx => $q) {
                $band = $q['niveau_band'] ?? null;
                $this->assertContains($band, $allowed, "Invalid niveau_band at {$lang}#{$idx}: " . var_export($band, true));
            }
        }
    }

    public function test_concept_ids_are_consistent_across_languages(): void
    {
        $svc = new SeedQuestionPoolService();
        $reference = null;
        foreach (self::REQUIRED_LANGUAGES as $lang) {
            $ids = array_map(fn($q) => $q['concept_id'] ?? null, $svc->inventoryFor($lang));
            sort($ids);
            if ($reference === null) {
                $reference = $ids;
                continue;
            }
            $this->assertSame($reference, $ids, "Language `{$lang}` does not have the same concept set as the reference language.");
        }
    }
}
