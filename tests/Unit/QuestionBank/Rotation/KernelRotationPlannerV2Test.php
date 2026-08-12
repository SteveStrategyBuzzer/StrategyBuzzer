<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests de KernelRotationPlanner — interface V2.
 *
 * Couvre : planV2, applyEmptyTransitionV2, receiveKernelReceivedV2.
 *
 * DB : SQLite in-memory (tables créées manuellement).
 */
class KernelRotationPlannerV2Test extends TestCase
{
    private KernelRotationPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();

        // Tables V2
        Schema::create('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('active_depth')->nullable();
            $table->string('active_tour_id', 36)->nullable();
            $table->string('rotation_status', 64)->default('TOUR_IN_PROGRESS');
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

    // =========================================================================
    // planV2 — initialisation
    // =========================================================================

    public function test_plan_v2_returns_rotation_assigned_on_first_call(): void
    {
        $blueprint = $this->makeBlueprint();

        $result = $this->planner->planV2($blueprint);

        $this->assertSame(KernelRotationPlanner::RESULT_ROTATION_ASSIGNED, $result);
    }

    public function test_plan_v2_writes_depth_and_domain_to_blueprint(): void
    {
        $blueprint = $this->makeBlueprint();

        $this->planner->planV2($blueprint);

        $this->assertNotNull($blueprint->depth);
        $this->assertNotNull($blueprint->domain);
        $this->assertIsInt($blueprint->depth);
        $this->assertIsString($blueprint->domain);
    }

    public function test_plan_v2_selects_depth_2_as_first_depth(): void
    {
        $blueprint = $this->makeBlueprint();

        $this->planner->planV2($blueprint);

        $this->assertSame(2, $blueprint->depth, 'Premier Depth du DepthCycle doit être 2 (DEC-065)');
    }

    public function test_plan_v2_selects_geographie_as_first_domain(): void
    {
        $blueprint = $this->makeBlueprint();

        $this->planner->planV2($blueprint);

        $this->assertSame('geographie', $blueprint->domain,
            'Premier Domaine ON = geographie (tête du DomainCycle)');
    }

    public function test_plan_v2_creates_rotation_state_v2_row(): void
    {
        $blueprint = $this->makeBlueprint();

        $this->planner->planV2($blueprint);

        $this->assertSame(1, DB::table('kernel_rotation_state_v2')->count());
    }

    public function test_plan_v2_persists_tour_in_progress_status(): void
    {
        $blueprint = $this->makeBlueprint();

        $this->planner->planV2($blueprint);

        $state = DB::table('kernel_rotation_state_v2')->first();

        $this->assertSame('TOUR_IN_PROGRESS', $state->rotation_status);
    }

    // =========================================================================
    // planV2 — PRODUCTION_ON_HOLD
    // =========================================================================

    public function test_plan_v2_returns_production_on_hold_when_all_depths_saturated(): void
    {
        $this->saturateAllDepths();

        $blueprint = $this->makeBlueprint();
        $result    = $this->planner->planV2($blueprint);

        $this->assertSame(KernelRotationPlanner::RESULT_PRODUCTION_ON_HOLD, $result);
    }

    public function test_plan_v2_does_not_write_depth_on_production_on_hold(): void
    {
        $this->saturateAllDepths();

        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $this->assertNull($blueprint->depth);
        $this->assertNull($blueprint->domain);
    }

    // =========================================================================
    // planV2 — avancement du DomainCycle
    // =========================================================================

    public function test_plan_v2_advances_to_next_domain_when_previous_provided(): void
    {
        // Premier appel
        $bp1 = $this->makeBlueprint();
        $this->planner->planV2($bp1, null);
        $this->assertSame('geographie', $bp1->domain);

        // Deuxième appel avec previousDomain = geographie
        $bp2 = $this->makeBlueprint();
        $this->planner->planV2($bp2, 'geographie');
        $this->assertSame('histoire', $bp2->domain, 'Avance vers histoire après geographie');
    }

    public function test_plan_v2_does_not_produce_rotation_identifier(): void
    {
        $blueprint = $this->makeBlueprint();
        $result    = $this->planner->planV2($blueprint);

        // rotation_identifier est supprimé (DEC-059)
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertObjectNotHasProperty('last_rotation_identifier', $state);
    }

    public function test_plan_v2_does_not_write_kernel_code(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $this->assertNull($blueprint->kernel_code, 'KRP ne touche jamais kernel_code (DEC-068)');
    }

    public function test_plan_v2_does_not_write_taxonomy_slots(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $this->assertNull($blueprint->subdomain_active,     'KRP n\'écrit pas subdomain_active');
        $this->assertNull($blueprint->subject_active,       'KRP n\'écrit pas subject_active');
        $this->assertNull($blueprint->dominant_idea_active, 'KRP n\'écrit pas dominant_idea_active');
    }

    // =========================================================================
    // applyEmptyTransitionV2
    // =========================================================================

    public function test_apply_empty_passes_domain_to_off(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $this->planner->applyEmptyTransitionV2('geographie');

        $state     = DB::table('kernel_rotation_state_v2')->first();
        $tourState = DepthTourState::fromArray(json_decode($state->tour_domain_states, true));

        $this->assertTrue($tourState->isOff('geographie'));
        $this->assertSame(1, $tourState->getEmptyProgress());
    }

    public function test_apply_empty_idempotent_for_already_off_domain(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $this->planner->applyEmptyTransitionV2('geographie');
        $this->planner->applyEmptyTransitionV2('geographie'); // second signal

        $state     = DB::table('kernel_rotation_state_v2')->first();
        $tourState = DepthTourState::fromArray(json_decode($state->tour_domain_states, true));

        $this->assertSame(1, $tourState->getEmptyProgress(), 'Pas de double incrément');
    }

    public function test_apply_empty_8_domains_closes_tour_and_increments_cycle_completed(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $activeDepth = (int) $blueprint->depth;

        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $this->planner->applyEmptyTransitionV2($domain);
        }

        $state = DB::table('kernel_rotation_state_v2')->first();

        // Le Tour est fermé → soit nouveau Tour, soit PRODUCTION_ON_HOLD
        // cycle_completed[activeDepth] == 1
        $matrixRow = DB::table('kernel_depth_matrix')->where('depth', $activeDepth)->first();
        $this->assertSame(1, (int) $matrixRow->cycle_completed, 'cycle_completed incrémenté');
    }

    public function test_apply_empty_tour_closure_changes_active_depth(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $depthBefore = (int) $blueprint->depth;

        // Fermer le Tour complet
        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $this->planner->applyEmptyTransitionV2($domain);
        }

        $state = DB::table('kernel_rotation_state_v2')->first();

        if ($state->rotation_status === 'TOUR_IN_PROGRESS') {
            $this->assertNotSame($depthBefore, (int) $state->active_depth,
                'Après Tour complet, active_depth doit changer (DEC-062)');
        } else {
            $this->assertSame('NOT_ENGAGED_PRODUCTION_ON_HOLD', $state->rotation_status);
        }
    }

    // =========================================================================
    // receiveKernelReceivedV2 — idempotence
    // =========================================================================

    public function test_receive_kernel_received_increments_total(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $this->planner->receiveKernelReceivedV2($blueprint->blueprint_id, 2, 'geographie');

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'geographie')
            ->value('kernel_received_total');

        $this->assertSame(1, (int) $total);
    }

    public function test_receive_kernel_received_is_idempotent(): void
    {
        $blueprint = $this->makeBlueprint();
        $this->planner->planV2($blueprint);

        $this->planner->receiveKernelReceivedV2($blueprint->blueprint_id, 2, 'geographie');
        $this->planner->receiveKernelReceivedV2($blueprint->blueprint_id, 2, 'geographie'); // doublon

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'geographie')
            ->value('kernel_received_total');

        $this->assertSame(1, (int) $total, 'Pas de double incrément');
        $this->assertSame(1, DB::table('kernel_current_kernel_receipts')->count());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeBlueprint(?string $blueprintId = null): KernelBlueprint
    {
        $id = $blueprintId ?? (string) \Illuminate\Support\Str::orderedUuid();
        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId($id);

        // Enregistrer dans kernel_blueprint_runs
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => $bp->blueprint_id,
            'execution_state' => 'CREATED_UNENGAGED',
            'depth'           => null,
            'domain_code'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $bp;
    }

    private function seedDepthMatrix(): void
    {
        $now = now();

        foreach (DepthNeedMatrix::CYCLE_TARGET as $depth => $target) {
            DB::table('kernel_depth_matrix')->insert([
                'depth'                       => $depth,
                'cycle_target'                => $target,
                'cycle_completed'             => 0,
                'empty_progress_current_tour' => 0,
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ]);
        }
    }

    private function seedDepthDomainTotals(): void
    {
        $now     = now();
        $domains = DepthTourState::DOMAIN_CYCLE;

        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            foreach ($domains as $domain) {
                DB::table('kernel_depth_domain_totals')->insert([
                    'depth'                 => $depth,
                    'domain_code'           => $domain,
                    'kernel_received_total' => 0,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ]);
            }
        }
    }

    private function saturateAllDepths(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }
    }
}
