<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M-07 — seed_kernel_depth_matrix
 *
 * Insère les 7 lignes officielles du DepthCycle (DEC-060, DEC-065).
 * Idempotent : ignore les lignes existantes (INSERT IGNORE / conflict skip).
 */
return new class extends Migration
{
    private const CYCLE_TARGET = [
        2  => 250,
        4  => 300,
        6  => 350,
        7  => 350,
        8  => 350,
        9  => 250,
        10 => 100,
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::CYCLE_TARGET as $depth => $target) {
            DB::table('kernel_depth_matrix')->upsert(
                [
                    'depth'                       => $depth,
                    'cycle_target'                => $target,
                    'cycle_completed'             => 0,
                    'empty_progress_current_tour' => 0,
                    'current_tour_id'             => null,
                    'created_at'                  => $now,
                    'updated_at'                  => $now,
                ],
                uniqueBy: ['depth'],
                update:   [] // ne jamais écraser si existe déjà
            );
        }
    }

    public function down(): void
    {
        DB::table('kernel_depth_matrix')
            ->whereIn('depth', array_keys(self::CYCLE_TARGET))
            ->delete();
    }
};
