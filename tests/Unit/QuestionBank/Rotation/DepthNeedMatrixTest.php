<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests unitaires pour DepthNeedMatrix.
 *
 * DB : schéma PostgreSQL officiel créé par la chaîne complète des migrations.
 * La fixture réinitialise uniquement les lignes utilisées par ces tests.
 */
class DepthNeedMatrixTest extends TestCase
{
    private DepthNeedMatrix $matrix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetDepthMatrix();
        $this->resetDepthDomainTotals();

        $this->matrix = new DepthNeedMatrix();
    }

    // =========================================================================
    // Constantes officielles
    // =========================================================================

    public function test_depth_cycle_contains_7_depths(): void
    {
        $this->assertCount(7, DepthNeedMatrix::DEPTH_CYCLE);
    }

    public function test_depth_cycle_contains_depth_2_and_10(): void
    {
        $this->assertContains(2,  DepthNeedMatrix::DEPTH_CYCLE, 'Depth 2 requis (DEC-065)');
        $this->assertContains(10, DepthNeedMatrix::DEPTH_CYCLE, 'Depth 10 requis (DEC-065)');
    }

    public function test_depth_cycle_official_order(): void
    {
        $this->assertSame([2, 4, 6, 7, 8, 9, 10], DepthNeedMatrix::DEPTH_CYCLE);
    }

    public function test_cycle_target_defined_for_all_depths(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $this->assertArrayHasKey($depth, DepthNeedMatrix::CYCLE_TARGET,
                "cycle_target manquant pour Depth {$depth}");
        }
    }

    public function test_cycle_target_all_positive(): void
    {
        foreach (DepthNeedMatrix::CYCLE_TARGET as $depth => $target) {
            $this->assertGreaterThan(0, $target, "cycle_target[{$depth}] doit être > 0");
        }
    }

    // =========================================================================
    // nextRequiredDepth — depuis null
    // =========================================================================

    public function test_next_required_depth_from_null_returns_depth_2(): void
    {
        // Aucun cycle_completed → Depth 2 premier du cycle
        $next = $this->matrix->nextRequiredDepth(null);
        $this->assertSame(2, $next);
    }

    public function test_next_required_depth_skips_saturated_depths(): void
    {
        // Saturer Depth 2 (cycle_completed = cycle_target)
        DB::table('kernel_depth_matrix')
            ->where('depth', 2)
            ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[2]]);

        $next = $this->matrix->nextRequiredDepth(null);
        $this->assertSame(4, $next, 'Depth 2 saturé → passer à Depth 4');
    }

    public function test_next_required_depth_returns_null_when_all_saturated(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }

        $next = $this->matrix->nextRequiredDepth(null);
        $this->assertNull($next, 'null = PRODUCTION_ON_HOLD');
    }

    // =========================================================================
    // nextRequiredDepth — depuis un Depth donné
    // =========================================================================

    public function test_next_required_depth_after_2_is_4(): void
    {
        $next = $this->matrix->nextRequiredDepth(2);
        $this->assertSame(4, $next);
    }

    public function test_next_required_depth_after_10_wraps_to_2(): void
    {
        // Depth 2 non saturé → doit revenir à 2 après 10
        $next = $this->matrix->nextRequiredDepth(10);
        $this->assertSame(2, $next, 'Après Depth 10 : reprend à Depth 2 (DEC-065)');
    }

    public function test_next_required_depth_after_9_is_10(): void
    {
        $next = $this->matrix->nextRequiredDepth(9);
        $this->assertSame(10, $next);
    }

    // =========================================================================
    // incrementCycleCompleted
    // =========================================================================

    public function test_increment_cycle_completed_increases_by_1(): void
    {
        $before = $this->matrix->getCycleCompleted(4);
        $this->matrix->incrementCycleCompleted(4);
        $after  = $this->matrix->getCycleCompleted(4);

        $this->assertSame($before + 1, $after);
    }

    public function test_get_cycle_completed_returns_0_for_fresh_depth(): void
    {
        $this->assertSame(0, $this->matrix->getCycleCompleted(6));
    }

    // =========================================================================
    // kernel_received_total
    // =========================================================================

    public function test_get_kernel_received_total_returns_0_initially(): void
    {
        $total = $this->matrix->getKernelReceivedTotal(2, 'GEO');
        $this->assertSame(0, $total);
    }

    public function test_legacy_slug_inputs_are_read_through_canonical_rows(): void
    {
        DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'GEO')
            ->update(['kernel_received_total' => 3]);

        $this->assertSame(3, $this->matrix->getKernelReceivedTotal(2, 'GEO'));
        $this->assertSame(3, $this->matrix->getKernelReceivedTotal(2, 'geographie'));
    }

    public function test_increment_kernel_received_increments_by_1(): void
    {
        $this->matrix->incrementKernelReceived(4, 'HIS');
        $this->matrix->incrementKernelReceived(4, 'HIS');

        $this->assertSame(2, $this->matrix->getKernelReceivedTotal(4, 'HIS'));
    }

    public function test_increment_kernel_received_is_per_depth_domain(): void
    {
        $this->matrix->incrementKernelReceived(4, 'SPO');
        $this->matrix->incrementKernelReceived(6, 'SPO');

        $this->assertSame(1, $this->matrix->getKernelReceivedTotal(4, 'SPO'));
        $this->assertSame(1, $this->matrix->getKernelReceivedTotal(6, 'SPO'));
    }

    // =========================================================================
    // initializeFromReadyBank
    // =========================================================================

    public function test_initialize_from_ready_bank_sets_totals(): void
    {
        $this->matrix->initializeFromReadyBank(7, ['GEO' => 15, 'HIS' => 8]);

        $this->assertSame(15, $this->matrix->getKernelReceivedTotal(7, 'GEO'));
        $this->assertSame(8,  $this->matrix->getKernelReceivedTotal(7, 'HIS'));
    }

    public function test_initialize_from_ready_bank_does_not_overwrite_higher_value(): void
    {
        // Simuler déjà 20 noyaux
        DB::table('kernel_depth_domain_totals')
            ->where('depth', 8)
            ->where('domain_code', 'FAU')
            ->update(['kernel_received_total' => 20]);

        // Initialiser à 10 (inférieur) → ne doit pas écraser
        $this->matrix->initializeFromReadyBank(8, ['FAU' => 10]);

        $this->assertSame(20, $this->matrix->getKernelReceivedTotal(8, 'FAU'),
            'initializeFromReadyBank ne doit pas écraser une valeur plus haute');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function resetDepthMatrix(): void
    {
        $now = now();

        foreach (DepthNeedMatrix::CYCLE_TARGET as $depth => $target) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update([
                'cycle_target'                => $target,
                'cycle_completed'             => 0,
                'empty_progress_current_tour' => 0,
                'current_tour_id'             => null,
                'updated_at'                  => $now,
            ]);
        }
    }

    private function resetDepthDomainTotals(): void
    {
        $now     = now();
        $domains = ['GEO', 'HIS', 'FAU', 'ART', 'SPO', 'CIN', 'CUI', 'SCI'];

        DB::table('kernel_depth_domain_totals')
            ->whereNotIn('domain_code', $domains)
            ->delete();

        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            foreach ($domains as $domain) {
                DB::table('kernel_depth_domain_totals')->updateOrInsert(
                    ['depth' => $depth, 'domain_code' => $domain],
                    [
                        'kernel_received_total' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }
}
