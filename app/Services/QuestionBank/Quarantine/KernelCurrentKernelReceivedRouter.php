<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use Illuminate\Support\Facades\DB;
use LogicException;
use Illuminate\Support\Str;

/**
 * Durable CURRENT_KERNEL_RECEIVED direction gate.  The singleton gate is
 * only the in-flight mutex; every event decision is its own dispatch row.
 */
final class KernelCurrentKernelReceivedRouter
{
    public const QUARANTINE = 'QUARANTINE';
    public const KBP = 'KBP';
    public const BLOCKED = 'BLOCKED';

    public function __construct(
        private readonly KernelCurrentKernelDispatchRepository $dispatches = new KernelCurrentKernelDispatchRepository(),
    ) {}

    /** @return array{direction:string,event_id:string,blueprint_id:string,copy_id:?string,copy_version:?int,claim_token:?string,state:string} */
    public function decide(string $eventId, string $blueprintId): array
    {
        return DB::transaction(function () use ($eventId, $blueprintId): array {
            $existing = DB::table('kernel_current_kernel_dispatches')
                ->where('event_id', $eventId)->first();
            if ($existing !== null) {
                return $this->map($existing);
            }

            $gate = DB::table('kernel_current_kernel_route_gate')
                ->where('gate_id', 1)->lockForUpdate()->first();
            if ($gate === null) {
                DB::table('kernel_current_kernel_route_gate')->insert([
                    'gate_id' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $gate = DB::table('kernel_current_kernel_route_gate')
                    ->where('gate_id', 1)->lockForUpdate()->first();
            }

            $copy = null;
            if ($gate->active_copy_id !== null) {
                $copy = DB::table('kernel_quarantine_work_copies')
                    ->where('copy_id', $gate->active_copy_id)->lockForUpdate()->first();
            }
            if ($copy === null) {
                $copy = DB::table('kernel_quarantine_work_copies')
                    ->where('state', 'READY')->orderBy('ready_order')->orderBy('copy_id')
                    ->lockForUpdate()->first();
            }
            if ($copy !== null && (string) $copy->state === 'IN_FLIGHT') {
                return [
                    'direction' => self::BLOCKED,
                    'event_id' => $eventId,
                    'blueprint_id' => $blueprintId,
                    'copy_id' => (string) $copy->copy_id,
                    'copy_version' => (int) $copy->copy_version,
                    'claim_token' => (string) ($copy->claim_token ?? ''),
                    'state' => 'BLOCKED',
                ];
            }

            $token = null;
            $direction = self::KBP;
            $order = null;
            $claimNow = $copy !== null && (string) $copy->state === 'READY';
            if ($copy !== null) {
                $direction = self::QUARANTINE;
                $token = (string) ($copy->claim_token ?? Str::orderedUuid());
                $order = $copy->ready_order === null ? null : (int) $copy->ready_order;
                if ($claimNow) {
                    $updated = DB::table('kernel_quarantine_work_copies')
                        ->where('copy_id', $copy->copy_id)->where('state', 'READY')
                        ->where('copy_version', $copy->copy_version)
                        ->update([
                            'state' => 'IN_FLIGHT',
                            'claimed_event_id' => $eventId,
                            'claimed_version' => $copy->copy_version,
                            'claim_token' => $token,
                            'claimed_at' => now(),
                            'updated_at' => now(),
                        ]);
                    if ($updated !== 1) {
                        throw new LogicException('La copie Quarantaine a été réclamée par un autre worker.');
                    }
                    DB::table('kernel_current_kernel_route_gate')->where('gate_id', 1)->update([
                        'active_copy_id' => $copy->copy_id,
                        'active_copy_version' => $copy->copy_version,
                        'active_claim_token' => $token,
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('kernel_current_kernel_dispatches')->insert([
                'event_id' => $eventId,
                'blueprint_id' => $blueprintId,
                'direction' => $direction,
                'copy_id' => $copy?->copy_id,
                'copy_version' => $copy === null ? null : (int) $copy->copy_version,
                'ready_order' => $order,
                'claim_token' => $token,
                'state' => $direction === self::QUARANTINE
                    ? ($claimNow ? 'IN_FLIGHT' : 'WAITING')
                    : 'READY',
                'claimed_at' => $claimNow ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return [
                'direction' => $direction,
                'event_id' => $eventId,
                'blueprint_id' => $blueprintId,
                'copy_id' => $copy?->copy_id,
                'copy_version' => $copy === null ? null : (int) $copy->copy_version,
                'claim_token' => $token,
                'state' => $direction === self::QUARANTINE
                    ? ($claimNow ? 'IN_FLIGHT' : 'WAITING')
                    : 'READY',
            ];
        });
    }

    public function route(string $eventId, string $blueprintId): string
    {
        return $this->decide($eventId, $blueprintId)['direction'];
    }

    /** @return array{direction:string,event_id:string,blueprint_id:string,copy_id:?string,copy_version:?int,claim_token:?string,state:string} */
    private function map(object $row): array
    {
        return [
            'direction' => (string) $row->direction,
            'event_id' => (string) $row->event_id,
            'blueprint_id' => (string) $row->blueprint_id,
            'copy_id' => $row->copy_id === null ? null : (string) $row->copy_id,
            'copy_version' => $row->copy_version === null ? null : (int) $row->copy_version,
            'claim_token' => $row->claim_token === null ? null : (string) $row->claim_token,
            'state' => (string) $row->state,
        ];
    }
}