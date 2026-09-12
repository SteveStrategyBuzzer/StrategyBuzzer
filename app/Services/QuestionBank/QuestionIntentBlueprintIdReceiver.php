<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

/**
 * Final identity boundary for QuestionIntent. Only the persistent Blueprint
 * identity crosses the Taxonomy → QuestionIntent boundary.
 */
final class QuestionIntentBlueprintIdReceiver
{
    public function __construct(
        private readonly KernelCodeEngine $engine = new KernelCodeEngine(),
    ) {}

    public function process(string $blueprintId): string
    {
        $this->engine->assignKernelCode($blueprintId);

        return $blueprintId;
    }
}