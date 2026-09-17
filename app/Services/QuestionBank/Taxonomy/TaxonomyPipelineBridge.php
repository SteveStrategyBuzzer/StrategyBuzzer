<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use App\Services\QuestionBank\QuestionIntentBlueprintIdReceiver;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\KernelBlueprintRunRepository;
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
        private readonly QuestionIntentBlueprintIdReceiver $questionIntent,
        private readonly KernelBlueprintCognitiveSlotRepository $slots =
            new KernelBlueprintCognitiveSlotRepository(),
        private readonly KernelBlueprintRunRepository $runs =
            new KernelBlueprintRunRepository(),
    ) {}

    public function process(string $blueprintId): void
    {
        $this->processOpenedBlueprint($this->openPersistentBlueprint($blueprintId));
    }

    private function processOpenedBlueprint(KernelBlueprint $blueprint): KernelBlueprint
    {
        DB::transaction(function () use ($blueprint): void {
            $this->taxonomy->assignToBlueprint($blueprint);
            $updated = $this->runs->writeTaxonomyProjection(
                (string) $blueprint->blueprint_id,
                [
                    'subdomain_active'     => $blueprint->subdomain_active,
                    'subject_active'       => $blueprint->subject_active,
                    'dominant_idea_active' => $blueprint->dominant_idea_active,
                    'kernel_code_sub'      => $blueprint->kernel_code_sub,
                    'kernel_code_suj'      => $blueprint->kernel_code_suj,
                    'kernel_code_ide'      => $blueprint->kernel_code_ide,
                ],
            );

            if ($updated === 0) {
                $existing = $this->runs->findById((string) $blueprint->blueprint_id);
                if ($existing === null
                    || $existing->subdomain_active !== $blueprint->subdomain_active
                    || $existing->subject_active !== $blueprint->subject_active
                    || $existing->dominant_idea_active !== $blueprint->dominant_idea_active
                    || $existing->kernel_code_sub !== $blueprint->kernel_code_sub
                    || $existing->kernel_code_suj !== $blueprint->kernel_code_suj
                    || $existing->kernel_code_ide !== $blueprint->kernel_code_ide) {
                    throw new RuntimeException(
                        "[TaxonomyPipelineBridge] Taxonomy concurrente ou divergente: {$blueprint->blueprint_id}."
                    );
                }
            }
        });
        $this->questionIntent->process((string) $blueprint->blueprint_id);
        $this->deliverPendingTerminalFacts();

        return $blueprint;
    }

    /**
     * Reprend l'unique Blueprint engagé si un appel précédent a échoué après
     * l'engagement KRP et avant (ou pendant) l'attribution Taxonomy.
     */
    public function resumeActiveBlueprint(): ?KernelBlueprint
    {
        $run = $this->runs->findActive();

        if ($run === null) {
            return null;
        }

        return $this->processOpenedBlueprint($this->openPersistentBlueprint((string) $run->blueprint_id));
    }

    /**
     * Reprend exclusivement l'identité fournie par KBP; ne recherche jamais un
     * autre Blueprint actif.
     */
    public function resumeBlueprint(KernelBlueprint $blueprint): KernelBlueprint
    {
        throw new RuntimeException(
            '[TaxonomyPipelineBridge] Le pont reçoit uniquement blueprint_id; rechargez le Blueprint persistant.'
        );
    }

    private function openPersistentBlueprint(string $blueprintId): KernelBlueprint
    {
        $loader = new \App\Services\QuestionBank\Rotation\KernelBlueprintProvisionedLoader($this->slots);

        try {
            return $loader->loadEngaged($blueprintId);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                "[TaxonomyPipelineBridge] Blueprint Rotation introuvable ou incomplet: {$blueprintId}.",
                previous: $exception,
            );
        }
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