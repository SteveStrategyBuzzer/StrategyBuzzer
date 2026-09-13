<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Quarantine;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Quarantine\KernelQuarantineAdminService;
use App\Services\QuestionBank\Quarantine\KernelCurrentKernelReceivedRouter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class KernelQuarantineAdminServiceTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('kernel_code', 23)->nullable();
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code')->nullable();
            $table->string('subdomain_active')->nullable();
            $table->string('subject_active')->nullable();
            $table->string('dominant_idea_active')->nullable();
        });
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->json('source');
            $table->json('creation_failure')->nullable();
            $table->json('translations')->default('{}');
            $table->string('creation_status')->default('EMPTY');
            $table->string('validation_status')->default('NOT_VALIDATED');
            $table->json('validation_findings')->default('[]');
            $table->timestamps();
            $table->primary(['blueprint_id', 'cognitive_type']);
            $table->foreign('blueprint_id', 'kbcs_blueprint_id_fk')
                ->references('blueprint_id')->on('kernel_blueprint_runs')->cascadeOnDelete();
        });
        $this->migration = require base_path('database/migrations/2026_09_10_000001_create_dec125_quarantine_foundation.php');
        $this->migration->up();
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => 'bp-admin', 'kernel_code' => '06-SCI-SUB-SUJ-IDE-0000',
            'depth' => 6, 'domain_code' => 'science',
        ]);
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            DB::table('kernel_blueprint_cognitive_slots')->insert([
                'blueprint_id' => 'bp-admin',
                'cognitive_type' => $type,
                'source' => json_encode(KernelBlueprint::emptyCognitiveSlotSource($type)),
                'creation_status' => 'CREATED',
                'validation_status' => 'PASS',
                'validation_findings' => '[]',
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    public function test_editing_green_slot_clears_only_its_canonical_position_and_is_idempotent_stale_safe(): void
    {
        $service = new KernelQuarantineAdminService();
        $copy = $service->createCopyFromCanonical('bp-admin');
        $result = $service->editSlot(
            $copy['copy_id'],
            'QCM_RECOGNITION',
            ['source' => KernelBlueprint::emptyCognitiveSlotSource('QCM_RECOGNITION')],
            1,
        );

        self::assertSame(2, $result['copy_version']);
        self::assertSame('EMPTY', DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', 'bp-admin')->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('creation_status'));
        self::assertSame(6, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', 'bp-admin')->where('creation_status', 'CREATED')->count());
        self::assertSame('YELLOW', DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copy['copy_id'])->where('cognitive_type', 'QCM_RECOGNITION')->value('color'));

        $this->expectException(LogicException::class);
        $service->editSlot($copy['copy_id'], 'QCM_RECOGNITION', [], 1);
    }

    public function test_enqueue_is_idempotent_and_does_not_claim_copy(): void
    {
        $service = new KernelQuarantineAdminService();
        $copy = $service->createCopyFromCanonical('bp-admin');
        $secondCopy = $service->createCopyFromCanonical('bp-admin');
        $first = $service->enqueue($copy['copy_id'], 1, 'ready-event');
        $second = $service->enqueue($copy['copy_id'], 1, 'other-event');
        $service->enqueue($secondCopy['copy_id'], 1, 'ready-event-2');
        self::assertSame($first, $second);
        self::assertLessThan(
            DB::table('kernel_quarantine_work_copies')->where('copy_id', $secondCopy['copy_id'])->value('ready_order'),
            DB::table('kernel_quarantine_work_copies')->where('copy_id', $copy['copy_id'])->value('ready_order'),
        );
        self::assertSame('READY', DB::table('kernel_current_kernel_dispatches')
            ->where('event_id', 'ready-event')->value('state'));
        self::assertSame('READY', DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copy['copy_id'])->value('state'));
    }

    public function test_old_copy_cannot_clear_a_newer_canonical_revision(): void
    {
        $service = new KernelQuarantineAdminService();
        $first = $service->createCopyFromCanonical('bp-admin');
        $second = $service->createCopyFromCanonical('bp-admin');
        $service->editSlot($first['copy_id'], 'QCM_RECOGNITION', [], 1);

        $this->expectException(LogicException::class);
        $service->editSlot($second['copy_id'], 'QCM_RECOGNITION', [], 1);
    }

    public function test_router_shape_is_identical_on_first_decision_and_replay(): void
    {
        $service = new KernelQuarantineAdminService();
        $copy = $service->createCopyFromCanonical('bp-admin');
        $service->enqueue($copy['copy_id'], 1, 'ready-request');
        $router = new KernelCurrentKernelReceivedRouter();

        $first = $router->decide('ckr-1', 'bp-admin');
        $replay = $router->decide('ckr-1', 'bp-admin');

        self::assertSame('QUARANTINE', $first['direction']);
        self::assertSame('IN_FLIGHT', $first['state']);
        self::assertSame($first, $replay);

        $secondCopy = $service->createCopyFromCanonical('bp-admin');
        $service->enqueue($secondCopy['copy_id'], 1, 'ready-request-2');
        $blocked = $router->decide('ckr-2', 'bp-admin');
        self::assertSame('BLOCKED', $blocked['direction']);
        self::assertSame(3, DB::table('kernel_current_kernel_dispatches')->count());
    }

    public function test_red_copy_slot_tracks_revision_after_canonical_clear(): void
    {
        DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', 'bp-admin')
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['validation_status' => 'SUSPICION']);

        $copy = (new KernelQuarantineAdminService())->createCopyFromCanonical('bp-admin');
        $canonicalRevision = (int) DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', 'bp-admin')
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('canonical_revision');
        self::assertSame($canonicalRevision, (int) DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copy['copy_id'])
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('canonical_base_revision'));

        try {
            (new KernelQuarantineAdminService())->editSlot(
                $copy['copy_id'], 'QCM_RECOGNITION', [], 1, null, $canonicalRevision - 1
            );
            self::fail('An explicitly stale canonical revision must be rejected.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
        $result = (new KernelQuarantineAdminService())->editSlot(
            $copy['copy_id'],
            'QCM_RECOGNITION',
            [],
            1,
            null,
            $canonicalRevision,
        );
        self::assertSame(2, $result['copy_version']);
    }
}