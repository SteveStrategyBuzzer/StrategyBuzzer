<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\CreatesApplication;

/**
 * Tests §40–47 — TaxonomyOrchestrator
 *
 * Couvre :
 *   - peekNext() idempotent
 *   - confirmConsumed() avance d'un cran
 *   - isExhausted() avec banque vide vs remplie
 *   - Fail-closed sur Depth inconnu
 *   - Mémoire cumulative : Gemini appelé seulement si aucune idée disponible
 *   - NO_MORE_IDEAS → épuisement marqué
 *   - Cascade subdomain → subject → idea
 *
 * PATTERN SQLite (jamais RefreshDatabase — ADD CONSTRAINT CHECK est PG-only) :
 *   setUp()    → Schema::create() toutes les tables
 *   tearDown() → Schema::dropIfExists() toutes les tables
 */
class TaxonomyOrchestratorTest extends TestCase
{
    use CreatesApplication;

    private TaxonomyOrchestrator         $orchestrator;
    private TaxonomyBankRepository       $repo;
    private MockObject&TaxonomyGeminiClient  $gemini;
    private ValidationDominantIdeas      $validator;

    protected function setUp(): void
    {
        $this->createApplication();

        // ── SQLite en-mémoire ────────────────────────────────────────────────
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();

        // ── Dépendances ──────────────────────────────────────────────────────
        $this->repo      = new TaxonomyBankRepository();
        $this->gemini    = $this->createMock(TaxonomyGeminiClient::class);
        $this->validator = new ValidationDominantIdeas();

        $this->orchestrator = new TaxonomyOrchestrator(
            $this->repo,
            $this->gemini,
            $this->validator,
        );
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
    // §40 — isExhausted() : banque vide → false (jamais initialisée)
    // =========================================================================

    public function test_is_not_exhausted_when_bank_is_empty(): void
    {
        $this->assertFalse($this->orchestrator->isExhausted(2, 'histoire'));
    }

    // =========================================================================
    // §41 — Fail-closed : Depth inconnu → InvalidArgumentException
    // =========================================================================

    public function test_peek_next_throws_for_unknown_depth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->orchestrator->peekNext(99, 'histoire');
    }

    public function test_is_exhausted_does_not_throw_for_unknown_depth(): void
    {
        // isExhausted doit retourner false (banque vide = pas initialisée)
        // sans passer par DepthContractRegistry (pas besoin du contrat)
        $this->assertFalse($this->orchestrator->isExhausted(99, 'histoire'));
    }

    // =========================================================================
    // §42 — peekNext() retourne null quand Gemini signale NO_MORE_IDEAS
    // =========================================================================

    public function test_peek_next_returns_null_when_gemini_returns_no_more(): void
    {
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'NO_MORE_SUBDOMAINS',
            'candidates' => [],
        ]);
        $this->gemini->method('generateSubjects')->willReturn([
            'status'     => 'NO_MORE_SUBJECTS',
            'candidates' => [],
        ]);
        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'NO_MORE_IDEAS',
            'candidates' => [],
        ]);

        $result = $this->orchestrator->peekNext(2, 'histoire');

        $this->assertNull($result);
    }

    // =========================================================================
    // §43 — peekNext() retourne le triplet quand Gemini génère des idées
    // =========================================================================

    public function test_peek_next_returns_territory_when_gemini_provides_ideas(): void
    {
        $this->mockGeminiFullPipeline(
            subdomain: 'Première Guerre mondiale',
            subject: 'Batailles majeures',
            idea: 'Bataille de la Somme',
        );

        $result = $this->orchestrator->peekNext(2, 'histoire');

        $this->assertNotNull($result);
        $this->assertSame('Première Guerre mondiale', $result['sub_domain']);
        $this->assertSame('Batailles majeures', $result['subject']);
        $this->assertSame('Bataille de la Somme', $result['dominant_idea_active']);
    }

    // =========================================================================
    // §44 — peekNext() est IDEMPOTENT : deux appels = même résultat
    // =========================================================================

    public function test_peek_next_is_idempotent(): void
    {
        $this->mockGeminiFullPipeline(
            subdomain: 'Révolution française',
            subject: 'Causes',
            idea: 'Crise financière',
        );

        // Gemini doit être appelé exactement une fois (grâce au cache DB)
        $this->gemini->expects($this->once())
            ->method('generateSubdomains')
            ->willReturn([
                'status'     => 'CANDIDATES',
                'candidates' => [['value' => 'Révolution française']],
            ]);

        $r1 = $this->orchestrator->peekNext(2, 'histoire');
        $r2 = $this->orchestrator->peekNext(2, 'histoire');

        $this->assertSame($r1, $r2);
    }

    // =========================================================================
    // §45 — confirmConsumed() avance l'idée → peekNext() retourne null ensuite
    //        (si une seule idée générée)
    // =========================================================================

    public function test_confirm_consumed_advances_past_single_idea(): void
    {
        $this->mockGeminiFullPipeline(
            subdomain: 'Empire romain',
            subject: 'Empereurs',
            idea: 'Auguste',
        );

        // Premier appel : idée disponible
        $before = $this->orchestrator->peekNext(2, 'histoire');
        $this->assertNotNull($before);
        $this->assertSame('Auguste', $before['dominant_idea_active']);

        // Consommer l'idée
        $this->orchestrator->confirmConsumed(2, 'histoire');

        // Gemini ne peut plus rien générer (NO_MORE dans tous les cas)
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'NO_MORE_SUBDOMAINS',
            'candidates' => [],
        ]);
        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'NO_MORE_IDEAS',
            'candidates' => [],
        ]);

        // Deuxième appel : banque épuisée
        $after = $this->orchestrator->peekNext(2, 'histoire');
        $this->assertNull($after);
    }

    // =========================================================================
    // §46 — confirmConsumed() est no-op quand rien n'est disponible
    // =========================================================================

    public function test_confirm_consumed_is_noop_when_nothing_available(): void
    {
        // Ne doit pas lancer d'exception
        $this->orchestrator->confirmConsumed(2, 'histoire');
        $this->assertTrue(true);
    }

    // =========================================================================
    // §47 — isExhausted() après que tous les sujets/idées sont épuisés
    // =========================================================================

    public function test_is_exhausted_after_bank_is_fully_consumed(): void
    {
        // Créer la structure directement en DB
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Empire romain');
        DB::table('taxonomy_subdomain_bank')
            ->where('id', $sd->id)
            ->update(['generation_exhausted' => true]);

        $s = $this->repo->findOrCreateSubject($sd->id, 'Empereurs');
        DB::table('taxonomy_subject_bank')
            ->where('id', $s->id)
            ->update(['idea_generation_exhausted' => true]);

        // Aucune idée AVAILABLE
        $this->assertTrue($this->orchestrator->isExhausted(2, 'histoire'));
    }

    // =========================================================================
    // §47b — isExhausted() = false si une idée AVAILABLE existe
    // =========================================================================

    public function test_is_not_exhausted_when_available_idea_exists(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Empire romain');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Empereurs');
        $this->repo->persistPassIdea($s->id, 'Auguste');

        $this->assertFalse($this->orchestrator->isExhausted(2, 'histoire'));
    }

    // =========================================================================
    // §47c — Isolation par domaine : histoire et science sont indépendants
    // =========================================================================

    public function test_domains_are_isolated(): void
    {
        // Créer une idée pour 'histoire'
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Empire romain');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Empereurs');
        $this->repo->persistPassIdea($s->id, 'Auguste');

        // 'science' doit rester vide
        $result = $this->repo->findFirstAvailableIdea(2, 'science');
        $this->assertNull($result);
    }

    // =========================================================================
    // §47d — peekNext() retourne dominant_idea_active (clé attendue par KPO)
    // =========================================================================

    public function test_peek_next_returns_dominant_idea_active_key(): void
    {
        // Injecter une idée directement en DB
        $sd = $this->repo->findOrCreateSubdomain(4, 'histoire', 'Guerre froide');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Protagonistes');
        $this->repo->persistPassIdea($s->id, 'Harry Truman');

        // Gemini ne doit pas être appelé
        $this->gemini->expects($this->never())->method('generateSubdomains');
        $this->gemini->expects($this->never())->method('generateIdeas');

        $result = $this->orchestrator->peekNext(4, 'histoire');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('dominant_idea_active', $result);
        $this->assertArrayHasKey('sub_domain', $result);
        $this->assertArrayHasKey('subject', $result);
        $this->assertSame('Harry Truman', $result['dominant_idea_active']);
        $this->assertSame('Guerre froide', $result['sub_domain']);
        $this->assertSame('Protagonistes', $result['subject']);
    }

    // =========================================================================
    // §47e — Mémoire cumulative : persistée en DB après un appel Gemini
    // =========================================================================

    public function test_cumulative_memory_persisted_after_gemini_call(): void
    {
        $this->mockGeminiFullPipeline(
            subdomain: 'Âge des découvertes',
            subject: 'Navigateurs',
            idea: 'Christophe Colomb',
        );

        $this->orchestrator->peekNext(2, 'histoire');

        // La mémoire IDEA doit avoir été créée
        $contextKey = TaxonomyBankRepository::ideaContextKey(2, 'histoire', 'Âge des découvertes', 'Navigateurs');
        $memory     = $this->repo->getCumulativeMemory('IDEA', $contextKey);

        $this->assertNotEmpty($memory);
        $this->assertSame(1, $memory[0]['attempt']);
    }

    // =========================================================================
    // §47f — INVARIANT CRITIQUE : diversité validée AVANT persistance
    //
    // Une idée rejetée pour SET_DIVERSITY_COLLISION ne doit JAMAIS être
    // stockée comme PASS+AVAILABLE dans la banque.
    // =========================================================================

    public function test_diversity_collision_never_persisted_as_pass(): void
    {
        // Gemini retourne 4 noms de personnages → la 4ème causerait SET_DIVERSITY_COLLISION
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Pères fondateurs']],
        ]);
        $this->gemini->method('generateSubjects')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Dirigeants']],
        ]);
        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [
                ['value' => 'John A. Macdonald'],
                ['value' => 'George-Étienne Cartier'],
                ['value' => 'Samuel Leonard Tilley'],
                ['value' => 'Charles Tupper'], // 4ème → 4/4 = 100% personnes → diversité FAIL
            ],
        ]);

        $this->orchestrator->peekNext(2, 'histoire');

        // Vérifier l'état en DB
        $sd = DB::table('taxonomy_subdomain_bank')
            ->where('subdomain_name', 'Pères fondateurs')
            ->first();
        $this->assertNotNull($sd, 'Le sous-domaine doit exister');

        $s = DB::table('taxonomy_subject_bank')
            ->where('subject_name', 'Dirigeants')
            ->first();
        $this->assertNotNull($s, 'Le sujet doit exister');

        // Compter les idées PASS+AVAILABLE dans la banque
        $passCount = DB::table('taxonomy_dominant_idea_bank')
            ->where('subject_id', $s->id)
            ->where('validation_status', 'PASS')
            ->where('status', 'AVAILABLE')
            ->count();

        // La diversité limite à ≤ 3 personnages (le 4ème cause FAIL)
        // Certaines implémentations peuvent avoir 0, 1, 2, ou 3 PASS (selon ordre de rejet)
        // mais JAMAIS 4 PASS quand 4/4 = 100% personnes
        $this->assertLessThan(4, $passCount,
            'Le 4ème nom de personnage ne doit pas être PASS+AVAILABLE en DB si diversité FAIL.'
        );

        // Vérifier qu'il n'y a pas de PASS row pour une idée qui a aussi un FAIL row identique
        $ideaValues = DB::table('taxonomy_dominant_idea_bank')
            ->where('subject_id', $s->id)
            ->get(['idea_value', 'validation_status', 'status'])
            ->toArray();

        $passValues = array_map(fn($r) => $r->idea_value,
            array_filter($ideaValues, fn($r) => $r->validation_status === 'PASS')
        );
        $failValues = array_map(fn($r) => $r->idea_value,
            array_filter($ideaValues, fn($r) => $r->validation_status === 'FAIL')
        );

        // Aucune valeur ne doit être à la fois PASS et FAIL
        $overlap = array_intersect($passValues, $failValues);
        $this->assertEmpty($overlap,
            'Une idée ne peut pas être à la fois PASS et FAIL dans la banque : '
            . implode(', ', $overlap)
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Configure le mock Gemini pour retourner un sous-domaine → sujet → idée valides.
     */
    private function mockGeminiFullPipeline(string $subdomain, string $subject, string $idea): void
    {
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => $subdomain]],
        ]);

        $this->gemini->method('generateSubjects')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => $subject]],
        ]);

        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => $idea]],
        ]);
    }

    /**
     * Crée le schéma SQLite en-mémoire pour tous les tests de cette classe.
     */
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
