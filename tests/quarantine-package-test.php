<?php
/**
 * Controlled quarantine audit package test.
 *
 * Usage: php tests/quarantine-package-test.php
 *
 * Uses fixture data only — zero DB writes, zero AI calls, zero real bank interaction.
 * A fake intent_id (99999) is used throughout. Nothing is written to question_intents.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\QuestionBank\KernelQuarantineManager;

// ── Fixtures ─────────────────────────────────────────────────────────────────

$INTENT_ID = 99999;

$KERNEL_CORE = [
    'semantic_key'   => 'speed_of_light_vacuum',
    'subject'        => 'The speed of light in vacuum',
    'answer_target'  => 'exactly 299,792,458 m/s (defined constant)',
    'potential_trap' => 'Confusing speed in vacuum with speed in a medium (glass, water)',
    'micro_angle'    => 'exact SI definition since 1983',
    'domain'         => 'Science',
    'sub_domain'     => 'Physics',
    'concept_family' => 'Electromagnetism',
    'depth'          => 3,
];

// 7 variants — attempt 1 content (2 deliberately weak)
$VARIANTS_ATTEMPT1 = [
    'qcm_recognition' => [
        'question_text'      => 'What is the speed of light in a vacuum?',
        'answer_a'           => '299,792,458 m/s',
        'answer_b'           => '300,000,000 m/s',
        'answer_c'           => '186,282 miles per second',
        'answer_d'           => '3×10⁸ km/s',
        'correct_answer_key' => 'a',
        'explanation'        => 'The speed of light in vacuum is exactly 299,792,458 metres per second — a defined constant since 1983.',
        'saviez_vous'        => 'Since 1983, the metre is defined via the speed of light: 1 m = the distance light travels in 1/299,792,458 s.',
    ],
    'qcm_reasoning' => [
        'question_text'      => 'Why does light travel slower in glass than in vacuum?',
        'answer_a'           => 'Because glass molecules absorb and re-emit photons, causing a delay',
        'answer_b'           => 'Because glass is heavier than air',
        'answer_c'           => 'Because photons lose energy in glass',
        'answer_d'           => 'Because the electric field is weaker in glass',
        'correct_answer_key' => 'a',
        'explanation'        => 'This is actually a weak explanation unrelated to the subject.',
        'saviez_vous'        => 'Some unrelated fact here.',
    ],
    'qcm_deceptive_trap' => [
        'question_text'      => 'Which statement about the speed of light is INCORRECT?',
        'answer_a'           => 'It is 299,792,458 m/s in vacuum',
        'answer_b'           => 'It is the same in all media',
        'answer_c'           => 'It is a defined physical constant',
        'answer_d'           => 'It is used to define the metre',
        'correct_answer_key' => 'b',
        'explanation'        => 'Light slows down in media denser than vacuum — its speed in vacuum remains 299,792,458 m/s.',
        'saviez_vous'        => 'The refractive index n of a material equals c/v where v is the speed of light in that material.',
        'cognitive_contract' => [
            'trap_type'                => 'false_universality',
            'intuitive_wrong_answer'   => 'The speed of light is always the same everywhere',
            'intuitive_answer_presence'=> 'b',
        ],
    ],
    'tf_recognition_true' => [
        'question_text'      => 'The speed of light in a vacuum is exactly 299,792,458 m/s.',
        'answer_a'           => 'True',
        'answer_b'           => 'False',
        'answer_c'           => null,
        'answer_d'           => null,
        'correct_answer_key' => 'a',
        'explanation'        => 'This value is a defined constant in the SI system since 1983.',
        'saviez_vous'        => 'Nothing travels faster than light in vacuum — it is the universal speed limit.',
    ],
    'tf_recognition_false' => [
        'question_text'      => 'The speed of light in a vacuum is approximately 300,000 km/s.',
        'answer_a'           => 'True',
        'answer_b'           => 'False',
        'answer_c'           => null,
        'answer_d'           => null,
        'correct_answer_key' => 'b',
        'explanation'        => 'This statement is only an approximation. The exact speed is 299,792,458 m/s by definition.',
        'saviez_vous'        => 'The approximation c ≈ 3×10⁸ m/s is widely used in physics but not exact.',
    ],
    'tf_reasoning_true' => [
        'question_text'      => 'If the metre is defined using the speed of light, then the speed of light cannot be measured — it is defined by convention.',
        'answer_a'           => 'True',
        'answer_b'           => 'False',
        'answer_c'           => null,
        'answer_d'           => null,
        'correct_answer_key' => 'a',
        'explanation'        => 'Correct — since 1983, c is fixed at 299,792,458 m/s and the metre is derived from it.',
        'saviez_vous'        => 'This circularity was intentional: it eliminates measurement uncertainty for c.',
    ],
    'tf_reasoning_false' => [
        'question_text'      => 'The concept of speed is not really relevant here.',
        'answer_a'           => 'True',
        'answer_b'           => 'False',
        'answer_c'           => null,
        'answer_d'           => null,
        'correct_answer_key' => 'b',
        'explanation'        => 'A weak question that drifts from the subject.',
        'saviez_vous'        => 'Some unrelated fact.',
    ],
];

// Phase 2 after attempt 1 — 2 variants flagged (C policy)
$PHASE2_ORIGINAL = [
    'ok'     => false,
    'policy' => 'C',
    'summary' => [
        'ok_count'      => 5,
        'warn_count'    => 0,
        'partial_count' => 2,
        'review_count'  => 0,
    ],
    'recommendation' => 'partial_review: 2 variant(s) need human review + 0 at human_review level.',
    'variant_scores' => [
        'qcm_recognition' => [
            'score' => 0.820, 'grade' => 'A', 'haystack_len' => 210,
            'subscores' => ['lexical_subject_touch' => 0.820, 'semantic_chain_alignment' => 0.780, 'cognitive_integrity' => 0.900, 'composite' => 0.812],
        ],
        'qcm_reasoning' => [
            'score' => 0.155, 'grade' => 'C', 'haystack_len' => 120,
            'subscores' => ['lexical_subject_touch' => 0.155, 'semantic_chain_alignment' => 0.130, 'cognitive_integrity' => 0.540, 'composite' => 0.222],
        ],
        'qcm_deceptive_trap' => [
            'score' => 0.710, 'grade' => 'A', 'haystack_len' => 185,
            'subscores' => ['lexical_subject_touch' => 0.710, 'semantic_chain_alignment' => 0.650, 'cognitive_integrity' => 0.850, 'composite' => 0.706],
        ],
        'tf_recognition_true' => [
            'score' => 0.880, 'grade' => 'A', 'haystack_len' => 145,
            'subscores' => ['lexical_subject_touch' => 0.880, 'semantic_chain_alignment' => 0.820, 'cognitive_integrity' => 0.950, 'composite' => 0.869],
        ],
        'tf_recognition_false' => [
            'score' => 0.760, 'grade' => 'A', 'haystack_len' => 155,
            'subscores' => ['lexical_subject_touch' => 0.760, 'semantic_chain_alignment' => 0.710, 'cognitive_integrity' => 0.830, 'composite' => 0.748],
        ],
        'tf_reasoning_true' => [
            'score' => 0.680, 'grade' => 'A', 'haystack_len' => 160,
            'subscores' => ['lexical_subject_touch' => 0.680, 'semantic_chain_alignment' => 0.610, 'cognitive_integrity' => 0.790, 'composite' => 0.660],
        ],
        'tf_reasoning_false' => [
            'score' => 0.075, 'grade' => 'C', 'haystack_len' => 80,
            'subscores' => ['lexical_subject_touch' => 0.075, 'semantic_chain_alignment' => 0.060, 'cognitive_integrity' => 0.280, 'composite' => 0.122],
        ],
    ],
    'structured_issues' => [
        [
            'variant_key'    => 'qcm_reasoning',
            'policy'         => 'C',
            'grade'          => 'C',
            'score'          => 0.155,
            'drift_type'     => 'weak_reasoning',
            'action_required'=> 'retry_variant',
            'message_humain' => '[qcm_reasoning] score=0.155 sous le seuil warn (0.22) — retry ou review humaine recommandée.',
        ],
        [
            'variant_key'    => 'tf_reasoning_false',
            'policy'         => 'C',
            'grade'          => 'C',
            'score'          => 0.075,
            'drift_type'     => 'weak_reasoning',
            'action_required'=> 'retry_variant',
            'message_humain' => '[tf_reasoning_false] score=0.075 sous le seuil warn (0.22) — retry ou review humaine recommandée.',
        ],
    ],
    'issues' => [
        'qcm_reasoning: subject_touch_score=0.155 → partial_review recommended',
        'tf_reasoning_false: subject_touch_score=0.075 → partial_review recommended',
    ],
];

// Retry guidance for attempt 2
$RETRY_GUIDANCE = [
    'policy'          => 'C',
    'failed_variants' => ['qcm_reasoning', 'tf_reasoning_false'],
    'avoid'           => ['off-topic reasoning', 'generic questions not anchored to speed of light'],
    'retry_goal'      => ['anchor each question to 299,792,458 m/s or the SI definition of the metre'],
];

// Attempt 2 — variants after targeted retry (still weak, triggering quarantine)
$VARIANTS_ATTEMPT2 = array_merge($VARIANTS_ATTEMPT1, [
    'qcm_reasoning' => [
        'question_text'      => 'What does the exact value of the speed of light (299,792,458 m/s) imply for the measurement of distance?',
        'answer_a'           => 'It means the metre is now defined in terms of c, making c a defined constant',
        'answer_b'           => 'It means all distances in the universe are exactly known',
        'answer_c'           => 'It proves that light has no mass',
        'answer_d'           => 'It shows that the metre was always exactly 1/299,792,458 of a light-second',
        'correct_answer_key' => 'a',
        'explanation'        => 'Since 1983, the metre is defined as the distance light travels in 1/299,792,458 seconds, so c is no longer measured but defined.',
        'saviez_vous'        => 'Before 1983, the metre was defined by a physical platinum-iridium bar kept in Paris.',
    ],
    'tf_reasoning_false' => [
        'question_text'      => 'Because the speed of light is a defined constant, it has changed slightly over the centuries as measurement precision improved.',
        'answer_a'           => 'True',
        'answer_b'           => 'False',
        'answer_c'           => null,
        'answer_d'           => null,
        'correct_answer_key' => 'b',
        'explanation'        => 'The speed of light has not changed — what changed was our ability to measure it. Since 1983 it is fixed by definition.',
        'saviez_vous'        => 'Early measurements of c by Rømer (1676) using Jupiter\'s moons gave ~220,000 km/s — already impressively close.',
    ],
]);

// Phase 2 after attempt 2 — still C policy (→ quarantine)
$PHASE2_RETRY = [
    'ok'     => false,
    'policy' => 'C',
    'summary' => [
        'ok_count'      => 6,
        'warn_count'    => 0,
        'partial_count' => 1,
        'review_count'  => 0,
    ],
    'recommendation' => 'partial_review: 1 variant(s) need human review + 0 at human_review level.',
    'variant_scores' => [
        'qcm_recognition' => [
            'score' => 0.820, 'grade' => 'A', 'haystack_len' => 210,
            'subscores' => ['lexical_subject_touch' => 0.820, 'semantic_chain_alignment' => 0.780, 'cognitive_integrity' => 0.900, 'composite' => 0.812],
        ],
        'qcm_reasoning' => [
            'score' => 0.580, 'grade' => 'A', 'haystack_len' => 220,
            'subscores' => ['lexical_subject_touch' => 0.580, 'semantic_chain_alignment' => 0.530, 'cognitive_integrity' => 0.720, 'composite' => 0.576],
        ],
        'qcm_deceptive_trap' => [
            'score' => 0.710, 'grade' => 'A', 'haystack_len' => 185,
            'subscores' => ['lexical_subject_touch' => 0.710, 'semantic_chain_alignment' => 0.650, 'cognitive_integrity' => 0.850, 'composite' => 0.706],
        ],
        'tf_recognition_true' => [
            'score' => 0.880, 'grade' => 'A', 'haystack_len' => 145,
            'subscores' => ['lexical_subject_touch' => 0.880, 'semantic_chain_alignment' => 0.820, 'cognitive_integrity' => 0.950, 'composite' => 0.869],
        ],
        'tf_recognition_false' => [
            'score' => 0.760, 'grade' => 'A', 'haystack_len' => 155,
            'subscores' => ['lexical_subject_touch' => 0.760, 'semantic_chain_alignment' => 0.710, 'cognitive_integrity' => 0.830, 'composite' => 0.748],
        ],
        'tf_reasoning_true' => [
            'score' => 0.680, 'grade' => 'A', 'haystack_len' => 160,
            'subscores' => ['lexical_subject_touch' => 0.680, 'semantic_chain_alignment' => 0.610, 'cognitive_integrity' => 0.790, 'composite' => 0.660],
        ],
        'tf_reasoning_false' => [
            'score' => 0.195, 'grade' => 'C', 'haystack_len' => 170,
            'subscores' => ['lexical_subject_touch' => 0.195, 'semantic_chain_alignment' => 0.155, 'cognitive_integrity' => 0.480, 'composite' => 0.255],
        ],
    ],
    'structured_issues' => [
        [
            'variant_key'    => 'tf_reasoning_false',
            'policy'         => 'C',
            'grade'          => 'C',
            'score'          => 0.195,
            'drift_type'     => 'weak_reasoning',
            'action_required'=> 'retry_variant',
            'message_humain' => '[tf_reasoning_false] score=0.195 sous le seuil warn (0.22) — retry ou review humaine recommandée.',
        ],
    ],
    'issues' => [
        'tf_reasoning_false: subject_touch_score=0.195 → partial_review recommended',
    ],
];

// Build the final frame (post-retry state)
$FINAL_FRAME = [
    'kernel_core'    => $KERNEL_CORE,
    'variants'       => $VARIANTS_ATTEMPT2,
    'phase2_result'  => $PHASE2_RETRY,
    '_fill_attempt_count' => 2,
];

// Build snapshotOriginal (captured after attempt 1)
$SNAPSHOT_ORIGINAL = [
    'frame'              => ['kernel_core' => $KERNEL_CORE, 'variants' => $VARIANTS_ATTEMPT1, 'phase2_result' => $PHASE2_ORIGINAL],
    'phase2'             => $PHASE2_ORIGINAL,
    'sources'            => ['master' => 'openai/gpt-4o', 'derived' => 'openai/gpt-4o'],
    'latency_master_ms'  => 2341,
    'latency_derived_ms' => 4812,
    'at'                 => now()->toIso8601String(),
];

// Build snapshotRetry (captured after attempt 2)
$SNAPSHOT_RETRY = [
    'variants_before' => $VARIANTS_ATTEMPT1,
    'variants_after'  => $VARIANTS_ATTEMPT2,
    'fixed_keys'      => ['qcm_reasoning', 'tf_reasoning_false'],
    'phase2_before'   => $PHASE2_ORIGINAL,
    'phase2_after'    => $PHASE2_RETRY,
    'latency_ms'      => 3105,
    'source'          => 'openai/gpt-4o',
    'at'              => now()->toIso8601String(),
];

// ── Run test ─────────────────────────────────────────────────────────────────

$manager = new KernelQuarantineManager();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   Quarantine Audit Package — Controlled Test (fixtures)    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "  Intent ID (test) : #{$INTENT_ID}\n";
echo "  Subject          : {$KERNEL_CORE['subject']}\n";
echo "  Retried variants : " . implode(', ', $SNAPSHOT_RETRY['fixed_keys']) . "\n";
echo "  Phase 2 policy   : {$PHASE2_ORIGINAL['policy']} (attempt 1) → {$PHASE2_RETRY['policy']} (attempt 2) → QUARANTINE\n";
echo "\n";

// ── Run 1 ─────────────────────────────────────────────────────────────────────
echo "  Run 1 ...\n";
$dir1 = $manager->writeQuarantinePackage(
    $INTENT_ID,
    $SNAPSHOT_ORIGINAL,
    $SNAPSHOT_RETRY,
    $RETRY_GUIDANCE,
    $FINAL_FRAME
);

if ($dir1 === '') {
    echo "  ❌  writeQuarantinePackage() returned empty string — check logs.\n";
    exit(1);
}

echo "  📁  Package dir : {$dir1}\n\n";

// ── Verify 8 files ────────────────────────────────────────────────────────────
$expected = [
    '01_original_generation.json',
    '02_phase2_analysis.json',
    '03_retry_guidance.json',
    '04_retry_generation.json',
    '05_phase2_retry_analysis.json',
    '06_diff_report.json',
    '07_final_quarantine.json',
    '08_human_review.md',
];

$allOk = true;
foreach ($expected as $f) {
    $path   = $dir1 . DIRECTORY_SEPARATOR . $f;
    $exists = file_exists($path);
    $size   = $exists ? filesize($path) : 0;
    $marker = $exists ? '✅' : '❌';
    printf("  %s  %-38s  %d bytes\n", $marker, $f, $size);
    if (! $exists) {
        $allOk = false;
    }
}
echo "\n";

// ── Verify diff content ───────────────────────────────────────────────────────
$diffPath = $dir1 . '/06_diff_report.json';
$diff     = json_decode(file_get_contents($diffPath), true);

echo "  06_diff_report.json — verification:\n";
foreach ($diff['diffs'] ?? [] as $d) {
    $key    = $d['variant_key'];
    $evo    = $d['evolution'];
    $sign   = $evo['policy_improved'] ? '↑ improved' : '→ unchanged';
    printf("    %-22s  grade %s→%s  score_delta=%+.3f  policy_improved=%s  %s\n",
        $key,
        $evo['grade_before'],
        $evo['grade_after'],
        $evo['score_delta'],
        $evo['policy_improved'] ? 'true ' : 'false',
        $sign
    );
}
echo "\n";

// ── Verify retry_history in file 07 ──────────────────────────────────────────
$finalPath = $dir1 . '/07_final_quarantine.json';
$final     = json_decode(file_get_contents($finalPath), true);
$rh        = $final['retry_history'] ?? [];

echo "  07_final_quarantine.json — retry_history:\n";
echo "    attempt_1: policy=" . ($rh['attempt_1']['policy'] ?? '?') . "  issues=" . ($rh['attempt_1']['issues_count'] ?? '?') . "\n";
echo "    attempt_2: policy=" . ($rh['attempt_2']['policy'] ?? '?') . "  issues=" . ($rh['attempt_2']['issues_count'] ?? '?') . "  fixed_keys=[" . implode(', ', $rh['attempt_2']['fixed_keys'] ?? []) . "]\n";
echo "    still_problematic: " . count($final['still_problematic'] ?? []) . " variant(s)\n";
echo "\n";

// ── Run 2 (verify no-overwrite) ───────────────────────────────────────────────
sleep(1);
echo "  Run 2 (no-overwrite check, 1s apart) ...\n";
$dir2 = $manager->writeQuarantinePackage(
    $INTENT_ID,
    $SNAPSHOT_ORIGINAL,
    $SNAPSHOT_RETRY,
    $RETRY_GUIDANCE,
    $FINAL_FRAME
);

if ($dir2 === $dir1) {
    echo "  ❌  FAIL: Run 2 produced the same directory as Run 1 — possible overwrite!\n";
    $allOk = false;
} else {
    echo "  ✅  No overwrite — Run 2 dir: " . basename($dir2) . "\n";
}
echo "\n";

// ── Print 08_human_review.md ──────────────────────────────────────────────────
echo str_repeat('═', 66) . "\n";
echo "08_human_review.md — full content:\n";
echo str_repeat('═', 66) . "\n\n";
echo file_get_contents($dir1 . '/08_human_review.md');
echo "\n\n";
echo str_repeat('═', 66) . "\n";
echo $allOk
    ? "✅  All checks passed. Package integrity verified.\n"
    : "❌  Some checks FAILED — see output above.\n";
echo str_repeat('═', 66) . "\n\n";
