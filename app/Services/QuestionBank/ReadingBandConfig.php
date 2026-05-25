<?php

namespace App\Services\QuestionBank;

/**
 * Single source of truth for reading_band definitions.
 *
 * Two axes are kept SEPARATE in StrategyBuzzer:
 *   difficulty_depth  — cognitive depth (3-9)
 *   reading_band      — reading load / estimated reading time
 *
 * Bands define SOFT (prompt target) and HARD (reject threshold) char limits
 * per script group:
 *   'en'  — all languages except zh / ar
 *   'zh'  — Chinese (denser per character, ~55 % of EN limit)
 *   'ar'  — Arabic  (RTL + diacritics, ~65 % of EN limit)
 *
 * Validator produces: OK / WARNING / REVIEW_NEEDED — never hard-rejects
 * a question that is only slightly over the soft target.
 */
final class ReadingBandConfig
{
    public const DEFAULT_BAND = 'normal_reader';

    /**
     * Soft max  = prompt target  (ask the LLM to stay below this)
     * Hard max  = reject ceiling (return REVIEW_NEEDED above this)
     */
    public const BANDS = [
        'slow_reader_safe' => [
            'en' => ['soft' => 110, 'hard' => 135],
            'zh' => ['soft' =>  60, 'hard' =>  75],
            'ar' => ['soft' =>  75, 'hard' =>  90],
        ],
        'normal_reader' => [
            'en' => ['soft' => 145, 'hard' => 170],
            'zh' => ['soft' =>  75, 'hard' =>  90],
            'ar' => ['soft' =>  95, 'hard' => 115],
        ],
        'fast_reader_dense' => [
            'en' => ['soft' => 175, 'hard' => 210],
            'zh' => ['soft' =>  90, 'hard' => 110],
            'ar' => ['soft' => 115, 'hard' => 140],
        ],
    ];

    /**
     * Default reading_band per cognitive variant type.
     * Can be overridden via frame_en.variants[key].reading_band_override.
     */
    public const VARIANT_DEFAULT_BANDS = [
        'qcm_recognition'        => 'slow_reader_safe',
        'tf_recognition_true'    => 'slow_reader_safe',
        'tf_recognition_false'   => 'slow_reader_safe',
        'qcm_reasoning'          => 'normal_reader',
        'tf_reasoning_true'      => 'normal_reader',
        'tf_reasoning_false'     => 'normal_reader',
        'qcm_deceptive_trap'     => 'fast_reader_dense',
    ];

    /**
     * Default kernel-level reading_band derived from difficulty_depth.
     * The kernel band is a conservative default; per-variant overrides
     * (VARIANT_DEFAULT_BANDS) handle the real per-variant granularity.
     *
     * depth 3-4  → slow_reader_safe   (shallow concept, short phrasing)
     * depth 5-9  → normal_reader      (deeper concept, some context needed)
     */
    public static function defaultBandForDepth(int $depth): string
    {
        if ($depth <= 4) {
            return 'slow_reader_safe';
        }
        return 'normal_reader';
    }

    /**
     * Resolve BANDS entry for $band, falling back to DEFAULT_BAND.
     */
    public static function resolve(string $band): array
    {
        return self::BANDS[$band] ?? self::BANDS[self::DEFAULT_BAND];
    }

    /**
     * Resolve ['soft' => ..., 'hard' => ...] for a specific band + language.
     * Languages zh and ar have tighter caps; all others use the 'en' entry.
     */
    public static function resolveForLang(string $band, string $lang): array
    {
        $entry = self::resolve($band);
        if ($lang === 'zh') {
            return $entry['zh'];
        }
        if ($lang === 'ar') {
            return $entry['ar'];
        }
        return $entry['en'];
    }

    /**
     * Default reading_band for a given variant key.
     * Falls back to DEFAULT_BAND if variantKey is unknown.
     */
    public static function defaultForVariant(string $variantKey): string
    {
        return self::VARIANT_DEFAULT_BANDS[$variantKey] ?? self::DEFAULT_BAND;
    }

    /**
     * Assess a question_text length against a band + language.
     * Returns an array: ['status' => 'OK'|'WARNING'|'REVIEW_NEEDED', 'detail' => string]
     */
    public static function assess(int $qLen, string $band, string $lang): array
    {
        $limits = self::resolveForLang($band, $lang);
        $soft   = $limits['soft'];
        $hard   = $limits['hard'];

        if ($qLen <= $soft) {
            return ['status' => 'OK', 'detail' => "{$qLen} ≤ soft={$soft}"];
        }
        if ($qLen <= $hard) {
            return [
                'status' => 'WARNING',
                'detail' => "{$qLen} > soft={$soft} but ≤ hard={$hard} (band={$band}, lang={$lang})",
            ];
        }
        return [
            'status' => 'REVIEW_NEEDED',
            'detail' => "{$qLen} > hard={$hard} (band={$band}, lang={$lang})",
        ];
    }

    /**
     * List of valid band names.
     */
    public static function validBands(): array
    {
        return array_keys(self::BANDS);
    }
}
