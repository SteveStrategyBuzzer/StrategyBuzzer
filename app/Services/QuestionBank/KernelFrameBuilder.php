<?php

namespace App\Services\QuestionBank;

use App\Services\QuestionBank\ReadingBandConfig;

use App\Models\QuestionIntent;

/**
 * KernelFrameBuilder
 *
 * Builds the complete Phase 1 skeleton for frame_en.
 * Pure function — reads the QuestionIntent, returns an array.
 * Never writes to the database.
 *
 * Structure produced:
 *   frame_en.kernel_core           (11 fields from the intent row)
 *   frame_en.translation_constraints  (9 langs × 4 length constants)
 *   frame_en.variants              (7 fixed keys)
 *     └─ each variant:
 *          question_type / cognitive_type
 *          question_text / answer_a/b/c/d / correct_answer_key / explanation / saviez_vous  (all null)
 *          cognitive_contract  (full for deceptive_trap, minimal for others)
 *          gameplay_constraints
 *          translation_slots   (9 langs × 9 fields each → 63 total slots)
 */
class KernelFrameBuilder
{
    // ─── Translation languages (no 'en' — EN is the source) ─────────────────
    private const TRANSLATION_LANGS = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

    // ─── Answer / saviez-vous caps (unchanged) ──────────────────────────────
    private const A_MAX     = 60;
    private const A_MAX_ZH  = 30;
    private const A_MAX_AR  = 40;
    private const SV_MAX    = 220;
    private const SV_MAX_ZH = 100;
    private const SV_MAX_AR = 140;
    private const SV_MIN    = 30;
    // question_max_length is now band-aware — see qMax() / ReadingBandConfig

    // ─── 7 variant definitions: [question_type, cognitive_type] ─────────────
    // TF variants encode polarity in the key name (_true → key=A, _false → key=B).
    private const VARIANTS = [
        'qcm_recognition'      => ['qcm',        'recognition'],
        'qcm_reasoning'        => ['qcm',        'reasoning'],
        'qcm_deceptive_trap'   => ['qcm',        'deceptive_trap'],
        'tf_recognition_true'  => ['true_false', 'recognition'],
        'tf_recognition_false' => ['true_false', 'recognition'],
        'tf_reasoning_true'    => ['true_false', 'reasoning'],
        'tf_reasoning_false'   => ['true_false', 'reasoning'],
    ];

    /**
     * Build the complete Phase 1 frame_en skeleton.
     * Returns an array ready to be JSON-encoded and stored in frame_en.
     * No DB write happens here.
     */
    public function buildSkeleton(QuestionIntent $intent): array
    {
        $band = ReadingBandConfig::defaultBandForDepth((int) $intent->difficulty_depth);

        return [
            'kernel_core'             => $this->buildKernelCore($intent, $band),
            'translation_constraints' => $this->buildTranslationConstraints($band),
            'variants'                => $this->buildVariants(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // kernel_core
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // translation_constraints — per language length caps
    // ─────────────────────────────────────────────────────────────────────────

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

    /**
     * question_max_length = soft_max_chars for the kernel's default_reading_band.
     * Per-script adjustments: zh ≈ 55 %, ar ≈ 65 % of EN soft limit.
     */
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

    // ─────────────────────────────────────────────────────────────────────────
    // variants — 7 fixed keys
    // ─────────────────────────────────────────────────────────────────────────

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
            'question_type'        => $questionType,
            'cognitive_type'       => $cognitiveType,
            'reading_band_override'=> ReadingBandConfig::defaultForVariant($variantKey),
            'question_text'        => null,
            'answer_a'            => null,
            'answer_b'            => null,
            'answer_c'            => null,
            'answer_d'            => null,
            'correct_answer_key'  => null,
            'explanation'         => null,
            'saviez_vous'         => null,
            'cognitive_contract'  => $this->buildCognitiveContract($variantKey),
            'gameplay_constraints'=> $this->buildGameplayConstraints($questionType),
            'translation_slots'   => $this->buildTranslationSlots(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // cognitive_contract
    // ─────────────────────────────────────────────────────────────────────────

    private function buildCognitiveContract(string $variantKey): array
    {
        if ($variantKey === 'qcm_deceptive_trap') {
            return [
                'requires_inference'         => true,
                'has_deceptive_distractor'   => true,
                'trap_description'           => null,
                'trap_type'                  => null,
                'intuitive_wrong_answer'     => null,
                'intuitive_answer_presence'  => null,
                'recadrage_expected'         => null,
                'fairness_reason'            => null,
                'alignment_with_kernel_core' => null,
            ];
        }

        return [
            'requires_inference'       => str_contains($variantKey, 'reasoning'),
            'has_deceptive_distractor' => false,
            'trap_description'         => null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // gameplay_constraints
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // translation_slots — 9 langs per variant (63 total across 7 variants)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildTranslationSlots(): array
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
}
