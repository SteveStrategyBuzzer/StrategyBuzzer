<?php

namespace App\Services\QuestionBank;

use App\Services\QuestionBank\ReadingBandConfig;
use App\Models\QuestionIntent;

/**
 * KernelFrameBuilder
 *
 * Construit le Blueprint Frame — ticket courant passé aux mécanismes du pipeline.
 * Pure function — lit le QuestionIntent (pour les champs legacy), retourne un array.
 * N'écrit jamais en DB.
 *
 * ══ ARCHITECTURE BLUEPRINT (2026-07-07) ═══════════════════════════════════════
 *
 * Le Blueprint est un INSTRUMENT PASSIF et ULTRA-LÉGER.
 * Il ne contient que l'élément courant consommé par les fonctions — jamais le réservoir.
 *
 * Séparation fondamentale :
 *   RÉSERVOIR TAXONOMIE (externe) = 8 SD × 50 sujets × 250 idées, géré par
 *                                   TaxonomyProgressManager + IdeaSlotLoader + TaxonomyReader.
 *   BLUEPRINT (ticket courant)    = 1 sujet + 1 idée à la fois, poussé "goutte à goutte"
 *                                   par Taxonomy dans ce ticket.
 *
 * Sections du Blueprint :
 *   A — ROTATION        : depth, domain_code, rotation_identifier
 *                         (rempli par KernelRotationPlanner, une fois par cycle)
 *   B — CONTENU COURANT : sub_domain, subject, dominant_idea, knowledge_frequency
 *                         (poussé goutte à goutte par Taxonomy)
 *   C — MÉCANISMES      : kld_result, ks_result, ks_hash
 *                         (mis à jour par KLD puis KEY_STRUCTURE)
 *   D — INTENT          : semantic_key, intent_hash, intent_keys
 *                         (produits par QuestionIntent)
 *   E — COGNITIFS       : cognitive_slots (7 variants, remplis par Phase1-4)
 *   F — IDENTITÉ        : kernel_code (construit progressivement par le pipeline)
 *   G — PIPELINE        : statuses, traces
 *   LEGACY              : kernel_core, translation_constraints, variants
 *                         (conservés pour compatibilité pipeline existant)
 *
 * Ce qui N'EST PAS dans le Blueprint (appartient au réservoir externe) :
 *   - subjects_inventory (50 coquilles) → TaxonomyProgressManager
 *   - dominant_ideas (liste 5 idées)    → IdeaSlotLoader
 *   - active_subject (pointeur)         → TaxonomyProgressManager
 *   - active_dominant_idea (pointeur)   → IdeaSlotLoader
 *   - history, remaining_subjects, remaining_ideas → état réservoir
 *   - object_contracts, relation_map, rules, mechanisms, constraints → documentation
 *
 * ══ RÈGLE FONDAMENTALE ════════════════════════════════════════════════════════
 *
 * KernelFrameBuilder NE choisit rien :
 *   - Pas de sélection Depth ni Domaine
 *   - Pas de génération de sujet ni d'idée dominante
 *   - Pas de génération de question
 *
 * Il crée uniquement la structure vide + les champs legacy.
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

    // ─── Référence pipeline ───────────────────────────────────────────────────
    public const DOMINANT_IDEAS_MAX = 5;
    public const COGNITIVE_COUNT    = 7;

    // ═════════════════════════════════════════════════════════════════════════
    // Point d'entrée public
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Construit le Blueprint Frame complet.
     *
     * Sections A–D : tous null/[] à la construction — remplis par les briques pipeline.
     * Section E    : cognitive_slots = 7 coquilles vides — remplies par Phase1-4.
     * Section F    : kernel_code = null — construit progressivement (KRP→Taxonomy→KS→KLD).
     * Section G    : statuses = 10 étapes null, traces = [].
     * LEGACY       : kernel_core, translation_constraints, variants pré-remplis depuis l'intent.
     */
    public function buildSkeleton(QuestionIntent $intent): array
    {
        $band = ReadingBandConfig::defaultBandForDepth((int) $intent->difficulty_depth);

        return [
            'schema_version'  => '2.0.0',
            'content_version' => '1.0.0',

            // ══ A — ROTATION (rempli par KernelRotationPlanner) ══════════════
            'depth'               => null,  // int 1-10
            'domain_code'         => null,  // string — ex: 'science'
            'rotation_identifier' => null,  // string — identifiant unique du cycle

            // ══ B — CONTENU COURANT (poussé goutte à goutte par Taxonomy) ════
            // ⛔ Jamais de subjects_inventory, dominant_ideas, listes, pointeurs réservoir
            'sub_domain'          => null,  // string — ex: 'Capitales'
            'subject'             => null,  // string — ex: 'Nairobi'
            'dominant_idea'       => null,  // string — ex: 'hub_régional'
            'knowledge_frequency' => null,  // int 1-10

            // ══ C — RÉSULTATS MÉCANISMES ══════════════════════════════════════
            'kld_result'          => null,  // 'PENDING' | 'PASS' | 'FAIL'
            'ks_result'           => null,  // 'PENDING' | 'PASS' | 'FAIL'
            'ks_hash'             => null,  // string|null — posé si ks_result = 'PASS'

            // ══ D — IDENTIFIANTS INTENT (produits par QuestionIntent) ═════════
            'semantic_key'        => null,  // string|null
            'intent_hash'         => null,  // string|null
            'intent_keys'         => [],    // string[] — 7 clés cognitives

            // ══ E — COGNITIFS (7 slots — remplis par Phase1-4) ═══════════════
            'cognitive_slots'     => $this->buildCognitiveSlots($band),

            // ══ F — IDENTITÉ NOYAU ════════════════════════════════════════════
            'kernel_code'         => null,  // format yy-xx-xxx-xxx-xxx-zz

            // ══ G — PIPELINE STATUS & TRACES ══════════════════════════════════
            'statuses'            => $this->buildStatuses(),
            'traces'              => [],

            // ══ LEGACY — compatibilité pipeline existant ══════════════════════
            'kernel_core'             => $this->buildKernelCore($intent, $band),
            'translation_constraints' => $this->buildTranslationConstraints($band),
            'variants'                => $this->buildVariants(),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Section E — Cognitive slots (7 variants × question/réponses/SV/traductions)
    // ═════════════════════════════════════════════════════════════════════════

    private function buildCognitiveSlots(string $band): array
    {
        $slots = [];

        foreach (self::VARIANTS as $variantKey => [$questionType, $cognitiveType]) {
            $isTf = ($questionType === 'true_false');

            $slots[$variantKey] = [
                'question_type'  => $questionType,
                'cognitive_type' => $cognitiveType,

                'question_slot' => [
                    'value'     => null,
                    'filled_at' => null,
                    'status'    => 'EMPTY',
                    'locked'    => false,
                    'rules'     => [
                        'creator'          => 'KernelFrameBuilder',
                        'filler'           => 'Phase1 (KernelContentBuilder)',
                        'language'         => 'en',
                        'max_chars'        => ReadingBandConfig::resolveForLang($band, 'en')['soft'] ?? 280,
                        'depends_on'       => ['QuestionIntent'],
                        'read_by'          => ['Phase2', 'Phase3', 'READY_BANK'],
                        'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
                        'locked_after'     => 'Phase2 validation',
                        'transmitted_to'   => 'Phase2 (validation source EN), Phase3 (source traduction)',
                        'forbidden'        => 'Aucune autre brique ne peut modifier question_slot.',
                        'expected_content' => 'question EN — chaîne de max max_chars caractères',
                    ],
                    'traces' => [],
                ],

                'answer_slots'       => $this->buildAnswerSlots($isTf),
                'correct_answer_key' => null,

                'sv_slot' => [
                    'value'     => null,
                    'filled_at' => null,
                    'status'    => 'EMPTY',
                    'locked'    => false,
                    'rules'     => [
                        'creator'          => 'KernelFrameBuilder',
                        'filler'           => 'Phase1 (KernelContentBuilder)',
                        'language'         => 'en',
                        'min_chars'        => self::SV_MIN,
                        'max_chars'        => self::SV_MAX,
                        'depends_on'       => ['QuestionIntent'],
                        'read_by'          => ['Phase2', 'Phase3', 'READY_BANK'],
                        'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
                        'locked_after'     => 'Phase2 validation',
                        'transmitted_to'   => 'Phase2 (validation), Phase3 (source traduction), READY_BANK',
                        'forbidden'        => 'Aucune autre brique ne peut modifier sv_slot.',
                        'expected_content' => '"Saviez-vous" EN — ' . self::SV_MIN . '–' . self::SV_MAX . ' chars',
                    ],
                    'traces' => [],
                ],

                'translation_slots' => $this->buildCognitiveTranslationSlots($isTf),

                'status' => 'EMPTY',
                'rules'  => [
                    'creator'          => 'KernelFrameBuilder',
                    'filler'           => 'Phase1 (KernelContentBuilder)',
                    'depends_on'       => 'QuestionIntent verrouillé',
                    'read_by'          => ['Phase2', 'Phase3', 'Phase4', 'READY_BANK'],
                    'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
                    'locked_after'     => 'Phase2 validation',
                    'transmitted_to'   => 'Phase2, Phase3 (source traduction), READY_BANK',
                    'forbidden'        => 'Aucune autre brique ne peut remplir ce slot cognitif.',
                    'expected_content' => 'question + réponses + sv EN validés — status = validated_ok',
                ],
                'traces' => [],
            ];
        }

        return $slots;
    }

    private function buildAnswerSlots(bool $isTf): array
    {
        $answerRules = [
            'creator'          => 'KernelFrameBuilder',
            'filler'           => 'Phase1 (KernelContentBuilder)',
            'language'         => 'en',
            'max_chars'        => self::A_MAX,
            'depends_on'       => ['QuestionIntent'],
            'read_by'          => ['Phase2', 'Phase3', 'READY_BANK'],
            'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
            'locked_after'     => 'Phase2 validation',
            'transmitted_to'   => 'Phase3 (source traduction)',
            'forbidden'        => 'Aucune autre brique ne peut modifier answer_slots.',
            'expected_content' => 'réponse EN — ≤ ' . self::A_MAX . ' chars',
        ];

        $answerSlot = fn() => [
            'value'     => null,
            'filled_at' => null,
            'status'    => 'EMPTY',
            'locked'    => false,
            'rules'     => $answerRules,
            'traces'    => [],
        ];

        if ($isTf) {
            return ['answer_a' => $answerSlot(), 'answer_b' => $answerSlot()];
        }

        return [
            'answer_a' => $answerSlot(),
            'answer_b' => $answerSlot(),
            'answer_c' => $answerSlot(),
            'answer_d' => $answerSlot(),
        ];
    }

    private function buildCognitiveTranslationSlots(bool $isTf): array
    {
        $slots = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $isZh = ($lang === 'zh');
            $isAr = ($lang === 'ar');

            $answerMax = $isZh ? self::A_MAX_ZH : ($isAr ? self::A_MAX_AR : self::A_MAX);
            $svMax     = $isZh ? self::SV_MAX_ZH : ($isAr ? self::SV_MAX_AR : self::SV_MAX);

            $slots[$lang] = [
                'status'             => 'EMPTY',
                'filled_at'          => null,
                'locked'             => false,
                'question_text'      => null,
                'answer_a'           => null,
                'answer_b'           => null,
                'answer_c'           => $isTf ? 'n/a' : null,
                'answer_d'           => $isTf ? 'n/a' : null,
                'correct_answer_key' => null,
                'explanation'        => null,
                'saviez_vous'        => null,
                'rules'              => [
                    'creator'          => 'KernelFrameBuilder',
                    'filler'           => 'Phase3 (KernelTranslator)',
                    'validator'        => 'Phase4',
                    'source'           => 'question_slot EN (Phase1 output)',
                    'language'         => $lang,
                    'depends_on'       => ['Phase2_validation'],
                    'answer_max'       => $answerMax,
                    'sv_max'           => $svMax,
                    'sv_min'           => self::SV_MIN,
                    'read_by'          => ['Phase4', 'READY_BANK', 'Gameplay'],
                    'write_access'     => 'Phase3 (KernelTranslator) uniquement',
                    'locked_after'     => 'Phase4 validation',
                    'transmitted_to'   => 'READY_BANK, Gameplay',
                    'forbidden'        => 'Aucune autre brique ne peut modifier translation_slots.',
                    'expected_content' => "traduction complète {$lang} — question + réponses + sv",
                ],
                'traces' => [],
            ];
        }

        return $slots;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Section G — Pipeline statuses
    // ═════════════════════════════════════════════════════════════════════════

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
    // LEGACY — compatibilité pipeline existant
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
            'translation_slots'     => $this->buildTranslationSlots(),
            'status'                => 'EMPTY',
        ];
    }

    private function buildCognitiveContract(string $variantKey): array
    {
        $contracts = [
            'qcm_recognition'      => ['type' => 'recognition', 'trap_allowed' => false, 'min_wrong_answers' => 3],
            'qcm_reasoning'        => ['type' => 'reasoning',   'trap_allowed' => false, 'min_wrong_answers' => 3],
            'qcm_deceptive_trap'   => ['type' => 'deceptive',   'trap_allowed' => true,  'min_wrong_answers' => 3],
            'tf_recognition_true'  => ['type' => 'recognition', 'expected_truth' => true,  'binary' => true],
            'tf_recognition_false' => ['type' => 'recognition', 'expected_truth' => false, 'binary' => true],
            'tf_reasoning_true'    => ['type' => 'reasoning',   'expected_truth' => true,  'binary' => true],
            'tf_reasoning_false'   => ['type' => 'reasoning',   'expected_truth' => false, 'binary' => true],
        ];

        return $contracts[$variantKey] ?? [];
    }

    private function buildGameplayConstraints(string $questionType): array
    {
        return [
            'display_mode'   => $questionType === 'true_false' ? 'binary' : 'quad',
            'time_limit_sec' => 30,
            'buzz_eligible'  => true,
        ];
    }

    private function buildTranslationSlots(): array
    {
        $slots = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $slots[$lang] = [
                'question_text'      => null,
                'answer_a'           => null,
                'answer_b'           => null,
                'answer_c'           => null,
                'answer_d'           => null,
                'correct_answer_key' => null,
                'explanation'        => null,
                'saviez_vous'        => null,
                'status'             => 'EMPTY',
            ];
        }

        return $slots;
    }
}
