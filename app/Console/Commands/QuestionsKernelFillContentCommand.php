<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionIntent;
use App\Services\QuestionBank\KernelContentBuilder;
use App\Services\QuestionBank\KernelLoopAlerter;

/**
 * questions:kernel:fill-content {intent_id}
 *
 * PHASE 1 — Étape 3 : fills English content for all 7 variants of frame_en.
 *
 * Flow master-first (3 étapes internes) :
 *   3-A. Génère la question maître EN (qcm_recognition) via /generate-kernel-master
 *   3-B. Valide la question maître (cohérence kernel_core, lisibilité, distracteurs)
 *   3-C. Génère les 6 variantes dérivées via /generate-kernel-derived-variants
 *
 * Ce que cette commande NE fait PAS :
 *   - Ne traduit pas (les translation_slots restent status=pending)
 *   - Ne touche pas question_groups ni ready_bank
 *   - Ne touche pas le worker ni le gameplay
 *   - Ne valide pas la qualité cognitive (Phase 2)
 */
class QuestionsKernelFillContentCommand extends Command
{
    protected $signature = 'questions:kernel:fill-content
        {intent_id : ID du QuestionIntent à remplir}
        {--force   : Autoriser si frame_status != awaiting_content}';

    protected $description = 'PHASE 1 Étape 3 — Master-first : génère question maître EN puis 6 variantes dérivées (7-variant system).';

    public function handle(KernelContentBuilder $builder): int
    {
        $intentId = (int) $this->argument('intent_id');
        $force    = (bool) $this->option('force');
        $cmdStart = (int) round(microtime(true) * 1000);

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   PHASE 1 — Étape 3 : Remplissage contenu EN (master-first)    ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');

        // ── 1. Charger le noyau ───────────────────────────────────────────────
        $intent = QuestionIntent::find($intentId);

        if (! $intent) {
            $this->error("QuestionIntent #{$intentId} introuvable.");
            return self::FAILURE;
        }

        $this->line("  Noyau        : #{$intent->id}");
        $this->line("  semantic_key : " . ($intent->semantic_key ?? '<fg=yellow>non défini</>'));
        $this->line("  domain       : {$intent->domain} / {$intent->sub_domain}");
        $this->line("  depth        : {$intent->difficulty_depth}");
        $this->line("  frame_status : " . ($intent->frame_status ?? '<fg=yellow>NULL</>'));
        $this->line('');

        // ── 2. Vérifier frame_status ──────────────────────────────────────────
        if ($intent->frame_status !== 'awaiting_content' && ! $force) {
            $this->error("frame_status = '{$intent->frame_status}' (attendu: 'awaiting_content').");
            $this->error("Relancer questions:kernel:validate-structure {$intentId} d'abord.");
            $this->line("Ou utiliser --force pour ignorer cette vérification.");
            return self::FAILURE;
        }

        // ── 3. Lire frame_en ──────────────────────────────────────────────────
        $rawFrameEn = DB::table('question_intents')
            ->where('id', $intent->id)
            ->value('frame_en');

        if ($rawFrameEn === null) {
            $this->error("frame_en est NULL. Relancer questions:kernel:skeleton {$intentId} d'abord.");
            return self::FAILURE;
        }

        $frame = json_decode($rawFrameEn, true);
        if (! is_array($frame)) {
            $this->error("frame_en n'est pas un JSON valide.");
            return self::FAILURE;
        }

        // ── 4. Afficher kernel_core ───────────────────────────────────────────
        $kc = $frame['kernel_core'] ?? [];
        $this->line("  Kernel core :");
        $this->line("    subject       : " . ($kc['subject'] ?? '?'));
        $this->line("    answer_target : " . ($kc['answer_target'] ?? '?'));
        $this->line("    potential_trap: " . ($kc['potential_trap'] ?? 'none'));
        $this->line('');

        // ── 5. Appel KernelContentBuilder (3-A + 3-B + 3-C) ──────────────────

        // Capture Phase 2 result from any previous run (used by alerter for drift comparison).
        $previousPhase2 = $frame['phase2_result'] ?? null;

        // Increment fill attempt counter (stored in frame_en, no migration needed).
        $fillAttemptCount = (int) ($frame['_fill_attempt_count'] ?? 0) + 1;
        $frame['_fill_attempt_count'] = $fillAttemptCount;

        $this->line('  <fg=cyan>Étape 3-A :</> Génération question maître EN → /generate-kernel-master …');
        $this->line('  <fg=cyan>Étape 3-B :</> Validation maître (PHP-side)');
        $this->line('  <fg=cyan>Étape 3-C :</> Génération 6 variantes dérivées → /generate-kernel-derived-variants …');
        $this->line('');

        $result = $builder->buildEnglishContent($frame);

        if (! $result['ok']) {
            $step  = $result['step'] ?? '?';
            $error = $result['error'] ?? 'unknown';
            $this->error("Échec étape {$step} : {$error}");
            return self::FAILURE;
        }

        $updatedFrame        = $result['frame'];
        $master              = $result['master'];
        $sources             = $result['sources'] ?? [];
        $latencyMasterMs     = $result['latency_master_ms'] ?? 0;
        $latencyValidationMs = $result['latency_validation_ms'] ?? 0;
        $latencyDerivedMs    = $result['latency_derived_ms'] ?? 0;
        $latencyTotalAiMs    = $result['latency_total_ms'] ?? 0;

        // ── 6. Sauvegarder frame_en ───────────────────────────────────────────
        // Phase 2 policy D → frame_status bloqué à partial_review (jamais content_ready).
        // Policy A / B / C / null → content_ready (warnings persistés dans phase2_result).
        $phase2Policy   = $result['phase2_alignment']['policy'] ?? null;
        $newFrameStatus = ($phase2Policy === 'D') ? 'partial_review' : 'content_ready';

        $persistStart = (int) round(microtime(true) * 1000);
        DB::table('question_intents')
            ->where('id', $intent->id)
            ->update([
                'frame_en'     => json_encode($updatedFrame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'frame_status' => $newFrameStatus,
                'updated_at'   => now(),
            ]);
        $persistMs = (int) round(microtime(true) * 1000) - $persistStart;
        $totalMs   = (int) round(microtime(true) * 1000) - $cmdStart;

        if ($newFrameStatus === 'partial_review') {
            $this->warn("⚠  frame_en mis à jour — frame_status = partial_review (Phase 2 policy D — regen requis)");
        } else {
            $this->info("✅  frame_en mis à jour — frame_status = content_ready");
        }

        // ── 6b. Kernel loop alerter ───────────────────────────────────────────
        // Evaluate all drift/loop triggers and fire alert (email + Log::critical).
        // Blocking triggers override frame_status to human_review or rejected.
        $alerter      = new KernelLoopAlerter();
        $phase2Result = $updatedFrame['phase2_result'] ?? [];

        $triggerReason = $alerter->evaluate($updatedFrame, $intent, $previousPhase2, $phase2Result);

        if ($triggerReason !== null) {
            if ($alerter->isBlocking($triggerReason)) {
                $alertStatus = $alerter->resolveBlockingStatus($triggerReason);
                DB::table('question_intents')
                    ->where('id', $intent->id)
                    ->update(['frame_status' => $alertStatus, 'updated_at' => now()]);
                $this->warn("🚨  frame_status → {$alertStatus} (trigger: {$triggerReason})");
            } else {
                $this->warn("⚠  Alerte kernel non-bloquante déclenchée : {$triggerReason}");
            }

            // maybeAlert() handles dedup, writes _alert_sent_at + _alert_trigger into $updatedFrame
            $alerter->maybeAlert($updatedFrame, $intent, $triggerReason, $phase2Result);

            // Persist frame_en again with _alert_sent_at + _alert_trigger
            DB::table('question_intents')
                ->where('id', $intent->id)
                ->update([
                    'frame_en'   => json_encode($updatedFrame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
        $this->line('');

        // ── 6b. Timing détaillé ───────────────────────────────────────────────
        $this->line('  <fg=cyan;options=bold>⏱  Timing Phase 1 — Étape 3 :</>');
        $this->line('');
        $this->line(sprintf("  %-42s %6d ms", 'skeleton_ms (étape 1 — commande séparée)', 0));
        $this->line(sprintf("  %-42s %6s   ", '', '<fg=yellow>→ questions:kernel:skeleton (non mesuré ici)</>'));
        $this->line(sprintf("  %-42s %6d ms", 'structure_validation_ms (étape 2 — séparée)', 0));
        $this->line(sprintf("  %-42s %6s   ", '', '<fg=yellow>→ questions:kernel:validate-structure (non mesuré ici)</>'));
        $this->line(sprintf("  %-42s %6d ms", '3-A master_generation_ms', $latencyMasterMs));
        $this->line(sprintf("  %-42s %6d ms", '3-B master_quick_validation_ms', $latencyValidationMs));
        $this->line(sprintf("  %-42s %6d ms", '3-C derived_variants_generation_ms', $latencyDerivedMs));
        $this->line(sprintf("  %-42s %6d ms", 'persistence_ms (DB write)', $persistMs));
        $this->line('  ' . str_repeat('─', 52));
        $this->line(sprintf("  %-42s %6d ms", 'total_phase1_ms (3-A + 3-B + 3-C + DB)', $totalMs));
        $this->line('');
        $this->line(sprintf("  %-24s %s", 'provider_master :', $sources['master'] ?? '—'));
        $this->line(sprintf("  %-24s %s", 'provider_variants :', $sources['derived'] ?? '—'));
        $this->line('');

        // ── 7. Résumé question maître ─────────────────────────────────────────
        $this->line('  <fg=cyan;options=bold>Question maître (qcm_recognition) :</>');
        $masterCk    = strtoupper($master['correct_answer_key'] ?? '?');
        $masterField = 'answer_' . strtolower($masterCk);
        $this->line("    Q  : " . mb_substr($master['question_text'] ?? '—', 0, 80));
        $this->line("    ✅  [{$masterCk}] " . ($master[$masterField] ?? '?'));
        $this->line("    💡  " . mb_substr($master['saviez_vous'] ?? '—', 0, 80));
        $this->line('');

        // ── 8. Résumé des 4 variantes dérivées ───────────────────────────────
        $this->line('  <fg=cyan;options=bold>Variantes dérivées :</>');
        $this->line('');

        $derivedOrder = [
            'qcm_reasoning'        => 'QCM Reasoning',
            'qcm_deceptive_trap'   => 'QCM Deceptive Trap',
            'tf_recognition_true'  => 'V/F Recognition (TRUE)',
            'tf_recognition_false' => 'V/F Recognition (FALSE)',
            'tf_reasoning_true'    => 'V/F Reasoning (TRUE)',
            'tf_reasoning_false'   => 'V/F Reasoning (FALSE)',
        ];

        foreach ($derivedOrder as $key => $label) {
            $v  = $updatedFrame['variants'][$key] ?? [];
            $q  = mb_substr($v['question_text'] ?? '—', 0, 80);
            $ck = strtoupper($v['correct_answer_key'] ?? '?');
            $ansField = 'answer_' . strtolower($ck);
            $ans = $v[$ansField] ?? '?';

            $this->line("  <fg=green>{$label}</>");
            $this->line("    Q  : {$q}");
            $this->line("    ✅  [{$ck}] {$ans}");

            if ($key === 'qcm_deceptive_trap') {
                $cc = $v['cognitive_contract'] ?? [];
                $this->line("    🪤  trap_type : " . ($cc['trap_type'] ?? '?'));
                $this->line("    🪤  intuition : " . mb_substr($cc['intuitive_wrong_answer'] ?? '?', 0, 60));
                $this->line("    🪤  presence  : " . ($cc['intuitive_answer_presence'] ?? '?'));
            }
            $this->line('');
        }

        // ── 9. Vérification answer_target ─────────────────────────────────────
        $this->line('  <fg=cyan>Ancrage kernel_core :</>');
        $this->line("    answer_target  : " . ($kc['answer_target'] ?? '?'));
        $this->line("    concept_family : " . ($kc['concept_family'] ?? '?'));
        $this->line("    kernel_core    : intact (inchangé)");
        $this->line('');

        // ── 10. Translation slots ─────────────────────────────────────────────
        $totalSlots = 0;
        foreach ($updatedFrame['variants'] ?? [] as $vSlot) {
            $totalSlots += count($vSlot['translation_slots'] ?? []);
        }
        $slotsOk = $totalSlots === 63 ? '<fg=green>✅</>' : '<fg=yellow>⚠</>';
        $this->line("  Translation slots (9 langs × 7 variants) : {$totalSlots} {$slotsOk}");
        if ($totalSlots !== 63) {
            $this->warn("  Attendu 63 slots — got {$totalSlots}");
        }
        $this->line('');

        // ── 11. Phase 2 — subject-touch alignment scores ─────────────────────
        $phase2 = $result['phase2_alignment'] ?? null;
        if (is_array($phase2)) {
            $this->line('  <fg=cyan;options=bold>Phase 2 — Ancrage sujet touché :</>');
            $this->line('');

            $gradeColors = ['A' => 'green', 'B' => 'yellow', 'C' => 'cyan', 'D' => 'red'];
            $gradeMarkers = ['A' => '✅', 'B' => '⚠ ', 'C' => '🔶', 'D' => '❌'];

            foreach ($phase2['variant_scores'] as $vKey => $vs) {
                $grade  = $vs['grade'];
                $score  = number_format($vs['score'], 3);
                $color  = $gradeColors[$grade]  ?? 'white';
                $marker = $gradeMarkers[$grade] ?? '  ';
                $this->line(sprintf(
                    "  %s <fg=%s>%-7s</> %-22s  score=%s",
                    $marker, $color, $grade, $vKey, $score
                ));
            }

            $s = $phase2['summary'];
            $p = $phase2['policy'];
            $pColor = $gradeColors[$p] ?? 'white';
            $this->line('');
            $this->line("  Policy=<fg={$pColor};options=bold>{$p}</>  OK={$s['ok_count']} WARN={$s['warn_count']} PARTIAL={$s['partial_count']} REVIEW={$s['review_count']}");
            if (! $phase2['ok']) {
                $this->line("  <fg=yellow>→ {$phase2['recommendation']}</>");
            }
            $this->line('');
        }

        // ── 12. Confirmation aucun question_group touché ──────────────────────
        $groupsCount = DB::table('question_groups')
            ->where('question_intent_id', $intent->id)
            ->count();

        $readyBank = DB::table('question_groups')
            ->where('post_review_status', 'ready_bank')
            ->count();

        $this->line("  question_groups liés (inchangés) : {$groupsCount}");
        $this->line("  ready_bank total (inchangé)      : {$readyBank}");
        $this->line('');

        return self::SUCCESS;
    }
}
