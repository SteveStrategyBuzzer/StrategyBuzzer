<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\Listeners\ApplyCurrentKernelReceivedToRotation;
use App\Services\QuestionBank\Rotation\ProcessKernelPipelineOutbox;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Console\Command;

/**
 * questions:kernel:process-outbox
 *
 * Traite les événements CURRENT_KERNEL_RECEIVED en attente dans kernel_pipeline_outbox.
 *
 * Pour chaque événement :
 *   1. Comptabilisation idempotente (ApplyCurrentKernelReceivedToRotation::applyCount)
 *   2. Déclenchement du Blueprint suivant (KernelPipelineOrchestrator::run)
 *   3. Marquage processed_at UNIQUEMENT après succès complet
 *
 * Ne touche aucun composant BankWorker.
 *
 * Si un Scheduler existe, cette commande doit être planifiée avec withoutOverlapping().
 */
class QuestionsKernelProcessOutboxCommand extends Command
{
    protected $signature = 'questions:kernel:process-outbox
                            {--batch=10 : Nombre maximum d\'événements traités par exécution}
                            {--dry-run  : Affiche les événements en attente sans les traiter}';

    protected $description = 'V2 — Traite les événements CURRENT_KERNEL_RECEIVED de l\'Outbox Kernel et déclenche le Blueprint suivant (KRP-R11).';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $dryRun    = (bool) $this->option('dry-run');

        if ($dryRun) {
            return $this->runDryMode();
        }

        $processor = $this->buildProcessor();
        $results   = $processor->process($batchSize);

        if (empty($results)) {
            $this->info('[questions:kernel:process-outbox] Aucun événement en attente.');
            return self::SUCCESS;
        }

        foreach ($results as $r) {
            $outcome = $r['outcome'];
            $eventId = $r['event_id'];

            match ($outcome) {
                ProcessKernelPipelineOutbox::OUTCOME_PROCESSED => $this->info(
                    "[PROCESSED] event_id={$eventId} orchestrator={$r['orchestrator_status']}"
                ),
                ProcessKernelPipelineOutbox::OUTCOME_ERROR => $this->error(
                    "[ERROR] event_id={$eventId} error={$r['error']}"
                ),
                ProcessKernelPipelineOutbox::OUTCOME_NO_OP,
                ProcessKernelPipelineOutbox::OUTCOME_ALREADY_PROCESSED => $this->line(
                    "[NO-OP] event_id={$eventId}"
                ),
                default => $this->warn("[UNKNOWN] event_id={$eventId} outcome={$outcome}"),
            };
        }

        $processed = count(array_filter($results, fn($r) => $r['outcome'] === ProcessKernelPipelineOutbox::OUTCOME_PROCESSED));
        $errors    = count(array_filter($results, fn($r) => $r['outcome'] === ProcessKernelPipelineOutbox::OUTCOME_ERROR));

        $this->info("[questions:kernel:process-outbox] Terminé — {$processed} traité(s), {$errors} erreur(s).");

        return self::SUCCESS;
    }

    // =========================================================================
    // Mode dry-run
    // =========================================================================

    private function runDryMode(): int
    {
        $outboxRepo = new KernelPipelineOutboxRepository();
        $pending    = $outboxRepo->findPending('CURRENT_KERNEL_RECEIVED', 50);

        if ($pending->isEmpty()) {
            $this->info('[DRY-RUN] Aucun événement en attente.');
            return self::SUCCESS;
        }

        $this->table(
            ['event_id', 'blueprint_id', 'depth', 'domain', 'attempt_count', 'created_at'],
            $pending->map(fn($row) => [
                $row->event_id,
                json_decode($row->payload, true)['blueprint_id'] ?? '—',
                json_decode($row->payload, true)['depth']        ?? '—',
                json_decode($row->payload, true)['domain']       ?? '—',
                $row->attempt_count,
                $row->created_at,
            ])->toArray()
        );

        $this->line('[DRY-RUN] ' . $pending->count() . ' événement(s) en attente — aucun traitement effectué.');

        return self::SUCCESS;
    }

    // =========================================================================
    // Construction du processeur
    // =========================================================================

    private function buildProcessor(): ProcessKernelPipelineOutbox
    {
        $outboxRepo  = new KernelPipelineOutboxRepository();
        $listener    = new ApplyCurrentKernelReceivedToRotation();

        $orchestrator = new KernelPipelineOrchestrator(
            new KernelBlueprintFactory(),
            new KernelRotationPlanner(),
            new TaxonomyOrchestrator(
                new TaxonomyBankRepository(),
                new TaxonomyGeminiClient(),
                new ValidationDominantIdeas(),
            ),
        );

        return new ProcessKernelPipelineOutbox($listener, $orchestrator, $outboxRepo);
    }
}
