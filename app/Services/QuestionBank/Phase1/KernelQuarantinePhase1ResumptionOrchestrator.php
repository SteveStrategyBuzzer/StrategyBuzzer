<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The quarantine Phase 1 owner.  It intentionally has no KBP/KRP/Taxonomy
 * dependency: it only advances persisted Phase 1 operations which an Admin
 * resumption index explicitly requires.
 */
final class KernelQuarantinePhase1ResumptionOrchestrator
{
    public function __construct(
        private readonly KernelQuarantinePhase1Provider $provider =
            new QuestionApiKernelQuarantinePhase1Provider(),
    ) {}

    public function run(string $blueprintId, string $copyId, int $copyVersion, string $claimToken): void
    {
        DB::transaction(function () use ($blueprintId, $copyId, $copyVersion, $claimToken): void {
            $copy = DB::table('kernel_quarantine_work_copies')
                ->where('copy_id', $copyId)->lockForUpdate()->first();
            if ($copy === null
                || (string) $copy->blueprint_id !== $blueprintId
                || (int) $copy->copy_version !== $copyVersion
                || (string) ($copy->claim_token ?? '') !== $claimToken
                || (string) $copy->state !== 'IN_FLIGHT') {
                throw new LogicException('Reprise Phase 1 Quarantaine périmée.');
            }

            $resumptions = DB::table('kernel_quarantine_slot_resumptions')
                ->where('copy_id', $copyId)->where('state', 'ACTIVE')
                ->lockForUpdate()->get();
            if ($resumptions->isEmpty()) {
                throw new LogicException('Aucune opération Phase 1 de reprise n’est persistée.');
            }

            $creationResumptions = [];
            foreach ($resumptions as $resume) {
                $slot = DB::table('kernel_quarantine_work_copy_slots')
                    ->where('copy_id', $copyId)
                    ->where('cognitive_type', $resume->cognitive_type)
                    ->lockForUpdate()->first();
                if ($slot === null) {
                    throw new LogicException('Indice de reprise sans slot Quarantaine.');
                }
                if ((int) $resume->copy_version !== $copyVersion
                    || (int) $resume->manual_revision !== (int) ($slot->manual_revision ?? 0)) {
                    throw new LogicException('Indice de reprise Phase 1 périmé.');
                }
                if ((bool) ($resume->phase1_creation_required ?? false)
                    || (string) ($slot->creation_status ?? '') === 'EMPTY') {
                    $creationResumptions[] = [$resume, $slot];
                }
            }
            if ($creationResumptions !== []) {
                $slots = DB::table('kernel_quarantine_work_copy_slots')
                    ->where('copy_id', $copyId)->lockForUpdate()->get()
                    ->keyBy('cognitive_type')->all();
                // Provider retries and validates the complete Phase 1 response.
                // It returns data; this owner is the only writer and writes
                // only the copy slots.
                $created = $this->provider->create($copy, $slots);
                foreach ($creationResumptions as [$resume, $slot]) {
                    $source = $created[$resume->cognitive_type] ?? null;
                    if (! is_array($source)) {
                        throw new LogicException('Création Phase 1 sans source persistable.');
                    }
                    $updated = DB::table('kernel_quarantine_work_copy_slots')
                        ->where('copy_id', $copyId)
                        ->where('cognitive_type', $resume->cognitive_type)
                        ->where('slot_revision', $slot->slot_revision)
                        ->update([
                            'source' => json_encode($source, JSON_THROW_ON_ERROR),
                            'creation_failure' => null,
                            'creation_status' => 'CREATED',
                            'validation_status' => 'NOT_VALIDATED',
                            'validation_findings' => '[]',
                            'color' => 'YELLOW',
                            'updated_at' => now(),
                        ]);
                    if ($updated !== 1) {
                        throw new LogicException('La source Phase 1 n’a pas été persistée dans la copie.');
                    }
                }
            }
            foreach ($resumptions as $resume) {
                if ((bool) $resume->phase1_remaining) {
                    DB::table('kernel_quarantine_slot_resumptions')
                        ->where('copy_id', $copyId)
                        ->where('cognitive_type', $resume->cognitive_type)
                        ->where('state', 'ACTIVE')
                        ->where('copy_version', $copyVersion)
                        ->update([
                            'phase1_remaining' => false,
                            'phase1_creation_required' => false,
                            'current_stage' => 'VALIDATION_PHASE1',
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }
}