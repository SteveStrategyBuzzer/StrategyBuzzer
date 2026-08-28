<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use Illuminate\Support\Facades\DB;

/**
 * KRP-side persistence boundary for a terminal Taxonomy fact.
 *
 * Taxonomy will later call the public KRP entry with an immutable fact identity,
 * depth and domain. This repository deliberately knows no Taxonomy internals:
 * it stores the fact with the KRP active-tour correlation, exposes one pending
 * fact under lock, and confirms exactly one consumption.
 */
final class KernelTerminalFactRepository
{
    private const TABLE = 'kernel_taxonomy_terminal_facts';

    public function lockByFactId(string $factId): ?object
    {
        return DB::table(self::TABLE)
            ->where('fact_id', $factId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Records a terminal fact once. A replay of the same fact_id is a NO-OP.
     */
    public function record(string $factId, int $depth, string $domain, string $tourId): void
    {
        $inserted = DB::table(self::TABLE)->insertOrIgnore([
            'fact_id'     => $factId,
            'depth'       => $depth,
            'domain_code' => $domain,
            'tour_id'     => $tourId,
            'received_at' => now(),
            'consumed_at' => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        if ($inserted === 1) {
            return;
        }

        $existing = DB::table(self::TABLE)
            ->where('fact_id', $factId)
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            throw new \RuntimeException(
                "[KRP] Fait terminal {$factId} absent après conflit d'idempotence."
            );
        }

        if ((int) $existing->depth !== $depth
            || (string) $existing->domain_code !== $domain
            || (string) $existing->tour_id !== $tourId) {
            throw new \RuntimeException(
                "[KRP] Violation d'immuabilité du fait terminal {$factId}."
            );
        }
    }

    /**
     * Returns the oldest pending fact for the active Depth with a row lock.
     *
     * This must be called from the transaction that owns the KRP state lock.
     */
    public function lockNextPendingForTour(int $depth, string $tourId): ?object
    {
        return DB::table(self::TABLE)
            ->where('depth', $depth)
            ->where('tour_id', $tourId)
            ->whereNull('consumed_at')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Confirms the fact was consumed by KRP. A replay confirmation is a NO-OP.
     */
    public function markConsumed(string $factId): void
    {
        DB::table(self::TABLE)
            ->where('fact_id', $factId)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'updated_at'  => now(),
            ]);
    }
}