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
    // 2. kernel_code — slot structuré
    // =========================================================================

    public function test_kernel_code_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['kernel_code'];

        foreach (['value', 'format', 'status', 'locked', 'rules', 'traces'] as $key) {
            $this->assertArrayHasKey($key, $slot, "kernel_code manque : {$key}");
        }
    }

    public function test_kernel_code_value_is_null_at_construction(): void
    {
        $slot = $this->skeleton()['kernel_code'];

        $this->assertNull($slot['value']);
        $this->assertSame('empty', $slot['status']);
        $this->assertFalse($slot['locked']);
        $this->assertSame('yy-xx-xxx-xxx-xxx-zz', $slot['format']);
    }

    public function test_kernel_code_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['kernel_code']['rules'];

        $this->assertSame('KernelFrameBuilder', $rules['creator']);
        $this->assertStringContainsString('KEY_STRUCTURE', $rules['filler_prefix']);
        $this->assertStringContainsString('KLD',           $rules['filler_suffix']);
        $this->assertContains('QuestionIntent',  $rules['read_by']);
        $this->assertContains('READY_BANK',      $rules['read_by']);
        $this->assertStringContainsString('KLD',            $rules['locked_after']);
        $this->assertStringContainsString('Immutable',      $rules['forbidden']);
        $this->assertArrayHasKey('transmitted_to',          $rules);
        $this->assertArrayHasKey('expected_content',        $rules);
    }

    // =========================================================================
    // 3. depth_slot
    // =========================================================================

    public function test_depth_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['depth_slot'];

        foreach ([
            'requested_depth', 'actual_depth', 'selection_source',
            'filled_at', 'status', 'locked', 'rules', 'traces',
        ] as $key) {
            $this->assertArrayHasKey($key, $slot, "depth_slot manque : {$key}");
        }
    }

    public function test_depth_slot_prefills_from_intent_when_depth_present(): void
    {
        $slot = $this->skeleton()['depth_slot'];

        $this->assertSame(4, $slot['requested_depth']);
        $this->assertSame(4, $slot['actual_depth']);
        $this->assertSame('legacy_intent', $slot['selection_source']);
        $this->assertNull($slot['filled_at']);
        $this->assertSame('filled', $slot['status']);
        $this->assertTrue($slot['locked']);
    }

    public function test_depth_slot_is_empty_when_intent_has_no_depth(): void
    {
        $this->intent = $this->makeIntent(['difficulty_depth' => null]);
        $slot = $this->skeleton()['depth_slot'];

        $this->assertNull($slot['requested_depth']);
        $this->assertNull($slot['actual_depth']);
        $this->assertNull($slot['selection_source']);
        $this->assertNull($slot['filled_at']);
        $this->assertSame('empty', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_depth_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['depth_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',     $rules['creator']);
        $this->assertSame('KernelRotationPlanner',  $rules['filler']);
        $this->assertSame('DepthNeedMatrix',         $rules['driven_by']);
        $this->assertSame('Taxonomy',                $rules['transmitted_to']);
        $this->assertContains('Taxonomy',        $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',   $rules['read_by']);
        $this->assertContains('QuestionIntent',  $rules['read_by']);
        $this->assertContains('Phase1',          $rules['read_by']);
        $this->assertContains('READY_BANK',      $rules['read_by']);
        $this->assertStringContainsString('KernelRotationPlanner', $rules['write_access']);
        $this->assertStringContainsString('depth_slot',            $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',               $rules);
    }

    // =========================================================================
    // 4. domain_slot
    // =========================================================================

    public function test_domain_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['domain_slot'];

        foreach ([
            'requested_domain', 'actual_domain', 'selection_source',
            'filled_at', 'status', 'locked', 'rules', 'traces',
        ] as $key) {
            $this->assertArrayHasKey($key, $slot, "domain_slot manque : {$key}");
        }
    }

    public function test_domain_slot_prefills_from_intent(): void
    {
        $slot = $this->skeleton()['domain_slot'];

        $this->assertSame('Géographie', $slot['requested_domain']);
        $this->assertSame('Géographie', $slot['actual_domain']);
        $this->assertSame('legacy_intent', $slot['selection_source']);
        $this->assertNull($slot['filled_at']);
        $this->assertSame('filled', $slot['status']);
        $this->assertTrue($slot['locked']);
    }

    public function test_domain_slot_is_empty_when_intent_has_no_domain(): void
    {
        $this->intent = $this->makeIntent(['domain' => null]);
        $slot = $this->skeleton()['domain_slot'];

        $this->assertNull($slot['requested_domain']);
        $this->assertNull($slot['actual_domain']);
        $this->assertNull($slot['selection_source']);
        $this->assertNull($slot['filled_at']);
        $this->assertSame('empty', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_domain_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['domain_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',    $rules['creator']);
        $this->assertSame('KernelRotationPlanner', $rules['filler']);
        $this->assertSame('DomainCycle',           $rules['driven_by']);
        $this->assertSame('depth_slot',            $rules['depends_on']);
        $this->assertSame('Taxonomy',              $rules['transmitted_to']);
        $this->assertContains('Taxonomy',        $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',   $rules['read_by']);
        $this->assertContains('QuestionIntent',  $rules['read_by']);
        $this->assertContains('Phase1',          $rules['read_by']);
        $this->assertContains('READY_BANK',      $rules['read_by']);
        $this->assertStringContainsString('KernelRotationPlanner', $rules['write_access']);
        $this->assertStringContainsString('domain_slot',           $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',               $rules);
    }

    // =========================================================================
    // 5. sub_domain_slot
    // =========================================================================

    public function test_sub_domain_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['sub_domain_slot'];

        foreach ([
            'requested_sub_domain', 'actual_sub_domain', 'selection_source',
            'filled_at', 'status', 'locked', 'rules', 'traces',
        ] as $key) {
            $this->assertArrayHasKey($key, $slot, "sub_domain_slot manque : {$key}");
        }
    }

    public function test_sub_domain_slot_prefills_from_intent(): void
    {
        $slot = $this->skeleton()['sub_domain_slot'];

        $this->assertSame('Capitales', $slot['requested_sub_domain']);
        $this->assertSame('Capitales', $slot['actual_sub_domain']);
        $this->assertSame('legacy_intent', $slot['selection_source']);
        $this->assertNull($slot['filled_at']);
        $this->assertSame('filled', $slot['status']);
        $this->assertTrue($slot['locked']);
    }

    public function test_sub_domain_slot_is_empty_when_intent_has_no_sub_domain(): void
    {
        $this->intent = $this->makeIntent(['sub_domain' => null]);
        $slot = $this->skeleton()['sub_domain_slot'];

        $this->assertNull($slot['requested_sub_domain']);
        $this->assertNull($slot['actual_sub_domain']);
        $this->assertNull($slot['selection_source']);
        $this->assertNull($slot['filled_at']);
        $this->assertSame('empty', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_sub_domain_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['sub_domain_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',            $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',      $rules['filler']);
        $this->assertContains('depth_slot',       $rules['depends_on']);
        $this->assertContains('domain_slot',      $rules['depends_on']);
        $this->assertContains('KEY_STRUCTURE',    $rules['read_by']);
        $this->assertContains('QuestionIntent',   $rules['read_by']);
        $this->assertContains('READY_BANK',       $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',          $rules['write_access']);
        $this->assertStringContainsString('Taxonomy',          $rules['locked_after']);
        $this->assertStringContainsString('subjects_inventory',$rules['transmitted_to']);
        $this->assertStringContainsString('sub_domain_slot',   $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',           $rules);
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

    public function test_subjects_inventory_slots_have_required_keys(): void
    {
        $inventory = $this->skeleton()['subjects_inventory'];

        foreach ($inventory as $i => $slot) {
            foreach (['index', 'label', 'filled_at', 'status', 'locked', 'rules', 'traces'] as $key) {
                $this->assertArrayHasKey($key, $slot, "subjects_inventory[{$i}] manque : {$key}");
            }
        }
    }

    public function test_subjects_inventory_slots_are_all_empty(): void
    {
        $inventory = $this->skeleton()['subjects_inventory'];

        foreach ($inventory as $i => $slot) {
            $this->assertNull($slot['label'],      "Slot {$i} label devrait être null.");
            $this->assertNull($slot['filled_at'],  "Slot {$i} filled_at devrait être null.");
            $this->assertFalse($slot['locked'],    "Slot {$i} locked devrait être false.");
            $this->assertSame('available', $slot['status'], "Slot {$i} status devrait être 'available'.");
            $this->assertSame($i + 1, $slot['index'], "Slot {$i} index devrait être " . ($i + 1) . ".");
        }
    }

    public function test_subjects_inventory_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['subjects_inventory'][0]['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertSame(50,                            $rules['max_slots']);
        $this->assertContains('active_subject', $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',  $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',         $rules['write_access']);
        $this->assertStringContainsString('active',           $rules['locked_after']);
        $this->assertStringContainsString('active_subject',   $rules['transmitted_to']);
        $this->assertStringContainsString('dominant_ideas',   $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',          $rules);
    }

    // =========================================================================
    // 7. active_subject — slot structuré
    // =========================================================================

    public function test_active_subject_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['active_subject'];

        foreach ([
            'subject_index', 'subject_label', 'set_at', 'status', 'locked', 'rules', 'traces',
        ] as $key) {
            $this->assertArrayHasKey($key, $slot, "active_subject manque : {$key}");
        }
    }

    public function test_active_subject_is_empty_at_construction(): void
    {
        $slot = $this->skeleton()['active_subject'];

        $this->assertNull($slot['subject_index']);
        $this->assertNull($slot['subject_label']);
        $this->assertNull($slot['set_at']);
        $this->assertSame('empty', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_active_subject_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['active_subject']['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertContains('dominant_ideas',  $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',   $rules['read_by']);
        $this->assertContains('QuestionIntent',  $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',          $rules['write_access']);
        $this->assertStringContainsString('dominant_ideas',    $rules['locked_after']);
        $this->assertStringContainsString('dominant_ideas',    $rules['transmitted_to']);
        $this->assertStringContainsString('active_subject',    $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',           $rules);
    }

    // =========================================================================
    // 8. dominant_ideas — slot structuré
    // =========================================================================

    public function test_dominant_ideas_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['dominant_ideas'];

        foreach (['ideas', 'status', 'locked', 'rules', 'traces'] as $key) {
            $this->assertArrayHasKey($key, $slot, "dominant_ideas manque : {$key}");
        }
    }

    public function test_dominant_ideas_ideas_is_empty_at_construction(): void
    {
        $slot = $this->skeleton()['dominant_ideas'];

        $this->assertSame([], $slot['ideas']);
        $this->assertSame('empty', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_dominant_ideas_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['dominant_ideas']['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertSame(5,                             $rules['max_ideas']);
        $this->assertStringContainsString('active_subject',       $rules['scope']);
        $this->assertContains('active_dominant_idea', $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',        $rules['read_by']);
        $this->assertContains('QuestionIntent',       $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',             $rules['write_access']);
        $this->assertStringContainsString('QuestionIntent',       $rules['locked_after']);
        $this->assertStringContainsString('active_dominant_idea', $rules['transmitted_to']);
        $this->assertStringContainsString('active_subject',       $rules['forbidden']);
        $this->assertStringContainsString('5',                    $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',              $rules);
    }

    // =========================================================================
    // 9. active_dominant_idea — slot structuré
    // =========================================================================

    public function test_active_dominant_idea_slot_has_required_keys(): void
    {
        $slot = $this->skeleton()['active_dominant_idea'];

        foreach ([
            'idea_index', 'idea_label', 'set_at', 'status', 'locked', 'rules', 'traces',
        ] as $key) {
            $this->assertArrayHasKey($key, $slot, "active_dominant_idea manque : {$key}");
        }
    }

    public function test_active_dominant_idea_is_empty_at_construction(): void
    {
        $slot = $this->skeleton()['active_dominant_idea'];

        $this->assertNull($slot['idea_index']);
        $this->assertNull($slot['idea_label']);
        $this->assertNull($slot['set_at']);
        $this->assertSame('empty', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_active_dominant_idea_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['active_dominant_idea']['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertSame('dominant_ideas',              $rules['depends_on']);
        $this->assertContains('KEY_STRUCTURE',   $rules['read_by']);
        $this->assertContains('QuestionIntent',  $rules['read_by']);
        $this->assertContains('Phase1',          $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',                  $rules['write_access']);
        $this->assertStringContainsString('QuestionIntent',            $rules['locked_after']);
        $this->assertStringContainsString('KEY_STRUCTURE',             $rules['transmitted_to']);
        $this->assertStringContainsString('active_dominant_idea',      $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',                   $rules);
    }

    // =========================================================================
    // 10. cognitive_slots
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
            $this->assertNull($slot['question_slot']['value'],   "{$variantKey}.question_slot.value devrait être null.");
            $this->assertNull($slot['correct_answer_key'],       "{$variantKey}.correct_answer_key devrait être null.");
            $this->assertNull($slot['sv_slot']['value'],         "{$variantKey}.sv_slot.value devrait être null.");
            $this->assertSame('empty', $slot['status'],          "{$variantKey}.status devrait être 'empty'.");
        }
    }

    public function test_question_slot_has_full_contract_keys(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];
        $qs = $slots['qcm_recognition']['question_slot'];

        foreach (['value', 'filled_at', 'status', 'locked', 'rules', 'traces'] as $key) {
            $this->assertArrayHasKey($key, $qs, "question_slot manque : {$key}");
        }
        $this->assertSame('empty', $qs['status']);
        $this->assertFalse($qs['locked']);
        $this->assertNull($qs['filled_at']);
    }

    public function test_question_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['cognitive_slots']['qcm_recognition']['question_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',              $rules['creator']);
        $this->assertSame('Phase1 (KernelContentBuilder)',   $rules['filler']);
        $this->assertSame('en',                              $rules['language']);
        $this->assertContains('Phase2',       $rules['read_by']);
        $this->assertContains('Phase3',       $rules['read_by']);
        $this->assertContains('READY_BANK',   $rules['read_by']);
        $this->assertStringContainsString('Phase1',            $rules['write_access']);
        $this->assertStringContainsString('Phase2',            $rules['locked_after']);
        $this->assertStringContainsString('Phase3',            $rules['transmitted_to']);
        $this->assertStringContainsString('question_slot',     $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',           $rules);
    }

    public function test_sv_slot_has_full_contract_keys(): void
    {
        $sv = $this->skeleton()['cognitive_slots']['qcm_recognition']['sv_slot'];

        foreach (['value', 'filled_at', 'status', 'locked', 'rules', 'traces'] as $key) {
            $this->assertArrayHasKey($key, $sv, "sv_slot manque : {$key}");
        }
        $this->assertSame('empty', $sv['status']);
        $this->assertFalse($sv['locked']);
        $this->assertNull($sv['filled_at']);
    }

    public function test_sv_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['cognitive_slots']['qcm_recognition']['sv_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',            $rules['creator']);
        $this->assertSame('Phase1 (KernelContentBuilder)', $rules['filler']);
        $this->assertSame('en',                            $rules['language']);
        $this->assertIsInt($rules['min_chars']);
        $this->assertIsInt($rules['max_chars']);
        $this->assertContains('Phase2',     $rules['read_by']);
        $this->assertContains('Phase3',     $rules['read_by']);
        $this->assertContains('READY_BANK', $rules['read_by']);
        $this->assertStringContainsString('Phase1',    $rules['write_access']);
        $this->assertStringContainsString('sv_slot',   $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',   $rules);
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

    public function test_answer_slots_have_full_contract_keys(): void
    {
        $ans = $this->skeleton()['cognitive_slots']['qcm_recognition']['answer_slots'];

        foreach (['answer_a', 'answer_b', 'answer_c', 'answer_d'] as $ansKey) {
            foreach (['value', 'filled_at', 'status', 'locked', 'rules', 'traces'] as $key) {
                $this->assertArrayHasKey($key, $ans[$ansKey], "{$ansKey} manque : {$key}");
            }
            $this->assertNull($ans[$ansKey]['value']);
            $this->assertNull($ans[$ansKey]['filled_at']);
            $this->assertSame('empty', $ans[$ansKey]['status']);
            $this->assertFalse($ans[$ansKey]['locked']);
        }
    }

    public function test_answer_slots_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['cognitive_slots']['qcm_recognition']['answer_slots']['answer_a']['rules'];

        $this->assertSame('KernelFrameBuilder',            $rules['creator']);
        $this->assertSame('Phase1 (KernelContentBuilder)', $rules['filler']);
        $this->assertSame('en',                            $rules['language']);
        $this->assertIsInt($rules['max_chars']);
        $this->assertContains('Phase2',     $rules['read_by']);
        $this->assertContains('Phase3',     $rules['read_by']);
        $this->assertContains('READY_BANK', $rules['read_by']);
        $this->assertStringContainsString('Phase1',       $rules['write_access']);
        $this->assertStringContainsString('Phase3',       $rules['transmitted_to']);
        $this->assertStringContainsString('answer_slots', $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',      $rules);
    }

    // =========================================================================
    // 11. translation_slots
    // =========================================================================

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

    public function test_translation_slots_have_full_contract_keys(): void
    {
        $frSlot = $this->skeleton()['cognitive_slots']['qcm_recognition']['translation_slots']['fr'];

        foreach ([
            'status', 'filled_at', 'locked',
            'question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
            'correct_answer_key', 'explanation', 'saviez_vous',
            'rules', 'traces',
        ] as $key) {
            $this->assertArrayHasKey($key, $frSlot, "translation_slot[fr] manque : {$key}");
        }
        $this->assertFalse($frSlot['locked']);
        $this->assertNull($frSlot['filled_at']);
    }

    public function test_translation_slots_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['cognitive_slots']['qcm_recognition']['translation_slots']['fr']['rules'];

        $this->assertSame('KernelFrameBuilder',           $rules['creator']);
        $this->assertSame('Phase3 (KernelTranslator)',    $rules['filler']);
        $this->assertSame('Phase4',                       $rules['validator']);
        $this->assertSame('fr',                           $rules['language']);
        $this->assertIsInt($rules['answer_max']);
        $this->assertIsInt($rules['sv_max']);
        $this->assertContains('Phase4',    $rules['read_by']);
        $this->assertContains('READY_BANK',$rules['read_by']);
        $this->assertContains('Gameplay',  $rules['read_by']);
        $this->assertStringContainsString('Phase3',            $rules['write_access']);
        $this->assertStringContainsString('Phase4',            $rules['locked_after']);
        $this->assertStringContainsString('READY_BANK',        $rules['transmitted_to']);
        $this->assertStringContainsString('translation_slots', $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',           $rules);
    }

    public function test_translation_slots_zh_has_reduced_char_limits(): void
    {
        $zhRules = $this->skeleton()['cognitive_slots']['qcm_recognition']['translation_slots']['zh']['rules'];
        $frRules = $this->skeleton()['cognitive_slots']['qcm_recognition']['translation_slots']['fr']['rules'];

        $this->assertLessThan($frRules['answer_max'], $zhRules['answer_max']);
        $this->assertLessThan($frRules['sv_max'],     $zhRules['sv_max']);
    }

    // =========================================================================
    // 12. rules / mechanisms / constraints / statuses / traces
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

    public function test_root_traces_is_empty_array_at_construction(): void
    {
        $this->assertSame([], $this->skeleton()['traces']);
    }

    // =========================================================================
    // 13. Compatibilité legacy (kernel_core / variants / translation_constraints)
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
    // 14. Sortie JSON valide (exemple noyau vide)
    // =========================================================================

    public function test_skeleton_encodes_to_valid_json(): void
    {
        $json = json_encode($this->skeleton(), JSON_THROW_ON_ERROR);

        $this->assertIsString($json);
        $this->assertNotEmpty($json);

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded['kernel_code']);
        $this->assertNull($decoded['kernel_code']['value']);
        $this->assertSame('empty', $decoded['kernel_code']['status']);
        $this->assertCount(50, $decoded['subjects_inventory']);
        $this->assertCount(7, $decoded['cognitive_slots']);
        $this->assertCount(10, $decoded['statuses']);
        $this->assertSame([], $decoded['traces']);
        $this->assertSame('yy-xx-xxx-xxx-xxx-zz', $decoded['rules']['kernel_code_format']);
    }
}
