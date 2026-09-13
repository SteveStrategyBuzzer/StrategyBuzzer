<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Quarantine\KernelQuarantineWorkCopySlotRepository;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Quarantine's Phase 1 boundary.  Only identifiers and the claim proof cross
 * this boundary; the complete seven-slot copy is reloaded from persistence.
 */
final class KernelQuarantinePhase1EntryBoundary
{
    public function __construct(
        private readonly KernelQuarantinePhase1ResumptionOrchestrator $orchestrator =
            new KernelQuarantinePhase1ResumptionOrchestrator(),
    ) {}

    public function receive(
        string $blueprintId,
        string $copyId,
        int $claimedCopyVersion,
        string $claimToken,
    ): string {
        $copy = DB::table('kernel_quarantine_work_copies')->where('copy_id', $copyId)->first();
        if ($copy === null
            || (string) $copy->blueprint_id !== $blueprintId
            || (int) $copy->copy_version !== $claimedCopyVersion
            || (string) ($copy->claim_token ?? '') !== $claimToken
            || (string) $copy->state !== 'IN_FLIGHT') {
            throw new LogicException('Entrée Phase 1 Quarantaine périmée (copie ou token).');
        }
        $canonical = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)->first();
        if ($canonical === null
            || ((string) ($canonical->kernel_code ?? '') !== '' &&
                (string) ($canonical->kernel_code ?? '') !== (string) ($copy->kernel_code ?? ''))) {
            throw new LogicException('Identité Blueprint Quarantaine périmée.');
        }
        $slotRepository = new KernelQuarantineWorkCopySlotRepository();
        $slotRepository->assertExactlySeven($copyId);
        $slots = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->get()->keyBy('cognitive_type');
        $indexes = DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', $copyId)->get()->keyBy('cognitive_type');
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            $index = $indexes->get($type);
            if ($index !== null) {
                if ((int) $index->copy_version !== $claimedCopyVersion
                    || (int) $index->manual_revision !== (int) ($slots->get($type)->manual_revision ?? 0)
                    || (int) $index->resumption_number < 1
                    || (string) $index->state !== 'ACTIVE') {
                    throw new LogicException('Indice de reprise Quarantaine périmé.');
                }
            }
        }
        $this->orchestrator->run($blueprintId, $copyId, $claimedCopyVersion, $claimToken);
        return $blueprintId;
    }
}