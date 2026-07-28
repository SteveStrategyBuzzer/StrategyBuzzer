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
 * Tests de KernelPipelineOrchestrator — PÉRIMÈTRE KRP UNIQUEMENT.
 *
 * Couvre exclusivement les responsabilités de 02_KernelRotationPlanner :
 *   - Création du Blueprint (Factory)
 *   - Sélection Depth + Domaine (KRP planV2)
 *   - Boucle EMPTY : applyEmptyTransitionV2 + planV2 + Taxonomy
 *   - TERRITORY_PROVIDED → ROTATION_ASSIGNED
 *   - PRODUCTION_ON_HOLD
 *   - Guard : Factory bloque si Blueprint actif existe
 *
 * Hors périmètre (tests futurs séparés) :
 *   - IdeaSlotLoader (dominant_idea absent)
 *   - KLD (KeyLearningDirection)
 *   - KEY_STRUCTURE
 *   - confirmConsumed()
 *   - Phases 1 & 2 / Validations
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

    /** Retourne les états {domain → ON|OFF} depuis kernel_rotation_state_v2. */
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
    // Tests KRP — responsabilités propres
    // =========================================================================

    /**
     * Quand Taxonomy fournit un territoire, l'orchestrateur retourne ROTATION_ASSIGNED.
     * Le Blueprint est ENGAGED_IN_PIPELINE en DB avec depth et domain écrits.
     *
     * Contrat de sortie KRP :
     *   status = ROTATION_ASSIGNED
     *   blueprint.blueprint_id non null
     *   blueprint.depth non null
     *   blueprint.domain non null
     */
    public function test_rotation_assigned_when_taxonomy_returns_territory(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'Moyen Âge',
            'subject'    => 'La Magna Carta',
        ]);

        $result = $this->makeOrchestrator($taxonomy)->run(null);

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
     * Quand TaxonomyProgressManager::peekNext() retourne null (EMPTY),
     * l'orchestrateur appelle applyEmptyTransitionV2 et relance planV2.
     *
     * Preuves :
     *   - tour_domain_states en DB montre exactement 1 domaine OFF
     *   - peekNext appelé exactement 2 fois
     *   - même Blueprint réutilisé (RÈGLE KRP-R11)
     */
    public function test_empty_taxonomy_triggers_apply_empty_transition_v2(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return null; // EMPTY
                }
                return [
                    'sub_domain' => 'Moyen Âge',
                    'subject'    => 'La Magna Carta',
                ];
            });

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame(2, $callCount, 'peekNext doit avoir été appelé exactement 2 fois');

        $tourStates = $this->getTourStates();
        $this->assertNotEmpty($tourStates, 'tour_domain_states doit être présent en DB');

        $offCount = count(array_filter($tourStates, fn($s) => $s === 'OFF'));
        $this->assertSame(1, $offCount, 'Exactement 1 domaine doit être OFF après un signal EMPTY');
    }

    /**
     * EMPTY réutilise le même Blueprint — aucun nouveau Blueprint créé.
     * RÈGLE KRP-R11.
     */
    public function test_empty_reuses_same_blueprint(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return $callCount === 1 ? null : ['sub_domain' => 'X', 'subject' => 'Y'];
            });

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);

        // Un seul Blueprint en DB
        $count = DB::table('kernel_blueprint_runs')->count();
        $this->assertSame(1, $count, 'EMPTY ne doit pas créer un nouveau Blueprint');
    }

    /**
     * PRODUCTION_ON_HOLD quand tous les Depths ont atteint leur cible.
     */
    public function test_production_on_hold_when_all_depths_completed(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $result   = $this->makeOrchestrator($taxonomy)->run(null);

        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);
    }

    /**
     * PRODUCTION_ON_HOLD → Blueprint classé NOT_ENGAGED_PRODUCTION_ON_HOLD en DB.
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

        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);

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

    /**
     * KRP écrit uniquement depth + domain dans le Blueprint.
     * Les champs Taxonomy (subdomain, subject) sont écrits après TERRITORY_PROVIDED.
     * Aucun autre champ métier n'est touché par KRP.
     */
    public function test_krp_writes_only_depth_and_domain(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'Espace',
            'subject'    => 'La Lune',
        ]);

        $result    = $this->makeOrchestrator($taxonomy)->run(null);
        $blueprint = $result['blueprint'];

        $this->assertNotNull($blueprint->depth, 'depth doit être écrit par KRP');
        $this->assertNotNull($blueprint->domain, 'domain doit être écrit par KRP');

        // Vérifier en DB que depth + domain_code sont présents
        $row = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->first();
        $this->assertNotNull($row->depth);
        $this->assertNotNull($row->domain_code);
        // kernel_code jamais écrit par KRP
        $this->assertObjectNotHasProperty('kernel_code', $row);
    }
}
