<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\TaxonomyBlueprintIdReceiver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Frontière externe Taxonomy → KRP.
 *
 * Taxonomy ne reçoit aucune information de rotation et n'appelle KRP que par
 * receiveTaxonomyTerminalFact(). La livraison est tirée d'un outbox Taxonomy
 * persistant afin qu'un échec KRP ne puisse jamais effacer le fait terminal.
 */
final class TaxonomyPipelineBridge implements TaxonomyBlueprintIdReceiver
{
    public function __construct(
        private readonly TaxonomyOrchestrator $taxonomy,
        private readonly TaxonomyBankRepository $repo,
        private readonly KernelRotationPlanner $planner,
        private readonly KernelCodeEngine $kernelCodeEngine,
        private readonly KernelBlueprintCognitiveSlotRepository $slots =
            new KernelBlueprintCognitiveSlotRepository(),
    ) {}

    public function process(string $blueprintId): void
    {
        $this->processOpenedBlueprint($this->openPersistentBlueprint($blueprintId));
    }

    private function processOpenedBlueprint(KernelBlueprint $blueprint): KernelBlueprint
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

        return $this->processOpenedBlueprint($blueprint);
    }

    /**
     * Reprend exclusivement l'identité fournie par KBP; ne recherche jamais un
     * autre Blueprint actif.
     */
    public function resumeBlueprint(KernelBlueprint $blueprint): KernelBlueprint
    {
        return $this->processOpenedBlueprint($blueprint);
    }

    private function openPersistentBlueprint(string $blueprintId): KernelBlueprint
    {
        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)
            ->first();

        if ($run === null
            || $run->execution_state !== 'ENGAGED_IN_PIPELINE'
            || $run->depth === null
            || $run->domain_code === null) {
            throw new RuntimeException(
                "[TaxonomyPipelineBridge] Blueprint Rotation introuvable ou incomplet: {$blueprintId}."
            );
        }

        $slots = $this->slots->allForBlueprint($blueprintId);
        if (count($slots) !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new RuntimeException(
                "[TaxonomyPipelineBridge] Blueprint sans les sept CognitiveSlots: {$blueprintId}."
            );
        }

        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($blueprintId);
        $blueprint->fillRotation((int) $run->depth, (string) $run->domain_code);
        $blueprint->initializeCognitiveSlots($slots);

        return $blueprint;
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