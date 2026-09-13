<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use Illuminate\Support\Facades\DB;

final class KernelCurrentKernelDispatchRepository
{
    public function createIfAbsent(
        string $eventId,
        string $blueprintId,
        string $route,
        ?string $copyId = null,
        ?int $copyVersion = null
    ): object {
        $existing = DB::table('kernel_current_kernel_dispatches')
            ->where('event_id', $eventId)->first();
        if ($existing !== null) {
            return $existing;
        }
        try {
            $order = $route === KernelCurrentKernelReceivedRouter::QUARANTINE
                ? (int) DB::table('kernel_current_kernel_dispatches')->max('ready_order') + 1
                : null;
            DB::table('kernel_current_kernel_dispatches')->insert([
                'event_id' => $eventId,
                'blueprint_id' => $blueprintId,
                'direction' => $route,
                'copy_id' => $copyId,
                'copy_version' => $copyVersion,
                'ready_order' => $order,
                'state' => 'READY',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // A concurrent event with the same id won the unique key.
        }
        return DB::table('kernel_current_kernel_dispatches')->where('event_id', $eventId)->first();
    }

    /** @return object|null */
    public function oldestReady(bool $lock = false): ?object
    {
        $query = DB::table('kernel_current_kernel_dispatches')
            ->where('state', 'READY')->orderBy('ready_order')->orderBy('event_id');
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first();
    }

    public function markDone(string $eventId): void
    {
        DB::table('kernel_current_kernel_dispatches')->where('event_id', $eventId)->update([
            'state' => 'DONE', 'updated_at' => now(),
        ]);
    }
}