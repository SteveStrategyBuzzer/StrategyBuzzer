<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Knowledge\LearningDirectionLexicon;
use App\Services\QuestionBank\Knowledge\LearningIdeaFamilyIndex;
use App\Services\QuestionBank\Rotation\DTO\LearningDirectionInput;
use App\Services\QuestionBank\Rotation\DTO\LearningDirectionResult;
use App\Services\QuestionBank\Rotation\KeyLearningDirection;
use App\Services\QuestionBank\Rotation\LearningDirectionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires purs — ZÉRO DB, ZÉRO Eloquent, ZÉRO Laravel.
 *
 * Vérifie les 3 sorties de KeyLearningDirection :
 *   PASS             — direction inédite
 *   FAIL             — doublon certain (même direction_key ou synonyme)
 *   REVIEW_STRUCTURE — même famille, KEY_STRUCTURE tranche
 */
final class KeyLearningDirectionTest extends TestCase
{
    private KeyLearningDirection $kld;
    private LearningDirectionLexicon $lexicon;
    private LearningIdeaFamilyIndex  $familyIndex;

    protected function setUp(): void
    {
        $this->lexicon     = new LearningDirectionLexicon();
        $this->familyIndex = new LearningIdeaFamilyIndex();
        $this->kld         = new KeyLearningDirection($this->lexicon, $this->familyIndex);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function input(string $subject, string $idea, string $domain = 'transport'): LearningDirectionInput
    {
        return new LearningDirectionInput(
            depth:              4,
            domainCode:         $domain,
            subDomain:          'mobilite',
            subject:            $subject,
            dominantIdea:       $idea,
            knowledgeFrequency: 3,
        );
    }

    private function registryWith(string $subject, string $idea): LearningDirectionRegistry
    {
        $registry      = new LearningDirectionRegistry();
        $subjectKey    = $this->lexicon->normalize($subject);
        $ideaCanonical = $this->lexicon->resolve($idea);
        $directionKey  = $subjectKey . '::' . $ideaCanonical;
        $registry->add($directionKey, $subjectKey, $ideaCanonical);

        return $registry;
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 1 — PASS quand sujet ET idée sont inédits
    // ─────────────────────────────────────────────────────────────────

    public function test_passes_when_subject_and_idea_are_both_new(): void
    {
        $result = $this->kld->check($this->input('transport', 'voiture'), new LearningDirectionRegistry());

        $this->assertTrue($result->isPass());
        $this->assertNull($result->reason);
        $this->assertSame('transport', $result->normalizedSubject);
        $this->assertSame('voiture', $result->normalizedDominantIdea);
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 2 — FAIL si direction_key exacte déjà enregistrée
    // ─────────────────────────────────────────────────────────────────

    public function test_fails_when_direction_key_already_exists_exactly(): void
    {
        $registry = $this->registryWith('transport', 'voiture');

        $result = $this->kld->check($this->input('transport', 'voiture'), $registry);

        $this->assertTrue($result->isFail());
        $this->assertSame(LearningDirectionResult::REASON_DIRECT_PAIR_DUPLICATE, $result->reason);
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 3 — FAIL si l'idée se résout vers un canonical déjà enregistré
    // ─────────────────────────────────────────────────────────────────

    public function test_fails_when_idea_resolves_to_existing_canonical_via_synonym(): void
    {
        $registry = $this->registryWith('transport', 'voiture');

        foreach (['auto', 'char', 'bagnole', 'automobile'] as $synonym) {
            $result = $this->kld->check($this->input('transport', $synonym), $registry);

            $this->assertTrue($result->isFail(), "Attendu FAIL pour synonym '$synonym'");
            $this->assertSame(LearningDirectionResult::REASON_DIRECT_PAIR_DUPLICATE, $result->reason);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 4 — REVIEW_STRUCTURE si même famille qu'une direction existante
    // ─────────────────────────────────────────────────────────────────

    public function test_returns_review_structure_when_idea_is_in_same_family_as_existing(): void
    {
        $registry = $this->registryWith('transport', 'voiture');

        foreach (['camion', 'autobus', 'pickup'] as $neighbor) {
            $result = $this->kld->check($this->input('transport', $neighbor), $registry);

            $this->assertTrue(
                $result->isReviewStructure(),
                "Attendu REVIEW_STRUCTURE pour '$neighbor' (même famille véhicules_routiers)"
            );
            $this->assertSame(LearningDirectionResult::REASON_POSSIBLE_CONTEXTUAL_DUPLICATE, $result->reason);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 5 — PASS si idée d'une famille différente
    // ─────────────────────────────────────────────────────────────────

    public function test_passes_when_idea_is_in_different_family_from_existing(): void
    {
        $registry = $this->registryWith('transport', 'voiture');

        foreach (['train', 'avion', 'bateau'] as $idea) {
            $result = $this->kld->check($this->input('transport', $idea), $registry);

            $this->assertTrue($result->isPass(), "Attendu PASS pour '$idea' (famille différente)");
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 6 — PASS si famille inconnue pour ce domaine
    // ─────────────────────────────────────────────────────────────────

    public function test_passes_when_idea_family_is_unknown(): void
    {
        $registry = new LearningDirectionRegistry();
        $subjectKey   = 'gastronomie';
        $ideaCanonical = 'pizza';
        $registry->add($subjectKey . '::' . $ideaCanonical, $subjectKey, $ideaCanonical);

        $result = $this->kld->check(
            $this->input('gastronomie', 'sushi', 'gastronomie'),
            $registry,
        );

        $this->assertTrue($result->isPass());
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 7 — la résolution synonyme précède la construction de la clé
    // ─────────────────────────────────────────────────────────────────

    public function test_resolves_synonym_to_canonical_before_building_direction_key(): void
    {
        $registry = new LearningDirectionRegistry();

        $result = $this->kld->check($this->input('transport', 'auto'), $registry);

        $this->assertTrue($result->isPass());
        $this->assertSame('voiture', $result->normalizedDominantIdea, 'auto doit être résolu en voiture');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 8 — aucun hash généré
    // ─────────────────────────────────────────────────────────────────

    public function test_does_not_generate_hashes(): void
    {
        $result = $this->kld->check($this->input('transport', 'voiture'), new LearningDirectionRegistry());

        $this->assertFalse(property_exists($result, 'hash'));
        $this->assertFalse(property_exists($result, 'kld_hash'));
        $this->assertFalse(property_exists($result, 'kernel_code'));
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 9 — aucune dépendance à une DB ou classe Eloquent
    // ─────────────────────────────────────────────────────────────────

    public function test_has_no_database_dependency(): void
    {
        $reflection = new \ReflectionClass(KeyLearningDirection::class);
        $uses       = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $param) {
            $uses[] = $param->getType()?->getName();
        }

        $this->assertNotContains('Illuminate\Database\Eloquent\Model', $uses);
        $this->assertNotContains('Illuminate\Support\Facades\DB', $uses);
        $this->assertNotContains('App\Models\QuestionIntent', $uses);
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 10 — accepte un LearningDirectionInput typé
    // ─────────────────────────────────────────────────────────────────

    public function test_accepts_typed_learning_direction_input(): void
    {
        $input = new LearningDirectionInput(
            depth:              6,
            domainCode:         'transport',
            subDomain:          'mobilite_urbaine',
            subject:            'transport',
            dominantIdea:       'voiture',
            knowledgeFrequency: 5,
            rotationIdentifier: 'rot-001',
        );

        $result = $this->kld->check($input, new LearningDirectionRegistry());

        $this->assertInstanceOf(LearningDirectionResult::class, $result);
        $this->assertTrue($result->isPass());
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 11 — REVIEW_STRUCTURE porte le bon reason
    // ─────────────────────────────────────────────────────────────────

    public function test_review_structure_carries_possible_contextual_duplicate_reason(): void
    {
        $registry = $this->registryWith('transport', 'voiture');

        $result = $this->kld->check($this->input('transport', 'camion'), $registry);

        $this->assertTrue($result->isReviewStructure());
        $this->assertSame(LearningDirectionResult::REASON_POSSIBLE_CONTEXTUAL_DUPLICATE, $result->reason);
        $this->assertNull($result->synonymDetected);
    }
}
