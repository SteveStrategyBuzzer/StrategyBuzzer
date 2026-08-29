<?php

namespace Tests\Unit\QuestionBank;

use App\Models\QuestionIntent;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelFrameBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — Kernel Blueprint Frame (thin ticket, 2026-07-07)
 *
 * KernelFrameBuilder est une pure function : lit un QuestionIntent,
 * retourne un array. Aucune DB, aucun appel AI, aucune migration.
 * Étend PHPUnit\Framework\TestCase directement (pas Tests\TestCase)
 * pour éviter RefreshDatabase et les migrations SQLite incompatibles.
 *
 * Architecture Blueprint validée :
 *   A — ROTATION        : depth, domain_code, rotation_identifier (null à la construction)
 *   B — CONTENU COURANT : sub_domain, subject, dominant_idea, knowledge_frequency (null)
 *   C — MÉCANISMES      : kld_result, ks_result, ks_hash (null)
 *   D — INTENT          : semantic_key, intent_hash, intent_keys ([])
 *   E — COGNITIFS       : cognitive_slots (7 coquilles vides)
 *   F — IDENTITÉ        : kernel_code (null, ou valeur du Blueprint canonique fourni)
 *   G — PIPELINE        : statuses (10 étapes null), traces ([])
 *   LEGACY              : kernel_core, translation_constraints, variants
 *
 * Ce qui N'EST PAS dans le Blueprint :
 *   subjects_inventory, dominant_ideas (liste), active_subject (pointeur réservoir),
 *   active_dominant_idea (pointeur réservoir), object_contracts, relation_map,
 *   rules (top-level), mechanisms, constraints, history, remaining_subjects,
 *   remaining_ideas.
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

    private function skeleton(array $overrides = []): array
    {
        if ($overrides) {
            return $this->builder->buildSkeleton($this->makeIntent($overrides));
        }
        return $this->builder->buildSkeleton($this->intent);
    }

    // =========================================================================
    // 1. Structure racine — ticket mince
    // =========================================================================

    public function test_returns_an_array(): void
    {
        $this->assertIsArray($this->skeleton());
    }

    public function test_contains_all_blueprint_top_level_keys(): void
    {
        $frame = $this->skeleton();

        $expectedKeys = [
            'schema_version', 'content_version',
            // A — ROTATION
            'depth', 'domain_code', 'rotation_identifier',
            // B — CONTENU COURANT
            'sub_domain', 'subject', 'dominant_idea', 'knowledge_frequency',
            // C — MÉCANISMES
            'kld_result', 'ks_result', 'ks_hash',
            // D — INTENT
            'semantic_key', 'intent_hash', 'intent_keys',
            // E — COGNITIFS
            'cognitive_slots',
            // F — IDENTITÉ
            'kernel_code',
            // G — PIPELINE
            'statuses', 'traces',
            // LEGACY
            'kernel_core', 'translation_constraints', 'variants',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $frame, "Blueprint manque la clé : {$key}");
        }
    }

    public function test_schema_version_is_2_0_0(): void
    {
        $this->assertSame('2.0.0', $this->skeleton()['schema_version']);
    }

    // =========================================================================
    // 2. Absence du réservoir — le Blueprint NE contient PAS ces clés
    // =========================================================================

    /**
     * @dataProvider reservoirKeysProvider
     */
    public function test_reservoir_keys_absent_from_blueprint(string $key): void
    {
        $this->assertArrayNotHasKey(
            $key,
            $this->skeleton(),
            "Clé réservoir '{$key}' trouvée dans le Blueprint — elle appartient au réservoir externe."
        );
    }

    public static function reservoirKeysProvider(): array
    {
        return [
            ['subjects_inventory'],
            ['dominant_ideas'],
            ['active_subject'],
            ['active_dominant_idea'],
            ['object_contracts'],
            ['relation_map'],
            ['mechanisms'],
            ['constraints'],
            ['history'],
            ['remaining_subjects'],
            ['remaining_ideas'],
        ];
    }

    // =========================================================================
    // 3. Section A — ROTATION (null à la construction)
    // =========================================================================

    public function test_section_a_depth_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['depth']);
    }

    public function test_section_a_domain_code_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['domain_code']);
    }

    public function test_section_a_rotation_identifier_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['rotation_identifier']);
    }

    // =========================================================================
    // 4. Section B — CONTENU COURANT (null à la construction)
    // =========================================================================

    public function test_section_b_sub_domain_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['sub_domain']);
    }

    public function test_section_b_subject_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['subject']);
    }

    public function test_section_b_dominant_idea_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['dominant_idea']);
    }

    public function test_section_b_knowledge_frequency_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['knowledge_frequency']);
    }

    // =========================================================================
    // 5. Section C — RÉSULTATS MÉCANISMES (null à la construction)
    // =========================================================================

    public function test_section_c_kld_result_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['kld_result']);
    }

    public function test_section_c_ks_result_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['ks_result']);
    }

    public function test_section_c_ks_hash_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['ks_hash']);
    }

    // =========================================================================
    // 6. Section D — IDENTIFIANTS INTENT (null / [] à la construction)
    // =========================================================================

    public function test_section_d_semantic_key_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['semantic_key']);
    }

    public function test_section_d_intent_hash_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['intent_hash']);
    }

    public function test_section_d_intent_keys_is_empty_array_at_construction(): void
    {
        $intentKeys = $this->skeleton()['intent_keys'];
        $this->assertIsArray($intentKeys);
        $this->assertEmpty($intentKeys);
    }

    // =========================================================================
    // 7. Section E — COGNITIVE SLOTS (7 variants × coquilles vides)
    // =========================================================================

    public function test_cognitive_slots_is_an_array(): void
    {
        $this->assertIsArray($this->skeleton()['cognitive_slots']);
    }

    public function test_cognitive_slots_has_exactly_7_variants(): void
    {
        $this->assertCount(7, $this->skeleton()['cognitive_slots']);
    }

    public function test_cognitive_slots_variant_keys_are_the_7_expected(): void
    {
        $expectedVariants = [
            'qcm_recognition', 'qcm_reasoning', 'qcm_deceptive_trap',
            'tf_recognition_true', 'tf_recognition_false',
            'tf_reasoning_true', 'tf_reasoning_false',
        ];

        $actualKeys = array_keys($this->skeleton()['cognitive_slots']);
        sort($expectedVariants);
        sort($actualKeys);

        $this->assertSame($expectedVariants, $actualKeys);
    }

    public function test_cognitive_slots_each_variant_has_required_top_keys(): void
    {
        $required = [
            'question_type', 'cognitive_type',
            'question_slot', 'answer_slots', 'correct_answer_key',
            'sv_slot', 'translation_slots',
            'status', 'rules', 'traces',
        ];

        foreach ($this->skeleton()['cognitive_slots'] as $key => $slot) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $slot, "cognitive_slots[{$key}] manque : {$field}");
            }
        }
    }

    public function test_cognitive_slots_qcm_variants_have_4_answer_slots(): void
    {
        $qcmKeys = ['qcm_recognition', 'qcm_reasoning', 'qcm_deceptive_trap'];
        $slots   = $this->skeleton()['cognitive_slots'];

        foreach ($qcmKeys as $key) {
            $this->assertCount(4, $slots[$key]['answer_slots'], "{$key} devrait avoir 4 answer_slots");
            $this->assertArrayHasKey('answer_a', $slots[$key]['answer_slots']);
            $this->assertArrayHasKey('answer_b', $slots[$key]['answer_slots']);
            $this->assertArrayHasKey('answer_c', $slots[$key]['answer_slots']);
            $this->assertArrayHasKey('answer_d', $slots[$key]['answer_slots']);
        }
    }

    public function test_cognitive_slots_tf_variants_have_2_answer_slots(): void
    {
        $tfKeys = ['tf_recognition_true', 'tf_recognition_false', 'tf_reasoning_true', 'tf_reasoning_false'];
        $slots  = $this->skeleton()['cognitive_slots'];

        foreach ($tfKeys as $key) {
            $this->assertCount(2, $slots[$key]['answer_slots'], "{$key} devrait avoir 2 answer_slots");
            $this->assertArrayHasKey('answer_a', $slots[$key]['answer_slots']);
            $this->assertArrayHasKey('answer_b', $slots[$key]['answer_slots']);
        }
    }

    public function test_cognitive_slots_question_slot_is_empty_at_construction(): void
    {
        foreach ($this->skeleton()['cognitive_slots'] as $key => $slot) {
            $qs = $slot['question_slot'];
            $this->assertNull($qs['value'],     "question_slot.value devrait être null pour {$key}");
            $this->assertNull($qs['filled_at'], "question_slot.filled_at devrait être null pour {$key}");
            $this->assertSame('EMPTY', $qs['status'], "question_slot.status devrait être EMPTY pour {$key}");
            $this->assertFalse($qs['locked'],   "question_slot.locked devrait être false pour {$key}");
        }
    }

    public function test_cognitive_slots_sv_slot_is_empty_at_construction(): void
    {
        foreach ($this->skeleton()['cognitive_slots'] as $key => $slot) {
            $sv = $slot['sv_slot'];
            $this->assertNull($sv['value'],     "sv_slot.value devrait être null pour {$key}");
            $this->assertSame('EMPTY', $sv['status'], "sv_slot.status devrait être EMPTY pour {$key}");
        }
    }

    public function test_cognitive_slots_correct_answer_key_is_null_at_construction(): void
    {
        foreach ($this->skeleton()['cognitive_slots'] as $key => $slot) {
            $this->assertNull($slot['correct_answer_key'], "correct_answer_key devrait être null pour {$key}");
        }
    }

    public function test_cognitive_slots_status_is_empty_at_construction(): void
    {
        foreach ($this->skeleton()['cognitive_slots'] as $key => $slot) {
            $this->assertSame('EMPTY', $slot['status'], "status devrait être EMPTY pour {$key}");
        }
    }

    public function test_cognitive_slots_translation_slots_have_9_languages(): void
    {
        $expectedLangs = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

        foreach ($this->skeleton()['cognitive_slots'] as $key => $slot) {
            $actualLangs = array_keys($slot['translation_slots']);
            sort($actualLangs);
            $sorted = $expectedLangs;
            sort($sorted);
            $this->assertSame($sorted, $actualLangs, "translation_slots de {$key} doit avoir 9 langues");
        }
    }

    public function test_cognitive_slots_translation_slots_empty_at_construction(): void
    {
        foreach ($this->skeleton()['cognitive_slots'] as $varKey => $slot) {
            foreach ($slot['translation_slots'] as $lang => $ts) {
                $this->assertSame('EMPTY', $ts['status'],
                    "cognitive_slots[{$varKey}][translation_slots][{$lang}].status doit être EMPTY"
                );
                $this->assertNull($ts['question_text'],
                    "cognitive_slots[{$varKey}][translation_slots][{$lang}].question_text doit être null"
                );
            }
        }
    }

    public function test_cognitive_slots_qcm_question_type_is_qcm(): void
    {
        $qcmKeys = ['qcm_recognition', 'qcm_reasoning', 'qcm_deceptive_trap'];
        $slots   = $this->skeleton()['cognitive_slots'];

        foreach ($qcmKeys as $key) {
            $this->assertSame('qcm', $slots[$key]['question_type'], "{$key} question_type doit être 'qcm'");
        }
    }

    public function test_cognitive_slots_tf_question_type_is_true_false(): void
    {
        $tfKeys = ['tf_recognition_true', 'tf_recognition_false', 'tf_reasoning_true', 'tf_reasoning_false'];
        $slots  = $this->skeleton()['cognitive_slots'];

        foreach ($tfKeys as $key) {
            $this->assertSame('true_false', $slots[$key]['question_type'],
                "{$key} question_type doit être 'true_false'"
            );
        }
    }

    public function test_cognitive_slots_tf_translation_slots_c_and_d_are_na(): void
    {
        $tfKeys = ['tf_recognition_true', 'tf_recognition_false', 'tf_reasoning_true', 'tf_reasoning_false'];
        $slots  = $this->skeleton()['cognitive_slots'];

        foreach ($tfKeys as $key) {
            foreach ($slots[$key]['translation_slots'] as $lang => $ts) {
                $this->assertSame('n/a', $ts['answer_c'],
                    "{$key}[{$lang}].answer_c doit être 'n/a' pour TF"
                );
                $this->assertSame('n/a', $ts['answer_d'],
                    "{$key}[{$lang}].answer_d doit être 'n/a' pour TF"
                );
            }
        }
    }

    public function test_cognitive_slots_question_slot_rules_declare_creator(): void
    {
        foreach ($this->skeleton()['cognitive_slots'] as $key => $slot) {
            $rules = $slot['question_slot']['rules'];
            $this->assertSame('KernelFrameBuilder', $rules['creator'],
                "question_slot.rules.creator doit être KernelFrameBuilder pour {$key}"
            );
            $this->assertSame('Phase1 (KernelContentBuilder)', $rules['filler']);
        }
    }

    // =========================================================================
    // 8. Section F — IDENTITÉ NOYAU
    // =========================================================================

    public function test_section_f_kernel_code_is_null_at_construction(): void
    {
        $this->assertNull($this->skeleton()['kernel_code']);
    }

    public function test_section_f_uses_canonical_blueprint_kernel_code_when_available(): void
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId('bp-frame-0001');
        $blueprint->fillRotation(4, 'Science');
        $blueprint->fillTaxonomy('Physique', 'Lumière', 'Réfraction');
        $blueprint->fillKernelCode('04-SCI-PHY-LUM-REF-0001');

        $frame = $this->builder->buildSkeleton($this->intent, $blueprint);

        $this->assertSame('04-SCI-PHY-LUM-REF-0001', $frame['kernel_code']);
    }

    // =========================================================================
    // 9. Section G — PIPELINE STATUS & TRACES
    // =========================================================================

    public function test_section_g_statuses_is_array(): void
    {
        $this->assertIsArray($this->skeleton()['statuses']);
    }

    public function test_section_g_statuses_has_10_pipeline_stages(): void
    {
        $this->assertCount(10, $this->skeleton()['statuses']);
    }

    public function test_section_g_statuses_has_all_expected_stage_keys(): void
    {
        $expected = [
            'rotation', 'taxonomy', 'key_structure', 'kld', 'question_intent',
            'phase1_content', 'phase2_validation', 'phase3_translation',
            'phase4_translation_val', 'ready_bank',
        ];

        $actual = array_keys($this->skeleton()['statuses']);
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_section_g_statuses_all_null_at_construction(): void
    {
        foreach ($this->skeleton()['statuses'] as $stage => $value) {
            $this->assertNull($value, "statuses[{$stage}] doit être null à la construction");
        }
    }

    public function test_section_g_traces_is_empty_array_at_construction(): void
    {
        $traces = $this->skeleton()['traces'];
        $this->assertIsArray($traces);
        $this->assertEmpty($traces);
    }

    // =========================================================================
    // 10. LEGACY — kernel_core
    // =========================================================================

    public function test_legacy_kernel_core_exists(): void
    {
        $this->assertArrayHasKey('kernel_core', $this->skeleton());
    }

    public function test_legacy_kernel_core_has_required_fields(): void
    {
        $required = [
            'domain', 'sub_domain', 'difficulty_depth', 'default_reading_band',
            'concept_family', 'semantic_key', 'subject', 'angle_large',
            'micro_angle', 'answer_target', 'potential_trap', 'pedagogical_intent',
        ];

        $kc = $this->skeleton()['kernel_core'];

        foreach ($required as $field) {
            $this->assertArrayHasKey($field, $kc, "kernel_core manque : {$field}");
        }
    }

    public function test_legacy_kernel_core_is_prefilled_from_intent(): void
    {
        $kc = $this->skeleton()['kernel_core'];

        $this->assertSame('Géographie', $kc['domain']);
        $this->assertSame('Capitales',  $kc['sub_domain']);
        $this->assertSame(4,            $kc['difficulty_depth']);
        $this->assertSame('Nairobi',    $kc['subject']);
        $this->assertSame('économique', $kc['angle_large']);
        $this->assertSame('hub régional', $kc['micro_angle']);
        $this->assertSame('geo-cap-nairobi-economique-v1', $kc['semantic_key']);
        $this->assertNull($kc['pedagogical_intent']);
    }

    public function test_legacy_kernel_core_difficulty_depth_is_integer(): void
    {
        $this->assertIsInt($this->skeleton()['kernel_core']['difficulty_depth']);
    }

    public function test_legacy_kernel_core_default_reading_band_is_string(): void
    {
        $this->assertIsString($this->skeleton()['kernel_core']['default_reading_band']);
        $this->assertNotEmpty($this->skeleton()['kernel_core']['default_reading_band']);
    }

    // =========================================================================
    // 11. LEGACY — translation_constraints
    // =========================================================================

    public function test_legacy_translation_constraints_exists(): void
    {
        $this->assertArrayHasKey('translation_constraints', $this->skeleton());
    }

    public function test_legacy_translation_constraints_has_9_languages(): void
    {
        $tc    = $this->skeleton()['translation_constraints'];
        $langs = array_keys($tc);
        sort($langs);

        $expected = ['ar', 'de', 'el', 'es', 'fr', 'it', 'pt', 'ru', 'zh'];

        $this->assertSame($expected, $langs);
    }

    public function test_legacy_translation_constraints_each_lang_has_4_fields(): void
    {
        $required = ['question_max_length', 'answer_max_length', 'funFact_max_length', 'funFact_min_length'];

        foreach ($this->skeleton()['translation_constraints'] as $lang => $entry) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $entry, "translation_constraints[{$lang}] manque : {$field}");
                $this->assertIsInt($entry[$field], "translation_constraints[{$lang}][{$field}] doit être int");
            }
        }
    }

    public function test_legacy_translation_constraints_zh_has_reduced_caps(): void
    {
        $zh = $this->skeleton()['translation_constraints']['zh'];

        $this->assertSame(30, $zh['answer_max_length']);
        $this->assertSame(100, $zh['funFact_max_length']);
    }

    public function test_legacy_translation_constraints_ar_has_reduced_caps(): void
    {
        $ar = $this->skeleton()['translation_constraints']['ar'];

        $this->assertSame(40, $ar['answer_max_length']);
        $this->assertSame(140, $ar['funFact_max_length']);
    }

    // =========================================================================
    // 12. LEGACY — variants (7 variantes)
    // =========================================================================

    public function test_legacy_variants_exists(): void
    {
        $this->assertArrayHasKey('variants', $this->skeleton());
    }

    public function test_legacy_variants_has_exactly_7_variants(): void
    {
        $this->assertCount(7, $this->skeleton()['variants']);
    }

    public function test_legacy_variants_each_has_required_keys(): void
    {
        $required = [
            'question_type', 'cognitive_type', 'reading_band_override',
            'question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
            'correct_answer_key', 'explanation', 'saviez_vous',
            'cognitive_contract', 'gameplay_constraints', 'translation_slots', 'status',
        ];

        foreach ($this->skeleton()['variants'] as $key => $variant) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $variant, "variants[{$key}] manque : {$field}");
            }
        }
    }

    public function test_legacy_variants_each_has_9_translation_slots(): void
    {
        $expectedLangs = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

        foreach ($this->skeleton()['variants'] as $key => $variant) {
            $actualLangs = array_keys($variant['translation_slots']);
            sort($actualLangs);
            $sorted = $expectedLangs;
            sort($sorted);
            $this->assertSame($sorted, $actualLangs, "variants[{$key}] translation_slots doit avoir 9 langues");
        }
    }

    public function test_legacy_variants_total_translation_slots_are_63(): void
    {
        $total = 0;
        foreach ($this->skeleton()['variants'] as $variant) {
            $total += count($variant['translation_slots']);
        }

        $this->assertSame(63, $total, '7 variantes × 9 langues = 63 translation_slots');
    }

    public function test_legacy_variants_all_content_null_at_construction(): void
    {
        $nullFields = ['question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
                       'correct_answer_key', 'explanation', 'saviez_vous'];

        foreach ($this->skeleton()['variants'] as $key => $variant) {
            foreach ($nullFields as $field) {
                $this->assertNull($variant[$field], "variants[{$key}][{$field}] doit être null à la construction");
            }
        }
    }

    public function test_legacy_variants_status_is_empty_at_construction(): void
    {
        foreach ($this->skeleton()['variants'] as $key => $variant) {
            $this->assertSame('EMPTY', $variant['status'], "variants[{$key}].status doit être EMPTY");
        }
    }

    public function test_legacy_variants_cognitive_contract_not_empty(): void
    {
        foreach ($this->skeleton()['variants'] as $key => $variant) {
            $this->assertNotEmpty($variant['cognitive_contract'],
                "variants[{$key}].cognitive_contract ne doit pas être vide"
            );
        }
    }

    public function test_legacy_variants_gameplay_constraints_structure(): void
    {
        foreach ($this->skeleton()['variants'] as $key => $variant) {
            $gc = $variant['gameplay_constraints'];
            $this->assertArrayHasKey('display_mode',   $gc, "variants[{$key}] manque display_mode");
            $this->assertArrayHasKey('time_limit_sec', $gc, "variants[{$key}] manque time_limit_sec");
            $this->assertArrayHasKey('buzz_eligible',  $gc, "variants[{$key}] manque buzz_eligible");
        }
    }

    public function test_legacy_variants_qcm_display_mode_is_quad(): void
    {
        $qcmKeys = ['qcm_recognition', 'qcm_reasoning', 'qcm_deceptive_trap'];
        $variants = $this->skeleton()['variants'];

        foreach ($qcmKeys as $key) {
            $this->assertSame('quad', $variants[$key]['gameplay_constraints']['display_mode'],
                "{$key} display_mode doit être 'quad'"
            );
        }
    }

    public function test_legacy_variants_tf_display_mode_is_binary(): void
    {
        $tfKeys = ['tf_recognition_true', 'tf_recognition_false', 'tf_reasoning_true', 'tf_reasoning_false'];
        $variants = $this->skeleton()['variants'];

        foreach ($tfKeys as $key) {
            $this->assertSame('binary', $variants[$key]['gameplay_constraints']['display_mode'],
                "{$key} display_mode doit être 'binary'"
            );
        }
    }

    // =========================================================================
    // 13. Compatibilité commande skeleton (QuestionsKernelSkeletonCommand)
    // =========================================================================

    public function test_skeleton_command_compat_variant_count_is_7(): void
    {
        $variantCount = count($this->skeleton()['variants'] ?? []);
        $this->assertSame(7, $variantCount);
    }

    public function test_skeleton_command_compat_translation_slot_count_is_63(): void
    {
        $slotCount = 0;
        foreach ($this->skeleton()['variants'] as $v) {
            $slotCount += count($v['translation_slots'] ?? []);
        }
        $this->assertSame(63, $slotCount);
    }

    public function test_skeleton_command_compat_kernel_core_domain_readable(): void
    {
        $kc = $this->skeleton()['kernel_core'];
        $this->assertNotEmpty($kc['domain'] ?? null);
        $this->assertNotEmpty($kc['sub_domain'] ?? null);
        $this->assertNotEmpty($kc['semantic_key'] ?? null);
    }

    // =========================================================================
    // 14. Encodage JSON — le skeleton entier doit être encodable
    // =========================================================================

    public function test_skeleton_is_json_encodable(): void
    {
        $json = json_encode($this->skeleton(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertNotFalse($json, 'json_encode a échoué : ' . json_last_error_msg());
        $this->assertNotEmpty($json);
    }

    public function test_skeleton_json_round_trip_preserves_structure(): void
    {
        $skeleton = $this->skeleton();
        $json     = json_encode($skeleton, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $decoded  = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame($skeleton['schema_version'], $decoded['schema_version']);
        $this->assertCount(7, $decoded['cognitive_slots']);
        $this->assertCount(7, $decoded['variants']);
        $this->assertArrayNotHasKey('subjects_inventory', $decoded,
            'subjects_inventory ne doit pas être présent après encodage JSON'
        );
    }

    // =========================================================================
    // 15. Constantes publiques
    // =========================================================================

    public function test_dominant_ideas_max_constant_is_5(): void
    {
        $this->assertSame(5, KernelFrameBuilder::DOMINANT_IDEAS_MAX);
    }

    public function test_cognitive_count_constant_is_7(): void
    {
        $this->assertSame(7, KernelFrameBuilder::COGNITIVE_COUNT);
    }

    // =========================================================================
    // 16. Intent différent — depth différent
    // =========================================================================

    public function test_skeleton_with_depth_1_produces_valid_frame(): void
    {
        $frame = $this->skeleton(['difficulty_depth' => 1]);

        $this->assertSame('2.0.0', $frame['schema_version']);
        $this->assertNull($frame['depth']);
        $this->assertCount(7, $frame['cognitive_slots']);
        $this->assertSame(1, $frame['kernel_core']['difficulty_depth']);
    }

    public function test_skeleton_with_depth_10_produces_valid_frame(): void
    {
        $frame = $this->skeleton(['difficulty_depth' => 10]);

        $this->assertSame('2.0.0', $frame['schema_version']);
        $this->assertNull($frame['depth']);
        $this->assertCount(7, $frame['cognitive_slots']);
        $this->assertSame(10, $frame['kernel_core']['difficulty_depth']);
    }
}
