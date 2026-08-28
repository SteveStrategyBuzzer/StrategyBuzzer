<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyBlockedException;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\CreatesApplication;

/**
 * Contrat actif Taxonomy v1.1.
 *
 * Les trois appels v1.0 ci-dessous sont exclusivement des tests de refus.
 */
final class TaxonomyOrchestratorTest extends TestCase
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

    public function test_assign_to_blueprint_selects_writes_and_consumes_exactly_one_idea(): void
    {
        $this->gemini->expects($this->never())->method('generateOccurrence');
        $this->gemini->expects($this->never())->method('generateSubjects');
        $this->gemini->expects($this->never())->method('generateIdeas');
        $this->seedAvailableIdeas(['Charge électrique', 'Spin quantique']);
        $blueprint = $this->blueprint('00000000-0000-7000-8000-000000000101');

        $this->orchestrator->assignToBlueprint($blueprint);

        $this->assertSame(2, $blueprint->depth);
        $this->assertSame('science', $blueprint->domain);
        $this->assertSame('Propriétés de la matière', $blueprint->subdomain_active);
        $this->assertSame('Particules élémentaires', $blueprint->subject_active);
        $this->assertSame('Charge électrique', $blueprint->dominant_idea_active);
        $this->assertSame(1, DB::table('taxonomy_v11_blueprint_assignments')->count());
        $this->assertSame(1, DB::table('taxonomy_v11_ideas')->where('status', 'CONSUMED')->count());
        $this->assertSame(1, DB::table('taxonomy_v11_ideas')->where('status', 'AVAILABLE')->count());
    }

    public function test_blueprint_idempotence_reuses_assignment_without_second_consumption(): void
    {
        $this->seedAvailableIdeas(['Charge électrique', 'Spin quantique']);
        $id = '00000000-0000-7000-8000-000000000102';
        $first = $this->blueprint($id);
        $this->orchestrator->assignToBlueprint($first);

        $reloaded = $this->blueprint($id);
        $this->orchestrator->assignToBlueprint($reloaded);

        $this->assertSame($first->subdomain_active, $reloaded->subdomain_active);
        $this->assertSame($first->subject_active, $reloaded->subject_active);
        $this->assertSame($first->dominant_idea_active, $reloaded->dominant_idea_active);
        $this->assertSame(1, DB::table('taxonomy_v11_blueprint_assignments')->count());
        $this->assertSame(1, DB::table('taxonomy_v11_ideas')->where('status', 'CONSUMED')->count());
    }

    public function test_losing_assignment_retry_reloads_winner_without_consuming_claimed_idea(): void
    {
        $this->seedAvailableIdeas(['Charge électrique', 'Spin quantique']);
        $blueprintId = '00000000-0000-7000-8000-000000000104';
        $winnerIdea = DB::table('taxonomy_v11_ideas')
            ->where('idea_value', 'Spin quantique')
            ->first();
        $subject = DB::table('taxonomy_v11_subjects')->where('id', $winnerIdea->subject_id)->first();
        $subdomain = DB::table('taxonomy_v11_subdomains')->where('id', $subject->subdomain_id)->first();
        $occurrence = DB::table('taxonomy_v11_occurrences')->where('id', $subdomain->occurrence_id)->first();

        $ideaReads = 0;
        $winnerInserted = false;
        DB::listen(function ($query) use (
            &$ideaReads,
            &$winnerInserted,
            $blueprintId,
            $winnerIdea,
            $subject,
            $subdomain,
            $occurrence,
        ): void {
            if (
                $winnerInserted
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains($query->sql, 'taxonomy_v11_ideas')
            ) {
                return;
            }

            $ideaReads++;
            if ($ideaReads < 2) {
                return;
            }

            $winnerInserted = true;
            DB::table('taxonomy_v11_blueprint_assignments')->insert([
                'blueprint_id'         => $blueprintId,
                'occurrence_id'        => $occurrence->id,
                'subdomain_id'         => $subdomain->id,
                'subject_id'           => $subject->id,
                'idea_id'              => $winnerIdea->id,
                'depth'                => 2,
                'domain_code'          => 'science',
                'subdomain_active'     => $subdomain->subdomain_name,
                'subject_active'       => $subject->subject_name,
                'dominant_idea_active' => $winnerIdea->idea_value,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
            DB::table('taxonomy_v11_ideas')
                ->where('id', $winnerIdea->id)
                ->update(['status' => 'CONSUMED', 'updated_at' => now()]);
        });

        $blueprint = $this->blueprint($blueprintId);
        $this->orchestrator->assignToBlueprint($blueprint);

        $this->assertTrue($winnerInserted);
        $this->assertSame('Spin quantique', $blueprint->dominant_idea_active);
        $this->assertSame(
            'AVAILABLE',
            DB::table('taxonomy_v11_ideas')->where('idea_value', 'Charge électrique')->value('status'),
        );
        $this->assertSame(1, DB::table('taxonomy_v11_ideas')->where('status', 'CONSUMED')->count());
        $this->assertSame(1, DB::table('taxonomy_v11_blueprint_assignments')->count());
    }

    public function test_terminal_fact_is_persistent_and_idempotent(): void
    {
        $occurrence = $this->repo->findOrCreateV11Occurrence(2, 'science');
        $this->repo->markV11OccurrenceOpen((int) $occurrence->id);

        $this->repo->markV11OccurrenceExhausted((int) $occurrence->id, 2, 'science');
        $this->repo->markV11OccurrenceExhausted((int) $occurrence->id, 2, 'science');

        $this->assertSame('EXHAUSTED', DB::table('taxonomy_v11_occurrences')->value('status'));
        $this->assertSame(1, DB::table('taxonomy_v11_terminal_facts')->count());
        $this->assertSame('PENDING', DB::table('taxonomy_v11_terminal_facts')->value('status'));
    }

    public function test_lookback_two_is_scoped_by_depth_and_domain_and_keeps_subject_ideas_together(): void
    {
        $this->seedExhaustedOccurrence(2, 'science', 'A', 'Sujet A', 'PASS A', 'FAIL A');
        $this->seedExhaustedOccurrence(2, 'science', 'B', 'Sujet B', 'PASS B', 'FAIL B');
        $this->seedExhaustedOccurrence(2, 'science', 'C', 'Sujet C', 'PASS C', 'FAIL C');
        $this->seedExhaustedOccurrence(4, 'science', 'Hors profondeur', 'Sujet D', 'PASS D', 'FAIL D');
        $this->seedExhaustedOccurrence(2, 'histoire', 'Hors domaine', 'Sujet E', 'PASS E', 'FAIL E');

        $lookback = $this->repo->v11Lookback(2, 'science');

        $this->assertCount(2, $lookback);
        $this->assertSame(['C', 'B'], array_column($lookback, 'subdomain'));
        $this->assertSame('Sujet C', $lookback[0]['subjects'][0]['subject']);
        $this->assertSame(['PASS C'], $lookback[0]['subjects'][0]['pass_ideas']);
        $this->assertSame(
            [['value' => 'FAIL C', 'reason' => 'TEST_FAIL']],
            $lookback[0]['subjects'][0]['fail_ideas'],
        );
    }

    public function test_blocked_occurrence_refuses_assignment_before_any_gemini_call(): void
    {
        $occurrence = $this->repo->findOrCreateV11Occurrence(2, 'science');
        $this->repo->recordV11TechnicalFailure((int) $occurrence->id, 'failure 1');
        $this->repo->recordV11TechnicalFailure((int) $occurrence->id, 'failure 2');
        $this->repo->recordV11TechnicalFailure((int) $occurrence->id, 'failure 3');
        $this->gemini->expects($this->never())->method('generateOccurrence');
        $this->gemini->expects($this->never())->method('generateSubjects');
        $this->gemini->expects($this->never())->method('generateIdeas');

        $this->expectException(TaxonomyBlockedException::class);
        $this->orchestrator->assignToBlueprint(
            $this->blueprint('00000000-0000-7000-8000-000000000103')
        );
    }

    public function test_peek_next_tombstone_throws_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->orchestrator->peekNext(2, 'science');
    }

    public function test_confirm_consumed_tombstone_throws_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->orchestrator->confirmConsumed(2, 'science');
    }

    public function test_is_exhausted_tombstone_throws_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->orchestrator->isExhausted(2, 'science');
    }

    private function seedAvailableIdeas(array $ideas): void
    {
        $occurrence = $this->repo->findOrCreateV11Occurrence(2, 'science');
        $subdomain = $this->repo->createV11Subdomain(
            (int) $occurrence->id,
            'Propriétés de la matière',
        );
        $this->repo->createV11Subjects((int) $subdomain->id, ['Particules élémentaires']);
        $subject = $this->repo->getV11SubjectsForSubdomain((int) $subdomain->id)[0];
        foreach ($ideas as $idea) {
            $this->repo->persistV11PassIdea((int) $subject->id, $idea);
        }
        $this->repo->markV11OccurrenceOpen((int) $occurrence->id);
    }

    private function seedExhaustedOccurrence(
        int $depth,
        string $domain,
        string $subdomainName,
        string $subjectName,
        string $pass,
        string $fail,
    ): void {
        $occurrence = $this->repo->findOrCreateV11Occurrence($depth, $domain);
        $subdomain = $this->repo->createV11Subdomain((int) $occurrence->id, $subdomainName);
        $this->repo->createV11Subjects((int) $subdomain->id, [$subjectName]);
        $subject = $this->repo->getV11SubjectsForSubdomain((int) $subdomain->id)[0];
        $this->repo->persistV11PassIdea((int) $subject->id, $pass);
        $this->repo->persistV11FailIdea((int) $subject->id, $fail, 'TEST_FAIL');
        $this->repo->markV11OccurrenceOpen((int) $occurrence->id);
        $this->repo->markV11OccurrenceExhausted((int) $occurrence->id, $depth, $domain);
    }

    private function blueprint(string $id): KernelBlueprint
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($id);
        $blueprint->fillRotation(2, 'science');
        return $blueprint;
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