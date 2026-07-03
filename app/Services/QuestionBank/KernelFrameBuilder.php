<?php

namespace App\Services\QuestionBank;

use App\Services\QuestionBank\ReadingBandConfig;
use App\Models\QuestionIntent;

/**
 * KernelFrameBuilder
 *
 * Construit le Kernel Blueprint Frame complet — contenant vide du noyau mère.
 * Pure function — lit le QuestionIntent, retourne un array. N'écrit jamais en DB.
 *
 * ══ ARCHITECTURE NOYAU MÈRE (2026-07-03) ══════════════════════════════════════
 *
 * Le frame est organisé en deux couches :
 *
 *   1. BLUEPRINT FRAME (nouvelle architecture)
 *      Tous les slots du noyau mère avec leurs règles, mécanismes, contraintes,
 *      statuts et traces. Toutes les valeurs = null / [] à la construction.
 *      C'est le contenant. Rien n'est sélectionné ici.
 *
 *      Slots Blueprint :
 *        kernel_code            → null (yy-xx-xxx-xxx-xxx-zz — généré par KEY_STRUCTURE+KLD)
 *        depth_slot             → Rempli par KernelRotationPlanner via DepthNeedMatrix
 *        domain_slot            → Rempli par KernelRotationPlanner via DomainCycle
 *        sub_domain_slot        → Rempli par Taxonomy
 *        subjects_inventory     → Jusqu'à 50 sujets (Taxonomy remplit les coquilles)
 *        active_subject         → Curseur sujet actif (Taxonomy)
 *        dominant_ideas         → 5 idées du sujet actif seulement (Taxonomy)
 *        active_dominant_idea   → Curseur idée active (Taxonomy)
 *        cognitive_slots        → 7 cognitifs × {question/réponses/SV/traduction/statut/traces}
 *        rules                  → Règles de remplissage de chaque slot
 *        mechanisms             → Qui remplit quoi et quand
 *        constraints            → Invariants non négociables
 *        statuses               → Statut pipeline de chaque composant
 *        traces                 → Historique chronologique des décisions
 *
 *   2. KERNEL CORE LEGACY (conservé — compatibilité pipeline existant)
 *      kernel_core, translation_constraints, variants
 *      Utilisés par KernelFrameValidator, KernelContentBuilder, QualityGuards.
 *      Ne pas supprimer tant que la migration vers cognitive_slots n'est pas complète.
 *
 * ══ RÈGLE FONDAMENTALE ════════════════════════════════════════════════════════
 *
 * KernelFrameBuilder NE choisit rien :
 *   - Pas de sélection Depth
 *   - Pas de sélection Domaine
 *   - Pas de génération de sujet
 *   - Pas de génération d'idée dominante
 *   - Pas de génération de question
 *
 * Il crée uniquement la structure vide + les règles de remplissage.
 * Sans ce frame, KernelRotationPlanner n'a rien à taguer.
 */
class KernelFrameBuilder
{
    // ─── Langues de traduction (9 — sans 'en' qui est la source) ────────────
    private const TRANSLATION_LANGS = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

    // ─── Caps réponses / saviez-vous ─────────────────────────────────────────
    private const A_MAX     = 60;
    private const A_MAX_ZH  = 30;
    private const A_MAX_AR  = 40;
    private const SV_MAX    = 220;
    private const SV_MAX_ZH = 100;
    private const SV_MAX_AR = 140;
    private const SV_MIN    = 30;

    // ─── 7 variants : [question_type, cognitive_type] ────────────────────────
    private const VARIANTS = [
        'qcm_recognition'      => ['qcm',        'recognition'],
        'qcm_reasoning'        => ['qcm',        'reasoning'],
        'qcm_deceptive_trap'   => ['qcm',        'deceptive_trap'],
        'tf_recognition_true'  => ['true_false', 'recognition'],
        'tf_recognition_false' => ['true_false', 'recognition'],
        'tf_reasoning_true'    => ['true_false', 'reasoning'],
        'tf_reasoning_false'   => ['true_false', 'reasoning'],
    ];

    // ─── Nombre max de sujets dans l'inventaire ──────────────────────────────
    private const SUBJECTS_INVENTORY_MAX = 50;

    // ─── Nombre d'idées dominantes pour le sujet actif ───────────────────────
    private const DOMINANT_IDEAS_MAX = 5;

    // ─── Nombre de cognitifs attendus ────────────────────────────────────────
    private const COGNITIVE_COUNT = 7;

    // ═════════════════════════════════════════════════════════════════════════
    // Point d'entrée public
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Construit le Kernel Blueprint Frame complet.
     *
     * Retourne un array prêt à être encodé en JSON et stocké dans frame_en.
     * Toutes les valeurs à remplir ultérieurement sont null ou [].
     * Si l'intent fournit déjà domain/depth (pipeline legacy), ils sont pré-remplis
     * avec source='legacy_intent' et status='filled'.
     */
    public function buildSkeleton(QuestionIntent $intent): array
    {
        $band = ReadingBandConfig::defaultBandForDepth((int) $intent->difficulty_depth);

        return [
            // ══ BLUEPRINT FRAME — nouvelle architecture ══════════════════════
            'kernel_code'          => null,
            'depth_slot'           => $this->buildDepthSlot($intent),
            'domain_slot'          => $this->buildDomainSlot($intent),
            'sub_domain_slot'      => $this->buildSubDomainSlot($intent),
            'subjects_inventory'   => $this->buildSubjectsInventory(),
            'active_subject'       => null,
            'dominant_ideas'       => [],
            'active_dominant_idea' => null,
            'cognitive_slots'      => $this->buildCognitiveSlots($band),
            'rules'                => $this->buildRules(),
            'mechanisms'           => $this->buildMechanisms(),
            'constraints'          => $this->buildConstraints(),
            'statuses'             => $this->buildStatuses(),
            'traces'               => [],

            // ══ KERNEL CORE LEGACY — compatibilité pipeline existant ═════════
            'kernel_core'             => $this->buildKernelCore($intent, $band),
            'translation_constraints' => $this->buildTranslationConstraints($band),
            'variants'                => $this->buildVariants(),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Blueprint Frame — slots principaux
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * depth_slot — Rempli par KernelRotationPlanner via DepthNeedMatrix.
     * Pré-rempli si l'intent a déjà un difficulty_depth (pipeline legacy).
     */
    private function buildDepthSlot(QuestionIntent $intent): array
    {
        $hasDepth = $intent->difficulty_depth !== null;

        return [
            'value'  => $hasDepth ? (int) $intent->difficulty_depth : null,
            'source' => $hasDepth ? 'legacy_intent' : null,
            'status' => $hasDepth ? 'filled' : 'empty',
            'rules'  => [
                'filler'         => 'KernelRotationPlanner',
                'driven_by'      => 'DepthNeedMatrix',
                'allowed_values' => '1–10',
                'frozen_after'   => 'KEY_STRUCTURE',
                'note'           => 'Priorité déterminée par les cibles bank par Depth (Redis+config).',
            ],
            'traces' => [],
        ];
    }

    /**
     * domain_slot — Rempli par KernelRotationPlanner via DomainCycle.
     * Pré-rempli si l'intent a déjà un domain (pipeline legacy).
     */
    private function buildDomainSlot(QuestionIntent $intent): array
    {
        $hasDomain = $intent->domain !== null && $intent->domain !== '';

        return [
            'value'  => $hasDomain ? $intent->domain : null,
            'source' => $hasDomain ? 'legacy_intent' : null,
            'status' => $hasDomain ? 'filled' : 'empty',
            'rules'  => [
                'filler'      => 'KernelRotationPlanner',
                'driven_by'   => 'DomainCycle',
                'depends_on'  => 'depth_slot',
                'source_data' => 'taxonomy.json',
                'frozen_after'=> 'KEY_STRUCTURE',
                'note'        => 'Cycle déterministe sur les domaines disponibles dans la taxonomie.',
            ],
            'traces' => [],
        ];
    }

    /**
     * sub_domain_slot — Rempli par Taxonomy après domain_slot.
     * Pré-rempli si l'intent a déjà un sub_domain (pipeline legacy).
     */
    private function buildSubDomainSlot(QuestionIntent $intent): array
    {
        $hasSubDomain = $intent->sub_domain !== null && $intent->sub_domain !== '';

        return [
            'value'  => $hasSubDomain ? $intent->sub_domain : null,
            'source' => $hasSubDomain ? 'legacy_intent' : null,
            'status' => $hasSubDomain ? 'filled' : 'empty',
            'rules'  => [
                'filler'      => 'Taxonomy (TaxonomyReader)',
                'depends_on'  => ['depth_slot', 'domain_slot'],
                'source_data' => 'taxonomy.json',
                'frozen_after'=> 'KEY_STRUCTURE',
                'note'        => 'Sélectionné par TaxonomyReader parmi les sous-domaines disponibles pour domain+depth.',
            ],
            'traces' => [],
        ];
    }

    /**
     * subjects_inventory — Coquilles pour jusqu'à 50 sujets du sous-domaine.
     * Rempli par Taxonomy. Les coquilles vides sont pré-allouées ici.
     * Seul active_subject aura ses dominant_ideas générées.
     */
    private function buildSubjectsInventory(): array
    {
        $inventory = [];

        for ($i = 1; $i <= self::SUBJECTS_INVENTORY_MAX; $i++) {
            $inventory[] = [
                'index'   => $i,
                'value'   => null,
                'status'  => 'available',
                'rules'   => [
                    'filler'             => 'Taxonomy',
                    'max_slots'          => self::SUBJECTS_INVENTORY_MAX,
                    'ideas_generated_if' => 'active',
                    'note'               => 'Les idées dominantes ne sont générées que pour le sujet actif.',
                ],
                'traces'  => [],
            ];
        }

        return $inventory;
    }

    /**
     * cognitive_slots — 7 cognitifs × {question/réponses/SV/traduction/statut/traces}.
     * Toutes les valeurs null. Rempli par Phase 1.
     */
    private function buildCognitiveSlots(string $band): array
    {
        $slots = [];

        foreach (self::VARIANTS as $variantKey => [$questionType, $cognitiveType]) {
            $isTf = ($questionType === 'true_false');

            $slots[$variantKey] = [
                'question_type'  => $questionType,
                'cognitive_type' => $cognitiveType,

                // ── question slot ─────────────────────────────────────────
                'question_slot' => [
                    'value'  => null,
                    'status' => 'empty',
                    'rules'  => [
                        'filler'   => 'Phase1 (KernelContentBuilder)',
                        'language' => 'en',
                        'max_chars'=> ReadingBandConfig::resolveForLang($band, 'en')['soft'] ?? 280,
                    ],
                    'traces' => [],
                ],

                // ── answer slots ──────────────────────────────────────────
                'answer_slots' => $this->buildAnswerSlots($isTf),

                // ── correct answer key ────────────────────────────────────
                'correct_answer_key' => null,

                // ── sv slot (saviez-vous) ─────────────────────────────────
                'sv_slot' => [
                    'value'  => null,
                    'status' => 'empty',
                    'rules'  => [
                        'filler'   => 'Phase1 (KernelContentBuilder)',
                        'language' => 'en',
                        'min_chars'=> self::SV_MIN,
                        'max_chars'=> self::SV_MAX,
                    ],
                    'traces' => [],
                ],

                // ── translation slots (9 langues) ─────────────────────────
                'translation_slots' => $this->buildCognitiveTranslationSlots($isTf),

                // ── statut et traces du cognitif ──────────────────────────
                'status' => 'empty',
                'rules'  => [
                    'filler'       => 'Phase1',
                    'depends_on'   => 'QuestionIntent',
                    'note'         => 'Rempli après QuestionIntent verrouillé. Master = qcm_recognition.',
                ],
                'traces' => [],
            ];
        }

        return $slots;
    }

    /**
     * answer_slots — 4 pour QCM, 2 pour TF.
     */
    private function buildAnswerSlots(bool $isTf): array
    {
        if ($isTf) {
            return [
                'answer_a' => ['value' => null, 'status' => 'empty', 'max_chars' => self::A_MAX],
                'answer_b' => ['value' => null, 'status' => 'empty', 'max_chars' => self::A_MAX],
            ];
        }

        return [
            'answer_a' => ['value' => null, 'status' => 'empty', 'max_chars' => self::A_MAX],
            'answer_b' => ['value' => null, 'status' => 'empty', 'max_chars' => self::A_MAX],
            'answer_c' => ['value' => null, 'status' => 'empty', 'max_chars' => self::A_MAX],
            'answer_d' => ['value' => null, 'status' => 'empty', 'max_chars' => self::A_MAX],
        ];
    }

    /**
     * translation_slots par cognitif — 9 langues × {question/réponses/SV/statut}.
     */
    private function buildCognitiveTranslationSlots(bool $isTf): array
    {
        $slots = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $isZh = ($lang === 'zh');
            $isAr = ($lang === 'ar');

            $slots[$lang] = [
                'status'             => 'pending',
                'question_text'      => null,
                'answer_a'           => null,
                'answer_b'           => null,
                'answer_c'           => $isTf ? null : null,
                'answer_d'           => $isTf ? null : null,
                'correct_answer_key' => null,
                'explanation'        => null,
                'saviez_vous'        => null,
                'rules'              => [
                    'filler'         => 'Phase3 (KernelTranslator)',
                    'language'       => $lang,
                    'answer_max'     => $isZh ? self::A_MAX_ZH : ($isAr ? self::A_MAX_AR : self::A_MAX),
                    'sv_max'         => $isZh ? self::SV_MAX_ZH : ($isAr ? self::SV_MAX_AR : self::SV_MAX),
                    'sv_min'         => self::SV_MIN,
                ],
                'traces'             => [],
            ];
        }

        return $slots;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Blueprint Frame — méta-slots (rules, mechanisms, constraints, statuses)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * rules — Règles de remplissage de chaque slot du noyau.
     */
    private function buildRules(): array
    {
        return [
            'kernel_code_format'              => 'yy-xx-xxx-xxx-xxx-zz',
            'kernel_code_builder'             => 'KEY_STRUCTURE (yy-xx-xxx-xxx-xxx) + KLD (zz)',
            'kernel_code_frozen_after'        => 'KLD validation',
            'subjects_inventory_max'          => self::SUBJECTS_INVENTORY_MAX,
            'dominant_ideas_max'              => self::DOMINANT_IDEAS_MAX,
            'dominant_ideas_scope'            => 'active_subject only — autres sujets sans idées tant que non actifs',
            'cognitive_count'                 => self::COGNITIVE_COUNT,
            'ready_bank_unit'                 => 'noyau_mere_encoded — pas une banque de cognitifs isolés',
            'frame_is_container_only'         => 'KernelFrameBuilder ne choisit rien — structure vide uniquement',
            'legacy_fields_preserved'         => 'kernel_core/variants/translation_constraints conservés pour compatibilité pipeline existant',
        ];
    }

    /**
     * mechanisms — Qui remplit quoi et quand.
     */
    private function buildMechanisms(): array
    {
        return [
            'step_1_blueprint_frame'   => 'KernelFrameBuilder — crée le contenant vide (ce fichier)',
            'step_2_depth_domain'      => 'KernelRotationPlanner (DepthNeedMatrix + DomainCycle) — remplit depth_slot + domain_slot + début kernel_code yy-xx',
            'step_3_taxonomy'          => 'TaxonomyReader via KernelRotationPlanner — remplit sub_domain_slot + subjects_inventory + active_subject + dominant_ideas + milieu kernel_code xxx-xxx-xxx',
            'step_4_key_structure'     => 'IntentKeyBuilder.KEY_STRUCTURE — valide égrainage + cohérence + ks_hash + début traces',
            'step_5_kld'               => 'IntentKeyBuilder.KLD — anti-doublon directionnel + kld_hash + suffixe zz + verrouille paire Sujet/Idée',
            'step_6_question_intent'   => 'QuestionIntent — verrouille intent_key + semantic_key + prépare 7 variant_keys',
            'step_7_phase1'            => 'KernelContentBuilder — remplit cognitive_slots (questions + réponses + SV)',
            'step_8_phase2'            => 'KernelFrameValidator + VariantAlignmentChecker — valide contenu EN',
            'step_9_phase3'            => 'KernelTranslator — remplit translation_slots des cognitive_slots (9 langues)',
            'step_10_phase4'           => 'KernelTranslator validation — vérifie qualité traductions',
            'step_11_ready_bank'       => 'KernelExporter — post_review_status = ready_bank (noyau mère encodé complet)',
        ];
    }

    /**
     * constraints — Invariants non négociables du noyau mère.
     */
    private function buildConstraints(): array
    {
        return [
            'frame_builder_no_selection'           => true,
            'frame_builder_no_question_generation' => true,
            'dominant_ideas_only_for_active_subject'=> true,
            'kernel_code_immutable_after_kld'       => true,
            'ready_bank_stores_encoded_noyau'       => true,
            'no_isolated_cognitive_in_ready_bank'   => true,
            'cognitive_consumed_by_gameplay'        => 'Gameplay consomme les cognitifs internes — READY_BANK stocke le noyau entier',
            'backward_compat_variants_preserved'    => 'Ne pas supprimer kernel_core/variants tant que migration non complète',
        ];
    }

    /**
     * statuses — Statut pipeline de chaque composant.
     * Tous null à la construction — mis à jour par chaque composant.
     */
    private function buildStatuses(): array
    {
        return [
            'rotation'               => null,
            'taxonomy'               => null,
            'key_structure'          => null,
            'kld'                    => null,
            'question_intent'        => null,
            'phase1_content'         => null,
            'phase2_validation'      => null,
            'phase3_translation'     => null,
            'phase4_translation_val' => null,
            'ready_bank'             => null,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Kernel Core Legacy — conservé pour compatibilité pipeline existant
    // ═════════════════════════════════════════════════════════════════════════

    private function buildKernelCore(QuestionIntent $intent, string $band): array
    {
        return [
            'domain'               => $intent->domain,
            'sub_domain'           => $intent->sub_domain,
            'difficulty_depth'     => (int) $intent->difficulty_depth,
            'default_reading_band' => $band,
            'concept_family'       => $intent->concept_family,
            'semantic_key'         => $intent->semantic_key,
            'subject'              => $intent->subject,
            'angle_large'          => $intent->angle_large,
            'micro_angle'          => $intent->micro_angle,
            'answer_target'        => $intent->answer_target,
            'potential_trap'       => $intent->potential_trap,
            'pedagogical_intent'   => null,
        ];
    }

    private function buildTranslationConstraints(string $band): array
    {
        $constraints = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $constraints[$lang] = [
                'question_max_length' => $this->qMax($lang, $band),
                'answer_max_length'   => $this->aMax($lang),
                'funFact_max_length'  => $this->svMax($lang),
                'funFact_min_length'  => self::SV_MIN,
            ];
        }

        return $constraints;
    }

    private function qMax(string $lang, string $band): int
    {
        return ReadingBandConfig::resolveForLang($band, $lang)['soft'];
    }

    private function aMax(string $lang): int
    {
        return match ($lang) {
            'zh'    => self::A_MAX_ZH,
            'ar'    => self::A_MAX_AR,
            default => self::A_MAX,
        };
    }

    private function svMax(string $lang): int
    {
        return match ($lang) {
            'zh'    => self::SV_MAX_ZH,
            'ar'    => self::SV_MAX_AR,
            default => self::SV_MAX,
        };
    }

    private function buildVariants(): array
    {
        $variants = [];

        foreach (self::VARIANTS as $variantKey => [$questionType, $cognitiveType]) {
            $variants[$variantKey] = $this->buildVariant($variantKey, $questionType, $cognitiveType);
        }

        return $variants;
    }

    private function buildVariant(string $variantKey, string $questionType, string $cognitiveType): array
    {
        return [
            'question_type'         => $questionType,
            'cognitive_type'        => $cognitiveType,
            'reading_band_override' => ReadingBandConfig::defaultForVariant($variantKey),
            'question_text'         => null,
            'answer_a'              => null,
            'answer_b'              => null,
            'answer_c'              => null,
            'answer_d'              => null,
            'correct_answer_key'    => null,
            'explanation'           => null,
            'saviez_vous'           => null,
            'cognitive_contract'    => $this->buildCognitiveContract($variantKey),
            'gameplay_constraints'  => $this->buildGameplayConstraints($questionType),
            'translation_slots'     => $this->buildVariantTranslationSlots(),
        ];
    }

    private function buildGameplayConstraints(string $questionType): array
    {
        if ($questionType === 'true_false') {
            return [
                'question_type'       => 'true_false',
                'answer_count'        => 2,
                'answer_keys_allowed' => ['A', 'B'],
            ];
        }

        return [
            'question_type'       => 'qcm',
            'answer_count'        => 4,
            'answer_keys_allowed' => ['A', 'B', 'C', 'D'],
        ];
    }

    private function buildVariantTranslationSlots(): array
    {
        $slots = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $slots[$lang] = [
                'status'             => 'pending',
                'correct_answer_key' => null,
                'question_text'      => null,
                'answer_a'           => null,
                'answer_b'           => null,
                'answer_c'           => null,
                'answer_d'           => null,
                'explanation'        => null,
                'saviez_vous'        => null,
            ];
        }

        return $slots;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Cognitive contracts — un contract distinct par cognitif
    // ═════════════════════════════════════════════════════════════════════════

    private function buildCognitiveContract(string $variantKey): array
    {
        return match ($variantKey) {

            'qcm_recognition' => [
                'subject_scope'                 => 'subdomain_and_subject',
                'requires_inference'            => false,
                'has_deceptive_distractor'      => false,
                'question_form'                 => 'direct_retrieval',
                'answer_directly_names_subject' => true,
            ],

            'qcm_reasoning' => [
                'subject_scope'               => 'subdomain_and_subject',
                'requires_inference'          => true,
                'has_deceptive_distractor'    => false,
                'reasoning_type'              => null,
                'reasoning_scope'             => 'subdomain_and_subject',
                'reasoning_anchor'            => 'subject',
                'answer_derives_from_subject' => true,
                'no_direct_recall'            => true,
            ],

            'qcm_deceptive_trap' => [
                'subject_scope'                         => 'subdomain_and_subject',
                'requires_inference'                    => true,
                'has_deceptive_distractor'              => true,
                'trap_anchored_to'                      => 'sub_domain_and_subject',
                'trap_carriers'                         => null,
                'natural_hypothesis_triggered'          => null,
                'hypothesis_overturned_after_full_read' => null,
                'implicit_hypothesis'                   => null,
                'hypothesis_invalidated_by'             => null,
                'reconstruction_required'               => null,
                'intuitive_wrong_answer'                => null,
                'intuitive_answer_presence'             => null,
                'fairness_reason'                       => null,
                'alignment_with_kernel_core'            => null,
            ],

            'tf_recognition_true' => [
                'subject_scope'                => 'subdomain_and_subject',
                'requires_inference'           => false,
                'has_deceptive_distractor'     => false,
                'polarity'                     => 'true',
                'expected_master_proximity'    => true,
                'proximity_is_never_penalized' => true,
            ],

            'tf_recognition_false' => [
                'subject_scope'            => 'subdomain_and_subject',
                'requires_inference'       => false,
                'has_deceptive_distractor' => false,
                'polarity'                 => 'false',
                'must_appear_plausible'    => true,
                'correct_answer_key'       => 'B',
            ],

            'tf_reasoning_true' => [
                'subject_scope'            => 'subdomain_and_subject',
                'requires_inference'       => true,
                'has_deceptive_distractor' => false,
                'polarity'                 => 'true',
                'reasoning_type'           => null,
                'reasoning_scope'          => 'subdomain_and_subject',
                'reasoning_anchor'         => 'subject',
                'player_must_reason'       => true,
            ],

            'tf_reasoning_false' => [
                'subject_scope'               => 'subdomain_and_subject',
                'requires_inference'          => true,
                'has_deceptive_distractor'    => false,
                'polarity'                    => 'false',
                'trivial_inversion_forbidden' => true,
                'player_must_reason'          => true,
                'reasoning_type'              => null,
                'reasoning_scope'             => 'subdomain_and_subject',
                'reasoning_anchor'            => 'subject',
            ],
        };
    }
}
