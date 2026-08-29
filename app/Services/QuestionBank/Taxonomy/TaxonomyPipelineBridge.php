<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Frontière externe Taxonomy → KRP.
 *
 * Taxonomy ne reçoit aucune information de rotation et n'appelle KRP que par
 * receiveTaxonomyTerminalFact(). La livraison est tirée d'un outbox Taxonomy
 * persistant afin qu'un échec KRP ne puisse jamais effacer le fait terminal.
 */
final class TaxonomyPipelineBridge
{
    public function __construct(
        private readonly TaxonomyOrchestrator $taxonomy,
        private readonly TaxonomyBankRepository $repo,
        private readonly KernelRotationPlanner $planner,
        private readonly KernelCodeEngine $kernelCodeEngine,
    ) {}

    public function process(KernelBlueprint $blueprint): KernelBlueprint
    {
        $this->taxonomy->assignToBlueprint($blueprint);
        $this->kernelCodeEngine->assignKernelCode($blueprint);
        $this->deliverPendingTerminalFacts();

        return $blueprint;
    }

    /**
     * Reprend l'unique Blueprint engagé si un appel précédent a échoué après
     * l'engagement KRP et avant (ou pendant) l'attribution Taxonomy.
     */
    public function resumeActiveBlueprint(): ?KernelBlueprint
    {
        $run = DB::table('kernel_blueprint_runs')
            ->where('execution_state', 'ENGAGED_IN_PIPELINE')
            ->orderByDesc('created_at')
            ->first();

        if ($run === null) {
            return null;
        }

        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId((string) $run->blueprint_id);
        $blueprint->fillRotation((int) $run->depth, (string) $run->domain_code);

        return $this->process($blueprint);
    }

    private function deliverPendingTerminalFacts(): void
    {
        foreach ($this->repo->pendingV11TerminalFacts() as $fact) {
            try {
                $this->planner->receiveTaxonomyTerminalFact(
                    (string) $fact->fact_id,
                    (int) $fact->depth,
                    (string) $fact->domain_code,
                );

                $this->repo->markV11TerminalFactDelivered((int) $fact->id);
            } catch (\Throwable $exception) {
                // La livraison KRP est rejouable; l'occurrence reste terminale
                // mais son outbox ne peut pas être perdue.
                $this->repo->recordV11TerminalDeliveryFailure(
                    (int) $fact->id,
                    $exception->getMessage(),
                );

                Log::warning('[TaxonomyPipelineBridge] Livraison terminale KRP à rejouer', [
                    'fact_id' => $fact->fact_id,
                    'depth' => $fact->depth,
                    'domain' => $fact->domain_code,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}