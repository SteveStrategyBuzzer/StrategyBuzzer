<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\CurrentKernelReceivedKbpAdapter;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelBlueprintProvisioner;
use App\Services\QuestionBank\Rotation\KernelBlueprintReadyBankReceiver;
use App\Services\QuestionBank\Rotation\KernelBlueprintRunRepository;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\KernelRotationStateRepository;
use App\Services\QuestionBank\Rotation\ProcessKernelPipelineOutbox;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ReadyBankOutboxKbpRotationPostgresTest extends TestCase
{
    private const CONNECTION = 'readybank_kbp_chain_test';
    private const DOMAINS = [
        'geographie',
        'histoire',
        'faune',
        'art',
        'sport',
        'cinema',
        'cuisine',
        'science',
    ];

    private string $schemaName;
    private string $originalDefault;
    private mixed $originalSearchPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefault = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');
        $this->schemaName = 'test_readybank_kbp_' . bin2hex(random_bytes(6));

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

        $this->assertSame(
            $this->schemaName,
            DB::selectOne('SELECT current_schema() AS name')->name,
        );

        $this->createSchema();
        $this->seedDepthMatrix();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('pgsql')->statement(
                'DROP SCHEMA IF EXISTS ' . $this->quotedSchema() . ' CASCADE'
            );
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

    public function test_readybank_outbox_kbp_rotation_is_atomic_and_replayable(): void
    {
        $runRepository = new KernelBlueprintRunRepository();
        $outboxRepository = new KernelPipelineOutboxRepository();

        $oldBlueprint = (new KernelBlueprintFactory())->create();
        $oldBlueprint->fillRotation(2, 'geographie');
        $runRepository->markEngaged(
            $oldBlueprint->blueprint_id,
            $oldBlueprint->depth,
            $oldBlueprint->domain,
        );

        $event = (new KernelBlueprintReadyBankReceiver(
            $runRepository,
            $outboxRepository,
        ))->receive($oldBlueprint);

        $payload = json_decode(
            (string) DB::table('kernel_pipeline_outbox')
                ->where('event_id', $event->eventId)
                ->value('payload'),
            true,
        );

        $this->assertSame([
            'event_id',
            'event_type',
            'schema_version',
            'blueprint_id',
            'occurred_at',
        ], array_keys($payload));
        $this->assertSame($oldBlueprint->blueprint_id, $payload['blueprint_id']);
        $this->assertArrayNotHasKey('depth', $payload);
        $this->assertArrayNotHasKey('domain', $payload);

        $quotedEventId = DB::getPdo()->quote($event->eventId);
        DB::statement(
            'ALTER TABLE kernel_blueprint_request_refs '
            . 'ADD CONSTRAINT reject_first_kbp_binding '
            . "CHECK (request_reference <> {$quotedEventId})"
        );

        $processor = $this->processor();
        $failed = $processor->process(10);

        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_ERROR, $failed[0]['outcome']);
        $this->assertSame(1, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_request_refs')->count());
        $this->assertSame(1, DB::table('kernel_current_kernel_receipts')->count());
        $this->assertSame(1, $this->receivedTotal());

        DB::statement(
            'ALTER TABLE kernel_blueprint_request_refs '
            . 'DROP CONSTRAINT reject_first_kbp_binding'
        );

        $retried = $processor->process(10);
        $this->assertSame(
            ProcessKernelPipelineOutbox::OUTCOME_PROCESSED,
            $retried[0]['outcome'],
        );

        $newBlueprintId = (string) DB::table('kernel_blueprint_request_refs')
            ->where('request_reference', $event->eventId)
            ->value('blueprint_id');

        $this->assertNotSame('', $newBlueprintId);
        $this->assertNotSame($oldBlueprint->blueprint_id, $newBlueprintId);
        $this->assertSame('READY_BANK_RECEIVED', DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $oldBlueprint->blueprint_id)
            ->value('execution_state'));
        $this->assertSame('ENGAGED_IN_PIPELINE', DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $newBlueprintId)
            ->value('execution_state'));
        $this->assertSame($newBlueprintId, DB::table('kernel_rotation_state_v2')
            ->value('active_blueprint_identity'));
        $this->assertSame(2, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(14, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(1, DB::table('kernel_blueprint_request_refs')->count());
        $this->assertSame(1, DB::table('kernel_current_kernel_receipts')->count());
        $this->assertSame(1, $this->receivedTotal());

        DB::table('kernel_pipeline_outbox')
            ->where('event_id', $event->eventId)
            ->update([
                'processed_at' => null,
                'attempt_count' => 0,
                'last_error' => null,
            ]);

        $replayed = $processor->process(10);

        $this->assertSame(
            ProcessKernelPipelineOutbox::OUTCOME_PROCESSED,
            $replayed[0]['outcome'],
        );
        $this->assertSame($newBlueprintId, DB::table('kernel_blueprint_request_refs')
            ->where('request_reference', $event->eventId)
            ->value('blueprint_id'));
        $this->assertSame(2, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(14, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(1, DB::table('kernel_blueprint_request_refs')->count());
        $this->assertSame(1, DB::table('kernel_current_kernel_receipts')->count());
        $this->assertSame(1, $this->receivedTotal());
    }

    private function processor(): ProcessKernelPipelineOutbox
    {
        $planner = new KernelRotationPlanner();
        $stateRepository = new KernelRotationStateRepository();

        return new ProcessKernelPipelineOutbox(
            $planner,
            new KernelPipelineOrchestrator($planner, $stateRepository),
            new KernelPipelineOutboxRepository(),
            new CurrentKernelReceivedKbpAdapter(new KernelBlueprintProvisioner()),
        );
    }

    private function receivedTotal(): int
    {
        return (int) DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'geographie')
            ->value('kernel_received_total');
    }

    private function createSchema(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 23)->nullable()->unique();
            $table->timestampTz('engaged_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement(
            "CREATE UNIQUE INDEX one_active_blueprint_idx
             ON kernel_blueprint_runs ((1))
             WHERE execution_state IN ('CREATED_UNENGAGED', 'ENGAGED_IN_PIPELINE')"
        );

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
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->cascadeOnDelete();
        });

        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table): void {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);
            $table->foreign('blueprint_id')
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->cascadeOnDelete();
        });

        Schema::create('kernel_pipeline_outbox', function (Blueprint $table): void {
            $table->string('event_id', 36)->primary();
            $table->string('event_type', 128);
            $table->integer('schema_version')->default(1);
            $table->text('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('processed_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        Schema::create('kernel_current_kernel_receipts', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('event_id', 36)->unique();
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->timestampTz('received_at');
        });

        Schema::create('kernel_depth_matrix', function (Blueprint $table): void {
            $table->smallInteger('depth')->primary();
            $table->integer('cycle_target')->default(0);
            $table->integer('cycle_completed')->default(0);
            $table->smallInteger('empty_progress_current_tour')->default(0);
            $table->string('current_tour_id', 36)->nullable();
            $table->timestampsTz();
        });

        Schema::create('kernel_depth_domain_totals', function (Blueprint $table): void {
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->bigInteger('kernel_received_total')->default(0);
            $table->timestampsTz();
            $table->primary(['depth', 'domain_code']);
        });

        Schema::create('kernel_rotation_state_v2', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('active_depth')->nullable();
            $table->string('depth_state', 64)->default('ROTATION_ACTIVE');
            $table->text('domain_states')->nullable();
            $table->integer('pending_depth_exhausted_depth')->nullable();
            $table->integer('domain_position')->nullable();
            $table->text('tour_domain_states')->nullable();
            $table->string('active_tour_id', 36)->nullable();
            $table->string('tour_state', 32)->nullable();
            $table->string('last_closed_tour_id', 36)->nullable();
            $table->smallInteger('last_closed_depth')->nullable();
            $table->string('last_closed_tour_summary_hash', 64)->nullable();
            $table->string('active_blueprint_identity', 36)->nullable();
            $table->string('last_counted_blueprint_identity', 36)->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();
        });

        Schema::create('kernel_taxonomy_terminal_facts', function (Blueprint $table): void {
            $table->id();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('tour_id', 36);
            $table->timestampTz('received_at');
            $table->timestampTz('consumed_at')->nullable();
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
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (self::DOMAINS as $domain) {
                DB::table('kernel_depth_domain_totals')->insert([
                    'depth' => $depth,
                    'domain_code' => $domain,
                    'kernel_received_total' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function quotedSchema(): string
    {
        return '"' . $this->schemaName . '"';
    }
}