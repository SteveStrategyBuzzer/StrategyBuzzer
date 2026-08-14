<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
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
 *   seed (subdomain + subject + idée PASS+AVAILABLE)
 *     → TaxonomyOrchestrator::peekNext() retourne le territoire (SANS Gemini)
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
            $taxonomy,
            new KernelCodeEngine(),
        );
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        Schema::dropIfExists('taxonomy_generation_memory');
        Schema::dropIfExists('taxonomy_dominant_idea_bank');
        Schema::dropIfExists('taxonomy_subject_bank');
        Schema::dropIfExists('taxonomy_subdomain_bank');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_code_sequences');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // =========================================================================
    // Chemin nominal : banque semée → ROTATION_ASSIGNED
    // =========================================================================

    public function test_seeded_bank_yields_rotation_assigned_blueprint(): void
    {
        // Gemini ne doit JAMAIS être appelé quand la banque est déjà semée
        $this->gemini->expects($this->never())->method('generateSubdomains');
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

        // ── 4. Blueprint engagé en DB ─────────────────────────────────────────
        $run = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->first();
        $this->assertNotNull($run);
        $this->assertSame('ENGAGED_IN_PIPELINE', $run->execution_state);
        $this->assertSame($this->firstDepth, (int) $run->depth);
        $this->assertSame(self::FIRST_DOMAIN, $run->domain_code);
    }

    // =========================================================================
    // peekNext direct — contrat de forme du territoire
    // =========================================================================

    public function test_peek_next_returns_seeded_territory_without_gemini(): void
    {
        $this->gemini->expects($this->never())->method('generateSubdomains');
        $this->gemini->expects($this->never())->method('generateSubjects');
        $this->gemini->expects($this->never())->method('generateIdeas');

        $this->seedBankCell($this->firstDepth, self::FIRST_DOMAIN, 'Capitales européennes', 'Paris', 'Paris est traversée par la Seine');

        $taxonomy = new TaxonomyOrchestrator($this->repo, $this->gemini, new ValidationDominantIdeas());
        $territory = $taxonomy->peekNext($this->firstDepth, self::FIRST_DOMAIN);

        $this->assertNotNull($territory);
        $this->assertSame('Capitales européennes', $territory['sub_domain'] ?? null);
        $this->assertSame('Paris', $territory['subject'] ?? null);
        $this->assertSame(
            'Paris est traversée par la Seine',
            $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? null,
            'Le territoire doit exposer l\'idée dominante sous une clé reconnue par KernelPipelineOrchestrator'
        );
    }

    // =========================================================================
    // Cellule semée dans le MAUVAIS domaine → EMPTY puis ROTATION sur le bon
    // =========================================================================

    public function test_bank_seeded_in_second_domain_is_reached_via_empty_transition(): void
    {
        // Banque vide pour 'geographie' — Gemini n'a plus rien à produire
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'NO_MORE_SUBDOMAINS',
            'candidates' => [],
        ]);

        // Seed uniquement le 2e domaine du cycle : 'histoire'
        $this->seedBankCell($this->firstDepth, 'histoire', 'Moyen Âge', 'La Magna Carta', 'La Magna Carta limite le pouvoir royal');

        $result = $this->orchestrator->run(null);

        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result['status']);
        $this->assertSame('histoire', $result['blueprint']->domain);
        $this->assertSame('Moyen Âge', $result['blueprint']->subdomain_active);
        $this->assertSame('La Magna Carta', $result['blueprint']->subject_active);
        $this->assertSame('La Magna Carta limite le pouvoir royal', $result['blueprint']->dominant_idea_active);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Insère un sous-domaine + sujet + idée PASS+AVAILABLE pour (depth, domain). */
    private function seedBankCell(int $depth, string $domainCode, string $subdomainName, string $subjectName, string $ideaValue): void
    {
        $subdomain = $this->repo->findOrCreateSubdomain($depth, $domainCode, $subdomainName);
        $subject   = $this->repo->findOrCreateSubject($subdomain->id, $subjectName);
        $this->repo->persistPassIdea($subject->id, $ideaValue);
    }

    private function createKernelSchema(): void
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

    }

    private function createTaxonomySchema(): void
    {
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('taxonomy_subdomain_bank', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('subdomain_name', 256);
            $table->string('status', 16)->default('ACTIVE');
            $table->boolean('generation_exhausted')->default(false);
            $table->unsignedTinyInteger('subject_attempt_count')->default(0);
            $table->timestamps();
            $table->unique(['depth', 'domain_code', 'subdomain_name']);
        });

        Schema::create('taxonomy_subject_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdomain_id')->constrained('taxonomy_subdomain_bank')->cascadeOnDelete();
            $table->string('subject_name', 256);
            $table->string('status', 16)->default('AVAILABLE');
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);
            $table->boolean('idea_generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['subdomain_id', 'subject_name']);
        });

        Schema::create('taxonomy_dominant_idea_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('taxonomy_subject_bank')->cascadeOnDelete();
            $table->string('idea_value', 512);
            $table->string('validation_status', 8);
            $table->string('fail_reason', 64)->nullable();
            $table->string('fail_conflict_with', 512)->nullable();
            $table->string('status', 16);
            $table->timestamps();
            $table->index(['subject_id', 'validation_status', 'status']);
        });

        Schema::create('taxonomy_generation_memory', function (Blueprint $table) {
            $table->id();
            $table->string('context_type', 16);
            $table->string('context_key', 512);
            $table->unsignedSmallInteger('attempt_number');
            $table->json('candidates')->nullable();
            $table->json('pass_items')->nullable();
            $table->json('fail_items')->nullable();
            $table->json('covered_directions')->nullable();
            $table->boolean('generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['context_type', 'context_key', 'attempt_number']);
        });
    }
}
