<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Taxonomy\TaxonomyPipelineBridge;
use Illuminate\Support\Facades\DB;

/**
 * KRP v4 Factory -> Blueprint -> KRP orchestration.
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
        private readonly KernelBlueprintFactory $factory,
        private readonly KernelRotationPlanner $planner,
        private readonly KernelRotationStateRepository $stateRepository,
        private readonly ?TaxonomyPipelineBridge $taxonomyBridge = null,
    ) {}

    /**
     * Creates a new Factory Blueprint, lets KRP consume one pending terminal
     * fact, and writes the resulting depth + domain exactly once.
     *
     * @return array{status: string, blueprint: KernelBlueprint|null}
     */
    public function run(?string $previousDomain = null): array
    {
        // Si Taxonomy a échoué après l'engagement KRP, seule la reprise du même
        // Blueprint est autorisée. La rotation ne doit surtout pas être relancée.
        if ($this->taxonomyBridge !== null) {
            $resumedBlueprint = $this->taxonomyBridge->resumeActiveBlueprint();
            if ($resumedBlueprint !== null) {
                return [
                    'status' => self::STATUS_ROTATION_ASSIGNED,
                    'blueprint' => $resumedBlueprint,
                ];
            }
        }

        $blueprint = null;

        DB::transaction(function () use (&$blueprint) {
            $state = $this->stateRepository->firstForUpdate();

            // The only pre-Factory KRP gate is a previously persisted HOLD.
            if ($this->planner->isProductionOnHold($state)) {
                return;
            }

            $candidate = $this->factory->create();
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
}