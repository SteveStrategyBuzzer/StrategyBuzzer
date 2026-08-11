<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\DepthContract;
use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests §36 — DepthContract + DepthContractRegistry
 *
 * Couvre :
 *   - les 7 Depths officiels (2,4,6,7,8,9,10)
 *   - fail-closed sur Depth inconnu
 *   - cohérence des valeurs de chaque contrat
 *   - officialDepths() + isKnown()
 *   - toPromptText() — non vide, contient les infos structurantes
 */
class DepthContractTest extends TestCase
{
    // =========================================================================
    // §36.1 — Les 7 Depths officiels retournent un DepthContract valide
    // =========================================================================

    /** @dataProvider officialDepthProvider */
    public function test_official_depth_returns_contract(int $depth): void
    {
        $contract = DepthContractRegistry::get($depth);

        $this->assertInstanceOf(DepthContract::class, $contract);
        $this->assertSame($depth, $contract->depth);
    }

    public static function officialDepthProvider(): array
    {
        return [
            [2], [4], [6], [7], [8], [9], [10],
        ];
    }

    // =========================================================================
    // §36.2 — Fail-closed sur Depth inconnu
    // =========================================================================

    /** @dataProvider unknownDepthProvider */
    public function test_unknown_depth_throws_invalid_argument_exception(int $depth): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Depths supportés/');

        DepthContractRegistry::get($depth);
    }

    public static function unknownDepthProvider(): array
    {
        return [
            'depth 0'   => [0],
            'depth 1'   => [1],
            'depth 3'   => [3],
            'depth 5'   => [5],
            'depth 11'  => [11],
            'depth -1'  => [-1],
            'depth 100' => [100],
        ];
    }

    // =========================================================================
    // §36.3 — Cohérence des plages knowledge_frequency
    // =========================================================================

    /** @dataProvider officialDepthProvider */
    public function test_knowledge_frequency_range_is_valid(int $depth): void
    {
        $c = DepthContractRegistry::get($depth);

        $this->assertGreaterThanOrEqual(1, $c->knowledgeFrequencyMin);
        $this->assertLessThanOrEqual(8, $c->knowledgeFrequencyMax);
        $this->assertLessThanOrEqual($c->knowledgeFrequencyMax, $c->knowledgeFrequencyMin);
    }

    // =========================================================================
    // §36.4 — Profil non vide
    // =========================================================================

    /** @dataProvider officialDepthProvider */
    public function test_subject_profile_is_non_empty(int $depth): void
    {
        $c = DepthContractRegistry::get($depth);

        $this->assertNotEmpty($c->subjectProfileLabel);
        $this->assertNotEmpty($c->subjectProfileDescription);
        $this->assertNotEmpty($c->referenceProfile);
    }

    // =========================================================================
    // §36.5 — Règles interdites présentes pour chaque Depth
    // =========================================================================

    /** @dataProvider officialDepthProvider */
    public function test_forbidden_rules_are_not_empty(int $depth): void
    {
        $c = DepthContractRegistry::get($depth);

        $this->assertNotEmpty($c->forbiddenRules);
        foreach ($c->forbiddenRules as $rule) {
            $this->assertIsString($rule);
            $this->assertNotEmpty($rule);
        }
    }

    // =========================================================================
    // §36.6 — Croissance de la complexité : Depth 2 < Depth 10
    // =========================================================================

    public function test_depth2_is_simpler_than_depth10(): void
    {
        $d2  = DepthContractRegistry::get(2);
        $d10 = DepthContractRegistry::get(10);

        // Depth 2 : fréquence très élevée (très commune) → valeur basse
        // Depth 10 : fréquence extrêmement rare → valeur haute
        $this->assertLessThan($d10->knowledgeFrequencyMin, $d2->knowledgeFrequencyMax + 1);
    }

    // =========================================================================
    // §36.7 — officialDepths() retourne exactement 7 valeurs
    // =========================================================================

    public function test_official_depths_returns_exactly_seven(): void
    {
        $depths = DepthContractRegistry::officialDepths();

        $this->assertCount(7, $depths);
        $this->assertSame([2, 4, 6, 7, 8, 9, 10], $depths);
    }

    // =========================================================================
    // §36.8 — isKnown()
    // =========================================================================

    public function test_is_known_returns_true_for_official_depths(): void
    {
        foreach ([2, 4, 6, 7, 8, 9, 10] as $depth) {
            $this->assertTrue(DepthContractRegistry::isKnown($depth));
        }
    }

    public function test_is_known_returns_false_for_unknown_depths(): void
    {
        foreach ([0, 1, 3, 5, 11, -1] as $depth) {
            $this->assertFalse(DepthContractRegistry::isKnown($depth));
        }
    }

    // =========================================================================
    // §36.9 — toPromptText() est non vide et contient les sections clés
    // =========================================================================

    /** @dataProvider officialDepthProvider */
    public function test_to_prompt_text_contains_key_sections(int $depth): void
    {
        $c    = DepthContractRegistry::get($depth);
        $text = $c->toPromptText();

        $this->assertNotEmpty($text);
        $this->assertStringContainsString("DEPTH {$depth}", $text);
        $this->assertStringContainsString('subject_profile', $text);
        $this->assertStringContainsString('knowledge_frequency', $text);
        $this->assertStringContainsString('forbidden_rules', $text);
    }

    // =========================================================================
    // §36.10 — Cache interne (même instance retournée deux fois)
    // =========================================================================

    public function test_registry_returns_same_instance_on_second_call(): void
    {
        $c1 = DepthContractRegistry::get(7);
        $c2 = DepthContractRegistry::get(7);

        $this->assertSame($c1, $c2);
    }

    // =========================================================================
    // §36.11 — Valeurs exactes pour Depth 2 (sentinelle grand public)
    // =========================================================================

    public function test_depth2_contract_values(): void
    {
        $c = DepthContractRegistry::get(2);

        $this->assertSame(2, $c->depth);
        $this->assertSame(1, $c->knowledgeFrequencyMin);
        $this->assertSame(2, $c->knowledgeFrequencyMax);
        $this->assertSame('Très commune', $c->knowledgeFrequencyLabel);
        $this->assertSame('Grand public', $c->subjectProfileLabel);
    }

    // =========================================================================
    // §36.12 — Valeurs exactes pour Depth 10 (sentinelle expert avancé)
    // =========================================================================

    public function test_depth10_contract_values(): void
    {
        $c = DepthContractRegistry::get(10);

        $this->assertSame(10, $c->depth);
        $this->assertSame(8, $c->knowledgeFrequencyMin);
        $this->assertSame(8, $c->knowledgeFrequencyMax);
        $this->assertSame('Extrêmement rare', $c->knowledgeFrequencyLabel);
        $this->assertSame('Expert avancé', $c->subjectProfileLabel);
    }
}
