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
    private const MIGRATION = 'database/migrations/2026_08_28_000002_migrate_legacy_kernel_code_sequences.php';
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

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->assertSame('pgsql', DB::connection()->getDriverName());

        Schema::dropIfExists('kernel_code_sequences');
        Schema::dropIfExists('kernel_blueprint_runs');

        Schema::create('kernel_code_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('depth');
            $table->char('domain_code', 3);
            $table->integer('next_value')->default(0);
            $table->timestamps();
            $table->primary(['depth', 'domain_code']);
        });

        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('ENGAGED_IN_PIPELINE');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 23)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_blueprint_runs');
        Schema::dropIfExists('kernel_code_sequences');

        parent::tearDown();
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
        $migration = require base_path(self::MIGRATION);
        $migration->up();
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