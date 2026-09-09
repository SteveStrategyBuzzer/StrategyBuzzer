<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

/**
 * Rotation-to-Taxonomy boundary: the persistent Blueprint identity is the only
 * value allowed to cross the phase boundary.
 */
interface TaxonomyBlueprintIdReceiver
{
    public function process(string $blueprintId): void;
}