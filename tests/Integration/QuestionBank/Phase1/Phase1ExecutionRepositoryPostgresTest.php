<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Phase1;

use App\Services\QuestionBank\Phase1\Phase1ExecutionRepository;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Runs against the full disposable PostgreSQL migration chain.
 *
 * The test owns only its inserted Blueprint/arbitration rows and never
 * recreates or drops an official table.
 */
final class Phase1ExecutionRepositoryPostgresTest extends TestCase
{
    private const BLUEPRINT_ID = 'bp-phase1-arbiter-test';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('kernel_phase1_executions')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->delete();
        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->delete();

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => self::BLUEPRINT_ID,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth' => 4,
            'domain_code' => 'SCI',
            'subdomain_active' => 'Physics',
            'subject_active' => 'Light',
            'dominant_idea_active' => 'Refraction',
            'kernel_code_dd' => '04',
            'kernel_code_do' => 'SCI',
            'kernel_code_sub' => 'PHY',
            'kernel_code_suj' => 'LIG',
            'kernel_code_ide' => 'REF',
            'kernel_code_vvvv' => '0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('kernel_phase1_executions')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->delete();
        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->delete();
        parent::tearDown();
    }

    public function test_claim_is_unique_and_replay_is_active_then_completed_no_op(): void
    {
        $repository = new Phase1ExecutionRepository();
        $first = $repository->claim(self::BLUEPRINT_ID);
        $second = $repository->claim(self::BLUEPRINT_ID);

        $this->assertSame(Phase1ExecutionRepository::CLAIMED, $first['status']);
        $this->assertSame(Phase1ExecutionRepository::NO_OP_ACTIVE, $second['status']);
        $this->assertSame($first['execution_id'], $second['execution_id']);

        $this->assertSame(
            'COMPLETED',
            $repository->complete(
                $first['execution_id'],
                $first['lease_token'],
                self::BLUEPRINT_ID,
                $first['identity_revision'],
                ['handoff' => true],
            )
        );
        $terminal = $repository->claim(self::BLUEPRINT_ID);
        $this->assertSame(Phase1ExecutionRepository::NO_OP_COMPLETED, $terminal['status']);
    }

    public function test_two_real_processes_produce_one_claim_and_one_active_no_op(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the PostgreSQL concurrency proof.');
        }

        $directory = sys_get_temp_dir() . '/phase1-arbiter-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $pids = [];
        try {
            for ($worker = 0; $worker < 2; $worker++) {
                $pid = pcntl_fork();
                if ($pid === 0) {
                    try {
                        DB::purge(config('database.default'));
                        DB::reconnect(config('database.default'));
                        $claim = (new Phase1ExecutionRepository())->claim(self::BLUEPRINT_ID);
                        file_put_contents(
                            "{$directory}/{$worker}",
                            json_encode($claim, JSON_THROW_ON_ERROR),
                        );
                        exit(0);
                    } catch (\Throwable $exception) {
                        file_put_contents(
                            "{$directory}/{$worker}.error",
                            get_class($exception) . ': ' . $exception->getMessage(),
                        );
                        exit(1);
                    }
                }
                $this->assertGreaterThan(0, $pid);
                $pids[] = $pid;
            }

            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertSame(0, pcntl_wexitstatus($status));
            }

            $claims = [];
            foreach ([0, 1] as $worker) {
                $path = "{$directory}/{$worker}";
                $this->assertFileExists($path);
                $claims[] = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            }
            $statuses = array_column($claims, 'status');
            sort($statuses);
            $this->assertSame(
                [Phase1ExecutionRepository::CLAIMED, Phase1ExecutionRepository::NO_OP_ACTIVE],
                $statuses,
            );
            $this->assertSame($claims[0]['execution_id'], $claims[1]['execution_id']);
            $this->assertSame(
                1,
                DB::table('kernel_phase1_executions')
                    ->where('blueprint_id', self::BLUEPRINT_ID)
                    ->count(),
            );
        } finally {
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status, WNOHANG);
            }
            foreach (glob("{$directory}/*") ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
            DB::purge(config('database.default'));
            DB::reconnect(config('database.default'));
        }
    }

    public function test_old_identity_cannot_finalize_after_persisted_identity_changes(): void
    {
        $repository = new Phase1ExecutionRepository();
        $claim = $repository->claim(self::BLUEPRINT_ID);

        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', self::BLUEPRINT_ID)
            ->update(['dominant_idea_active' => 'Dispersion', 'updated_at' => now()]);

        $this->assertSame(
            Phase1ExecutionRepository::STALE_RESULT,
            $repository->complete(
                $claim['execution_id'],
                $claim['lease_token'],
                self::BLUEPRINT_ID,
                $claim['identity_revision'],
            )
        );
    }

    public function test_completed_or_stale_lease_cannot_execute_a_write_callback(): void
    {
        $repository = new Phase1ExecutionRepository();
        $claim = $repository->claim(self::BLUEPRINT_ID);
        $this->assertSame(
            'COMPLETED',
            $repository->complete(
                $claim['execution_id'],
                $claim['lease_token'],
                self::BLUEPRINT_ID,
                $claim['identity_revision'],
            ),
        );

        $called = false;
        try {
            $repository->withActiveLease(
                $claim['execution_id'],
                $claim['lease_token'],
                self::BLUEPRINT_ID,
                $claim['identity_revision'],
                function () use (&$called): void {
                    $called = true;
                },
            );
            $this->fail('A completed lease must not authorize writes.');
        } catch (\LogicException) {
            $this->assertFalse($called);
        }
    }
}