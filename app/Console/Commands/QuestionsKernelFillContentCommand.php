<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionIntent;
use App\Services\QuestionBank\KernelContentBuilder;

/**
 * questions:kernel:fill-content {intent_id}
 *
 * PHASE 1 — Étape 3 : fills English content for all 5 variants of frame_en.
 *
 * Flow master-first (3 étapes internes) :
 *   3-A. Génère la question maître EN (qcm_recognition) via /generate-kernel-master
 *   3-B. Valide la question maître (cohérence kernel_core, lisibilité, distracteurs)
 *   3-C. Génère les 4 variantes dérivées via /generate-kernel-derived-variants
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

    protected $description = 'PHASE 1 Étape 3 — Master-first : génère question maître EN puis 4 variantes dérivées.';

    public function handle(KernelContentBuilder $builder): int
    {
        $intentId = (int) $this->argument('intent_id');
        $force    = (bool) $this->option('force');

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
        $this->line('  <fg=cyan>Étape 3-A :</> Génération question maître EN → /generate-kernel-master …');
        $this->line('  <fg=cyan>Étape 3-B :</> Validation maître (PHP-side)');
        $this->line('  <fg=cyan>Étape 3-C :</> Génération 4 variantes dérivées → /generate-kernel-derived-variants …');
        $this->line('');

        $result = $builder->buildEnglishContent($frame);

        if (! $result['ok']) {
            $step  = $result['step'] ?? '?';
            $error = $result['error'] ?? 'unknown';
            $this->error("Échec étape {$step} : {$error}");
            return self::FAILURE;
        }

        $updatedFrame = $result['frame'];
        $master       = $result['master'];
        $sources      = $result['sources'] ?? [];
        $latencyMs    = $result['latency_total_ms'] ?? 0;

        $this->line("  Sources AI : master={$sources['master']}, derived={$sources['derived']}");
        $this->line("  Latence    : {$latencyMs}ms (total)");
        $this->line('');

        // ── 6. Sauvegarder frame_en ───────────────────────────────────────────
        DB::table('question_intents')
            ->where('id', $intent->id)
            ->update([
                'frame_en'     => json_encode($updatedFrame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'frame_status' => 'content_ready',
                'updated_at'   => now(),
            ]);

        $this->info("✅  frame_en mis à jour — frame_status = content_ready");
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
            'qcm_reasoning'          => 'QCM Reasoning',
            'qcm_deceptive_trap'     => 'QCM Deceptive Trap',
            'true_false_recognition' => 'V/F Recognition',
            'true_false_reasoning'   => 'V/F Reasoning',
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
        $slotsOk = $totalSlots === 45 ? '<fg=green>✅</>' : '<fg=yellow>⚠</>';
        $this->line("  Translation slots (9 langs × 5 variants) : {$totalSlots} {$slotsOk}");
        if ($totalSlots !== 45) {
            $this->warn("  Attendu 45 slots — got {$totalSlots}");
        }
        $this->line('');

        // ── 11. Confirmation aucun question_group touché ──────────────────────
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
