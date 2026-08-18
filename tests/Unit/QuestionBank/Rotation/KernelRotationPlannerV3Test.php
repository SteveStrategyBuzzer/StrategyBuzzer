<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests contractuels KernelRotationPlanner v3.3.
 *
 * Couvre strictement le module 02 :
 * - sélection depth + domain ;
 * - cadran VISIBLE / ESTOMPÉ ;
 * - DOMAIN_EXHAUSTED ;
 * - DEPTH_EXHAUSTED = fin d'un tour ;
 * - cycle_target / cycle_completed ;
 * - wrap Depth 10 -> Depth 2 ;
 * - PRODUCTION_ON_HOLD uniquement lorsque toutes les cibles sont satisfaites ;
 * - CURRENT_KERNEL_RECEIVED legacy ne pilote aucune transition KRP.
 */
class KernelRotationPlannerV3Test extends TestCase
{
    private KernelRotationPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedDepthMatrix();
        $this->seedDepthDomainTotals();
        $this->planner = new KernelRotationPlanner();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_current_kernel_receipts');
        Schema::dropIfExists('kernel_blueprint_runs');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_rotation_state_v2');
        parent::tearDown();
    }

    public function test_initial_resolution_is_depth_2_geographie(): void
    {
        $resolution = $this->planner->resolveNextRotation(null);

        $this->assertTrue($resolution->isAvailable());
        $this->assertSame(2, $resolution->depth);
        $this->assertSame('geographie', $resolution->domain);
        $this->assertSame(0, $resolution->domainPosition);
    }

    public function test_resolution_skips_dimmed_domain(): void
    {
        $states = $this->domainStates();
        $states['2']['geographie'] = KernelRotationPlanner::DOMAIN_DIMMED;
        $state = $this->insertState([
            'active_depth' => 2,
            'domain_states' => json_encode($states, JSON_UNESCAPED_UNICODE),
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertSame('histoire', $resolution->domain);
        $this->assertSame(1, $resolution->domainPosition);
    }

    public function test_all_domains_dimmed_waits_for_depth_exhausted(): void
    {
        $states = $this->allDimmedForDepth(2);
        $state = $this->insertState([
            'active_depth' => 2,
            'domain_states' => json_encode($states, JSON_UNESCAPED_UNICODE),
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertTrue($resolution->isNoRotation());
        $this->assertSame('AWAITING_DEPTH_EXHAUSTED', $resolution->noRotationReason());
    }

    public function test_blocked_state_returns_no_rotation(): void
    {
        $state = $this->insertState(['depth_state' => KernelRotationPlanner::DEPTH_STATE_BLOCKED]);
        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertTrue($resolution->isNoRotation());
        $this->assertSame(KernelRotationPlanner::DEPTH_STATE_BLOCKED, $resolution->noRotationReason());
    }

    public function test_apply_rotation_writes_only_depth_and_domain(): void
    {
        $this->insertState(['active_depth' => 2]);
        $blueprint = $this->makeBlueprint();
        $this->insertBlueprintRun($blueprint->blueprint_id);

        $this->planner->applyRotation($blueprint, 2, 'histoire', 1);

        $this->assertSame(2, $blueprint->depth);
        $this->assertSame('histoire', $blueprint->domain);
        $this->assertNull($blueprint->subdomain_active);
        $this->assertNull($blueprint->subject_active);
        $this->assertNull($blueprint->dominant_idea_active);
        $this->assertNull($blueprint->kernel_code);

        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprint->blueprint_id)->first();
        $this->assertSame('CREATED_UNENGAGED', $run->execution_state);
        $this->assertSame(2, (int) $run->depth);
        $this->assertSame('histoire', $run->domain_code);
    }

    public function test_apply_rotation_remains_write_once(): void
    {
        $this->insertState(['active_depth' => 2]);
        $blueprint = $this->makeBlueprint();
        $this->insertBlueprintRun($blueprint->blueprint_id);

        $this->planner->applyRotation($blueprint, 2, 'geographie', 0);

        $this->expectException(\LogicException::class);
        $this->planner->applyRotation($blueprint, 2, 'histoire', 1);
    }

    public function test_general_is_rejected_as_creation_domain(): void
    {
        $this->insertState(['active_depth' => 2]);
        $blueprint = $this->makeBlueprint();
        $this->insertBlueprintRun($blueprint->blueprint_id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Domaine inconnu/');
        $this->planner->applyRotation($blueprint, 2, 'general', 0);
    }

    public function test_domain_exhausted_persists_visible_to_dimmed(): void
    {
        $this->insertState(['active_depth' => 2]);

        $this->planner->receiveDomainExhausted(2, 'geographie');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $states = json_decode((string) $state->domain_states, true);
        $this->assertSame(KernelRotationPlanner::DOMAIN_DIMMED, $states['2']['geographie']);
        $this->assertSame(KernelRotationPlanner::DOMAIN_VISIBLE, $states['2']['histoire']);
    }

    public function test_domain_exhausted_is_idempotent(): void
    {
        $this->insertState(['active_depth' => 2]);

        $this->planner->receiveDomainExhausted(2, 'geographie');
        $firstLock = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');
        $this->planner->receiveDomainExhausted(2, 'geographie');
        $secondLock = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');

        $this->assertSame($firstLock, $secondLock);
    }

    public function test_domain_exhausted_for_non_active_depth_is_noop(): void
    {
        $this->insertState(['active_depth' => 2]);

        $this->planner->receiveDomainExhausted(4, 'geographie');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $states = json_decode((string) $state->domain_states, true);
        $this->assertSame(KernelRotationPlanner::DOMAIN_VISIBLE, $states['4']['geographie']);
    }

    public function test_depth_exhausted_before_eight_domains_dimmed_is_noop(): void
    {
        $this->insertState(['active_depth' => 2]);

        $this->planner->receiveDepthExhausted(2);

        $this->assertSame(0, (new DepthNeedMatrix())->getCycleCompleted(2));
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(2, (int) $state->active_depth);
    }

    public function test_valid_depth_exhausted_counts_one_tour_and_moves_to_next_required_depth(): void
    {
        $this->insertState([
            'active_depth' => 2,
            'domain_states' => json_encode($this->allDimmedForDepth(2), JSON_UNESCAPED_UNICODE),
        ]);

        $this->planner->receiveDepthExhausted(2);

        $this->assertSame(1, (new DepthNeedMatrix())->getCycleCompleted(2));
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(4, (int) $state->active_depth);
        $this->assertSame(KernelRotationPlanner::DEPTH_STATE_ROTATION_ACTIVE, $state->depth_state);
        $this->assertNull($state->domain_position);

        $states = json_decode((string) $state->domain_states, true);
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $this->assertSame(KernelRotationPlanner::DOMAIN_VISIBLE, $states['4'][$domain]);
        }
    }

    public function test_depth_transition_skips_globally_satisfied_depths(): void
    {
        DB::table('kernel_depth_matrix')->where('depth', 4)->update([
            'cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[4],
        ]);
        DB::table('kernel_depth_matrix')->where('depth', 6)->update([
            'cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[6],
        ]);

        $this->insertState([
            'active_depth' => 2,
            'domain_states' => json_encode($this->allDimmedForDepth(2), JSON_UNESCAPED_UNICODE),
        ]);

        $this->planner->receiveDepthExhausted(2);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(7, (int) $state->active_depth);
    }

    public function test_depth_10_wraps_to_depth_2_when_global_need_remains(): void
    {
        $this->insertState([
            'active_depth' => 10,
            'domain_states' => json_encode($this->allDimmedForDepth(10), JSON_UNESCAPED_UNICODE),
        ]);

        $this->planner->receiveDepthExhausted(10);

        $this->assertSame(1, (new DepthNeedMatrix())->getCycleCompleted(10));
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(2, (int) $state->active_depth);
        $this->assertSame(KernelRotationPlanner::DEPTH_STATE_ROTATION_ACTIVE, $state->depth_state);
    }

    public function test_production_on_hold_only_after_all_targets_are_satisfied(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->where('depth', $depth)->update([
                'cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth],
            ]);
        }
        DB::table('kernel_depth_matrix')->where('depth', 10)->update([
            'cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[10] - 1,
        ]);

        $this->insertState([
            'active_depth' => 10,
            'domain_states' => json_encode($this->allDimmedForDepth(10), JSON_UNESCAPED_UNICODE),
        ]);

        $this->planner->receiveDepthExhausted(10);

        $this->assertSame(DepthNeedMatrix::CYCLE_TARGET[10], (new DepthNeedMatrix())->getCycleCompleted(10));
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(KernelRotationPlanner::DEPTH_STATE_PRODUCTION_ON_HOLD, $state->depth_state);
        $this->assertNull($state->active_depth);
    }

    public function test_duplicate_depth_exhausted_after_transition_does_not_double_count(): void
    {
        $this->insertState([
            'active_depth' => 2,
            'domain_states' => json_encode($this->allDimmedForDepth(2), JSON_UNESCAPED_UNICODE),
        ]);

        $this->planner->receiveDepthExhausted(2);
        $this->planner->receiveDepthExhausted(2);

        $this->assertSame(1, (new DepthNeedMatrix())->getCycleCompleted(2));
    }

    public function test_legacy_current_kernel_received_does_not_change_active_depth(): void
    {
        $this->insertState(['active_depth' => 4, 'domain_position' => 3]);

        $this->planner->receiveKernelReceivedV2('bp-legacy', 4, 'art');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(4, (int) $state->active_depth);
        $this->assertSame(3, (int) $state->domain_position);
        $this->assertSame(1, (new DepthNeedMatrix())->getKernelReceivedTotal(4, 'art'));
    }

    private function createTables(): void
    {
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

        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->timestamp('engaged_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_current_kernel_receipts', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('event_id', 36)->unique();
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->timestamp('received_at');
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
        }
    }

    private function seedDepthDomainTotals(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
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

    private function insertState(array $overrides = []): object
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

        return DB::table('kernel_rotation_state_v2')->first();
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

    private function allDimmedForDepth(int $depth): array
    {
        $states = $this->domainStates();
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $states[(string) $depth][$domain] = KernelRotationPlanner::DOMAIN_DIMMED;
        }
        return $states;
    }

    private function makeBlueprint(): KernelBlueprint
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId((string) Str::orderedUuid());
        return $blueprint;
    }

    private function insertBlueprintRun(string $blueprintId): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $blueprintId,
            'execution_state' => 'CREATED_UNENGAGED',
            'depth' => null,
            'domain_code' => null,
            'engaged_at' => null,
            'received_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
