<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

use App\Services\QuestionBank\Phase1\Phase1ExecutionOrchestrator;

/**
 * Final identity boundary for QuestionIntent. Only the persistent Blueprint
 * identity crosses the Taxonomy → QuestionIntent boundary.
 */
final class QuestionIntentBlueprintIdReceiver
{
    public function __construct(
        private readonly KernelCodeEngine $engine = new KernelCodeEngine(),
        private readonly ?Phase1ExecutionOrchestrator $phase1 = null,
    ) {}

    public function process(string $blueprintId): string
    {
        $this->engine->assignKernelCode($blueprintId);
        if ($this->phase1 !== null) {
            $this->phase1->process($blueprintId);
        }

        return $blueprintId;
    }
}