<?php

namespace Tests\Unit\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — KernelBlueprint Partie 1
 *
 * Vérifie la structure officielle, les responsabilités d'écriture
 * et les règles architecturales du Blueprint.
 *
 * Aucune DB, aucun appel AI. Étend PHPUnit\Framework\TestCase directement.
 *
 * Structure testée :
 *   depth               ← KernelRotationPlanner
 *   domain              ← KernelRotationPlanner
 *   subdomain_active    ← Taxonomy
 *   subject_active      ← Taxonomy
 *   dominant_idea_active← Taxonomy
 *   kernel_code         ← KernelCodeEngine
 */
class KernelBlueprintPart1Test extends TestCase
{
    private function blueprint(): KernelBlueprint
    {
        return new KernelBlueprint();
    }

    // =========================================================================
    // 1. Structure initiale — 6 champs null
    // =========================================================================

    public function test_blueprint_is_instantiable(): void
    {
        $this->assertInstanceOf(KernelBlueprint::class, $this->blueprint());
    }

    public function test_depth_is_null_at_construction(): void
    {
        $this->assertNull($this->blueprint()->depth);
    }

    public function test_domain_is_null_at_construction(): void
    {
        $this->assertNull($this->blueprint()->domain);
    }

    public function test_subdomain_active_is_null_at_construction(): void
    {
        $this->assertNull($this->blueprint()->subdomain_active);
    }

    public function test_subject_active_is_null_at_construction(): void
    {
        $this->assertNull($this->blueprint()->subject_active);
    }

    public function test_dominant_idea_active_is_null_at_construction(): void
    {
        $this->assertNull($this->blueprint()->dominant_idea_active);
    }

    public function test_kernel_code_is_null_at_construction(): void
    {
        $this->assertNull($this->blueprint()->kernel_code);
    }

    // =========================================================================
    // 2. toArray() — exactement 6 clés, aucune règle
    // =========================================================================

    public function test_toArray_returns_array(): void
    {
        $this->assertIsArray($this->blueprint()->toArray());
    }

    public function test_toArray_has_exactly_6_keys(): void
    {
        $this->assertCount(6, $this->blueprint()->toArray());
    }

    public function test_toArray_contains_the_6_official_keys(): void
    {
        $expected = [
            'depth', 'domain', 'subdomain_active',
            'subject_active', 'dominant_idea_active', 'kernel_code',
        ];

        $actual = array_keys($this->blueprint()->toArray());
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_toArray_all_values_null_at_construction(): void
    {
        foreach ($this->blueprint()->toArray() as $key => $value) {
            $this->assertNull($value, "toArray()[{$key}] doit être null à la construction");
        }
    }

    /**
     * Le Blueprint ne stocke pas de règles.
     *
     * @dataProvider forbiddenKeysProvider
     */
    public function test_toArray_does_not_contain_rules_or_reservoir_keys(string $key): void
    {
        $this->assertArrayNotHasKey(
            $key,
            $this->blueprint()->toArray(),
            "Clé interdite '{$key}' trouvée dans le Blueprint — les règles/réservoirs restent externes."
        );
    }

    public static function forbiddenKeysProvider(): array
    {
        return [
            // Anciens slots réservoir
            ['subjects_inventory'],
            ['dominant_ideas'],
            ['active_subject'],
            ['active_dominant_idea'],
            // Règles et métadonnées
            ['rules'],
            ['mechanisms'],
            ['constraints'],
            ['object_contracts'],
            ['relation_map'],
            // Anciens identifiants techniques
            ['rotation_identifier'],
            ['domain_code'],
            ['sub_domain'],
            ['subject'],
            ['dominant_idea'],
            // Champs mécanismes (Partie 2+)
            ['kld_result'],
            ['ks_result'],
            ['semantic_key'],
            ['intent_hash'],
            // Réservoir Taxonomy
            ['knowledge_frequency'],
            ['history'],
            ['remaining_subjects'],
            ['remaining_ideas'],
        ];
    }

    // =========================================================================
    // 3. fillRotation — responsabilité KernelRotationPlanner
    // =========================================================================

    public function test_fillRotation_sets_depth(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');

        $this->assertSame(4, $bp->depth);
    }

    public function test_fillRotation_sets_domain(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');

        $this->assertSame('science', $bp->domain);
    }

    public function test_fillRotation_does_not_touch_taxonomy_fields(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');

        $this->assertNull($bp->subdomain_active,     'fillRotation ne doit pas toucher subdomain_active');
        $this->assertNull($bp->subject_active,        'fillRotation ne doit pas toucher subject_active');
        $this->assertNull($bp->dominant_idea_active,  'fillRotation ne doit pas toucher dominant_idea_active');
    }

    public function test_fillRotation_does_not_touch_kernel_code(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');

        $this->assertNull($bp->kernel_code, 'fillRotation ne doit pas toucher kernel_code');
    }

    public function test_fillRotation_accepts_all_valid_depths(): void
    {
        foreach (range(1, 10) as $depth) {
            $bp = $this->blueprint();
            $bp->fillRotation($depth, 'géographie');
            $this->assertSame($depth, $bp->depth, "fillRotation doit accepter depth={$depth}");
        }
    }

    // =========================================================================
    // 4. fillTaxonomy — responsabilité Taxonomy
    // =========================================================================

    public function test_fillTaxonomy_sets_subdomain_active(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertSame('Physique', $bp->subdomain_active);
    }

    public function test_fillTaxonomy_sets_subject_active(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertSame('Lumière', $bp->subject_active);
    }

    public function test_fillTaxonomy_sets_dominant_idea_active(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertSame('réfraction', $bp->dominant_idea_active);
    }

    public function test_fillTaxonomy_does_not_overwrite_rotation_fields(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(6, 'histoire');
        $bp->fillTaxonomy('Révolutions', 'Bastille', 'prise_1789');

        $this->assertSame(6,         $bp->depth,  'fillTaxonomy ne doit pas modifier depth');
        $this->assertSame('histoire', $bp->domain, 'fillTaxonomy ne doit pas modifier domain');
    }

    public function test_fillTaxonomy_does_not_touch_kernel_code(): void
    {
        $bp = $this->blueprint();
        $bp->fillTaxonomy('SD', 'S', 'I');

        $this->assertNull($bp->kernel_code, 'fillTaxonomy ne doit pas toucher kernel_code');
    }

    // =========================================================================
    // 5. fillKernelCode — responsabilité KernelCodeEngine
    // =========================================================================

    public function test_fillKernelCode_sets_kernel_code(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');
        $bp->fillKernelCode('04-sc-phy-lum-ref-01');

        $this->assertSame('04-sc-phy-lum-ref-01', $bp->kernel_code);
    }

    public function test_fillKernelCode_does_not_overwrite_rotation_fields(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillKernelCode('04-sc-phy-lum-ref-01');

        $this->assertSame(4,         $bp->depth,  'fillKernelCode ne doit pas modifier depth');
        $this->assertSame('science', $bp->domain, 'fillKernelCode ne doit pas modifier domain');
    }

    public function test_fillKernelCode_does_not_overwrite_taxonomy_fields(): void
    {
        $bp = $this->blueprint();
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');
        $bp->fillKernelCode('04-sc-phy-lum-ref-01');

        $this->assertSame('Physique',    $bp->subdomain_active,     'fillKernelCode ne doit pas modifier subdomain_active');
        $this->assertSame('Lumière',     $bp->subject_active,        'fillKernelCode ne doit pas modifier subject_active');
        $this->assertSame('réfraction',  $bp->dominant_idea_active,  'fillKernelCode ne doit pas modifier dominant_idea_active');
    }

    // =========================================================================
    // 6. Helpers d'état
    // =========================================================================

    public function test_isRotationFilled_false_when_empty(): void
    {
        $this->assertFalse($this->blueprint()->isRotationFilled());
    }

    public function test_isRotationFilled_false_when_only_depth_set(): void
    {
        $bp = $this->blueprint();
        $bp->depth = 4;

        $this->assertFalse($bp->isRotationFilled());
    }

    public function test_isRotationFilled_false_when_only_domain_set(): void
    {
        $bp = $this->blueprint();
        $bp->domain = 'science';

        $this->assertFalse($bp->isRotationFilled());
    }

    public function test_isRotationFilled_true_after_fillRotation(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');

        $this->assertTrue($bp->isRotationFilled());
    }

    public function test_isTaxonomyFilled_false_when_empty(): void
    {
        $this->assertFalse($this->blueprint()->isTaxonomyFilled());
    }

    public function test_isTaxonomyFilled_false_when_only_subdomain_set(): void
    {
        $bp = $this->blueprint();
        $bp->subdomain_active = 'Physique';

        $this->assertFalse($bp->isTaxonomyFilled());
    }

    public function test_isTaxonomyFilled_true_after_fillTaxonomy(): void
    {
        $bp = $this->blueprint();
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertTrue($bp->isTaxonomyFilled());
    }

    public function test_isIdentityComplete_false_when_only_rotation_filled(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');

        $this->assertFalse($bp->isIdentityComplete());
    }

    public function test_isIdentityComplete_false_when_only_taxonomy_filled(): void
    {
        $bp = $this->blueprint();
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertFalse($bp->isIdentityComplete());
    }

    public function test_isIdentityComplete_true_when_rotation_and_taxonomy_filled(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertTrue($bp->isIdentityComplete());
    }

    public function test_isComplete_false_when_identity_complete_but_no_kernel_code(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertFalse($bp->isComplete());
    }

    public function test_isComplete_true_when_all_6_fields_filled(): void
    {
        $bp = $this->blueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');
        $bp->fillKernelCode('04-sc-phy-lum-ref-01');

        $this->assertTrue($bp->isComplete());
    }

    // =========================================================================
    // 7. Scénario pipeline complet (séquence Rotation → Taxonomy → KernelCodeEngine)
    // =========================================================================

    public function test_full_pipeline_sequence_produces_correct_state(): void
    {
        $bp = new KernelBlueprint();

        // Étape 1 — KernelRotationPlanner
        $bp->fillRotation(6, 'histoire');

        $this->assertTrue($bp->isRotationFilled());
        $this->assertFalse($bp->isTaxonomyFilled());
        $this->assertFalse($bp->isIdentityComplete());
        $this->assertFalse($bp->isComplete());

        // Étape 2 — Taxonomy
        $bp->fillTaxonomy('Révolutions', 'Bastille', 'prise_1789');

        $this->assertTrue($bp->isRotationFilled());
        $this->assertTrue($bp->isTaxonomyFilled());
        $this->assertTrue($bp->isIdentityComplete());
        $this->assertFalse($bp->isComplete());

        // Étape 3 — KernelCodeEngine
        $bp->fillKernelCode('06-hi-rev-bas-pr1-01');

        $this->assertTrue($bp->isComplete());
        $this->assertSame('06-hi-rev-bas-pr1-01', $bp->kernel_code);
    }

    public function test_full_pipeline_toArray_reflects_all_writes(): void
    {
        $bp = new KernelBlueprint();
        $bp->fillRotation(6, 'histoire');
        $bp->fillTaxonomy('Révolutions', 'Bastille', 'prise_1789');
        $bp->fillKernelCode('06-hi-rev-bas-pr1-01');

        $arr = $bp->toArray();

        $this->assertSame(6,                   $arr['depth']);
        $this->assertSame('histoire',            $arr['domain']);
        $this->assertSame('Révolutions',         $arr['subdomain_active']);
        $this->assertSame('Bastille',            $arr['subject_active']);
        $this->assertSame('prise_1789',          $arr['dominant_idea_active']);
        $this->assertSame('06-hi-rev-bas-pr1-01', $arr['kernel_code']);
    }

    // =========================================================================
    // 8. Taxonomy lit depth + domain mais ne les modifie jamais
    // =========================================================================

    public function test_taxonomy_reads_rotation_but_cannot_overwrite_it(): void
    {
        $bp = new KernelBlueprint();
        $bp->fillRotation(8, 'géographie');

        // Taxonomy lit depth + domain pour travailler dans le bon réservoir,
        // mais ne les modifie pas (pas de paramètre depth/domain dans fillTaxonomy).
        $bp->fillTaxonomy('Capitales', 'Nairobi', 'hub_économique');

        $this->assertSame(8,            $bp->depth,  'depth doit rester inchangé après fillTaxonomy');
        $this->assertSame('géographie', $bp->domain, 'domain doit rester inchangé après fillTaxonomy');
    }
}
