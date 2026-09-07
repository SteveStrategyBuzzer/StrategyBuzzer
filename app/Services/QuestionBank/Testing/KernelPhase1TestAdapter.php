<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

use App\Services\QuestionBank\Phase1\KernelPhase1Generator;

/**
 * Adaptateur id-only de Phase 1 pour le harness externe.
 */
final class KernelPhase1TestAdapter implements KernelBlueprintTestPhase
{
    public function __construct(
        private readonly KernelBlueprintPersistentLoader $loader,
        private readonly KernelPhase1Generator $generator,
        private readonly KernelBlueprintTerminalSink $terminalSink,
    ) {}

    public function run(string $blueprintId): void
    {
        $blueprint = $this->loader->load($blueprintId);
        $result = $this->generator->generate($blueprint);
        $this->terminalSink->accept($blueprintId, $result);
    }
}