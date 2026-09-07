<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

/**
 * Harness isolé pour un essai manuel ciblé sur un Blueprint.
 *
 * Le graphe est volontairement fermé :
 *   fixture isolée (Factory + projections) → phase id-only → nettoyage.
 *
 * Il ne matérialise aucun Blueprint et n'instancie ni orchestrateur, ni KRP,
 * ni Taxonomy, ni outbox, ni queue.
 * Le callback d'inspection reste dans le bloc try/finally afin qu'une assertion
 * échouée déclenche elle aussi la suppression du parent et de ses slots.
 */
final class KernelBlueprintTestHarness
{
    public function __construct(
        private readonly KernelBlueprintFixture $fixture = new KernelBlueprintFixture(),
    ) {}

    /**
     * @template TResult
     *
     * @param callable(string): TResult $inspect
     * @return TResult
     */
    public function execute(
        KernelBlueprintManualPreconditions $preconditions,
        KernelBlueprintTestPhase $phase,
        callable $inspect,
    ): mixed {
        $blueprintId = null;

        try {
            $blueprintId = $this->fixture->create($preconditions);

            // Une seule invocation, sans aucune transition aval.
            $phase->run($blueprintId);

            return $inspect($blueprintId);
        } finally {
            if ($blueprintId !== null) {
                $this->fixture->cleanup($blueprintId);
            }
        }
    }
}