<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hydrate un Blueprint complet depuis les projections persistantes ciblées.
 *
 * Cette lecture appartient à l'adaptateur de phase, jamais au harness.
 */
final class KernelBlueprintPersistentLoader
{
    public function __construct(
        private readonly KernelBlueprintCognitiveSlotRepository $slots =
            new KernelBlueprintCognitiveSlotRepository(),
    ) {}

    public function load(string $blueprintId): KernelBlueprint
    {
        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->first();
        $assignment = DB::table('taxonomy_v11_blueprint_assignments')
            ->where('blueprint_id', $blueprintId)->first();

        if ($run === null || $assignment === null
            || $run->depth === null || $run->domain_code === null || $run->kernel_code === null) {
            throw new RuntimeException("Blueprint persistant incomplet ou introuvable: {$blueprintId}.");
        }
        if ((string) $run->execution_state !== 'ENGAGED_IN_PIPELINE') {
            throw new RuntimeException("Blueprint persistant non engagé pour Phase 1: {$blueprintId}.");
        }
        if ((int) $run->depth !== (int) $assignment->depth
            || (string) $run->domain_code !== (string) $assignment->domain_code) {
            throw new RuntimeException("Blueprint persistant divergent run/Taxonomy: {$blueprintId}.");
        }

        $slots = $this->slots->allForBlueprint($blueprintId);
        if (count($slots) !== count(KernelBlueprint::COGNITIVE_TYPES)
            || array_diff(KernelBlueprint::COGNITIVE_TYPES, array_keys($slots)) !== []) {
            throw new RuntimeException("Blueprint persistant sans les sept slots officiels: {$blueprintId}.");
        }

        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($blueprintId);
        $blueprint->fillRotation((int) $run->depth, (string) $run->domain_code);
        $blueprint->fillTaxonomy(
            (string) $assignment->subdomain_active,
            (string) $assignment->subject_active,
            (string) $assignment->dominant_idea_active,
        );
        $blueprint->fillKernelCode((string) $run->kernel_code);
        $blueprint->initializeCognitiveSlots($slots);

        if (! $blueprint->isComplete()) {
            throw new RuntimeException("Blueprint persistant sans identité complète: {$blueprintId}.");
        }

        return $blueprint;
    }
}