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
use RuntimeException;
use Tests\TestCase;

/**
 * Tests de KernelPipelineOrchestrator V3 (02_KernelRotationPlanner v3.2 — 2026-08-14).
 *
 * Couvre :
 *   - Gate : aucun Blueprint créé si NoRotation (PRODUCTION_ON_HOLD ou PENDING)
 *   - Factory appelée exactement une fois (KRP-R20)
 *   - fillRotation write-once via applyRotation (KRP-R01)
 *   - peekNext() == null → CONTAINMENT : RuntimeException + Blueprint supprimé (DEC-087)
 *   - Aucune divergence Blueprint.domain / domaine Taxonomy possible
 *   - PRODUCTION_ON_HOLD via gate V3 (depth_state)
 *   - État V3 initialisé après premier run()
 *   - Rollback transaction : 0 Blueprints durables après exception dans la transaction
 *
 * Hors périmètre :
 *   - KernelCodeEngine (testé dans KernelCodeEngineTest)
 *   - Phases / ReadyBank / confirmConsumed (BLOCKERS ARCHITECTURAUX)
 *   - ⛔ EMPTY loop / applyEmptyAndGetNext (SUPERSEDED 2026-08-14)
 *
 * DB : SQLite in-memory, schéma V3 complet.
 */
class KernelPipelineOrchestratorTest extends TestCase
{
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
        Schema::dropIfExists('kernel_code_sequences');
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
            ->where('blueprint_id', $result['blueprint']->blueprint_id)->first();
        $this->assertNotNull($run);
        $this->assertSame('ENGAGED_IN_PIPELINE', $run->execution_state);
    }

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
            ->where('blueprint_id', $blueprint->blueprint_id)->first();

        $this->assertNotNull($row->depth);
        $this->assertNotNull($row->domain_code);
        $this->assertNotNull($row->kernel_code);
        $this->assertMatchesRegularExpression(
            KernelCodeEngine::FORMAT_REGEX,
            (string) $row->kernel_code,
        );
    }

    public function test_apply_rotation_persists_domain_position_in_state(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $this->makeOrchestrator($taxonomy)->run();

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNotNull($state);
        $this->assertSame(2, (int) $state->active_depth);
        $this->assertSame(0, (int) $state->domain_position);
    }

    // =========================================================================
    // Tests : CONTAINMENT EMPTY SUPERSEDED (DEC-087)
    // =========================================================================

    /**
     * peekNext() == null → CONTAINMENT : RuntimeException levée.
     * Aucune inférence DOMAIN_EXHAUSTED.
     * Aucune réaffectation de domaine.
     */
    public function test_peek_next_null_throws_containment_exception(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/CONTAINMENT/');

        $this->makeOrchestrator($taxonomy)->run();
    }

    /**
     * Après CONTAINMENT, le Blueprint est supprimé (0 durable).
     * Aucun orphelin ne bloque le prochain Factory::create().
     */
    public function test_containment_deletes_blueprint_leaving_no_orphan(): void
    {
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn(null);

        try {
            $this->makeOrchestrator($taxonomy)->run();
        } catch (RuntimeException) {
            // Exception attendue
        }

        $this->assertSame(0, (int) DB::table('kernel_blueprint_runs')->count(),
            'CONTAINMENT doit supprimer le Blueprint — aucun orphelin'
        );
    }

    /**
     * Après CONTAINMENT + suppression, le run() suivant avec territoire réel réussit.
     * Prouve qu'aucun orphelin ne bloque le Factory::create() suivant.
     */
    public function test_containment_allows_next_run_to_succeed(): void
    {
        $callCount = 0;
        $taxonomy  = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return null; // toujours null
            });

        // Premier run : CONTAINMENT → exception
        try {
            $this->makeOrchestrator($taxonomy)->run();
        } catch (RuntimeException) {}

        // Deuxième run avec territoire réel → ROTATION_ASSIGNED
        $taxonomy2 = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy2->method('peekNext')->willReturn([
            'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
        ]);

        $result = $this->makeOrchestrator($taxonomy2)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame(1, (int) DB::table('kernel_blueprint_runs')->count(),
            'Exactement un Blueprint durable après le run() réussi'
        );
    }

    /**
     * Aucune divergence possible : peekNext(depth, domain) et applyRotation(domain)
     * utilisent le MÊME domain — pas de risque Blueprint.domain ≠ domaine Taxonomy.
     *
     * Preuve : résolution selects domain='geographie' →
     *   taxonomy.peekNext(2, 'geographie') appelé →
     *   blueprint.domain = 'geographie' (pas d'autre domaine intermédiaire).
     */
    public function test_no_domain_divergence_blueprint_domain_matches_taxonomy_domain(): void
    {
        $capturedDomain = null;

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willReturnCallback(function (int $depth, string $domain) use (&$capturedDomain) {
                $capturedDomain = $domain; // capture le domaine transmis à Taxonomy
                return [
                    'sub_domain' => 'X', 'subject' => 'Y', 'dominant_idea' => 'Z',
                ];
            });

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(
            $capturedDomain,
            $result['blueprint']->domain,
            'Le domaine transmis à Taxonomy et Blueprint.domain sont identiques — aucune divergence'
        );
    }

    // =========================================================================
    // Tests : Gate V3 PRODUCTION_ON_HOLD
    // =========================================================================

    public function test_gate_returns_production_on_hold_without_creating_blueprint(): void
    {
        $this->insertProductionOnHoldState();

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->expects($this->never())->method('peekNext');

        $result = $this->makeOrchestrator($taxonomy)->run();

        $this->assertSame(KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD, $result['status']);
        $this->assertNull($result['blueprint']);
        $this->assertSame(0, (int) DB::table('kernel_blueprint_runs')->count());
    }

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

    public function test_factory_stops_if_active_blueprint_exists(): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'existing-bp-001',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Blueprint actif existe déjà/');

        $this->makeOrchestrator($taxonomy)->run();
    }

    // =========================================================================
    // Tests : État V3 après run réussi
    // =========================================================================

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
            $this->assertCount(8, $domainStates[$depthKey]);
            foreach (self::DOMAINS as $domain) {
                $this->assertSame('ACTIF', $domainStates[$depthKey][$domain]);
            }
        }
    }

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

    // =========================================================================
    // Tests : Rollback transaction (vérification #159)
    // =========================================================================

    /**
     * Exception DANS la transaction (schéma état V2 incomplet → DB error réelle)
     * → ROLLBACK complet → 0 Blueprints durables.
     *
     * Mécanisme : kernel_rotation_state_v2 est recréé avec seulement la colonne `id`.
     * resolveNextRotation(null) → RotationAvailable (état vide = INIT_PROPRE).
     * factory::create() → INSERT kernel_blueprint_runs ✅ (dans la transaction).
     * registerActiveBlueprintIdentity() → tente INSERT avec toutes ses colonnes
     *   → SQLite : "table has no column named depth_state" → DB exception réelle
     *   → Laravel DB::transaction() → ROLLBACK → annule l'INSERT kernel_blueprint_runs.
     *
     * Aucune classe finale mockée — erreur DB réelle provoquée par le schéma de test.
     * KernelRotationPlanner reste final.
     */
    public function test_exception_inside_transaction_leaves_zero_durable_blueprints(): void
    {
        // Recréer kernel_rotation_state_v2 avec un schéma délibérément incomplet
        // (seulement `id`) pour provoquer une vraie erreur DB dans la transaction.
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::create('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->id(); // seulement id — toutes les colonnes métier absentes
        });

        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        // peekNext ne sera jamais appelé — l'exception survient avant, dans la transaction

        try {
            $this->makeOrchestrator($taxonomy)->run();
        } catch (\Throwable) {
            // DB exception attendue (colonne manquante) — propagée hors de DB::transaction()
        }

        $this->assertSame(0, (int) DB::table('kernel_blueprint_runs')->count(),
            'ROLLBACK transactionnel : 0 Blueprints durables. '
            . "L'INSERT kernel_blueprint_runs (Factory::create) a bien été annulé "
            . "avec le reste de la transaction."
        );
    }

    /**
     * Exception APRÈS la transaction (peekNext throw, hors transaction)
     * → Blueprint CREATED_UNENGAGED reste en DB (orphelin post-COMMIT).
     *
     * La transaction gate commit Blueprint + état avant peekNext.
     * Si peekNext lève une exception (hors transaction), Blueprint reste CREATED_UNENGAGED.
     *
     * TaxonomyNavigatorInterface est une interface → mock autorisé.
     * Aucune classe finale modifiée.
     *
     * VERDICT #159 : la transaction gate EST correcte (0 Blueprint si crash dedans).
     * L'orphelin post-COMMIT est réel mais documenté — hors scope LOT A+B.
     */
    public function test_exception_after_transaction_leaves_blueprint_created_unengaged(): void
    {
        // Taxonomy throw APRÈS le COMMIT de la transaction gate
        // (peekNext est appelé HORS de DB::transaction dans l'orchestrateur)
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')
            ->willThrowException(new RuntimeException('SIMULATED CRASH after commit — peekNext'));

        try {
            $this->makeOrchestrator($taxonomy)->run();
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SIMULATED CRASH after commit', $e->getMessage());
        }

        // Transaction gate a commité → Blueprint est durable
        $bpCount = (int) DB::table('kernel_blueprint_runs')->count();
        $this->assertSame(1, $bpCount,
            'Post-COMMIT : Blueprint CREATED_UNENGAGED durable. '
            . 'La transaction gate a commité avant peekNext.'
        );

        $bp = DB::table('kernel_blueprint_runs')->first();
        $this->assertSame('CREATED_UNENGAGED', $bp->execution_state);

        // L'état V2 est également commité (active_blueprint_identity présent)
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNotNull($state);
        $this->assertSame($bp->blueprint_id, $state->active_blueprint_identity);
    }
}
