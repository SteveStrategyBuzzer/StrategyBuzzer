<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use Illuminate\Support\Facades\DB;

final class KernelQuarantineSlotResumptionRepository
{
    public function upsert(
        string $copyId,
        string $type,
        int $copyVersion,
        int $manualRevision,
        string $stage = 'PHASE1',
        string $requiredOperation = 'VALIDATE'
    ): void {
        $creationRequired = (string) DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $type)
            ->value('creation_status') === 'EMPTY';
        $values = [
            'resumption_number' => 1,
            'copy_version' => $copyVersion,
            'manual_revision' => $manualRevision,
            'phase1_remaining' => true,
            'phase1_creation_required' => $creationRequired,
            'validation_phase1_remaining' => true,
            'phase2_remaining' => false,
            'validation_phase2_remaining' => false,
            'current_stage' => $stage,
            'state' => 'ACTIVE',
            'updated_at' => now(),
        ];
        $exists = DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', $copyId)->where('cognitive_type', $type)->exists();
        if ($exists) {
            $values['resumption_number'] = (int) DB::table('kernel_quarantine_slot_resumptions')
                ->where('copy_id', $copyId)->where('cognitive_type', $type)
                ->value('resumption_number') + 1;
            DB::table('kernel_quarantine_slot_resumptions')
                ->where('copy_id', $copyId)->where('cognitive_type', $type)->update($values);
            return;
        }
        $blueprintId = DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copyId)->value('blueprint_id');
        $values += [
            'blueprint_id' => $blueprintId,
            'copy_id' => $copyId,
            'cognitive_type' => $type,
            'created_at' => now(),
        ];
        DB::table('kernel_quarantine_slot_resumptions')->insert($values);
    }

    public function find(string $copyId, string $type, bool $lock = false): ?object
    {
        $query = DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', $copyId)->where('cognitive_type', $type);
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first();
    }
}