<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyConfig;
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
    // §48 — Survie au redémarrage : une idée consommée n'est jamais re-servie
    //        après instanciation d'un orchestrateur tout neuf
    //
    // Simule un redémarrage de processus : même DB (SQLite persistante dans
    // le même test), nouvel objet TaxonomyOrchestrator — aucun état in-memory
    // partagé entre les deux instances.
    // =========================================================================

    public function test_consumed_idea_not_returned_after_process_restart(): void
    {
        // Pré-peupler DEUX idées pour le même sujet (A puis B)
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Renaissance');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Artistes');
        $this->repo->persistPassIdea($s->id, 'Léonard de Vinci');
        $this->repo->persistPassIdea($s->id, 'Michel-Ange');

        // ── Instance 1 (avant le "redémarrage") ─────────────────────────────
        $result1 = $this->orchestrator->peekNext(2, 'histoire');
        $this->assertNotNull($result1);
        $ideaA = $result1['dominant_idea_active'];

        // Consommer l'idée A
        $this->orchestrator->confirmConsumed(2, 'histoire');

        // ── Instance 2 — simule un redémarrage de processus ─────────────────
        // Gemini ne doit pas être appelé car une idée PASS+AVAILABLE reste en DB
        $gemini2 = $this->createMock(TaxonomyGeminiClient::class);
        $gemini2->expects($this->never())->method('generateSubdomains');
        $gemini2->expects($this->never())->method('generateIdeas');

        $freshOrchestrator = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            $gemini2,
            new ValidationDominantIdeas(),
        );

        $result2 = $freshOrchestrator->peekNext(2, 'histoire');

        // Le fresh orchestrateur doit retourner une idée…
        $this->assertNotNull($result2,
            'Un orchestrateur tout neuf doit trouver l\'idée B encore disponible en DB.'
        );

        // …et ce ne doit PAS être l'idée déjà consommée
        $ideaB = $result2['dominant_idea_active'];
        $this->assertNotSame($ideaA, $ideaB,
            "L'idée «{$ideaA}» a été consommée avant le redémarrage ; "
            . "elle ne doit jamais être re-servie par un nouvel orchestrateur."
        );
    }

    // =========================================================================
    // §49 — Anti-régression : zéro chevauchement sur 10 cycles consécutifs
    //
    // Pré-peuple 10 idées, simule 10 "redémarrages" (un orchestrateur neuf
    // par cycle), vérifie qu'aucune idée n'est retournée deux fois.
    // =========================================================================

    public function test_no_overlap_across_ten_restart_cycles(): void
    {
        // Pré-peupler 10 idées dans la même banque (depth=4, domain=histoire)
        $sd = $this->repo->findOrCreateSubdomain(4, 'histoire', 'Révolution industrielle');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Inventions clés');

        $allIdeas = [
            'Machine à vapeur',
            'Métier à tisser mécanique',
            'Locomotive à vapeur',
            'Procédé Bessemer',
            'Dynamo électrique',
            'Télégraphe électrique',
            'Machine à coudre industrielle',
            'Laminoir',
            'Pompe à vapeur de Watt',
            'Réfrigérateur à compression',
        ];

        foreach ($allIdeas as $idea) {
            $this->repo->persistPassIdea($s->id, $idea);
        }

        $returned = [];

        for ($cycle = 1; $cycle <= 10; $cycle++) {
            // Chaque itération crée un orchestrateur neuf (= redémarrage processus)
            // Gemini ne doit jamais être appelé : toutes les idées viennent de la DB
            $gemini = $this->createMock(TaxonomyGeminiClient::class);
            $gemini->expects($this->never())->method('generateSubdomains');
            $gemini->expects($this->never())->method('generateIdeas');

            $freshOrchestrator = new TaxonomyOrchestrator(
                new TaxonomyBankRepository(),
                $gemini,
                new ValidationDominantIdeas(),
            );

            $result = $freshOrchestrator->peekNext(4, 'histoire');

            $this->assertNotNull($result,
                "Cycle {$cycle}/10 : l'orchestrateur doit trouver une idée AVAILABLE en DB."
            );

            $idea = $result['dominant_idea_active'];

            $this->assertNotContains($idea, $returned,
                "Cycle {$cycle}/10 : l'idée «{$idea}» a déjà été retournée dans un cycle précédent "
                . '(chevauchement interdit).'
            );

            $returned[] = $idea;

            // Consommer via le même orchestrateur frais (confirmConsumed est DB-only)
            $freshOrchestrator->confirmConsumed(4, 'histoire');
        }

        // Garantie finale : exactement 10 idées distinctes retournées
        $this->assertCount(10, $returned,
            '10 cycles doivent produire 10 idées distinctes, sans répétition.'
        );
        $this->assertCount(10, array_unique($returned),
            'Aucune idée ne doit apparaître plus d\'une fois sur les 10 cycles.'
        );
    }

    // =========================================================================
    // §50 — warnIfZeroPass : warning émis quand toutes les idées sont FAIL
    //
    // Strategy: bypass validation entirely by pre-seeding FAIL rows directly
    // in DB via repo, then mock Gemini to return NO_MORE_IDEAS + empty candidates.
    // This gives totalFail > 0 and totalPass = 0 → warning fires reliably,
    // independent of what ValidationDominantIdeas decides about any specific value.
    // =========================================================================

    public function test_warning_logged_when_subject_exhausted_with_only_fail_ideas(): void
    {
        // Pre-seed subdomain + subject + FAIL ideas directly in DB.
        // This bypasses ValidationDominantIdeas entirely so the test is not
        // coupled to which strings the validator accepts or rejects.
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Époque médiévale');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Croisades');
        $this->repo->persistFailIdea($s->id, 'Idée invalide A', 'FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION', null);
        $this->repo->persistFailIdea($s->id, 'Idée invalide B', 'GENERIC_CATEGORY', null);

        // Gemini returns NO_MORE_IDEAS with empty candidates so no new ideas
        // are processed.  failDetails loads 2 FAILs from DB, newFailDetails
        // stays empty → totalPass=0, totalFail=2 → warnIfZeroPass fires.
        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'NO_MORE_IDEAS',
            'candidates' => [],
        ]);
        // Stop the fillBank() loop cleanly after subject exhaustion
        $this->gemini->method('generateSubjects')->willReturn([
            'status'     => 'NO_MORE_SUBJECTS',
            'candidates' => [],
        ]);
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'NO_MORE_SUBDOMAINS',
            'candidates' => [],
        ]);

        $handler = $this->pushTestLogHandler();
        $this->orchestrator->peekNext(2, 'histoire');

        $warnings = $this->filterWarnings($handler, 'taxonomy.subject_exhausted_with_zero_pass');
        $this->assertNotEmpty($warnings, 'Expected warning to be logged when subject exhausted with only FAILs.');

        $ctx = $warnings[0]['context'];
        $this->assertSame('histoire', $ctx['domain_code']);
        $this->assertSame('Croisades', $ctx['subject_name']);
        $this->assertGreaterThan(0, $ctx['fail_count']);
        $this->assertArrayHasKey('depth', $ctx);
        $this->assertArrayHasKey('subdomain_name', $ctx);
        $this->assertArrayHasKey('subject_id', $ctx);
        $this->assertArrayHasKey('attempt_number', $ctx);
        $this->assertArrayHasKey('message', $ctx);
    }

    public function test_warning_logged_on_early_return_path_when_max_attempts_already_reached(): void
    {
        // Simulate MAX_ATTEMPTS_ALREADY_REACHED: 3 memory entries already stored
        // so getNextAttemptNumber returns MAX+1 → early-return branch.
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Monde antique');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Cités grecques');

        $this->repo->persistFailIdea($s->id, 'mauvaise idée A', 'FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION', null);
        $this->repo->persistFailIdea($s->id, 'mauvaise idée B', 'GENERIC_CATEGORY', null);

        $contextKey = TaxonomyBankRepository::ideaContextKey(
            2, 'histoire', 'Monde antique', 'Cités grecques'
        );
        for ($i = 1; $i <= TaxonomyConfig::MAX_DOMINANT_IDEA_GENERATION_ATTEMPTS; $i++) {
            $this->repo->persistMemoryEntry(
                'IDEA', $contextKey, $i,
                ['mauvaise idée A', 'mauvaise idée B'],
                [],
                [['value' => 'mauvaise idée A', 'reason' => 'FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION', 'conflict_with' => null]],
                [],
                false
            );
        }

        // Mark subdomain generation_exhausted so fillBank() reaches
        // generateNewSubdomain() (not generateNewSubject()) after early-return.
        DB::table('taxonomy_subdomain_bank')
            ->where('id', $sd->id)
            ->update(['generation_exhausted' => true]);

        // generateIdeas must NOT be called on the early-return path
        $this->gemini->expects($this->never())->method('generateIdeas');
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'NO_MORE_SUBDOMAINS',
            'candidates' => [],
        ]);

        $handler = $this->pushTestLogHandler();
        $this->orchestrator->peekNext(2, 'histoire');

        $warnings = $this->filterWarnings($handler, 'taxonomy.subject_exhausted_with_zero_pass');
        $this->assertNotEmpty($warnings, 'Expected warning on MAX_ATTEMPTS_ALREADY_REACHED path.');

        $ctx = $warnings[0]['context'];
        $this->assertSame('histoire', $ctx['domain_code']);
        $this->assertSame('Cités grecques', $ctx['subject_name']);
        $this->assertSame('MAX_ATTEMPTS_ALREADY_REACHED', $ctx['status']);
        $this->assertGreaterThan(0, $ctx['fail_count']);
    }

    // =========================================================================
    // §51 — warnIfZeroPass : PAS de warning quand ≥1 idée PASS générée
    // =========================================================================

    public function test_no_warning_when_subject_has_at_least_one_pass_idea(): void
    {
        $this->mockGeminiFullPipeline(
            subdomain: 'Révolution industrielle',
            subject: 'Inventeurs',
            idea: 'James Watt',
        );

        $handler = $this->pushTestLogHandler();
        $result  = $this->orchestrator->peekNext(2, 'histoire');

        $this->assertNotNull($result);
        $this->assertSame('James Watt', $result['dominant_idea_active']);

        $warnings = $this->filterWarnings($handler, 'taxonomy.subject_exhausted_with_zero_pass');
        $this->assertEmpty($warnings, 'No warning should be logged when at least one PASS idea was generated.');
    }

    // =========================================================================
    // §52 — TaxonomyBankRepository::findExhaustedWithOnlyFails()
    // =========================================================================

    public function test_find_exhausted_with_only_fails_returns_subjects_with_no_pass(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Pharaons');

        // Marquer épuisé + persister 2 FAILs, 0 PASS
        $this->repo->persistFailIdea($s->id, 'Toutankhamon ?', 'INVALID_CHAR', null);
        $this->repo->persistFailIdea($s->id, '??', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        $rows = $this->repo->findExhaustedWithOnlyFails(minFails: 1);

        $this->assertCount(1, $rows);
        $this->assertSame('Pharaons', $rows[0]->subject_name);
        $this->assertSame('Antiquité', $rows[0]->subdomain_name);
        $this->assertSame(2, (int) $rows[0]->fail_count);
    }

    public function test_find_exhausted_with_only_fails_excludes_subjects_with_pass_ideas(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Pharaons');

        // 1 PASS + 1 FAIL → doit être EXCLU (le sujet a produit du bon contenu)
        $this->repo->persistPassIdea($s->id, 'Ramsès II');
        $this->repo->persistFailIdea($s->id, '??', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        $rows = $this->repo->findExhaustedWithOnlyFails(minFails: 1);

        $this->assertCount(0, $rows, 'Un sujet avec ≥1 PASS ne doit pas apparaître dans le rapport d\'alerte.');
    }

    public function test_find_exhausted_with_only_fails_excludes_non_exhausted_subjects(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Pharaons');

        // 2 FAILs mais PAS marqué exhausted → doit être EXCLU
        $this->repo->persistFailIdea($s->id, '?', 'TOO_SHORT', null);
        $this->repo->persistFailIdea($s->id, '??', 'TOO_SHORT', null);
        // idea_generation_exhausted reste false

        $rows = $this->repo->findExhaustedWithOnlyFails(minFails: 1);

        $this->assertCount(0, $rows, 'Un sujet non-exhausted ne doit pas apparaître même avec des FAILs.');
    }

    public function test_find_exhausted_with_only_fails_respects_min_fails_threshold(): void
    {
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Pharaons');

        // 1 FAIL uniquement
        $this->repo->persistFailIdea($s->id, '?', 'TOO_SHORT', null);
        DB::table('taxonomy_subject_bank')->where('id', $s->id)->update(['idea_generation_exhausted' => true]);

        // minFails=1 → inclus
        $this->assertCount(1, $this->repo->findExhaustedWithOnlyFails(minFails: 1));
        // minFails=2 → exclu (seulement 1 FAIL)
        $this->assertCount(0, $this->repo->findExhaustedWithOnlyFails(minFails: 2));
        // minFails=3 → exclu
        $this->assertCount(0, $this->repo->findExhaustedWithOnlyFails(minFails: 3));
    }

    // =========================================================================
    // §53 — Deux consommateurs concurrents sur le même (depth, domain)
    //
    // Simule Duo/Ligue : deux processus appellent peekNext() AVANT que l'un
    // d'eux appelle confirmConsumed(). Le test est séquentiel (SQLite ne
    // supporte pas les verrous ligne), mais il modélise fidèlement le
    // comportement sous PostgreSQL grâce au SELECT … FOR UPDATE dans
    // claimFirstAvailableIdea() : la seconde transaction attend le COMMIT de
    // la première, puis re-lit et tombe sur l'idée suivante.
    //
    // Vérifie que :
    //   a) Les deux peeks voient le même triplet (idempotence de l'ordre stable)
    //   b) Le premier confirmConsumed() marque l'idée A comme CONSUMED
    //   c) Le second confirmConsumed() ré-évalue claimFirstAvailableIdea()
    //      DANS sa transaction et tombe sur l'idée B (pas sur A déjà CONSUMED)
    //   d) Au final : 0 idée AVAILABLE, 2 idées CONSUMED, aucun doublon
    // =========================================================================

    public function test_concurrent_consumers_each_consume_distinct_idea(): void
    {
        // Pré-peupler DEUX idées dans le même bucket (depth=2, domain=histoire)
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Moyen Âge');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Chevaliers');
        $ideaRowA = $this->repo->persistPassIdea($s->id, 'Lancelot du Lac');
        $ideaRowB = $this->repo->persistPassIdea($s->id, 'Godefroy de Bouillon');

        // ── Consommateur A : peekNext() ──────────────────────────────────────
        $geminiA = $this->createMock(TaxonomyGeminiClient::class);
        $geminiA->expects($this->never())->method('generateSubdomains');
        $geminiA->expects($this->never())->method('generateIdeas');

        $orchestratorA = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            $geminiA,
            new ValidationDominantIdeas(),
        );

        $peekA = $orchestratorA->peekNext(2, 'histoire');
        $this->assertNotNull($peekA, 'Consumer A doit trouver une idée disponible.');

        // ── Consommateur B : peekNext() AVANT que A consomme ─────────────────
        $geminiB = $this->createMock(TaxonomyGeminiClient::class);
        $geminiB->expects($this->never())->method('generateSubdomains');
        $geminiB->expects($this->never())->method('generateIdeas');

        $orchestratorB = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            $geminiB,
            new ValidationDominantIdeas(),
        );

        $peekB = $orchestratorB->peekNext(2, 'histoire');
        $this->assertNotNull($peekB, 'Consumer B doit aussi trouver une idée disponible.');

        // Les deux peeks doivent retourner le MÊME triplet (ordre stable, idempotent)
        $this->assertSame(
            $peekA['dominant_idea_active'],
            $peekB['dominant_idea_active'],
            'Les deux peeks avant consommation doivent retourner la même idée '
            . '(garantie d\'idempotence sur l\'ordre stable).'
        );
        $this->assertSame('Lancelot du Lac', $peekA['dominant_idea_active'],
            'La première idée (ID le plus bas) doit être retournée en premier.'
        );

        // ── Consumer A consomme en premier ───────────────────────────────────
        $orchestratorA->confirmConsumed(2, 'histoire');

        // L'idée A doit maintenant être CONSUMED en DB
        $ideaAStatus = DB::table('taxonomy_dominant_idea_bank')
            ->where('id', $ideaRowA->id)
            ->value('status');
        $this->assertSame('CONSUMED', $ideaAStatus,
            'Après confirmConsumed() par A, l\'idée A doit être CONSUMED.'
        );

        // ── Consumer B consomme à son tour ───────────────────────────────────
        // La transaction ré-évalue findFirstAvailableIdea → tombe sur l'idée B
        $orchestratorB->confirmConsumed(2, 'histoire');

        // ── Assertions finales ───────────────────────────────────────────────

        // Plus aucune idée AVAILABLE
        $availableCount = DB::table('taxonomy_dominant_idea_bank')
            ->join('taxonomy_subject_bank as s', 's.id', '=', 'taxonomy_dominant_idea_bank.subject_id')
            ->join('taxonomy_subdomain_bank as sd', 'sd.id', '=', 's.subdomain_id')
            ->where('sd.depth', 2)
            ->where('sd.domain_code', 'histoire')
            ->where('taxonomy_dominant_idea_bank.validation_status', 'PASS')
            ->where('taxonomy_dominant_idea_bank.status', 'AVAILABLE')
            ->count();

        $this->assertSame(0, $availableCount,
            'Aucune idée ne doit rester AVAILABLE après que les deux consommateurs ont confirmé.'
        );

        // Exactement 2 idées CONSUMED
        $consumedCount = DB::table('taxonomy_dominant_idea_bank')
            ->join('taxonomy_subject_bank as s', 's.id', '=', 'taxonomy_dominant_idea_bank.subject_id')
            ->join('taxonomy_subdomain_bank as sd', 'sd.id', '=', 's.subdomain_id')
            ->where('sd.depth', 2)
            ->where('sd.domain_code', 'histoire')
            ->where('taxonomy_dominant_idea_bank.validation_status', 'PASS')
            ->where('taxonomy_dominant_idea_bank.status', 'CONSUMED')
            ->count();

        $this->assertSame(2, $consumedCount,
            'Les deux idées distinctes doivent être CONSUMED : chaque confirmConsumed() '
            . 'doit avoir touché une idée différente.'
        );

        // Idée B (Godefroy) est aussi CONSUMED (consommée par B)
        $ideaBStatus = DB::table('taxonomy_dominant_idea_bank')
            ->where('id', $ideaRowB->id)
            ->value('status');
        $this->assertSame('CONSUMED', $ideaBStatus,
            'L\'idée B doit être CONSUMED : le second confirmConsumed() doit avoir '
            . 'ré-évalué findFirstAvailableIdea et touché l\'idée suivante.'
        );
    }

    // =========================================================================
    // §51 — Deux consommateurs, une seule idée disponible
    //
    // Cas extrême : une seule idée dans la banque. Les deux peeks retournent
    // la même idée. Le premier confirmConsumed() la marque CONSUMED. Le second
    // confirmConsumed() est un no-op (findFirstAvailableIdea retourne null
    // dans la transaction car l'idée est déjà CONSUMED).
    // Garantit : exactement 1 idée CONSUMED, pas de doublon.
    // =========================================================================

    public function test_concurrent_consumers_single_idea_second_confirm_is_noop(): void
    {
        // Pré-peupler UNE SEULE idée
        $sd = $this->repo->findOrCreateSubdomain(2, 'science', 'Physique');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Lois fondamentales');
        $ideaRow = $this->repo->persistPassIdea($s->id, 'Loi de la gravitation universelle');

        // Consumer A peeks
        $orchestratorA = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            $this->createMock(TaxonomyGeminiClient::class),
            new ValidationDominantIdeas(),
        );
        $peekA = $orchestratorA->peekNext(2, 'science');
        $this->assertNotNull($peekA);
        $this->assertSame('Loi de la gravitation universelle', $peekA['dominant_idea_active']);

        // Consumer B peeks (même idée, idempotent)
        $orchestratorB = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            $this->createMock(TaxonomyGeminiClient::class),
            new ValidationDominantIdeas(),
        );
        $peekB = $orchestratorB->peekNext(2, 'science');
        $this->assertNotNull($peekB);
        $this->assertSame($peekA['dominant_idea_active'], $peekB['dominant_idea_active'],
            'Avec une seule idée, les deux peeks doivent retourner la même valeur.'
        );

        // Consumer A consomme
        $orchestratorA->confirmConsumed(2, 'science');

        $this->assertSame('CONSUMED',
            DB::table('taxonomy_dominant_idea_bank')->where('id', $ideaRow->id)->value('status'),
            'Après confirmConsumed() par A, l\'idée unique doit être CONSUMED.'
        );

        // Consumer B tente de consommer → no-op (plus rien d'AVAILABLE)
        $orchestratorB->confirmConsumed(2, 'science'); // ne doit pas lancer d'exception

        // Vérification : toujours 1 seul CONSUMED, pas de doublon
        $consumedCount = DB::table('taxonomy_dominant_idea_bank')
            ->where('subject_id', $s->id)
            ->where('status', 'CONSUMED')
            ->count();

        $this->assertSame(1, $consumedCount,
            'Avec une seule idée, le second confirmConsumed() doit être un no-op : '
            . 'exactement 1 idée CONSUMED, pas 2.'
        );

        // markIdeaConsumed est idempotent grâce à ->where(status, AVAILABLE)
        // L'idée ne doit pas avoir été dupliquée ou corrompue
        $totalRows = DB::table('taxonomy_dominant_idea_bank')
            ->where('subject_id', $s->id)
            ->count();
        $this->assertSame(1, $totalRows,
            'Aucune ligne supplémentaire ne doit avoir été créée par le second confirmConsumed().'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    // =========================================================================
    // Log-capture helpers
    //
    // Log::fake() requires Illuminate\Foundation\Testing\TestCase and is not
    // available in PHPUnit\Framework\TestCase + CreatesApplication.  Instead we
    // push a Monolog TestHandler directly onto the active log channel's
    // underlying Monolog logger so we can inspect captured records.
    // =========================================================================

    /**
     * Pushes a Monolog TestHandler onto the active log channel.
     * Returns the handler so the caller can inspect records after the act.
     */
    private function pushTestLogHandler(): \Monolog\Handler\TestHandler
    {
        // $this->app is not set in PHPUnit\Framework\TestCase — use the global
        // app() helper which resolves from the same IoC container bootstrapped
        // by CreatesApplication::createApplication().
        /** @var \Illuminate\Log\Logger $illuminateLogger */
        $illuminateLogger = app('log')->driver();

        /** @var \Monolog\Logger $monolog */
        $monolog = $illuminateLogger->getLogger();

        $handler = new \Monolog\Handler\TestHandler();
        $monolog->pushHandler($handler);

        return $handler;
    }

    /**
     * Returns records from $handler whose level is WARNING and whose message
     * matches $message exactly.
     *
     * @return array<array{level: \Monolog\Level, message: string, context: array}>
     */
    private function filterWarnings(\Monolog\Handler\TestHandler $handler, string $message): array
    {
        // In Monolog 3, TestHandler::getRecords() returns LogRecord objects that
        // implement ArrayAccess. Accessing $r['level'] via ArrayAccess returns the
        // integer value (e.g. 300), NOT the Level enum. Use $r->level (the public
        // readonly property) to get the actual Level enum for comparison.
        return array_values(array_filter(
            $handler->getRecords(),
            fn ($r) => $r->level === \Monolog\Level::Warning && $r->message === $message
        ));
    }

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
