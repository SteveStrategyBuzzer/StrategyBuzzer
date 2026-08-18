<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\ProcessKernelPipelineOutbox;
use Illuminate\Console\Command;

/**
 * questions:kernel:process-outbox
 *
 * CURRENT_KERNEL_RECEIVED
 * → création du Blueprint suivant
 * → KRP assigne depth + domain
 *
 * Aucun appel direct CURRENT_KERNEL_RECEIVED → KRP.
 */
class QuestionsKernelProcessOutboxCommand extends Command
{
    protected $signature = 'questions:kernel:process-outbox
                            {--batch=10 : Nombre maximum d\'événements traités par exécution}
                            {--dry-run  : Affiche les événements en attente sans les traiter}';

    protected $description = 'Traite CURRENT_KERNEL_RECEIVED et déclenche le prochain Blueprint puis le module 02.';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            return $this->runDryMode();
        }

        $results = $this->buildProcessor()->process($batchSize);

        if (empty($results)) {
            $this->info('[questions:kernel:process-outbox] Aucun événement en attente.');
            return self::SUCCESS;
        }

        foreach ($results as $r) {
            $eventId = $r['event_id'];
            match ($r['outcome']) {
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
                default => $this->warn("[UNKNOWN] event_id={$eventId}"),
            };
        }

        return self::SUCCESS;
    }

    private function runDryMode(): int
    {
        $pending = (new KernelPipelineOutboxRepository())
            ->findPending('CURRENT_KERNEL_RECEIVED', 50);

        if ($pending->isEmpty()) {
            $this->info('[DRY-RUN] Aucun événement en attente.');
            return self::SUCCESS;
        }

        $this->table(
            ['event_id', 'blueprint_id', 'depth', 'domain', 'attempt_count', 'created_at'],
            $pending->map(fn($row) => [
                $row->event_id,
                json_decode($row->payload, true)['blueprint_id'] ?? '—',
                json_decode($row->payload, true)['depth'] ?? '—',
                json_decode($row->payload, true)['domain'] ?? '—',
                $row->attempt_count,
                $row->created_at,
            ])->toArray()
        );

        return self::SUCCESS;
    }

    private function buildProcessor(): ProcessKernelPipelineOutbox
    {
        $planner = new KernelRotationPlanner();
        $orchestrator = new KernelPipelineOrchestrator(
            new KernelBlueprintFactory(),
            $planner,
        );

        return new ProcessKernelPipelineOutbox(
            $orchestrator,
            new KernelPipelineOutboxRepository(),
        );
    }
}
