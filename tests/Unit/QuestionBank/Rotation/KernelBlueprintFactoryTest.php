<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests pour KernelBlueprintFactory.
 *
 * DB : SQLite in-memory (tables créées manuellement).
 */
class KernelBlueprintFactoryTest extends TestCase
{
    private KernelBlueprintFactory $factory;

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

        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table) {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->json('source')->nullable();
            $table->json('creation_failure')->nullable();
            $table->json('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
            $table->json('validation_findings')->default('[]');
            $table->timestamps();
            $table->primary(['blueprint_id', 'cognitive_type']);
            $table->foreign('blueprint_id')
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->cascadeOnDelete();
        });

        $this->factory = new KernelBlueprintFactory();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // =========================================================================
    // Création basique
    // =========================================================================

    public function test_create_returns_blueprint_with_blueprint_id(): void
    {
        $blueprint = $this->factory->create();

        $this->assertNotNull($blueprint->blueprint_id);
        $this->assertIsString($blueprint->blueprint_id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $blueprint->blueprint_id,
            'blueprint_id doit être un UUID valide'
        );
    }

    public function test_create_blueprint_ids_are_unique(): void
    {
        // Nettoyer les actifs pour permettre la création multiple
        $bp1 = $this->factory->create();

        // Simuler la réception (état non-actif) pour débloquer la garde
        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $bp1->blueprint_id)
            ->update(['execution_state' => 'READY_BANK_RECEIVED']);

        $bp2 = $this->factory->create();

        $this->assertNotSame($bp1->blueprint_id, $bp2->blueprint_id);
    }

    public function test_create_inserts_row_in_kernel_blueprint_runs(): void
    {
        $blueprint = $this->factory->create();

        $row = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('CREATED_UNENGAGED', $row->execution_state);
        $this->assertNull($row->depth);
        $this->assertNull($row->domain_code);
    }

    public function test_create_atomically_inserts_the_seven_empty_slots(): void
    {
        $blueprint = $this->factory->create();

        $rows = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->get();

        $this->assertCount(7, $rows);
        $this->assertEqualsCanonicalizing(
            KernelBlueprint::COGNITIVE_TYPES,
            $rows->pluck('cognitive_type')->all()
        );
        foreach ($rows as $row) {
            $this->assertSame('EMPTY', $row->creation_status);
            $this->assertSame('NOT_VALIDATED', $row->validation_status);
            $this->assertNotNull($row->source);
            $this->assertSame(
                KernelBlueprint::emptyCognitiveSlotSource($row->cognitive_type),
                json_decode($row->source, true)
            );
            $this->assertNull($row->creation_failure);
            $this->assertSame('{}', $row->translations);
            $this->assertSame('[]', $row->validation_findings);
        }

        $this->assertCount(7, $blueprint->cognitive_slots);
    }

    public function test_create_rolls_back_parent_when_slot_initialization_fails(): void
    {
        $repository = new class extends KernelBlueprintCognitiveSlotRepository
        {
            public function initializeEmptySlots(string $blueprintId): array
            {
                throw new RuntimeException('slot initialization failed');
            }
        };

        $factory = new KernelBlueprintFactory($repository);

        try {
            $factory->create();
            $this->fail('La création devait échouer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('slot initialization failed', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
    }

    public function test_create_blueprint_has_null_depth_and_domain(): void
    {
        $blueprint = $this->factory->create();

        $this->assertNull($blueprint->depth);
        $this->assertNull($blueprint->domain);
    }

    public function test_create_blueprint_has_null_taxonomy_slots(): void
    {
        $blueprint = $this->factory->create();

        $this->assertNull($blueprint->subdomain_active);
        $this->assertNull($blueprint->subject_active);
        $this->assertNull($blueprint->dominant_idea_active);
        $this->assertNull($blueprint->kernel_code);
    }

    // =========================================================================
    // Garde — un seul Blueprint actif
    // =========================================================================

    public function test_create_throws_when_created_unengaged_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Blueprint actif existe déjà/');

        $this->factory->create(); // premier Blueprint actif
        $this->factory->create(); // doit lever une exception
    }

    public function test_create_throws_when_engaged_in_pipeline_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Blueprint actif existe déjà/');

        $bp = $this->factory->create();

        // Simuler l'engagement
        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $bp->blueprint_id)
            ->update(['execution_state' => 'ENGAGED_IN_PIPELINE']);

        $this->factory->create(); // doit lever une exception
    }

    public function test_create_allowed_when_previous_is_ready_bank_received(): void
    {
        $bp1 = $this->factory->create();

        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $bp1->blueprint_id)
            ->update(['execution_state' => 'READY_BANK_RECEIVED']);

        $bp2 = $this->factory->create(); // doit réussir

        $this->assertNotNull($bp2->blueprint_id);
    }

    public function test_create_allowed_when_previous_is_production_on_hold(): void
    {
        $bp1 = $this->factory->create();

        DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $bp1->blueprint_id)
            ->update(['execution_state' => 'NOT_ENGAGED_PRODUCTION_ON_HOLD']);

        $bp2 = $this->factory->create(); // doit réussir

        $this->assertNotNull($bp2->blueprint_id);
    }
}
