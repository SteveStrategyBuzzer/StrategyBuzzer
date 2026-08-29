<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\KernelRotationStateRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PostgreSQL strict validation for the DEC-119 terminal-fact boundary.
 *
 * Every test owns a random schema. Both simulated workers use independent
 * connections whose search_path contains only that schema.
 */
class KernelRotationPlannerPostgresStrictTest extends TestCase
{
    private const DOMAINS = [
        'geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science',
    ];

    private string $schemaName;
    private string $originalDefaultConnection;
    private mixed $originalPgsqlSearchPath;
    private bool $schemaCreated = false;

    /** @var array<int, array{relation_name: string, relation_oid: string}> */
    private array $publicRelationOids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) config('database.default');
        $this->originalPgsqlSearchPath = config('database.connections.pgsql.search_path');

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->assertSame('pgsql', DB::connection('pgsql')->getDriverName());
        $this->publicRelationOids = $this->snapshotPublicRelationOids();
        $this->schemaName = 'test_krp_v4_strict_' . bin2hex(random_bytes(6));

        DB::connection('pgsql')->statement('CREATE SCHEMA ' . $this->quotedSchemaName());
        $this->schemaCreated = true;

        $this->configureIsolatedConnection('pgsql');
        $this->configureIsolatedConnection('krp_worker_a');
        $this->configureIsolatedConnection('krp_worker_b');
        DB::setDefaultConnection('pgsql');

        $this->assertIsolatedSchemaActive(DB::connection('pgsql'));
        $this->assertIsolatedSchemaActive(DB::connection('krp_worker_a'));
        $this->assertIsolatedSchemaActive(DB::connection('krp_worker_b'));

        $this->createKrpV4Schema();
        $this->seedDepthMatrix();
    }

    protected function tearDown(): void
    {
        try {
            $this->dropIsolatedSchema();
        } finally {
            DB::setDefaultConnection($this->originalDefaultConnection);
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.pgsql.search_path' => $this->originalPgsqlSearchPath,
            ]);

            foreach (['pgsql', 'krp_worker_a', 'krp_worker_b'] as $connection) {
                DB::purge($connection);
            }

            parent::tearDown();
        }
    }

    public function test_taxonomy_terminal_fact_replay_is_consumed_once_by_two_workers(): void
    {
        $tourId = '01995d4c-4ab0-7000-8000-000000000001';
        $this->insertActiveState(2, 'geographie', $tourId, 'bp-active-domain');

        $this->runAsWorker('krp_worker_a', static function (KernelRotationPlanner $planner): void {
            $planner->receiveTaxonomyTerminalFact('fact-domain-geographie', 2, 'geographie');
        });
        $this->runAsWorker('krp_worker_b', static function (KernelRotationPlanner $planner): void {
            $planner->receiveTaxonomyTerminalFact('fact-domain-geographie', 2, 'geographie');
        });

        $this->assertSame(1, DB::table('kernel_taxonomy_terminal_facts')->count());
        $this->assertNull(
            DB::table('kernel_taxonomy_terminal_facts')
                ->where('fact_id', 'fact-domain-geographie')
                ->value('consumed_at')
        );

        $firstBlueprint = $this->newBlueprint('bp-next-domain-a');
        $firstResolution = $this->prepareAsWorker('krp_worker_a', $firstBlueprint);

        $this->assertSame(2, $firstResolution->depth);
        $this->assertSame('histoire', $firstResolution->domain);
        $this->assertSame('histoire', $firstBlueprint->domain);

        $consumedAt = DB::table('kernel_taxonomy_terminal_facts')
            ->where('fact_id', 'fact-domain-geographie')
            ->value('consumed_at');
        $this->assertNotNull($consumedAt);

        $stateAfterFirstConsumption = DB::table('kernel_rotation_state_v2')->first();
        $domainStates = json_decode((string) $stateAfterFirstConsumption->domain_states, true);
        $this->assertSame('ESTOMPÉ', $domainStates['2']['geographie']);
        $this->assertSame('OPEN', $stateAfterFirstConsumption->tour_state);
        $this->assertNull($stateAfterFirstConsumption->last_closed_tour_id);

        $this->runAsWorker('krp_worker_b', static function (KernelRotationPlanner $planner): void {
            $planner->receiveTaxonomyTerminalFact('fact-domain-geographie', 2, 'geographie');
        });
        $secondBlueprint = $this->newBlueprint('bp-next-domain-b');
        $secondResolution = $this->prepareAsWorker('krp_worker_b', $secondBlueprint);

        $this->assertSame('faune', $secondResolution->domain);
        $this->assertSame(1, DB::table('kernel_taxonomy_terminal_facts')->count());
        $this->assertSame(
            $consumedAt,
            DB::table('kernel_taxonomy_terminal_facts')
                ->where('fact_id', 'fact-domain-geographie')
                ->value('consumed_at')
        );
        $this->assertSame(
            0,
            (int) DB::table('kernel_depth_matrix')->where('depth', 2)->value('cycle_completed')
        );
        $this->assertNull(DB::table('kernel_rotation_state_v2')->value('last_closed_tour_id'));
    }

    public function test_last_visible_domain_closes_tour_once_and_advances_depth_with_two_workers(): void
    {
        $closedTourId = '01995d4c-4ab0-7000-8000-000000000002';
        $domainStates = $this->visibleDomainStates();
        foreach (self::DOMAINS as $domain) {
            $domainStates['2'][$domain] = $domain === 'science' ? 'VISIBLE' : 'ESTOMPÉ';
        }

        $this->insertActiveState(
            2,
            'science',
            $closedTourId,
            'bp-active-final-domain',
            $domainStates,
        );

        $this->runAsWorker('krp_worker_a', static function (KernelRotationPlanner $planner): void {
            $planner->receiveTaxonomyTerminalFact('fact-final-science', 2, 'science');
        });
        $this->runAsWorker('krp_worker_b', static function (KernelRotationPlanner $planner): void {
            $planner->receiveTaxonomyTerminalFact('fact-final-science', 2, 'science');
        });

        $firstBlueprint = $this->newBlueprint('bp-depth-four-a');
        $firstResolution = $this->prepareAsWorker('krp_worker_a', $firstBlueprint);

        $stateAfterClosure = DB::table('kernel_rotation_state_v2')->first();
        $newTourId = (string) $stateAfterClosure->active_tour_id;

        $this->assertSame(4, $firstResolution->depth);
        $this->assertSame('geographie', $firstResolution->domain);
        $this->assertSame('geographie', $firstBlueprint->domain);
        $this->assertSame($closedTourId, $stateAfterClosure->last_closed_tour_id);
        $this->assertSame(2, (int) $stateAfterClosure->last_closed_depth);
        $this->assertSame('OPEN', $stateAfterClosure->tour_state);
        $this->assertNotSame($closedTourId, $newTourId);
        $this->assertSame(
            1,
            (int) DB::table('kernel_depth_matrix')->where('depth', 2)->value('cycle_completed')
        );
        $this->assertNotNull(
            DB::table('kernel_taxonomy_terminal_facts')
                ->where('fact_id', 'fact-final-science')
                ->value('consumed_at')
        );

        $this->runAsWorker('krp_worker_b', static function (KernelRotationPlanner $planner): void {
            $planner->receiveTaxonomyTerminalFact('fact-final-science', 2, 'science');
        });
        $secondBlueprint = $this->newBlueprint('bp-depth-four-b');
        $secondResolution = $this->prepareAsWorker('krp_worker_b', $secondBlueprint);

        $stateAfterReplay = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(4, $secondResolution->depth);
        $this->assertSame('histoire', $secondResolution->domain);
        $this->assertSame($newTourId, $stateAfterReplay->active_tour_id);
        $this->assertSame($closedTourId, $stateAfterReplay->last_closed_tour_id);
        $this->assertSame(
            1,
            (int) DB::table('kernel_depth_matrix')->where('depth', 2)->value('cycle_completed')
        );
        $this->assertSame(1, DB::table('kernel_taxonomy_terminal_facts')->count());
    }

    public function test_taxonomy_terminal_fact_with_contradictory_depth_is_rejected_on_postgres(): void
    {
        $this->insertActiveState(
            4,
            'geographie',
            '01995d4c-4ab0-7000-8000-000000000003',
            'bp-active-depth-four',
        );

        try {
            $this->runAsWorker('krp_worker_a', static function (KernelRotationPlanner $planner): void {
                $planner->receiveTaxonomyTerminalFact(
                    'fact-contradictory-depth',
                    6,
                    'geographie',
                );
            });
            $this->fail('A contradictory terminal fact must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'depth/domain ne correspondent pas au Blueprint KRP actif',
                $exception->getMessage(),
            );
        }

        $this->assertSame(0, DB::table('kernel_taxonomy_terminal_facts')->count());
        $this->assertSame(4, (int) DB::table('kernel_rotation_state_v2')->value('active_depth'));
    }

    private function configureIsolatedConnection(string $connectionName): void
    {
        $configuration = config('database.connections.pgsql');
        $configuration['search_path'] = $this->quotedSchemaName();

        config(["database.connections.{$connectionName}" => $configuration]);
        DB::purge($connectionName);
        DB::reconnect($connectionName);
    }

    private function createKrpV4Schema(): void
    {
        $this->assertIsolatedSchemaActive(DB::connection('pgsql'));

        Schema::connection('pgsql')->create('kernel_rotation_state_v2', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('active_depth')->nullable();
            $table->uuid('active_tour_id')->nullable();
            $table->string('tour_state', 16)->default('OPEN');
            $table->uuid('last_closed_tour_id')->nullable();
            $table->unsignedTinyInteger('last_closed_depth')->nullable();
            $table->string('depth_state', 64)->default('ROTATION_ACTIVE');
            $table->json('domain_states')->nullable();
            $table->integer('domain_position')->nullable();
            $table->string('active_blueprint_identity', 36)->nullable();
            $table->string('last_counted_blueprint_identity', 36)->nullable();
            $table->integer('pending_depth_exhausted_depth')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();
        });

        Schema::connection('pgsql')->create('kernel_taxonomy_terminal_facts', function (Blueprint $table): void {
            $table->id();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->uuid('tour_id');
            $table->timestampTz('received_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
            $table->index(
                ['depth', 'tour_id', 'consumed_at', 'received_at'],
                'kttf_pending_tour_idx',
            );
        });

        Schema::connection('pgsql')->create('kernel_depth_matrix', function (Blueprint $table): void {
            $table->smallInteger('depth')->primary();
            $table->integer('cycle_target');
            $table->integer('cycle_completed')->default(0);
            $table->smallInteger('empty_progress_current_tour')->default(0);
            $table->string('current_tour_id', 36)->nullable();
            $table->timestampsTz();
        });
    }

    private function seedDepthMatrix(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->insert([
                'depth' => $depth,
                'cycle_target' => DepthNeedMatrix::CYCLE_TARGET[$depth],
                'cycle_completed' => 0,
                'empty_progress_current_tour' => 0,
                'current_tour_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param array<string, array<string, string>>|null $domainStates
     */
    private function insertActiveState(
        int $depth,
        string $domain,
        string $tourId,
        string $blueprintId,
        ?array $domainStates = null,
    ): void {
        $position = array_search($domain, DepthTourState::DOMAIN_CYCLE, true);
        $this->assertIsInt($position);

        DB::table('kernel_rotation_state_v2')->insert([
            'active_depth' => $depth,
            'active_tour_id' => $tourId,
            'tour_state' => 'OPEN',
            'last_closed_tour_id' => null,
            'last_closed_depth' => null,
            'depth_state' => 'ROTATION_ACTIVE',
            'domain_states' => json_encode($domainStates ?? $this->visibleDomainStates()),
            'domain_position' => $position,
            'active_blueprint_identity' => $blueprintId,
            'last_counted_blueprint_identity' => null,
            'pending_depth_exhausted_depth' => null,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function visibleDomainStates(): array
    {
        $states = [];
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $states[(string) $depth] = array_fill_keys(self::DOMAINS, 'VISIBLE');
        }

        return $states;
    }

    private function newBlueprint(string $blueprintId): KernelBlueprint
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($blueprintId);

        return $blueprint;
    }

    private function prepareAsWorker(string $connection, KernelBlueprint $blueprint): object
    {
        return $this->runAsWorker(
            $connection,
            static function (KernelRotationPlanner $planner) use ($blueprint): object {
                return DB::transaction(static function () use ($planner, $blueprint): object {
                    $state = (new KernelRotationStateRepository())->firstForUpdate();

                    return $planner->prepareNewBlueprint($blueprint, $state);
                });
            },
        );
    }

    private function runAsWorker(string $connection, callable $callback): mixed
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            $this->assertIsolatedSchemaActive(DB::connection($connection));

            return $callback(new KernelRotationPlanner());
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    private function assertIsolatedSchemaActive(ConnectionInterface $connection): void
    {
        $activeSchema = $connection->selectOne(
            'SELECT current_schema() AS schema_name'
        )->schema_name ?? null;
        $searchPath = $connection->selectOne('SHOW search_path')->search_path ?? '';

        if ($activeSchema !== $this->schemaName || str_contains(strtolower($searchPath), 'public')) {
            throw new \RuntimeException(
                'KRP v4 tests require an exclusively isolated PostgreSQL schema.',
            );
        }

        $this->assertSame($this->schemaName, $activeSchema);
    }

    private function dropIsolatedSchema(): void
    {
        if (! $this->schemaCreated) {
            return;
        }

        DB::setDefaultConnection('pgsql');
        DB::connection('pgsql')->statement(
            'DROP SCHEMA IF EXISTS ' . $this->quotedSchemaName() . ' CASCADE'
        );
        $this->schemaCreated = false;

        $schemaCount = (int) (DB::connection('pgsql')->selectOne(
            'SELECT COUNT(*) AS schema_count '
            . 'FROM pg_catalog.pg_namespace WHERE nspname = ?',
            [$this->schemaName],
        )->schema_count ?? -1);

        $this->assertSame(0, $schemaCount, 'The temporary PostgreSQL schema must be removed.');
        $this->assertSame(
            $this->publicRelationOids,
            $this->snapshotPublicRelationOids(),
            'Relations in the public schema must remain untouched.',
        );
    }

    /**
     * @return array<int, array{relation_name: string, relation_oid: string}>
     */
    private function snapshotPublicRelationOids(): array
    {
        return array_map(
            static fn (object $row): array => [
                'relation_name' => (string) $row->relation_name,
                'relation_oid' => (string) $row->relation_oid,
            ],
            DB::connection('pgsql')->select(<<<'SQL'
SELECT relation.relname AS relation_name, relation.oid::text AS relation_oid
FROM pg_catalog.pg_class AS relation
INNER JOIN pg_catalog.pg_namespace AS namespace ON namespace.oid = relation.relnamespace
WHERE namespace.nspname = 'public'
  AND relation.relname IN (
      'kernel_rotation_state_v2',
      'kernel_taxonomy_terminal_facts',
      'kernel_depth_matrix'
  )
ORDER BY relation.relname
SQL),
        );
    }

    private function quotedSchemaName(): string
    {
        return '"' . $this->schemaName . '"';
    }
}