<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use App\Services\QuestionBank\Rotation\KernelBlueprintProvisioner;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * KBP is tested directly, in an isolated PostgreSQL schema. No pipeline phase
 * or test ownership object is part of this boundary.
 */
final class KernelBlueprintProvisionerPostgresTest extends TestCase
{
    private const CONNECTION = 'kpb_provisioner_test';

    private string $schemaName;
    private string $originalDefault;
    private mixed $originalSearchPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDefault = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');
        $this->schemaName = 'test_kbp_' . bin2hex(random_bytes(6));

        DB::connection('pgsql')->statement('CREATE SCHEMA ' . $this->quotedSchema());
        $config = config('database.connections.pgsql');
        $config['search_path'] = $this->quotedSchema();
        config([
            'database.default' => self::CONNECTION,
            'database.connections.' . self::CONNECTION => $config,
        ]);
        DB::purge(self::CONNECTION);
        DB::reconnect(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);
        $this->assertSame($this->schemaName, DB::selectOne('SELECT current_schema() AS name')->name);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS ' . $this->quotedSchema() . ' CASCADE');
        } finally {
            DB::setDefaultConnection($this->originalDefault);
            config([
                'database.default' => $this->originalDefault,
                'database.connections.pgsql.search_path' => $this->originalSearchPath,
            ]);
            DB::purge(self::CONNECTION);
            parent::tearDown();
        }
    }

    public function test_request_reference_migration_shape_has_only_the_two_contract_columns_and_cascade_fk(): void
    {
        $columns = DB::select(<<<'SQL'
SELECT column_name, data_type, character_maximum_length
FROM information_schema.columns
WHERE table_schema = current_schema() AND table_name = 'kernel_blueprint_request_refs'
ORDER BY ordinal_position
SQL);

        $this->assertSame([
            ['request_reference', 'character varying', 128],
            ['blueprint_id', 'character varying', 36],
        ], array_map(static fn (object $column) => [
            $column->column_name, $column->data_type, $column->character_maximum_length,
        ], $columns));
        $this->assertSame('FOREIGN KEY', DB::selectOne(<<<'SQL'
SELECT constraint_type AS type
FROM information_schema.table_constraints
WHERE table_schema = current_schema()
  AND table_name = 'kernel_blueprint_request_refs'
  AND constraint_type = 'FOREIGN KEY'
SQL)->type);
        $this->assertSame('c', DB::selectOne(<<<'SQL'
SELECT confdeltype
FROM pg_constraint
WHERE conrelid = 'kernel_blueprint_request_refs'::regclass AND contype = 'f'
SQL)->confdeltype);
    }

    public function test_provisioning_creates_an_empty_parent_and_seven_slots_atomically(): void
    {
        $id = $this->provisioner()->provisionForTest('test:atomic', 'input-only');
        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $id)->first();

        $this->assertSame('CREATED_UNENGAGED', $run->execution_state);
        $this->assertNull($run->depth);
        $this->assertNull($run->domain_code);
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $id)->count());
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $id)->where('creation_status', 'EMPTY')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $id)->whereNotNull('creation_failure')->count());
    }

    public function test_production_event_replay_returns_the_same_blueprint_id(): void
    {
        $event = new CurrentKernelReceived('event-kbp-replay', 'received-blueprint', 2, 'science', now()->toIso8601String());
        $first = $this->provisioner()->provisionForCurrentKernelReceived($event);
        $second = $this->provisioner()->provisionForCurrentKernelReceived($event);

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(1, DB::table('kernel_blueprint_request_refs')->count());
    }

    public function test_test_reference_returns_only_an_id_and_never_persists_requesting_phase(): void
    {
        $id = $this->provisioner()->provisionForTest('test:input-only', 'phase-that-must-not-be-stored');

        $this->assertIsString($id);
        $this->assertSame(
            ['request_reference', 'blueprint_id'],
            array_map(static fn (object $column) => $column->column_name, DB::select(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'kernel_blueprint_request_refs' ORDER BY ordinal_position"
            )),
        );
    }

    public function test_cleanup_is_idempotent_and_refuses_mismatch_or_public_schema(): void
    {
        $provisioner = $this->provisioner();
        $id = $provisioner->provisionForTest('test:cleanup', 'phase');
        try {
            $provisioner->cleanupTestContext('other-id', 'test:cleanup');
            $this->fail('A mismatched id must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('non autorisé', $exception->getMessage());
        }

        $provisioner->cleanupTestContext($id, 'test:cleanup');
        $provisioner->cleanupTestContext($id, 'test:cleanup');
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_request_refs')->count());

        DB::statement('SET search_path TO public');
        try {
            $provisioner->cleanupTestContext($id, 'test:cleanup');
            $this->fail('Cleanup in public must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('public', $exception->getMessage());
        } finally {
            DB::statement('SET search_path TO ' . $this->quotedSchema());
        }
    }

    public function test_failed_binding_rolls_back_parent_slots_and_binding(): void
    {
        DB::statement("ALTER TABLE kernel_blueprint_request_refs ADD CONSTRAINT reject_rollback CHECK (request_reference <> 'test:rollback')");
        try {
            $this->provisioner()->provisionForTest('test:rollback', 'phase');
            $this->fail('The binding constraint must fail.');
        } catch (\Throwable) {
            $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
            $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
            $this->assertSame(0, DB::table('kernel_blueprint_request_refs')->count());
        }
    }

    public function test_two_real_processes_converge_on_one_binding_and_no_orphans(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the PostgreSQL concurrency proof.');
        }

        $resultDirectory = sys_get_temp_dir() . '/kpb-' . bin2hex(random_bytes(6));
        mkdir($resultDirectory);
        $event = ['event-concurrent', 'received-blueprint', 2, 'science', now()->toIso8601String()];
        $pids = [];
        for ($worker = 0; $worker < 2; $worker++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge(self::CONNECTION);
                DB::reconnect(self::CONNECTION);
                $id = (new KernelBlueprintProvisioner())->provisionForCurrentKernelReceived(new CurrentKernelReceived(...$event));
                file_put_contents("{$resultDirectory}/{$worker}", $id);
                exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        $ids = [file_get_contents("{$resultDirectory}/0"), file_get_contents("{$resultDirectory}/1")];
        unlink("{$resultDirectory}/0");
        unlink("{$resultDirectory}/1");
        rmdir($resultDirectory);
        $this->assertSame($ids[0], $ids[1]);
        $this->assertSame(1, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(1, DB::table('kernel_blueprint_request_refs')->count());
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')->count());
    }

    private function provisioner(): KernelBlueprintProvisioner
    {
        return new KernelBlueprintProvisioner();
    }

    private function createSchema(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64);
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->timestampTz('engaged_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->jsonb('source');
            $table->jsonb('creation_failure')->nullable();
            $table->jsonb('translations')->default('{}');
            $table->string('creation_status', 32);
            $table->string('validation_status', 32);
            $table->jsonb('validation_findings')->default('[]');
            $table->timestampsTz();
            $table->primary(['blueprint_id', 'cognitive_type']);
            $table->foreign('blueprint_id')->references('blueprint_id')->on('kernel_blueprint_runs')->cascadeOnDelete();
        });
        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table): void {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);
            $table->foreign('blueprint_id')->references('blueprint_id')->on('kernel_blueprint_runs')->cascadeOnDelete();
        });
    }

    private function quotedSchema(): string
    {
        return '"' . $this->schemaName . '"';
    }
}