<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #182 Rotation — convert the 56 CKR counter keys from legacy slugs to codes.
 *
 * This migration deliberately does not edit the historical seed migration.
 * The application remains compatible with legacy slugs until this transaction
 * has completed, so Rotation never has to read an incompatible representation.
 */
return new class extends Migration
{
    private const DEPTHS = [2, 4, 6, 7, 8, 9, 10];

    private const DOMAIN_MAP = [
        'geographie' => 'GEO',
        'histoire'   => 'HIS',
        'faune'      => 'FAU',
        'art'        => 'ART',
        'sport'      => 'SPO',
        'cinema'     => 'CIN',
        'cuisine'    => 'CUI',
        'science'    => 'SCI',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->lockTransitionTables();
            $this->assertPreconditions();

            foreach (self::DEPTHS as $depth) {
                foreach (self::DOMAIN_MAP as $slug => $code) {
                    DB::table('kernel_depth_domain_totals')
                        ->where('depth', $depth)
                        ->where('domain_code', $slug)
                        ->update(['domain_code' => $code]);
                }
            }

            $this->assertCanonicalResult();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->lockTransitionTables();
            $this->assertCanonicalPreconditions();

            foreach (self::DEPTHS as $depth) {
                foreach (self::DOMAIN_MAP as $slug => $code) {
                    DB::table('kernel_depth_domain_totals')
                        ->where('depth', $depth)
                        ->where('domain_code', $code)
                        ->update(['domain_code' => $slug]);
                }
            }
        });
    }

    private function lockTransitionTables(): void
    {
        DB::statement('LOCK TABLE kernel_current_kernel_receipts IN ACCESS EXCLUSIVE MODE');
        DB::statement('LOCK TABLE kernel_depth_domain_totals IN ACCESS EXCLUSIVE MODE');
    }

    private function assertPreconditions(): void
    {
        if (! Schema::hasTable('kernel_depth_domain_totals')) {
            throw new RuntimeException(
                'Conversion #182 interrompue : table kernel_depth_domain_totals absente.'
            );
        }

        if (! Schema::hasTable('kernel_current_kernel_receipts')) {
            throw new RuntimeException(
                'Conversion #182 interrompue : table des reçus CKR absente.'
            );
        }

        if (DB::table('kernel_current_kernel_receipts')->count() !== 0) {
            throw new RuntimeException(
                'Conversion #182 interrompue : des reçus CKR existent déjà.'
            );
        }

        $rows = DB::table('kernel_depth_domain_totals')
            ->orderBy('depth')
            ->orderBy('domain_code')
            ->get();

        if ($rows->count() !== 56) {
            throw new RuntimeException(
                "Conversion #182 interrompue : 56 lignes attendues, {$rows->count()} trouvées."
            );
        }

        $expected = [];
        foreach (self::DEPTHS as $depth) {
            foreach (array_keys(self::DOMAIN_MAP) as $slug) {
                $expected["{$depth}:{$slug}"] = true;
            }
        }

        foreach ($rows as $row) {
            $key = "{$row->depth}:{$row->domain_code}";
            if (! isset($expected[$key])) {
                throw new RuntimeException(
                    "Conversion #182 interrompue : couple inattendu {$key}."
                );
            }

            if ((int) $row->kernel_received_total !== 0) {
                throw new RuntimeException(
                    "Conversion #182 interrompue : compteur non nul pour {$key}."
                );
            }

            unset($expected[$key]);
        }

        if ($expected !== []) {
            throw new RuntimeException(
                'Conversion #182 interrompue : couples manquants : '
                . implode(', ', array_keys($expected)) . '.'
            );
        }
    }

    private function assertCanonicalPreconditions(): void
    {
        if (! Schema::hasTable('kernel_current_kernel_receipts')
            || DB::table('kernel_current_kernel_receipts')->count() !== 0) {
            throw new RuntimeException(
                'Rollback #182 interrompu : des reçus CKR existent ou leur table est absente.'
            );
        }

        $rows = DB::table('kernel_depth_domain_totals')->get();
        if ($rows->count() !== 56 || $rows->sum('kernel_received_total') !== 0) {
            throw new RuntimeException(
                'Rollback #182 interrompu : les 56 compteurs ne sont plus tous à zéro.'
            );
        }

        $expectedCodes = array_values(self::DOMAIN_MAP);
        foreach ($rows as $row) {
            if (! in_array((string) $row->depth, array_map('strval', self::DEPTHS), true)
                || ! in_array((string) $row->domain_code, $expectedCodes, true)) {
                throw new RuntimeException(
                    "Rollback #182 interrompu : représentation inattendue {$row->depth}/{$row->domain_code}."
                );
            }
        }
    }

    private function assertCanonicalResult(): void
    {
        $rows = DB::table('kernel_depth_domain_totals')->get();
        if ($rows->count() !== 56 || $rows->sum('kernel_received_total') !== 0) {
            throw new RuntimeException(
                'Conversion #182 interrompue : résultat canonique incomplet.'
            );
        }

        foreach ($rows as $row) {
            if (! in_array((int) $row->depth, self::DEPTHS, true)
                || ! in_array((string) $row->domain_code, array_values(self::DOMAIN_MAP), true)) {
                throw new RuntimeException(
                    "Conversion #182 interrompue : résultat inattendu {$row->depth}/{$row->domain_code}."
                );
            }
        }
    }
};