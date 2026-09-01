<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Testing\KernelBlueprintManualPreconditions;
use App\Services\QuestionBank\Testing\KernelBlueprintTestHarness;
use App\Services\QuestionBank\Testing\KernelBlueprintTestPhase;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Démonstration intégrée du harness manuel dans un schéma PostgreSQL dédié.
 *
 * Aucune migration n'est appelée. Les deux seules tables sont créées
 * directement dans le schéma aléatoire, puis celui-ci est supprimé en tearDown.
 */
final class KernelBlueprintTestHarnessPostgresTest extends TestCase
{
    private const ISOLATED_CONNECTION = 'manual_blueprint_test';

    private string $schemaName = '';
    private string $originalDefaultConnection = '';
    private mixed $originalSearchPath = null;
    private mixed $originalDedicatedConfig = null;
    private bool $schemaCreated = false;

    /** @var array<int, array{relation_name: string, relation_oid: string}> */
    private array $publicRelationOids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');
        $this->originalDedicatedConfig = config(
            'database.connections.' . self::ISOLATED_CONNECTION,
        );

        $this->schemaName = 'test_manual_blueprint_' . bin2hex(random_bytes(6));
        $baseConnection = DB::connection('pgsql');
        $this->publicRelationOids = $this->snapshotPublicRelationOids($baseConnection);
        $baseConnection->statement('CREATE SCHEMA ' . $this->quotedSchemaName());
        $this->schemaCreated = true;

        $configuration = config('database.connections.pgsql');
        $configuration['search_path'] = $this->quotedSchemaName();
        config([
            'database.connections.' . self::ISOLATED_CONNECTION => $configuration,
            'database.default' => self::ISOLATED_CONNECTION,
        ]);
        DB::purge(self::ISOLATED_CONNECTION);
        DB::reconnect(self::ISOLATED_CONNECTION);
        DB::setDefaultConnection(self::ISOLATED_CONNECTION);

        $connection = DB::connection(self::ISOLATED_CONNECTION);
        $this->assertSame('pgsql', $connection->getDriverName());
        $this->assertIsolatedSchemaActive($connection);
        $this->createHarnessSchema($connection);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->schemaCreated) {
                $baseConnection = DB::connection('pgsql');
                $baseConnection->statement(
                    'DROP SCHEMA IF EXISTS ' . $this->quotedSchemaName() . ' CASCADE',
                );
                $this->schemaCreated = false;

                $schemaCount = (int) ($baseConnection->selectOne(
                    'SELECT COUNT(*) AS schema_count '
                    . 'FROM pg_catalog.pg_namespace WHERE nspname = ?',
                    [$this->schemaName],
                )->schema_count ?? -1);
                $this->assertSame(0, $schemaCount);
                $this->assertSame(
                    $this->publicRelationOids,
                    $this->snapshotPublicRelationOids($baseConnection),
                    'Les relations public ne doivent pas changer.',
                );
            }
        } finally {
            DB::setDefaultConnection($this->originalDefaultConnection);
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.pgsql.search_path' => $this->originalSearchPath,
                'database.connections.' . self::ISOLATED_CONNECTION => $this->originalDedicatedConfig,
            ]);
            DB::purge(self::ISOLATED_CONNECTION);
            DB::purge('pgsql');
            parent::tearDown();
        }
    }

    public function test_real_factory_creates_seven_empty_slots_and_invokes_only_the_fake_phase_once(): void
    {
        $phase = new RecordingPhase();
        $harness = new KernelBlueprintTestHarness();
        $preconditions = $this->validPreconditions();

        $blueprintId = $harness->execute(
            $preconditions,
            $phase,
            function (KernelBlueprint $blueprint) use ($phase): string {
                $this->assertSame('CREATED_UNENGAGED', DB::table('kernel_blueprint_runs')
                    ->where('blueprint_id', $blueprint->blueprint_id)
                    ->value('execution_state'));
                $this->assertSame(1, DB::table('kernel_blueprint_runs')->count());
                $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')->count());
                $this->assertCount(7, $blueprint->cognitive_slots);
                $this->assertEqualsCanonicalizing(
                    KernelBlueprint::COGNITIVE_TYPES,
                    array_keys($blueprint->cognitive_slots),
                );
                $this->assertSame(1, $phase->calls);
                $this->assertSame($blueprint->blueprint_id, $phase->receivedBlueprintId);

                $parent = DB::table('kernel_blueprint_runs')
                    ->where('blueprint_id', $blueprint->blueprint_id)
                    ->first();
                $this->assertSame(4, (int) $parent->depth);
                $this->assertSame('science', $parent->domain_code);
                $this->assertSame('04-SCI-PHY-LUM-REF-0001', $parent->kernel_code);

                foreach ($blueprint->cognitive_slots as $type => $slot) {
                    $isQcm = str_starts_with($type, 'QCM_');
                    $this->assertSame($type, $slot['cognitive_type']);
                    $this->assertNull($slot['source']['question']);
                    $this->assertSame(
                        $isQcm
                            ? ['a' => null, 'b' => null, 'c' => null, 'd' => null]
                            : ['a' => null, 'b' => null],
                        $slot['source']['choices'],
                    );
                    $this->assertSame(
                        $isQcm || str_ends_with($type, '_TRUE') ? 'a' : 'b',
                        $slot['source']['correct_answer_key'],
                    );
                    $this->assertNull($slot['source']['sv']);
                    $this->assertNull($slot['source']['creation_evidence']);
                    $this->assertSame([], $slot['translations']);
                    $this->assertSame('EMPTY', $slot['creation_status']);
                    $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
                    $this->assertSame([], $slot['validation_findings']);
                    $this->assertNull($slot['creation_failure']);
                }

                $this->assertSame(
                    '{}',
                    DB::table('kernel_blueprint_cognitive_slots')
                        ->where('blueprint_id', $blueprint->blueprint_id)
                        ->value('translations'),
                );
                $this->assertSame(0, $this->relationCount('kernel_pipeline_outbox'));
                $this->assertSame(0, $this->relationCount('kernel_depth_matrix'));
                $this->assertSame(0, $this->relationCount('kernel_taxonomy_terminal_facts'));
                $this->assertSame(0, $this->relationCount('question_groups'));

                return (string) $blueprint->blueprint_id;
            },
        );

        $this->assertSame($phase->receivedBlueprintId, $blueprintId);
        $this->assertSame(1, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
    }

    public function test_phase_failure_still_cleans_parent_and_cascaded_slots(): void
    {
        $phase = new FailingPhase();
        $harness = new KernelBlueprintTestHarness();

        try {
            $harness->execute($this->validPreconditions(), $phase, static fn (): null => null);
            $this->fail('La phase simulée devait échouer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated phase failure', $exception->getMessage());
        }

        $this->assertSame(1, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
    }

    public function test_assertion_failure_still_cleans_parent_and_slots(): void
    {
        $harness = new KernelBlueprintTestHarness();
        $phase = new RecordingPhase();

        try {
            $harness->execute(
                $this->validPreconditions(),
                $phase,
                function (): never {
                    throw new AssertionFailedError('intentional inspection failure');
                },
            );
            $this->fail('Le callback devait échouer.');
        } catch (AssertionFailedError $exception) {
            $this->assertSame('intentional inspection failure', $exception->getMessage());
        }

        $this->assertSame(1, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
    }

    public function test_invalid_manual_preconditions_are_rejected_before_phase_and_cleaned(): void
    {
        $phase = new RecordingPhase();
        $harness = new KernelBlueprintTestHarness();
        $invalid = new KernelBlueprintManualPreconditions(
            depth: 0,
            domain: 'science',
            subdomainActive: 'Physique',
            subjectActive: 'Lumière',
            dominantIdeaActive: 'Réfraction',
            kernelCode: '04-SCI-PHY-LUM-REF-0001',
        );

        try {
            $harness->execute($invalid, $phase, static fn (): null => null);
            $this->fail('Les préconditions invalides devaient être rejetées.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('depth', $exception->getMessage());
        }

        $this->assertSame(0, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
    }

    private function validPreconditions(): KernelBlueprintManualPreconditions
    {
        return new KernelBlueprintManualPreconditions(
            depth: 4,
            domain: 'science',
            subdomainActive: 'Physique',
            subjectActive: 'Lumière',
            dominantIdeaActive: 'Réfraction',
            kernelCode: '04-SCI-PHY-LUM-REF-0001',
        );
    }

    private function createHarnessSchema(ConnectionInterface $connection): void
    {
        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'kernel_blueprint_runs',
            function (Blueprint $table): void {
                $table->string('blueprint_id', 36)->primary();
                $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
                $table->smallInteger('depth')->nullable();
                $table->string('domain_code', 64)->nullable();
                $table->string('kernel_code', 23)->nullable();
                $table->timestampTz('engaged_at')->nullable();
                $table->timestampTz('received_at')->nullable();
                $table->timestampsTz();
            },
        );

        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'kernel_blueprint_cognitive_slots',
            function (Blueprint $table): void {
                $table->string('blueprint_id', 36);
                $table->string('cognitive_type', 64);
                $table->jsonb('source');
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
            },
        );

        $this->assertIsolatedSchemaActive($connection);
    }

    private function assertIsolatedSchemaActive(ConnectionInterface $connection): void
    {
        $activeSchema = $connection->selectOne(
            'SELECT current_schema() AS schema_name',
        )->schema_name ?? null;
        $searchPath = (string) ($connection->selectOne('SHOW search_path')->search_path ?? '');

        $this->assertSame($this->schemaName, $activeSchema);
        $this->assertStringNotContainsString('public', strtolower($searchPath));
    }

    private function relationCount(string $relation): int
    {
        return (int) ($this->isolatedConnection()->selectOne(
            'SELECT COUNT(*) AS relation_count '
            . 'FROM pg_catalog.pg_class c '
            . 'JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace '
            . 'WHERE n.nspname = ? AND c.relname = ?',
            [$this->schemaName, $relation],
        )->relation_count ?? 0);
    }

    private function isolatedConnection(): ConnectionInterface
    {
        return DB::connection(self::ISOLATED_CONNECTION);
    }

    /**
     * @return array<int, array{relation_name: string, relation_oid: string}>
     */
    private function snapshotPublicRelationOids(ConnectionInterface $connection): array
    {
        return array_map(
            static fn (object $row): array => [
                'relation_name' => (string) $row->relation_name,
                'relation_oid' => (string) $row->relation_oid,
            ],
            $connection->select(
                "SELECT c.relname AS relation_name, c.oid::text AS relation_oid
                 FROM pg_catalog.pg_class c
                 JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public'
                   AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
                 ORDER BY c.relname",
            ),
        );
    }

    private function quotedSchemaName(): string
    {
        return '"' . str_replace('"', '""', $this->schemaName) . '"';
    }
}

final class RecordingPhase implements KernelBlueprintTestPhase
{
    public int $calls = 0;
    public ?string $receivedBlueprintId = null;

    public function run(KernelBlueprint $blueprint): void
    {
        $this->calls++;
        $this->receivedBlueprintId = $blueprint->blueprint_id;
    }
}

final class FailingPhase implements KernelBlueprintTestPhase
{
    public int $calls = 0;

    public function run(KernelBlueprint $blueprint): void
    {
        $this->calls++;
        throw new RuntimeException('simulated phase failure');
    }
}