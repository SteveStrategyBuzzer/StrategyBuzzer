<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyConfig;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\CreatesApplication;

/**
 * Task #126 — Exhausted-subject warning regression suite.
 *
 * Verifies that TaxonomyOrchestrator::generateIdeasForSubject() emits
 * Log::warning('taxonomy.subject_exhausted_with_zero_pass') under exactly
 * the right conditions, and that TaxonomyBankRepository::findExhaustedWithOnlyFails()
 * returns precisely the rows it should.
 *
 * Three behavioural contracts tested:
 *   A) MAX_ATTEMPTS reached with 0 PASS → warning fired with correct context keys
 *   B) NO_MORE_IDEAS status with 0 PASS → warning fired with correct context keys
 *   C) ≥1 PASS idea generated → no warning, ever
 *   D-G) findExhaustedWithOnlyFails() inclusion / exclusion rules
 *
 * SQLite pattern (no RefreshDatabase — PG-only ADD CONSTRAINT CHECK migration):
 *   setUp() creates only the 4 Taxonomy tables manually.
 *   tearDown() drops them in reverse FK order.
 */
class TaxonomySubjectExhaustionWarningTest extends TestCase
{
    use CreatesApplication;

    private TaxonomyOrchestrator           $orchestrator;
    private TaxonomyBankRepository         $repo;
    private MockObject&TaxonomyGeminiClient $gemini;

    protected function setUp(): void
    {
        $this->createApplication();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->buildSchema();

        $this->repo         = new TaxonomyBankRepository();
        $this->gemini       = $this->createMock(TaxonomyGeminiClient::class);
        $this->orchestrator = new TaxonomyOrchestrator(
            $this->repo,
            $this->gemini,
            new ValidationDominantIdeas(),
        );
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        Schema::dropIfExists('taxonomy_generation_memory');
        Schema::dropIfExists('taxonomy_dominant_idea_bank');
        Schema::dropIfExists('taxonomy_subject_bank');
        Schema::dropIfExists('taxonomy_subdomain_bank');
        parent::tearDown();
    }

    // =========================================================================
    // A — Warning fires when MAX_ATTEMPTS already reached (early-return branch)
    //
    // Precondition: MAX_DOMINANT_IDEA_GENERATION_ATTEMPTS memory entries already
    // exist for the subject, so getNextAttemptNumber() returns MAX+1 and
    // generateIdeasForSubject() takes the early-return path without calling Gemini.
    // =========================================================================

    public function test_warning_emitted_when_max_attempts_reached_and_zero_pass(): void
    {
        // Seed subdomain + subject + FAIL ideas (no PASS).
        $sd = $this->repo->findOrCreateSubdomain(2, 'science', 'Physique quantique');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Principes fondamentaux');
        $this->repo->persistFailIdea($s->id, 'bad idea 1', 'FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION', null);
        $this->repo->persistFailIdea($s->id, 'bad idea 2', 'GENERIC_CATEGORY', null);

        // Pre-fill MAX attempts in memory so getNextAttemptNumber() returns MAX+1.
        $contextKey = TaxonomyBankRepository::ideaContextKey(
            2, 'science', 'Physique quantique', 'Principes fondamentaux'
        );
        for ($i = 1; $i <= TaxonomyConfig::MAX_DOMINANT_IDEA_GENERATION_ATTEMPTS; $i++) {
            $this->repo->persistMemoryEntry(
                'IDEA', $contextKey, $i,
                ['bad idea 1', 'bad idea 2'],
                [],
                [['value' => 'bad idea 1', 'reason' => 'FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION', 'conflict_with' => null]],
                [],
                false,
            );
        }

        // Mark the subdomain exhausted so fillBank() terminates after the subject
        // early-return without entering generateNewSubdomain() in an infinite loop.
        DB::table('taxonomy_subdomain_bank')
            ->where('id', $sd->id)
            ->update(['generation_exhausted' => true]);

        // Gemini must NOT be called on the early-return path.
        $this->gemini->expects($this->never())->method('generateIdeas');
        $this->gemini->method('generateSubdomains')->willReturn([
            'status' => 'NO_MORE_SUBDOMAINS', 'candidates' => [],
        ]);

        $handler = $this->attachLogHandler();
        $this->orchestrator->peekNext(2, 'science');

        $warnings = $this->warningsNamed($handler, 'taxonomy.subject_exhausted_with_zero_pass');
        $this->assertNotEmpty($warnings,
            'Warning must fire when MAX_ATTEMPTS is reached with 0 PASS ideas.'
        );

        $ctx = $warnings[0]['context'];
        $this->assertSame(2,                           $ctx['depth'],          'context key: depth');
        $this->assertSame('science',                   $ctx['domain_code'],    'context key: domain_code');
        $this->assertArrayHasKey('subdomain_name',     $ctx,                   'context key: subdomain_name');
        $this->assertSame($s->id,                      $ctx['subject_id'],     'context key: subject_id');
        $this->assertSame('Principes fondamentaux',    $ctx['subject_name'],   'context key: subject_name');
        $this->assertGreaterThan(0,                    $ctx['fail_count'],     'context key: fail_count');
        $this->assertArrayHasKey('attempt_number',     $ctx,                   'context key: attempt_number');
        $this->assertSame('MAX_ATTEMPTS_ALREADY_REACHED', $ctx['status'],      'context key: status');
        $this->assertArrayHasKey('message',            $ctx,                   'context key: message');
    }

    // =========================================================================
    // B — Warning fires when Gemini signals NO_MORE_IDEAS with 0 PASS
    //
    // Precondition: FAIL ideas already in DB, Gemini returns NO_MORE_IDEAS with
    // empty candidates → totalPass=0, totalFail>0 → warning must fire.
    // =========================================================================

    public function test_warning_emitted_when_no_more_ideas_and_zero_pass(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(4, 'geographie', 'Océans');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Courants marins');
        $this->repo->persistFailIdea($s->id, 'Idée nulle A', 'FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION', null);
        $this->repo->persistFailIdea($s->id, 'Idée nulle B', 'GENERIC_CATEGORY', null);
        $this->repo->persistFailIdea($s->id, 'Idée nulle C', 'DUPLICATE', null);

        // Gemini: no more ideas for this subject, nothing new to validate.
        $this->gemini->method('generateIdeas')->willReturn([
            'status' => 'NO_MORE_IDEAS', 'candidates' => [],
        ]);
        // Stop fillBank() after subject exhaustion.
        $this->gemini->method('generateSubjects')->willReturn([
            'status' => 'NO_MORE_SUBJECTS', 'candidates' => [],
        ]);
        $this->gemini->method('generateSubdomains')->willReturn([
            'status' => 'NO_MORE_SUBDOMAINS', 'candidates' => [],
        ]);

        $handler = $this->attachLogHandler();
        $this->orchestrator->peekNext(4, 'geographie');

        $warnings = $this->warningsNamed($handler, 'taxonomy.subject_exhausted_with_zero_pass');
        $this->assertNotEmpty($warnings,
            'Warning must fire when Gemini returns NO_MORE_IDEAS and 0 PASS ideas exist.'
        );

        $ctx = $warnings[0]['context'];
        $this->assertSame(4,                $ctx['depth']);
        $this->assertSame('geographie',     $ctx['domain_code']);
        $this->assertSame($s->id,           $ctx['subject_id']);
        $this->assertSame('Courants marins',$ctx['subject_name']);
        $this->assertSame(3,                $ctx['fail_count']);
        $this->assertArrayHasKey('message', $ctx);
    }

    // =========================================================================
    // C — No warning when at least one PASS idea is generated
    //
    // Gemini provides one valid idea → ValidationDominantIdeas marks it PASS →
    // warnIfZeroPass must NOT emit any warning.
    // =========================================================================

    public function test_no_warning_when_at_least_one_pass_idea_generated(): void
    {
        $this->gemini->method('generateSubdomains')->willReturn([
            'status' => 'CANDIDATES', 'candidates' => [['value' => 'Ère spatiale']],
        ]);
        $this->gemini->method('generateSubjects')->willReturn([
            'status' => 'CANDIDATES', 'candidates' => [['value' => 'Missions lunaires']],
        ]);
        // Return one valid idea → validator marks it PASS.
        $this->gemini->method('generateIdeas')->willReturn([
            'status' => 'CANDIDATES', 'candidates' => [['value' => 'Apollo 11']],
        ]);

        $handler = $this->attachLogHandler();
        $result  = $this->orchestrator->peekNext(6, 'science');

        $this->assertNotNull($result,
            'peekNext() must return a territory when a PASS idea was generated.'
        );
        $this->assertSame('Apollo 11', $result['dominant_idea_active']);

        $warnings = $this->warningsNamed($handler, 'taxonomy.subject_exhausted_with_zero_pass');
        $this->assertEmpty($warnings,
            'No warning must be logged when the subject has ≥1 PASS idea.'
        );
    }

    // =========================================================================
    // D–G — TaxonomyBankRepository::findExhaustedWithOnlyFails()
    // =========================================================================

    /** D — Exhausted subject with only FAILs is returned. */
    public function test_find_exhausted_with_only_fails_includes_zero_pass_subjects(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'cinema', 'Cinéma muet');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Réalisateurs pionniers');

        $this->repo->persistFailIdea($s->id, 'idée A', 'INVALID', null);
        $this->repo->persistFailIdea($s->id, 'idée B', 'INVALID', null);
        DB::table('taxonomy_subject_bank')
            ->where('id', $s->id)
            ->update(['idea_generation_exhausted' => true]);

        $rows = $this->repo->findExhaustedWithOnlyFails(minFails: 1);

        $this->assertCount(1, $rows);
        $this->assertSame('Réalisateurs pionniers', $rows[0]->subject_name);
        $this->assertSame('Cinéma muet',            $rows[0]->subdomain_name);
        $this->assertSame(2,                        (int) $rows[0]->fail_count);
        $this->assertSame('cinema',                 $rows[0]->domain_code);
        $this->assertSame(2,                        (int) $rows[0]->depth);
    }

    /** E — Subject with ≥1 PASS is excluded even if it has FAILs. */
    public function test_find_exhausted_with_only_fails_excludes_subjects_with_pass(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'cinema', 'Cinéma muet');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Réalisateurs pionniers');

        $this->repo->persistPassIdea($s->id, 'Bonne idée');
        $this->repo->persistFailIdea($s->id, 'Mauvaise idée', 'INVALID', null);
        DB::table('taxonomy_subject_bank')
            ->where('id', $s->id)
            ->update(['idea_generation_exhausted' => true]);

        $rows = $this->repo->findExhaustedWithOnlyFails(minFails: 1);

        $this->assertCount(0, $rows,
            'A subject with ≥1 PASS idea must not appear in the alert query.'
        );
    }

    /** F — Non-exhausted subject is excluded even if it has FAILs. */
    public function test_find_exhausted_with_only_fails_excludes_non_exhausted_subjects(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'cinema', 'Cinéma muet');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Réalisateurs pionniers');

        $this->repo->persistFailIdea($s->id, 'Mauvaise idée', 'INVALID', null);
        // idea_generation_exhausted stays false (default)

        $rows = $this->repo->findExhaustedWithOnlyFails(minFails: 1);

        $this->assertCount(0, $rows,
            'A subject not yet marked exhausted must not appear in the alert query.'
        );
    }

    /** G — minFails threshold is respected exactly. */
    public function test_find_exhausted_with_only_fails_respects_min_fails_threshold(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'cinema', 'Cinéma muet');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Réalisateurs pionniers');

        $this->repo->persistFailIdea($s->id, 'seule mauvaise idée', 'INVALID', null);
        DB::table('taxonomy_subject_bank')
            ->where('id', $s->id)
            ->update(['idea_generation_exhausted' => true]);

        $this->assertCount(1, $this->repo->findExhaustedWithOnlyFails(minFails: 1),
            'minFails=1 must include a subject with exactly 1 FAIL.'
        );
        $this->assertCount(0, $this->repo->findExhaustedWithOnlyFails(minFails: 2),
            'minFails=2 must exclude a subject with only 1 FAIL.'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Attaches a Monolog TestHandler to the active log channel so we can inspect
     * emitted records without Log::fake() (which requires Illuminate TestCase).
     */
    private function attachLogHandler(): \Monolog\Handler\TestHandler
    {
        /** @var \Monolog\Logger $monolog */
        $monolog = app('log')->driver()->getLogger();
        $handler = new \Monolog\Handler\TestHandler();
        $monolog->pushHandler($handler);

        return $handler;
    }

    /**
     * Returns all WARNING-level records whose message matches $name exactly.
     *
     * @return array<int, \Monolog\LogRecord>
     */
    private function warningsNamed(\Monolog\Handler\TestHandler $handler, string $name): array
    {
        return array_values(array_filter(
            $handler->getRecords(),
            fn ($r) => $r->level === \Monolog\Level::Warning && $r->message === $name,
        ));
    }

    /**
     * Creates the 4 Taxonomy tables on the SQLite in-memory connection.
     * Mirrors the production migrations exactly enough for these tests.
     */
    private function buildSchema(): void
    {
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('taxonomy_subdomain_bank', function ($t) {
            $t->id();
            $t->unsignedTinyInteger('depth');
            $t->string('domain_code', 32);
            $t->string('subdomain_name', 256);
            $t->string('status', 16)->default('ACTIVE');
            $t->boolean('generation_exhausted')->default(false);
            $t->unsignedTinyInteger('subject_attempt_count')->default(0);
            $t->timestamps();
            $t->unique(['depth', 'domain_code', 'subdomain_name']);
        });

        Schema::create('taxonomy_subject_bank', function ($t) {
            $t->id();
            $t->foreignId('subdomain_id')
              ->constrained('taxonomy_subdomain_bank')
              ->cascadeOnDelete();
            $t->string('subject_name', 256);
            $t->string('status', 16)->default('AVAILABLE');
            $t->unsignedTinyInteger('idea_attempt_count')->default(0);
            $t->boolean('idea_generation_exhausted')->default(false);
            $t->timestamps();
            $t->unique(['subdomain_id', 'subject_name']);
        });

        Schema::create('taxonomy_dominant_idea_bank', function ($t) {
            $t->id();
            $t->foreignId('subject_id')
              ->constrained('taxonomy_subject_bank')
              ->cascadeOnDelete();
            $t->string('idea_value', 512);
            $t->string('validation_status', 8);
            $t->string('fail_reason', 64)->nullable();
            $t->string('fail_conflict_with', 512)->nullable();
            $t->string('status', 16);
            $t->timestamps();
            $t->index(['subject_id', 'validation_status', 'status']);
        });

        Schema::create('taxonomy_generation_memory', function ($t) {
            $t->id();
            $t->string('context_type', 16);
            $t->string('context_key', 512);
            $t->unsignedSmallInteger('attempt_number');
            $t->json('candidates')->nullable();
            $t->json('pass_items')->nullable();
            $t->json('fail_items')->nullable();
            $t->json('covered_directions')->nullable();
            $t->boolean('generation_exhausted')->default(false);
            $t->timestamps();
            $t->unique(['context_type', 'context_key', 'attempt_number']);
        });
    }
}
