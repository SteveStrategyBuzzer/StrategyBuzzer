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
            'schema_version',
            'content_version',
            'correction_version',
            'kernel_code',
            'depth_slot',
            'domain_slot',
            'sub_domain_slot',
            'subjects_inventory',
            'active_subject',
            'dominant_ideas',
            'active_dominant_idea',
            'cognitive_slots',
            'object_contracts',
            'relation_map',
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

    public function test_versioning_fields_are_present_and_correct(): void
    {
        $frame = $this->skeleton();

        $this->assertSame('1.0.0', $frame['schema_version'],     'schema_version devrait être 1.0.0');
        $this->assertSame('1.0.0', $frame['content_version'],    'content_version devrait être 1.0.0');
        $this->assertSame(0,       $frame['correction_version'],  'correction_version devrait être 0');
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
        $this->assertSame('EMPTY', $slot['status']);
        $this->assertFalse($slot['locked']);
        $this->assertSame('yy-xx-xxx-xxx-xxx-zz', $slot['format']);
    }

    public function test_kernel_code_has_progressive_owners(): void
    {
        $slot = $this->skeleton()['kernel_code'];

        $this->assertArrayHasKey('owners', $slot);
        $this->assertCount(5, $slot['owners']);

        $steps = array_column($slot['owners'], 'component');
        $this->assertContains('KernelFrameBuilder',       $steps);
        $this->assertContains('KernelRotationPlanner',    $steps);
        $this->assertContains('Taxonomy (TaxonomyReader)',$steps);
        $this->assertContains('KEY_STRUCTURE',            $steps);
        $this->assertContains('KLD',                      $steps);

        foreach ($slot['owners'] as $owner) {
            $this->assertArrayHasKey('step',      $owner);
            $this->assertArrayHasKey('component', $owner);
            $this->assertArrayHasKey('action',    $owner);
        }
    }

    public function test_kernel_code_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['kernel_code']['rules'];

        $this->assertSame('KernelFrameBuilder', $rules['creator']);
        $this->assertStringContainsString('progressive',  $rules['construction']);
        $this->assertContains('depth_slot',        $rules['depends_on']);
        $this->assertContains('active_subject',    $rules['depends_on']);
        $this->assertContains('active_dominant_idea',$rules['depends_on']);
        $this->assertContains('QuestionIntent',    $rules['read_by']);
        $this->assertContains('READY_BANK',        $rules['read_by']);
        $this->assertStringContainsString('KLD',       $rules['locked_after']);
        $this->assertStringContainsString('Immutable', $rules['forbidden']);
        $this->assertArrayHasKey('transmitted_to',     $rules);
        $this->assertArrayHasKey('expected_content',   $rules);
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
        $this->assertSame('FILLED', $slot['status']);
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
        $this->assertSame('EMPTY', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_depth_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['depth_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',    $rules['creator']);
        $this->assertSame('KernelRotationPlanner', $rules['filler']);
        $this->assertSame('DepthNeedMatrix',        $rules['driven_by']);
        $this->assertSame('Taxonomy',               $rules['transmitted_to']);
        $this->assertIsArray($rules['depends_on']);
        $this->assertEmpty($rules['depends_on'], 'depth_slot has no dependencies');
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
        $this->assertSame('FILLED', $slot['status']);
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
        $this->assertSame('EMPTY', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_domain_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['domain_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',    $rules['creator']);
        $this->assertSame('KernelRotationPlanner', $rules['filler']);
        $this->assertSame('DomainCycle',           $rules['driven_by']);
        $this->assertContains('depth_slot',        $rules['depends_on']);
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
        $this->assertSame('FILLED', $slot['status']);
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
        $this->assertSame('EMPTY', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_sub_domain_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['sub_domain_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',            $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',      $rules['filler']);
        $this->assertContains('depth_slot',         $rules['depends_on']);
        $this->assertContains('domain_slot',        $rules['depends_on']);
        $this->assertContains('subjects_inventory', $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',      $rules['read_by']);
        $this->assertContains('QuestionIntent',     $rules['read_by']);
        $this->assertContains('READY_BANK',         $rules['read_by']);
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
            $this->assertSame('EMPTY', $slot['status'], "Slot {$i} status devrait être 'EMPTY'.");
            $this->assertSame($i + 1, $slot['index'], "Slot {$i} index devrait être " . ($i + 1) . ".");
        }
    }

    public function test_subjects_inventory_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['subjects_inventory'][0]['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertContains('sub_domain_slot', $rules['depends_on']);
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
        $this->assertSame('EMPTY', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_active_subject_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['active_subject']['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertContains('subjects_inventory', $rules['depends_on']);
        $this->assertContains('dominant_ideas',  $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',   $rules['read_by']);
        $this->assertContains('QuestionIntent',  $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',              $rules['write_access']);
        $this->assertStringContainsString('jamais',                $rules['locked_after'], 'active_subject ne doit pas être verrouillé définitivement');
        $this->assertStringContainsString('épuisées',              $rules['locked_after']);
        $this->assertArrayHasKey('rotation',                       $rules);
        $this->assertStringContainsString('Taxonomy active Sujet', $rules['rotation']);
        $this->assertStringContainsString('dominant_ideas',        $rules['transmitted_to']);
        $this->assertStringContainsString('active_subject',        $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',               $rules);
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
        $this->assertSame('EMPTY', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_dominant_ideas_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['dominant_ideas']['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertContains('active_subject',      $rules['depends_on']);
        $this->assertSame(5,                             $rules['max_ideas']);
        $this->assertStringContainsString('active_subject',       $rules['scope']);
        $this->assertContains('active_dominant_idea', $rules['read_by']);
        $this->assertContains('KEY_STRUCTURE',        $rules['read_by']);
        $this->assertContains('QuestionIntent',       $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',              $rules['write_access']);
        $this->assertStringContainsString('jamais',                $rules['locked_after'], 'dominant_ideas ne doit pas être verrouillé définitivement');
        $this->assertStringContainsString('active_subject change', $rules['locked_after']);
        $this->assertArrayHasKey('rotation',                       $rules);
        $this->assertStringContainsString('Taxonomy recharge',     $rules['rotation']);
        $this->assertArrayHasKey('status_progression',             $rules);
        $this->assertStringContainsString('EMPTY',                 $rules['status_progression']);
        $this->assertStringContainsString('FILLED',                $rules['status_progression']);
        $this->assertStringContainsString('5',                     $rules['status_progression']);
        $this->assertArrayHasKey('locked_semantics',               $rules);
        $this->assertStringContainsString('LOCKED',                $rules['locked_semantics']);
        $this->assertStringContainsString('active_subject change', $rules['locked_semantics']);
        $this->assertStringContainsString('active_dominant_idea',  $rules['transmitted_to']);
        $this->assertStringContainsString('active_subject',        $rules['forbidden']);
        $this->assertStringContainsString('5',                     $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',               $rules);
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
        $this->assertSame('EMPTY', $slot['status']);
        $this->assertFalse($slot['locked']);
    }

    public function test_active_dominant_idea_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['active_dominant_idea']['rules'];

        $this->assertSame('KernelFrameBuilder',         $rules['creator']);
        $this->assertSame('Taxonomy (TaxonomyReader)',   $rules['filler']);
        $this->assertContains('dominant_ideas',      $rules['depends_on']);
        $this->assertContains('KEY_STRUCTURE',   $rules['read_by']);
        $this->assertContains('QuestionIntent',  $rules['read_by']);
        $this->assertContains('Phase1',          $rules['read_by']);
        $this->assertStringContainsString('Taxonomy',                  $rules['write_access']);
        $this->assertStringContainsString('PAIRE',                     $rules['locked_after'], 'QuestionIntent verrouille la PAIRE sujet+idée, pas le bloc Taxonomy');
        $this->assertArrayHasKey('pair_lock',                          $rules);
        $this->assertStringContainsString('PAIRE',                     $rules['pair_lock']);
        $this->assertStringContainsString('pas le bloc Taxonomy',      $rules['pair_lock']);
        $this->assertArrayHasKey('rotation',                           $rules);
        $this->assertStringContainsString('Sujet 2 actif',             $rules['rotation']);
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
            $this->assertSame('EMPTY', $slot['status'],          "{$variantKey}.status devrait être 'EMPTY'.");
        }
    }

    public function test_question_slot_has_full_contract_keys(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];
        $qs = $slots['qcm_recognition']['question_slot'];

        foreach (['value', 'filled_at', 'status', 'locked', 'rules', 'traces'] as $key) {
            $this->assertArrayHasKey($key, $qs, "question_slot manque : {$key}");
        }
        $this->assertSame('EMPTY', $qs['status']);
        $this->assertFalse($qs['locked']);
        $this->assertNull($qs['filled_at']);
    }

    public function test_question_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['cognitive_slots']['qcm_recognition']['question_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',            $rules['creator']);
        $this->assertSame('Phase1 (KernelContentBuilder)', $rules['filler']);
        $this->assertSame('en',                            $rules['language']);
        $this->assertContains('QuestionIntent', $rules['depends_on']);
        $this->assertContains('Phase2',         $rules['read_by']);
        $this->assertContains('Phase3',         $rules['read_by']);
        $this->assertContains('READY_BANK',     $rules['read_by']);
        $this->assertStringContainsString('Phase1',        $rules['write_access']);
        $this->assertStringContainsString('Phase2',        $rules['locked_after']);
        $this->assertStringContainsString('Phase3',        $rules['transmitted_to']);
        $this->assertStringContainsString('question_slot', $rules['forbidden']);
        $this->assertArrayHasKey('expected_content',       $rules);
    }

    public function test_sv_slot_has_full_contract_keys(): void
    {
        $sv = $this->skeleton()['cognitive_slots']['qcm_recognition']['sv_slot'];

        foreach (['value', 'filled_at', 'status', 'locked', 'rules', 'traces'] as $key) {
            $this->assertArrayHasKey($key, $sv, "sv_slot manque : {$key}");
        }
        $this->assertSame('EMPTY', $sv['status']);
        $this->assertFalse($sv['locked']);
        $this->assertNull($sv['filled_at']);
    }

    public function test_sv_slot_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['cognitive_slots']['qcm_recognition']['sv_slot']['rules'];

        $this->assertSame('KernelFrameBuilder',            $rules['creator']);
        $this->assertSame('Phase1 (KernelContentBuilder)', $rules['filler']);
        $this->assertSame('en',                            $rules['language']);
        $this->assertContains('QuestionIntent', $rules['depends_on']);
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
            $this->assertSame('EMPTY', $ans[$ansKey]['status']);
            $this->assertFalse($ans[$ansKey]['locked']);
        }
    }

    public function test_answer_slots_rules_declare_full_access_contract(): void
    {
        $rules = $this->skeleton()['cognitive_slots']['qcm_recognition']['answer_slots']['answer_a']['rules'];

        $this->assertSame('KernelFrameBuilder',            $rules['creator']);
        $this->assertSame('Phase1 (KernelContentBuilder)', $rules['filler']);
        $this->assertSame('en',                            $rules['language']);
        $this->assertContains('QuestionIntent', $rules['depends_on']);
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

    public function test_translation_slots_status_is_empty_at_construction(): void
    {
        $slots = $this->skeleton()['cognitive_slots'];

        foreach ($slots as $variantKey => $slot) {
            foreach ($slot['translation_slots'] as $lang => $langSlot) {
                $this->assertSame('EMPTY', $langSlot['status'], "{$variantKey}.{$lang}.status devrait être 'EMPTY'.");
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
        $this->assertContains('Phase2_validation', $rules['depends_on']);
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

    public function test_rules_contains_statuses_hierarchy(): void
    {
        $sh = $this->skeleton()['rules']['statuses_hierarchy'];

        // Clés globales préservées
        $this->assertArrayHasKey('kernel_level',    $sh);
        $this->assertArrayHasKey('slot_level',      $sh);
        $this->assertArrayHasKey('slot_enum',       $sh);
        $this->assertArrayHasKey('locked_semantics',$sh);
        $this->assertArrayHasKey('rule',            $sh);

        $this->assertStringContainsString('10',         $sh['kernel_level']);
        $this->assertStringContainsString('depth_slot', $sh['slot_level']);

        $enum = $sh['slot_enum'];
        $this->assertContains('EMPTY',             $enum);
        $this->assertContains('FILLED',            $enum);
        $this->assertContains('VALIDATED_OK',      $enum);
        $this->assertContains('LOCKED',            $enum);
        $this->assertContains('REJECTED',          $enum);
        $this->assertContains('CORRECTION_NEEDED', $enum);
        $this->assertCount(6, $enum);

        $this->assertStringContainsString('LOCKED',          $sh['locked_semantics']);
        $this->assertStringContainsString('active_subject',  $sh['locked_semantics']);

        // ── Couche A : slot_status ──────────────────────────────────────────
        $this->assertArrayHasKey('slot_status_layer', $sh);
        $layerA = $sh['slot_status_layer'];
        $this->assertArrayHasKey('slot_enum',   $layerA);
        $this->assertArrayHasKey('no_consumed', $layerA);
        $this->assertContains('EMPTY',             $layerA['slot_enum']);
        $this->assertContains('VALIDATED_OK',      $layerA['slot_enum']);
        $this->assertStringContainsString('CONSUMED', $layerA['no_consumed']);

        // ── Couche B : kernel_pipeline ──────────────────────────────────────
        $this->assertArrayHasKey('kernel_pipeline_layer', $sh);
        $layerB = $sh['kernel_pipeline_layer'];
        $this->assertArrayHasKey('stages',      $layerB);
        $this->assertArrayHasKey('final_state', $layerB);
        $this->assertContains('ready_bank',        $layerB['stages']);
        $this->assertStringContainsString('READY_BANK', $layerB['final_state']);

        // ── Couche C : player_gameplay ──────────────────────────────────────
        $this->assertArrayHasKey('player_gameplay_layer', $sh);
        $layerC = $sh['player_gameplay_layer'];
        $this->assertArrayHasKey('states',       $layerC);
        $this->assertArrayHasKey('location',     $layerC);
        $this->assertArrayHasKey('consumed_def', $layerC);
        $this->assertContains('vierge',      $layerC['states']);
        $this->assertContains('touché',      $layerC['states']);
        $this->assertContains('back_support',$layerC['states']);
        $this->assertStringContainsString('player_kernel_cognitive_usage', $layerC['location']);
        $this->assertStringContainsString('JAMAIS', $layerC['description'] ?? $layerC['rule'] ?? '');

        // La règle globale mentionne les 3 couches
        $this->assertStringContainsString('3', $sh['rule']);
    }

    public function test_rules_contains_traces_hierarchy(): void
    {
        $th = $this->skeleton()['rules']['traces_hierarchy'];

        $this->assertArrayHasKey('root_level', $th);
        $this->assertArrayHasKey('slot_level', $th);
        $this->assertArrayHasKey('rule',       $th);
        $this->assertStringContainsString('Append-only', $th['rule']);
    }

    public function test_rules_contains_dependency_graph(): void
    {
        $dg = $this->skeleton()['rules']['dependency_graph'];

        $this->assertArrayHasKey('depth_slot',           $dg);
        $this->assertArrayHasKey('domain_slot',          $dg);
        $this->assertArrayHasKey('sub_domain_slot',      $dg);
        $this->assertArrayHasKey('subjects_inventory',   $dg);
        $this->assertArrayHasKey('active_subject',       $dg);
        $this->assertArrayHasKey('dominant_ideas',       $dg);
        $this->assertArrayHasKey('active_dominant_idea', $dg);
        $this->assertArrayHasKey('kernel_code',          $dg);
        $this->assertArrayHasKey('QuestionIntent',       $dg);
        $this->assertArrayHasKey('READY_BANK',           $dg);

        $this->assertEmpty($dg['depth_slot'],              'depth_slot a aucune dépendance');
        $this->assertContains('depth_slot',   $dg['domain_slot']);
        $this->assertContains('depth_slot',   $dg['sub_domain_slot']);
        $this->assertContains('domain_slot',  $dg['sub_domain_slot']);
        $this->assertContains('active_subject',       $dg['QuestionIntent']);
        $this->assertContains('active_dominant_idea', $dg['QuestionIntent']);
        $this->assertContains('Phase4', $dg['READY_BANK']);
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

        // Contraintes player-level séparation (CONSUMED interdit dans le noyau)
        $this->assertArrayHasKey('cognitive_consumption_is_player_level', $constraints);
        $this->assertArrayHasKey('ready_bank_no_consumption_tracking',    $constraints);
        $this->assertArrayNotHasKey('cognitive_consumed_by_gameplay',     $constraints,
            'Ancienne clé cognitive_consumed_by_gameplay remplacée');

        $this->assertStringContainsString('CONSUMED',                    $constraints['cognitive_consumption_is_player_level']);
        $this->assertStringContainsString('player_kernel_cognitive_usage',$constraints['cognitive_consumption_is_player_level']);
        $this->assertStringContainsString('READY_BANK',                  $constraints['ready_bank_no_consumption_tracking']);
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
    // 13. object_contracts — Contrats de données des objets métier
    // =========================================================================

    public function test_object_contracts_key_exists_in_skeleton(): void
    {
        $this->assertArrayHasKey('object_contracts', $this->skeleton());
    }

    public function test_object_contracts_has_all_8_types(): void
    {
        $oc = $this->skeleton()['object_contracts'];

        foreach (['KernelFrame', 'SubjectSlot', 'DominantIdeaSlot', 'CognitiveSlot',
                  'QuestionSlot', 'AnswerSlot', 'SVSlot', 'TranslationSlot'] as $type) {
            $this->assertArrayHasKey($type, $oc, "object_contracts manque le type : {$type}");
        }
        $this->assertCount(8, $oc);
    }

    public function test_kernel_frame_contract_has_description_fields_and_owner(): void
    {
        $kf = $this->skeleton()['object_contracts']['KernelFrame'];

        $this->assertArrayHasKey('description', $kf);
        $this->assertArrayHasKey('fields',       $kf);
        $this->assertArrayHasKey('owner',        $kf);
        $this->assertStringContainsString('KernelFrameBuilder', $kf['owner']);

        $fields = $kf['fields'];
        foreach ([
            'kernel_code', 'depth_slot', 'domain_slot', 'sub_domain_slot',
            'subjects_inventory', 'active_subject', 'dominant_ideas',
            'active_dominant_idea', 'cognitive_slots', 'object_contracts',
            'relation_map', 'rules', 'mechanisms', 'constraints', 'statuses', 'traces',
        ] as $field) {
            $this->assertArrayHasKey($field, $fields, "KernelFrame.fields manque : {$field}");
        }
    }

    public function test_subject_slot_contract_has_all_required_sections(): void
    {
        $ss = $this->skeleton()['object_contracts']['SubjectSlot'];

        $this->assertArrayHasKey('description',    $ss);
        $this->assertArrayHasKey('filled_by',      $ss);
        $this->assertArrayHasKey('fields',         $ss);
        $this->assertArrayHasKey('initial_state',  $ss);

        $this->assertStringContainsString('Taxonomy', $ss['filled_by']);

        $fields = $ss['fields'];
        foreach ([
            'index', 'label', 'subject_id', 'subject_code',
            'active', 'exhausted', 'dominant_ideas_count',
            'status', 'locked', 'filled_at', 'traces', 'rules',
        ] as $field) {
            $this->assertArrayHasKey($field, $fields, "SubjectSlot.fields manque : {$field}");
        }

        $init = $ss['initial_state'];
        $this->assertNull($init['label']);
        $this->assertNull($init['subject_id']);
        $this->assertNull($init['subject_code']);
        $this->assertFalse($init['active']);
        $this->assertFalse($init['exhausted']);
        $this->assertSame(0,       $init['dominant_ideas_count']);
        $this->assertSame('EMPTY', $init['status']);
        $this->assertFalse($init['locked']);
        $this->assertNull($init['filled_at']);

        $this->assertArrayHasKey('lifecycle',      $ss);
        $this->assertArrayHasKey('no_archived_v1', $ss);
        $this->assertStringContainsString('EMPTY',     $ss['lifecycle']);
        $this->assertStringContainsString('ACTIVE',    $ss['lifecycle']);
        $this->assertStringContainsString('EXHAUSTED', $ss['lifecycle']);
        $this->assertStringNotContainsString('ARCHIVED', $ss['lifecycle']);
        $this->assertStringContainsString('ARCHIVED', $ss['no_archived_v1']);
        $this->assertStringContainsString('future',   $ss['no_archived_v1']);
    }

    public function test_dominant_idea_slot_contract_has_all_required_sections(): void
    {
        $di = $this->skeleton()['object_contracts']['DominantIdeaSlot'];

        $this->assertArrayHasKey('description',   $di);
        $this->assertArrayHasKey('filled_by',     $di);
        $this->assertArrayHasKey('capacity',      $di);
        $this->assertArrayHasKey('fields',        $di);
        $this->assertArrayHasKey('initial_state', $di);
        $this->assertArrayHasKey('lifecycle',     $di);

        $this->assertSame(5, $di['capacity']);
        $this->assertStringContainsString('Taxonomy', $di['filled_by']);

        $fields = $di['fields'];
        foreach ([
            'index', 'idea', 'idea_id', 'idea_code',
            'active', 'rejected', 'validated',
            'status', 'filled_at',
        ] as $field) {
            $this->assertArrayHasKey($field, $fields, "DominantIdeaSlot.fields manque : {$field}");
        }
        $this->assertArrayNotHasKey('consumed', $fields, 'DominantIdeaSlot ne doit pas avoir de champ consumed');

        $init = $di['initial_state'];
        $this->assertNull($init['idea']);
        $this->assertFalse($init['active']);
        $this->assertArrayNotHasKey('consumed', $init, 'DominantIdeaSlot.initial_state ne doit pas avoir consumed');
        $this->assertFalse($init['rejected']);
        $this->assertFalse($init['validated']);
        $this->assertSame('EMPTY', $init['status']);

        $this->assertStringContainsString('EMPTY',                    $di['lifecycle']);
        $this->assertStringContainsString('FILLED',                   $di['lifecycle']);
        $this->assertStringContainsString('ACTIVE',                   $di['lifecycle']);
        $this->assertStringContainsString('LOCKED_BY_QUESTION_INTENT', $di['lifecycle']);
        $this->assertStringContainsString('REJECTED',                 $di['lifecycle']);

        $this->assertArrayHasKey('no_consumed', $di);
        $this->assertStringContainsString('CONSUMED', $di['no_consumed']);
        $this->assertStringContainsString('player_kernel_cognitive_usage', $di['no_consumed']);
    }

    public function test_cognitive_slot_contract_has_all_required_sections(): void
    {
        $cs = $this->skeleton()['object_contracts']['CognitiveSlot'];

        $this->assertArrayHasKey('description', $cs);
        $this->assertArrayHasKey('filled_by',   $cs);
        $this->assertArrayHasKey('count',       $cs);
        $this->assertArrayHasKey('fields',      $cs);
        $this->assertArrayHasKey('initial_state', $cs);

        $this->assertSame(7, $cs['count']);
        $this->assertStringContainsString('Phase1', $cs['filled_by']);
        $this->assertStringContainsString('Phase3', $cs['filled_by']);

        $fields = $cs['fields'];
        foreach ([
            'variant_key', 'cognitive_family', 'cognitive_form',
            'question_slot', 'answer_slots', 'correct_answer_key',
            'sv_slot', 'translation_slots', 'status', 'traces',
        ] as $field) {
            $this->assertArrayHasKey($field, $fields, "CognitiveSlot.fields manque : {$field}");
        }

        $this->assertStringContainsString('qcm', $fields['cognitive_family']);
        $this->assertStringContainsString('true_false', $fields['cognitive_family']);
        $this->assertNull($cs['initial_state']['correct_answer_key']);
        $this->assertSame('EMPTY', $cs['initial_state']['status']);

        $this->assertArrayHasKey('lifecycle',   $cs);
        $this->assertArrayHasKey('no_consumed', $cs);
        $this->assertStringContainsString('READY_BANK', $cs['lifecycle']);
        $this->assertStringContainsString('CONSUMED',   $cs['no_consumed']);
        $this->assertStringContainsString('player_kernel_cognitive_usage', $cs['no_consumed']);
    }

    public function test_question_slot_contract_has_gameplay_constraints_and_initial_state(): void
    {
        $qs = $this->skeleton()['object_contracts']['QuestionSlot'];

        $this->assertArrayHasKey('description',    $qs);
        $this->assertArrayHasKey('filled_by',      $qs);
        $this->assertArrayHasKey('language',       $qs);
        $this->assertArrayHasKey('fields',         $qs);
        $this->assertArrayHasKey('initial_state',  $qs);

        $this->assertSame('en', $qs['language']);
        $this->assertStringContainsString('Phase1', $qs['filled_by']);

        $fields = $qs['fields'];
        $this->assertArrayHasKey('value',                $fields);
        $this->assertArrayHasKey('gameplay_constraints', $fields);
        $this->assertArrayHasKey('language',             $fields);
        $this->assertArrayNotHasKey('consumed',          $fields, 'QuestionSlot ne doit pas avoir de champ consumed');
        $this->assertArrayNotHasKey('validation_state',  $fields, 'QuestionSlot ne doit pas avoir validation_state (doublon de status)');

        $gc = $fields['gameplay_constraints'];
        $this->assertArrayHasKey('question_type',  $gc);
        $this->assertArrayHasKey('max_chars',      $gc);
        $this->assertArrayHasKey('timing_weight',  $gc);

        $init = $qs['initial_state'];
        $this->assertNull($init['value']);
        $this->assertArrayNotHasKey('consumed',         $init, 'QuestionSlot.initial_state ne doit pas avoir consumed');
        $this->assertArrayNotHasKey('validation_state', $init, 'QuestionSlot.initial_state ne doit pas avoir validation_state');
        $this->assertSame('EMPTY', $init['status']);
        $this->assertFalse($init['locked']);
        $this->assertNull($init['filled_at']);

        $this->assertArrayHasKey('lifecycle',   $qs);
        $this->assertArrayHasKey('no_consumed', $qs);
        $this->assertStringContainsString('READY_BANK', $qs['lifecycle']);
        $this->assertStringContainsString('CONSUMED',   $qs['no_consumed']);
    }

    public function test_answer_slot_contract_has_is_correct_and_constraint(): void
    {
        $as = $this->skeleton()['object_contracts']['AnswerSlot'];

        $this->assertArrayHasKey('description',   $as);
        $this->assertArrayHasKey('filled_by',     $as);
        $this->assertArrayHasKey('language',      $as);
        $this->assertArrayHasKey('fields',        $as);
        $this->assertArrayHasKey('initial_state', $as);
        $this->assertArrayHasKey('constraint',    $as);

        $this->assertSame('en', $as['language']);
        $this->assertStringContainsString('1', $as['constraint']);
        $this->assertStringContainsString('is_correct', $as['constraint']);

        $fields = $as['fields'];
        foreach (['value', 'is_correct', 'answer_key', 'language', 'filled_at', 'status', 'locked'] as $f) {
            $this->assertArrayHasKey($f, $fields, "AnswerSlot.fields manque : {$f}");
        }

        $init = $as['initial_state'];
        $this->assertNull($init['value']);
        $this->assertFalse($init['is_correct']);
        $this->assertNull($init['answer_key']);
        $this->assertSame('EMPTY', $init['status']);

        $this->assertArrayHasKey('lifecycle',   $as);
        $this->assertArrayHasKey('no_consumed', $as);
        $this->assertStringContainsString('READY_BANK', $as['lifecycle']);
        $this->assertStringContainsString('CONSUMED',   $as['no_consumed']);
    }

    public function test_sv_slot_contract_has_char_limits_and_language(): void
    {
        $sv = $this->skeleton()['object_contracts']['SVSlot'];

        $this->assertArrayHasKey('description',   $sv);
        $this->assertArrayHasKey('filled_by',     $sv);
        $this->assertArrayHasKey('language',      $sv);
        $this->assertArrayHasKey('fields',        $sv);
        $this->assertArrayHasKey('initial_state', $sv);

        $this->assertSame('en', $sv['language']);
        $this->assertStringContainsString('Phase1', $sv['filled_by']);

        $fields = $sv['fields'];
        foreach (['value', 'min_chars', 'max_chars', 'language', 'filled_at', 'status', 'locked'] as $f) {
            $this->assertArrayHasKey($f, $fields, "SVSlot.fields manque : {$f}");
        }

        $this->assertStringContainsString('220', $fields['max_chars']);
        $this->assertStringContainsString('100', $fields['max_chars']);

        $init = $sv['initial_state'];
        $this->assertNull($init['value']);
        $this->assertSame('EMPTY', $init['status']);

        $this->assertArrayHasKey('lifecycle',   $sv);
        $this->assertArrayHasKey('no_consumed', $sv);
        $this->assertStringContainsString('READY_BANK', $sv['lifecycle']);
        $this->assertStringContainsString('CONSUMED',   $sv['no_consumed']);
    }

    public function test_translation_slot_contract_has_all_fields_and_char_caps(): void
    {
        $ts = $this->skeleton()['object_contracts']['TranslationSlot'];

        $this->assertArrayHasKey('description',    $ts);
        $this->assertArrayHasKey('filled_by',      $ts);
        $this->assertArrayHasKey('languages',      $ts);
        $this->assertArrayHasKey('fields',         $ts);
        $this->assertArrayHasKey('initial_state',  $ts);
        $this->assertArrayHasKey('char_caps',      $ts);
        $this->assertArrayHasKey('lifecycle_note', $ts);
        $this->assertArrayNotHasKey('validated_by', $ts, 'TranslationSlot ne doit pas avoir validated_by en v1 — cycle Phase3 non verrouillé');

        $this->assertSame(9, count($ts['languages']));
        $this->assertContains('fr', $ts['languages']);
        $this->assertContains('zh', $ts['languages']);
        $this->assertContains('ar', $ts['languages']);

        $this->assertStringContainsString('Phase3', $ts['filled_by']);
        $this->assertStringContainsString('EMPTY',  $ts['lifecycle_note']);

        $fields = $ts['fields'];
        foreach ([
            'question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
            'correct_answer_key', 'explanation', 'saviez_vous',
            'answer_max', 'sv_max', 'status', 'locked', 'filled_at',
        ] as $f) {
            $this->assertArrayHasKey($f, $fields, "TranslationSlot.fields manque : {$f}");
        }

        $caps = $ts['char_caps'];
        $this->assertArrayHasKey('default_answer_max', $caps);
        $this->assertArrayHasKey('zh_answer_max',      $caps);
        $this->assertArrayHasKey('ar_answer_max',      $caps);
        $this->assertArrayHasKey('default_sv_max',     $caps);
        $this->assertArrayHasKey('zh_sv_max',          $caps);
        $this->assertArrayHasKey('ar_sv_max',          $caps);
        $this->assertSame(60,  $caps['default_answer_max']);
        $this->assertSame(30,  $caps['zh_answer_max']);
        $this->assertSame(220, $caps['default_sv_max']);
        $this->assertSame(100, $caps['zh_sv_max']);

        $init = $ts['initial_state'];
        $this->assertNull($init['question_text']);
        $this->assertNull($init['saviez_vous']);
        $this->assertSame('EMPTY', $init['status']);
        $this->assertFalse($init['locked']);
    }

    // =========================================================================
    // 14. relation_map — Schéma des relations hiérarchiques
    // =========================================================================

    public function test_relation_map_key_exists_in_skeleton(): void
    {
        $this->assertArrayHasKey('relation_map', $this->skeleton());
    }

    public function test_relation_map_has_required_keys(): void
    {
        $rm = $this->skeleton()['relation_map'];

        $this->assertArrayHasKey('description',                   $rm);
        $this->assertArrayHasKey('chain',                         $rm);
        $this->assertArrayHasKey('relations',                     $rm);
        $this->assertArrayHasKey('total_cognitifs_per_noyau',     $rm);
        $this->assertArrayHasKey('total_translations_per_noyau',  $rm);
        $this->assertArrayHasKey('total_subjects_inventory',      $rm);
        $this->assertArrayHasKey('total_ideas_per_subject',       $rm);
    }

    public function test_relation_map_chain_encodes_1_50_1_5_1_7(): void
    {
        $chain = $this->skeleton()['relation_map']['chain'];

        $this->assertStringContainsString('50',  $chain);
        $this->assertStringContainsString('5',   $chain);
        $this->assertStringContainsString('7',   $chain);
        $this->assertStringContainsString('KernelFrame',   $chain);
        $this->assertStringContainsString('SubjectSlot',   $chain);
        $this->assertStringContainsString('DominantIdeaSlot', $chain);
        $this->assertStringContainsString('CognitiveSlot', $chain);
    }

    public function test_relation_map_has_9_relations(): void
    {
        $relations = $this->skeleton()['relation_map']['relations'];

        $this->assertIsArray($relations);
        $this->assertCount(9, $relations);
    }

    public function test_relation_map_each_relation_has_required_keys(): void
    {
        $relations = $this->skeleton()['relation_map']['relations'];

        foreach ($relations as $i => $rel) {
            foreach (['from', 'to', 'cardinality', 'slot', 'note'] as $key) {
                $this->assertArrayHasKey($key, $rel, "relation[{$i}] manque : {$key}");
            }
        }
    }

    public function test_relation_map_totals_are_consistent(): void
    {
        $rm = $this->skeleton()['relation_map'];

        $this->assertSame(7,   $rm['total_cognitifs_per_noyau']);
        $this->assertSame(63,  $rm['total_translations_per_noyau']);  // 7 × 9
        $this->assertSame(50,  $rm['total_subjects_inventory']);
        $this->assertSame(5,   $rm['total_ideas_per_subject']);
    }

    public function test_relation_map_includes_answer_slot_cardinality_2_or_4(): void
    {
        $relations = $this->skeleton()['relation_map']['relations'];

        $answerRel = collect($relations)->first(fn($r) => $r['to'] === 'AnswerSlot');
        $this->assertNotNull($answerRel, 'relation_map devrait inclure AnswerSlot');
        $this->assertStringContainsString('2', $answerRel['cardinality']);
        $this->assertStringContainsString('4', $answerRel['cardinality']);
        $this->assertStringContainsString('is_correct', $answerRel['note']);
    }

    public function test_relation_map_includes_translation_slot_with_9_langs(): void
    {
        $relations = $this->skeleton()['relation_map']['relations'];

        $transRel = collect($relations)->first(fn($r) => $r['to'] === 'TranslationSlot');
        $this->assertNotNull($transRel, 'relation_map devrait inclure TranslationSlot');
        $this->assertStringContainsString('9', $transRel['cardinality']);
        $this->assertStringContainsString('Phase3', $transRel['note']);
    }

    // =========================================================================
    // 15. Compatibilité legacy (kernel_core / variants / translation_constraints)
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
        $this->assertSame('EMPTY', $decoded['kernel_code']['status']);
        $this->assertCount(50, $decoded['subjects_inventory']);
        $this->assertCount(7, $decoded['cognitive_slots']);
        $this->assertCount(10, $decoded['statuses']);
        $this->assertSame([], $decoded['traces']);
        $this->assertSame('yy-xx-xxx-xxx-xxx-zz', $decoded['rules']['kernel_code_format']);
    }
}
