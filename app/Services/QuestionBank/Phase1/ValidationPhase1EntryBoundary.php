<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\KernelBlueprintProvisionedLoader;
use RuntimeException;

final class ValidationPhase1EntryBoundary
{
    public function __construct(
        private readonly KernelBlueprintProvisionedLoader $loader =
            new KernelBlueprintProvisionedLoader(),
    ) {}

    public function receive(string $blueprintId): KernelBlueprint
    {
        $blueprint = $this->loader->loadEngaged($blueprintId);

        if (! $blueprint->isComplete()) {
            throw new RuntimeException(
                "[ValidationPhase1] Blueprint incomplet: {$blueprintId}."
            );
        }

        if (count($blueprint->cognitive_slots) !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new RuntimeException(
                "[ValidationPhase1] Les sept CognitiveSlots sont requis: {$blueprintId}."
            );
        }

        foreach ($blueprint->cognitive_slots as $slot) {
            if (($slot['creation_status'] ?? null) !== 'CREATED') {
                throw new RuntimeException(
                    "[ValidationPhase1] CognitiveSlot non créé: {$blueprintId}."
                );
            }
        }

        return $blueprint;
    }
}