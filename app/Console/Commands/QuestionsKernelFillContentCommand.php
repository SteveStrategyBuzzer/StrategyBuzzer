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
 * Ce que cette commande fait :
 *   1. Lit frame_en (frame_status doit être 'awaiting_content')
 *   2. Appelle KernelContentBuilder::buildEnglishContent()
 *   3. Sauvegarde frame_en mis à jour
 *   4. frame_status = 'content_ready'
 *
 * Ce que cette commande NE fait PAS :
 *   - Ne traduit pas (les translation_slots restent status=pending)
 *   - Ne touche pas question_groups
 *   - Ne touche pas ready_bank
 *   - Ne touche pas le worker
 *   - Ne touche pas le gameplay
 *   - Ne valide pas la qualité cognitive (Phase 2)
 */
class QuestionsKernelFillContentCommand extends Command
{
    protected $signature = 'questions:kernel:fill-content
        {intent_id : ID du QuestionIntent à remplir}
        {--force   : Autoriser si frame_status != awaiting_content}';

    protected $description = 'PHASE 1 Étape 3 — Remplit le contenu EN des 5 variantes et passe frame_status à content_ready.';

    public function handle(KernelContentBuilder $builder): int
    {
        $intentId = (int) $this->argument('intent_id');
        $force    = (bool) $this->option('force');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   PHASE 1 — Étape 3 : Remplissage contenu EN                   ║');
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

        // ── 4. Appel KernelContentBuilder ─────────────────────────────────────
        $kc = $frame['kernel_core'] ?? [];
        $this->line("  Génération EN pour :");
        $this->line("    subject      : " . ($kc['subject'] ?? '?'));
        $this->line("    answer_target: " . ($kc['answer_target'] ?? '?'));
        $this->line("    potential_trap: " . ($kc['potential_trap'] ?? 'none'));
        $this->line('');
        $this->line('  Appel Question API → /generate-kernel-variants …');
        $this->line('');

        $result = $builder->buildEnglishContent($frame);

        if (! $result['ok']) {
            $this->error("Échec génération : " . ($result['error'] ?? 'unknown'));
            return self::FAILURE;
        }

        $updatedFrame = $result['frame'];
        $source       = $result['source'] ?? 'unknown';
        $latencyMs    = $result['latency_ms'] ?? 0;

        $this->line("  Source AI  : {$source}");
        $this->line("  Latence    : {$latencyMs}ms");
        $this->line('');

        // ── 5. Sauvegarder frame_en ───────────────────────────────────────────
        DB::table('question_intents')
            ->where('id', $intent->id)
            ->update([
                'frame_en'     => json_encode($updatedFrame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'frame_status' => 'content_ready',
                'updated_at'   => now(),
            ]);

        $this->info("✅  frame_en mis à jour — frame_status = content_ready");
        $this->line('');

        // ── 6. Résumé des 5 variants ──────────────────────────────────────────
        $this->line('  <fg=cyan;options=bold>Résumé des 5 variantes EN :</>');
        $this->line('');

        $variantOrder = [
            'qcm_recognition'        => 'QCM Recognition',
            'qcm_reasoning'          => 'QCM Reasoning',
            'qcm_deceptive_trap'     => 'QCM Deceptive Trap',
            'true_false_recognition' => 'V/F Recognition',
            'true_false_reasoning'   => 'V/F Reasoning',
        ];

        foreach ($variantOrder as $key => $label) {
            $v = $updatedFrame['variants'][$key] ?? [];
            $q = mb_substr($v['question_text'] ?? '—', 0, 80);
            $ck = $v['correct_answer_key'] ?? '?';
            $ans = $v["answer_{$this->keyToField($ck)}"] ?? '?';
            $this->line("  <fg=green>{$label}</>");
            $this->line("    Q : {$q}");
            $this->line("    ✅ [{$ck}] {$ans}");

            if ($key === 'qcm_deceptive_trap') {
                $cc = $v['cognitive_contract'] ?? [];
                $this->line("    🪤 trap_type  : " . ($cc['trap_type'] ?? '?'));
                $this->line("    🪤 intuition  : " . mb_substr($cc['intuitive_wrong_answer'] ?? '?', 0, 60));
                $this->line("    🪤 presence   : " . ($cc['intuitive_answer_presence'] ?? '?'));
            }
            $this->line('');
        }

        // ── 7. Vérification answer_target cohérent ────────────────────────────
        $this->line('  <fg=cyan>Vérification answer_target :</>');
        $answerTarget = $kc['answer_target'] ?? '';
        $this->line("    kernel_core.answer_target : {$answerTarget}");
        $this->line("    (Vérification sémantique manuelle ou Phase 2 DT-3)");
        $this->line('');

        // ── 8. Translation slots — comptage dans les variants ────────────────
        $totalSlots = 0;
        foreach ($updatedFrame['variants'] ?? [] as $vSlot) {
            $totalSlots += count($vSlot['translation_slots'] ?? []);
        }
        $this->line("  Translation slots (9 langs × 5 variants) : {$totalSlots}");
        if ($totalSlots !== 45) {
            $this->warn("  ⚠ Attendu 45 slots — got {$totalSlots}");
        }
        $this->line('');

        // ── 9. Confirmation aucun question_group touché ───────────────────────
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

    private function keyToField(string $key): string
    {
        return match (strtoupper($key)) {
            'A' => 'a',
            'B' => 'b',
            'C' => 'c',
            'D' => 'd',
            default => 'a',
        };
    }
}
