<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\ProcessKernelPipelineOutbox;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests de ProcessKernelPipelineOutbox V3.
 *
 * Couvre :
 *   1. Réception normale → compteur +1 → Blueprint suivant créé (ENGAGED)
 *   2. Même événement rejoué → compteur inchangé → aucun deuxième Blueprint
 *   3. État depth_state = PRODUCTION_ON_HOLD → gate V3 → PRODUCTION_ON_HOLD
 *   4. Événement déjà traité → NO-OP
 *   5. Payload JSON invalide → ERROR, attempt_count++, pas de processed_at
 *   6. (#157) pending_depth_exhausted_depth = 4 + CKR depth=4 → transition vers depth=6
 *   7. (#157) pending = 10 + CKR → PRODUCTION_ON_HOLD (dernier Depth)
 *
 * Implémentation canonique CKR (V3) :
 *   ProcessKernelPipelineOutbox → KernelRotationPlanner::receiveKernelReceivedV2
 *   (ApplyCurrentKernelReceivedToRotation::applyCount() DÉSACTIVÉE — DEC-093)
 *
 * DB : SQLite in-memory, schéma V3 complet.
 */
class ProcessKernelPipelineOutboxTest extends TestCase
{
    private const DOMAINS = [
        'geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedDepthMatrix();
        // kernel_rotation_state_v2 intentionnellement vide — initialisé par le premier run()
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_current_kernel_receipts');
        Schema::dropIfExists('kernel_pipeline_outbox');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_code_sequences');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // =========================================================================
    // Test 1 — Réception normale
    // =========================================================================

    public function test_normal_processing_increments_counter_and_creates_next_blueprint(): void
    {
        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-processed-001';

        $this->insertOutboxEvent($eventId, $blueprintId, 2, 'geographie');

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $results[0]['outcome']);
        $this->assertSame($eventId, $results[0]['event_id']);

        // Événement marqué processed
        $row = DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->first();
        $this->assertNotNull($row->processed_at);

        // Reçu inséré
        $receipt = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', $blueprintId)->first();
        $this->assertNotNull($receipt, 'Un reçu doit exister pour blueprint_id');

        // Compteur incrémenté
        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)->where('domain_code', 'geographie')
            ->value('kernel_received_total');
        $this->assertSame(1, (int) $total);

        // Blueprint suivant créé
        $bpCount = DB::table('kernel_blueprint_runs')->count();
        $this->assertGreaterThanOrEqual(1, $bpCount);
    }

    // =========================================================================
    // Test 2 — Rejeu idempotent
    // =========================================================================

    public function test_same_event_replayed_does_not_double_count(): void
    {
        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-replay-001';

        $this->insertOutboxEvent($eventId, $blueprintId, 2, 'geographie');

        $this->makeProcessor()->process(10);

        // Simuler un rejeu : effacer processed_at
        DB::table('kernel_pipeline_outbox')
            ->where('event_id', $eventId)
            ->update(['processed_at' => null, 'attempt_count' => 0]);

        $this->makeProcessor()->process(10);

        // Compteur ne doit pas avoir doublé
        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)->where('domain_code', 'geographie')
            ->value('kernel_received_total');
        $this->assertSame(1, (int) $total, 'Rejeu idempotent : compteur toujours à 1');

        $receiptCount = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', $blueprintId)->count();
        $this->assertSame(1, $receiptCount, 'Un seul reçu malgré le rejeu');
    }

    // =========================================================================
    // Test 3 — PRODUCTION_ON_HOLD via gate V3
    // =========================================================================

    public function test_production_on_hold_when_gate_state_is_on_hold(): void
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

        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-onhold-001';
        $this->insertOutboxEvent($eventId, $blueprintId, 2, 'geographie');

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $results[0]['outcome']);
        $this->assertSame('PRODUCTION_ON_HOLD', $results[0]['orchestrator_status']);
    }

    // =========================================================================
    // Test 4 — Événement déjà traité
    // =========================================================================

    public function test_already_processed_event_is_noop(): void
    {
        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-done-001';

        DB::table('kernel_pipeline_outbox')->insert([
            'event_id'       => $eventId,
            'event_type'     => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'payload'        => json_encode($this->makePayload($eventId, $blueprintId, 2, 'geographie')),
            'occurred_at'    => now(),
            'processed_at'   => now(),
            'attempt_count'  => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(0, $results, 'Aucun traitement pour un événement déjà traité');
    }

    // =========================================================================
    // Test 5 — Payload invalide
    // =========================================================================

    public function test_invalid_payload_causes_error_and_preserves_event_for_replay(): void
    {
        $eventId = (string) Str::orderedUuid();

        DB::table('kernel_pipeline_outbox')->insert([
            'event_id'       => $eventId,
            'event_type'     => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'payload'        => '{invalid json',
            'occurred_at'    => now(),
            'processed_at'   => null,
            'attempt_count'  => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_ERROR, $results[0]['outcome']);

        $row = DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->first();
        $this->assertNull($row->processed_at, 'processed_at doit rester NULL sur erreur');
        $this->assertGreaterThan(0, (int) $row->attempt_count);
        $this->assertNotNull($row->last_error);
    }

    // =========================================================================
    // Test 6 — #157 : transition Depth pending appliquée (Depth 4 → 6)
    // =========================================================================

    /**
     * pending_depth_exhausted_depth = 4
     * + CURRENT_KERNEL_RECEIVED(blueprint_id, depth=4, domain='geographie')
     * ─────────────────────────────────────────────────────────────────────
     * Attendu :
     *   - receipt exactement une fois
     *   - kernel_received_total +1 exactement une fois
     *   - active_depth = 6
     *   - domain_position = NULL
     *   - pending = NULL
     */
    public function test_pending_depth_4_transitions_to_depth_6_on_receipt(): void
    {
        // État : Depth 4 actif, pending=4 (DEPTH_EXHAUSTED reçu), tous domaines ACTIF
        $domainStates = $this->buildDomainStates();
        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => 4,
            'domain_position'                => 3, // sera réinitialisé
            'domain_states'                  => json_encode($domainStates),
            'pending_depth_exhausted_depth'  => 4, // signal DEPTH_EXHAUSTED mémorisé
            'tour_domain_states'             => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'      => null,
            'last_counted_blueprint_identity' => null,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);

        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-depth4-last';
        $this->insertOutboxEvent($eventId, $blueprintId, 4, 'geographie');

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $results[0]['outcome']);

        // Vérifier le reçu — exactement une fois
        $receiptCount = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', $blueprintId)->count();
        $this->assertSame(1, $receiptCount, 'Reçu exactement une fois');

        // Vérifier le compteur — exactement une fois
        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 4)->where('domain_code', 'geographie')
            ->value('kernel_received_total');
        $this->assertSame(1, (int) $total, 'kernel_received_total +1 exactement une fois');

        // Vérifier la transition Depth
        // domain_position est null APRÈS applyDepthTransition, puis l'orchestrator::run()
        // appelle applyRotation qui le réécrit à 0 (geographie = index 0 du DomainCycle).
        // La valeur importante est : ancienne valeur 3 éliminée + pending = null.
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNull($state->pending_depth_exhausted_depth, 'pending réinitialisé');
        $this->assertNotEquals(3, (int) $state->domain_position,
            'domain_position n\'est plus l\'ancienne valeur (3) — transition réinitialisée'
        );
        $this->assertSame('ROTATION_ACTIVE', $state->depth_state);
        $this->assertSame(6, (int) $state->active_depth, 'Depth avancé de 4 → 6');
    }

    // =========================================================================
    // Test 7 — #157 : pending = 10 → PRODUCTION_ON_HOLD
    // =========================================================================

    /**
     * pending_depth_exhausted_depth = 10 (dernier Depth du cycle)
     * + CURRENT_KERNEL_RECEIVED(blueprint_id, depth=10)
     * ─────────────────────────────────────────────────────────────────────
     * Attendu :
     *   - compteur +1
     *   - transition : DepthCycle figé → Depth 10 = dernier → PRODUCTION_ON_HOLD
     *   - depth_state = PRODUCTION_ON_HOLD
     *   - pending = NULL
     *   - orchestrator::run() retourne PRODUCTION_ON_HOLD
     *
     * SANS SATURATION DE MATRIX :
     *   cycle_target / cycle_completed ne pilotent PAS cette transition.
     *   applyDepthTransition() utilise DEPTH_CYCLE_NEXT[10] = null directement.
     */
    public function test_pending_depth_10_causes_production_on_hold(): void
    {
        $domainStates = $this->buildDomainStates();
        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => 10,
            'domain_position'                => 0,
            'domain_states'                  => json_encode($domainStates),
            'pending_depth_exhausted_depth'  => 10,
            'tour_domain_states'             => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'      => null,
            'last_counted_blueprint_identity' => null,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);

        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-depth10-last';
        $this->insertOutboxEvent($eventId, $blueprintId, 10, 'geographie');

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $results[0]['outcome']);
        $this->assertSame('PRODUCTION_ON_HOLD', $results[0]['orchestrator_status']);

        // Vérifier l'état après transition
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame('PRODUCTION_ON_HOLD', $state->depth_state);
        $this->assertNull($state->pending_depth_exhausted_depth, 'pending réinitialisé');

        // Compteur incrémenté
        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 10)->where('domain_code', 'geographie')
            ->value('kernel_received_total');
        $this->assertSame(1, (int) $total, 'kernel_received_total +1 même pour le dernier Depth');
    }

    // =========================================================================
    // Helpers — factory processeur
    // =========================================================================

    /**
     * Construit le processeur V3 :
     *   ProcessKernelPipelineOutbox
     *     → KernelRotationPlanner (receiveKernelReceivedV2 — DEC-093)
     *     → KernelPipelineOrchestrator
     */
    private function makeProcessor(
        ?TaxonomyNavigatorInterface $taxonomy = null,
    ): ProcessKernelPipelineOutbox {
        if ($taxonomy === null) {
            $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
            $taxonomy->method('peekNext')->willReturn([
                'sub_domain'    => 'Époque contemporaine',
                'subject'       => 'Guerre froide',
                'dominant_idea' => 'tensions',
            ]);
        }

        $planner = new KernelRotationPlanner();

        $orchestrator = new KernelPipelineOrchestrator(
            new KernelBlueprintFactory(),
            $planner,
            $taxonomy,
            new KernelCodeEngine(),
        );

        return new ProcessKernelPipelineOutbox($planner, $orchestrator, new KernelPipelineOutboxRepository());
    }

    // =========================================================================
    // Helpers — schéma DB
    // =========================================================================

    private function createTables(): void
    {
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

        Schema::create('kernel_current_kernel_receipts', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('event_id', 36)->unique();
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->timestamp('received_at');
        });

        Schema::create('kernel_pipeline_outbox', function (Blueprint $table) {
            $table->string('event_id', 36)->primary();
            $table->string('event_type', 128);
            $table->integer('schema_version')->default(1);
            $table->text('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        // Schéma V3 complet
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
    }

    private function seedDepthMatrix(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->insert([
                'depth'                       => $depth,
                'cycle_target'                => DepthNeedMatrix::CYCLE_TARGET[$depth],
                'cycle_completed'             => 0,
                'empty_progress_current_tour' => 0,
                'created_at'                  => now(),
                'updated_at'                  => now(),
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

    private function insertOutboxEvent(
        string $eventId,
        string $blueprintId,
        int    $depth,
        string $domain,
    ): void {
        DB::table('kernel_pipeline_outbox')->insert([
            'event_id'       => $eventId,
            'event_type'     => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'payload'        => json_encode($this->makePayload($eventId, $blueprintId, $depth, $domain)),
            'occurred_at'    => now(),
            'processed_at'   => null,
            'attempt_count'  => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function makePayload(string $eventId, string $blueprintId, int $depth, string $domain): array
    {
        return [
            'event_id'       => $eventId,
            'event_type'     => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'blueprint_id'   => $blueprintId,
            'depth'          => $depth,
            'domain'         => $domain,
            'occurred_at'    => now()->toIso8601String(),
        ];
    }

    /**
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
}
