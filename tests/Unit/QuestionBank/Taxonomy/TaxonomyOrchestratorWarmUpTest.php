<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\CreatesApplication;

final class TaxonomyOrchestratorWarmUpTest extends TestCase
{
    use CreatesApplication;

    private TaxonomyBankRepository $repo;
    private MockObject&TaxonomyGeminiClient $gemini;
    private TaxonomyOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->createApplication();
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();
        $this->repo = new TaxonomyBankRepository();
        $this->gemini = $this->createMock(TaxonomyGeminiClient::class);
        $this->orchestrator = new TaxonomyOrchestrator(
            $this->repo,
            $this->gemini,
            new ValidationDominantIdeas(),
        );
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach ([
            'taxonomy_v11_blueprint_assignments',
            'taxonomy_v11_terminal_facts',
            'taxonomy_v11_generation_memory',
            'taxonomy_v11_ideas',
            'taxonomy_v11_subjects',
            'taxonomy_v11_subdomains',
            'taxonomy_v11_occurrences',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_warm_up_v11_prepares_available_idea_without_consuming_or_assigning(): void
    {
        $this->mockPreparation();

        $result = $this->orchestrator->warmUpV11Cell(2, 'science', 1);

        $this->assertSame(1, $result);
        $this->assertSame(1, DB::table('taxonomy_v11_ideas')->where('status', 'AVAILABLE')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_ideas')->where('status', 'CONSUMED')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_blueprint_assignments')->count());
    }

    public function test_warm_up_v11_is_idempotent_when_target_is_already_reached(): void
    {
        $this->mockPreparation();
        $this->orchestrator->warmUpV11Cell(2, 'science', 1);

        $freshGemini = $this->createMock(TaxonomyGeminiClient::class);
        $freshGemini->expects($this->never())->method('generateOccurrence');
        $freshGemini->expects($this->never())->method('generateSubjects');
        $freshGemini->expects($this->never())->method('generateIdeas');
        $fresh = new TaxonomyOrchestrator(
            $this->repo,
            $freshGemini,
            new ValidationDominantIdeas(),
        );

        $this->assertSame(1, $fresh->warmUpV11Cell(2, 'science', 1));
        $this->assertSame(1, DB::table('taxonomy_v11_ideas')->count());
    }

    private function mockPreparation(): void
    {
        $this->gemini->expects($this->once())->method('generateOccurrence')->willReturn([
            'status' => 'CANDIDATES',
            'subdomain' => 'Propriétés de la matière',
            'subjects' => ['Particules élémentaires'],
        ]);
        $this->gemini->expects($this->once())->method('generateSubjects')->willReturn([
            'status' => 'NO_MORE_SUBJECTS',
            'candidates' => [],
        ]);
        $this->gemini->expects($this->once())->method('generateIdeas')->willReturn([
            'status' => 'CANDIDATES',
            'candidates' => [['value' => 'Charge électrique']],
        ]);
    }

    private function createSchema(): void
    {
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('taxonomy_v11_occurrences', function ($table) {
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
        Schema::create('taxonomy_v11_subdomains', function ($table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
            $table->string('subdomain_name', 256);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
        });
        Schema::create('taxonomy_v11_subjects', function ($table) {
            $table->id();
            $table->foreignId('subdomain_id')->constrained('taxonomy_v11_subdomains')->cascadeOnDelete();
            $table->string('subject_name', 256);
            $table->string('status', 24)->default('AVAILABLE');
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);
            $table->boolean('idea_generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['subdomain_id', 'subject_name']);
        });
        Schema::create('taxonomy_v11_ideas', function ($table) {
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
        Schema::create('taxonomy_v11_generation_memory', function ($table) {
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
        Schema::create('taxonomy_v11_terminal_facts', function ($table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
            $table->string('fact_id', 128)->unique();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('status', 16)->default('PENDING');
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('taxonomy_v11_blueprint_assignments', function ($table) {
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