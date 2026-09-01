<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use Illuminate\Support\Facades\DB;

/**
 * Harness isolé pour un essai manuel ciblé sur un Blueprint.
 *
 * Le graphe est volontairement fermé :
 *   Factory → préconditions locales → phase simulée → nettoyage.
 *
 * Il n'instancie ni orchestrateur, ni KRP, ni Taxonomy, ni outbox, ni queue.
 * Le callback d'inspection reste dans le bloc try/finally afin qu'une assertion
 * échouée déclenche elle aussi la suppression du parent et de ses slots.
 */
final class KernelBlueprintTestHarness
{
    private const RUNS_TABLE = 'kernel_blueprint_runs';

    public function __construct(
        private readonly KernelBlueprintFactory $factory = new KernelBlueprintFactory(),
    ) {}

    /**
     * @template TResult
     *
     * @param callable(KernelBlueprint): TResult $inspect
     * @return TResult
     */
    public function execute(
        KernelBlueprintManualPreconditions $preconditions,
        KernelBlueprintTestPhase $phase,
        callable $inspect,
    ): mixed {
        $blueprint = null;

        try {
            $blueprint = $this->factory->create();
            $preconditions->applyTo($blueprint);
            $this->persistManualIdentity($blueprint);

            // Une seule invocation, sans aucune transition aval.
            $phase->run($blueprint);

            return $inspect($blueprint);
        } finally {
            if ($blueprint instanceof KernelBlueprint && $blueprint->blueprint_id !== null) {
                $this->deleteBlueprint($blueprint->blueprint_id);
            }
        }
    }

    private function persistManualIdentity(KernelBlueprint $blueprint): void
    {
        if ($blueprint->blueprint_id === null || ! $blueprint->isComplete()) {
            throw new \LogicException(
                'Le harness ne peut persister que les préconditions d’un Blueprint complet.',
            );
        }

        $updated = DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->where('execution_state', 'CREATED_UNENGAGED')
            ->update([
                'depth' => $blueprint->depth,
                'domain_code' => $blueprint->domain,
                'kernel_code' => $blueprint->kernel_code,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new \RuntimeException(
                'Le parent Factory du Blueprint manuel est introuvable ou n’est plus CREATED_UNENGAGED.',
            );
        }
    }

    private function deleteBlueprint(string $blueprintId): void
    {
        DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprintId)
            ->delete();
    }
}