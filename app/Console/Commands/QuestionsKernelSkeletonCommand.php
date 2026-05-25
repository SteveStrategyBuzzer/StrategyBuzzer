<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionIntent;
use App\Services\QuestionBank\KernelFrameBuilder;

/**
 * questions:kernel:skeleton {intent_id}
 *
 * PHASE 1 — Étape 1 : créer le squelette complet frame_en pour 1 noyau.
 *
 * Ce que cette commande fait :
 *   1. Charge le QuestionIntent demandé
 *   2. Appelle KernelFrameBuilder::buildSkeleton()
 *   3. Écrit frame_en + frame_status = 'draft' dans question_intents
 *
 * Ce que cette commande NE fait PAS :
 *   - Ne touche pas question_groups
 *   - Ne touche pas question_translations
 *   - Ne touche pas post_review_status / ready_bank
 *   - Ne génère pas de contenu EN
 *   - Ne traduit pas
 *   - Ne modifie pas le worker
 */
class QuestionsKernelSkeletonCommand extends Command
{
    protected $signature = 'questions:kernel:skeleton
        {intent_id : ID du QuestionIntent à squelettiser}
        {--force   : Écraser un frame_en existant}
        {--dry-run : Afficher le JSON sans écrire en DB}';

    protected $description = 'PHASE 1 Étape 1 — Construit le squelette frame_en (draft) pour 1 noyau.';

    public function handle(KernelFrameBuilder $builder): int
    {
        $intentId = (int) $this->argument('intent_id');
        $force    = (bool) $this->option('force');
        $dryRun   = (bool) $this->option('dry-run');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   PHASE 1 — Étape 1 : Skeleton frame_en                        ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');

        // ── 1. Charger le noyau ───────────────────────────────────────────────
        $intent = QuestionIntent::find($intentId);

        if (! $intent) {
            $this->error("QuestionIntent #{$intentId} introuvable.");
            return self::FAILURE;
        }

        $this->line("  Noyau       : #{$intent->id}");
        $this->line("  intent_key  : {$intent->intent_key}");
        $this->line("  semantic_key: " . ($intent->semantic_key ?? '<fg=yellow>non défini</>'));
        $this->line("  domain      : {$intent->domain} / {$intent->sub_domain}");
        $this->line("  depth       : {$intent->difficulty_depth}");
        $this->line("  frame_status: " . ($intent->frame_status ?? 'NULL'));
        $this->line('');

        // ── 2. Refus si frame_en déjà présent sans --force ────────────────────
        if ($intent->frame_en !== null && ! $force) {
            $this->warn("frame_en déjà présent sur ce noyau (frame_status={$intent->frame_status}).");
            $this->warn("Relancer avec --force pour écraser.");
            return self::FAILURE;
        }

        // ── 3. Construire le squelette ────────────────────────────────────────
        $skeleton = $builder->buildSkeleton($intent);

        // ── 4. Validation de structure minimale avant écriture ────────────────
        $variantCount = count($skeleton['variants'] ?? []);
        $slotCount    = 0;
        foreach ($skeleton['variants'] as $v) {
            $slotCount += count($v['translation_slots'] ?? []);
        }

        $this->line("  Variantes générées  : {$variantCount} / 7 attendues");
        $this->line("  Translation slots   : {$slotCount} / 63 attendus");
        $this->line('');

        if ($variantCount !== 7) {
            $this->error("ERREUR : {$variantCount} variantes au lieu de 7. Abandon.");
            return self::FAILURE;
        }

        if ($slotCount !== 63) {
            $this->error("ERREUR : {$slotCount} slots au lieu de 63. Abandon.");
            return self::FAILURE;
        }

        // ── 5. Dry-run : afficher et sortir ───────────────────────────────────
        if ($dryRun) {
            $this->line('<fg=yellow>[DRY-RUN]</> JSON compact :');
            $this->line('');
            $this->printStructureSummary($skeleton);
            $this->line('');
            $this->info('[DRY-RUN] Aucune écriture DB effectuée.');
            return self::SUCCESS;
        }

        // ── 6. Écriture DB ────────────────────────────────────────────────────
        DB::table('question_intents')
            ->where('id', $intent->id)
            ->update([
                'frame_en'     => json_encode($skeleton, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'frame_status' => 'draft',
                'updated_at'   => now(),
            ]);

        $this->info("✅  frame_en écrit — frame_status = draft");
        $this->line('');

        // ── 7. Résumé structure ───────────────────────────────────────────────
        $this->printStructureSummary($skeleton);
        $this->line('');

        // ── 8. Confirmation aucun question_group touché ───────────────────────
        $groupsAfter = DB::table('question_groups')
            ->where('question_intent_id', $intent->id)
            ->count();

        $this->line("  question_groups liés (inchangés) : {$groupsAfter}");
        $this->line('');
        $this->info("LOT P1-A terminé pour le noyau #{$intent->id}.");

        return self::SUCCESS;
    }

    private function printStructureSummary(array $skeleton): void
    {
        $this->line('  <fg=cyan>kernel_core :</> ' . implode(' / ', array_filter([
            $skeleton['kernel_core']['domain'] ?? null,
            $skeleton['kernel_core']['sub_domain'] ?? null,
            'depth=' . ($skeleton['kernel_core']['difficulty_depth'] ?? '?'),
            $skeleton['kernel_core']['semantic_key'] ?? null,
        ])));

        $this->line('');
        $this->line('  <fg=cyan>translation_constraints :</> ' . count($skeleton['translation_constraints']) . ' langues');

        $langs = array_keys($skeleton['translation_constraints']);
        $sample = $skeleton['translation_constraints'][$langs[0]] ?? [];
        $this->line('    ' . $langs[0] . ' : q_max=' . ($sample['question_max_length'] ?? '?')
            . ' a_max=' . ($sample['answer_max_length'] ?? '?')
            . ' sv_max=' . ($sample['funFact_max_length'] ?? '?')
            . ' sv_min=' . ($sample['funFact_min_length'] ?? '?'));

        $this->line('');
        $this->line('  <fg=cyan>variants :</> 7 variantes');

        foreach ($skeleton['variants'] as $key => $v) {
            $slotCount = count($v['translation_slots'] ?? []);
            $contract  = $v['cognitive_contract'] ?? [];
            $contractKeys = count($contract);
            $this->line("    <fg=green>{$key}</> [{$v['question_type']} / {$v['cognitive_type']}]"
                . " · {$slotCount} slots · contract={$contractKeys} champs");
        }
    }
}
