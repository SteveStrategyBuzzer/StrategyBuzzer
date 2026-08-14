<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\RotationResolution;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests de KernelRotationPlanner V3 (02_KernelRotationPlanner v3.2 — VERROUILLÉ 2026-08-13).
 *
 * Couvre :
 *   - resolveNextRotation (gate + sélection initiale)
 *   - selectNextDomain (DomainCycle, DOMAIN_EXHAUSTED, wrapping, INCOHÉRENCE_ÉTAT)
 *   - applyRotation (write-once, persistance)
 *   - receiveDomainExhausted (idempotence, intégration avec resolveNextRotation)
 *   - receiveDepthExhausted (idempotence, invariants)
 *   - receiveKernelReceivedV2 (ordre DEC-093, transition pending)
 *
 * DB : SQLite in-memory avec schéma V3 (toutes les nouvelles colonnes).
 */
class KernelRotationPlannerV3Test extends TestCase
{
    private const DOMAINS = [
        'geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science',
    ];

    private KernelRotationPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->seedDepthMatrix();
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
    // Groupe A — resolveNextRotation (gate + initialisation)
    // =========================================================================

    /**
     * T-01 : INIT_PROPRE (pas de ligne) → RotationAvailable{depth=2, domain='geographie', position=0}.
     *
     * domain_position = null → premier domaine = geographie (index 0).
     */
    public function test_resolve_returns_geographie_at_depth_2_on_first_call(): void
    {
        $resolution = $this->planner->resolveNextRotation(null);

        $this->assertTrue($resolution->isAvailable());
        $this->assertSame(2, $resolution->depth);
        $this->assertSame('geographie', $resolution->domain);
        $this->assertSame(0, $resolution->domainPosition);
    }

    /**
     * T-02 : État depth_state = PRODUCTION_ON_HOLD → NoRotation(PRODUCTION_ON_HOLD).
     */
    public function test_resolve_returns_no_rotation_when_depth_state_is_production_on_hold(): void
    {
        $state = $this->insertState(['depth_state' => 'PRODUCTION_ON_HOLD']);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertTrue($resolution->isNoRotation());
        $this->assertSame('PRODUCTION_ON_HOLD', $resolution->noRotationReason());
    }

    /**
     * T-03 : État depth_state = ROTATION_ACTIVE → RotationAvailable.
     */
    public function test_resolve_returns_available_when_depth_state_is_rotation_active(): void
    {
        $state = $this->insertState([
            'active_depth'  => 4,
            'depth_state'   => 'ROTATION_ACTIVE',
            'domain_states' => json_encode($this->buildDomainStates()),
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertTrue($resolution->isAvailable());
        $this->assertSame(4, $resolution->depth);
    }

    // =========================================================================
    // Groupe B — selectNextDomain (DomainCycle, skip, wrap)
    // =========================================================================

    /**
     * T-04 : domain_position = null → geographie (index 0).
     */
    public function test_resolve_selects_geographie_when_position_is_null(): void
    {
        $state = $this->insertState([
            'active_depth'   => 2,
            'domain_position' => null,
            'domain_states'  => json_encode($this->buildDomainStates()),
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertSame('geographie', $resolution->domain);
        $this->assertSame(0, $resolution->domainPosition);
    }

    /**
     * T-05 : domain_position = 0 (geographie) → histoire (index 1).
     */
    public function test_resolve_advances_to_histoire_when_position_is_0(): void
    {
        $state = $this->insertState([
            'active_depth'    => 2,
            'domain_position' => 0,
            'domain_states'   => json_encode($this->buildDomainStates()),
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertSame('histoire', $resolution->domain);
        $this->assertSame(1, $resolution->domainPosition);
    }

    /**
     * T-06 : domain_position = 7 (science) → wrap → geographie (index 0).
     */
    public function test_resolve_wraps_from_science_to_geographie(): void
    {
        $state = $this->insertState([
            'active_depth'    => 2,
            'domain_position' => 7, // science
            'domain_states'   => json_encode($this->buildDomainStates()),
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertSame('geographie', $resolution->domain);
        $this->assertSame(0, $resolution->domainPosition);
    }

    /**
     * T-07 : geographie DOMAIN_EXHAUSTED → saut vers histoire.
     */
    public function test_resolve_skips_domain_exhausted_domain(): void
    {
        $domainStates = $this->buildDomainStates();
        $domainStates['2']['geographie'] = 'DOMAIN_EXHAUSTED';

        $state = $this->insertState([
            'active_depth'    => 2,
            'domain_position' => null,
            'domain_states'   => json_encode($domainStates),
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertSame('histoire', $resolution->domain, 'geographie exhausted → histoire');
        $this->assertSame(1, $resolution->domainPosition);
    }

    /**
     * T-08 : Tous les 8 domaines DOMAIN_EXHAUSTED + pending ≠ null → NoRotation(PENDING_DEPTH_TRANSITION).
     */
    public function test_resolve_returns_pending_depth_transition_when_all_domains_exhausted_with_pending(): void
    {
        $domainStates = $this->buildDomainStates();
        foreach (DepthTourState::DOMAIN_CYCLE as $d) {
            $domainStates['2'][$d] = 'DOMAIN_EXHAUSTED';
        }

        $state = $this->insertState([
            'active_depth'                  => 2,
            'domain_states'                 => json_encode($domainStates),
            'pending_depth_exhausted_depth' => 2,
        ]);

        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertTrue($resolution->isNoRotation());
        $this->assertSame('PENDING_DEPTH_TRANSITION', $resolution->noRotationReason());
    }

    /**
     * T-09 : Tous les 8 domaines DOMAIN_EXHAUSTED + pending = null → RuntimeException (INCOHÉRENCE_ÉTAT).
     */
    public function test_resolve_throws_incoherence_when_all_domains_exhausted_without_pending(): void
    {
        $domainStates = $this->buildDomainStates();
        foreach (DepthTourState::DOMAIN_CYCLE as $d) {
            $domainStates['2'][$d] = 'DOMAIN_EXHAUSTED';
        }

        $state = $this->insertState([
            'active_depth'                  => 2,
            'domain_states'                 => json_encode($domainStates),
            'pending_depth_exhausted_depth' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/INCOHÉRENCE_ÉTAT/');

        $this->planner->resolveNextRotation($state);
    }

    // =========================================================================
    // Groupe C — applyRotation (write-once, persistance)
    // =========================================================================

    /**
     * T-10 : applyRotation écrit depth + domain dans le Blueprint (fillRotation).
     */
    public function test_apply_rotation_fills_blueprint_depth_and_domain(): void
    {
        $this->insertState(['active_depth' => 2]);
        $blueprint = $this->makeBlueprint();

        $this->planner->applyRotation($blueprint, 2, 'histoire', 1);

        $this->assertSame(2, $blueprint->depth);
        $this->assertSame('histoire', $blueprint->domain);
    }

    /**
     * T-11 : applyRotation persiste active_depth + domain_position dans l'état V2.
     */
    public function test_apply_rotation_updates_state_row_with_depth_and_position(): void
    {
        $this->insertState(['active_depth' => null]);
        $blueprint = $this->makeBlueprint();

        $this->planner->applyRotation($blueprint, 6, 'faune', 2);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(6, (int) $state->active_depth);
        $this->assertSame(2, (int) $state->domain_position);
    }

    /**
     * T-12 : applyRotation → fillRotation est write-once → second appel lève LogicException.
     */
    public function test_apply_rotation_is_write_once_via_fill_rotation(): void
    {
        $this->insertState(['active_depth' => 2]);
        $blueprint = $this->makeBlueprint();

        $this->planner->applyRotation($blueprint, 2, 'geographie', 0);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/write-once/');

        $this->planner->applyRotation($blueprint, 2, 'histoire', 1);
    }

    // =========================================================================
    // Groupe D — receiveDomainExhausted
    // =========================================================================

    /**
     * T-13 : receiveDomainExhausted persiste DOMAIN_EXHAUSTED dans domain_states.
     */
    public function test_receive_domain_exhausted_marks_domain_in_state(): void
    {
        $this->insertState([
            'active_depth'  => 2,
            'domain_states' => json_encode($this->buildDomainStates()),
        ]);

        $this->planner->receiveDomainExhausted(2, 'geographie');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $ds    = json_decode($state->domain_states, true);
        $this->assertSame('DOMAIN_EXHAUSTED', $ds['2']['geographie']);
        $this->assertSame('ACTIF', $ds['2']['histoire'], 'Autres domaines inchangés');
    }

    /**
     * T-14 : receiveDomainExhausted est idempotent — second appel = NO-OP.
     */
    public function test_receive_domain_exhausted_is_idempotent(): void
    {
        $this->insertState([
            'active_depth'  => 2,
            'domain_states' => json_encode($this->buildDomainStates()),
        ]);

        $this->planner->receiveDomainExhausted(2, 'histoire');
        $lockVersionAfterFirst = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');

        $this->planner->receiveDomainExhausted(2, 'histoire');
        $lockVersionAfterSecond = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');

        $this->assertSame(
            $lockVersionAfterFirst,
            $lockVersionAfterSecond,
            'lock_version ne doit pas changer sur un appel idempotent'
        );
    }

    /**
     * T-15 : receiveDomainExhausted lève RuntimeException pour un domaine inconnu.
     */
    public function test_receive_domain_exhausted_throws_on_unknown_domain(): void
    {
        $this->insertState(['active_depth' => 2]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/domaine inconnu/');

        $this->planner->receiveDomainExhausted(2, 'musique'); // inconnu
    }

    /**
     * T-16 : Après receiveDomainExhausted, resolveNextRotation saute le domaine exhausted.
     */
    public function test_resolve_skips_domain_marked_exhausted_by_receive(): void
    {
        $this->insertState([
            'active_depth'    => 2,
            'domain_position' => null,
            'domain_states'   => json_encode($this->buildDomainStates()),
        ]);

        $this->planner->receiveDomainExhausted(2, 'geographie');

        $state      = DB::table('kernel_rotation_state_v2')->first();
        $resolution = $this->planner->resolveNextRotation($state);

        $this->assertSame('histoire', $resolution->domain,
            'geographie marqué DOMAIN_EXHAUSTED → doit sauter vers histoire'
        );
    }

    // =========================================================================
    // Groupe E — receiveDepthExhausted
    // =========================================================================

    /**
     * T-17 : receiveDepthExhausted persiste pending_depth_exhausted_depth.
     */
    public function test_receive_depth_exhausted_sets_pending_field(): void
    {
        $this->insertState(['active_depth' => 2]);

        $this->planner->receiveDepthExhausted(2);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(2, (int) $state->pending_depth_exhausted_depth);
    }

    /**
     * T-18 : receiveDepthExhausted est idempotent — même Depth deux fois = NO-OP.
     */
    public function test_receive_depth_exhausted_is_idempotent_for_same_depth(): void
    {
        $this->insertState(['active_depth' => 2]);

        $this->planner->receiveDepthExhausted(2);
        $lockAfterFirst = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');

        $this->planner->receiveDepthExhausted(2);
        $lockAfterSecond = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');

        $this->assertSame($lockAfterFirst, $lockAfterSecond, 'Idempotent — lock_version stable');
    }

    /**
     * T-19 : receiveDepthExhausted pour un Depth différent du pending existant → ERREUR D'INVARIANT.
     */
    public function test_receive_depth_exhausted_throws_invariant_error_when_different_pending_exists(): void
    {
        $this->insertState([
            'active_depth'                  => 2,
            'pending_depth_exhausted_depth' => 2,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/ERREUR D'INVARIANT/");

        $this->planner->receiveDepthExhausted(4); // Depth différent du pending=2
    }

    /**
     * T-20 : receiveDepthExhausted pour Depth ≠ active_depth → INCOHÉRENCE_ÉTAT.
     */
    public function test_receive_depth_exhausted_throws_incoherence_when_depth_differs_from_active(): void
    {
        $this->insertState(['active_depth' => 2]); // active=2, reçoit signal pour 4

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/INCOHÉRENCE_ÉTAT/');

        $this->planner->receiveDepthExhausted(4);
    }

    // =========================================================================
    // Groupe F — receiveKernelReceivedV2
    // =========================================================================

    /**
     * T-21 : receiveKernelReceivedV2 insère dans la table des reçus.
     */
    public function test_receive_kernel_inserts_into_receipts_table(): void
    {
        $this->insertState(['active_depth' => 2]);
        $this->seedDepthDomainTotals();

        $this->planner->receiveKernelReceivedV2('bp-001', 2, 'geographie');

        $this->assertSame(1, (int) DB::table('kernel_current_kernel_receipts')->count());
        $row = DB::table('kernel_current_kernel_receipts')->first();
        $this->assertSame('bp-001', $row->blueprint_id);
        $this->assertSame(2, (int) $row->depth);
        $this->assertSame('geographie', $row->domain_code);
    }

    /**
     * T-22 : receiveKernelReceivedV2 est idempotent — second appel = NO-OP (pas de doublon).
     */
    public function test_receive_kernel_is_idempotent(): void
    {
        $this->insertState(['active_depth' => 2]);
        $this->seedDepthDomainTotals();

        $this->planner->receiveKernelReceivedV2('bp-001', 2, 'geographie');
        $this->planner->receiveKernelReceivedV2('bp-001', 2, 'geographie');

        $this->assertSame(1, (int) DB::table('kernel_current_kernel_receipts')->count(),
            'Un seul reçu même après deux appels'
        );

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)->where('domain_code', 'geographie')
            ->value('kernel_received_total');
        $this->assertSame(1, (int) $total, 'Compteur incrémenté UNE seule fois (idempotence)');
    }

    /**
     * T-23 : receiveKernelReceivedV2 — compteur incrémenté AVANT vérification transition (DEC-093).
     *
     * Preuve : même si pending est non-null, le compteur kernel_received_total est bien incrémenté.
     */
    public function test_receive_kernel_increments_counter_before_transition_check(): void
    {
        $domainStates = $this->buildDomainStates();
        foreach (DepthTourState::DOMAIN_CYCLE as $d) {
            $domainStates['2'][$d] = 'DOMAIN_EXHAUSTED';
        }

        $this->insertState([
            'active_depth'                  => 2,
            'domain_states'                 => json_encode($domainStates),
            'pending_depth_exhausted_depth' => 2,
        ]);
        $this->seedDepthDomainTotals();

        $this->planner->receiveKernelReceivedV2('bp-xyz', 2, 'geographie');

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)->where('domain_code', 'geographie')
            ->value('kernel_received_total');

        $this->assertSame(1, (int) $total,
            'Compteur incrémenté MÊME quand une transition est en cours — DEC-093'
        );
    }

    /**
     * T-24 : receiveKernelReceivedV2 — transition Depth appliquée si pending === depth reçu.
     *
     * Après transition : pending = null, active_depth = prochain Depth, domain_position = null.
     */
    public function test_receive_kernel_applies_depth_transition_when_pending_matches(): void
    {
        $domainStates = $this->buildDomainStates();

        $this->insertState([
            'active_depth'                  => 2,
            'domain_states'                 => json_encode($domainStates),
            'pending_depth_exhausted_depth' => 2,
            'domain_position'               => 5,
        ]);
        $this->seedDepthDomainTotals();

        $this->planner->receiveKernelReceivedV2('bp-trans', 2, 'geographie');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNull($state->pending_depth_exhausted_depth, 'pending réinitialisé après transition');
        $this->assertNull($state->domain_position, 'domain_position réinitialisé après transition');
        // active_depth doit avoir avancé (2 → 4 car cycle_target[2] > cycle_completed)
        // OU depth_state = PRODUCTION_ON_HOLD si plus de Depth disponible
        // Dans ce test, cycle_target[2]=1000 > cycle_completed=1 → active_depth = 4
        $this->assertSame('ROTATION_ACTIVE', $state->depth_state);
        $this->assertSame(4, (int) $state->active_depth,
            'Transition Depth 2 → 4 (prochain dans DEPTH_CYCLE)'
        );
    }

    /**
     * T-25 : receiveKernelReceivedV2 met à jour last_counted_blueprint_identity.
     */
    public function test_receive_kernel_updates_last_counted_blueprint_identity(): void
    {
        $this->insertState(['active_depth' => 2]);
        $this->seedDepthDomainTotals();

        $this->planner->receiveKernelReceivedV2('bp-last', 2, 'histoire');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame('bp-last', $state->last_counted_blueprint_identity);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeBlueprint(): KernelBlueprint
    {
        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId((string) \Illuminate\Support\Str::uuid());
        return $bp;
    }

    private function insertState(array $overrides = []): object
    {
        $defaults = [
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => null,
            'domain_position'                => null,
            'domain_states'                  => null,
            'pending_depth_exhausted_depth'  => null,
            'tour_domain_states'             => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'      => null,
            'last_counted_blueprint_identity' => null,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ];

        DB::table('kernel_rotation_state_v2')->insert(array_merge($defaults, $overrides));

        return DB::table('kernel_rotation_state_v2')->first();
    }

    /**
     * Construit domain_states avec toutes les entrées ACTIF.
     *
     * @return array<string, array<string, string>>
     */
    private function buildDomainStates(): array
    {
        $states = [];
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $key          = (string) $depth;
            $states[$key] = [];
            foreach (self::DOMAINS as $domain) {
                $states[$key][$domain] = 'ACTIF';
            }
        }
        return $states;
    }

    private function seedDepthMatrix(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->insert([
                'depth'           => $depth,
                'cycle_target'    => DepthNeedMatrix::CYCLE_TARGET[$depth],
                'cycle_completed' => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    private function seedDepthDomainTotals(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            foreach (self::DOMAINS as $domain) {
                if (!DB::table('kernel_depth_domain_totals')
                    ->where('depth', $depth)->where('domain_code', $domain)->exists()) {
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
    }
}
