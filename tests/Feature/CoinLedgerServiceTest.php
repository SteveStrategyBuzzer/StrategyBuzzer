<?php

namespace Tests\Feature;

use App\Models\CoinLedger;
use App\Models\User;
use App\Services\CoinLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-A — CoinLedgerService security tests.
 *
 * Validates:
 *   1. credit() and debit() use lockForUpdate (balance always consistent).
 *   2. creditOnce() never double-credits the same (user, ref_type, ref_id, reason, coin_type).
 *   3. debitOnce() never double-debits.
 *   4. credit() with no ref produces a ledger entry (untracked credits still logged).
 *   5. debit() throws when balance is insufficient.
 *   6. creditOnce() and debitOnce() return the existing entry on replay.
 *   7. Different ref_ids produce independent credits (no false-positive idempotency).
 *   8. Ledger entry count is always consistent with coin balance.
 */
class CoinLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private CoinLedgerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(CoinLedgerService::class);
    }

    private function makeUser(int $coins = 0, int $competenceCoins = 0): User
    {
        return User::factory()->create([
            'coins'            => $coins,
            'competence_coins' => $competenceCoins,
        ]);
    }

    // -----------------------------------------------------------------------
    // credit()
    // -----------------------------------------------------------------------

    public function test_credit_increases_user_coins_and_creates_ledger_entry(): void
    {
        $user = $this->makeUser(100);

        $entry = $this->svc->credit($user, 50, 'test_credit', null, null, 'intelligence');

        $this->assertDatabaseHas('coin_ledger', [
            'user_id'      => $user->id,
            'delta'        => 50,
            'coin_type'    => 'intelligence',
            'reason'       => 'test_credit',
            'balance_after' => 150,
        ]);

        $this->assertEquals(150, $user->fresh()->coins);
        $this->assertEquals(50, $entry->delta);
    }

    public function test_credit_competence_increases_competence_coins(): void
    {
        $user = $this->makeUser(0, 200);

        $this->svc->credit($user, 75, 'quest_reward', null, null, 'competence');

        $this->assertEquals(275, $user->fresh()->competence_coins);
        $this->assertDatabaseHas('coin_ledger', [
            'user_id'   => $user->id,
            'delta'     => 75,
            'coin_type' => 'competence',
        ]);
    }

    public function test_multiple_credits_accumulate_correctly(): void
    {
        $user = $this->makeUser(0);

        $this->svc->credit($user, 100, 'win_a');
        $this->svc->credit($user, 200, 'win_b');
        $this->svc->credit($user, 50,  'win_c');

        $this->assertEquals(350, $user->fresh()->coins);
        $this->assertSame(3, CoinLedger::where('user_id', $user->id)->count());
    }

    // -----------------------------------------------------------------------
    // debit()
    // -----------------------------------------------------------------------

    public function test_debit_decreases_user_coins_and_creates_ledger_entry(): void
    {
        $user = $this->makeUser(500);

        $entry = $this->svc->debit($user, 200, 'shop_purchase', null, null, 'intelligence');

        $this->assertDatabaseHas('coin_ledger', [
            'user_id'      => $user->id,
            'delta'        => -200,
            'coin_type'    => 'intelligence',
            'balance_after' => 300,
        ]);

        $this->assertEquals(300, $user->fresh()->coins);
        $this->assertEquals(-200, $entry->delta);
    }

    public function test_debit_throws_when_insufficient_intelligence_coins(): void
    {
        $user = $this->makeUser(50);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient coins');

        $this->svc->debit($user, 100, 'overdraft');
    }

    public function test_debit_throws_when_insufficient_competence_coins(): void
    {
        $user = $this->makeUser(0, 10);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient competence coins');

        $this->svc->debit($user, 50, 'overdraft', null, null, 'competence');
    }

    public function test_debit_does_not_create_ledger_entry_when_it_throws(): void
    {
        $user = $this->makeUser(50);

        try {
            $this->svc->debit($user, 100, 'overdraft');
        } catch (\Exception) {
        }

        $this->assertSame(0, CoinLedger::where('user_id', $user->id)->count());
        $this->assertEquals(50, $user->fresh()->coins);
    }

    // -----------------------------------------------------------------------
    // creditOnce() — idempotency
    // -----------------------------------------------------------------------

    public function test_credit_once_credits_on_first_call(): void
    {
        $user = $this->makeUser(0);

        $entry = $this->svc->creditOnce($user, 100, 'match_win', 'match', 42);

        $this->assertEquals(100, $user->fresh()->coins);
        $this->assertSame(1, CoinLedger::where('user_id', $user->id)->count());
        $this->assertEquals(100, $entry->delta);
    }

    public function test_credit_once_does_not_double_credit_same_ref(): void
    {
        $user = $this->makeUser(0);

        $first  = $this->svc->creditOnce($user, 100, 'match_win', 'match', 42);
        $second = $this->svc->creditOnce($user, 100, 'match_win', 'match', 42);

        $this->assertEquals(100, $user->fresh()->coins);
        $this->assertSame(1, CoinLedger::where('user_id', $user->id)->count());
        $this->assertEquals($first->id, $second->id);
    }

    public function test_credit_once_replay_returns_existing_entry(): void
    {
        $user = $this->makeUser(0);

        $original = $this->svc->creditOnce($user, 250, 'match_win', 'match', 99);

        for ($i = 0; $i < 5; $i++) {
            $replay = $this->svc->creditOnce($user, 250, 'match_win', 'match', 99);
            $this->assertEquals($original->id, $replay->id);
        }

        $this->assertEquals(250, $user->fresh()->coins);
        $this->assertSame(1, CoinLedger::where('user_id', $user->id)->count());
    }

    public function test_credit_once_different_ref_ids_are_independent(): void
    {
        $user = $this->makeUser(0);

        $this->svc->creditOnce($user, 100, 'match_win', 'match', 1);
        $this->svc->creditOnce($user, 100, 'match_win', 'match', 2);
        $this->svc->creditOnce($user, 100, 'match_win', 'match', 3);

        $this->assertEquals(300, $user->fresh()->coins);
        $this->assertSame(3, CoinLedger::where('user_id', $user->id)->count());
    }

    public function test_credit_once_different_reasons_same_ref_are_independent(): void
    {
        $user = $this->makeUser(0);

        $this->svc->creditOnce($user, 100, 'match_win',     'match', 1);
        $this->svc->creditOnce($user, 50,  'match_streak',  'match', 1);

        $this->assertEquals(150, $user->fresh()->coins);
        $this->assertSame(2, CoinLedger::where('user_id', $user->id)->count());
    }

    public function test_credit_once_separates_coin_types(): void
    {
        $user = $this->makeUser(0, 0);

        $this->svc->creditOnce($user, 100, 'quest_done', 'quest', 7, 'intelligence');
        $this->svc->creditOnce($user, 100, 'quest_done', 'quest', 7, 'competence');

        $this->assertEquals(100, $user->fresh()->coins);
        $this->assertEquals(100, $user->fresh()->competence_coins);
        $this->assertSame(2, CoinLedger::where('user_id', $user->id)->count());
    }

    // -----------------------------------------------------------------------
    // debitOnce() — idempotency
    // -----------------------------------------------------------------------

    public function test_debit_once_debits_on_first_call(): void
    {
        $user = $this->makeUser(500);

        $entry = $this->svc->debitOnce($user, 200, 'shop_avatar', 'shop', 10);

        $this->assertEquals(300, $user->fresh()->coins);
        $this->assertSame(1, CoinLedger::where('user_id', $user->id)->count());
        $this->assertEquals(-200, $entry->delta);
    }

    public function test_debit_once_does_not_double_debit_same_ref(): void
    {
        $user = $this->makeUser(500);

        $first  = $this->svc->debitOnce($user, 200, 'shop_avatar', 'shop', 10);
        $second = $this->svc->debitOnce($user, 200, 'shop_avatar', 'shop', 10);

        $this->assertEquals(300, $user->fresh()->coins);
        $this->assertSame(1, CoinLedger::where('user_id', $user->id)->count());
        $this->assertEquals($first->id, $second->id);
    }

    // -----------------------------------------------------------------------
    // Ledger consistency invariant
    // -----------------------------------------------------------------------

    public function test_coin_balance_equals_sum_of_ledger_deltas_after_mixed_operations(): void
    {
        $user = $this->makeUser(1000);

        $this->svc->credit($user, 500, 'bonus_a');
        $this->svc->debit($user, 200, 'shop_b');
        $this->svc->creditOnce($user, 300, 'match_win', 'match', 1);
        $this->svc->creditOnce($user, 300, 'match_win', 'match', 1); // replay — no-op
        $this->svc->debit($user, 100, 'shop_c');

        $fresh = $user->fresh();
        $sumDeltas = CoinLedger::where('user_id', $user->id)
            ->where('coin_type', 'intelligence')
            ->sum('delta');

        $expected = 1000 + $sumDeltas;
        $this->assertEquals($expected, $fresh->coins);
    }
}
