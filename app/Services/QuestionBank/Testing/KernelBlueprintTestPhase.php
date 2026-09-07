<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

/**
 * Contrat local au harness de test manuel.
 *
 * Ce contrat n'est pas le contrat des phases de production. Une implémentation
 * de test reçoit seulement l'identifiant persistant. Une phase adaptatrice
 * charge elle-même le vrai Blueprint lorsqu'elle en a besoin; le harness ne
 * matérialise jamais l'agrégat.
 */
interface KernelBlueprintTestPhase
{
    public function run(string $blueprintId): void;
}