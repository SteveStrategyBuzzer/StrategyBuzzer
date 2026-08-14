<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests de KernelPipelineOrchestrator V3 (02_KernelRotationPlanner v3.2).
 *
 * Couvre exclusivement les responsabilités de l'orchestrateur :
 *   - Gate : aucun Blueprint créé si NoRotation (PRODUCTION_ON_HOLD ou PENDING)
 *   - Factory appelée exactement une fois (KRP-R20)
 *   - fillRotation write-once via applyRotation (KRP-R01)
 *   - EMPTY loop : Blueprint réutilisé (KRP-R11), même blueprint_id
 *   - TERRITORY_PROVIDED → ROTATION_ASSIGNED
 *   - PRODUCTION_ON_HOLD via depth_state (V3 gate)
 *   - PRODUCTION_ON_HOLD via EMPTY tour complet (legacy)
 *   - Guard : Factory bloque si Blueprint actif existe
 *
 * Hors périmètre :
 *   - KernelCodeEngine : testé dans KernelCodeEngineTest
 *   - Phases / ReadyBank / confirmConsumed : BLOCKERS ARCHITECTURAUX
 *   - ⛔ planV2 / applyEmptyTransitionV2 : SUPPRIMÉS en V3
 *
 * DB : SQLite in-memory avec schéma V3.
 */
class KernelPipelineOrchestratorTest extends TestCase
{
    /** Domaines officiels du DomainCycle. */
    private const DOMAINS = [
        'geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 22)->nullable()->unique();
            $table->timestamp('engaged_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_code_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('depth');
            $table->char('domain_code', 2);
            $table->unsignedInteger('next_value')->default(0);
            $table->timestamps();
            $table->primary(['depth', 'domain_code']);
        });

        // Schéma V3 complet (nouvelles colonnes incluses)
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

        // Seed : tous les Depths du cycle avec cycle_target > 0
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->insert([
                'depth'           => $depth,
                'cycle_target'    => DepthNeedMatrix::CYCLE_TARGET[$depth],
                'cycle_completed' => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            foreach (self::DOMAINS as $domain) {
                DB::table('kernel_depth_domain_totals')->insert([
                    'depth'                 => $depth,
                    'domain_code'           => $domain,
                    'kernel_received_total' => 0,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_code_sequences');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeOrchestrator(TaxonomyNavigatorInterface $taxonomy): KernelPipelineOrchestrator
    {
        return new KernelPipelineOrchestrator(
            new KernelBlueprintFactory(),
            new KernelRotationPlanner(),
            $taxonomy,
            new KernelCodeEngine(),
        );
    }

    /** Retourne les états {domain → ON|OFF} depuis tour_domain_states en DB. */
    private function getTourStates(): array
    {
        $row = DB::table('kernel_rotation_state_v2')->first();
        if ($row === null) {
            return [];
        }
        $decoded = json_decode((string) $row->tour_domain_states, true);
        return $decoded['states'] ?? $decoded ?? [];
    }

    /** Insère un état V3 avec depth_state = PRODUCTION_ON_HOLD. */
    private function insertProductionOnHoldState(): void
    {
        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'PRODUCTION_ON_HOLD',
            'active_depth'                   => null,
            'domain_position'                => null,
            'domain_states'                  => null,
            'pending_depth_exhausted_depth'  => null,
            'tour_domain_states'             => null,
            'active_blueprint_identity'      => null,
            'last_counted_blueprint_identity' => null,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);
    }

    // =========================================================================
    // Tests : ROTATION_ASSIGNED (flux nominal)
    // =========================================================================

    /**
     * Quand Taxonomy fournit un territoire, l'orchestrateur retourne ROTATION_ASSIGNED.
     * Le Blueprint est ENGAGED_IN_PIPELINE en DB avec depth + domain écrits.
     */
    public function test_rotation_assigned_when_taxonomy_returns_territory(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain'    => 'Moyen Âge',
            'subject'       => 'La Magna Carta',
            'dominant_idea' => 'La Magna Carta limite le pouvoir royal',
        ]);

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertInstanceOf(KernelBlueprint::class, $result['blueprint']);
        $this->assertNotNull($result['blueprint']->blueprint_id);
        $this->assertNotNull($result['blueprint']->depth);
        $this->assertNotNull($result['blueprint']->domain);

        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $result['blueprint']->blueprint_id)
            ->first();
        $this->assertNotNull($run);
        $this->assertSame('ENGAGED_IN_PIPELINE', $run->execution_state);
        $this->assertNotNull($run->depth);
        $this->assertNotNull($run->domain_code);
    }

    /**
     * Depth=2 et domain='geographie' sont les valeurs initiales (INIT_PROPRE).
     */
    public function test_initial_rotation_selects_depth_2_and_geographie(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(2, $result['blueprint']->depth);
        $this->assertSame('geographie', $result['blueprint']->domain);
    }

    /**
     * KRP écrit depth + domain dans le Blueprint et en DB.
     * kernel_code est écrit par KernelCodeEngine (PAS par KRP).
     */
    public function test_krp_writes_depth_and_domain_kernel_code_engine_writes_kernel_code(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain'    => 'Espace',
            'subject'       => 'La Lune',
            'dominant_idea' => 'La Lune provoque les marées terrestres',
        ]);

        $result    = $this->makeOrchestrator($taxonomy)->run();
        $blueprint = $result['blueprint'];

        $this->assertNotNull($blueprint->depth,  'KRP écrit depth');
        $this->assertNotNull($blueprint->domain, 'KRP écrit domain');

        $row = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->first();

        $this->assertNotNull($row->depth,        'KRP persiste depth en DB');
        $this->assertNotNull($row->domain_code,  'KRP persiste domain_code en DB');
        $this->assertNotNull($row->kernel_code,  'KernelCodeEngine persiste kernel_code');
        $this->assertMatchesRegularExpression(
            KernelCodeEngine::FORMAT_REGEX,
            (string) $row->kernel_code,
            'kernel_code respecte le format DD-DO-SUB-SUJ-IDE-VVVV'
        );
    }

    /**
     * applyRotation persiste active_depth + domain_position dans l'état V2.
     */
    public function test_apply_rotation_persists_domain_position_in_state(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $this->makeOrchestrator($taxonomy)->run();

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNotNull($state, 'État V3 doit exister après un run()');
        $this->assertSame(2, (int) $state->active_depth, 'active_depth = 2 (premier Depth)');
        $this->assertSame(0, (int) $state->domain_position, 'domain_position = 0 (geographie)');
    }

    // =========================================================================
    // Tests : EMPTY loop (legacy SUPERSEDED — conservé jusqu'à LOT C)
    // =========================================================================

    /**
     * EMPTY : peekNext retourne null une fois puis un territoire.
     * Résultat : ROTATION_ASSIGNED, peekNext appelé 2 fois, 1 domaine OFF en tour.
     */
    public function test_empty_taxonomy_cycles_domain_and_returns_rotation_assigned(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return null; // EMPTY
                }
                return ['sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z'];
            });

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame(2, $callCount, 'peekNext appelé exactement 2 fois (1 EMPTY + 1 territoire)');

        $tourStates = $this->getTourStates();
        $this->assertNotEmpty($tourStates, 'tour_domain_states doit être présent en DB');
        $offCount = count(array_filter($tourStates, fn($s) => $s === 'OFF'));
        $this->assertSame(1, $offCount, 'Exactement 1 domaine OFF après 1 signal EMPTY');
    }

    /**
     * EMPTY : le même Blueprint est réutilisé — aucun nouveau Blueprint créé (KRP-R11).
     */
    public function test_empty_reuses_same_blueprint(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return $callCount === 1 ? null : ['sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z'];
            });

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame(1, (int) DB::table('kernel_blueprint_runs')->count(),
            'EMPTY ne crée pas de nouveau Blueprint (KRP-R11)'
        );
    }

    /**
     * Factory appelée exactement une fois, même avec 3 EMPTYs consécutifs (KRP-R20).
     */
    public function test_factory_called_exactly_once_even_with_multiple_empties(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return $callCount <= 3 ? null : ['sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z'];
            });

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame(1, (int) DB::table('kernel_blueprint_runs')->count(),
            'Factory doit être appelée exactement une fois — KRP-R20'
        );
        $this->assertSame(4, $callCount,
            'peekNext appelé 4 fois (3 EMPTY + 1 territoire)'
        );
    }

    /**
     * fillRotation est appelé UNE SEULE FOIS même avec EMPTY (KRP-R01).
     *
     * Preuve : blueprint retourné a depth + domain définis, pas de LogicException.
     */
    public function test_fill_rotation_called_once_even_after_empty(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return $callCount === 1 ? null : ['sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z'];
            });

        // Doit passer sans LogicException (pas de double fillRotation)
        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertNotNull($result['blueprint']->depth,  'depth défini après EMPTY');
        $this->assertNotNull($result['blueprint']->domain, 'domain défini après EMPTY');
        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
    }

    /**
     * Le même blueprint_id est préservé à travers plusieurs EMPTYs.
     */
    public function test_same_blueprint_id_preserved_through_multiple_empties(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return $callCount <= 3 ? null : ['sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z'];
            });

        $result = $this->makeOrchestrator($taxonomy)->run();

        $allBlueprints = DB::table('kernel_blueprint_runs')->get();
        $this->assertCount(1, $allBlueprints, 'Un seul Blueprint en DB après 3 EMPTYs');
        $this->assertSame(
            $result['blueprint']->blueprint_id,
            $allBlueprints->first()->blueprint_id,
            'blueprint_id retourné = celui créé par Factory'
        );
    }

    /**
     * PRODUCTION_ON_HOLD quand EMPTY tour complet (8/8 domaines épuisés via legacy).
     */
    public function test_production_on_hold_when_empty_tour_complete(): void
    {
        // Saturer tous les Depths : cycle_completed = cycle_target
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }

        // Injecter un état avec Tour déjà à 7/8 (7 domaines OFF, 1 ON)
        // Pour déclencher le Tour complet sur le premier EMPTY
        $tourState = DepthTourState::initTour();
        $domains   = DepthTourState::DOMAIN_CYCLE;
        for ($i = 0; $i < 7; $i++) {
            $tourState = $tourState->applyEmpty($domains[$i]);
        }

        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => 2,
            'domain_position'                => null,
            'domain_states'                  => null,
            'pending_depth_exhausted_depth'  => null,
            'tour_domain_states'             => json_encode($tourState->toArray()),
            'active_blueprint_identity'      => null,
            'last_counted_blueprint_identity' => null,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn(null); // Toujours EMPTY

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);
    }

    // =========================================================================
    // Tests : Gate V3 (PRODUCTION_ON_HOLD sans Blueprint créé)
    // =========================================================================

    /**
     * V3 Gate : si depth_state = PRODUCTION_ON_HOLD → aucun Blueprint créé.
     * Résultat : STATUS_PRODUCTION_ON_HOLD, blueprint = null.
     */
    public function test_gate_returns_production_on_hold_without_creating_blueprint(): void
    {
        $this->insertProductionOnHoldState();

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->expects($this->never())->method('peekNext'); // peekNext ne doit pas être appelé

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);
        $this->assertNull($result['blueprint'], 'V3 Gate : aucun Blueprint créé si PRODUCTION_ON_HOLD');
        $this->assertSame(0, (int) DB::table('kernel_blueprint_runs')->count(),
            'Factory ne doit pas être appelée quand le gate bloque'
        );
    }

    /**
     * V3 Gate : PRODUCTION_ON_HOLD sans ligne d'état N'EST PAS déclenchée.
     * (INIT_PROPRE = premier appel → RotationAvailable{depth=2, domain='geographie'})
     */
    public function test_gate_does_not_block_on_init_propre(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
    }

    // =========================================================================
    // Tests : Single-active guard (Factory)
    // =========================================================================

    /**
     * Factory lève RuntimeException si un Blueprint actif existe déjà.
     * L'orchestrateur ne crée pas deux Blueprints simultanément.
     */
    public function test_factory_stops_if_active_blueprint_exists(): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'existing-bp-001',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Blueprint actif existe déjà/');

        $this->makeOrchestrator($taxonomy)->run();
    }

    // =========================================================================
    // Tests : État V3 après run réussi
    // =========================================================================

    /**
     * Après un run() réussi, active_blueprint_identity est écrit dans l'état V2.
     */
    public function test_state_has_active_blueprint_identity_after_run(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $result = $this->makeOrchestrator($taxonomy)->run();

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNotNull($state);
        $this->assertSame(
            $result['blueprint']->blueprint_id,
            $state->active_blueprint_identity
        );
    }

    /**
     * Après un run() réussi, domain_states sont initialisés (56 paires ACTIF).
     */
    public function test_state_has_domain_states_initialized_after_first_run(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $this->makeOrchestrator($taxonomy)->run();

        $state        = DB::table('kernel_rotation_state_v2')->first();
        $domainStates = json_decode((string) $state->domain_states, true);

        $this->assertIsArray($domainStates);
        $this->assertCount(7, $domainStates, '7 Depths dans domain_states');

        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $depthKey = (string) $depth;
            $this->assertArrayHasKey($depthKey, $domainStates);
            $this->assertCount(8, $domainStates[$depthKey], "8 domaines pour Depth {$depth}");
            foreach (self::DOMAINS as $domain) {
                $this->assertSame(
                    'ACTIF',
                    $domainStates[$depthKey][$domain],
                    "domain_states[{$depth}][{$domain}] doit être ACTIF"
                );
            }
        }
    }

    /**
     * Après un run() réussi, depth_state = ROTATION_ACTIVE.
     */
    public function test_state_depth_state_is_rotation_active_after_run(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $this->makeOrchestrator($taxonomy)->run();

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame('ROTATION_ACTIVE', $state->depth_state);
    }
}
