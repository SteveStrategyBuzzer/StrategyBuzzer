<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\QuestionIntentBlueprintIdReceiver;
use App\Services\QuestionBank\Phase1\KernelPhase1EntryBoundary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class KernelBlueprintPhasedPersistencePostgresTest extends TestCase
{
    private string $schemaName;
    private string $originalDefault;
    private mixed $originalSearchPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDefault = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');
        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->schemaName = 'test_kernel_phase_' . bin2hex(random_bytes(6));
        DB::connection('pgsql')->statement('CREATE SCHEMA "' . $this->schemaName . '"');
        config(['database.connections.pgsql.search_path' => '"' . $this->schemaName . '"']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 23)->nullable();
            $table->timestampsTz();
        });
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('pgsql')->statement(
                'DROP SCHEMA IF EXISTS "' . $this->schemaName . '" CASCADE'
            );
        } finally {
            config([
                'database.default' => $this->originalDefault,
                'database.connections.pgsql.search_path' => $this->originalSearchPath,
            ]);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            parent::tearDown();
        }
    }

    public function test_non_null_old_kernel_code_refuses_before_any_ddl(): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => 'bp-old',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'kernel_code' => '04-GEO-CAN-CON-ACT-0000',
        ]);

        try {
            $this->runMigration();
            $this->fail('The migration must refuse historical kernel_code values.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('existing kernel_code', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('kernel_blueprint_runs', 'kernel_code'));
        $this->assertFalse(Schema::hasColumn('kernel_blueprint_runs', 'kernel_code_dd'));
        $this->assertSame(
            '04-GEO-CAN-CON-ACT-0000',
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-old')->value('kernel_code'),
        );
    }

    public function test_preexisting_partial_rotation_refuses_before_any_ddl(): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => 'bp-partial-old',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth' => 4,
        ]);

        try {
            $this->runMigration();
            $this->fail('The migration must refuse a partial historical Rotation.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('partial Rotation', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('kernel_blueprint_runs', 'kernel_code_dd'));
        $this->assertSame(4, (int) DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', 'bp-partial-old')->value('depth'));
    }

    public function test_empty_row_has_null_segments_and_generated_projection_is_exact_and_read_only(): void
    {
        $this->runMigration();
        $this->insertEmpty('bp-empty');

        $row = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-empty')->first();
        foreach ([
            'kernel_code_dd',
            'kernel_code_do',
            'kernel_code_sub',
            'kernel_code_suj',
            'kernel_code_ide',
            'kernel_code_vvvv',
            'kernel_code',
        ] as $column) {
            $this->assertNull($row->{$column});
        }

        DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-empty')->update([
            'depth' => 4,
            'domain_code' => 'Géographie',
            'kernel_code_dd' => '04',
            'kernel_code_do' => 'GEO',
            'subdomain_active' => 'Canada',
            'subject_active' => 'Confédération canadienne',
            'dominant_idea_active' => 'Acte',
            'kernel_code_sub' => 'CAN',
            'kernel_code_suj' => 'CON',
            'kernel_code_ide' => 'ACT',
            'kernel_code_vvvv' => '000A',
        ]);

        $this->assertSame(
            '04-GEO-CAN-CON-ACT-000A',
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-empty')->value('kernel_code'),
        );

        $this->expectException(Throwable::class);
        DB::statement(
            "UPDATE kernel_blueprint_runs SET kernel_code = '04-GEO-CAN-CON-ACT-000B' "
            . "WHERE blueprint_id = 'bp-empty'"
        );
    }

    public function test_phase_update_rolls_back_on_segment_constraint_failure(): void
    {
        $this->runMigration();
        $this->insertEmpty('bp-rollback');

        try {
            DB::transaction(function (): void {
                DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-rollback')->update([
                    'depth' => 4,
                    'domain_code' => 'Géographie',
                    'kernel_code_dd' => '04',
                    'kernel_code_do' => 'GEO',
                    'subdomain_active' => 'Canada',
                    'subject_active' => 'Confédération canadienne',
                    'dominant_idea_active' => 'Acte',
                    'kernel_code_sub' => 'invalid',
                ]);
            });
            $this->fail('The taxonomy segment constraint must fail.');
        } catch (Throwable) {
            $this->assertNull(DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', 'bp-rollback')->value('depth'));
            $this->assertNull(DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', 'bp-rollback')->value('kernel_code_sub'));
        }
    }

    public function test_every_partial_rotation_combination_is_rejected_by_shipped_schema(): void
    {
        $this->runMigration();
        $columns = ['depth', 'domain_code', 'kernel_code_dd', 'kernel_code_do'];
        $values = [4, 'Géographie', '04', 'GEO'];

        for ($mask = 1; $mask < 15; $mask++) {
            $id = 'bp-partial-' . $mask;
            $this->insertEmpty($id);
            $payload = [];
            foreach ($columns as $index => $column) {
                if (($mask & (1 << $index)) !== 0) {
                    $payload[$column] = $values[$index];
                }
            }

            $rejected = false;
            try {
                DB::table('kernel_blueprint_runs')->where('blueprint_id', $id)->update($payload);
            } catch (Throwable) {
                $rejected = true;
                $this->assertNull(DB::table('kernel_blueprint_runs')
                    ->where('blueprint_id', $id)->value('depth'));
                $this->assertNull(DB::table('kernel_blueprint_runs')
                    ->where('blueprint_id', $id)->value('kernel_code_dd'));
            }
            $this->assertTrue($rejected, "Partial Rotation mask {$mask} must be rejected.");
        }
    }

    public function test_rotation_depth_domain_and_segments_are_one_atomic_stage(): void
    {
        $this->runMigration();
        $this->insertEmpty('bp-rotation');
        DB::statement(
            'ALTER TABLE kernel_blueprint_runs '
            . 'ADD CONSTRAINT reject_rotation_stage CHECK (domain_code IS NULL)'
        );

        try {
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-rotation')->update([
                'depth' => 4,
                'domain_code' => 'Géographie',
                'kernel_code_dd' => '04',
                'kernel_code_do' => 'GEO',
            ]);
            $this->fail('The forced Rotation constraint must fail.');
        } catch (Throwable) {
            $this->assertNull(DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', 'bp-rotation')->value('depth'));
            $this->assertNull(DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', 'bp-rotation')->value('kernel_code_dd'));
        } finally {
            DB::statement(
                'ALTER TABLE kernel_blueprint_runs DROP CONSTRAINT reject_rotation_stage'
            );
        }

        DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-rotation')->update([
            'depth' => 4,
            'domain_code' => 'Géographie',
            'kernel_code_dd' => '04',
            'kernel_code_do' => 'GEO',
        ]);
        $row = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-rotation')->first();
        $this->assertSame(4, (int) $row->depth);
        $this->assertSame('Géographie', $row->domain_code);
        $this->assertSame('04', $row->kernel_code_dd);
        $this->assertSame('GEO', $row->kernel_code_do);
    }

    public function test_taxonomy_persists_full_values_and_exact_segments_atomically(): void
    {
        $this->runMigration();
        $this->insertEmpty('bp-taxonomy');
        DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-taxonomy')->update([
            'depth' => 4,
            'domain_code' => 'Géographie',
            'kernel_code_dd' => '04',
            'kernel_code_do' => 'GEO',
        ]);

        DB::transaction(function (): void {
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-taxonomy')->update([
                'subdomain_active' => 'Canada',
                'subject_active' => 'Confédération canadienne',
                'dominant_idea_active' => 'Acte',
                'kernel_code_sub' => 'CAN',
                'kernel_code_suj' => 'CON',
                'kernel_code_ide' => 'ACT',
            ]);
        });

        $row = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-taxonomy')->first();
        $this->assertSame('Canada', $row->subdomain_active);
        $this->assertSame('Confédération canadienne', $row->subject_active);
        $this->assertSame('Acte', $row->dominant_idea_active);
        $this->assertSame('CAN', $row->kernel_code_sub);
        $this->assertSame('CON', $row->kernel_code_suj);
        $this->assertSame('ACT', $row->kernel_code_ide);
        $this->assertNull($row->kernel_code_vvvv);
        $this->assertNull($row->kernel_code);
    }

    public function test_phase1_reopens_by_id_with_full_segments_and_seven_empty_slots(): void
    {
        $this->runMigration();
        $this->createPhase1Children();
        $this->insertComplete('bp-phase1');

        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            DB::table('kernel_blueprint_cognitive_slots')->insert([
                'blueprint_id' => 'bp-phase1',
                'cognitive_type' => $type,
                'source' => json_encode(KernelBlueprint::emptyCognitiveSlotSource($type), JSON_THROW_ON_ERROR),
                'translations' => '{}',
                'validation_findings' => '[]',
                'creation_status' => 'EMPTY',
                'validation_status' => 'NOT_VALIDATED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('kernel_blueprint_request_refs')->insert([
            'request_reference' => 'phase1:test',
            'blueprint_id' => 'bp-phase1',
        ]);

        $beforeRun = (array) DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', 'bp-phase1')->first();
        $beforeSlots = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', 'bp-phase1')->orderBy('cognitive_type')->get()->map(
                static fn (object $slot): array => (array) $slot,
            )->all();

        // The boundary constructor accepts only a persistent loader: no provider
        // client can be injected or called by this phase-entry operation.
        $boundary = new KernelPhase1EntryBoundary();
        $receivedBlueprintId = $boundary->receive('bp-phase1');

        $this->assertSame('bp-phase1', $receivedBlueprintId);
        $this->assertSame(
            7,
            DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', 'bp-phase1')->where('creation_status', 'EMPTY')->count(),
        );
        $afterRun = (array) DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', 'bp-phase1')->first();
        $afterSlots = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', 'bp-phase1')->orderBy('cognitive_type')->get()->map(
                static fn (object $slot): array => (array) $slot,
            )->all();
        $this->assertSame($beforeRun, $afterRun);
        $this->assertSame($beforeSlots, $afterSlots);
    }

    public function test_question_intent_persists_only_vvvv_and_replay_is_idempotent(): void
    {
        $this->runMigration();
        $this->createPhase1Children();
        Schema::create('kernel_code_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('depth');
            $table->char('domain_code', 3);
            $table->unsignedInteger('next_value')->default(0);
            $table->timestampsTz();
            $table->primary(['depth', 'domain_code']);
        });
        $this->insertComplete('bp-question-intent');
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            DB::table('kernel_blueprint_cognitive_slots')->insert([
                'blueprint_id' => 'bp-question-intent',
                'cognitive_type' => $type,
                'source' => json_encode(KernelBlueprint::emptyCognitiveSlotSource($type), JSON_THROW_ON_ERROR),
                'translations' => '{}',
                'validation_findings' => '[]',
                'creation_status' => 'EMPTY',
                'validation_status' => 'NOT_VALIDATED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('kernel_blueprint_request_refs')->insert([
            'request_reference' => 'question-intent:test',
            'blueprint_id' => 'bp-question-intent',
        ]);
        DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-question-intent')
            ->update(['kernel_code_vvvv' => null]);
        DB::table('kernel_code_sequences')->insert([
            'depth' => 4,
            'domain_code' => 'GEO',
            'next_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-question-intent')->first();

        $engine = new KernelCodeEngine();
        $this->assertSame(
            'bp-question-intent',
            (new QuestionIntentBlueprintIdReceiver($engine))->process('bp-question-intent'),
        );
        $code = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', 'bp-question-intent')->value('kernel_code');
        $after = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-question-intent')->first();
        $this->assertSame('04-GEO-CAN-CON-ACT-0000', $code);
        $this->assertSame($code, $after->kernel_code);
        $this->assertSame('0000', $after->kernel_code_vvvv);
        foreach (['depth', 'domain_code', 'kernel_code_dd', 'kernel_code_do',
            'subdomain_active', 'subject_active', 'dominant_idea_active',
            'kernel_code_sub', 'kernel_code_suj', 'kernel_code_ide'] as $column) {
            $this->assertSame($before->{$column}, $after->{$column}, $column);
        }

        $this->assertSame(
            'bp-question-intent',
            (new QuestionIntentBlueprintIdReceiver($engine))->process('bp-question-intent'),
        );
        $this->assertSame($code, DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', 'bp-question-intent')->value('kernel_code'));
        $this->assertSame(1, DB::table('kernel_code_sequences')
            ->where('depth', 4)->where('domain_code', 'GEO')->value('next_value'));
    }

    private function runMigration(): void
    {
        $migration = require base_path(
            'database/migrations/2026_09_08_000001_authorize_phased_kernel_blueprint_persistence.php'
        );
        $migration->up();
    }

    private function insertEmpty(string $id): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $id,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertComplete(string $id): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $id,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth' => 4,
            'domain_code' => 'Géographie',
            'kernel_code_dd' => '04',
            'kernel_code_do' => 'GEO',
            'subdomain_active' => 'Canada',
            'subject_active' => 'Confédération canadienne',
            'dominant_idea_active' => 'Acte',
            'kernel_code_sub' => 'CAN',
            'kernel_code_suj' => 'CON',
            'kernel_code_ide' => 'ACT',
            'kernel_code_vvvv' => '000A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPhase1Children(): void
    {
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
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
                ->references('blueprint_id')->on('kernel_blueprint_runs')->cascadeOnDelete();
        });
        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table): void {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);
            $table->foreign('blueprint_id')
                ->references('blueprint_id')->on('kernel_blueprint_runs')->cascadeOnDelete();
        });
    }
}