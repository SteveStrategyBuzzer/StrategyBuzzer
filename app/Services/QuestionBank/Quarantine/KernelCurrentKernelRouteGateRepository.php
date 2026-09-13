<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use Illuminate\Support\Facades\DB;

/**
 * Small persistence adapter for the route decision ledger.  Keeping this
 * separate from dispatch storage makes it impossible for a queue retry to
 * silently turn a previous QUARANTINE decision into KBP.
 */
final class KernelCurrentKernelRouteGateRepository
{
    public function find(string $eventId, bool $lock = false): ?object
    {
        $query = DB::table('kernel_current_kernel_route_gate')->where('event_id', $eventId);
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first();
    }

    public function insert(array $values): void
    {
        DB::table('kernel_current_kernel_route_gate')->insert($values + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}