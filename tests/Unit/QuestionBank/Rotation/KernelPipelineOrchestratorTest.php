<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Tests du raccord strict du module 02. */
class KernelPipelineOrchestratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedDepthMatrix();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_run_creates_blueprint_then_assigns_depth_and_domain_only(): void
    {
        $result = $this->orchestrator()->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertInstanceOf(KernelBlueprint::class, $result['blueprint']);
        $this->assertSame(2, $result['blueprint']->depth);
        $this->assertSame('geographie', $result['blueprint']->domain);
        $this->assertNull($result['blueprint']->subdomain_active);
        $this->assertNull($result['blueprint']->subject_active);
        $this->assertNull($result['blueprint']->dominant_idea_active);
        $this->assertNull($result['blueprint']->kernel_code);
    }

    public function test_module_02_leaves_blueprint_created_unengaged_for_taxonomy(): void
    {
        $result = $this->orchestrator()->run();
        $row = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $result['blueprint']->blueprint_id)
            ->first();

        $this->assertSame('CREATED_UNENGAGED', $row->execution_state);
        $this->assertSame(2, (int) $row->depth);
        $this->assertSame('geographie', $row->domain_code);
        $this->assertNull($row->engaged_at);
    }

    public function test_rotation_state_records_active_blueprint_and_position(): void
    {
        $result = $this->orchestrator()->run();
        $state = DB::table('kernel_rotation_state_v2')->first();

        $this->assertSame($result['blueprint']->blueprint_id, $state->active_blueprint_identity);
        $this->assertSame(2, (int) $state->active_depth);
        $this->assertSame(0, (int) $state->domain_position);
        $this->assertSame(KernelRotationPlanner::DEPTH_STATE_ROTATION_ACTIVE, $state->depth_state);
    }

    public function test_no_blueprint_created_when_all_global_targets_are_satisfied(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->where('depth', $depth)->update([
                'cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth],
            ]);
        }

        $result = $this->orchestrator()->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);
        $this->assertNull($result['blueprint']);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_no_blueprint_created_while_waiting_for_depth_exhausted(): void
    {
        $states = $this->domainStates();
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $states['2'][$domain] = KernelRotationPlanner::DOMAIN_DIMMED;
        }

        $this->insertState([
            'active_depth' => 2,
            'domain_states' => json_encode($states, JSON_UNESCAPED_UNICODE),
        ]);

        $result = $this->orchestrator()->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_AWAITING_DEPTH_EXHAUSTED, $result['status']);
        $this->assertNull($result['blueprint']);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_blocked_state_prevents_new_blueprint(): void
    {
        $this->insertState(['depth_state' => KernelRotationPlanner::DEPTH_STATE_BLOCKED]);

        $result = $this->orchestrator()->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_BLOCKED, $result['status']);
        $this->assertNull($result['blueprint']);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
    }

    public function test_second_run_cannot_create_another_active_blueprint(): void
    {
        $this->orchestrator()->run();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Blueprint actif existe déjà/');

        $this->orchestrator()->run();
    }

    private function orchestrator(): KernelPipelineOrchestrator
    {
        return new KernelPipelineOrchestrator(
            new KernelBlueprintFactory(),
            new KernelRotationPlanner(),
        );
    }

    private function createTables(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 22)->nullable();
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
