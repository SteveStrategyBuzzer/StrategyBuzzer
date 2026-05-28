<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionIntent;
use App\Services\QuestionBank\KernelContentBuilder;
use App\Services\QuestionBank\KernelLoopAlerter;
use App\Services\QuestionBank\KernelQuarantineManager;

/**
 * questions:kernel:fill-content {intent_id}
 *
 * PHASE 1 — Étape 3 : fills English content for all 7 variants of frame_en.
 *
 * Official flow (2-attempt loop):
 *
 *   Attempt 1 — Full generation (master-first):
 *     3-A. Generate master question (qcm_recognition) → /generate-kernel-master
 *     3-B. Validate master (PHP-side coherence checks)
 *     3-C. Generate 6 derived variants          → /generate-kernel-derived-variants
 *     Phase 2 inline alignment check
 *
 *   If Phase 2 detects anomalies (policy C or D):
 *     Attempt 2 — Targeted retry (derived variants only):
 *       Phase 1 corrects ONLY the flagged variants (qcm_recognition preserved)
 *       → /generate-kernel-derived-variants with retry_guidance
 *       Phase 2 re-checks the merged frame
 *
 *   If Phase 2 still reports major anomalies after attempt 2:
 *     → QUARANTINE: frame_status = 'quarantine'
 *     → Quarantine file written to storage/app/quarantine/
 *     → Alert email (QB_KERNEL_ALERT_EMAIL) or Log::critical fallback
 *     → Pipeline stops; Steve corrects the file manually
 *
 *   If Phase 2 OK after attempt 1 or attempt 2:
 *     → frame_status = 'content_ready'
 *     → translation_slots status = 'pending' (ready for translation)
 *
 *   Loop detection (KernelLoopAlerter) runs after the 2-attempt loop:
 *     same_drift_repeat, max_attempts_reached, kernel_collapse, etc.
 *     These are cross-command-run triggers (not intra-run).
 *
 * Ce que cette commande NE fait PAS :
 *   - Ne traduit pas (les translation_slots restent status=pending)
 *   - Ne touche pas question_groups ni ready_bank
 *   - Ne touche pas le worker ni le gameplay
 */
class QuestionsKernelFillContentCommand extends Command
{
    protected $signature = 'questions:kernel:fill-content
        {intent_id : ID du QuestionIntent à remplir}
        {--force   : Autoriser si frame_status != awaiting_content}';

    protected $description = 'PHASE 1 Étape 3 — Master-first + inline retry + quarantine : génère 7 variants, réessaie les variants signalés, met en quarantaine si nécessaire.';

    // Policy levels that trigger the targeted retry attempt
    private const RETRY_POLICIES = ['C', 'D'];

    // Policy levels that trigger quarantine after retry fails
    private const QUARANTINE_POLICIES = ['C', 'D'];

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

        // ── 5. Tentative 1 — Génération complète (3-A + 3-B + 3-C) ──────────

        $previousPhase2 = $frame['phase2_result'] ?? null;

        $fillAttemptCount = (int) ($frame['_fill_attempt_count'] ?? 0) + 1;
        $frame['_fill_attempt_count'] = $fillAttemptCount;

        $retryGuidance = $builder->buildRetryGuidance($previousPhase2);

        if ($retryGuidance !== null) {
            $this->line('  <fg=yellow>Retry guidance actif</> (policy ' . ($retryGuidance['policy'] ?? '?') . ', ' . count($retryGuidance['failed_variants'] ?? []) . ' variante(s) à corriger)');
        }

        $this->line('  <fg=cyan>Tentative 1 — génération complète :</>');
        $this->line('    3-A : Génération question maître EN → /generate-kernel-master …');
        $this->line('    3-B : Validation maître (PHP-side)');
        $this->line('    3-C : Génération 6 variantes dérivées → /generate-kernel-derived-variants …');
        $this->line('');

        $result = $builder->buildEnglishContent($frame, $retryGuidance);

        if (! $result['ok']) {
            $step  = $result['step'] ?? '?';
            $error = $result['error'] ?? 'unknown';
            $this->error("Échec tentative 1 étape {$step} : {$error}");
            return self::FAILURE;
        }

        $updatedFrame        = $result['frame'];
        $master              = $result['master'];
        $sources             = $result['sources'] ?? [];
        $latencyMasterMs     = $result['latency_master_ms']     ?? 0;
        $latencyValidationMs = $result['latency_validation_ms'] ?? 0;
        $latencyDerivedMs    = $result['latency_derived_ms']    ?? 0;
        $latencyRetryMs      = 0;

        $phase2After1 = $result['phase2_alignment'] ?? null;
        $phase2Policy = $phase2After1['policy'] ?? 'A';

        $this->printPhase2Summary('Tentative 1', $phase2After1);

        // ── 6. Tentative 2 — Retry ciblé si Phase 2 signale C ou D ───────────
        $retryAttempted  = false;
        $retryFixedKeys  = [];

        if (in_array($phase2Policy, self::RETRY_POLICIES, true) && $phase2After1 !== null) {
            $issues1 = $phase2After1['structured_issues'] ?? [];

            // Extract only the flagged derived variant keys (qcm_recognition excluded)
            $failedKeys = array_values(array_unique(array_filter(
                array_column($issues1, 'variant_key'),
                static fn(string $k) => $k !== 'qcm_recognition'
            )));

            if (! empty($failedKeys)) {
                $retryAttempted = true;
                $retryGuidance2 = $builder->buildRetryGuidance($phase2After1);

                $this->line('  <fg=cyan>Tentative 2 — retry ciblé (Phase 1 corrige variants signalés seulement) :</>');
                $this->line('    Variants à corriger : ' . implode(', ', $failedKeys));
                $this->line('    Master (qcm_recognition) : <fg=green>préservé — non régénéré</>');
                $this->line('');

                $retryResult = $builder->retryFlaggedVariants($updatedFrame, $failedKeys, $retryGuidance2);

                if ($retryResult['ok'] && ! ($retryResult['skipped'] ?? false)) {
                    $updatedFrame  = $retryResult['frame'];
                    $retryFixedKeys = $retryResult['fixed_variants'] ?? [];
                    $latencyRetryMs = $retryResult['latency_retry_ms'] ?? 0;

                    $phase2After2 = $retryResult['phase2_alignment'] ?? null;
                    $phase2Policy = $phase2After2['policy'] ?? $phase2Policy;

                    $this->printPhase2Summary('Tentative 2', $phase2After2);
                } elseif (! $retryResult['ok']) {
                    $this->warn('  ⚠  Tentative 2 échouée (' . ($retryResult['error'] ?? 'unknown') . ') — on continue avec le résultat de la tentative 1.');
                }
            }
        }

        // ── 7. Déterminer frame_status final ──────────────────────────────────
        // Quarantine: Phase 2 still C/D after 2 attempts.
        // normal content_ready / partial_review otherwise.
        $needsQuarantine = $retryAttempted && in_array($phase2Policy, self::QUARANTINE_POLICIES, true);
        $newFrameStatus  = $this->resolveFrameStatus($phase2Policy, $needsQuarantine);

        // ── 8. Persister frame_en + frame_status ─────────────────────────────
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

        $this->printFrameStatusLine($newFrameStatus, $phase2Policy);

        // ── 9. Quarantaine — fichier + alerte ────────────────────────────────
        if ($needsQuarantine) {
            $this->line('');
            $this->warn('  ⛔  QUARANTAINE — Phase 2 toujours signalée après 2 tentatives.');
            $this->line('  frame_status → <fg=red;options=bold>quarantine</>');
            $this->line('');

            $quarantineManager = new KernelQuarantineManager();
            $currentPhase2     = $updatedFrame['phase2_result'] ?? [];

            $quarantineFile = $quarantineManager->writeQuarantineFile(
                $intent->id,
                $updatedFrame,
                $currentPhase2
            );

            if ($quarantineFile !== '') {
                $this->line("  📁  Fichier quarantaine : <fg=yellow>{$quarantineFile}</>");
            }

            // QB_KERNEL_ALERT_EMAIL status
            $recipient = (string) config('question_bank_profiles.kernel_alert.email_recipient', '');
            $mailer    = (string) config('mail.default', 'log');

            if ($recipient === '') {
                $this->line('  📧  QB_KERNEL_ALERT_EMAIL non configuré → alerte Log::critical uniquement.');
            } elseif ($mailer === 'log') {
                $this->line("  📧  QB_KERNEL_ALERT_EMAIL configuré ({$recipient}) mais MAIL_MAILER=log → Log::critical uniquement.");
            } else {
                $this->line("  📧  Email quarantaine → {$recipient}");
            }

            $quarantineManager->sendAlert(
                $intent->id,
                $quarantineFile,
                $currentPhase2,
                $kc
            );

            $this->line('');
            $this->warn('  Workflow arrêté. Steve doit corriger le fichier et relancer :');
            $this->line('  php artisan questions:kernel:import-quarantine <file>');
            $this->line('');
        }

        // ── 10. KernelLoopAlerter — détection de boucles cross-run ──────────
        // Runs regardless of quarantine — catches same_drift_repeat,
        // max_attempts_reached, kernel_collapse across multiple command runs.
        $alerter      = new KernelLoopAlerter();
        $currentPhase2 = $updatedFrame['phase2_result'] ?? [];

        $triggerReason = $alerter->evaluate($updatedFrame, $intent, $previousPhase2, $currentPhase2);

        if ($triggerReason !== null) {
            if ($alerter->isBlocking($triggerReason) && $newFrameStatus !== 'quarantine') {
                $alertStatus = $alerter->resolveBlockingStatus($triggerReason);
                DB::table('question_intents')
                    ->where('id', $intent->id)
                    ->update(['frame_status' => $alertStatus, 'updated_at' => now()]);
                $this->warn("🚨  frame_status → {$alertStatus} (trigger: {$triggerReason})");
            } else {
                $this->warn("⚠  Alerte kernel non-bloquante : {$triggerReason}");
            }

            $alerter->maybeAlert($updatedFrame, $intent, $triggerReason, $currentPhase2);

            DB::table('question_intents')
                ->where('id', $intent->id)
                ->update([
                    'frame_en'   => json_encode($updatedFrame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        $this->line('');

        // ── 11. Timing ────────────────────────────────────────────────────────
        $this->line('  <fg=cyan;options=bold>⏱  Timing Phase 1 — Étape 3 :</>');
        $this->line('');
        $this->line(sprintf("  %-42s %6d ms", '3-A master_generation_ms',          $latencyMasterMs));
        $this->line(sprintf("  %-42s %6d ms", '3-B master_quick_validation_ms',    $latencyValidationMs));
        $this->line(sprintf("  %-42s %6d ms", '3-C derived_variants_generation_ms',$latencyDerivedMs));
        if ($retryAttempted && $latencyRetryMs > 0) {
            $this->line(sprintf("  %-42s %6d ms", '3-C-retry targeted_variants_ms', $latencyRetryMs));
        }
        $this->line(sprintf("  %-42s %6d ms", 'persistence_ms (DB write)',         $persistMs));
        $this->line('  ' . str_repeat('─', 52));
        $this->line(sprintf("  %-42s %6d ms", 'total_ms', $totalMs));
        $this->line('');
        $this->line(sprintf("  %-24s %s", 'provider_master :',  $sources['master']  ?? '—'));
        $this->line(sprintf("  %-24s %s", 'provider_variants :', $sources['derived'] ?? '—'));
        $this->line('');

        // ── 12. Résumé question maître ────────────────────────────────────────
        $this->line('  <fg=cyan;options=bold>Question maître (qcm_recognition) :</>');
        $masterCk    = strtoupper($master['correct_answer_key'] ?? '?');
        $masterField = 'answer_' . strtolower($masterCk);
        $this->line("    Q  : " . mb_substr($master['question_text'] ?? '—', 0, 80));
        $this->line("    ✅  [{$masterCk}] " . ($master[$masterField] ?? '?'));
        $this->line("    💡  " . mb_substr($master['saviez_vous'] ?? '—', 0, 80));
        $this->line('');

        // ── 13. Résumé variants dérivés ───────────────────────────────────────
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

        $retryFixedSet = array_flip($retryFixedKeys);

        foreach ($derivedOrder as $key => $label) {
            $v  = $updatedFrame['variants'][$key] ?? [];
            $q  = mb_substr($v['question_text'] ?? '—', 0, 80);
            $ck = strtoupper($v['correct_answer_key'] ?? '?');
            $ansField = 'answer_' . strtolower($ck);
            $ans = $v[$ansField] ?? '?';

            $retryMarker = isset($retryFixedSet[$key]) ? ' <fg=cyan>[retry-fixed]</>' : '';
            $this->line("  <fg=green>{$label}</>{$retryMarker}");
            $this->line("    Q  : {$q}");
            $this->line("    ✅  [{$ck}] {$ans}");

            if ($key === 'qcm_deceptive_trap') {
                $cc = $v['cognitive_contract'] ?? $v;
                $this->line("    🪤  trap_type : " . ($cc['trap_type'] ?? '?'));
                $this->line("    🪤  intuition : " . mb_substr($cc['intuitive_wrong_answer'] ?? '?', 0, 60));
                $this->line("    🪤  presence  : " . ($cc['intuitive_answer_presence'] ?? '?'));
            }
            $this->line('');
        }

        // ── 14. Ancrage kernel_core ───────────────────────────────────────────
        $this->line('  <fg=cyan>Ancrage kernel_core :</>');
        $this->line("    answer_target  : " . ($kc['answer_target'] ?? '?'));
        $this->line("    concept_family : " . ($kc['concept_family'] ?? '?'));
        $this->line("    kernel_core    : intact (inchangé)");
        $this->line('');

        // ── 15. Translation slots ─────────────────────────────────────────────
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

        // ── 16. Phase 2 final inline display ─────────────────────────────────
        $finalPhase2 = $updatedFrame['phase2_result'] ?? null;
        if (is_array($finalPhase2)) {
            $this->line('  <fg=cyan;options=bold>Phase 2 — Ancrage sujet touché (état final) :</>');
            $this->line('');
            $this->printPhase2Scores($finalPhase2);
            $this->line('');
        }

        // ── 17. Confirmation question_groups ──────────────────────────────────
        $groupsCount = DB::table('question_groups')
            ->where('question_intent_id', $intent->id)
            ->count();
        $readyBank = DB::table('question_groups')
            ->where('post_review_status', 'ready_bank')
            ->count();
        $this->line("  question_groups liés (inchangés) : {$groupsCount}");
        $this->line("  ready_bank total (inchangé)      : {$readyBank}");
        $this->line('');

        return $needsQuarantine ? self::FAILURE : self::SUCCESS;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve the frame_status from Phase 2 policy + quarantine flag.
     *
     *   quarantine   : policy C/D after 2 attempts → 'quarantine'
     *   policy D     : without quarantine (1st attempt) → 'partial_review'
     *   policy C     : without quarantine (1st attempt) → 'partial_review'
     *   policy A/B   : → 'content_ready'
     */
    private function resolveFrameStatus(string $phase2Policy, bool $needsQuarantine): string
    {
        if ($needsQuarantine) {
            return 'quarantine';
        }
        return in_array($phase2Policy, ['C', 'D'], true) ? 'partial_review' : 'content_ready';
    }

    private function printFrameStatusLine(string $newFrameStatus, string $phase2Policy): void
    {
        match ($newFrameStatus) {
            'quarantine'     => $this->warn("⛔  frame_en mis à jour — frame_status = quarantine (Phase 2 policy {$phase2Policy} après 2 tentatives)"),
            'partial_review' => $this->warn("⚠  frame_en mis à jour — frame_status = partial_review (Phase 2 policy {$phase2Policy})"),
            default          => $this->info("✅  frame_en mis à jour — frame_status = content_ready"),
        };
    }

    private function printPhase2Summary(string $label, ?array $phase2): void
    {
        if (! is_array($phase2)) {
            return;
        }
        $p = $phase2['policy'] ?? '?';
        $s = $phase2['summary'] ?? [];
        $colors = ['A' => 'green', 'B' => 'yellow', 'C' => 'cyan', 'D' => 'red'];
        $color  = $colors[$p] ?? 'white';

        $this->line("  Phase 2 ({$label}) — Policy=<fg={$color};options=bold>{$p}</>  OK={$s['ok_count']}  WARN={$s['warn_count']}  PARTIAL={$s['partial_count']}  REVIEW={$s['review_count']}");
        if (! ($phase2['ok'] ?? true)) {
            $this->line("  <fg=yellow>→ {$phase2['recommendation']}</>");
        }
        $this->line('');
    }

    private function printPhase2Scores(array $phase2): void
    {
        $gradeColors  = ['A' => 'green', 'B' => 'yellow', 'C' => 'cyan', 'D' => 'red'];
        $gradeMarkers = ['A' => '✅', 'B' => '⚠ ', 'C' => '🔶', 'D' => '❌'];

        foreach ($phase2['variant_scores'] ?? [] as $vKey => $vs) {
            $grade  = $vs['grade'];
            $score  = number_format($vs['score'], 3);
            $color  = $gradeColors[$grade]  ?? 'white';
            $marker = $gradeMarkers[$grade] ?? '  ';

            // Show composite sub-score if available
            $composite = isset($vs['subscores']['composite'])
                ? sprintf('  composite=%s', number_format($vs['subscores']['composite'], 3))
                : '';

            $this->line(sprintf(
                "  %s <fg=%s>%-7s</> %-22s  score=%s%s",
                $marker, $color, $grade, $vKey, $score, $composite
            ));
        }

        $p = $phase2['policy'] ?? '?';
        $s = $phase2['summary'] ?? [];
        $pColor = $gradeColors[$p] ?? 'white';
        $this->line('');
        $this->line("  Policy=<fg={$pColor};options=bold>{$p}</>  OK={$s['ok_count']} WARN={$s['warn_count']} PARTIAL={$s['partial_count']} REVIEW={$s['review_count']}");
        if (! ($phase2['ok'] ?? true)) {
            $this->line("  <fg=yellow>→ {$phase2['recommendation']}</>");
        }
    }
}
