<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelBlueprintProvisioner;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\KernelRotationStateRepository;
use App\Services\QuestionBank\Rotation\TaxonomyBlueprintIdReceiver;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PostgreSQL integration coverage for KRP v4 / DEC-119.
 *
 * The suite builds the exact tables it needs in a random isolated schema and
 * verifies that no relation in the public schema is created, replaced or used.
 */
class KernelRotationPlannerPostgresTest extends TestCase
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
        $this->schemaName = 'test_krp_v4_pg_' . bin2hex(random_bytes(6));

        DB::connection('pgsql')->statement('CREATE SCHEMA ' . $this->quotedSchemaName());
        $this->schemaCreated = true;

        $this->configureIsolatedConnection('pgsql');
        $this->configureIsolatedConnection('krp_lock_worker');
        $this->assertIsolatedSchemaActive(DB::connection('pgsql'));
        $this->assertIsolatedSchemaActive(DB::connection('krp_lock_worker'));

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

            foreach (['pgsql', 'krp_lock_worker'] as $connection) {
                DB::purge($connection);
            }

            parent::tearDown();
        }
    }

    public function test_for_update_lock_is_enforced_inside_the_isolated_schema(): void
    {
        $this->insertActiveState(
            2,
            'geographie',
            '01995d4c-4ab0-7000-8000-000000000010',
            'bp-lock-owner',
        );

        $owner = DB::connection('pgsql');
        $contender = DB::connection('krp_lock_worker');
        $owner->beginTransaction();

        try {
            $owner->select('SELECT id FROM kernel_rotation_state_v2 FOR UPDATE');

            try {
                $contender->select('SELECT id FROM kernel_rotation_state_v2 FOR UPDATE NOWAIT');
                $this->fail('The second PostgreSQL connection must observe lock contention.');
            } catch (QueryException $exception) {
                $this->assertSame('55P03', $exception->errorInfo[0] ?? null);
            }
        } finally {
            if ($owner->transactionLevel() > 0) {
                $owner->rollBack();
            }
        }
    }

    public function test_orchestrator_assigns_the_initial_rotation_with_v4_tour_state(): void
    {
        $orchestrator = $this->newOrchestrator();
        $blueprintId = (new KernelBlueprintProvisioner())->provisionForTest('test:krp:initial');
        $result = $orchestrator->runProvisioned($blueprintId);

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame($blueprintId, $result['blueprint_id']);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNotNull($state);
        $this->assertSame(2, (int) $state->active_depth);
        $this->assertNotNull($state->active_tour_id);
        $this->assertSame('OPEN', $state->tour_state);
        $this->assertNull($state->last_closed_tour_id);
        $this->assertSame(0, (int) $state->domain_position);

        $domainStates = json_decode((string) $state->domain_states, true);
        $this->assertSame('VISIBLE', $domainStates['2']['geographie']);
        $this->assertSame(
            'ENGAGED_IN_PIPELINE',
            DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->value('execution_state'),
        );
        $this->assertSame(2, (int) DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)->value('depth'));
        $this->assertSame('geographie', DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)->value('domain_code'));
    }

    public function test_last_visible_domain_can_close_the_final_need_and_persist_hold(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }
        DB::table('kernel_depth_matrix')
            ->where('depth', 10)
            ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[10] - 1]);

        $domainStates = $this->visibleDomainStates();
        foreach (self::DOMAINS as $domain) {
            $domainStates['10'][$domain] = $domain === 'science' ? 'VISIBLE' : 'ESTOMPÉ';
        }

        $closedTourId = '01995d4c-4ab0-7000-8000-000000000011';
        $this->insertActiveState(
            10,
            'science',
            $closedTourId,
            'bp-final-active',
            $domainStates,
        );

        (new KernelRotationPlanner())->receiveTaxonomyTerminalFact(
            'fact-final-required-tour',
            10,
            'science',
        );

        $result = $this->newOrchestrator()->runProvisioned(
            (new KernelBlueprintProvisioner())->provisionForTest('test:krp:hold'),
        );
        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);
        $this->assertNull($result['blueprint_id']);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame('PRODUCTION_ON_HOLD', $state->depth_state);
        $this->assertSame('CLOSED', $state->tour_state);
        $this->assertSame($closedTourId, $state->last_closed_tour_id);
        $this->assertSame(10, (int) $state->last_closed_depth);
        $this->assertNull($state->active_blueprint_identity);
        $this->assertSame(
            DepthNeedMatrix::CYCLE_TARGET[10],
            (int) DB::table('kernel_depth_matrix')
                ->where('depth', 10)
                ->value('cycle_completed'),
        );
        $this->assertNotNull(
            DB::table('kernel_taxonomy_terminal_facts')
                ->where('fact_id', 'fact-final-required-tour')
                ->value('consumed_at'),
        );
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_removed_external_exhaustion_entries_are_explicitly_rejected(): void
    {
        $planner = new KernelRotationPlanner();

        try {
            $planner->receiveDomainExhausted(2, 'geographie');
            $this->fail('The removed domain-exhaustion entry must reject callers.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Entrée v3', $exception->getMessage());
        }

        try {
            $planner->receiveDepthExhausted(2);
            $this->fail('The removed depth-exhaustion entry must reject callers.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('interne à KRP v4.0', $exception->getMessage());
        }
    }

    public function test_rotation_persists_the_existing_mechanism_result_atomically_and_transmits_only_its_id(): void
    {
        $blueprintId = (new KernelBlueprintProvisioner())
            ->provisionForTest('test:krp:persistent-id-boundary');
        $this->assertIsString($blueprintId);
        $recorder = new class implements TaxonomyBlueprintIdReceiver {
            public ?string $receivedBlueprintId = null;

            public function process(string $blueprintId): void
            {
                $this->receivedBlueprintId = $blueprintId;
            }
        };
        $orchestrator = new KernelPipelineOrchestrator(
            new KernelRotationPlanner(),
            new KernelRotationStateRepository(),
            new \App\Services\QuestionBank\Rotation\KernelBlueprintProvisionedLoader(),
            $recorder,
        );

        $before = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)
            ->first();
        $this->assertNotNull($before);
        $this->assertNull($before->depth);
        $this->assertNull($before->domain_code);
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $blueprintId)->count());
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $blueprintId)
            ->where('creation_status', 'EMPTY')
            ->where('validation_status', 'NOT_VALIDATED')
            ->count());

        DB::statement(
            'ALTER TABLE kernel_blueprint_runs '
            . 'ADD CONSTRAINT reject_rotation_write CHECK (domain_code IS NULL)'
        );

        try {
            $orchestrator->runProvisioned($blueprintId);
            $this->fail('The simulated Rotation persistence failure must roll back.');
        } catch (QueryException) {
            $afterFailure = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->first();
            $this->assertNull($afterFailure->depth);
            $this->assertNull($afterFailure->domain_code);
            $this->assertNull($recorder->receivedBlueprintId);
            $this->assertSame(0, DB::table('kernel_rotation_state_v2')->count());
        } finally {
            DB::statement(
                'ALTER TABLE kernel_blueprint_runs DROP CONSTRAINT reject_rotation_write'
            );
        }

        $result = $orchestrator->runProvisioned($blueprintId);
        $state = DB::table('kernel_rotation_state_v2')->first();
        $persisted = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)
            ->first();
        $determinedDomain = self::DOMAINS[(int) $state->domain_position];

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame($blueprintId, $result['blueprint_id']);
        $this->assertSame($blueprintId, $persisted->blueprint_id);
        $this->assertSame((int) $state->active_depth, (int) $persisted->depth);
        $this->assertSame($determinedDomain, $persisted->domain_code);
        $this->assertSame($blueprintId, $state->active_blueprint_identity);
        $this->assertSame($blueprintId, $recorder->receivedBlueprintId);
        $this->assertNull($persisted->kernel_code ?? null);
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $blueprintId)
            ->where('creation_status', 'EMPTY')
            ->where('validation_status', 'NOT_VALIDATED')
            ->count());
        $this->assertSame(0, DB::table('kernel_taxonomy_terminal_facts')->count());
    }

    public function test_rotation_rejects_a_slot_with_source_content_even_when_its_status_is_empty(): void
    {
        $blueprintId = (new KernelBlueprintProvisioner())
            ->provisionForTest('test:krp:source-content-gate');
        $source = KernelBlueprint::emptyCognitiveSlotSource('QCM_RECOGNITION');
        $source['question'] = 'Contenu intellectuel prématuré';
        DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['source' => json_encode($source, JSON_UNESCAPED_UNICODE)]);

        $recorder = new class implements TaxonomyBlueprintIdReceiver {
            public ?string $receivedBlueprintId = null;

            public function process(string $blueprintId): void
            {
                $this->receivedBlueprintId = $blueprintId;
            }
        };
        $orchestrator = new KernelPipelineOrchestrator(
            new KernelRotationPlanner(),
            new KernelRotationStateRepository(),
            new \App\Services\QuestionBank\Rotation\KernelBlueprintProvisionedLoader(),
            $recorder,
        );

        try {
            $orchestrator->runProvisioned($blueprintId);
            $this->fail('Rotation must reject a source-populated EMPTY slot.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('CognitiveSlot non vide', $exception->getMessage());
        }

        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)
            ->first();
        $this->assertNull($run->depth);
        $this->assertNull($run->domain_code);
        $this->assertSame('CREATED_UNENGAGED', $run->execution_state);
        $this->assertNull($recorder->receivedBlueprintId);
        $this->assertSame(0, DB::table('kernel_rotation_state_v2')->count());
    }

    private function newOrchestrator(): KernelPipelineOrchestrator
    {
        $stateRepository = new KernelRotationStateRepository();

        return new KernelPipelineOrchestrator(new KernelRotationPlanner(), $stateRepository);
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

        Schema::connection('pgsql')->create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->timestampTz('engaged_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampsTz();
            $table->index('execution_state');
        });

        Schema::connection('pgsql')->create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->jsonb('source')->nullable();
            $table->jsonb('creation_failure')->nullable();
            $table->jsonb('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
            $table->jsonb('validation_findings')->default('[]');
            $table->timestampsTz();
            $table->primary(['blueprint_id', 'cognitive_type']);
            $table->foreign('blueprint_id')
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->cascadeOnDelete();
        });

        Schema::connection('pgsql')->create('kernel_blueprint_request_refs', function (Blueprint $table): void {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);
            $table->foreign('blueprint_id')
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->cascadeOnDelete();
        });

        DB::statement(
            "CREATE UNIQUE INDEX one_active_blueprint_idx
             ON kernel_blueprint_runs ((1))
             WHERE execution_state IN ('CREATED_UNENGAGED', 'ENGAGED_IN_PIPELINE')"
        );
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
      'kernel_depth_matrix',
      'kernel_blueprint_runs'
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