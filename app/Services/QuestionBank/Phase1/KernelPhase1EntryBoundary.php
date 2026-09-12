<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\KernelBlueprintProvisionedLoader;
use RuntimeException;

/**
 * Phase 1 entry boundary.
 *
 * It deliberately stops at a hydrated, validated persistent Blueprint. The
 * provider-backed generator remains a separate legacy API and is not invoked
 * here.
 */
final class KernelPhase1EntryBoundary
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
                "[Phase1] Blueprint non prêt: identité complète et kernel_code généré requis ({$blueprintId})."
            );
        }

        if (count($blueprint->cognitive_slots) !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new RuntimeException(
                "[Phase1] Les sept slots cognitifs sont requis ({$blueprintId})."
            );
        }

        return $blueprint;
    }
}