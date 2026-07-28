<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use App\Services\QuestionBank\Rotation\Listeners\ApplyCurrentKernelReceivedToRotation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests pour ApplyCurrentKernelReceivedToRotation.
 *
 * Couvre : idempotence, incrément, rejeu Outbox.
 *
 * DB : SQLite in-memory (tables créées manuellement).
 */
class ApplyCurrentKernelReceivedToRotationTest extends TestCase
{
    private ApplyCurrentKernelReceivedToRotation $listener;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('kernel_depth_matrix', function (Blueprint $table) {
            $table->smallInteger('depth')->primary();
            $table->integer('cycle_target')->default(0);
            $table->integer('cycle_completed')->default(0);
            $table->smallInteger('empty_progress_current_tour')->default(0);
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
            $table->string('rotation_status', 64)->default('TOUR_IN_PROGRESS');
            $table->text('tour_domain_states')->nullable();
            $table->string('active_blueprint_identity', 36)->nullable();
            $table->string('last_counted_blueprint_identity', 36)->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
        });

        $this->seedDomainTotals();
        $this->seedDepthMatrix();
        $this->seedStateV2();

        $this->listener = new ApplyCurrentKernelReceivedToRotation();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_pipeline_outbox');
        Schema::dropIfExists('kernel_current_kernel_receipts');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        parent::tearDown();
    }

    // =========================================================================
    // Comptabilisation
    // =========================================================================

    public function test_handle_inserts_receipt_and_increments_total(): void
    {
        $event = $this->makeEvent('bp-001', 2, 'geographie');

        $this->listener->handle($event);

        $receipt = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', 'bp-001')
            ->first();

        $this->assertNotNull($receipt);
        $this->assertSame(2, (int) $receipt->depth);
        $this->assertSame('geographie', $receipt->domain_code);

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'geographie')
            ->value('kernel_received_total');

        $this->assertSame(1, (int) $total);
    }

    // =========================================================================
    // Idempotence
    // =========================================================================

    public function test_handle_idempotent_on_duplicate_blueprint_id(): void
    {
        $event = $this->makeEvent('bp-001', 2, 'geographie');

        $this->listener->handle($event);
        $this->listener->handle($event); // doublon

        $receiptCount = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', 'bp-001')
            ->count();

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 2)
            ->where('domain_code', 'geographie')
            ->value('kernel_received_total');

        $this->assertSame(1, $receiptCount, 'Un seul reçu inséré');
        $this->assertSame(1, (int) $total, 'Pas de double incrément');
    }

    // =========================================================================
    // Outbox
    // =========================================================================

    public function test_handle_marks_outbox_event_as_processed(): void
    {
        $eventId = (string) Str::orderedUuid();
        $this->insertOutboxEvent($eventId);

        $event = new CurrentKernelReceived(
            eventId:     $eventId,
            blueprintId: 'bp-002',
            depth:       4,
            domain:      'histoire',
            occurredAt:  now()->toIso8601String(),
        );

        $this->listener->handle($event);

        $outbox = DB::table('kernel_pipeline_outbox')
            ->where('event_id', $eventId)
            ->first();

        $this->assertNotNull($outbox->processed_at, 'processed_at doit être défini');
    }

    public function test_handle_outbox_replay_does_not_double_count(): void
    {
        $eventId = (string) Str::orderedUuid();
        $this->insertOutboxEvent($eventId);

        $event = new CurrentKernelReceived(
            eventId:     $eventId,
            blueprintId: 'bp-003',
            depth:       4,
            domain:      'faune',
            occurredAt:  now()->toIso8601String(),
        );

        $this->listener->handle($event); // première exécution
        $this->listener->handle($event); // rejeu

        $total = DB::table('kernel_depth_domain_totals')
            ->where('depth', 4)
            ->where('domain_code', 'faune')
            ->value('kernel_received_total');

        $this->assertSame(1, (int) $total, 'Rejeu Outbox idempotent');
    }

    // =========================================================================
    // Mise à jour de last_counted_blueprint_identity
    // =========================================================================

    public function test_handle_updates_last_counted_blueprint_identity(): void
    {
        $event = $this->makeEvent('bp-004', 6, 'sport');

        $this->listener->handle($event);

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame('bp-004', $state->last_counted_blueprint_identity);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeEvent(string $blueprintId, int $depth, string $domain): CurrentKernelReceived
    {
        return new CurrentKernelReceived(
            eventId:     (string) Str::orderedUuid(),
            blueprintId: $blueprintId,
            depth:       $depth,
            domain:      $domain,
            occurredAt:  now()->toIso8601String(),
        );
    }

    private function insertOutboxEvent(string $eventId): void
    {
        DB::table('kernel_pipeline_outbox')->insert([
            'event_id'       => $eventId,
            'event_type'     => 'CURRENT_KERNEL_RECEIVED',
            'schema_version' => 1,
            'payload'        => json_encode(['event_id' => $eventId]),
            'occurred_at'    => now(),
            'processed_at'   => null,
            'attempt_count'  => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function seedDomainTotals(): void
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

    private function seedStateV2(): void
    {
        DB::table('kernel_rotation_state_v2')->insert([
            'active_depth'                    => 2,
            'rotation_status'                 => 'TOUR_IN_PROGRESS',
            'tour_domain_states'              => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'       => null,
            'last_counted_blueprint_identity' => null,
            'lock_version'                    => 1,
            'created_at'                      => now(),
            'updated_at'                      => now(),
        ]);
    }
}
