<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests de KernelPipelineOrchestrator.
 *
 * Vérifications (Section 4 du contrat d'audit) :
 *   - TaxonomyProgressManager::peekNext() === null est interprété comme EMPTY
 *   - applyEmptyTransitionV2() est bien déclenché (vérifié via état DB)
 *   - Un territoire non-null conduit à l'état ENGAGED
 *   - PRODUCTION_ON_HOLD lorsque tous les Depths ont atteint leur cible
 *
 * KernelRotationPlanner et TaxonomyNavigatorInterface sont tous deux final/interface.
 * On utilise :
 *   - un mock de TaxonomyNavigatorInterface (pas final)
 *   - le vrai KernelRotationPlanner avec état DB contrôlé
 *
 * DB : SQLite in-memory, tables créées manuellement.
 */
class KernelPipelineOrchestratorTest extends TestCase
{
    /** Domaines officiels du DomainCycle. */
    private const DOMAINS = ['geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science'];

    protected function setUp(): void
    {
        parent::setUp();

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

        // Seed tous les Depths du cycle avec cycle_target > 0
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
        );
    }

    /**
     * Retourne le tableau `states` (domain → 'ON'|'OFF') depuis kernel_rotation_state_v2.
     * toArray() structure : { states: {...}, empty_progress: N }
     */
    private function getTourStates(): array
    {
        $row = DB::table('kernel_rotation_state_v2')->first();
        if ($row === null) {
            return [];
        }
        $decoded = json_decode($row->tour_domain_states, true);
        return $decoded['states'] ?? $decoded ?? [];
    }

    // =========================================================================
    // Tests
    // =========================================================================

    /**
     * Quand Taxonomy fournit un territoire, le Blueprint est ENGAGED.
     */
    public function test_engaged_when_taxonomy_returns_territory(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain'    => 'Moyen Âge',
            'subject'       => 'La Magna Carta',
            'dominant_idea' => 'droits',
        ]);
        $taxonomy->expects($this->once())->method('confirmConsumed');

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        $this->assertSame('ENGAGED', $result['status']);
        $this->assertInstanceOf(KernelBlueprint::class, $result['blueprint']);
        $this->assertNotNull($result['blueprint']->blueprint_id);
        $this->assertNotNull($result['blueprint']->depth);
        $this->assertNotNull($result['blueprint']->domain);

        // Vérifier l'état en DB
        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $result['blueprint']->blueprint_id)
            ->first();
        $this->assertNotNull($run);
        $this->assertSame('ENGAGED_IN_PIPELINE', $run->execution_state);
        $this->assertNotNull($run->depth);
        $this->assertNotNull($run->domain_code);
    }

    /**
     * SECTION 4 DU CONTRAT D'AUDIT :
     *
     * Quand TaxonomyProgressManager::peekNext() retourne null,
     * l'orchestrateur doit interpréter ce retour comme EMPTY et appeler
     * applyEmptyTransitionV2($emptyDomain).
     *
     * Preuve : tour_domain_states en DB montre un domaine passé de ON → OFF.
     */
    public function test_empty_taxonomy_triggers_apply_empty_transition_v2(): void
    {
        // peekNext : null au premier appel (EMPTY), territoire au second
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return null; // EMPTY
                }
                return [
                    'sub_domain'    => 'Moyen Âge',
                    'subject'       => 'La Magna Carta',
                    'dominant_idea' => 'droits',
                ];
            });
        $taxonomy->expects($this->once())->method('confirmConsumed');

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        // ── Le résultat est ENGAGED ───────────────────────────────────────────
        $this->assertSame('ENGAGED', $result['status']);
        $this->assertSame(2, $callCount, 'peekNext doit avoir été appelé exactement 2 fois');

        // ── Preuve que applyEmptyTransitionV2 a été déclenché ─────────────────
        // Le DomainCycle démarre à 'geographie' (premier ON sans previousDomain).
        // Après le signal EMPTY sur 'geographie', tour_domain_states doit montrer
        // 'geographie' = OFF et tous les autres = ON.
        $tourStates = $this->getTourStates();
        $this->assertNotEmpty($tourStates, 'tour_domain_states doit être présent en DB');

        // Compter les domaines OFF : exactement 1 après un seul signal EMPTY
        $offCount = count(array_filter($tourStates, fn($s) => $s === 'OFF'));
        $this->assertSame(1, $offCount,
            'Exactement 1 domaine doit être OFF après un signal EMPTY'
        );
    }

    /**
     * PRODUCTION_ON_HOLD quand tous les Depths ont atteint leur cible.
     */
    public function test_production_on_hold_when_all_depths_completed(): void
    {
        // Marquer TOUS les Depths comme complétés
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain'    => 'Moyen Âge',
            'subject'       => 'La Magna Carta',
            'dominant_idea' => 'droits',
        ]);

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        $this->assertSame('PRODUCTION_ON_HOLD', $result['status']);
    }

    /**
     * Le Blueprint créé lors de PRODUCTION_ON_HOLD porte l'état
     * NOT_ENGAGED_PRODUCTION_ON_HOLD en DB.
     */
    public function test_blueprint_state_is_not_engaged_when_on_hold(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $result   = $this->makeOrchestrator($taxonomy)->run(null);

        $this->assertSame('PRODUCTION_ON_HOLD', $result['status']);

        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $result['blueprint']->blueprint_id)
            ->first();
        $this->assertNotNull($run);
        $this->assertSame('NOT_ENGAGED_PRODUCTION_ON_HOLD', $run->execution_state);
    }

    /**
     * KernelBlueprintFactory lève une RuntimeException si un Blueprint actif
     * existe déjà — l'orchestrateur ne crée pas deux Blueprints simultanément.
     */
    public function test_factory_stops_if_active_blueprint_exists(): void
    {
        // Injecter manuellement un Blueprint actif
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'existing-bp-001',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Blueprint actif existe déjà/');

        $this->makeOrchestrator($taxonomy)->run(null);
    }
}
