<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Phase1\KernelPhase1Generator;
use App\Services\QuestionBank\Taxonomy\TaxonomyPipelineBridge;
use Illuminate\Support\Facades\DB;

/**
 * KRP orchestration for a Blueprint provisioned by KBP.
 *
 * KRP owns the rotation transaction. Once its Blueprint is committed as
 * engaged, the optional external Taxonomy bridge consumes depth + domain only.
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
        private readonly ?TaxonomyPipelineBridge $taxonomyBridge = null,
        private readonly ?KernelPhase1Generator $phase1 = null,
    ) {}

    /** @return array{status: string, blueprint: KernelBlueprint|null} */
    public function runProvisioned(string $blueprintId): array
    {
        $state = $this->loader->executionState($blueprintId);
        if ($state === 'ENGAGED_IN_PIPELINE') {
            $engaged = $this->loader->loadEngaged($blueprintId);
            if ($this->taxonomyBridge !== null) {
                $engaged = $this->taxonomyBridge->resumeBlueprint($engaged);
            }
            $this->runPhase1IfReady($engaged);

            return [
                'status' => self::STATUS_ROTATION_ASSIGNED,
                'blueprint' => $engaged,
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

            $candidate = $this->loader->loadCreated($blueprintId);
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
                'blueprint' => null,
            ];
        }

        // Hors de la transaction KRP : Gemini et l'outbox Taxonomy ne peuvent
        // jamais annuler ou falsifier la décision de rotation déjà engagée.
        $this->taxonomyBridge?->process($blueprint);
        $this->runPhase1IfReady($blueprint);

        return [
            'status' => self::STATUS_ROTATION_ASSIGNED,
            'blueprint' => $blueprint,
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
        DB::table(self::RUNS_TABLE)
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->update([
                'execution_state' => 'ENGAGED_IN_PIPELINE',
                'depth' => $blueprint->depth,
                'domain_code' => $blueprint->domain,
                'engaged_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function runPhase1IfReady(KernelBlueprint $blueprint): void
    {
        if ($this->phase1 === null) {
            return;
        }

        if (! $blueprint->isComplete()) {
            throw new \RuntimeException(
                '[KernelPipelineOrchestrator] Phase 1 exige Taxonomy et kernel_code complets.'
            );
        }

        $this->phase1->generate($blueprint);
    }
}