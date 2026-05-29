<?php

namespace App\Services\QuestionBank;

/**
 * KernelFrameValidator
 *
 * PHASE 1 — Étape 2 : validation structurelle de frame_en.
 *
 * Vérifie uniquement la STRUCTURE du squelette.
 * Aucune lecture DB, aucune écriture, aucun contenu EN.
 *
 * Niveaux de sortie :
 *   ERROR   → bloquant — frame_status reste 'draft'
 *   WARNING → non bloquant — frame passe quand même à 'awaiting_content'
 *
 * Retour :
 *   [
 *     'ok'       => bool,
 *     'errors'   => string[],
 *     'warnings' => string[],
 *     'summary'  => ['variant_count' => int, 'translation_slot_count' => int],
 *   ]
 */
class KernelFrameValidator
{
    // ─── Langues de traduction (9 — sans 'en') ───────────────────────────────
    private const TRANSLATION_LANGS = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

    // ─── 7 variantes obligatoires → [question_type, cognitive_type, polarity, answer_count, valid_keys]
    // polarity: null = QCM, 'true' = TF correct=True (key A), 'false' = TF correct=False (key B)
    private const VARIANT_META = [
        'qcm_recognition'      => ['qcm',        'recognition',    null,    4, ['A','B','C','D']],
        'qcm_reasoning'        => ['qcm',        'reasoning',      null,    4, ['A','B','C','D']],
        'qcm_deceptive_trap'   => ['qcm',        'deceptive_trap', null,    4, ['A','B','C','D']],
        'tf_recognition_true'  => ['true_false', 'recognition',    'true',  2, ['A','B']],
        'tf_recognition_false' => ['true_false', 'recognition',    'false', 2, ['A','B']],
        'tf_reasoning_true'    => ['true_false', 'reasoning',      'true',  2, ['A','B']],
        'tf_reasoning_false'   => ['true_false', 'reasoning',      'false', 2, ['A','B']],
    ];

    // ─── kernel_core : champs obligatoires non-null (identité noyau) ─────────
    private const KERNEL_REQUIRED_NON_NULL = [
        'domain', 'sub_domain', 'difficulty_depth', 'concept_family',
        'semantic_key', 'subject', 'angle_large', 'micro_angle', 'answer_target',
    ];

    // ─── kernel_core : champs optionnels (warning si null) ───────────────────
    private const KERNEL_OPTIONAL = ['potential_trap', 'pedagogical_intent', 'default_reading_band'];

    // ─── cognitive_contract clés requises par cognitif ───────────────────────
    // One set of required keys per cognitive type — mirrors KernelFrameBuilder::buildCognitiveContract().
    // Keys with null values ARE required to be present as keys in the skeleton
    // (they are later filled by the AI or the content builder).
    private const CC_KEYS_BY_VARIANT = [
        'qcm_recognition' => [
            'subject_scope',                                      // Fix 4 — grounding in sub_domain+subject
            'requires_inference', 'has_deceptive_distractor',
            'question_form', 'answer_directly_names_subject',
        ],
        'qcm_reasoning' => [
            'subject_scope',                                      // Fix 4
            'requires_inference', 'has_deceptive_distractor',
            'reasoning_type',
            'reasoning_scope', 'reasoning_anchor',               // Fix 1 — chain must derive from sub_domain+subject
            'answer_derives_from_subject', 'no_direct_recall',
        ],
        'qcm_deceptive_trap' => [
            'subject_scope',                                      // Fix 4
            'requires_inference', 'has_deceptive_distractor', 'trap_anchored_to',
            'trap_carriers',                                      // Fix 2 — which mechanics carry the trap
            'natural_hypothesis_triggered',                       // Fix 3 — step 1
            'hypothesis_overturned_after_full_read',              // Fix 3 — step 2
            'implicit_hypothesis', 'hypothesis_invalidated_by', 'reconstruction_required',
            'intuitive_wrong_answer', 'intuitive_answer_presence',
            'fairness_reason', 'alignment_with_kernel_core',
        ],
        'tf_recognition_true' => [
            'subject_scope',                                      // Fix 4
            'requires_inference', 'has_deceptive_distractor',
            'polarity', 'expected_master_proximity', 'proximity_is_never_penalized',
        ],
        'tf_recognition_false' => [
            'subject_scope',                                      // Fix 4
            'requires_inference', 'has_deceptive_distractor',
            'polarity', 'must_appear_plausible', 'correct_answer_key',
        ],
        'tf_reasoning_true' => [
            'subject_scope',                                      // Fix 4
            'requires_inference', 'has_deceptive_distractor',
            'polarity', 'reasoning_type',
            'reasoning_scope', 'reasoning_anchor',               // Fix 1
            'player_must_reason',
        ],
        'tf_reasoning_false' => [
            'subject_scope',                                      // Fix 4
            'requires_inference', 'has_deceptive_distractor',
            'polarity', 'trivial_inversion_forbidden', 'player_must_reason', 'reasoning_type',
            'reasoning_scope', 'reasoning_anchor',               // Fix 1
        ],
    ];

    // ─── translation_slots : champs obligatoires ──────────────────────────────
    private const SLOT_FIELDS = [
        'status', 'correct_answer_key', 'question_text',
        'answer_a', 'answer_b', 'answer_c', 'answer_d',
        'explanation', 'saviez_vous',
    ];

    // ─── translation_constraints : clés de longueur ───────────────────────────
    private const TC_KEYS = [
        'question_max_length', 'answer_max_length', 'funFact_max_length', 'funFact_min_length',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Point d'entrée
    // ─────────────────────────────────────────────────────────────────────────

    public function validateStructure(array $frame): array
    {
        $errors   = [];
        $warnings = [];

        $this->checkKernelCore($frame, $errors, $warnings);
        $this->checkTranslationConstraints($frame, $errors, $warnings);
        $this->checkVariants($frame, $errors, $warnings);

        $variantCount = isset($frame['variants']) && is_array($frame['variants'])
            ? count($frame['variants'])
            : 0;

        $slotCount = 0;
        if (isset($frame['variants']) && is_array($frame['variants'])) {
            foreach ($frame['variants'] as $v) {
                if (isset($v['translation_slots']) && is_array($v['translation_slots'])) {
                    $slotCount += count($v['translation_slots']);
                }
            }
        }

        return [
            'ok'       => count($errors) === 0,
            'errors'   => $errors,
            'warnings' => $warnings,
            'summary'  => [
                'variant_count'           => $variantCount,
                'translation_slot_count'  => $slotCount,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Check 1 — kernel_core
    // ─────────────────────────────────────────────────────────────────────────

    private function checkKernelCore(array $frame, array &$errors, array &$warnings): void
    {
        if (! isset($frame['kernel_core']) || ! is_array($frame['kernel_core'])) {
            $errors[] = 'kernel_core manquant ou invalide';
            return;
        }

        $kc = $frame['kernel_core'];

        // Champs obligatoires non-null
        foreach (self::KERNEL_REQUIRED_NON_NULL as $field) {
            if (! array_key_exists($field, $kc)) {
                $errors[] = "kernel_core.{$field} absent";
            } elseif ($kc[$field] === null || $kc[$field] === '') {
                $errors[] = "kernel_core.{$field} est null/vide";
            }
        }

        // Champs optionnels (warning si null)
        foreach (self::KERNEL_OPTIONAL as $field) {
            if (! array_key_exists($field, $kc)) {
                $errors[] = "kernel_core.{$field} absent (clé manquante)";
            } elseif ($kc[$field] === null) {
                $warnings[] = "kernel_core.{$field} est null (sera rempli Phase 3)";
            }
        }

        // difficulty_depth doit être entier 3-9
        if (array_key_exists('difficulty_depth', $kc) && $kc['difficulty_depth'] !== null) {
            $d = (int) $kc['difficulty_depth'];
            if ($d < 3 || $d > 9) {
                $errors[] = "kernel_core.difficulty_depth hors plage : {$d} (attendu 3-9)";
            }
        }

        // default_reading_band — valider la valeur si présente
        if (array_key_exists('default_reading_band', $kc) && $kc['default_reading_band'] !== null) {
            $validBands = ['slow_reader_safe', 'normal_reader', 'fast_reader_dense'];
            if (!in_array($kc['default_reading_band'], $validBands, true)) {
                $errors[] = "kernel_core.default_reading_band valeur inconnue: {$kc['default_reading_band']} (attendu: " . implode('|', $validBands) . ')';
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Check 2 — translation_constraints
    // ─────────────────────────────────────────────────────────────────────────

    private function checkTranslationConstraints(array $frame, array &$errors, array &$warnings): void
    {
        if (! isset($frame['translation_constraints']) || ! is_array($frame['translation_constraints'])) {
            $errors[] = 'translation_constraints manquant ou invalide';
            return;
        }

        $tc = $frame['translation_constraints'];

        foreach (self::TRANSLATION_LANGS as $lang) {
            if (! isset($tc[$lang]) || ! is_array($tc[$lang])) {
                $errors[] = "translation_constraints.{$lang} absent";
                continue;
            }
            foreach (self::TC_KEYS as $key) {
                if (! array_key_exists($key, $tc[$lang])) {
                    $errors[] = "translation_constraints.{$lang}.{$key} absent";
                } elseif (! is_int($tc[$lang][$key]) && ! is_float($tc[$lang][$key])) {
                    $errors[] = "translation_constraints.{$lang}.{$key} n'est pas un entier";
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Check 3-7 — variants
    // ─────────────────────────────────────────────────────────────────────────

    private function checkVariants(array $frame, array &$errors, array &$warnings): void
    {
        if (! isset($frame['variants']) || ! is_array($frame['variants'])) {
            $errors[] = 'variants manquant ou invalide';
            return;
        }

        $variants = $frame['variants'];

        // Check 3 — exactement les 5 clés, ni plus ni moins
        $expectedKeys = array_keys(self::VARIANT_META);
        $presentKeys  = array_keys($variants);
        sort($expectedKeys);
        sort($presentKeys);

        $missing = array_diff($expectedKeys, $presentKeys);
        $extra   = array_diff($presentKeys, $expectedKeys);

        foreach ($missing as $k) {
            $errors[] = "variants.{$k} absent";
        }
        foreach ($extra as $k) {
            $errors[] = "variants.{$k} clé non attendue";
        }

        // Check 4-7 — chaque variante individuellement
        foreach (self::VARIANT_META as $variantKey => [$qType, $cogType, $polarity, $ansCount, $ansKeys]) {
            if (! isset($variants[$variantKey])) {
                continue; // déjà signalé ci-dessus
            }
            $v = $variants[$variantKey];
            $this->checkVariant($variantKey, $v, $qType, $cogType, $polarity, $ansCount, $ansKeys, $errors, $warnings);
        }
    }

    private function checkVariant(
        string  $variantKey,
        array   $v,
        string  $expectedQType,
        string  $expectedCogType,
        ?string $expectedPolarity,
        int     $expectedAnsCount,
        array   $expectedAnsKeys,
        array   &$errors,
        array   &$warnings
    ): void {
        $pfx = "variants.{$variantKey}";

        // Check 4 — question_type et cognitive_type
        if (($v['question_type'] ?? null) !== $expectedQType) {
            $errors[] = "{$pfx}.question_type invalide : '{$v['question_type']}' (attendu '{$expectedQType}')";
        }
        if (($v['cognitive_type'] ?? null) !== $expectedCogType) {
            $errors[] = "{$pfx}.cognitive_type invalide : '{$v['cognitive_type']}' (attendu '{$expectedCogType}')";
        }

        // Check 4b — polarity for TF variants (enforced only after content is filled)
        // Phase 1 Étape 1 skeleton: correct_answer_key is null → skip polarity check.
        // Phase 1 Étape 3 content: validate polarity matches correct_answer_key.
        if ($expectedPolarity !== null && ($v['correct_answer_key'] ?? null) !== null) {
            $expectedKey = $expectedPolarity === 'true' ? 'A' : 'B';
            $actualKey   = strtoupper((string) ($v['correct_answer_key'] ?? ''));
            if ($actualKey !== $expectedKey) {
                $errors[] = "{$pfx}.correct_answer_key doit être '{$expectedKey}' (polarity={$expectedPolarity}) — valeur actuelle : '{$actualKey}'";
            }
        }

        // Check 4 — gameplay_constraints
        $this->checkGameplayConstraints($pfx, $v['gameplay_constraints'] ?? null, $expectedQType, $expectedAnsCount, $expectedAnsKeys, $errors);

        // Check 4+5 — cognitive_contract
        $this->checkCognitiveContract($pfx, $v['cognitive_contract'] ?? null, $variantKey, $errors);

        // Check 7 — contenu EN encore vide (tous null en Phase 1 Étape 1)
        $this->checkContentEmpty($pfx, $v, $errors, $warnings);

        // Check 6 — translation_slots
        $this->checkTranslationSlots($pfx, $v['translation_slots'] ?? null, $errors);
    }

    private function checkGameplayConstraints(
        string  $pfx,
        mixed   $gc,
        string  $expectedQType,
        int     $expectedAnsCount,
        array   $expectedAnsKeys,
        array   &$errors
    ): void {
        if (! is_array($gc)) {
            $errors[] = "{$pfx}.gameplay_constraints manquant ou invalide";
            return;
        }

        if (($gc['question_type'] ?? null) !== $expectedQType) {
            $errors[] = "{$pfx}.gameplay_constraints.question_type invalide";
        }
        if ((int) ($gc['answer_count'] ?? 0) !== $expectedAnsCount) {
            $errors[] = "{$pfx}.gameplay_constraints.answer_count invalide : {$gc['answer_count']} (attendu {$expectedAnsCount})";
        }

        $actualKeys = $gc['answer_keys_allowed'] ?? [];
        sort($actualKeys);
        $expectedSorted = $expectedAnsKeys;
        sort($expectedSorted);
        if ($actualKeys !== $expectedSorted) {
            $errors[] = "{$pfx}.gameplay_constraints.answer_keys_allowed invalide : [" . implode(',', $actualKeys) . "] (attendu [" . implode(',', $expectedSorted) . "])";
        }
    }

    private function checkCognitiveContract(
        string  $pfx,
        mixed   $cc,
        string  $variantKey,
        array   &$errors
    ): void {
        if (! is_array($cc)) {
            $errors[] = "{$pfx}.cognitive_contract manquant ou invalide";
            return;
        }

        $requiredKeys = self::CC_KEYS_BY_VARIANT[$variantKey] ?? [];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $cc)) {
                $errors[] = "{$pfx}.cognitive_contract.{$key} absent";
            }
        }
    }

    private function checkContentEmpty(
        string  $pfx,
        array   $v,
        array   &$errors,
        array   &$warnings
    ): void {
        $enFields = [
            'question_text', 'answer_a', 'answer_b', 'answer_c', 'answer_d',
            'correct_answer_key', 'explanation', 'saviez_vous',
        ];

        foreach ($enFields as $field) {
            if (! array_key_exists($field, $v)) {
                $errors[] = "{$pfx}.{$field} clé absente du squelette";
                continue;
            }
            if ($v[$field] !== null) {
                $errors[] = "{$pfx}.{$field} doit être null en Phase 1 Étape 1 (valeur : '{$v[$field]}')";
            }
        }
    }

    private function checkTranslationSlots(
        string  $pfx,
        mixed   $slots,
        array   &$errors
    ): void {
        if (! is_array($slots)) {
            $errors[] = "{$pfx}.translation_slots manquant ou invalide";
            return;
        }

        $presentLangs = array_keys($slots);
        $missing = array_diff(self::TRANSLATION_LANGS, $presentLangs);
        $extra   = array_diff($presentLangs, self::TRANSLATION_LANGS);

        foreach ($missing as $lang) {
            $errors[] = "{$pfx}.translation_slots.{$lang} absent";
        }
        foreach ($extra as $lang) {
            $errors[] = "{$pfx}.translation_slots.{$lang} langue non attendue";
        }

        foreach (self::TRANSLATION_LANGS as $lang) {
            if (! isset($slots[$lang]) || ! is_array($slots[$lang])) {
                continue; // déjà signalé
            }
            $slot = $slots[$lang];

            // Clés obligatoires
            foreach (self::SLOT_FIELDS as $field) {
                if (! array_key_exists($field, $slot)) {
                    $errors[] = "{$pfx}.translation_slots.{$lang}.{$field} absent";
                }
            }

            // status = 'pending'
            if (array_key_exists('status', $slot) && $slot['status'] !== 'pending') {
                $errors[] = "{$pfx}.translation_slots.{$lang}.status doit être 'pending' (valeur : '{$slot['status']}')";
            }

            // Tous les champs sauf status doivent être null
            $nullFields = array_diff(self::SLOT_FIELDS, ['status']);
            foreach ($nullFields as $field) {
                if (array_key_exists($field, $slot) && $slot[$field] !== null) {
                    $errors[] = "{$pfx}.translation_slots.{$lang}.{$field} doit être null (valeur non null trouvée)";
                }
            }
        }
    }
}
