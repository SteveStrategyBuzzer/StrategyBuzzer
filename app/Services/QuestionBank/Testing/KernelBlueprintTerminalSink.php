<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

/**
 * Port de relais externe explicite du résultat terminal KBP, sans
 * orchestrateur ni cascade. L'adaptateur remet exactement le résultat réel
 * retourné par le générateur à cette frontière substituable.
 */
interface KernelBlueprintTerminalSink
{
    /** @param array<string, mixed> $result */
    public function accept(string $blueprintId, array $result): void;
}