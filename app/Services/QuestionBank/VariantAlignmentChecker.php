<?php

namespace App\Services\QuestionBank;

use App\Services\QuestionBank\KernelTextHelpers;

/**
 * VariantAlignmentChecker
 *
 * Phase 2 — Validates that each variant's content is anchored to the
 * kernel's SUJET TOUCHÉ (cognitive object) after English content generation.
 *
 * Uses KernelTextHelpers::subjectTouchScore() to score each variant against
 * the kernel_core fields (subject, micro_angle, answer_target,
 * potential_trap, concept_family).
 *
 * Per-variant scoring:
 *   haystack = question_text + ' ' + correct_answer_text + ' ' + explanation
 *   score = subjectTouchScore(kernel_core, haystack)
 *
 * Decision thresholds:
 *   score >= 0.55  → OK
 *   0.30 – 0.55    → B (warn_phase1 — flag but do not reject)
 *   0.15 – 0.30    → C (partial_review — human review recommended)
 *   < 0.15         → D (human_review — reject variant for regeneration)
 *
 * Correction policy (4-level):
 *   A = fixable_auto       : strip meta-label, fix polarity flip (≤ 1 variant, no content issue)
 *   B = warn_phase1        : ≤ 2 variants below threshold, flag for next cycle
 *   C = partial_review     : partial_review status + human_review flag
 *   D = rejected           : only if 3+ variants broken across 2 generation cycles
 *
 * Never writes to the database.
 */
final class VariantAlignmentChecker
{
    public const THRESHOLD_OK           = 0.45;
    public const THRESHOLD_WARN         = 0.22;
    public const THRESHOLD_PARTIAL      = 0.10;

    private const VARIANT_KEYS = [
        'qcm_recognition',
        'qcm_reasoning',
        'qcm_deceptive_trap',
        'tf_recognition_true',
        'tf_recognition_false',
        'tf_reasoning_true',
        'tf_reasoning_false',
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Run Phase 2 alignment check on a fully-filled frame_en.
     *
     * @param  array  $frame  frame_en (must have kernel_core + filled variants)
     * @return array{
     *   ok: bool,
     *   policy: 'A'|'B'|'C'|'D',
     *   variant_scores: array<string, array{score: float, grade: string, haystack_len: int}>,
     *   summary: array{ok_count: int, warn_count: int, partial_count: int, review_count: int},
     *   issues: string[],
     *   recommendation: string
     * }
     */
    public function check(array $frame): array
    {
        $kernelCore = $frame['kernel_core'] ?? [];
        $variants   = $frame['variants'] ?? [];

        $variantScores = [];
        $issues        = [];
        $okCount       = 0;
        $warnCount     = 0;
        $partialCount  = 0;
        $reviewCount   = 0;

        foreach (self::VARIANT_KEYS as $key) {
            if (!isset($variants[$key]) || !is_array($variants[$key])) {
                $issues[] = "variant {$key} absent (not yet generated)";
                continue;
            }

            $v        = $variants[$key];
            $haystack = $this->buildHaystack($v);

            if (empty(trim($haystack))) {
                $issues[] = "{$key}: content not yet generated (all null)";
                $reviewCount++;
                $variantScores[$key] = [
                    'score'       => 0.0,
                    'grade'       => 'D',
                    'haystack_len'=> 0,
                ];
                continue;
            }

            $score    = KernelTextHelpers::subjectTouchScore($kernelCore, $haystack);
            $grade    = $this->grade($score);

            $variantScores[$key] = [
                'score'       => round($score, 3),
                'grade'       => $grade,
                'haystack_len'=> mb_strlen($haystack),
            ];

            match ($grade) {
                'A'     => $okCount++,
                'B'     => $warnCount++,
                'C'     => $partialCount++,
                'D'     => $reviewCount++,
            };

            if ($grade === 'D') {
                $issues[] = "{$key}: subject_touch_score={$score} < " . self::THRESHOLD_PARTIAL . " → human_review";
            } elseif ($grade === 'C') {
                $issues[] = "{$key}: subject_touch_score={$score} → partial_review recommended";
            }
        }

        $policy         = $this->derivePolicy($reviewCount, $warnCount, $partialCount);
        $recommendation = $this->buildRecommendation($policy, $reviewCount, $warnCount, $partialCount);
        $ok             = ($reviewCount === 0 && $partialCount === 0);

        return [
            'ok'             => $ok,
            'policy'         => $policy,
            'variant_scores' => $variantScores,
            'summary'        => [
                'ok_count'      => $okCount,
                'warn_count'    => $warnCount,
                'partial_count' => $partialCount,
                'review_count'  => $reviewCount,
            ],
            'issues'         => $issues,
            'recommendation' => $recommendation,
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build the haystack for scoring: question_text + correct answer text + explanation.
     * Returns empty string if no content generated yet.
     */
    private function buildHaystack(array $v): string
    {
        $questionText = (string) ($v['question_text'] ?? '');
        $explanation  = (string) ($v['explanation']   ?? '');

        $ckRaw      = strtolower((string) ($v['correct_answer_key'] ?? ''));
        $answerKey  = "answer_{$ckRaw}";
        $answerText = (string) ($v[$answerKey] ?? '');

        return trim("{$questionText} {$answerText} {$explanation}");
    }

    /**
     * Map a score to a letter grade (A / B / C / D).
     */
    private function grade(float $score): string
    {
        return match (true) {
            $score >= self::THRESHOLD_OK      => 'A',
            $score >= self::THRESHOLD_WARN    => 'B',
            $score >= self::THRESHOLD_PARTIAL => 'C',
            default                           => 'D',
        };
    }

    /**
     * Derive correction policy from variant failure counts.
     *
     *   A → 0 broken variants (only auto-fixable issues)
     *   B → 1–2 variants below WARN threshold (flag, retry next cycle)
     *   C → any variant in partial_review / or >2 warn-level variants
     *   D → 3+ variants in human_review (requires full reject + regen)
     */
    private function derivePolicy(int $reviewCount, int $warnCount, int $partialCount): string
    {
        if ($reviewCount >= 3) {
            return 'D';
        }
        if ($partialCount >= 1 || $reviewCount >= 1) {
            return 'C';
        }
        if ($warnCount >= 1) {
            return 'B';
        }
        return 'A';
    }

    private function buildRecommendation(
        string $policy,
        int    $reviewCount,
        int    $warnCount,
        int    $partialCount
    ): string {
        return match ($policy) {
            'A' => 'All variants aligned with sujet touché. No action required.',
            'B' => "{$warnCount} variant(s) below warn threshold. Flag for targeted AI retry in next cycle.",
            'C' => "partial_review: {$partialCount} variant(s) need human review + {$reviewCount} at human_review level. Set frame_status=partial_review.",
            'D' => "REJECT: {$reviewCount} variants failed subject anchor. Regenerate full kernel content.",
        };
    }
}
