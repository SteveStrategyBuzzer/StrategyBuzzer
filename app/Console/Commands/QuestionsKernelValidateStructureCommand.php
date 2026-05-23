<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionIntent;
use App\Services\QuestionBank\KernelFrameValidator;

/**
 * questions:kernel:validate-structure {intent_id}
 *
 * PHASE 1 — Étape 2 : validation structurelle de frame_en.
 *
 * Ce que cette commande fait :
 *   1. Lit frame_en depuis question_intents
 *   2. Appelle KernelFrameValidator::validateStructure()
 *   3. Si OK  → frame_status = 'awaiting_content'
 *   4. Si KO  → frame_status reste 'draft', affiche les erreurs détaillées
 *
 * Ce que cette commande NE fait PAS :
 *   - Ne génère aucun contenu EN
 *   - Ne traduit pas
 *   - Ne touche pas question_groups
 *   - Ne touche pas ready_bank
 *   - Ne touche pas le worker
 *   - Ne touche pas le gameplay
 */
class QuestionsKernelValidateStructureCommand extends Command
{
    protected $signature = 'questions:kernel:validate-structure
        {intent_id : ID du QuestionIntent à valider}';

    protected $description = 'PHASE 1 Étape 2 — Valide structurellement frame_en et avance frame_status à awaiting_content.';

    public function handle(KernelFrameValidator $validator): int
    {
        $intentId = (int) $this->argument('intent_id');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   PHASE 1 — Étape 2 : Validation structure frame_en            ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');

        // ── 1. Charger le noyau ───────────────────────────────────────────────
        $intent = QuestionIntent::find($intentId);

        if (! $intent) {
            $this->error("QuestionIntent #{$intentId} introuvable.");
            return self::FAILURE;
        }

        $this->line("  Noyau        : #{$intent->id}");
        $this->line("  intent_key   : {$intent->intent_key}");
        $this->line("  semantic_key : " . ($intent->semantic_key ?? '<fg=yellow>non défini</>'));
        $this->line("  frame_status : " . ($intent->frame_status ?? '<fg=yellow>NULL</>'));
        $this->line('');

        // ── 2. Vérifier que frame_en existe ───────────────────────────────────
        $rawFrameEn = DB::table('question_intents')
            ->where('id', $intent->id)
            ->value('frame_en');

        if ($rawFrameEn === null) {
            $this->error("frame_en est NULL sur ce noyau. Relancer questions:kernel:skeleton {$intentId} d'abord.");
            return self::FAILURE;
        }

        $frame = json_decode($rawFrameEn, true);

        if (! is_array($frame)) {
            $this->error("frame_en n'est pas un JSON valide.");
            return self::FAILURE;
        }

        // ── 3. Exécuter la validation structurelle ────────────────────────────
        $result = $validator->validateStructure($frame);

        $variantCount = $result['summary']['variant_count'];
        $slotCount    = $result['summary']['translation_slot_count'];

        $this->line("  Variants détectés  : {$variantCount}");
        $this->line("  Slots détectés     : {$slotCount}");
        $this->line('');

        // ── 4. Afficher warnings ──────────────────────────────────────────────
        if (! empty($result['warnings'])) {
            $this->line('<fg=yellow>⚠  WARNINGS (' . count($result['warnings']) . ') — non bloquants :</>');
            foreach ($result['warnings'] as $w) {
                $this->line("  <fg=yellow>·</> {$w}");
            }
            $this->line('');
        }

        // ── 5. Afficher erreurs ───────────────────────────────────────────────
        if (! empty($result['errors'])) {
            $this->line('<fg=red>✗  ERREURS (' . count($result['errors']) . ') — bloquantes :</>');
            foreach ($result['errors'] as $e) {
                $this->line("  <fg=red>·</> {$e}");
            }
            $this->line('');
        }

        // ── 6. Décision ───────────────────────────────────────────────────────
        if ($result['ok']) {
            DB::table('question_intents')
                ->where('id', $intent->id)
                ->update([
                    'frame_status' => 'awaiting_content',
                    'updated_at'   => now(),
                ]);

            $this->info("✅  Structure valide — frame_status = awaiting_content");
            $this->line('');
            $this->line("  variants              : {$variantCount} / 5  ✅");
            $this->line("  translation_slots     : {$slotCount} / 45 ✅");

            if (! empty($result['warnings'])) {
                $this->line("  warnings              : " . count($result['warnings']) . " (non bloquants)");
            }

        } else {
            $this->error("✗  Structure invalide (" . count($result['errors']) . " erreur(s)) — frame_status reste 'draft'");
            $this->line('  Corriger les erreurs ci-dessus puis relancer questions:kernel:skeleton {intent_id} --force');
            $this->line('  suivi de questions:kernel:validate-structure {intent_id}');
        }

        $this->line('');

        // ── 7. Confirmation aucun question_group touché ───────────────────────
        $groupsCount = DB::table('question_groups')
            ->where('question_intent_id', $intent->id)
            ->count();

        $this->line("  question_groups liés (inchangés) : {$groupsCount}");
        $this->line('');

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
