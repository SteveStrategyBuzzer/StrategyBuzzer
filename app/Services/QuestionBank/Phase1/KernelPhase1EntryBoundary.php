<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\KernelBlueprintProvisionedLoader;
use RuntimeException;

/**
 * Phase 1 entry boundary.
 *
 * Only the persistent identifier crosses the phase boundary.
 */
final class KernelPhase1EntryBoundary
{
    public function __construct(
        private readonly KernelBlueprintProvisionedLoader $loader =
            new KernelBlueprintProvisionedLoader(),
    ) {}

    public function receive(
        string $blueprintId,
        ?string $copyId = null,
        ?int $claimedCopyVersion = null,
        ?string $claimToken = null,
    ): string
    {
        if ($copyId !== null || $claimedCopyVersion !== null || $claimToken !== null) {
            if ($copyId === null || $claimedCopyVersion === null || $claimToken === null) {
                throw new RuntimeException('[Phase1] Preuve Quarantaine incomplète.');
            }
            return (new KernelQuarantinePhase1EntryBoundary())->receive(
                $blueprintId,
                $copyId,
                $claimedCopyVersion,
                $claimToken,
            );
        }
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

        return $blueprintId;
    }
}