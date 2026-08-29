<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

/**
 * Couverture préparée pour la migration DEC-121 v2.2 des bassins legacy.
 *
 * Ces tests ciblent PostgreSQL, car la migration utilise le SQL transactionnel
 * PostgreSQL et la colonne historique CHAR(2) élargie en CHAR(3).
 *
 * Ils sont volontairement préparés mais non exécutés dans le cadre de la
 * reconstruction contrôlée.
 */
class KernelCodeSequenceLegacyMigrationPostgresTest extends TestCase
{
    private const STRUCTURAL_MIGRATION = 'database/migrations/2026_08_28_000001_expand_kernel_code_for_dec_121_v22.php';
    private const LEGACY_MIGRATION = 'database/migrations/2026_08_28_000002_migrate_legacy_kernel_code_sequences.php';
    private const LEGACY_TO_OFFICIAL = [
        'GE' => 'GEO',
        'HI' => 'HIS',
        'FA' => 'FAU',
        'AR' => 'ART',
        'SP' => 'SPO',
        'CI' => 'CIN',
        'CU' => 'CUI',
        'SC' => 'SCI',
    ];

    private static ?string $isolatedSchemaName = null;
    private static bool $schemaReported = false;

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

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->publicRelationOids = $this->snapshotPublicRelationOids();

        self::$isolatedSchemaName ??= 'test_dec121_' . bin2hex(random_bytes(6));
        $this->schemaName = self::$isolatedSchemaName;

        DB::connection('pgsql')->statement(
            'CREATE SCHEMA ' . $this->quotedSchemaName()
        );
        $this->schemaCreated = true;

        config([
            'database.connections.pgsql.search_path' => $this->quotedSchemaName(),
        ]);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->assertIsolatedSchemaActive();
        $this->createPreDec121Schema();
        $this->runMigrationFile(self::STRUCTURAL_MIGRATION);

        if (! self::$schemaReported) {
            fwrite(STDOUT, "\nDEC121_TEMP_SCHEMA={$this->schemaName}\n");
            self::$schemaReported = true;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->dropIsolatedSchema();
        } finally {
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.pgsql.search_path' => $this->originalPgsqlSearchPath,
            ]);
            DB::purge('pgsql');
            DB::reconnect('pgsql');

            parent::tearDown();
        }
    }

    public function test_legacy_only_creates_official_with_same_counter_and_deletes_legacy(): void
    {
        $this->insertSequence(4, 'GE', 42);

        $this->runMigration();

        $this->assertSequence(4, 'GEO', 42);
        $this->assertSequenceAbsent(4, 'GE');
    }

    public function test_official_only_is_preserved(): void
    {
        $this->insertSequence(4, 'GEO', 19);

        $this->runMigration();

        $this->assertSequence(4, 'GEO', 19);
    }

    public function test_all_legacy_domains_map_to_their_official_basin(): void
    {
        $depth = 2;
        foreach (self::LEGACY_TO_OFFICIAL as $legacyCode => $officialCode) {
            $this->insertSequence($depth, $legacyCode, 73);
            $depth++;
        }

        $this->runMigration();

        $depth = 2;
        foreach (self::LEGACY_TO_OFFICIAL as $legacyCode => $officialCode) {
            $this->assertSequence($depth, $officialCode, 73);
            $this->assertSequenceAbsent($depth, $legacyCode);
            $depth++;
        }
    }

    public function test_identical_legacy_and_official_counters_keep_one_official_basin(): void
    {
        $this->insertSequence(4, 'GE', 19);
        $this->insertSequence(4, 'GEO', 19);

        $this->runMigration();

        $this->assertSequence(4, 'GEO', 19);
        $this->assertSequenceAbsent(4, 'GE');
        $this->assertCanonicalCount(4, 'GEO', 1);
    }

    public function test_legacy_superior_counter_wins_without_addition(): void
    {
        $this->insertSequence(4, 'GE', 41);
        $this->insertSequence(4, 'GEO', 19);

        $this->runMigration();

        $this->assertSequence(4, 'GEO', 41);
        $this->assertSequenceAbsent(4, 'GE');
    }

    public function test_official_superior_counter_wins_without_addition(): void
    {
        $this->insertSequence(4, 'GE', 19);
        $this->insertSequence(4, 'GEO', 41);

        $this->runMigration();

        $this->assertSequence(4, 'GEO', 41);
        $this->assertSequenceAbsent(4, 'GE');
    }

    public function test_unknown_legacy_domain_is_rejected(): void
    {
        $this->insertSequence(4, 'ZZ', 7);

        $this->expectMigrationFailure('code domaine legacy inconnu');
    }

    public function test_negative_counter_is_rejected(): void
    {
        $this->insertSequence(4, 'GE', -1);

        $this->expectMigrationFailure('next_value négatif');
    }

    public function test_counter_above_suffix_capacity_is_rejected(): void
    {
        $this->insertSequence(4, 'GE', 1_679_617);

        $this->expectMigrationFailure('dépasse la capacité');
    }

    public function test_failure_is_atomic_and_does_not_create_official_basin(): void
    {
        $this->insertSequence(4, 'GE', 42);
        $this->insertSequence(4, 'ZZ', 7);

        try {
            $this->runMigration();
            $this->fail('La migration devait échouer atomiquement.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('code domaine legacy inconnu', $exception->getMessage());
        }

        $this->assertSequence(4, 'GE', 42);
        $this->assertSequenceAbsent(4, 'GEO');
    }

    public function test_historical_kernel_code_is_not_modified(): void
    {
        $historicalCode = '04-GE-CAN-CON-ACT-000A';

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'bp-historical',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth'           => 4,
            'domain_code'     => 'Géographie',
            'kernel_code'     => $historicalCode,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $this->insertSequence(4, 'GE', 10);

        $this->runMigration();

        $this->assertSame(
            $historicalCode,
            DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', 'bp-historical')
                ->value('kernel_code')
        );
    }

    public function test_next_allocation_continues_exactly_at_merged_counter(): void
    {
        $this->insertSequence(4, 'GE', 41);
        $this->insertSequence(4, 'GEO', 19);
        $this->runMigration();

        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId('bp-next-allocation');
        $blueprint->fillRotation(4, 'Géographie');
        $blueprint->fillTaxonomy('Canada', 'Confédération canadienne', 'Acte');

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'bp-next-allocation',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth'           => 4,
            'domain_code'     => 'Géographie',
            'kernel_code'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $code = (new KernelCodeEngine())->assignKernelCode($blueprint);

        $this->assertSame('04-GEO-CAN-CON-ACT-0015', $code);
        $this->assertSame(42, (int) DB::table('kernel_code_sequences')
            ->where('depth', 4)
            ->where('domain_code', 'GEO')
            ->value('next_value'));
    }

    private function runMigration(): void
    {
        $this->runMigrationFile(self::LEGACY_MIGRATION);
    }

    private function runMigrationFile(string $path): void
    {
        $this->assertIsolatedSchemaActive();

        $migration = require base_path($path);
        $migration->up();
    }

    private function createPreDec121Schema(): void
    {
        $this->assertIsolatedSchemaActive();

        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('ENGAGED_IN_PIPELINE');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 22)->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_code_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('depth');
            $table->char('domain_code', 2);
            $table->integer('next_value')->default(0);
            $table->timestamps();
            $table->primary(['depth', 'domain_code']);
        });
    }

    private function assertIsolatedSchemaActive(): void
    {
        $connection = DB::connection('pgsql');
        $activeSchema = $connection->selectOne(
            'SELECT current_schema() AS schema_name'
        )->schema_name ?? null;
        $searchPath = $connection->selectOne('SHOW search_path')->search_path ?? '';

        if ($activeSchema !== $this->schemaName || str_contains(strtolower($searchPath), 'public')) {
            throw new \RuntimeException(
                'Protection DEC-121 : le schéma PostgreSQL actif doit être exclusivement isolé.'
            );
        }

        $this->assertSame($this->schemaName, $activeSchema);
    }

    private function dropIsolatedSchema(): void
    {
        if (! $this->schemaCreated) {
            return;
        }

        DB::connection('pgsql')->statement(
            'DROP SCHEMA IF EXISTS ' . $this->quotedSchemaName() . ' CASCADE'
        );
        $this->schemaCreated = false;

        $schemaCount = (int) (DB::connection('pgsql')->selectOne(
            'SELECT COUNT(*) AS schema_count '
            . 'FROM pg_catalog.pg_namespace WHERE nspname = ?',
            [$this->schemaName]
        )->schema_count ?? -1);

        $this->assertSame(0, $schemaCount, 'Le schéma PostgreSQL temporaire doit être supprimé.');
        $this->assertSame(
            $this->publicRelationOids,
            $this->snapshotPublicRelationOids(),
            'Les tables DEC-121 du schéma public ne doivent jamais être remplacées.'
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
SELECT
    relation.relname AS relation_name,
    relation.oid::text AS relation_oid
FROM pg_catalog.pg_class AS relation
INNER JOIN pg_catalog.pg_namespace AS namespace
    ON namespace.oid = relation.relnamespace
WHERE namespace.nspname = 'public'
  AND relation.relname IN (
      'kernel_blueprint_runs',
      'kernel_code_sequences'
  )
ORDER BY relation.relname
SQL)
        );
    }

    private function quotedSchemaName(): string
    {
        return '"' . $this->schemaName . '"';
    }

    private function expectMigrationFailure(string $message): void
    {
        try {
            $this->runMigration();
            $this->fail('La migration devait être refusée.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function insertSequence(int $depth, string $domainCode, int $nextValue): void
    {
        DB::table('kernel_code_sequences')->insert([
            'depth'       => $depth,
            'domain_code' => $domainCode,
            'next_value'  => $nextValue,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function assertSequence(int $depth, string $domainCode, int $expected): void
    {
        $this->assertSame($expected, (int) DB::table('kernel_code_sequences')
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->value('next_value'));
    }

    private function assertSequenceAbsent(int $depth, string $domainCode): void
    {
        $this->assertFalse(DB::table('kernel_code_sequences')
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->exists());
    }

    private function assertCanonicalCount(int $depth, string $domainCode, int $expected): void
    {
        $this->assertSame($expected, DB::table('kernel_code_sequences')
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->count());
    }
}