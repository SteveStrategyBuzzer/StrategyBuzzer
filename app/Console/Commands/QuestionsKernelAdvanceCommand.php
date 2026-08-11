<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\Rotation\KernelBlueprintReadyBankReceiver;
use App\Services\QuestionBank\Rotation\KernelBlueprintRunRepository;
use App\Services\QuestionBank\Rotation\KernelPipelineAdvancer;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\QuestionIntentEncoder;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * questions:kernel:advance
 *
 * Tick du flow canonique kernel (2026-08-11) :
 *   1. questions:kernel:process-outbox — RACCORDEMENT B aval :
 *      Taxonomy.confirmConsumed() idempotent + comptabilisation + Blueprint suivant (KRP-R11).
 *   2. KernelPipelineAdvancer — fait avancer le noyau en vol d'UN cran
 *      (Phases 1-2 → réception ReadyBank).
 *
 * Mode --loop : boucle continue pour le workflow « Kernel Pipeline »
 * (même convention que questions:worker).
 *
 * Ne touche AUCUN composant BankWorker.
 * ⛔ KLD / KEY_STRUCTURE : SUPERSEDED — jamais invoqués.
 */
class QuestionsKernelAdvanceCommand extends Command
{
    protected $signature = 'questions:kernel:advance
        {--loop     : Boucle continue — un tick toutes les --sleep secondes}
        {--sleep=60 : Secondes entre deux ticks en mode --loop}';

    protected $description = 'Flow canonique — traite l\'outbox CURRENT_KERNEL_RECEIVED puis fait avancer le noyau en vol (Phases 1-2 → ReadyBank).';

    public function handle(): int
    {
        if (! $this->option('loop')) {
            return $this->tick();
        }

        $sleep = max(5, (int) $this->option('sleep'));
        $this->info("[questions:kernel:advance] Boucle démarrée — un tick toutes les {$sleep}s.");

        while (true) {
            try {
                $this->tick();
            } catch (Throwable $e) {
                $this->error('[questions:kernel:advance] Tick en échec : ' . $e->getMessage());
                Log::error('[questions:kernel:advance] Tick en échec.', ['error' => $e->getMessage()]);
            }

            sleep($sleep);
        }
    }

    private function tick(): int
    {
        // ── 1. Outbox : confirmConsumed + comptage + Blueprint suivant ───────
        $this->call('questions:kernel:process-outbox');

        // ── 2. Faire avancer le noyau en vol ─────────────────────────────────
        $result  = $this->buildAdvancer()->advance();
        $outcome = $result['outcome'];

        $context = collect($result)
            ->except('outcome')
            ->map(fn ($v, $k) => "{$k}={$v}")
            ->implode(' ');

        match ($outcome) {
            KernelPipelineAdvancer::OUTCOME_NO_ACTIVE    => $this->line('[advance] Aucun Blueprint actif.'),
            KernelPipelineAdvancer::OUTCOME_RECEIVED     => $this->info("[advance] Noyau reçu en ReadyBank — {$context}"),
            KernelPipelineAdvancer::OUTCOME_QUARANTINED,
            KernelPipelineAdvancer::OUTCOME_BLOCKED      => $this->error("[advance] {$outcome} — {$context}"),
            KernelPipelineAdvancer::OUTCOME_STAGE_FAILED => $this->warn("[advance] {$outcome} — {$context}"),
            default                                      => $this->line("[advance] {$outcome} — {$context}"),
        };

        return self::SUCCESS;
    }

    private function buildAdvancer(): KernelPipelineAdvancer
    {
        $taxonomy = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            new TaxonomyGeminiClient(),
            new ValidationDominantIdeas(),
        );

        return new KernelPipelineAdvancer(
            new KernelBlueprintRunRepository(),
            $taxonomy,
            new QuestionIntentEncoder(),
            new KernelBlueprintReadyBankReceiver(
                new KernelBlueprintRunRepository(),
                new KernelPipelineOutboxRepository(),
            ),
        );
    }
}
