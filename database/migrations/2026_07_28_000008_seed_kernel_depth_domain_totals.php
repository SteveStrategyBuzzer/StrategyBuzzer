<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M-08 — seed_kernel_depth_domain_totals
 *
 * Insère les 56 lignes initiales (7 Depths × 8 Domaines officiels).
 * kernel_received_total commence à 0 (sera synchronisé avec ReadyBank au premier démarrage).
 * Idempotent : ignore les lignes existantes.
 */
return new class extends Migration
{
    private const DEPTHS = [2, 4, 6, 7, 8, 9, 10];

    private const DOMAINS = [
        'geographie',
        'histoire',
        'faune',
        'art',
        'sport',
        'cinema',
        'cuisine',
        'science',
    ];

    public function up(): void
    {
        $now  = now();
        $rows = [];

        foreach (self::DEPTHS as $depth) {
            foreach (self::DOMAINS as $domain) {
                $rows[] = [
                    'depth'                 => $depth,
                    'domain_code'           => $domain,
                    'kernel_received_total' => 0,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ];
            }
        }

        DB::table('kernel_depth_domain_totals')->upsert(
            $rows,
            uniqueBy: ['depth', 'domain_code'],
            update:   [] // ne jamais écraser si existe déjà
        );
    }

    public function down(): void
    {
        DB::table('kernel_depth_domain_totals')
            ->whereIn('depth', self::DEPTHS)
            ->delete();
    }
};
