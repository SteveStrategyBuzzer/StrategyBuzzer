<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KernelCodeStructuralMigrationCompatibilityPostgresTest extends TestCase
{
    private const STRUCTURAL_MIGRATION = 'database/migrations/2026_08_28_000001_expand_kernel_code_for_dec_121_v22.php';
    private const LEGACY_MIGRATION = 'database/migrations/2026_08_28_000002_migrate_legacy_kernel_code_sequences.php';
    private const PHASED_MIGRATION = 'database/migrations/2026_09_08_000001_authorize_phased_kernel_blueprint_persistence.php';

    private string $schemaName;
    private string $originalDefaultConnection;
    private mixed $originalSearchPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->schemaName = 'test_kernel_structural_' . bin2hex(random_bytes(6));
        DB::connection('pgsql')->statement('CREATE SCHEMA ' . $this->quotedSchema());

        config(['database.connections.pgsql.search_path' => $this->quotedSchema()]);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->assertIsolatedSchema();
        $this->createHistoricalSchema();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('pgsql')->statement(
                'DROP SCHEMA IF EXISTS ' . $this->quotedSchema() . ' CASCADE'
            );
        } finally {
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.pgsql.search_path' => $this->originalSearchPath,
            ]);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            parent::tearDown();
        }
    }

    public function test_path_a_expands_writable_code_then_reaches_segmented_projection(): void
    {
        $this->runMigration(self::STRUCTURAL_MIGRATION);

        $ordinary = $this->kernelCodeMetadata();
        $this->assertSame('NEVER', $ordinary->is_generated);
        $this->assertSame(23, (int) $ordinary->character_maximum_length);
        $this->assertSequenceDomainIsVarcharThree();

        $this->insertLegacySequence();
        $this->runMigration(self::LEGACY_MIGRATION);
        $this->assertLegacySequenceMigrated();

        $this->runMigration(self::PHASED_MIGRATION);

        $generated = $this->kernelCodeMetadata();
        $this->assertSame('ALWAYS', $generated->is_generated);
        $this->assertSame(23, (int) $generated->character_maximum_length);
        $this->assertNotNull($generated->generation_expression);
        $this->assertSame($this->expectedSegments(), $this->segmentMetadata());
    }

    public function test_path_b_preserves_existing_segmented_projection_exactly(): void
    {
        $this->runMigration(self::PHASED_MIGRATION);
        $before = $this->phasedContractSnapshot();

        $this->runMigration(self::STRUCTURAL_MIGRATION);
        $after = $this->phasedContractSnapshot();

        $this->assertSame($before, $after);
        $this->assertSequenceDomainIsVarcharThree();

        $structuralMigration = require base_path(self::STRUCTURAL_MIGRATION);
        $structuralMigration->down();
        $this->assertSame($before, $this->phasedContractSnapshot());
        $this->assertSequenceDomainIsCharTwo();

        $structuralMigration->up();
        $this->assertSame($before, $this->phasedContractSnapshot());
        $this->assertSequenceDomainIsVarcharThree();

        $this->insertLegacySequence();
        $this->runMigration(self::LEGACY_MIGRATION);
        $this->assertLegacySequenceMigrated();
    }

    private function createHistoricalSchema(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 22)->nullable();
            $table->timestampsTz();
        });

        Schema::create('kernel_code_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('depth');
            $table->char('domain_code', 2);
            $table->integer('next_value')->default(0);
            $table->timestampsTz();
            $table->primary(['depth', 'domain_code']);
        });
    }

    private function runMigration(string $path): void
    {
        $this->assertIsolatedSchema();
        (require base_path($path))->up();
    }

    private function kernelCodeMetadata(): object
    {
        return DB::connection('pgsql')->selectOne(<<<'SQL'
SELECT
    data_type,
    character_maximum_length,
    is_generated,
    generation_expression
FROM information_schema.columns
WHERE table_schema = current_schema()
  AND table_name = 'kernel_blueprint_runs'
  AND column_name = 'kernel_code'
SQL);
    }

    private function phasedContractSnapshot(): array
    {
        return [
            'kernel_code' => (array) $this->kernelCodeMetadata(),
            'segments' => $this->segmentMetadata(),
            'constraints' => array_map(
                static fn (object $constraint): array => (array) $constraint,
                DB::connection('pgsql')->select(<<<'SQL'
SELECT constraint_name, check_clause
FROM information_schema.check_constraints
WHERE constraint_schema = current_schema()
  AND constraint_name LIKE 'kernel_blueprint_runs_%_chk'
ORDER BY constraint_name
SQL)
            ),
            'index' => array_map(
                static fn (object $index): array => (array) $index,
                DB::connection('pgsql')->select(<<<'SQL'
SELECT indexdef
FROM pg_indexes
WHERE schemaname = current_schema()
  AND indexname = 'kernel_blueprint_runs_kernel_code_unique'
SQL)
            ),
        ];
    }

    private function segmentMetadata(): array
    {
        return array_map(
            static fn (object $column): array => (array) $column,
            DB::connection('pgsql')->select(<<<'SQL'
SELECT column_name, data_type, character_maximum_length, is_nullable, is_generated
FROM information_schema.columns
WHERE table_schema = current_schema()
  AND table_name = 'kernel_blueprint_runs'
  AND column_name IN (
      'kernel_code_dd',
      'kernel_code_do',
      'kernel_code_sub',
      'kernel_code_suj',
      'kernel_code_ide',
      'kernel_code_vvvv'
  )
ORDER BY ordinal_position
SQL)
        );
    }

    private function expectedSegments(): array
    {
        return [
            ['column_name' => 'kernel_code_dd', 'data_type' => 'character varying', 'character_maximum_length' => 2, 'is_nullable' => 'YES', 'is_generated' => 'NEVER'],
            ['column_name' => 'kernel_code_do', 'data_type' => 'character varying', 'character_maximum_length' => 3, 'is_nullable' => 'YES', 'is_generated' => 'NEVER'],
            ['column_name' => 'kernel_code_sub', 'data_type' => 'character varying', 'character_maximum_length' => 3, 'is_nullable' => 'YES', 'is_generated' => 'NEVER'],
            ['column_name' => 'kernel_code_suj', 'data_type' => 'character varying', 'character_maximum_length' => 3, 'is_nullable' => 'YES', 'is_generated' => 'NEVER'],
            ['column_name' => 'kernel_code_ide', 'data_type' => 'character varying', 'character_maximum_length' => 3, 'is_nullable' => 'YES', 'is_generated' => 'NEVER'],
            ['column_name' => 'kernel_code_vvvv', 'data_type' => 'character varying', 'character_maximum_length' => 4, 'is_nullable' => 'YES', 'is_generated' => 'NEVER'],
        ];
    }

    private function assertSequenceDomainIsVarcharThree(): void
    {
        $column = $this->sequenceDomainMetadata();

        $this->assertSame('character varying', $column->data_type);
        $this->assertSame(3, (int) $column->character_maximum_length);
    }

    private function assertSequenceDomainIsCharTwo(): void
    {
        $column = $this->sequenceDomainMetadata();

        $this->assertSame('character', $column->data_type);
        $this->assertSame(2, (int) $column->character_maximum_length);
    }

    private function sequenceDomainMetadata(): object
    {
        return DB::connection('pgsql')->selectOne(<<<'SQL'
SELECT data_type, character_maximum_length
FROM information_schema.columns
WHERE table_schema = current_schema()
  AND table_name = 'kernel_code_sequences'
  AND column_name = 'domain_code'
SQL);
    }

    private function insertLegacySequence(): void
    {
        DB::table('kernel_code_sequences')->insert([
            'depth' => 4,
            'domain_code' => 'GE',
            'next_value' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertLegacySequenceMigrated(): void
    {
        $this->assertSame(42, (int) DB::table('kernel_code_sequences')
            ->where('depth', 4)
            ->where('domain_code', 'GEO')
            ->value('next_value'));
        $this->assertFalse(DB::table('kernel_code_sequences')
            ->where('depth', 4)
            ->where('domain_code', 'GE')
            ->exists());
    }

    private function assertIsolatedSchema(): void
    {
        $connection = DB::connection('pgsql');
        $activeSchema = $connection->selectOne('SELECT current_schema() schema_name')->schema_name;
        $searchPath = $connection->selectOne('SHOW search_path')->search_path;

        if ($activeSchema !== $this->schemaName || str_contains(strtolower($searchPath), 'public')) {
            throw new \RuntimeException('Le test PostgreSQL doit rester dans son schéma temporaire isolé.');
        }
    }

    private function quotedSchema(): string
    {
        return '"' . $this->schemaName . '"';
    }
}