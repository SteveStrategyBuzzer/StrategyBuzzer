<?php
/**
 * Export Human Review — Real kernel intents from question bank.
 *
 * Generates one markdown file per domain in storage/app/human_review_exports/
 * Each file shows all 7 cognitive variants with full content, scores,
 * gameplay verdict, master proximity, and WHY FAILED where applicable.
 *
 * Usage:  php scripts/export-human-review.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// ─────────────────────────────────────────────────────────────────────────────
// Config
// ─────────────────────────────────────────────────────────────────────────────

$OUTPUT_DIR = storage_path('app/human_review_exports');
if (! is_dir($OUTPUT_DIR)) {
    mkdir($OUTPUT_DIR, 0755, true);
}

// Target: one real intent per domain.  Preferred depth 3-5, fallback any depth.
// We pick the best available intent per domain (has phase2_result preferred).
$TARGET_DOMAINS = [
    'Histoire', 'Géographie', 'Science', 'Art', 'Sport', 'Cinéma', 'Cuisine', 'Faune',
];

$VARIANT_ORDER = [
    'qcm_recognition',
    'qcm_reasoning',
    'qcm_deceptive_trap',
    'tf_recognition_true',
    'tf_recognition_false',
    'tf_reasoning_true',
    'tf_reasoning_false',
];

$VARIANT_LABEL = [
    'qcm_recognition'      => '1. QCM Recognition (Master)',
    'qcm_reasoning'        => '2. QCM Reasoning',
    'qcm_deceptive_trap'   => '3. QCM Deceptive Trap',
    'tf_recognition_true'  => '4. V/F Recognition — TRUE',
    'tf_recognition_false' => '5. V/F Recognition — FALSE',
    'tf_reasoning_true'    => '6. V/F Reasoning — TRUE',
    'tf_reasoning_false'   => '7. V/F Reasoning — FALSE',
];

// Expected proximity to master — tf_recognition_true is NORMAL, never flagged.
$PROXIMITY_EXPECTED_HIGH = ['tf_recognition_true'];
// Thresholds for suspicious proximity (composite score, raw lexical)
$PROXIMITY_SUSPICIOUS_THRESHOLD = 0.72;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function slugDomain(string $domain): string
{
    $map = [
        'Histoire'   => 'histoire',
        'Géographie' => 'geographie',
        'Science'    => 'science',
        'Art'        => 'art',
        'Sport'      => 'sport',
        'Cinéma'     => 'cinema',
        'Cuisine'    => 'cuisine',
        'Faune'      => 'faune',
    ];
    return $map[$domain] ?? strtolower(preg_replace('/[^a-z0-9]/i', '_', $domain));
}

function fmt(float $v): string
{
    return number_format($v, 3);
}

/**
 * Derive a gameplay verdict label from available data.
 */
function gameplayVerdict(string $variantKey, string $grade, ?string $driftType): string
{
    if ($grade === 'D') {
        return 'critical — must be fully rewritten';
    }

    switch ($variantKey) {
        case 'qcm_recognition':
            return $grade === 'A' ? 'excellent master' : ($grade === 'B' ? 'acceptable master' : 'weak master — subject anchor missing');

        case 'qcm_reasoning':
            if ($grade === 'A') return 'strong reasoning';
            if ($grade === 'B') return 'acceptable reasoning — minor drift';
            if ($driftType === 'weak_reasoning') return 'weak reasoning — causal chain insufficient';
            if ($driftType === 'subject_escape') return 'subject escape — off topic';
            return 'weak reasoning';

        case 'qcm_deceptive_trap':
            if ($grade === 'A') return 'excellent deceptive trap';
            if ($grade === 'B') return 'acceptable trap — slightly generic';
            if ($driftType === 'weak_deceptive_trap') return 'weak trap — distractor not anchored to subject';
            return 'weak trap';

        case 'tf_recognition_true':
            if ($grade === 'A') return 'excellent — expected proximity to master (normal)';
            if ($grade === 'B') return 'acceptable true statement';
            return 'weak true statement — too vague';

        case 'tf_recognition_false':
            if ($grade === 'A') return 'excellent false plausible';
            if ($grade === 'B') return 'acceptable false — slightly easy to detect';
            if ($driftType === 'false_not_plausible') return 'obvious false — statement not convincing enough';
            return 'weak false plausible';

        case 'tf_reasoning_true':
            if ($grade === 'A') return 'strong true reasoning';
            if ($grade === 'B') return 'acceptable true reasoning — slightly direct';
            if ($driftType === 'weak_reasoning') return 'weak reasoning — too direct, no inference required';
            return 'weak reasoning';

        case 'tf_reasoning_false':
            if ($grade === 'A') return 'strong false reasoning';
            if ($grade === 'B') return 'acceptable false reasoning';
            if ($driftType === 'weak_reasoning') return 'weak reasoning — trivial inversion, no subject knowledge required';
            if ($driftType === 'false_not_plausible') return 'obvious false — false statement not convincing';
            return 'weak false reasoning';
    }
    return $grade === 'A' ? 'ok' : 'needs review';
}

/**
 * Derive WHY FAILED reasons from available data (grade, score, drift_type).
 * NOTE: subscores (semantic_chain, cognitive_integrity) are only available
 * for quarantined intents — not stored for standard content_ready intents.
 */
function deriveWhyFailed(string $variantKey, string $grade, float $score, ?string $driftType, ?string $actionRequired): array
{
    if (in_array($grade, ['A', 'B'], true)) {
        return [];
    }

    $reasons = [];

    if ($score < 0.10) {
        $reasons[] = 'Subject anchor completely absent — topic not referenced at all';
    } elseif ($score < 0.22) {
        $reasons[] = 'Subject touch too low (' . fmt($score) . ') — topic barely present in question/answer/explanation';
    }

    switch ($driftType) {
        case 'weak_reasoning':
            if (str_contains($variantKey, 'tf_reasoning_false')) {
                $reasons[] = 'False statement unconvincing — trivial inversion, doesn\'t require subject knowledge to identify';
            } elseif (str_contains($variantKey, 'tf_reasoning_true')) {
                $reasons[] = 'True reasoning too direct — paraphrases definition without requiring inference';
            } else {
                $reasons[] = 'Reasoning chain doesn\'t require subject knowledge — answerable without knowing the topic';
            }
            break;
        case 'weak_deceptive_trap':
            $reasons[] = 'Trap distractor generic, not anchored to subject — must exploit the specific potential_trap for this kernel';
            break;
        case 'false_not_plausible':
            $reasons[] = 'False statement too obviously wrong — doesn\'t look true about this subject';
            break;
        case 'subject_escape':
            $reasons[] = 'Subject escape — variant drifted to a completely different topic';
            break;
        case 'subject_touch_low':
            $reasons[] = 'Topic keyword present but core question is off-subject';
            break;
        case 'kernel_collapse':
            $reasons[] = 'Kernel collapse — content is absent or completely off-topic';
            break;
    }

    if ($actionRequired && $actionRequired !== 'none') {
        $reasons[] = 'Action required: ' . $actionRequired;
    }

    return $reasons;
}

/**
 * Master proximity assessment per variant.
 *
 * RULE: tf_recognition_true proximity is ALWAYS expected — never flagged.
 * The T/F true format must describe the same fact as the master.
 */
function masterProximityNote(string $variantKey, float $score, ?float $masterScore): array
{
    // tf_recognition_true: proximity is expected and normal — never flag
    if ($variantKey === 'tf_recognition_true') {
        return [
            'proximity'        => 'expected',
            'note'             => 'NORMAL — tf_recognition_true describes the same correct fact as master, high overlap is by design',
            'flagged'          => false,
        ];
    }

    if ($variantKey === 'qcm_recognition') {
        return [
            'proximity'        => 'reference (master)',
            'note'             => 'This IS the master — all other variants measured against it',
            'flagged'          => false,
        ];
    }

    // For other variants: note score relative to expected range
    $suspicious = ($score >= 0.72);
    $low        = ($score < 0.15);

    $proximity = $suspicious ? 'suspicious — too close to master' : ($low ? 'distant — may have drifted' : 'acceptable');
    $note      = $suspicious
        ? 'Score ' . fmt($score) . ' ≥ 0.720 — wording too close to master; player who saw master may get free answer'
        : ($low
            ? 'Score ' . fmt($score) . ' < 0.150 — very low subject touch; may have drifted from the kernel subject'
            : 'Score ' . fmt($score) . ' — within acceptable range for this variant type');

    return [
        'proximity' => $proximity,
        'note'      => $note,
        'flagged'   => $suspicious || $low,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Build one markdown file for an intent
// ─────────────────────────────────────────────────────────────────────────────

function buildMarkdown(array $intent, array $variantOrder, array $variantLabel): string
{
    $frame   = $intent['frame'];
    $kc      = $frame['kernel_core'] ?? [];
    $variants = $frame['variants'] ?? [];
    $p2      = $frame['phase2_result'] ?? null;

    $hasScores = ($p2 !== null);
    $variantScores    = $p2['variant_scores']    ?? [];
    $structuredIssues = $p2['structured_issues'] ?? [];

    // Index issues by variant_key
    $issuesByKey = [];
    foreach ($structuredIssues as $si) {
        $k = $si['variant_key'] ?? null;
        if ($k) $issuesByKey[$k] = $si;
    }

    $lines = [];

    // ── Document header ───────────────────────────────────────────────────────
    $lines[] = "# Human Review Export — {$intent['domain']} depth {$intent['depth']}";
    $lines[] = "";
    $lines[] = "**Generated:** " . date('Y-m-d H:i:s') . " UTC";
    $lines[] = "";
    $lines[] = "| Field | Value |";
    $lines[] = "|---|---|";
    $lines[] = "| Intent ID | #{$intent['id']} |";
    $lines[] = "| Subject | " . ($kc['subject'] ?? $intent['subject']) . " |";
    $lines[] = "| Domain | {$intent['domain']} / " . ($kc['sub_domain'] ?? $intent['sub_domain']) . " |";
    $lines[] = "| Depth | {$intent['depth']} |";
    $lines[] = "| Frame status | {$intent['frame_status']} |";
    $lines[] = "| Dialysis status | {$intent['dialysis_status']} |";
    $lines[] = "| Answer target | " . ($kc['answer_target'] ?? '—') . " |";
    $lines[] = "| Potential trap | " . ($kc['potential_trap'] ?? '—') . " |";
    $lines[] = "| Semantic key | " . ($kc['semantic_key'] ?? '—') . " |";
    $lines[] = "";

    // ── Phase 2 summary ───────────────────────────────────────────────────────
    if ($hasScores) {
        $policy  = $p2['policy'] ?? '?';
        $summary = $p2['summary'] ?? [];
        $lines[] = "## Phase 2 Summary";
        $lines[] = "";
        $lines[] = "| Metric | Value |";
        $lines[] = "|---|---|";
        $lines[] = "| Policy | **{$policy}** |";
        $lines[] = "| OK (A) | " . ($summary['ok_count'] ?? '?') . " |";
        $lines[] = "| Warn (B) | " . ($summary['warn_count'] ?? '?') . " |";
        $lines[] = "| Partial (C) | " . ($summary['partial_count'] ?? '?') . " |";
        $lines[] = "| Review (D) | " . ($summary['review_count'] ?? '?') . " |";
        $lines[] = "";
        if (! empty($p2['recommendation'])) {
            $lines[] = "> **Recommendation:** " . $p2['recommendation'];
            $lines[] = "";
        }
    } else {
        $lines[] = "## Phase 2 Summary";
        $lines[] = "";
        $lines[] = "> ℹ️ No Phase 2 analysis stored for this intent (frame_status: `{$intent['frame_status']}`).";
        $lines[] = "> Content is available but scores (grade, composite, drift) were not persisted.";
        $lines[] = "";
    }
    $lines[] = "---";
    $lines[] = "";

    // ── Per-variant sections ──────────────────────────────────────────────────
    $masterScore = null;
    if ($hasScores && isset($variantScores['qcm_recognition']['score'])) {
        $masterScore = (float) $variantScores['qcm_recognition']['score'];
    }

    foreach ($variantOrder as $key) {
        $label = $variantLabel[$key] ?? $key;
        $v     = $variants[$key] ?? null;

        // Scores for this variant
        $vsRow     = $variantScores[$key] ?? null;
        $grade     = $vsRow !== null ? ($vsRow['grade'] ?? '?') : null;
        $composite = $vsRow !== null ? (float) ($vsRow['score'] ?? 0.0) : null;

        // Issue for this variant
        $issue       = $issuesByKey[$key] ?? null;
        $driftType   = $issue['drift_type']    ?? null;
        $actionReq   = $issue['action_required'] ?? null;
        $msgHumain   = $issue['message_humain']  ?? null;

        // Cognitive type from variant data itself
        $cogType = $v['cognitive_type'] ?? $v['question_type'] ?? ($key);

        // Status badge
        if ($grade === null) {
            $badge = '— (no score)';
        } elseif ($grade === 'A') {
            $badge = '✅ OK';
        } elseif ($grade === 'B') {
            $badge = '⚠️ WARN';
        } elseif ($grade === 'C') {
            $badge = '🔶 PARTIAL';
        } else {
            $badge = '❌ CRITICAL';
        }

        $lines[] = "## {$label}";
        $lines[] = "";
        $lines[] = "| Field | Value |";
        $lines[] = "|---|---|";
        $lines[] = "| Status | {$badge} |";
        $lines[] = "| Grade | " . ($grade ?? '—') . " |";
        $lines[] = "| Cognitive type | {$cogType} |";
        $lines[] = "| Composite score | " . ($composite !== null ? fmt($composite) : '—') . " |";
        $lines[] = "| Drift type | " . ($driftType ?? '—') . " |";
        $lines[] = "| Action required | " . ($actionReq ?? '—') . " |";
        $lines[] = "";

        // ── Question content ──────────────────────────────────────────────────
        if ($v !== null) {
            $qt  = $v['question_text'] ?? '_(no content)_';
            $ck  = strtolower($v['correct_answer_key'] ?? '');

            $lines[] = "**Question:**";
            $lines[] = "> " . $qt;
            $lines[] = "";
            $lines[] = "**Answers:**";
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                $ans  = $v["answer_{$letter}"] ?? '—';
                $mark = ($ck === $letter) ? ' ✅' : '';
                $lines[] = "- **" . strtoupper($letter) . ":** {$ans}{$mark}";
            }
            $lines[] = "";
            $lines[] = "**Explanation:**";
            $lines[] = "> " . ($v['explanation'] ?? '—');
            $lines[] = "";
            $lines[] = "**Saviez-vous:**";
            $lines[] = "> " . ($v['saviez_vous'] ?? '—');
            $lines[] = "";
        } else {
            $lines[] = "> ⚠️ Variant content missing in frame_en.";
            $lines[] = "";
        }

        // ── Master proximity ──────────────────────────────────────────────────
        $prox = masterProximityNote($key, $composite ?? 0.0, $masterScore);
        $lines[] = "**Master Proximity:**";
        $lines[] = "- Proximity type: **{$prox['proximity']}**";
        $lines[] = "- {$prox['note']}";
        if ($prox['flagged']) {
            $lines[] = "- ⚠️ Flag: review wording distance from master question";
        }
        $lines[] = "";

        // ── Gameplay Notes + Verdict ──────────────────────────────────────────
        $verdict = gameplayVerdict($key, $grade ?? 'A', $driftType);
        $lines[] = "**Gameplay Verdict:** {$verdict}";
        $lines[] = "";

        if ($msgHumain) {
            $lines[] = "**Phase 2 message:** {$msgHumain}";
            $lines[] = "";
        }

        // ── WHY FAILED ────────────────────────────────────────────────────────
        $whyReasons = $grade !== null
            ? deriveWhyFailed($key, $grade, $composite ?? 0.0, $driftType, $actionReq)
            : [];

        if (! empty($whyReasons)) {
            $lines[] = "**❌ WHY FAILED:**";
            foreach ($whyReasons as $r) {
                $lines[] = "- {$r}";
            }
            $lines[] = "";
        }

        // ── Scores table (if available, includes subscores note) ──────────────
        if ($hasScores && $vsRow !== null) {
            $lines[] = "**Scores:**";
            $lines[] = "";
            $lines[] = "| Metric | Value |";
            $lines[] = "|---|---|";
            $lines[] = "| composite (lexical subject touch) | " . fmt($composite ?? 0.0) . " |";
            // Subscores are only stored for quarantine packages, not regular intents
            $lines[] = "| semantic_chain | _not stored for non-quarantined intents_ |";
            $lines[] = "| cognitive_integrity | _not stored for non-quarantined intents_ |";
            $lines[] = "| Grade | {$grade} |";
            $lines[] = "";
        }

        $lines[] = "────────────────────────────────────────";
        $lines[] = "";
    }

    $lines[] = "---";
    $lines[] = "*Exported by scripts/export-human-review.php — StrategyBuzzer question bank calibration*";

    return implode("\n", $lines);
}

// ─────────────────────────────────────────────────────────────────────────────
// Main: query intents + generate files
// ─────────────────────────────────────────────────────────────────────────────

$generatedFiles = [];
$missingDomains = [];

foreach ($TARGET_DOMAINS as $domain) {
    // Try preferred depth 3-5 first, then any depth
    $row = DB::selectOne(
        "SELECT id, domain, sub_domain, difficulty_depth AS depth, subject, answer_target,
                potential_trap, semantic_key, frame_status, dialysis_status, frame_en
         FROM question_intents
         WHERE domain = :domain
           AND frame_en IS NOT NULL
           AND difficulty_depth BETWEEN 3 AND 5
         ORDER BY
           (frame_en::jsonb->'phase2_result') IS NOT NULL DESC,
           difficulty_depth DESC,
           id DESC
         LIMIT 1",
        ['domain' => $domain]
    );

    if (! $row) {
        // Fallback: any depth
        $row = DB::selectOne(
            "SELECT id, domain, sub_domain, difficulty_depth AS depth, subject, answer_target,
                    potential_trap, semantic_key, frame_status, dialysis_status, frame_en
             FROM question_intents
             WHERE domain = :domain
               AND frame_en IS NOT NULL
             ORDER BY
               (frame_en::jsonb->'phase2_result') IS NOT NULL DESC,
               difficulty_depth DESC,
               id DESC
             LIMIT 1",
            ['domain' => $domain]
        );
    }

    if (! $row) {
        $missingDomains[] = $domain;
        $slug     = slugDomain($domain);
        $filename = "{$slug}_no_data.md";
        $path     = $OUTPUT_DIR . '/' . $filename;
        $content  = "# {$domain} — No kernel data available\n\n"
            . "> No intent with `frame_en` content found for domain **{$domain}** in the question bank.\n\n"
            . "This domain has not yet been generated by the Bank Worker.\n\n"
            . "Run the worker targeting domain `{$domain}` at depth 3–5 to generate content.\n\n"
            . "_Generated: " . date('Y-m-d H:i:s') . " UTC_\n";
        file_put_contents($path, $content);
        $generatedFiles[] = ['domain' => $domain, 'file' => $filename, 'status' => 'missing'];
        continue;
    }

    $frame = json_decode($row->frame_en, true) ?? [];

    $intent = [
        'id'             => $row->id,
        'domain'         => $row->domain,
        'sub_domain'     => $row->sub_domain ?? '',
        'depth'          => $row->depth,
        'subject'        => $row->subject,
        'answer_target'  => $row->answer_target,
        'potential_trap' => $row->potential_trap,
        'semantic_key'   => $row->semantic_key,
        'frame_status'   => $row->frame_status,
        'dialysis_status' => $row->dialysis_status,
        'frame'          => $frame,
    ];

    $slug     = slugDomain($domain);
    $depth    = (int) $row->depth;
    $filename = "{$slug}_depth{$depth}.md";
    $path     = $OUTPUT_DIR . '/' . $filename;

    $markdown = buildMarkdown($intent, $VARIANT_ORDER, $VARIANT_LABEL);
    file_put_contents($path, $markdown);

    $byteSize = strlen($markdown);
    $hasP2    = isset($frame['phase2_result']) ? 'with scores' : 'no scores';
    $generatedFiles[] = [
        'domain'  => $domain,
        'id'      => $row->id,
        'file'    => $filename,
        'depth'   => $depth,
        'subject' => $row->subject,
        'status'  => $hasP2,
        'bytes'   => $byteSize,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Report
// ─────────────────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo "╔══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   Human Review Export — Real Kernel Intents                ║" . PHP_EOL;
echo "╚══════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo PHP_EOL;
echo "  Output dir : {$OUTPUT_DIR}" . PHP_EOL;
echo PHP_EOL;

foreach ($generatedFiles as $f) {
    $dom = str_pad($f['domain'], 12);
    if ($f['status'] === 'missing') {
        echo "  ⚠️  {$dom} → {$f['file']}  (no data in bank)" . PHP_EOL;
    } else {
        $note = $f['status'] === 'with scores' ? 'phase2 scores available' : 'content only (no phase2 stored)';
        echo "  ✅  {$dom} id=#{$f['id']}  depth={$f['depth']}  {$f['bytes']} bytes  — {$note}" . PHP_EOL;
        echo "       {$f['file']}" . PHP_EOL;
        echo "       subject: {$f['subject']}" . PHP_EOL;
    }
    echo PHP_EOL;
}

if (! empty($missingDomains)) {
    echo "══════════════════════════════════════════════════════════════════" . PHP_EOL;
    echo "⚠️  Missing domains (no frame_en in bank): " . implode(', ', $missingDomains) . PHP_EOL;
    echo "   These domains need to be generated by the Bank Worker first." . PHP_EOL;
    echo PHP_EOL;
}

// ─────────────────────────────────────────────────────────────────────────────
// Show full content of first real export (first non-missing file)
// ─────────────────────────────────────────────────────────────────────────────

$firstReal = null;
foreach ($generatedFiles as $f) {
    if ($f['status'] !== 'missing') {
        $firstReal = $f;
        break;
    }
}

if ($firstReal) {
    echo "══════════════════════════════════════════════════════════════════" . PHP_EOL;
    echo "Full content — {$firstReal['file']}:" . PHP_EOL;
    echo "══════════════════════════════════════════════════════════════════" . PHP_EOL;
    echo PHP_EOL;
    echo file_get_contents($OUTPUT_DIR . '/' . $firstReal['file']);
    echo PHP_EOL;
}
