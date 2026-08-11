<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\DepthContract;
use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use App\Services\QuestionBank\Taxonomy\FailReason;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use App\Services\QuestionBank\Taxonomy\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests §37–39 — ValidationDominantIdeas
 *
 * Couvre :
 *   - validateOne() : règles PHP-enforced (FORMAT, GENERIC, SUBJECT, TOO_BROAD,
 *                     NOT_DOMINANT, DUPLICATE, LEXICAL, CONCEPTUAL, ALREADY_COVERED, OUTSIDE_DEPTH)
 *   - validateDiversity() : validation collective avant persistance
 *   - ValidationResult : DTO PASS/FAIL
 *   - FailReason : phpEnforced() vs geminiEnforced()
 */
class ValidationDominantIdeasTest extends TestCase
{
    private ValidationDominantIdeas $vdi;
    private DepthContract           $contract2;  // Depth 2 — Grand public
    private DepthContract           $contract10; // Depth 10 — Expert avancé

    protected function setUp(): void
    {
        parent::setUp();
        $this->vdi       = new ValidationDominantIdeas();
        $this->contract2  = DepthContractRegistry::get(2);
        $this->contract10 = DepthContractRegistry::get(10);
    }

    // =========================================================================
    // §37 — ValidationResult DTO
    // =========================================================================

    public function test_validation_result_pass(): void
    {
        $r = ValidationResult::pass();

        $this->assertTrue($r->isPass());
        $this->assertFalse($r->isFail());
        $this->assertSame(ValidationResult::STATUS_PASS, $r->status);
        $this->assertNull($r->reason);
        $this->assertNull($r->conflictWith);
    }

    public function test_validation_result_fail_with_reason(): void
    {
        $r = ValidationResult::fail(FailReason::DUPLICATE, 'Acte de l\'Amérique du Nord britannique');

        $this->assertTrue($r->isFail());
        $this->assertFalse($r->isPass());
        $this->assertSame(FailReason::DUPLICATE, $r->reason);
        $this->assertSame('Acte de l\'Amérique du Nord britannique', $r->conflictWith);
    }

    public function test_validation_result_fail_without_conflict(): void
    {
        $r = ValidationResult::fail(FailReason::GENERIC_CATEGORY);

        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::GENERIC_CATEGORY, $r->reason);
        $this->assertNull($r->conflictWith);
    }

    // =========================================================================
    // §38 — validateOne() : R01 FORMAT_MINIMAL_IRREDUCTIBLE
    // =========================================================================

    /** R01 : phrase trop longue (> 6 mots) → FAIL */
    public function test_phrase_too_long_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Le fait que la Confédération canadienne a eu lieu en 1867',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION, $r->reason);
    }

    /** R01 : indicateurs de phrase → FAIL */
    /** @dataProvider phraseIndicatorProvider */
    public function test_phrase_indicators_fail(string $candidate): void
    {
        $r = $this->vdi->validateOne($candidate, 'Histoire', 'Canada', 'Confédération canadienne', $this->contract2);
        $this->assertTrue($r->isFail(), "'{$candidate}' devrait être FAIL (indicateur de phrase)");
        $this->assertSame(FailReason::FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION, $r->reason);
    }

    public static function phraseIndicatorProvider(): array
    {
        return [
            ['Le fait que la Canada était une colonie'],
            ['La façon dont les Pères ont signé'],
            ['Comment le parlement fonctionne'],
            ['Pourquoi la confédération a été créée'],
        ];
    }

    // =========================================================================
    // §38 — validateOne() : R02 GENERIC_CATEGORY
    // =========================================================================

    /** R02 : catégorie générique simple → FAIL */
    public function test_generic_category_fails(): void
    {
        $r = $this->vdi->validateOne('date', 'Histoire', 'Canada', 'Confédération canadienne', $this->contract2);
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::GENERIC_CATEGORY, $r->reason);
    }

    /** R02 : variantes de catégories génériques */
    /** @dataProvider genericCategoryProvider */
    public function test_generic_categories_all_fail(string $candidate): void
    {
        $r = $this->vdi->validateOne($candidate, 'Histoire', 'Canada', 'Confédération canadienne', $this->contract2);
        $this->assertTrue($r->isFail(), "'{$candidate}' devrait être FAIL comme catégorie générique");
        $this->assertSame(FailReason::GENERIC_CATEGORY, $r->reason);
    }

    public static function genericCategoryProvider(): array
    {
        return [
            ['Date'], ['Dates'], ['Personnage'], ['Personnages'],
            ['Cause'], ['Causes'], ['Conséquence'], ['Conséquences'],
            ['Lieu'], ['Lieux'], ['Document'], ['Documents'],
            ['Événement'], ['Événements'],
        ];
    }

    /** R02 : catégorie générique en minuscules → FAIL */
    public function test_generic_category_lowercase_fails(): void
    {
        $r = $this->vdi->validateOne('dates', 'Histoire', 'Canada', 'Confédération canadienne', $this->contract2);
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::GENERIC_CATEGORY, $r->reason);
    }

    // =========================================================================
    // §38 — validateOne() : R05 NOT_DOMINANT (méta-description)
    // =========================================================================

    /** R05 : méta-description "Histoire de X" → FAIL */
    public function test_meta_description_histoire_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Histoire de la Confédération',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::NOT_DOMINANT, $r->reason);
    }

    /** R05 : méta-description "Rôle de X" → FAIL */
    public function test_meta_description_role_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Rôle de Macdonald',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::NOT_DOMINANT, $r->reason);
    }

    /** R05 : méta-description "Impact de X" → FAIL */
    public function test_meta_description_impact_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Impact de la Confédération',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::NOT_DOMINANT, $r->reason);
    }

    // =========================================================================
    // §38 — validateOne() : R04 TOO_BROAD
    // =========================================================================

    /** R04 : conjonction "et" entre deux concepts → FAIL */
    public function test_too_broad_with_et_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Économie et gouvernement',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::TOO_BROAD, $r->reason);
    }

    /** R04 : conjonction "ou" entre deux concepts → FAIL */
    public function test_too_broad_with_ou_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Monarchie ou République',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::TOO_BROAD, $r->reason);
    }

    /** R04 : idée unitaire sans conjonction → PASS sur cet axe */
    public function test_single_concept_not_too_broad(): void
    {
        $r = $this->vdi->validateOne(
            'Acte de l\'Amérique du Nord britannique',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        // Ne doit pas être rejeté pour TOO_BROAD
        $this->assertNotSame(FailReason::TOO_BROAD, $r->reason);
    }

    // =========================================================================
    // §38 — validateOne() : R03 SUBJECT_REPETITION
    // =========================================================================

    /** R03 : idée = sujet → FAIL */
    public function test_subject_repetition_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Confédération canadienne',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::SUBJECT_REPETITION, $r->reason);
    }

    // =========================================================================
    // §38 — validateOne() : R10 OUTSIDE_DEPTH
    // =========================================================================

    /** R10 : vocabulaire ultra-technique pour Depth 2 → FAIL */
    public function test_outside_depth_too_technical_for_low_depth_fails(): void
    {
        // Deux suffixes techniques dans une même idée
        $r = $this->vdi->validateOne(
            'Cytologie histologie',
            'Science', 'Biologie', 'Cellule',
            $this->contract2  // Depth 2 : Grand public
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::OUTSIDE_DEPTH, $r->reason);
    }

    /** R10 : idée triviale mono-syllabique pour Depth 10 → FAIL */
    public function test_outside_depth_too_trivial_for_high_depth_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Loi',
            'Histoire', 'Droit constitutionnel', 'Jurisprudence',
            $this->contract10  // Depth 10 : Expert avancé
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::OUTSIDE_DEPTH, $r->reason);
    }

    /** R10 : idée normale pour Depth 2 → PASS sur cet axe */
    public function test_normal_idea_passes_depth_check(): void
    {
        $r = $this->vdi->validateOne(
            'John A. Macdonald',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertNotSame(FailReason::OUTSIDE_DEPTH, $r->reason);
    }

    // =========================================================================
    // §38 — validateOne() : R06 DUPLICATE
    // =========================================================================

    /** R06 : doublon exact → FAIL */
    public function test_duplicate_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Acte de l\'Amérique du Nord britannique',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2,
            passIdeas: ['Acte de l\'Amérique du Nord britannique']
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::DUPLICATE, $r->reason);
    }

    // =========================================================================
    // §38 — validateOne() : R07 LEXICAL_EQUIVALENCE
    // =========================================================================

    /** R07 : équivalence lexicale par inclusion → FAIL */
    public function test_lexical_equivalence_by_inclusion_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Provinces fondatrices canadiennes',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2,
            passIdeas: ['Provinces fondatrices']
        );
        $this->assertTrue($r->isFail());
        $this->assertContains($r->reason, [FailReason::LEXICAL_EQUIVALENCE, FailReason::CONCEPTUAL_COLLISION]);
    }

    /** R07 : reformulation d'une idée rejetée → FAIL */
    public function test_reformulation_of_rejected_idea_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Provinces canadiennes fondatrices',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2,
            passIdeas: [],
            failIdeas: ['Provinces fondatrices']
        );
        $this->assertTrue($r->isFail());
    }

    // =========================================================================
    // §38 — validateOne() : R08 CONCEPTUAL_COLLISION
    // =========================================================================

    /** R08 : mêmes mots-clés dominants (≥5 chars) → CONCEPTUAL_COLLISION */
    public function test_conceptual_collision_via_shared_keywords_fails(): void
    {
        // "Constitution" partage la racine "const" avec "Acte constitutionnel"
        $r = $this->vdi->validateOne(
            'Acte constitutionnel 1867',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2,
            passIdeas: ['Constitution de 1867']
        );
        $this->assertTrue($r->isFail());
        $this->assertContains($r->reason, [
            FailReason::LEXICAL_EQUIVALENCE,
            FailReason::CONCEPTUAL_COLLISION,
            FailReason::DUPLICATE,
        ]);
    }

    // =========================================================================
    // §38 — validateOne() : R09 ALREADY_COVERED
    // =========================================================================

    /** R09 : direction déjà couverte → FAIL */
    public function test_covered_direction_fails(): void
    {
        $r = $this->vdi->validateOne(
            'Acte de l\'Amérique du Nord britannique',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2,
            passIdeas: [],
            failIdeas: [],
            coveredDirections: ['Acte de l\'Amérique du Nord britannique']
        );
        $this->assertTrue($r->isFail());
        $this->assertSame(FailReason::ALREADY_COVERED, $r->reason);
    }

    // =========================================================================
    // §38 — validateOne() : Idées valides → PASS
    // =========================================================================

    /** Idée valide et unique → PASS */
    public function test_valid_idea_passes(): void
    {
        $r = $this->vdi->validateOne(
            'George-Étienne Cartier',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2,
            passIdeas: ['John A. Macdonald']
        );
        $this->assertTrue($r->isPass());
    }

    /** Idée courte et unique → PASS */
    public function test_short_unique_idea_passes(): void
    {
        $r = $this->vdi->validateOne(
            '1867',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isPass());
    }

    /** Idée valide sans existantes → PASS */
    public function test_valid_idea_with_no_existing_passes(): void
    {
        $r = $this->vdi->validateOne(
            'Provinces fondatrices',
            'Histoire', 'Canada', 'Confédération canadienne',
            $this->contract2
        );
        $this->assertTrue($r->isPass());
    }

    // =========================================================================
    // §39 — validateDiversity() : validation collective AVANT persistance
    // =========================================================================

    /** Ensemble varié → null (pas de problème) */
    public function test_diverse_set_returns_null(): void
    {
        $ideas = [
            'John A. Macdonald',
            '1867',
            'Provinces fondatrices',
            'Acte de l\'Amérique du Nord britannique',
        ];
        $result = $this->vdi->validateDiversity($ideas);
        $this->assertNull($result);
    }

    /** Moins de 3 idées → pas de validation diversité */
    public function test_two_ideas_skip_diversity_check(): void
    {
        $result = $this->vdi->validateDiversity(['John A. Macdonald', '1867']);
        $this->assertNull($result);
    }

    /** 4/4 noms de personnages → FAIL diversité */
    public function test_all_person_names_fails_diversity(): void
    {
        $ideas = [
            'John A. Macdonald',
            'George-Étienne Cartier',
            'Samuel Leonard Tilley',
            'Charles Tupper',
        ];
        $result = $this->vdi->validateDiversity($ideas);
        $this->assertNotNull($result);
        $this->assertTrue($result->isFail());
        $this->assertSame(FailReason::SET_DIVERSITY_COLLISION, $result->reason);
    }

    /** ≥75% de noms de personnages dans ≥4 idées → FAIL diversité */
    public function test_mostly_person_names_fails_diversity(): void
    {
        $ideas = [
            'John A. Macdonald',
            'George-Étienne Cartier',
            'Samuel Leonard Tilley',
            '1867',
        ];
        // 3/4 = 75% ≥ 0.75 → FAIL
        $result = $this->vdi->validateDiversity($ideas);
        $this->assertNotNull($result);
        $this->assertTrue($result->isFail());
        $this->assertSame(FailReason::SET_DIVERSITY_COLLISION, $result->reason);
    }

    /**
     * §39 — Invariant critique : validateDiversity() est conçu pour être
     * appelé AVANT persistance. Si la dernière candidate cause la collision,
     * elle doit être persistée comme FAIL, pas d'abord comme PASS.
     *
     * Ce test vérifie que la fonction identifie correctement la 4ème idée
     * comme problématique dans l'ensemble prospectif passé en argument.
     */
    public function test_diversity_check_before_persistence_identifies_culprit(): void
    {
        // Ensemble de 3 idées acceptables
        $accepted = [
            'John A. Macdonald',
            'George-Étienne Cartier',
            'Samuel Leonard Tilley',
        ];
        $this->assertNull($this->vdi->validateDiversity($accepted));

        // Ajout d'une 4ème candidate → 4/4 = 100% personnes → FAIL
        $prospective = array_merge($accepted, ['Charles Tupper']);
        $result = $this->vdi->validateDiversity($prospective);
        $this->assertNotNull($result);
        $this->assertTrue($result->isFail());
        $this->assertSame(FailReason::SET_DIVERSITY_COLLISION, $result->reason);
    }

    // =========================================================================
    // FailReason — classification PHP vs Gemini
    // =========================================================================

    public function test_fail_reason_php_enforced_are_non_empty(): void
    {
        $all = FailReason::phpEnforced();
        $this->assertNotEmpty($all);
        $this->assertContains(FailReason::DUPLICATE, $all);
        $this->assertContains(FailReason::GENERIC_CATEGORY, $all);
        $this->assertContains(FailReason::TOO_BROAD, $all);
        $this->assertContains(FailReason::NOT_DOMINANT, $all);
        $this->assertContains(FailReason::OUTSIDE_DEPTH, $all);
        $this->assertContains(FailReason::CONCEPTUAL_COLLISION, $all);
        $this->assertContains(FailReason::SET_DIVERSITY_COLLISION, $all);
        $this->assertContains(FailReason::FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION, $all);
    }

    public function test_fail_reason_gemini_enforced_are_non_empty(): void
    {
        $gemini = FailReason::geminiEnforced();
        $this->assertNotEmpty($gemini);
        $this->assertContains(FailReason::OUTSIDE_DOMAIN, $gemini);
        $this->assertContains(FailReason::OUTSIDE_SUBDOMAIN, $gemini);
        $this->assertContains(FailReason::PEDAGOGICAL_COLLISION, $gemini);
        $this->assertContains(FailReason::TOO_NARROW, $gemini);
    }

    public function test_php_and_gemini_enforced_are_disjoint(): void
    {
        $php    = FailReason::phpEnforced();
        $gemini = FailReason::geminiEnforced();
        $common = array_intersect($php, $gemini);
        $this->assertEmpty($common, 'Un code ne peut pas être à la fois PHP-enforced et Gemini-enforced.');
    }

    public function test_fail_reason_all_is_union_of_php_and_gemini(): void
    {
        $all    = FailReason::all();
        $php    = FailReason::phpEnforced();
        $gemini = FailReason::geminiEnforced();
        $union  = array_merge($php, $gemini);

        sort($all);
        sort($union);
        $this->assertSame($union, $all);
    }
}
