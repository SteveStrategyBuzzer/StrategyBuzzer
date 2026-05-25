<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionIntent;
use App\Services\QuestionBank\VariantAlignmentChecker;

/**
 * questions:kernel:validate-content {intent_id}
 *
 * PHASE 2 — Subject-touch validation.
 *
 * Runs VariantAlignmentChecker on a content_ready noyau:
 *   - Computes subject_touch_score for each of the 7 variants
 *   - Applies 4-level correction policy (A/B/C/D)
 *   - Optionally writes frame_status = partial_review (policy C)
 *
 * Does NOT regenerate content. Does NOT touch question_groups.
 */
class QuestionsKernelValidateContentCommand extends Command
{
    protected $signature = 'questions:kernel:validate-content
        {intent_id : ID du QuestionIntent à valider (Phase 2)}
        {--apply   : Appliquer frame_status=partial_review si policy=C ou D}';

    protected $description = 'PHASE 2 — Subject-touch score validation des 7 variantes (sujet touché).';

    public function handle(VariantAlignmentChecker $checker): int
    {
        $intentId = (int) $this->argument('intent_id');
        $apply    = (bool) $this->option('apply');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   PHASE 2 — Validation ancrage sujet touché                   ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');

        // ── 1. Charger le noyau ───────────────────────────────────────────────
        $intent = QuestionIntent::find($intentId);

        if (! $intent) {
            $this->error("QuestionIntent #{$intentId} introuvable.");
            return self::FAILURE;
        }

        $this->line("  Noyau        : #{$intent->id}");
        $this->line("  domain       : {$intent->domain} / {$intent->sub_domain}");
        $this->line("  subject      : " . ($intent->subject ?? '?'));
        $this->line("  answer_target: " . ($intent->answer_target ?? '?'));
        $this->line("  frame_status : " . ($intent->frame_status ?? 'NULL'));
        $this->line('');

        // ── 2. Vérifier frame_status ──────────────────────────────────────────
        if ($intent->frame_status !== 'content_ready') {
            $this->warn("frame_status = '{$intent->frame_status}' (attendu: content_ready).");
            $this->warn("Relancer questions:kernel:fill-content {$intentId} d'abord.");
            return self::FAILURE;
        }

        // ── 3. Lire frame_en ──────────────────────────────────────────────────
        $rawFrameEn = DB::table('question_intents')
            ->where('id', $intent->id)
            ->value('frame_en');

        if ($rawFrameEn === null) {
            $this->error("frame_en est NULL.");
            return self::FAILURE;
        }

        $frame = json_decode($rawFrameEn, true);
        if (! is_array($frame)) {
            $this->error("frame_en invalide (non-JSON).");
            return self::FAILURE;
        }

        // ── 4. Afficher les paramètres kernel_core utilisés ───────────────────
        $kc = $frame['kernel_core'] ?? [];
        $this->line('  <fg=cyan>Ancre kernel_core :</>');
        $this->line("    subject        : " . ($kc['subject']        ?? '?'));
        $this->line("    micro_angle    : " . ($kc['micro_angle']    ?? '?'));
        $this->line("    answer_target  : " . ($kc['answer_target']  ?? '?'));
        $this->line("    potential_trap : " . ($kc['potential_trap'] ?? 'none'));
        $this->line("    concept_family : " . ($kc['concept_family'] ?? '?'));
        $this->line('');

        // ── 5. Lancer VariantAlignmentChecker ────────────────────────────────
        $result = $checker->check($frame);

        // ── 6. Afficher scores par variante ───────────────────────────────────
        $this->line('  <fg=cyan;options=bold>Scores subject_touch par variante :</>');
        $this->line('');

        $gradeColors = [
            'A' => 'green',
            'B' => 'yellow',
            'C' => 'cyan',
            'D' => 'red',
        ];
        $gradeLabels = [
            'A' => 'OK',
            'B' => 'WARN',
            'C' => 'PARTIAL',
            'D' => 'REVIEW',
        ];

        foreach ($result['variant_scores'] as $variantKey => $vs) {
            $grade     = $vs['grade'];
            $score     = number_format($vs['score'], 3);
            $hayLen    = $vs['haystack_len'];
            $color     = $gradeColors[$grade] ?? 'white';
            $label     = $gradeLabels[$grade] ?? $grade;
            $marker    = $grade === 'A' ? '✅' : ($grade === 'B' ? '⚠ ' : ($grade === 'C' ? '🔶' : '❌'));

            $this->line(sprintf(
                "  %s <fg=%s>%-8s</> %-22s  score=%-6s  haystack=%d chars",
                $marker, $color, $label, $variantKey, $score, $hayLen
            ));
        }

        $this->line('');

        // ── 7. Résumé ─────────────────────────────────────────────────────────
        $s = $result['summary'];
        $this->line("  <fg=cyan>Résumé :</> OK={$s['ok_count']}  WARN={$s['warn_count']}  PARTIAL={$s['partial_count']}  REVIEW={$s['review_count']}");
        $this->line('');

        // ── 8. Politique de correction ────────────────────────────────────────
        $policy = $result['policy'];
        $policyColors = ['A' => 'green', 'B' => 'yellow', 'C' => 'cyan', 'D' => 'red'];
        $policyColor  = $policyColors[$policy] ?? 'white';

        $this->line("  <fg=cyan>Politique correction :</> <fg={$policyColor};options=bold>{$policy}</>");
        $this->line("  {$result['recommendation']}");
        $this->line('');

        // ── 9. Afficher issues ────────────────────────────────────────────────
        if (! empty($result['issues'])) {
            $this->line('  <fg=yellow>Issues détectées :</>');
            foreach ($result['issues'] as $issue) {
                $this->line("    · {$issue}");
            }
            $this->line('');
        }

        // ── 10. Appliquer frame_status si --apply et policy C ou D ───────────
        if ($apply && in_array($policy, ['C', 'D'], true)) {
            $newStatus = 'partial_review';
            DB::table('question_intents')
                ->where('id', $intent->id)
                ->update([
                    'frame_status' => $newStatus,
                    'updated_at'   => now(),
                ]);
            $this->warn("  frame_status → {$newStatus} (--apply)");
            $this->line('');
        } elseif ($apply && $policy === 'A') {
            $this->info("  Policy A — aucun changement de statut nécessaire.");
            $this->line('');
        }

        // ── 11. Résultat final ────────────────────────────────────────────────
        if ($result['ok']) {
            $this->info("✅  Phase 2 PASS — tous les variants ancrés au sujet touché.");
        } else {
            $this->warn("⚠   Phase 2 : " . count($result['issues']) . " issue(s) détectée(s). Voir recommandation ci-dessus.");
        }

        $this->line('');

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
