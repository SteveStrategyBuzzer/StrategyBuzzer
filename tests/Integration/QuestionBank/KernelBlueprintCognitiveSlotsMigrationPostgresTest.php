<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

class KernelBlueprintCognitiveSlotsMigrationPostgresTest extends TestCase
{
    private string $schemaName;
    private string $originalConnection;
    private mixed $originalSearchPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');
        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->schemaName = 'test_phase1_' . bin2hex(random_bytes(6));
        DB::connection('pgsql')->statement('CREATE SCHEMA "' . $this->schemaName . '"');
        config(['database.connections.pgsql.search_path' => '"' . $this->schemaName . '"']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        Schema::connection('pgsql')->create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
        });

        $migration = require base_path(
            'database/migrations/2026_08_30_000001_create_kernel_blueprint_cognitive_slots.php'
        );
        $migration->up();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('pgsql')->statement(
                'DROP SCHEMA IF EXISTS "' . $this->schemaName . '" CASCADE'
            );
        } finally {
            config([
                'database.default' => $this->originalConnection,
                'database.connections.pgsql.search_path' => $this->originalSearchPath,
            ]);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            parent::tearDown();
        }
    }

    public function test_migration_accepts_exactly_the_seven_official_empty_slots(): void
    {
        DB::table('kernel_blueprint_runs')->insert(['blueprint_id' => 'bp-pg']);
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            DB::table('kernel_blueprint_cognitive_slots')->insert([
                'blueprint_id' => 'bp-pg',
                'cognitive_type' => $type,
            ]);
        }

        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->expectException(Throwable::class);
        DB::table('kernel_blueprint_cognitive_slots')->insert([
            'blueprint_id' => 'bp-pg',
            'cognitive_type' => 'EIGHTH_TYPE',
        ]);
    }

    public function test_migration_enforces_state_json_and_parent_cascade_constraints(): void
    {
        DB::table('kernel_blueprint_runs')->insert(['blueprint_id' => 'bp-cascade']);
        DB::table('kernel_blueprint_cognitive_slots')->insert([
            'blueprint_id' => 'bp-cascade',
            'cognitive_type' => 'QCM_RECOGNITION',
        ]);

        DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-cascade')->delete();
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());

        DB::table('kernel_blueprint_runs')->insert(['blueprint_id' => 'bp-invalid']);
        $this->expectException(Throwable::class);
        DB::table('kernel_blueprint_cognitive_slots')->insert([
            'blueprint_id' => 'bp-invalid',
            'cognitive_type' => 'QCM_RECOGNITION',
            'creation_status' => 'CREATED',
            'source' => null,
        ]);
    }
}