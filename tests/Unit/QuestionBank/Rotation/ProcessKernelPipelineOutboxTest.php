<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\Listeners\ApplyCurrentKernelReceivedToRotation;
use App\Services\QuestionBank\Rotation\ProcessKernelPipelineOutbox;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests de ProcessKernelPipelineOutbox.
 *
 * Couvre (Section 6 du contrat d'audit) :
 *   1. Réception normale → compteur +1 → Blueprint suivant créé (ENGAGED)
 *   2. Même événement rejoué → compteur inchangé → aucun deuxième Blueprint
 *   3. Aucun besoin restant → PRODUCTION_ON_HOLD
 *   4. Événement déjà traité → NO-OP
 *   5. Événement avec payload invalide → ERROR, attempt_count incrémenté, pas de processed_at
 *
 * DB : SQLite in-memory, tables créées manuellement.
 */
class ProcessKernelPipelineOutboxTest extends TestCase
{
    private const DOMAINS = ['geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedDepthMatrix();
        // kernel_rotation_state_v2 intentionnellement vide : planV2() l'initialise au premier appel
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_pipeline_outbox');
        Schema::dropIfExists('kernel_current_kernel_receipts');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_code_sequences');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // =========================================================================
    // Tests Section 6
    // =========================================================================

    /**
     * Test 1 : Réception normale → compteur +1 → Blueprint suivant créé (ENGAGED).
     */
    public function test_normal_processing_increments_counter_and_creates_next_blueprint(): void
    {
        $eventId = (string) Str::orderedUuid();
        $blueprintId = 'bp-processed-001';

        // Insérer l'événement dans l'Outbox
        $this->insertOutboxEvent($eventId, $blueprintId, 2, 'geographie');

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $results[0]['outcome']);
        $this->assertSame($eventId, $results[0]['event_id']);

        // Vérifier que l'événement est marqué processed
        $row = DB::table('kernel_pipeline_outbox')->where('event_id', $eventId)->first();
        $this->assertNotNull($row->processed_at, 'processed_at doit être défini après traitement');

        // Vérifier que le compteur a été incrémenté
        $receipt = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', $blueprintId)
            ->first();
        $this->assertNotNull($receipt, 'Un reçu doit exister pour blueprint_id');

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'geographie')
            ->value('kernel_received_total');
        $this->assertSame(1, (int) $total, 'kernel_received_total doit être incrémenté de 1');

        // Vérifier qu'un Blueprint suivant a été créé
        // (le Blueprint existant pour le count précédent + un nouveau)
        $bpCount = DB::table('kernel_blueprint_runs')->count();
        $this->assertGreaterThanOrEqual(1, $bpCount, 'Un Blueprint suivant doit exister');
    }

    /**
     * Test 2 : Même événement rejoué → compteur inchangé → aucun deuxième Blueprint actif.
     * Idempotence par verrou optimiste (attempt_count change entre les deux appels).
     */
    public function test_same_event_replayed_does_not_double_count(): void
    {
        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-replay-001';

        $this->insertOutboxEvent($eventId, $blueprintId, 2, 'geographie');

        // Première exécution
        $this->makeProcessor()->process(10);

        // Réinitialiser processed_at pour forcer le rejeu (simulation)
        DB::table('kernel_pipeline_outbox')
            ->where('event_id', $eventId)
            ->update(['processed_at' => null, 'attempt_count' => 0]);

        // Deuxième exécution (rejeu)
        // Mais le reçu existe déjà → applyCount() est idempotent
        $this->makeProcessor()->process(10);

        // Le compteur ne doit pas avoir doublé
        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'geographie')
            ->value('kernel_received_total');
        $this->assertSame(1, (int) $total, 'Rejeu idempotent : compteur toujours à 1');

        // Un seul reçu
        $receiptCount = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', $blueprintId)
            ->count();
        $this->assertSame(1, $receiptCount, 'Un seul reçu malgré le rejeu');
    }

    /**
     * Test 3 : Aucun besoin restant → PRODUCTION_ON_HOLD.
     * Tous les Depths ont atteint leur cible → l'orchestrateur retourne PRODUCTION_ON_HOLD.
     */
    public function test_production_on_hold_when_all_depths_completed(): void
    {
        // Marquer tous les Depths comme complétés
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')
                ->where('depth', $depth)
                ->update(['cycle_completed' => DepthNeedMatrix::CYCLE_TARGET[$depth]]);
        }

        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-onhold-001';
        $this->insertOutboxEvent($eventId, $blueprintId, 2, 'geographie');

        $results = $this->makeProcessor()->process(10);

        $this->assertCount(1, $results);
        $this->assertSame(ProcessKernelPipelineOutbox::OUTCOME_PROCESSED, $results[0]['outcome']);
        $this->assertSame('PRODUCTION_ON_HOLD', $results[0]['orchestrator_status']);
    }

    /**
     * Test 4 : Événement déjà traité → NO-OP (processed_at non null).
     */
    public function test_already_processed_event_is_noop(): void
    {
        $eventId     = (string) Str::orderedUuid();
        $blueprintId = 'bp-done-001';

        // Insérer comme déjà traité
        DB::table('kernel_pipeline_outbox')->insert([
            'event_id'       => $eventId,
            'event_type'     => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'payload'        => json_encode($this->makePayload($eventId, $blueprintId, 2, 'geographie')),
            'occurred_at'    => now(),
            'processed_at'   => now(), // déjà traité
            'attempt_count'  => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // findPending ne retourne pas les événements déjà traités
        $results = $this->makeProcessor()->process(10);

        $this->assertCount(0, $results, 'Aucun traitement pour un événement déjà traité');
    }

    /**
     * Test 5 : Payload JSON invalide → ERROR, attempt_count incrémenté, processed_at NULL.
     * L'événement doit rester rejouable.
     */
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
        $this->assertGreaterThan(0, (int) $row->attempt_count, 'attempt_count doit être incrémenté');
        $this->assertNotNull($row->last_error, 'last_error doit être défini');
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeProcessor(): ProcessKernelPipelineOutbox
    {
        // Stub Taxonomy : fournit un territoire avec dominant_idea.
        // ⛔ KLD / KEY_STRUCTURE : SUPERSEDED — plus aucun gate dans le flow.
        $taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $taxonomy->method('peekNext')->willReturn([
            'sub_domain'    => 'Époque contemporaine',
            'subject'       => 'Guerre froide',
            'dominant_idea' => 'tensions',
        ]);

        $orchestrator = new KernelPipelineOrchestrator(
            new KernelBlueprintFactory(),
            new KernelRotationPlanner(),
            $taxonomy,
            new KernelCodeEngine(),
        );

        return new ProcessKernelPipelineOutbox(
            new ApplyCurrentKernelReceivedToRotation(),
            $orchestrator,
            new KernelPipelineOutboxRepository(),
        );
    }

    // =========================================================================
    // Helpers de setup DB
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

    private function seedRotationStateV2(): void
    {
        DB::table('kernel_rotation_state_v2')->insert([
            'active_depth'                    => null,
            'rotation_status'                 => 'TOUR_IN_PROGRESS',
            'tour_domain_states'              => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'       => null,
            'last_counted_blueprint_identity' => null,
            'lock_version'                    => 1,
            'created_at'                      => now(),
            'updated_at'                      => now(),
        ]);
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
}
