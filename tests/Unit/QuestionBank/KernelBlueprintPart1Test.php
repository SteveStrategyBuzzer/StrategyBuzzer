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
 * Structure Section 1 testée :
 *   blueprint_id        ← KernelBlueprintFactory
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

    private function identifiedBlueprint(): KernelBlueprint
    {
        $blueprint = $this->blueprint();
        $blueprint->initializeBlueprintId('bp-section-1');

        return $blueprint;
    }

    // =========================================================================
    // 1. Structure initiale — identité + 6 champs null
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

    public function test_empty_blueprint_exposes_null_kernel_code_projection(): void
    {
        $this->assertNull($this->blueprint()->kernelCodeProjection());
    }

    public function test_blueprint_id_is_null_at_construction(): void
    {
        $this->assertNull($this->blueprint()->blueprint_id);
    }

    // =========================================================================
    // 2. toArray() — identité + 6 clés, aucune règle
    // =========================================================================

    public function test_toArray_returns_array(): void
    {
        $this->assertIsArray($this->blueprint()->toArray());
    }

    public function test_toArray_has_exactly_14_keys(): void
    {
        $this->assertCount(14, $this->blueprint()->toArray());
    }

    public function test_toArray_contains_identity_and_exact_segment_keys(): void
    {
        $expected = [
            'blueprint_id',
            'depth', 'domain', 'subdomain_active',
            'subject_active', 'dominant_idea_active',
            'kernel_code_dd', 'kernel_code_do', 'kernel_code_sub',
            'kernel_code_suj', 'kernel_code_ide', 'kernel_code_vvvv',
            'kernel_code',
            'cognitive_slots',
        ];

        $actual = array_keys($this->blueprint()->toArray());
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_toArray_all_values_null_at_construction(): void
    {
        foreach ($this->blueprint()->toArray() as $key => $value) {
            if ($key === 'cognitive_slots') {
                $this->assertSame([], $value);
                continue;
            }
            $this->assertNull($value, "toArray()[{$key}] doit être null à la construction");
        }
    }

    public function test_blueprint_exposes_exactly_seven_initialized_cognitive_slots(): void
    {
        $blueprint = $this->identifiedBlueprint();
        $slots = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $slots[$type] = [
                'cognitive_type' => $type,
                'creation_status' => 'EMPTY',
            ];
        }

        $blueprint->initializeCognitiveSlots($slots);

        $this->assertSame($slots, $blueprint->cognitive_slots);
        $this->assertCount(7, $blueprint->cognitive_slots);
    }

    public function test_blueprint_rejects_an_eighth_cognitive_type(): void
    {
        $blueprint = $this->identifiedBlueprint();
        $slots = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $slots[$type] = ['cognitive_type' => $type];
        }
        $slots['EIGHTH_TYPE'] = ['cognitive_type' => 'EIGHTH_TYPE'];

        $this->expectException(\LogicException::class);
        $blueprint->initializeCognitiveSlots($slots);
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
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');

        $this->assertSame(4, $bp->depth);
    }

    public function test_fillRotation_sets_domain(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');

        $this->assertSame('SCI', $bp->domain);
    }

    public function test_fillRotation_does_not_touch_taxonomy_fields(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');

        $this->assertNull($bp->subdomain_active,     'fillRotation ne doit pas toucher subdomain_active');
        $this->assertNull($bp->subject_active,        'fillRotation ne doit pas toucher subject_active');
        $this->assertNull($bp->dominant_idea_active,  'fillRotation ne doit pas toucher dominant_idea_active');
    }

    public function test_fillRotation_does_not_touch_kernel_code(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');

        $this->assertNull($bp->kernel_code, 'fillRotation ne doit pas toucher kernel_code');
    }

    public function test_fillRotation_projects_depth_and_domain(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(2, 'histoire');

        $this->assertSame('HIS', $bp->domain);
        $this->assertNull($bp->kernelCodeProjection());
    }

    public function test_fillRotation_accepts_all_valid_depths(): void
    {
        foreach ([2, 4, 6, 7, 8, 9, 10] as $depth) {
            $bp = $this->identifiedBlueprint();
            $bp->fillRotation($depth, 'Géographie');
            $this->assertSame($depth, $bp->depth, "fillRotation doit accepter depth={$depth}");
        }
    }

    public function test_fillRotation_requires_canonical_identity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Identité canonique requise/');

        $this->blueprint()->fillRotation(4, 'science');
    }

    // =========================================================================
    // 4. fillTaxonomy — responsabilité Taxonomy
    // =========================================================================

    public function test_fillTaxonomy_sets_subdomain_active(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertSame('Physique', $bp->subdomain_active);
    }

    public function test_fillTaxonomy_sets_subject_active(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertSame('Lumière', $bp->subject_active);
    }

    public function test_fillTaxonomy_sets_dominant_idea_active(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertSame('réfraction', $bp->dominant_idea_active);
    }

    public function test_fillTaxonomy_does_not_overwrite_rotation_fields(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(6, 'histoire');
        $bp->fillTaxonomy('Révolutions', 'Bastille', 'prise_1789');

        $this->assertSame(6,         $bp->depth,  'fillTaxonomy ne doit pas modifier depth');
        $this->assertSame('HIS', $bp->domain, 'fillTaxonomy ne doit pas modifier domain');
    }

    public function test_fillTaxonomy_does_not_touch_kernel_code(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('SD', 'S', 'I');

        $this->assertNull($bp->kernel_code, 'fillTaxonomy ne doit pas toucher kernel_code');
    }

    public function test_fillTaxonomy_projects_complete_intellectual_identity(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(2, 'histoire');
        $bp->fillTaxonomy('Rome', 'César', 'Conquête');

        $this->assertNull($bp->kernelCodeProjection());
    }

    public function test_fillTaxonomy_requires_rotation(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Rotation requise/');

        $this->identifiedBlueprint()->fillTaxonomy('Physique', 'Lumière', 'réfraction');
    }

    // =========================================================================
    // 5. fillVvvv — responsabilité exclusive de QuestionIntent/KernelCodeEngine
    //
    // Il n'existe plus de méthode acceptant un kernel_code complet : seule
    // fillVvvv() peut écrire ce dernier segment, et uniquement à partir d'un
    // suffixe déjà alloué par PostgreSQL (KernelCodeEngine). Ceci prouve que
    // l'injection manuelle d'un kernel_code complet est neutralisée au niveau
    // du Blueprint lui-même.
    // =========================================================================

    public function test_fillVvvv_sets_kernel_code_projection(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');
        $bp->fillVvvv('0001');

        $this->assertSame('04-SCI-PHY-LUM-REF-0001', $bp->kernel_code);
        $this->assertSame($bp->kernel_code, $bp->kernelCodeProjection());
    }

    public function test_fillVvvv_does_not_overwrite_rotation_fields(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');
        $bp->fillVvvv('0001');

        $this->assertSame(4,         $bp->depth,  'fillVvvv ne doit pas modifier depth');
        $this->assertSame('SCI', $bp->domain, 'fillVvvv ne doit pas modifier domain');
    }

    public function test_fillVvvv_does_not_overwrite_taxonomy_fields(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');
        $bp->fillVvvv('0001');

        $this->assertSame('Physique',    $bp->subdomain_active,     'fillVvvv ne doit pas modifier subdomain_active');
        $this->assertSame('Lumière',     $bp->subject_active,        'fillVvvv ne doit pas modifier subject_active');
        $this->assertSame('réfraction',  $bp->dominant_idea_active,  'fillVvvv ne doit pas modifier dominant_idea_active');
    }

    public function test_fillVvvv_requires_rotation_and_taxonomy(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Taxonomy complète et VVVV canonique requis/');

        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillVvvv('0001');
    }

    public function test_no_method_accepts_a_complete_kernel_code(): void
    {
        // Preuve négative : l'entrée qui permettait autrefois d'injecter
        // manuellement un kernel_code complet (fillKernelCode) n'existe plus.
        $this->assertFalse(
            method_exists(KernelBlueprint::class, 'fillKernelCode'),
            'KernelBlueprint ne doit exposer aucune méthode acceptant un kernel_code complet.'
        );
    }

    // =========================================================================
    // 6. Helpers d'état
    // =========================================================================

    public function test_isRotationFilled_false_when_empty(): void
    {
        $this->assertFalse($this->blueprint()->isRotationFilled());
    }

    public function test_isRotationFilled_true_after_fillRotation(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');

        $this->assertTrue($bp->isRotationFilled());
    }

    public function test_isTaxonomyFilled_false_when_empty(): void
    {
        $this->assertFalse($this->blueprint()->isTaxonomyFilled());
    }

    public function test_isTaxonomyFilled_true_after_fillTaxonomy(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertTrue($bp->isTaxonomyFilled());
    }

    public function test_isIdentityComplete_false_when_only_rotation_filled(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');

        $this->assertFalse($bp->isIdentityComplete());
    }

    public function test_isIdentityComplete_false_before_rotation_and_taxonomy(): void
    {
        $bp = $this->identifiedBlueprint();
        $this->assertFalse($bp->isIdentityComplete());
    }

    public function test_isIdentityComplete_true_when_rotation_and_taxonomy_filled(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertTrue($bp->isIdentityComplete());
    }

    public function test_isComplete_false_when_identity_complete_but_no_kernel_code(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');

        $this->assertFalse($bp->isComplete());
    }

    public function test_isComplete_true_when_section_1_identity_and_6_fields_are_filled(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('Physique', 'Lumière', 'réfraction');
        $bp->fillVvvv('0001');

        $this->assertTrue($bp->isComplete());
    }

    // =========================================================================
    // 7. Scénario pipeline complet (identité → Rotation → Taxonomy → KernelCodeEngine)
    // =========================================================================

    public function test_full_pipeline_sequence_produces_correct_state(): void
    {
        $bp = $this->identifiedBlueprint();

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
        $bp->fillVvvv('0001');

        $this->assertTrue($bp->isComplete());
        $this->assertSame('06-HIS-REV-BAS-PRI-0001', $bp->kernel_code);
    }

    public function test_full_pipeline_toArray_reflects_all_writes(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(6, 'histoire');
        $bp->fillTaxonomy('Révolutions', 'Bastille', 'prise_1789');
        $bp->fillVvvv('0001');

        $arr = $bp->toArray();

        $this->assertSame(6,                   $arr['depth']);
        $this->assertSame('HIS',                 $arr['domain']);
        $this->assertSame('Révolutions',         $arr['subdomain_active']);
        $this->assertSame('Bastille',            $arr['subject_active']);
        $this->assertSame('prise_1789',          $arr['dominant_idea_active']);
        $this->assertSame('06-HIS-REV-BAS-PRI-0001', $arr['kernel_code']);
        $this->assertSame('bp-section-1',         $arr['blueprint_id']);
    }

    // =========================================================================
    // 8. Taxonomy lit depth + domain mais ne les modifie jamais
    // =========================================================================

    public function test_taxonomy_reads_rotation_but_cannot_overwrite_it(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(8, 'Géographie');

        // Taxonomy lit depth + domain pour travailler dans le bon réservoir,
        // mais ne les modifie pas (pas de paramètre depth/domain dans fillTaxonomy).
        $bp->fillTaxonomy('Capitales', 'Nairobi', 'hub_économique');

        $this->assertSame(8,            $bp->depth,  'depth doit rester inchangé après fillTaxonomy');
        $this->assertSame('GEO', $bp->domain, 'domain doit rester inchangé après fillTaxonomy');
    }

    // =========================================================================
    // 9. Contrat write-once — écriture directe externe interdite (B2)
    // =========================================================================

    public function test_direct_write_throws_logic_exception(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Écriture directe interdite/');

        $bp = $this->identifiedBlueprint();
        $bp->depth = 4; // doit lever une LogicException
    }

    public function test_blueprint_id_direct_write_throws(): void
    {
        $this->expectException(\LogicException::class);
        $bp = new KernelBlueprint();
        $bp->blueprint_id = 'should-fail';
    }

    public function test_initializeBlueprintId_write_once_throws_on_second_call(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/write-once violation/');

        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId('first-id');
        $bp->initializeBlueprintId('second-id'); // doit lever une LogicException
    }

    public function test_fillRotation_write_once_throws_on_second_call(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/write-once violation/');

        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillRotation(6, 'histoire'); // doit lever une LogicException
    }

    public function test_fillTaxonomy_write_once_throws_on_second_call(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/write-once violation/');

        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('SD1', 'S1', 'I1');
        $bp->fillTaxonomy('SD2', 'S2', 'I2'); // doit lever une LogicException
    }

    public function test_fillVvvv_write_once_throws_on_second_call_with_different_value(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/write-once violation/');

        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('SD1', 'S1', 'I1');
        $bp->fillVvvv('0001');
        $bp->fillVvvv('0002'); // doit lever une LogicException — VVVV immuable
    }

    public function test_fillVvvv_second_call_with_same_value_is_idempotent_noop(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(4, 'science');
        $bp->fillTaxonomy('SD1', 'S1', 'I1');
        $bp->fillVvvv('0001');
        $bp->fillVvvv('0001'); // rejeu identique — ne doit pas lever

        $this->assertSame('0001', $bp->kernel_code_vvvv);
    }

    public function test_read_via_magic_get_works_before_fill(): void
    {
        $bp = new KernelBlueprint();
        $this->assertNull($bp->depth);
        $this->assertNull($bp->domain);
        $this->assertNull($bp->blueprint_id);
        $this->assertNull($bp->kernel_code);
    }

    public function test_read_via_magic_get_works_after_fill(): void
    {
        $bp = $this->identifiedBlueprint();
        $bp->fillRotation(7, 'sport');
        $this->assertSame(7,       $bp->depth);
        $this->assertSame('SPO',   $bp->domain);
    }
}
