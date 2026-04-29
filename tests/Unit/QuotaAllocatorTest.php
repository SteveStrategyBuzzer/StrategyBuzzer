<?php

namespace Tests\Unit;

use App\Services\QuotaAllocator;
use PHPUnit\Framework\TestCase;

class QuotaAllocatorTest extends TestCase
{
    public function test_boss_100_55_30_15_on_30_yields_17_9_4(): void
    {
        $weights = ['recognition' => 55, 'reasoning' => 30, 'deceptive_trap' => 15];
        $stable = ['recognition', 'deceptive_trap', 'reasoning'];
        $alloc = QuotaAllocator::allocate($weights, 30, $stable);

        $this->assertSame(17, $alloc['recognition']);
        $this->assertSame(9, $alloc['reasoning']);
        $this->assertSame(4, $alloc['deceptive_trap']);
        $this->assertSame(30, array_sum($alloc));
    }

    public function test_ligue_or_50_30_20_on_30_yields_15_9_6(): void
    {
        $weights = ['recognition' => 50, 'reasoning' => 30, 'deceptive_trap' => 20];
        $stable = ['recognition', 'deceptive_trap', 'reasoning'];
        $alloc = QuotaAllocator::allocate($weights, 30, $stable);

        $this->assertSame(15, $alloc['recognition']);
        $this->assertSame(9, $alloc['reasoning']);
        $this->assertSame(6, $alloc['deceptive_trap']);
    }

    public function test_solo_50_20_30_on_30_yields_15_6_9(): void
    {
        $weights = ['recognition' => 50, 'reasoning' => 20, 'deceptive_trap' => 30];
        $stable = ['recognition', 'deceptive_trap', 'reasoning'];
        $alloc = QuotaAllocator::allocate($weights, 30, $stable);

        $this->assertSame(15, $alloc['recognition']);
        $this->assertSame(6, $alloc['reasoning']);
        $this->assertSame(9, $alloc['deceptive_trap']);
    }

    public function test_sum_always_equals_total(): void
    {
        $weights = ['a' => 33, 'b' => 33, 'c' => 34];
        for ($t = 1; $t <= 50; $t++) {
            $alloc = QuotaAllocator::allocate($weights, $t, []);
            $this->assertSame($t, array_sum($alloc), "Total mismatch for t={$t}");
        }
    }

    public function test_stable_order_breaks_ties(): void
    {
        // weights identiques → reste 1 pour 4 → recognition (premier en stable order) doit gagner
        $weights = ['recognition' => 33, 'deceptive_trap' => 33, 'reasoning' => 33];
        $stable = ['recognition', 'deceptive_trap', 'reasoning'];
        $alloc = QuotaAllocator::allocate($weights, 4, $stable);

        $this->assertSame(2, $alloc['recognition']);
        $this->assertSame(1, $alloc['deceptive_trap']);
        $this->assertSame(1, $alloc['reasoning']);
    }

    public function test_zero_total_returns_zero_quotas(): void
    {
        $weights = ['a' => 50, 'b' => 50];
        $alloc = QuotaAllocator::allocate($weights, 0, []);
        $this->assertSame(0, $alloc['a']);
        $this->assertSame(0, $alloc['b']);
    }
}
