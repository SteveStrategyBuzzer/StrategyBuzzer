<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\KernelRotationStateRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit coverage for the KRP v4 / DEC-119 terminal-fact lifecycle.
 */
class KernelRotationPlannerV4Test extends TestCase
{
    private const DOMAINS = [
        'geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science',
    ];

    private KernelRotationPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('kernel_rotation_state_v2', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('active_depth')->nullable();
            $table->string('active_tour_id', 36)->nullable();
            $table->string('tour_state', 16)->default('OPEN');
            $table->string('last_closed_tour_id', 36)->nullable();
            $table->unsignedTinyInteger('last_closed_depth')->nullable();
            $table->string('depth_state', 64)->default('ROTATION_ACTIVE');
            $table->text('domain_states')->nullable();
            $table->integer('domain_position')->nullable();
            $table->string('active_blueprint_identity', 36)->nullable();
            $table->string('last_counted_blueprint_identity', 36)->nullable();
            $table->integer('pending_depth_exhausted_depth')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('kernel_taxonomy_terminal_facts', function (Blueprint $table): void {
            $table->id();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('tour_id', 36);
            $table->timestamp('received_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(
                ['depth', 'tour_id', 'consumed_at', 'received_at'],
                'kttf_pending_tour_idx',
            );
        });

        Schema::create('kernel_depth_matrix', function (Blueprint $table): void {
            $table->smallInteger('depth')->primary();
            $table->integer('cycle_target');
            $table->integer('cycle_completed')->default(0);
            $table->smallInteger('empty_progress_current_tour')->default(0);
            $table->string('current_tour_id', 36)->nullable();
            $table->timestamps();
        });

        $this->seedDepthMatrix();
        $this->planner = new KernelRotationPlanner();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_taxonomy_terminal_facts');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_depth_matrix');

        parent::tearDown();
    }

    public function test_resolve_returns_the_first_official_rotation_without_persisting_state(): void
    {
        $resolution = $this->planner->resolveNextRotation(null);

        $this->assertTrue($resolution->isAvailable());
        $this->assertSame(2, $resolution->depth);
        $this->assertSame('geographie', $resolution->domain);
        $this->assertSame(0, $resolution->domainPosition);
        $this->assertSame(0, DB::table('kernel_rotation_state_v2')->count());
    }

    public function test_prepare_new_blueprint_initializes_the_v4_tour_and_writes_rotation_once(): void
    {
        $blueprint = $this->newBlueprint('bp-initial-v4');

        $resolution = DB::transaction(function () use ($blueprint): object {
            return $this->planner->prepareNewBlueprint($blueprint, null);
        });

        $this->assertSame(2, $resolution->depth);
        $this->assertSame('geographie', $resolution->domain);
        $this->assertSame(2, $blueprint->depth);
        $this->assertSame('geographie', $blueprint->domain);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(2, (int) $state->active_depth);
        $this->assertNotNull($state->active_tour_id);
        $this->assertSame('OPEN', $state->tour_state);
        $this->assertSame('bp-initial-v4', $state->active_blueprint_identity);
        $this->assertSame(0, (int) $state->domain_position);
    }

    public function test_terminal_fact_replay_is_idempotent_before_consumption(): void
    {
        $this->insertActiveState(2, 'geographie', 'tour-idempotent', 'bp-active');

        $this->planner->receiveTaxonomyTerminalFact('fact-idempotent', 2, 'geographie');
        $this->planner->receiveTaxonomyTerminalFact('fact-idempotent', 2, 'geographie');

        $this->assertSame(1, DB::table('kernel_taxonomy_terminal_facts')->count());
        $fact = DB::table('kernel_taxonomy_terminal_facts')->first();
        $this->assertSame('tour-idempotent', $fact->tour_id);
        $this->assertNull($fact->consumed_at);
    }

    public function test_replayed_fact_cannot_change_its_identity(): void
    {
        $this->insertActiveState(2, 'geographie', 'tour-immutable', 'bp-active');
        $this->planner->receiveTaxonomyTerminalFact('fact-immutable', 2, 'geographie');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Violation d.immuabilité du fait terminal/');

        $this->planner->receiveTaxonomyTerminalFact('fact-immutable', 4, 'geographie');
    }

    public function test_terminal_fact_must_match_the_active_blueprint_depth_and_domain(): void
    {
        $this->insertActiveState(4, 'geographie', 'tour-depth-four', 'bp-active');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/depth.domain ne correspondent pas au Blueprint KRP actif/'
        );

        $this->planner->receiveTaxonomyTerminalFact('fact-wrong-depth', 6, 'geographie');
    }

    public function test_terminal_fact_requires_an_active_correlated_blueprint(): void
    {
        $this->insertActiveState(2, 'geographie', 'tour-no-blueprint', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/aucun Blueprint KRP actif à corréler/');

        $this->planner->receiveTaxonomyTerminalFact('fact-no-blueprint', 2, 'geographie');
    }

    public function test_read_only_resolution_does_not_consume_a_pending_terminal_fact(): void
    {
        $this->insertActiveState(2, 'geographie', 'tour-read-only', 'bp-active');
        $this->planner->receiveTaxonomyTerminalFact('fact-read-only', 2, 'geographie');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertSame('histoire', $resolution->domain);
        $this->assertNull(
            DB::table('kernel_taxonomy_terminal_facts')
                ->where('fact_id', 'fact-read-only')
                ->value('consumed_at')
        );
        $domainStates = json_decode(
            (string) DB::table('kernel_rotation_state_v2')->value('domain_states'),
            true,
        );
        $this->assertSame('VISIBLE', $domainStates['2']['geographie']);
    }

    public function test_prepare_consumes_one_fact_and_estompes_its_domain(): void
    {
        $this->insertActiveState(2, 'geographie', 'tour-domain-close', 'bp-active');
        $this->planner->receiveTaxonomyTerminalFact('fact-domain-close', 2, 'geographie');

        $blueprint = $this->newBlueprint('bp-after-domain-close');
        $resolution = $this->prepareBlueprint($blueprint);

        $this->assertSame(2, $resolution->depth);
        $this->assertSame('histoire', $resolution->domain);
        $this->assertSame('histoire', $blueprint->domain);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $domainStates = json_decode((string) $state->domain_states, true);
        $this->assertSame('ESTOMPÉ', $domainStates['2']['geographie']);
        $this->assertSame('OPEN', $state->tour_state);
        $this->assertNull($state->last_closed_tour_id);
        $this->assertNotNull(
            DB::table('kernel_taxonomy_terminal_facts')
                ->where('fact_id', 'fact-domain-close')
                ->value('consumed_at')
        );
    }

    public function test_final_visible_domain_closes_tour_and_advances_to_next_required_depth(): void
    {
        $domainStates = $this->visibleDomainStates();
        foreach (self::DOMAINS as $domain) {
            $domainStates['2'][$domain] = $domain === 'science' ? 'VISIBLE' : 'ESTOMPÉ';
        }

        $this->insertActiveState(
            2,
            'science',
            'tour-closing-depth-two',
            'bp-active',
            $domainStates,
        );
        $this->planner->receiveTaxonomyTerminalFact('fact-final-domain', 2, 'science');

        $blueprint = $this->newBlueprint('bp-depth-four');
        $resolution = $this->prepareBlueprint($blueprint);
        $state = DB::table('kernel_rotation_state_v2')->first();

        $this->assertSame(4, $resolution->depth);
        $this->assertSame('geographie', $resolution->domain);
        $this->assertSame('tour-closing-depth-two', $state->last_closed_tour_id);
        $this->assertSame(2, (int) $state->last_closed_depth);
        $this->assertSame('OPEN', $state->tour_state);
        $this->assertNotSame('tour-closing-depth-two', $state->active_tour_id);
        $this->assertSame(
            1,
            (int) DB::table('kernel_depth_matrix')->where('depth', 2)->value('cycle_completed')
        );

        $nextDepthStates = json_decode((string) $state->domain_states, true);
        foreach (self::DOMAINS as $domain) {
            $this->assertSame('VISIBLE', $nextDepthStates['4'][$domain]);
        }
    }

    public function test_final_required_tour_persists_production_on_hold(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }
        DB::table('kernel_depth_matrix')
            ->where('depth', 10)
            ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[10] - 1]);

        $domainStates = $this->visibleDomainStates();
        foreach (self::DOMAINS as $domain) {
            $domainStates['10'][$domain] = $domain === 'science' ? 'VISIBLE' : 'ESTOMPÉ';
        }

        $this->insertActiveState(
            10,
            'science',
            'tour-final-need',
            'bp-active',
            $domainStates,
        );
        $this->planner->receiveTaxonomyTerminalFact('fact-final-need', 10, 'science');

        $blueprint = $this->newBlueprint('bp-unused-on-hold');
        $resolution = $this->prepareBlueprint($blueprint);
        $state = DB::table('kernel_rotation_state_v2')->first();

        $this->assertTrue($resolution->isNoRotation());
        $this->assertSame(
            KernelRotationPlanner::RESULT_PRODUCTION_ON_HOLD,
            $resolution->noRotationReason(),
        );
        $this->assertSame('PRODUCTION_ON_HOLD', $state->depth_state);
        $this->assertSame('CLOSED', $state->tour_state);
        $this->assertSame('tour-final-need', $state->last_closed_tour_id);
        $this->assertSame(10, (int) $state->last_closed_depth);
        $this->assertNull($state->active_blueprint_identity);
        $this->assertNull($blueprint->depth);
        $this->assertNull($blueprint->domain);
    }

    public function test_consumed_final_fact_cannot_close_the_same_tour_twice(): void
    {
        $domainStates = $this->visibleDomainStates();
        foreach (self::DOMAINS as $domain) {
            $domainStates['2'][$domain] = $domain === 'science' ? 'VISIBLE' : 'ESTOMPÉ';
        }

        $this->insertActiveState(
            2,
            'science',
            'tour-once-only',
            'bp-active',
            $domainStates,
        );
        $this->planner->receiveTaxonomyTerminalFact('fact-once-only', 2, 'science');

        $this->prepareBlueprint($this->newBlueprint('bp-first-after-close'));
        $newTourId = (string) DB::table('kernel_rotation_state_v2')->value('active_tour_id');

        $this->planner->receiveTaxonomyTerminalFact('fact-once-only', 2, 'science');
        $secondResolution = $this->prepareBlueprint($this->newBlueprint('bp-second-after-close'));

        $this->assertSame(4, $secondResolution->depth);
        $this->assertSame('histoire', $secondResolution->domain);
        $this->assertSame(
            1,
            (int) DB::table('kernel_depth_matrix')->where('depth', 2)->value('cycle_completed')
        );
        $this->assertSame(
            $newTourId,
            DB::table('kernel_rotation_state_v2')->value('active_tour_id')
        );
        $this->assertSame(1, DB::table('kernel_taxonomy_terminal_facts')->count());
    }

    public function test_removed_external_exhaustion_entries_only_act_as_rejection_guards(): void
    {
        try {
            $this->planner->receiveDomainExhausted(2, 'geographie');
            $this->fail('The removed domain-exhaustion entry must reject callers.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Entrée v3', $exception->getMessage());
        }

        try {
            $this->planner->receiveDepthExhausted(2);
            $this->fail('The removed depth-exhaustion entry must reject callers.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('interne à KRP v4.0', $exception->getMessage());
        }
    }

    private function prepareBlueprint(KernelBlueprint $blueprint): object
    {
        return DB::transaction(function () use ($blueprint): object {
            $state = (new KernelRotationStateRepository())->firstForUpdate();

            return $this->planner->prepareNewBlueprint($blueprint, $state);
        });
    }

    private function newBlueprint(string $blueprintId): KernelBlueprint
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($blueprintId);

        return $blueprint;
    }

    /**
     * @param array<string, array<string, string>>|null $domainStates
     */
    private function insertActiveState(
        int $depth,
        string $domain,
        string $tourId,
        ?string $blueprintId,
        ?array $domainStates = null,
    ): void {
        $position = array_search($domain, DepthTourState::DOMAIN_CYCLE, true);
        $this->assertIsInt($position);

        DB::table('kernel_rotation_state_v2')->insert([
            'active_depth' => $depth,
            'active_tour_id' => $tourId,
            'tour_state' => 'OPEN',
            'last_closed_tour_id' => null,
            'last_closed_depth' => null,
            'depth_state' => 'ROTATION_ACTIVE',
            'domain_states' => json_encode($domainStates ?? $this->visibleDomainStates()),
            'domain_position' => $position,
            'active_blueprint_identity' => $blueprintId,
            'last_counted_blueprint_identity' => null,
            'pending_depth_exhausted_depth' => null,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function visibleDomainStates(): array
    {
        $states = [];
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $states[(string) $depth] = array_fill_keys(self::DOMAINS, 'VISIBLE');
        }

        return $states;
    }

    private function seedDepthMatrix(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->insert([
                'depth' => $depth,
                'cycle_target' => DepthNeedMatrix::CYCLE_TARGET[$depth],
                'cycle_completed' => 0,
                'empty_progress_current_tour' => 0,
                'current_tour_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}