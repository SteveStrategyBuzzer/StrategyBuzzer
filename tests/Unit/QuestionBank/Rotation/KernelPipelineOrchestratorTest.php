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
 *   - TERRITORY_PROVIDED → ROTATION_ASSIGNED (fillTaxonomy + engagement)
 *   - PRODUCTION_ON_HOLD
 *   - Guard : Factory bloque si Blueprint actif existe
 *
 * Hors périmètre :
 *   - QuestionIntent / Phases / ReadyBank / confirmConsumed :
 *     ⏸ BLOCKERS ARCHITECTURAUX (audit 2026-08-11) — contrats officiels manquants,
 *     aucun code n'existe pour ces frontières (RÈGLE DU VIDE)
 *   - ⛔ KLD / KEY_STRUCTURE / IdeaSlotLoader : SUPERSEDED — retirés du flow
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
            'sub_domain'    => 'Moyen Âge',
            'subject'       => 'La Magna Carta',
            'dominant_idea' => 'La Magna Carta limite le pouvoir royal',
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
                    'sub_domain'    => 'Moyen Âge',
                    'subject'       => 'La Magna Carta',
                    'dominant_idea' => 'La Magna Carta limite le pouvoir royal',
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
                return $callCount === 1 ? null : ['sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z'];
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

    // =========================================================================
    // Tests KRP-R20 — Primauté du Blueprint
    // =========================================================================

    /**
     * KRP-R20 : Factory appelée exactement une fois, même avec plusieurs EMPTYs.
     *
     * Preuve comportementale : exactement 1 ligne dans kernel_blueprint_runs
     * après run(), quelle que soit la profondeur de la boucle EMPTY.
     */
    public function test_factory_called_exactly_once_even_with_multiple_empties(): void
    {
        // 3 appels EMPTY consécutifs, puis un territoire
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount <= 3) {
                    return null; // EMPTY
                }
                return ['sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z'];
            });

        $this->makeOrchestrator($taxonomy)->run(null);

        $count = DB::table('kernel_blueprint_runs')->count();
        $this->assertSame(1, $count,
            'Factory doit être appelée exactement une fois — KRP-R20'
        );
        $this->assertSame(4, $callCount,
            'peekNext doit avoir été appelé 4 fois (3 EMPTY + 1 territoire)'
        );
    }

    /**
     * KRP-R20 : blueprint_id identique à travers plusieurs EMPTYs.
     *
     * Le même Blueprint (même blueprint_id) est réutilisé lors de chaque
     * itération EMPTY. Aucun nouveau Blueprint n'est créé entre les EMPTYs.
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

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        // Un seul Blueprint en DB
        $allBlueprints = DB::table('kernel_blueprint_runs')->get();
        $this->assertCount(1, $allBlueprints,
            'Après 3 EMPTYs, exactement 1 Blueprint doit exister en DB — KRP-R20'
        );

        // Son blueprint_id correspond à ce que run() a retourné
        $this->assertSame(
            $result['blueprint']->blueprint_id,
            $allBlueprints->first()->blueprint_id,
            'Le blueprint_id retourné doit correspondre au Blueprint créé par Factory — KRP-R20'
        );
    }

    /**
     * KRP-R20 : planV2 reçoit le Blueprint créé par Factory.
     *
     * Preuve comportementale :
     *   - le blueprint_id présent en DB avant engagement correspond à celui
     *     que planV2 aurait dû recevoir (le seul créé par Factory)
     *   - après run(), ce blueprint_id est dans kernel_blueprint_runs avec
     *     state ENGAGED_IN_PIPELINE (preuve que planV2 a bien travaillé dessus)
     */
    public function test_planv2_receives_the_blueprint_created_by_factory(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain'    => 'Univers',
            'subject'       => 'Big Bang',
            'dominant_idea' => 'Le Big Bang marque l\'origine de l\'expansion de l\'Univers',
        ]);

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        // Le Blueprint retourné existe en DB avec l'état final d'engagement
        $row = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $result['blueprint']->blueprint_id)
            ->first();

        $this->assertNotNull($row,
            'Le Blueprint créé par Factory doit exister en DB — KRP-R20'
        );
        $this->assertSame('ENGAGED_IN_PIPELINE', $row->execution_state,
            'planV2 a bien travaillé sur le Blueprint créé par Factory — KRP-R20'
        );
        $this->assertNotNull($row->depth,
            'KRP a écrit depth sur le Blueprint reçu de Factory — KRP-R20'
        );
        $this->assertNotNull($row->domain_code,
            'KRP a écrit domain sur le Blueprint reçu de Factory — KRP-R20'
        );

        // Aucun autre Blueprint ne doit exister
        $this->assertSame(1, (int) DB::table('kernel_blueprint_runs')->count(),
            'Factory est appelée exactement une fois — KRP-R20'
        );
    }

    /**
     * KRP-R20 : le système de types PHP garantit que KRP ne peut pas
     * s'exécuter sans recevoir une instance de KernelBlueprint.
     *
     * Preuve architecturale via ReflectionMethod :
     *   KernelRotationPlanner::planV2() déclare KernelBlueprint comme
     *   premier paramètre obligatoire → impossible d'appeler planV2 sans Blueprint.
     */
    public function test_krp_type_system_enforces_blueprint_parameter(): void
    {
        $ref    = new \ReflectionMethod(KernelRotationPlanner::class, 'planV2');
        $params = $ref->getParameters();

        $this->assertNotEmpty($params, 'planV2 doit avoir au moins un paramètre');

        $firstParam = $params[0];
        $this->assertSame('blueprint', $firstParam->getName(),
            "Le premier paramètre de planV2 doit s'appeler 'blueprint' — KRP-R20"
        );
        $this->assertFalse($firstParam->isOptional(),
            'Le paramètre blueprint doit être obligatoire — KRP-R20'
        );

        $type = $firstParam->getType();
        $this->assertNotNull($type, 'Le paramètre blueprint doit être typé — KRP-R20');
        $this->assertSame(KernelBlueprint::class, (string) $type,
            'Le type doit être KernelBlueprint — KRP-R20'
        );
    }

    /**
     * KRP-R20 + KRP-R18 : PRODUCTION_ON_HOLD — le Blueprint est créé avant
     * que KRP constate l'absence de besoin.
     *
     * L'ordre imposé est maintenu même lorsqu'aucune production n'est requise :
     *   Factory → Blueprint CREATED_UNENGAGED → planV2 → PRODUCTION_ON_HOLD
     */
    public function test_production_on_hold_blueprint_exists_before_hold_detected(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        // peekNext ne doit jamais être appelé — planV2 retourne PRODUCTION_ON_HOLD
        $taxonomy->expects($this->never())->method('peekNext');

        $result = $this->makeOrchestrator($taxonomy)->run(null);

        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);

        // Le Blueprint doit exister en DB — Factory a bien été appelée avant planV2
        $row = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $result['blueprint']->blueprint_id)
            ->first();
        $this->assertNotNull($row,
            'Factory doit être appelée avant que KRP détecte PRODUCTION_ON_HOLD — KRP-R20'
        );
        $this->assertSame('NOT_ENGAGED_PRODUCTION_ON_HOLD', $row->execution_state,
            'Blueprint marqué NOT_ENGAGED_PRODUCTION_ON_HOLD après détection par planV2 — KRP-R20'
        );
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
            'sub_domain'    => 'Espace',
            'subject'       => 'La Lune',
            'dominant_idea' => 'La Lune provoque les marées terrestres',
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
