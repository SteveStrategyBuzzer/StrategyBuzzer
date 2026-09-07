<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

/** Terminal substituable pour les tests; aucune écriture persistante. */
final class InMemoryKernelBlueprintTerminalSink implements KernelBlueprintTerminalSink
{
    /** @var array<string, array<string, mixed>> */
    private array $results = [];

    public function accept(string $blueprintId, array $result): void
    {
        $this->results[$blueprintId] = $result;
    }

    /** @return array<string, mixed>|null */
    public function resultFor(string $blueprintId): ?array
    {
        return $this->results[$blueprintId] ?? null;
    }
}