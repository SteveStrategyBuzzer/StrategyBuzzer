<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;

/**
 * Strict outer adapter for the ReadyBank signal.
 *
 * Event payload data remains outside KBP: only its stable event identity is
 * consumed as the idempotence reference. The sole value crossing onward to
 * Rotation is the newly bound blueprint_id.
 */
final class CurrentKernelReceivedKbpAdapter
{
    public function __construct(
        private readonly KernelBlueprintProvisioner $provisioner,
    ) {}

    public function provisionNextBlueprint(CurrentKernelReceived $event): string
    {
        return $this->provisioner->provisionForCurrentKernelReceived($event);
    }
}