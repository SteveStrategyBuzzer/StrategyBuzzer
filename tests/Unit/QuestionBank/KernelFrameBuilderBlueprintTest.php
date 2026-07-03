<?php

namespace Tests\Unit\QuestionBank;

use App\Models\QuestionIntent;
use App\Services\QuestionBank\KernelFrameBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — Kernel Blueprint Frame
 *
 * KernelFrameBuilder est une pure function : lit un QuestionIntent,
 * retourne un array. Aucune DB, aucun appel AI, aucune migration.
 * Étend PHPUnit\Framework\TestCase directement (pas Tests\TestCase)
 * pour éviter RefreshDatabase et les migrations SQLite incompatibles.
 */
class KernelFrameBuilderBlueprintTest extends TestCase
{
    private KernelFrameBuilder $builder;
    private QuestionIntent $intent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new KernelFrameBuilder();
        $this->intent  = $this->makeIntent();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeIntent(array $overrides = []): QuestionIntent
    {
        $defaults = [
            'domain'           => 'Géographie',
            'sub_domain'       => 'Capitales',
            'difficulty_depth' => 4,
            'subject'          => 'Nairobi',
            'angle_large'      => 'économique',
            'micro_angle'      => 'hub régional',
            'answer_target'    => 'Nairobi est le principal hub économique d\'Afrique de l\'Est.',
            'potential_trap'   => 'Ne pas confondre avec Addis-Abeba (siège de l\'UA).',
            'concept_family'   => 'Afrique',
            'semantic_key'     => 'geo-cap-nairobi-economique-v1',
            'intent_key'       => 'geo|Capitales|Nairobi|economique|4',
            'language_source'  => 'en',
            'source'           => 'kernel',
        ];

        $attributes = array_merge($defaults, $overrides);

        $intent = new QuestionIntent();
        foreach ($attributes as $key => $value) {
            $intent->$key = $value;
        }

        return $intent;
    }

    private function skeleton(): array
    {
        return $this->builder->buildSkeleton($this->intent);
    }

    // =========================================================================
    // 1. Structure racine
    // =========================================================================

    public function test_returns_an_array(): void
    {
        $this->assertIsArray($this->skeleton());
    }

    public function test_contains_all_blueprint_frame_top_level_keys(): void
    {
        $frame = $this->skeleton();

        $expectedKeys = [
            // Blueprint Frame
            'kernel_code',
            'depth_slot',
            'domain_slot',
            'sub_domain_slot',
            'subjects_inventory',
            'active_subject',
            'dominant_ideas',
            'active_dominant_idea',
            'cognitive_slots',
            'rules',
            'mechanisms',
            'constraints',
            'statuses',
            'traces',
            // Legacy (backward compat)
            'kernel_core',
            'translation_constraints',
            'variants',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $frame, "Clé racine manquante : {$key}");
        }
    }

    // =========================================================================
    // 2. kernel_code
    // =========================================================================

    public function test_kernel_code_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['kernel_code']);
    }

    // =========================================================================
    // 3. depth_slot
    // =========================================================================

    public function test_depth_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['depth_slot'];

        foreach (['value', 'source', 'status', 'rules', 'traces'] as $key) {
            $this->assertArrayHasKey($key, $slot, "depth_slot manque : {$key}");
        }
    }

    public function test_depth_slot_prefills_from_intent_when_depth_present(): void
    {
        $slot = $this->skeleton()['depth_slot'];

        $this->assertSame(4, $slot['value']);
        $this->assertSame('legacy_intent', $slot['source']);
        $this->assertSame('filled', $slot['status']);
    }

    public function test_depth_slot_is_empty_when_intent_has_no_depth(): void
    {
        $this->intent = $this->makeIntent(['difficulty_depth' => null]);
        $slot = $this->skeleton()['depth_slot'];

        $this->assertNull($slot['value']);
        $this->assertNull($slot['source']);
        $this->assertSame('empty', $slot['status']);
    }

    public function test_depth_slot_rules_reference_rotation_planner(): void
    {
        $rules = $this->skeleton()['depth_slot']['rules'];

        $this->assertSame('KernelRotationPlanner', $rules['filler']);
        $this->assertSame('DepthNeedMatrix', $rules['driven_by']);
    }

    // =========================================================================
    // 4. domain_slot
    // =========================================================================

    public function test_domain_slot_prefills_from_intent(): void
    {
        $slot = $this->skeleton()['domain_slot'];

        $this->assertSame('Géographie', $slot['value']);
        $this->assertSame('legacy_intent', $slot['source']);
        $this->assertSame('filled', $slot['status']);
    }

    public function test_domain_slot_is_empty_when_intent_has_no_domain(): void
    {
        $this->intent = $this->makeIntent(['domain' => null]);
        $slot = $this->skeleton()['domain_slot'];

        $this->assertNull($slot['value']);
        $this->assertSame('empty', $slot['status']);
    }

    // =========================================================================
    // 5. sub_domain_slot
    // =========================================================================

    public function test_sub_domain_slot_prefills_from_intent(): void
    {
        $slot = $this->skeleton()['sub_domain_slot'];

        $this->assertSame('Capitales', $slot['value']);
        $this->assertSame('filled', $slot['status']);
    }

    // =========================================================================
    // 6. subjects_inventory
    // =========================================================================

    public function test_subjects_inventory_has_exactly_50_slots(): void
    {
        $inventory = $this->skeleton()['subjects_inventory'];

        $this->assertIsArray($inventory);
        $this->assertCount(50, $inventory);
    }

    public function test_subjects_inventory_slots_are_all_empty(): void
    {
        $inventory = $this->skeleton()['subjects_inventory'];

        foreach ($inventory as $i => $slot) {
            $this->assertNull($slot['value'], "Slot {$i} devrait être null.");
            $this->assertSame('available', $slot['status'], "Slot {$i} devrait avoir status=available.");
            $this->assertSame($i + 1, $slot['index'], "Slot {$i} devrait avoir index=" . ($i + 1) . ".");
        }
    }

    // =========================================================================
    // 7. active_subject / dominant_ideas / active_dominant_idea
    // =========================================================================

    public function test_active_subject_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['active_subject']);
    }

    public function test_dominant_ideas_is_empty_array_at_construction(): void
    {
        $ideas = $this->skeleton()['dominant_ideas'];
        $this->assertIsArray($ideas);
        $this->assertEmpty($ideas);
    }

    public function test_active_dominant_idea_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['active_dominant_idea']);
    }

    // =========================================================================
    // 8. cognitive_slots
    // =========================================================================

    public function test_cognitive_slots_has_exactly_7_entries(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];
        $this->assertCount(7, $slots);
    }

    public function test_cognitive_slots_has_all_7_variant_keys(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];

        $expectedKeys = [
            'qcm_recognition', 'qcm_reasoning', 'qcm_deceptive_trap',
            'tf_recognition_true', 'tf_recognition_false',
            'tf_reasoning_true', 'tf_reasoning_false',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $slots, "cognitive_slots manque : {$key}");
        }
    }

    public function test_each_cognitive_slot_has_required_sub_keys(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];

        $required = [
            'question_type', 'cognitive_type',
            'question_slot', 'answer_slots', 'correct_answer_key',
            'sv_slot', 'translation_slots', 'status', 'rules', 'traces',
        ];

        foreach ($slots as $variantKey => $slot) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $slot, "{$variantKey} manque : {$key}");
            }
        }
    }

    public function test_cognitive_slots_content_is_all_null_at_construction(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];

        foreach ($slots as $variantKey => $slot) {
            $this->assertNull($slot['question_slot']['value'], "{$variantKey}.question_slot.value devrait être null.");
            $this->assertNull($slot['correct_answer_key'], "{$variantKey}.correct_answer_key devrait être null.");
            $this->assertNull($slot['sv_slot']['value'], "{$variantKey}.sv_slot.value devrait être null.");
            $this->assertSame('empty', $slot['status'], "{$variantKey}.status devrait être 'empty'.");
        }
    }

    public function test_qcm_variants_have_4_answer_slots(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];

        foreach (['qcm_recognition', 'qcm_reasoning', 'qcm_deceptive_trap'] as $key) {
            $ans = $slots[$key]['answer_slots'];
            $this->assertArrayHasKey('answer_a', $ans, "{$key} devrait avoir answer_a.");
            $this->assertArrayHasKey('answer_b', $ans, "{$key} devrait avoir answer_b.");
            $this->assertArrayHasKey('answer_c', $ans, "{$key} devrait avoir answer_c.");
            $this->assertArrayHasKey('answer_d', $ans, "{$key} devrait avoir answer_d.");
        }
    }

    public function test_tf_variants_have_2_answer_slots_only(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];

        foreach (['tf_recognition_true', 'tf_recognition_false', 'tf_reasoning_true', 'tf_reasoning_false'] as $key) {
            $ans = $slots[$key]['answer_slots'];
            $this->assertArrayHasKey('answer_a', $ans, "{$key} devrait avoir answer_a.");
            $this->assertArrayHasKey('answer_b', $ans, "{$key} devrait avoir answer_b.");
            $this->assertArrayNotHasKey('answer_c', $ans, "{$key} ne devrait pas avoir answer_c.");
            $this->assertArrayNotHasKey('answer_d', $ans, "{$key} ne devrait pas avoir answer_d.");
        }
    }

    public function test_each_cognitive_slot_has_exactly_9_translation_langs(): void
    {
        $slots    = $this->skeleton()['cognitive_slots'];
        $expected = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

        foreach ($slots as $variantKey => $slot) {
            foreach ($expected as $lang) {
                $this->assertArrayHasKey($lang, $slot['translation_slots'], "{$variantKey} manque la langue : {$lang}");
            }
            $this->assertCount(9, $slot['translation_slots'], "{$variantKey} devrait avoir exactement 9 langues.");
        }
    }

    public function test_translation_slots_status_is_pending_at_construction(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];

        foreach ($slots as $variantKey => $slot) {
            foreach ($slot['translation_slots'] as $lang => $langSlot) {
                $this->assertSame('pending', $langSlot['status'], "{$variantKey}.{$lang}.status devrait être 'pending'.");
            }
        }
    }

    // =========================================================================
    // 9. rules / mechanisms / constraints / statuses / traces
    // =========================================================================

    public function test_rules_contains_kernel_code_format(): void
    {
        $rules = $this->skeleton()['rules'];

        $this->assertArrayHasKey('kernel_code_format', $rules);
        $this->assertSame('yy-xx-xxx-xxx-xxx-zz', $rules['kernel_code_format']);
    }

    public function test_mechanisms_lists_all_11_pipeline_steps(): void
    {
        $mechanisms = $this->skeleton()['mechanisms'];
        $this->assertCount(11, $mechanisms);
    }

    public function test_constraints_declares_no_selection_in_frame(): void
    {
        $constraints = $this->skeleton()['constraints'];

        $this->assertTrue($constraints['frame_builder_no_selection']);
        $this->assertTrue($constraints['frame_builder_no_question_generation']);
        $this->assertTrue($constraints['dominant_ideas_only_for_active_subject']);
        $this->assertTrue($constraints['kernel_code_immutable_after_kld']);
        $this->assertTrue($constraints['ready_bank_stores_encoded_noyau']);
        $this->assertTrue($constraints['no_isolated_cognitive_in_ready_bank']);
    }

    public function test_statuses_has_all_10_pipeline_stages_at_null(): void
    {
        $statuses = $this->skeleton()['statuses'];

        $expectedStages = [
            'rotation', 'taxonomy', 'key_structure', 'kld',
            'question_intent', 'phase1_content', 'phase2_validation',
            'phase3_translation', 'phase4_translation_val', 'ready_bank',
        ];

        $this->assertCount(10, $statuses);

        foreach ($expectedStages as $stage) {
            $this->assertArrayHasKey($stage, $statuses, "statuses manque : {$stage}");
            $this->assertNull($statuses[$stage], "statuses.{$stage} devrait être null.");
        }
    }

    public function test_traces_is_empty_array_at_construction(): void
    {
        $this->assertSame([], $this->skeleton()['traces']);
    }

    // =========================================================================
    // 10. Compatibilité legacy (kernel_core / variants / translation_constraints)
    // =========================================================================

    public function test_kernel_core_legacy_is_still_present(): void
    {
        $core = $this->skeleton()['kernel_core'];

        $this->assertArrayHasKey('domain', $core);
        $this->assertArrayHasKey('subject', $core);
        $this->assertArrayHasKey('difficulty_depth', $core);
        $this->assertSame('Géographie', $core['domain']);
        $this->assertSame(4, $core['difficulty_depth']);
    }

    public function test_variants_legacy_has_7_keys(): void
    {
        $variants = $this->skeleton()['variants'];
        $this->assertCount(7, $variants);
    }

    public function test_translation_constraints_legacy_has_9_langs(): void
    {
        $tc = $this->skeleton()['translation_constraints'];
        $this->assertCount(9, $tc);
    }

    // =========================================================================
    // 11. Sortie JSON valide (exemple noyau vide)
    // =========================================================================

    public function test_skeleton_encodes_to_valid_json(): void
    {
        $json = json_encode($this->skeleton(), JSON_THROW_ON_ERROR);

        $this->assertIsString($json);
        $this->assertNotEmpty($json);

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertNull($decoded['kernel_code']);
        $this->assertCount(50, $decoded['subjects_inventory']);
        $this->assertCount(7, $decoded['cognitive_slots']);
        $this->assertCount(10, $decoded['statuses']);
        $this->assertSame([], $decoded['traces']);
        $this->assertSame('yy-xx-xxx-xxx-xxx-zz', $decoded['rules']['kernel_code_format']);
    }
}
