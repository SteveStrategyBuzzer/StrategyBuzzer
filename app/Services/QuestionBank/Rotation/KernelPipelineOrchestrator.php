<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;

/**
 * KRP orchestration for a Blueprint provisioned by KBP.
 *
 * KRP owns the rotation transaction. Once its Blueprint is committed as
 * engaged, the optional external Taxonomy boundary receives only blueprint_id.
 */
final class KernelPipelineOrchestrator
{
    private const RUNS_TABLE = 'kernel_blueprint_runs';

    public const STATUS_ROTATION_ASSIGNED = 'ROTATION_ASSIGNED';
    public const STATUS_PRODUCTION_ON_HOLD = 'PRODUCTION_ON_HOLD';

    public function __construct(
        private readonly KernelRotationPlanner $planner,
        private readonly KernelRotationStateRepository $stateRepository,
        private readonly KernelBlueprintProvisionedLoader $loader = new KernelBlueprintProvisionedLoader(),
        private readonly ?TaxonomyBlueprintIdReceiver $taxonomyBridge = null,
    ) {}

    /** @return array{status: string, blueprint_id: string|null} */
    public function runProvisioned(string $blueprintId): array
    {
        $state = $this->loader->executionState($blueprintId);
        if ($state === 'ENGAGED_IN_PIPELINE') {
            $this->loader->loadEngaged($blueprintId);
            $this->taxonomyBridge?->process($blueprintId);

            return [
                'status' => self::STATUS_ROTATION_ASSIGNED,
                'blueprint_id' => $blueprintId,
            ];
        }
        if ($state !== 'CREATED_UNENGAGED') {
            throw new \RuntimeException(
                "[KernelPipelineOrchestrator] Blueprint non provisionnable pour Rotation: {$blueprintId}."
            );
        }

        $blueprint = null;

        DB::transaction(function () use (&$blueprint, $blueprintId) {
            $state = $this->stateRepository->firstForUpdate();

            // The only pre-Rotation gate is a previously persisted HOLD.
            if ($this->planner->isProductionOnHold($state)) {
                $this->deleteUnengagedBlueprint($blueprintId);
                return;
            }

            $candidate = $this->loader->loadCreatedForUpdate($blueprintId);
            $resolution = $this->planner->prepareNewBlueprint($candidate, $state);

            if ($resolution->isNoRotation()) {
                // The final pending fact could have completed every need and
                // persisted HOLD. Remove the unused Factory shell in-transaction.
                $this->deleteUnengagedBlueprint($candidate->blueprint_id);
                return;
            }

            $this->engageBlueprint($candidate);
            $blueprint = $candidate;
        });

        if ($blueprint === null) {
            return [
                'status' => self::STATUS_PRODUCTION_ON_HOLD,
                'blueprint_id' => null,
            ];
        }

        // Hors de la transaction KRP : Gemini et l'outbox Taxonomy ne peuvent
        // jamais annuler ou falsifier la décision de rotation déjà engagée.
        $this->taxonomyBridge?->process($blueprintId);

        return [
            'status' => self::STATUS_ROTATION_ASSIGNED,
            'blueprint_id' => $blueprintId,
        ];
    }

    private function deleteUnengagedBlueprint(string $blueprintId): void
    {
        DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('execution_state', 'CREATED_UNENGAGED')
            ->delete();
    }

    private function engageBlueprint(KernelBlueprint $blueprint): void
    {
        $updated = DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->where('execution_state', 'CREATED_UNENGAGED')
            ->whereNull('depth')
            ->whereNull('domain_code')
            ->update([
                'execution_state' => 'ENGAGED_IN_PIPELINE',
                'depth' => $blueprint->depth,
                'domain_code' => $blueprint->domain,
                'engaged_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new \RuntimeException(
                "[KernelPipelineOrchestrator] Écriture Rotation refusée: {$blueprint->blueprint_id}."
            );
        }
    }

}