<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Official KBP-to-Rotation lookup for a newly provisioned, empty Blueprint.
 */
final class KernelBlueprintProvisionedLoader
{
    public function __construct(
        private readonly KernelBlueprintCognitiveSlotRepository $slots =
            new KernelBlueprintCognitiveSlotRepository(),
    ) {}

    public function loadCreated(string $blueprintId): KernelBlueprint
    {
        return $this->load($blueprintId, 'CREATED_UNENGAGED');
    }

    public function loadCreatedForUpdate(string $blueprintId): KernelBlueprint
    {
        return $this->load($blueprintId, 'CREATED_UNENGAGED', true);
    }

    public function loadEngaged(string $blueprintId): KernelBlueprint
    {
        return $this->load($blueprintId, 'ENGAGED_IN_PIPELINE');
    }

    public function executionState(string $blueprintId): string
    {
        $bound = DB::table('kernel_blueprint_request_refs')
            ->where('blueprint_id', $blueprintId)
            ->exists();
        if (! $bound) {
            throw new RuntimeException("[KBP] Binding technique absent: {$blueprintId}.");
        }

        $state = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)
            ->value('execution_state');
        if (! is_string($state)) {
            throw new RuntimeException("[KBP] Blueprint provisionné introuvable: {$blueprintId}.");
        }

        return $state;
    }

    private function load(
        string $blueprintId,
        string $expectedState,
        bool $lockForUpdate = false,
    ): KernelBlueprint
    {
        $this->executionState($blueprintId);

        $query = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $run = $query->first();

        if ($run === null || $run->execution_state !== $expectedState) {
            throw new RuntimeException("[KBP] Blueprint provisionné invalide ou introuvable: {$blueprintId}.");
        }
        if ($expectedState === 'CREATED_UNENGAGED'
            && ($run->depth !== null || $run->domain_code !== null)) {
            throw new RuntimeException("[KBP] Blueprint CREATED_UNENGAGED intellectuellement non vide: {$blueprintId}.");
        }
        if ($expectedState === 'ENGAGED_IN_PIPELINE'
            && ($run->depth === null || $run->domain_code === null)) {
            throw new RuntimeException("[KBP] Blueprint ENGAGED_IN_PIPELINE sans Rotation: {$blueprintId}.");
        }

        $slots = $this->slots->allForBlueprint($blueprintId);
        if (count($slots) !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new RuntimeException("[KBP] Blueprint sans les sept slots requis: {$blueprintId}.");
        }
        if ($expectedState === 'CREATED_UNENGAGED') {
            foreach ($slots as $slot) {
                if (($slot['creation_status'] ?? null) !== 'EMPTY'
                    || ($slot['validation_status'] ?? null) !== 'NOT_VALIDATED'
                    || ($slot['creation_failure'] ?? null) !== null
                    || ($slot['translations'] ?? []) !== []
                    || ($slot['validation_findings'] ?? []) !== []
                    || ($slot['source'] ?? null) != KernelBlueprint::emptyCognitiveSlotSource(
                        (string) ($slot['cognitive_type'] ?? '')
                    )) {
                    throw new RuntimeException(
                        "[KBP] Blueprint provisionné avec CognitiveSlot non vide: {$blueprintId}."
                    );
                }
            }
        }

        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($blueprintId);
        if ($expectedState === 'ENGAGED_IN_PIPELINE') {
            $blueprint->fillRotation((int) $run->depth, (string) $run->domain_code);
        }
        $blueprint->initializeCognitiveSlots($slots);

        return $blueprint;
    }
}