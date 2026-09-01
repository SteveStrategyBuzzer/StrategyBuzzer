<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

use App\Services\QuestionBank\KernelBlueprint;

/**
 * Contrat local au harness de test manuel.
 *
 * Ce contrat n'est pas le contrat des phases de production. Une implémentation
 * de test reçoit le vrai Blueprint et décide elle-même quoi observer; le
 * harness n'enchaîne jamais une phase supplémentaire.
 */
interface KernelBlueprintTestPhase
{
    public function run(KernelBlueprint $blueprint): void;
}