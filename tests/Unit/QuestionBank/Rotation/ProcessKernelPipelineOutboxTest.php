<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\ProcessKernelPipelineOutbox;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Tests de la frontière CURRENT_KERNEL_RECEIVED → nouveau Blueprint → module 02. */
class ProcessKernelPipelineOutboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedDepthMatrix();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_pipeline_outbox');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_current_kernel_received_creates_next_blueprint_then_runs_module_02(): void
    {
        $eventId = $this->insertOutboxEvent('bp-previous', 2, 'geographie');

        $results = $this->processor()->process();

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $results[0]['outcome']);
        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $results[0]['orchestrator_status']);

        $event = DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->first();
        $this->assertNotNull($event->processed_at);

        $run = DB::table('kernel_blueprint_runs')->first();
        $this->assertNotNull($run);
        $this->assertSame('CREATED_UNENGAGED', $run->execution_state);
        $this->assertSame(2, (int) $run->depth);
        $this->assertSame('geographie', $run->domain_code);
    }

    public function test_processed_event_is_not_replayed(): void
    {
        $eventId = $this->insertOutboxEvent('bp-previous', 2, 'geographie');
        $this->processor()->process();

        $second = $this->processor()->process();

        $this->assertCount(0, $second);
        $this->assertSame(1, DB::table('kernel_blueprint_runs')->count());
        $this->assertNotNull(DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->value('processed_at'));
    }

    public function test_invalid_payload_remains_replayable(): void
    {
        $eventId = (string) Str::orderedUuid();
        DB::table('kernel_pipeline_outbox')->insert([
            'event_id' => $eventId,
            'event_type' => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'payload' => '{invalid-json',
            'occurred_at' => now(),
            'processed_at' => null,
            'attempt_count' => 0,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->processor()->process();

        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_ERROR, $result[0]['outcome']);
        $row = DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->first();
        $this->assertNull($row->processed_at);
        $this->assertSame(1, (int) $row->attempt_count);
        $this->assertNotNull($row->last_error);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_all_targets_satisfied_processes_ckr_without_creating_blueprint(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->where('depth', $depth)->update([
                'cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth],
            ]);
        }
        $eventId = $this->insertOutboxEvent('bp-last', 10, 'science');

        $result = $this->processor()->process();

        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $result[0]['outcome']);
        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result[0]['orchestrator_status']);
        $this->assertNotNull(DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->value('processed_at'));
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_blocked_krp_keeps_ckr_unprocessed_for_recovery(): void
    {
        $this->insertState(['depth_state' => KernelRotationPlanner::DEPTH_STATE_BLOCKED]);
        $eventId = $this->insertOutboxEvent('bp-before-block', 4, 'art');

        $result = $this->processor()->process();

        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_ERROR, $result[0]['outcome']);
        $row = DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->first();
        $this->assertNull($row->processed_at);
        $this->assertNotNull($row->last_error);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_awaiting_depth_exhausted_keeps_ckr_unprocessed_for_recovery(): void
    {
        $states = $this->domainStates();
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $states['2'][$domain] = KernelRotationPlanner::DOMAIN_DIMMED;
        }
        $this->insertState([
            'active_depth' => 2,
            'domain_states' => json_encode($states, JSON_UNESCAPED_UNICODE),
        ]);
        $eventId = $this->insertOutboxEvent('bp-awaiting-depth', 2, 'science');

        $result = $this->processor()->process();

        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_ERROR, $result[0]['outcome']);
        $this->assertNull(DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->value('processed_at'));
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_existing_active_blueprint_rolls_back_ckr_processing(): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => 'bp-still-active',
            'execution_state' => 'CREATED_UNENGAGED',
            'depth' => 2,
            'domain_code' => 'geographie',
            'engaged_at' => null,
            'received_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventId = $this->insertOutboxEvent('bp-previous', 2, 'geographie');

        $result = $this->processor()->process();

        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_ERROR, $result[0]['outcome']);
        $this->assertNull(DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->value('processed_at'));
        $this->assertSame(1, DB::table('kernel_blueprint_runs')->count());
    }

    private function processor(): ProcessKernelPipelineOutbox
    {
        $planner = new KernelRotationPlanner();
        return new ProcessKernelPipelineOutbox(
            new KernelPipelineOrchestrator(new KernelBlueprintFactory(), $planner),
            new KernelPipelineOutboxRepository(),
        );
    }

    private function createTables(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->timestamp('engaged_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('active_depth')->nullable();
            $table->string('depth_state', 64)->default('ROTATION_ACTIVE');
            $table->text('domain_states')->nullable();
            $table->integer('pending_depth_exhausted_depth')->nullable();
            $table->integer('domain_position')->nullable();
            $table->text('tour_domain_states')->nullable();
            $table->string('active_blueprint_identity', 36)->nullable();
            $table->string('last_counted_blueprint_identity', 36)->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('kernel_depth_matrix', function (Blueprint $table) {
            $table->smallInteger('depth')->primary();
            $table->integer('cycle_target')->default(0);
            $table->integer('cycle_completed')->default(0);
            $table->smallInteger('empty_progress_current_tour')->default(0);
            $table->string('current_tour_id', 36)->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_depth_domain_totals', function (Blueprint $table) {
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->bigInteger('kernel_received_total')->default(0);
            $table->timestamps();
            $table->primary(['depth', 'domain_code']);
        });

        Schema::create('kernel_pipeline_outbox', function (Blueprint $table) {
            $table->string('event_id', 36)->primary();
            $table->string('event_type', 128);
            $table->integer('schema_version')->default(1);
            $table->text('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    private function seedDepthMatrix(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->insert([
                'depth' => $depth,
                'cycle_target' => DepthNeedMatrix::CYCLE_TARGET[$depth],
                'cycle_completed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
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

    private function insertOutboxEvent(string $blueprintId, int $depth, string $domain): string
    {
        $eventId = (string) Str::orderedUuid();
        DB::table('kernel_pipeline_outbox')->insert([
            'event_id' => $eventId,
            'event_type' => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'payload' => json_encode([
                'event_id' => $eventId,
                'event_type' => 'CURRENT_KERNEL_RECEIVED',
                'schema_version' => 1,
                'blueprint_id' => $blueprintId,
                'depth' => $depth,
                'domain' => $domain,
                'occurred_at' => now()->toIso8601String(),
            ]),
            'occurred_at' => now(),
            'processed_at' => null,
            'attempt_count' => 0,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $eventId;
    }

    private function insertState(array $overrides = []): void
    {
        DB::table('kernel_rotation_state_v2')->insert(array_merge([
            'depth_state' => KernelRotationPlanner::DEPTH_STATE_ROTATION_ACTIVE,
            'active_depth' => null,
            'domain_position' => null,
            'domain_states' => json_encode($this->domainStates(), JSON_UNESCAPED_UNICODE),
            'pending_depth_exhausted_depth' => null,
            'tour_domain_states' => null,
            'active_blueprint_identity' => null,
            'last_counted_blueprint_identity' => null,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function domainStates(): array
    {
        $states = [];
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
                $states[(string) $depth][$domain] = KernelRotationPlanner::DOMAIN_VISIBLE;
            }
        }
        return $states;
    }
}
