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

    /**
     * Composite weights per variant key (shadow-mode monitoring only).
     * Drives composite sub-score; does NOT affect grade or policy.
     *
     * Recognition variants weight lexical heavily — they are expected to
     * reuse the master's vocabulary directly.
     * Reasoning / trap variants weight semantic chain and cognitive integrity
     * more heavily — they approach the subject from a different cognitive angle.
     */
    private const COMPOSITE_WEIGHTS = [
        'qcm_recognition'     => ['lexical' => 0.70, 'semantic' => 0.20, 'cognitive' => 0.10],
        'tf_recognition_true' => ['lexical' => 0.70, 'semantic' => 0.20, 'cognitive' => 0.10],
        'tf_recognition_false'=> ['lexical' => 0.40, 'semantic' => 0.30, 'cognitive' => 0.30],
        'qcm_reasoning'       => ['lexical' => 0.40, 'semantic' => 0.40, 'cognitive' => 0.20],
        'tf_reasoning_true'   => ['lexical' => 0.40, 'semantic' => 0.40, 'cognitive' => 0.20],
        'tf_reasoning_false'  => ['lexical' => 0.30, 'semantic' => 0.30, 'cognitive' => 0.40],
        'qcm_deceptive_trap'  => ['lexical' => 0.40, 'semantic' => 0.30, 'cognitive' => 0.30],
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
     *   structured_issues: array<int, array{
     *     variant_key: string, policy: string, grade: string, score: float,
     *     drift_type: string, action_required: string, message_humain: string
     *   }>,
     *   recommendation: string
     * }
     */
    public function check(array $frame): array
    {
        $kernelCore = $frame['kernel_core'] ?? [];
        $variants   = $frame['variants'] ?? [];

        $variantScores   = [];
        $issues          = [];
        $structuredIssues = [];
        $okCount         = 0;
        $warnCount       = 0;
        $partialCount    = 0;
        $reviewCount     = 0;

        foreach (self::VARIANT_KEYS as $key) {
            if (!isset($variants[$key]) || !is_array($variants[$key])) {
                $issues[] = "variant {$key} absent (not yet generated)";
                $structuredIssues[] = [
                    'variant_key'    => $key,
                    'policy'         => 'D',
                    'grade'          => 'D',
                    'score'          => 0.0,
                    'drift_type'     => 'kernel_collapse',
                    'action_required'=> 'regen_required',
                    'message_humain' => "Variant {$key} absent du frame_en — contenu non généré.",
                ];
                $reviewCount++;
                continue;
            }

            $v        = $variants[$key];
            $haystack = $this->buildHaystack($v, $key);

            if (empty(trim($haystack))) {
                $issues[] = "{$key}: content not yet generated (all null)";
                $variantScores[$key] = [
                    'score'       => 0.0,
                    'grade'       => 'D',
                    'haystack_len'=> 0,
                ];
                $structuredIssues[] = [
                    'variant_key'    => $key,
                    'policy'         => 'D',
                    'grade'          => 'D',
                    'score'          => 0.0,
                    'drift_type'     => 'kernel_collapse',
                    'action_required'=> 'regen_required',
                    'message_humain' => "Variant {$key} présent mais vide (question_text/answer/explanation tous null).",
                ];
                $reviewCount++;
                continue;
            }

            $score    = KernelTextHelpers::subjectTouchScore($kernelCore, $haystack);
            $grade    = $this->grade($score);

            // ── Shadow mode: sub-scores (monitoring only — do NOT affect grade/policy) ──
            $masterVariant  = $variants['qcm_recognition'] ?? [];
            $semanticChain  = KernelTextHelpers::semanticChainScore($masterVariant, $haystack);
            $cogIntegrity   = KernelTextHelpers::cognitiveIntegrityScore($v, $key);
            $composite      = $this->compositeScore($score, $semanticChain, $cogIntegrity, $key);

            $variantScores[$key] = [
                'score'       => round($score, 3),
                'grade'       => $grade,
                'haystack_len'=> mb_strlen($haystack),
                'subscores'   => [
                    'lexical_subject_touch'    => round($score, 3),
                    'semantic_chain_alignment' => round($semanticChain, 3),
                    'cognitive_integrity'      => round($cogIntegrity, 3),
                    'composite'                => round($composite, 3),
                ],
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

            if ($grade !== 'A') {
                $structuredIssues[] = $this->buildStructuredIssue($key, $grade, round($score, 3));
            }
        }

        $policy         = $this->derivePolicy($reviewCount, $warnCount, $partialCount);
        $recommendation = $this->buildRecommendation($policy, $reviewCount, $warnCount, $partialCount);
        $ok             = ($reviewCount === 0 && $partialCount === 0);

        // Policy D: upgrade all grade-D structured issues to kernel_collapse + reject_kernel.
        // This signals Phase 1 that the full kernel must be regenerated, not just individual variants.
        if ($policy === 'D') {
            foreach ($structuredIssues as &$si) {
                if ($si['grade'] === 'D') {
                    $si['drift_type']      = 'kernel_collapse';
                    $si['action_required'] = 'reject_kernel';
                    $si['policy']          = 'D';
                }
            }
            unset($si);
        }

        return [
            'ok'               => $ok,
            'policy'           => $policy,
            'variant_scores'   => $variantScores,
            'summary'          => [
                'ok_count'      => $okCount,
                'warn_count'    => $warnCount,
                'partial_count' => $partialCount,
                'review_count'  => $reviewCount,
            ],
            'issues'           => $issues,
            'structured_issues'=> $structuredIssues,
            'recommendation'   => $recommendation,
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build a single structured issue entry for a non-A grade variant.
     *
     * drift_type mapping (based on grade + variant semantics):
     *   B tf_recognition_true → proximity_expected (high master overlap is normal per spec — no retry)
     *   B others              → subject_touch_low  (score slightly below OK — monitor)
     *   C/D                   → variant-specific   (weak_reasoning, weak_deceptive_trap,
     *                                               false_not_plausible, subject_touch_low, subject_escape)
     *   may be upgraded to kernel_collapse by the caller when policy=D
     */
    private function buildStructuredIssue(string $variantKey, string $grade, float $score): array
    {
        $driftType      = $this->resolveDriftType($variantKey, $grade);
        $actionRequired = $this->resolveAction($grade, $driftType);
        $scoreStr       = number_format($score, 3);
        $message        = $this->buildCognitiveMessage($variantKey, $grade, $scoreStr);

        return [
            'variant_key'    => $variantKey,
            'policy'         => $grade,
            'grade'          => $grade,
            'score'          => $score,
            'drift_type'     => $driftType,
            'action_required'=> $actionRequired,
            'message_humain' => "[{$variantKey}] {$message}",
        ];
    }

    /**
     * Build a per-cognitive diagnostic message aligned with the spec.
     * Each cognitive type gets a message that names the mechanic that failed,
     * not just a generic threshold message.
     */
    private function buildCognitiveMessage(string $variantKey, string $grade, string $scoreStr): string
    {
        return match ($variantKey) {

            'tf_recognition_true' => match ($grade) {
                'B'     => "score={$scoreStr} — proximity au master attendue (normal per spec: proximity_is_never_penalized). Vérifier que les anchor terms du sujet apparaissent dans l'énoncé.",
                'C'     => "score={$scoreStr} — l'énoncé vrai ne reflète pas le sujet touché ; doit nommer le même fait que qcm_recognition.",
                'D'     => "score={$scoreStr} — aucune ancre sémantique vers le sujet ; statement détaché du kernel.",
                default => "score={$scoreStr}",
            },

            'tf_recognition_false' => match ($grade) {
                'B'     => "score={$scoreStr} légèrement sous OK (" . self::THRESHOLD_OK . ") — faux énoncé peu ancré au sujet ; must_appear_plausible doit être vérifié.",
                'C'     => "score={$scoreStr} — faux énoncé pas crédible sur le sujet ; le joueur doit pouvoir le confondre avec un vrai.",
                'D'     => "score={$scoreStr} — faux énoncé totalement déconnecté du sujet touché.",
                default => "score={$scoreStr}",
            },

            'tf_reasoning_false' => match ($grade) {
                'B'     => "score={$scoreStr} légèrement sous OK (" . self::THRESHOLD_OK . ") — chaîne causale à renforcer ; vérifier trivial_inversion_forbidden.",
                'C'     => "score={$scoreStr} — risque d'inversion triviale (\"not X\" n'est pas un raisonnement) ; le joueur doit raisonner pour identifier le faux.",
                'D'     => "score={$scoreStr} — ni chaîne causale ni ancrage sujet ; énoncé faux non raisonnable.",
                default => "score={$scoreStr}",
            },

            'qcm_reasoning' => match ($grade) {
                'B'     => "score={$scoreStr} légèrement sous OK (" . self::THRESHOLD_OK . ") — chaîne causale/conséquentielle à ancrer plus fortement au sujet.",
                'C'     => "score={$scoreStr} — raisonnement ne requiert pas de connaissance du sujet ; la réponse doit dériver du sujet (cause|conséquence|possibilité|impact).",
                'D'     => "score={$scoreStr} — chaîne de raisonnement brisée ou hors-sujet.",
                default => "score={$scoreStr}",
            },

            'qcm_deceptive_trap' => match ($grade) {
                'B'     => "score={$scoreStr} légèrement sous OK (" . self::THRESHOLD_OK . ") — piège faiblement ancré au sous-domaine+sujet ; vérifier implicit_hypothesis.",
                'C'     => "score={$scoreStr} — hypothèse implicite non déclenchée par le sous-domaine+sujet ; le piège doit invalider l'hypothèse réflexe à la lecture complète.",
                'D'     => "score={$scoreStr} — piège générique non ancré au sujet touché ; reconstruction logique impossible.",
                default => "score={$scoreStr}",
            },

            default => match ($grade) {
                'B'     => "score={$scoreStr} légèrement sous le seuil OK (" . self::THRESHOLD_OK . ") — surveiller au prochain cycle.",
                'C'     => "score={$scoreStr} sous le seuil warn (" . self::THRESHOLD_WARN . ") — retry ou review humaine recommandée.",
                'D'     => "score={$scoreStr} sous le seuil partial (" . self::THRESHOLD_PARTIAL . ") — contenu trop éloigné du sujet touché.",
                default => "grade={$grade} score={$scoreStr}",
            },
        };
    }

    /**
     * Resolve drift_type from variant key + grade.
     *
     *   tf_recognition_true grade B → proximity_expected (expected behavior per spec — not a real drift)
     *   grade B (others)            → subject_touch_low
     *   grade C/D → variant-specific:
     *     qcm_reasoning / tf_reasoning_* → weak_reasoning
     *     qcm_deceptive_trap             → weak_deceptive_trap
     *     tf_recognition_false           → false_not_plausible
     *     others grade D                 → subject_escape
     *     others grade C                 → subject_touch_low
     */
    private function resolveDriftType(string $variantKey, string $grade): string
    {
        // tf_recognition_true at grade B: proximity to master is expected — not a real drift
        if ($variantKey === 'tf_recognition_true' && $grade === 'B') {
            return 'proximity_expected';
        }

        if ($grade === 'B') {
            return 'subject_touch_low';
        }

        return match ($variantKey) {
            'qcm_reasoning',
            'tf_reasoning_true',
            'tf_reasoning_false'  => 'weak_reasoning',
            'qcm_deceptive_trap'  => 'weak_deceptive_trap',
            'tf_recognition_false'=> 'false_not_plausible',
            default               => ($grade === 'D') ? 'subject_escape' : 'subject_touch_low',
        };
    }

    /**
     * Map grade + drift_type → action required.
     *
     * proximity_expected (tf_recognition_true grade B) → verify_subject_anchor
     * not a real drift; no retry needed, but the human reviewer should confirm
     * the subject anchor terms are present in the statement.
     */
    private function resolveAction(string $grade, string $driftType = ''): string
    {
        if ($driftType === 'proximity_expected') {
            return 'verify_subject_anchor';
        }

        return match ($grade) {
            'A' => 'none',
            'B' => 'monitor',
            'C' => 'retry_variant',
            'D' => 'regen_required',
            default => 'monitor',
        };
    }

    /**
     * Build the haystack for subject-touch scoring.
     *
     * Default: question_text + correct_answer_text + explanation.
     *
     * qcm_deceptive_trap: also includes all 4 answer options (A/B/C/D).
     * The trap mechanic lives in the distractors — the intuitive wrong answer
     * must be anchored to the sub_domain+subject, not just the correct answer.
     * Scoring only the correct answer would miss the trap structure entirely.
     *
     * Returns empty string if no content has been generated yet.
     */
    private function buildHaystack(array $v, string $variantKey = ''): string
    {
        $questionText = (string) ($v['question_text'] ?? '');
        $explanation  = (string) ($v['explanation']   ?? '');

        $ckRaw      = strtolower((string) ($v['correct_answer_key'] ?? ''));
        $answerKey  = "answer_{$ckRaw}";
        $answerText = (string) ($v[$answerKey] ?? '');

        if ($variantKey === 'qcm_deceptive_trap') {
            $allAnswers = implode(' ', array_filter([
                (string) ($v['answer_a'] ?? ''),
                (string) ($v['answer_b'] ?? ''),
                (string) ($v['answer_c'] ?? ''),
                (string) ($v['answer_d'] ?? ''),
            ]));
            return trim("{$questionText} {$allAnswers} {$explanation}");
        }

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

    /**
     * Weighted composite score for shadow-mode monitoring.
     * Blends lexical_subject_touch, semantic_chain_alignment, and
     * cognitive_integrity using per-variant-key weights from COMPOSITE_WEIGHTS.
     * Result is stored in subscores.composite and never used for grading.
     */
    private function compositeScore(
        float  $lexical,
        float  $semantic,
        float  $cognitive,
        string $variantKey
    ): float {
        $w = self::COMPOSITE_WEIGHTS[$variantKey]
            ?? ['lexical' => 0.50, 'semantic' => 0.30, 'cognitive' => 0.20];

        return min(1.0,
            $lexical  * $w['lexical']  +
            $semantic * $w['semantic'] +
            $cognitive * $w['cognitive']
        );
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
