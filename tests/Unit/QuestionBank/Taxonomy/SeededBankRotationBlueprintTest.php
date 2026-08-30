<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\KernelRotationStateRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\TaxonomyPipelineBridge;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Test de bout en bout — banque Taxonomy semée → Blueprint ROTATION_ASSIGNED.
 *
 * Vérifie le chemin complet, jamais couvert par les tests unitaires isolés :
 *
 *   seed occurrence v1.1 (subdomain + subject + idée PASS+AVAILABLE)
 *     → TaxonomyPipelineBridge attribue le territoire (SANS Gemini)
 *     → KernelPipelineOrchestrator::run() retourne ROTATION_ASSIGNED
 *     → Blueprint : sub_domain / subject / dominant_idea remplis
 *
 * Composants RÉELS : KernelBlueprintFactory, KernelRotationPlanner,
 * TaxonomyOrchestrator, TaxonomyBankRepository (classes final — non mockables).
 * Le déterminisme du couple depth+domain vient des constantes officielles :
 *   - premier Depth requis  = DepthNeedMatrix::DEPTH_CYCLE[0]
 *   - premier Domaine ON    = DepthTourState::DOMAIN_CYCLE[0] ('geographie')
 * Seul TaxonomyGeminiClient est mocké — et le test prouve qu'il n'est
 * JAMAIS appelé quand la banque est déjà semée.
 *
 * DB : SQLite in-memory, tables créées manuellement (PATTERN sans RefreshDatabase).
 */
class SeededBankRotationBlueprintTest extends TestCase
{
    private const DOMAINS = ['geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science'];

    /** Premier domaine du DomainCycle officiel — celui que planV2 choisit au premier appel. */
    private const FIRST_DOMAIN = 'geographie';

    private TaxonomyBankRepository $repo;
    private MockObject&TaxonomyGeminiClient $gemini;
    private KernelPipelineOrchestrator $orchestrator;
    private int $firstDepth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createKernelSchema();
        $this->createTaxonomySchema();

        $this->firstDepth = DepthNeedMatrix::DEPTH_CYCLE[0];

        // Seed la matrice des Depths — toutes les cibles > 0
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

        // ── Dépendances réelles + Gemini mocké ───────────────────────────────
        $this->repo   = new TaxonomyBankRepository();
        $this->gemini = $this->createMock(TaxonomyGeminiClient::class);

        $taxonomy = new TaxonomyOrchestrator(
            $this->repo,
            $this->gemini,
            new ValidationDominantIdeas(),
        );

        $this->orchestrator = new KernelPipelineOrchestrator(
            new KernelBlueprintFactory(),
            new KernelRotationPlanner(),
            new KernelRotationStateRepository(),
            new TaxonomyPipelineBridge(
                $taxonomy,
                $this->repo,
                new KernelRotationPlanner(),
                new KernelCodeEngine(),
            ),
        );
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        Schema::dropIfExists('taxonomy_v11_blueprint_assignments');
        Schema::dropIfExists('taxonomy_v11_terminal_facts');
        Schema::dropIfExists('taxonomy_v11_generation_memory');
        Schema::dropIfExists('taxonomy_v11_ideas');
        Schema::dropIfExists('taxonomy_v11_subjects');
        Schema::dropIfExists('taxonomy_v11_subdomains');
        Schema::dropIfExists('taxonomy_v11_occurrences');
        Schema::dropIfExists('kernel_taxonomy_terminal_facts');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_code_sequences');
        Schema::dropIfExists('kernel_blueprint_cognitive_slots');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // =========================================================================
    // Chemin nominal : banque semée → ROTATION_ASSIGNED
    // =========================================================================

    public function test_seeded_bank_yields_rotation_assigned_blueprint(): void
    {
        // Gemini ne doit JAMAIS être appelé quand la banque est déjà semée
        $this->gemini->expects($this->never())->method('generateOccurrence');
        $this->gemini->expects($this->never())->method('generateSubjects');
        $this->gemini->expects($this->never())->method('generateIdeas');

        // ── 1. Seed minimal de la banque pour le premier couple depth+domain ──
        $this->seedBankCell($this->firstDepth, self::FIRST_DOMAIN, 'Capitales européennes', 'Paris', 'Paris est traversée par la Seine');

        // ── 2. Exécuter le pipeline complet ──────────────────────────────────
        $result = $this->orchestrator->run(null);

        // ── 3. ROTATION_ASSIGNED + Blueprint entièrement rempli ──────────────
        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);

        /** @var KernelBlueprint $blueprint */
        $blueprint = $result['blueprint'];
        $this->assertInstanceOf(KernelBlueprint::class, $blueprint);
        $this->assertSame($this->firstDepth, $blueprint->depth);
        $this->assertSame(self::FIRST_DOMAIN, $blueprint->domain);
        $this->assertSame('Capitales européennes', $blueprint->subdomain_active);
        $this->assertSame('Paris', $blueprint->subject_active);
        $this->assertSame('Paris est traversée par la Seine', $blueprint->dominant_idea_active);
        $this->assertTrue($blueprint->isTaxonomyFilled(), 'Les slots Taxonomy doivent être remplis');
        $this->assertNotNull($blueprint->kernel_code);
        $this->assertSame($blueprint->kernel_code, $blueprint->kernelCodeProjection());

        // ── 4. Blueprint engagé en DB ─────────────────────────────────────────
        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->first();
        $this->assertNotNull($run);
        $this->assertSame('ENGAGED_IN_PIPELINE', $run->execution_state);
        $this->assertSame($this->firstDepth, (int) $run->depth);
        $this->assertSame(self::FIRST_DOMAIN, $run->domain_code);
        $this->assertSame($blueprint->kernel_code, $run->kernel_code);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Insère une occurrence v1.1 OPEN avec une idée PASS+AVAILABLE. */
    private function seedBankCell(int $depth, string $domainCode, string $subdomainName, string $subjectName, string $ideaValue): void
    {
        $occurrence = $this->repo->findOrCreateV11Occurrence($depth, $domainCode);
        $subdomain = $this->repo->createV11Subdomain((int) $occurrence->id, $subdomainName);
        $this->repo->createV11Subjects((int) $subdomain->id, [$subjectName]);
        $subject = $this->repo->getV11SubjectsForSubdomain((int) $subdomain->id)[0];
        $this->repo->persistV11PassIdea((int) $subject->id, $ideaValue);
        $this->repo->markV11OccurrenceOpen((int) $occurrence->id);
    }

    private function createKernelSchema(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('kernel_code', 23)->nullable()->unique();
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
        });

        Schema::create('kernel_code_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('depth');
            $table->char('domain_code', 3);
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
            $table->string('active_tour_id', 36)->nullable();
            $table->string('tour_state', 32)->nullable();
            $table->string('last_closed_tour_id', 36)->nullable();
            $table->smallInteger('last_closed_depth')->nullable();
            $table->string('last_closed_tour_summary_hash', 64)->nullable();
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

        Schema::create('kernel_taxonomy_terminal_facts', function (Blueprint $table) {
            $table->id();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('tour_id', 36);
            $table->timestamp('received_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    private function createTaxonomySchema(): void
    {
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('taxonomy_v11_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->unsignedInteger('ordinal');
            $table->string('status', 16)->default('PREPARING');
            $table->unsignedTinyInteger('consecutive_technical_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('exhausted_at')->nullable();
            $table->timestamps();
            $table->unique(['depth', 'domain_code', 'ordinal']);
        });

        Schema::create('taxonomy_v11_subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()
                ->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
            $table->string('subdomain_name', 256);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('taxonomy_v11_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdomain_id')->constrained('taxonomy_v11_subdomains')->cascadeOnDelete();
            $table->string('subject_name', 256);
            $table->string('status', 24)->default('AVAILABLE');
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);
            $table->boolean('idea_generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['subdomain_id', 'subject_name']);
        });

        Schema::create('taxonomy_v11_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('taxonomy_v11_subjects')->cascadeOnDelete();
            $table->string('idea_value', 512);
            $table->string('validation_status', 8);
            $table->string('fail_reason', 64)->nullable();
            $table->string('fail_conflict_with', 512)->nullable();
            $table->string('status', 16);
            $table->timestamps();
            $table->unique(['subject_id', 'idea_value']);
        });

        Schema::create('taxonomy_v11_generation_memory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
            $table->string('context_type', 16);
            $table->string('context_key', 512);
            $table->unsignedSmallInteger('attempt_number');
            $table->json('candidates')->nullable();
            $table->json('pass_items')->nullable();
            $table->json('fail_items')->nullable();
            $table->json('covered_directions')->nullable();
            $table->boolean('generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['occurrence_id', 'context_type', 'context_key', 'attempt_number']);
        });

        Schema::create('taxonomy_v11_terminal_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()
                ->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('status', 16)->default('PENDING');
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('taxonomy_v11_blueprint_assignments', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->foreignId('occurrence_id')->constrained('taxonomy_v11_occurrences')->restrictOnDelete();
            $table->foreignId('subdomain_id')->constrained('taxonomy_v11_subdomains')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('taxonomy_v11_subjects')->restrictOnDelete();
            $table->foreignId('idea_id')->constrained('taxonomy_v11_ideas')->restrictOnDelete();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('subdomain_active', 256);
            $table->string('subject_active', 256);
            $table->string('dominant_idea_active', 512);
            $table->timestamps();
        });
    }
}
