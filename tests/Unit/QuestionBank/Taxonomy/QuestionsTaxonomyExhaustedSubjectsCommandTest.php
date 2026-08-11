<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use Tests\CreatesApplication;

/**
 * Tests §51 — QuestionsTaxonomyExhaustedSubjectsCommand
 *
 * Couvre :
 *   - exit code 0 quand aucun sujet épuisé sans PASS
 *   - exit code 1 quand ≥1 sujet épuisé sans PASS
 *   - filtre --depth, --domain, --min-fails
 *
 * PATTERN SQLite (jamais RefreshDatabase — ADD CONSTRAINT CHECK est PG-only) :
 *   setUp()    → Schema::create()
 *   tearDown() → Schema::dropIfExists()
 */
class QuestionsTaxonomyExhaustedSubjectsCommandTest extends TestCase
{
    use CreatesApplication;

    /** @var \Illuminate\Foundation\Application */
    private $app;

    private TaxonomyBankRepository $repo;

    protected function setUp(): void
    {
        $this->app = $this->createApplication();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->repo = new TaxonomyBankRepository();
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        \Illuminate\Support\Facades\Schema::dropIfExists('taxonomy_generation_memory');
        \Illuminate\Support\Facades\Schema::dropIfExists('taxonomy_dominant_idea_bank');
        \Illuminate\Support\Facades\Schema::dropIfExists('taxonomy_subject_bank');
        \Illuminate\Support\Facades\Schema::dropIfExists('taxonomy_subdomain_bank');
    }

    // =========================================================================
    // §51a — exit 0 quand aucun sujet n'est problématique
    // =========================================================================

    public function test_command_exits_zero_when_no_exhausted_subjects(): void
    {
        $exitCode = $this->callCommand('questions:taxonomy:exhausted-subjects');
        $this->assertSame(0, $exitCode);
    }

    public function test_command_exits_zero_when_exhausted_subject_has_pass_idea(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Empire romain');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Empereurs');
        $this->repo->persistPassIdea($s->id, 'Auguste');
        $this->repo->persistFailIdea($s->id, '?', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        $exitCode = $this->callCommand('questions:taxonomy:exhausted-subjects');
        $this->assertSame(0, $exitCode);
    }

    // =========================================================================
    // §51b — exit 1 quand ≥1 sujet épuisé sans PASS
    // =========================================================================

    public function test_command_exits_one_when_exhausted_subject_has_only_fails(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Pharaons');
        $this->repo->persistFailIdea($s->id, '?', 'TOO_SHORT', null);
        $this->repo->persistFailIdea($s->id, '??', 'TOO_SHORT', null);
        $this->repo->persistFailIdea($s->id, '???', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        $exitCode = $this->callCommand('questions:taxonomy:exhausted-subjects');
        $this->assertSame(1, $exitCode);
    }

    // =========================================================================
    // §51c — filtre --depth
    // =========================================================================

    public function test_command_depth_filter_finds_matching_depth(): void
    {
        $sd2 = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $s2  = $this->repo->findOrCreateSubject($sd2->id, 'Pharaons');
        $this->repo->persistFailIdea($s2->id, '?', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s2->id)->update(['idea_generation_exhausted' => true]);

        $sd4 = $this->repo->findOrCreateSubdomain(4, 'histoire', 'Moyen Âge');
        $s4  = $this->repo->findOrCreateSubject($sd4->id, 'Chevaliers');
        $this->repo->persistFailIdea($s4->id, '?', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s4->id)->update(['idea_generation_exhausted' => true]);

        // --depth=4 → trouve le sujet depth=4 → exit 1
        $this->assertSame(1, $this->callCommand('questions:taxonomy:exhausted-subjects', ['--depth' => 4]));

        // --depth=6 → ne trouve rien → exit 0
        $this->assertSame(0, $this->callCommand('questions:taxonomy:exhausted-subjects', ['--depth' => 6]));
    }

    // =========================================================================
    // §51d — filtre --domain
    // =========================================================================

    public function test_command_domain_filter_excludes_other_domains(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'sport', 'Football');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Clubs célèbres');
        $this->repo->persistFailIdea($s->id, '?', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        $this->assertSame(1, $this->callCommand('questions:taxonomy:exhausted-subjects', ['--domain' => 'sport']));
        $this->assertSame(0, $this->callCommand('questions:taxonomy:exhausted-subjects', ['--domain' => 'cinema']));
    }

    // =========================================================================
    // §51e — filtre --min-fails
    // =========================================================================

    public function test_command_min_fails_filter(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'cuisine', 'Pâtisserie');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Gâteaux');
        $this->repo->persistFailIdea($s->id, '?', 'TOO_SHORT', null);
        $this->repo->persistFailIdea($s->id, '??', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        // 2 FAILs → --min-fails=2 finds it, --min-fails=3 does not
        $this->assertSame(1, $this->callCommand('questions:taxonomy:exhausted-subjects', ['--min-fails' => 2]));
        $this->assertSame(0, $this->callCommand('questions:taxonomy:exhausted-subjects', ['--min-fails' => 3]));
    }

    // =========================================================================
    // §51f — sortie --json : exit code correct, structure vérifiable via repo
    // =========================================================================

    public function test_command_json_flag_exits_one_and_data_is_queryable(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(4, 'science', 'Physique');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Particules');
        $this->repo->persistFailIdea($s->id, 'x', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        $exitCode = $this->callCommand('questions:taxonomy:exhausted-subjects', ['--json' => true]);
        $this->assertSame(1, $exitCode);

        // Verify the underlying repo data that would populate the JSON
        $rows = $this->repo->findExhaustedWithOnlyFails(1);
        $this->assertCount(1, $rows);
        $this->assertSame('Particules', $rows[0]->subject_name);
        $this->assertSame('science', $rows[0]->domain_code);
        $this->assertSame(4, (int) $rows[0]->depth);
        $this->assertSame(1, (int) $rows[0]->fail_count);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Runs an artisan command via the console kernel and returns the exit code.
     */
    private function callCommand(string $command, array $params = []): int
    {
        return $this->app->make(ConsoleKernel::class)->call($command, $params);
    }

    private function createSchema(): void
    {
        DB::statement('PRAGMA foreign_keys = ON');

        \Illuminate\Support\Facades\Schema::create('taxonomy_subdomain_bank', function ($table) {
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

        \Illuminate\Support\Facades\Schema::create('taxonomy_subject_bank', function ($table) {
            $table->id();
            $table->foreignId('subdomain_id')->constrained('taxonomy_subdomain_bank')->cascadeOnDelete();
            $table->string('subject_name', 256);
            $table->string('status', 16)->default('AVAILABLE');
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);
            $table->boolean('idea_generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['subdomain_id', 'subject_name']);
        });

        \Illuminate\Support\Facades\Schema::create('taxonomy_dominant_idea_bank', function ($table) {
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

        \Illuminate\Support\Facades\Schema::create('taxonomy_generation_memory', function ($table) {
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
