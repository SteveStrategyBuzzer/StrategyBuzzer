<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use Tests\CreatesApplication;

/**
 * Tests §50–56 — TaxonomyOrchestrator::warmUpCell()
 *
 * Vérifie que warmUpCell() :
 *   - Pré-remplit la banque sans jamais consommer d'idées AVAILABLE
 *   - Cible uniquement les sujets qui n'ont aucune idée PASS+AVAILABLE
 *   - Est idempotent : les reruns ne diminuent jamais le compte d'idées disponibles
 *   - Retourne le nombre de sujets initialisés après l'appel
 *   - N'appelle Gemini que si nécessaire (cellule déjà à la cible → pas d'appel)
 *
 * PATTERN SQLite (jamais RefreshDatabase — ADD CONSTRAINT CHECK est PG-only) :
 *   setUp()    → Schema::create() toutes les tables
 *   tearDown() → Schema::dropIfExists() toutes les tables
 */
class TaxonomyOrchestratorWarmUpTest extends TestCase
{
    use CreatesApplication;

    private TaxonomyOrchestrator              $orchestrator;
    private TaxonomyBankRepository            $repo;
    private \PHPUnit\Framework\MockObject\MockObject&TaxonomyGeminiClient $gemini;
    private ValidationDominantIdeas           $validator;

    protected function setUp(): void
    {
        $this->createApplication();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();

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
        Schema::dropIfExists('taxonomy_generation_memory');
        Schema::dropIfExists('taxonomy_dominant_idea_bank');
        Schema::dropIfExists('taxonomy_subject_bank');
        Schema::dropIfExists('taxonomy_subdomain_bank');
    }

    // =========================================================================
    // §50 — Cellule vide → warmUpCell(target=1) → 1 sujet initialisé
    // =========================================================================

    public function test_warm_up_seeds_one_subject_for_empty_cell(): void
    {
        $this->mockGeminiPipeline('Révolution française', 'Causes', ['Crise économique']);

        $result = $this->orchestrator->warmUpCell(2, 'histoire', 1);

        $this->assertSame(1, $result, 'warmUpCell doit retourner 1 sujet initialisé');

        $availableCount = $this->countAvailableIdeas(2, 'histoire');
        $this->assertGreaterThanOrEqual(1, $availableCount, 'Au moins 1 idée PASS+AVAILABLE doit exister');
    }

    // =========================================================================
    // §51 — warmUpCell() ne consomme AUCUNE idée déjà disponible
    // =========================================================================

    public function test_warm_up_never_consumes_existing_available_ideas(): void
    {
        // Pré-remplir la banque directement avec 3 idées AVAILABLE
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Empire romain');
        $s  = $this->repo->findOrCreateSubject($sd->id, 'Empereurs');
        $this->repo->persistPassIdea($s->id, 'Auguste');
        $this->repo->persistPassIdea($s->id, 'Néron');
        $this->repo->persistPassIdea($s->id, 'Trajan');

        $availableBefore = $this->countAvailableIdeas(2, 'histoire');
        $this->assertSame(3, $availableBefore);

        // warmUpCell avec cible déjà atteinte → Gemini ne doit PAS être appelé
        $this->gemini->expects($this->never())->method('generateSubdomains');
        $this->gemini->expects($this->never())->method('generateSubjects');
        $this->gemini->expects($this->never())->method('generateIdeas');

        $result = $this->orchestrator->warmUpCell(2, 'histoire', 1);

        $availableAfter = $this->countAvailableIdeas(2, 'histoire');

        $this->assertSame(3, $availableAfter, 'Le nombre d\'idées AVAILABLE ne doit pas diminuer');
        $this->assertGreaterThanOrEqual(1, $result);
    }

    // =========================================================================
    // §52 — Idempotence : deux appels successifs ne diminuent pas le compte
    // =========================================================================

    public function test_warm_up_is_idempotent_on_rerun(): void
    {
        $this->mockGeminiPipeline('Guerre froide', 'Protagonistes', ['Truman', 'Churchill']);

        // Premier appel
        $this->orchestrator->warmUpCell(2, 'histoire', 1);
        $countAfterFirst = $this->countAvailableIdeas(2, 'histoire');

        // Deuxième appel — Gemini ne devrait plus être appelé (cible déjà atteinte)
        // On recrée le mock pour vérifier
        $freshGemini = $this->createMock(TaxonomyGeminiClient::class);
        $freshGemini->expects($this->never())->method('generateSubdomains');
        $freshGemini->expects($this->never())->method('generateSubjects');
        $freshGemini->expects($this->never())->method('generateIdeas');

        $orchestrator2 = new TaxonomyOrchestrator($this->repo, $freshGemini, $this->validator);
        $orchestrator2->warmUpCell(2, 'histoire', 1);

        $countAfterSecond = $this->countAvailableIdeas(2, 'histoire');

        $this->assertSame($countAfterFirst, $countAfterSecond,
            'Un second appel warmUpCell sur une cellule déjà initialisée ne doit pas modifier la banque'
        );
        $this->assertGreaterThan(0, $countAfterSecond);
    }

    // =========================================================================
    // §53 — target=2 → génère 2 sujets distincts (chacun avec ≥ 1 idée)
    // =========================================================================

    public function test_warm_up_seeds_two_distinct_subjects_for_target_two(): void
    {
        // Gemini génère un seul sous-domaine puis deux sujets différents
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Révolution française']],
        ]);

        $callCount = 0;
        $this->gemini->method('generateSubjects')->willReturnCallback(
            function () use (&$callCount) {
                $callCount++;
                $subject = $callCount === 1 ? 'Causes' : 'Conséquences';
                return [
                    'status'     => 'CANDIDATES',
                    'candidates' => [['value' => $subject]],
                ];
            }
        );

        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Idée principale']],
        ]);

        $result = $this->orchestrator->warmUpCell(2, 'histoire', 2);

        $this->assertSame(2, $result, 'warmUpCell(target=2) doit retourner 2 sujets initialisés');

        $seededSubjects = $this->repo->countSubjectsWithAvailableIdeas(2, 'histoire');
        $this->assertSame(2, $seededSubjects);

        // Aucune idée ne doit être CONSUMED
        $consumedCount = $this->countConsumedIdeas(2, 'histoire');
        $this->assertSame(0, $consumedCount,
            'warmUpCell ne doit JAMAIS marquer d\'idée comme CONSUMED'
        );
    }

    // =========================================================================
    // §54 — Cellule partiellement remplie : warmUpCell complète sans retoucher
    //        les idées déjà disponibles
    // =========================================================================

    public function test_warm_up_fills_gap_without_touching_existing_ideas(): void
    {
        // Sujet A déjà initialisé directement en DB
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $sA = $this->repo->findOrCreateSubject($sd->id, 'Grèce antique');
        $this->repo->persistPassIdea($sA->id, 'Démocratie athénienne');

        $this->assertSame(1, $this->repo->countSubjectsWithAvailableIdeas(2, 'histoire'));

        // Gemini génère le sujet B
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [], // Pas de nouveaux sous-domaines (Antiquité existe déjà)
        ]);
        $this->gemini->method('generateSubjects')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Rome antique']],
        ]);
        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'République romaine']],
        ]);

        $result = $this->orchestrator->warmUpCell(2, 'histoire', 2);

        $this->assertSame(2, $result);

        // L'idée initiale doit toujours être AVAILABLE (pas CONSUMED)
        $ideaStatus = DB::table('taxonomy_dominant_idea_bank')
            ->where('idea_value', 'Démocratie athénienne')
            ->value('status');
        $this->assertSame('AVAILABLE', $ideaStatus,
            'L\'idée existante ne doit pas être consommée par warmUpCell'
        );
    }

    // =========================================================================
    // §55 — Domaine épuisé → warmUpCell retourne le compte actuel sans boucle infinie
    // =========================================================================

    public function test_warm_up_returns_current_count_when_domain_exhausted(): void
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

        // Ne doit pas lancer d'exception ni boucler indéfiniment
        $result = $this->orchestrator->warmUpCell(2, 'histoire', 3);

        $this->assertSame(0, $result, 'Domaine épuisé → 0 sujet initialisé');

        // Aucune idée CONSUMED ne doit exister
        $this->assertSame(0, $this->countConsumedIdeas(2, 'histoire'));
    }

    // =========================================================================
    // §56b — Cible > MAX_FILL_ITERATIONS (32) : la boucle n'est pas plafonnée
    //         par le nombre total d'itérations mais par les échecs consécutifs.
    //
    // Si Gemini produit de vrais sujets à chaque appel, warmUpCell(target=40)
    // doit continuer jusqu'à atteindre 40 sujets initialisés (ou épuisement).
    // =========================================================================

    public function test_warm_up_can_reach_target_above_max_fill_iterations(): void
    {
        // Pre-insert one subdomain + 50 subjects directly — this bypasses Gemini
        // generation-attempt limits (MAX_SUBDOMAIN_GENERATION_ATTEMPTS = 3,
        // MAX_SUBJECT_GENERATION_ATTEMPTS = 3), which would otherwise cap the
        // reachable count at ~9. The test focus is the loop guard (noProgressStreak),
        // not subject/subdomain generation.
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Grand sous-domaine');
        for ($i = 1; $i <= 50; $i++) {
            $this->repo->findOrCreateSubject($sd->id, "Sujet pré-inséré {$i}");
        }

        // Only generateIdeas needs to be mocked — no subdomain/subject generation needed
        $this->gemini->expects($this->never())->method('generateSubdomains');
        $this->gemini->expects($this->never())->method('generateSubjects');
        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Idée principale']],
        ]);

        $target = 40; // well above MAX_FILL_ITERATIONS (32)
        $result = $this->orchestrator->warmUpCell(2, 'histoire', $target);

        $this->assertSame($target, $result,
            "warmUpCell(target={$target}) doit atteindre la cible même si elle dépasse MAX_FILL_ITERATIONS (32). "
            . "Le garde anti-boucle est basé sur les ÉCHECS consécutifs, pas sur le total d'itérations."
        );

        $dbCount = $this->repo->countSubjectsWithAvailableIdeas(2, 'histoire');
        $this->assertSame($dbCount, $result,
            'Le retour doit toujours égaler countSubjectsWithAvailableIdeas()'
        );

        // La garde anti-boucle ne doit pas avoir terminé prématurément
        $this->assertGreaterThanOrEqual(32, $result,
            'Le résultat doit dépasser MAX_FILL_ITERATIONS pour confirmer que le garde ne plafonne pas la progression'
        );

        // Aucune idée CONSUMED — seul warm-up, jamais de consommation
        $this->assertSame(0, $this->countConsumedIdeas(2, 'histoire'));
    }

    // =========================================================================
    // §56b-2 — Régression : sujet consommé à capacité → warmUpCell ne le compte pas
    //         comme initialisé et continue vers un nouveau sujet
    //
    // Scénario : le sujet A a 5 idées PASS mais toutes CONSUMED (suite au gameplay).
    // generateIdeasForSubject() retourne true pour ce sujet (il a des PASS historiques)
    // mais ne crée aucune idée AVAILABLE. warmUpCell() ne doit PAS le compter comme
    // initialisé et doit poursuivre pour créer un sujet B avec des idées AVAILABLE.
    // =========================================================================

    public function test_warm_up_does_not_count_consumed_at_capacity_subject_as_seeded(): void
    {
        // Sujet A : 5 idées PASS toutes CONSUMED, idea_generation_exhausted = false
        // (simule un sujet entièrement consommé par le gameplay avant exhaustion explicite)
        $sd = $this->repo->findOrCreateSubdomain(2, 'histoire', 'Antiquité');
        $sA = $this->repo->findOrCreateSubject($sd->id, 'Sujet consommé');

        foreach (['Idée 1', 'Idée 2', 'Idée 3', 'Idée 4', 'Idée 5'] as $v) {
            $this->repo->persistPassIdea($sA->id, $v);
        }
        // Marquer toutes les idées comme CONSUMED (simuler le gameplay)
        DB::table('taxonomy_dominant_idea_bank')
            ->where('subject_id', $sA->id)
            ->update(['status' => 'CONSUMED', 'updated_at' => now()]);

        // À ce stade : 0 idée AVAILABLE, idea_generation_exhausted = false
        $this->assertSame(0, $this->countAvailableIdeas(2, 'histoire'));
        $this->assertSame(0, $this->repo->countSubjectsWithAvailableIdeas(2, 'histoire'));

        // Gemini génère un deuxième sujet (B) avec une idée disponible
        $this->gemini->method('generateSubdomains')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [],  // Antiquité existe déjà
        ]);
        $this->gemini->method('generateSubjects')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Nouveau sujet']],
        ]);
        $this->gemini->method('generateIdeas')->willReturn([
            'status'     => 'CANDIDATES',
            'candidates' => [['value' => 'Nouvelle idée']],
        ]);

        $result = $this->orchestrator->warmUpCell(2, 'histoire', 1);

        // Le résultat doit être 1 (sujet B avec idées disponibles)
        $this->assertSame(1, $result,
            'warmUpCell doit retourner le compte DB réel de sujets avec idées AVAILABLE'
        );

        // Vérifier que le résultat == countSubjectsWithAvailableIdeas (cohérence)
        $dbCount = $this->repo->countSubjectsWithAvailableIdeas(2, 'histoire');
        $this->assertSame($dbCount, $result,
            'Le retour de warmUpCell doit toujours égaler countSubjectsWithAvailableIdeas()'
        );

        // Le sujet consommé ne doit pas avoir d'idées AVAILABLE ajoutées
        $consumedSubjectAvailable = DB::table('taxonomy_dominant_idea_bank')
            ->where('subject_id', $sA->id)
            ->where('status', 'AVAILABLE')
            ->count();
        $this->assertSame(0, $consumedSubjectAvailable,
            'warmUpCell ne doit pas créer d\'idées AVAILABLE sur un sujet consommé à capacité'
        );
    }

    // =========================================================================
    // §56c — Invariant : warmUpCell() retourne toujours countSubjectsWithAvailableIdeas()
    // =========================================================================

    public function test_warm_up_return_always_equals_db_available_count(): void
    {
        $this->mockGeminiPipeline('Période médiévale', 'Chevalerie', ['Code d\'honneur']);

        $result  = $this->orchestrator->warmUpCell(2, 'histoire', 1);
        $dbCount = $this->repo->countSubjectsWithAvailableIdeas(2, 'histoire');

        $this->assertSame($dbCount, $result,
            'Le retour de warmUpCell doit toujours être égal à countSubjectsWithAvailableIdeas()'
        );
    }

    // =========================================================================
    // §56 — Isolation des domaines : warmUpCell('histoire') ne touche pas 'science'
    // =========================================================================

    public function test_warm_up_does_not_affect_other_domains(): void
    {
        // Pré-remplir 'science'
        $sdSci = $this->repo->findOrCreateSubdomain(2, 'science', 'Physique');
        $sSci  = $this->repo->findOrCreateSubject($sdSci->id, 'Mécanique quantique');
        $this->repo->persistPassIdea($sSci->id, 'Principe d\'incertitude');

        $scienceCountBefore = $this->countAvailableIdeas(2, 'science');
        $this->assertSame(1, $scienceCountBefore);

        // Warm-up pour 'histoire'
        $this->mockGeminiPipeline('Révolution française', 'Causes', ['Crise économique']);
        $this->orchestrator->warmUpCell(2, 'histoire', 1);

        // 'science' doit rester intact
        $scienceCountAfter = $this->countAvailableIdeas(2, 'science');
        $this->assertSame($scienceCountBefore, $scienceCountAfter,
            'warmUpCell sur histoire ne doit pas affecter les idées de science'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function mockGeminiPipeline(string $subdomain, string $subject, array $ideas): void
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
            'candidates' => array_map(fn($i) => ['value' => $i], $ideas),
        ]);
    }

    private function countAvailableIdeas(int $depth, string $domainCode): int
    {
        return (int) DB::table('taxonomy_dominant_idea_bank as i')
            ->join('taxonomy_subject_bank as s', 's.id', '=', 'i.subject_id')
            ->join('taxonomy_subdomain_bank as sd', 'sd.id', '=', 's.subdomain_id')
            ->where('sd.depth', $depth)
            ->where('sd.domain_code', $domainCode)
            ->where('i.validation_status', 'PASS')
            ->where('i.status', 'AVAILABLE')
            ->count();
    }

    private function countConsumedIdeas(int $depth, string $domainCode): int
    {
        return (int) DB::table('taxonomy_dominant_idea_bank as i')
            ->join('taxonomy_subject_bank as s', 's.id', '=', 'i.subject_id')
            ->join('taxonomy_subdomain_bank as sd', 'sd.id', '=', 's.subdomain_id')
            ->where('sd.depth', $depth)
            ->where('sd.domain_code', $domainCode)
            ->where('i.status', 'CONSUMED')
            ->count();
    }

    private function createSchema(): void
    {
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('taxonomy_subdomain_bank', function ($table) {
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

        Schema::create('taxonomy_subject_bank', function ($table) {
            $table->id();
            $table->foreignId('subdomain_id')->constrained('taxonomy_subdomain_bank')->cascadeOnDelete();
            $table->string('subject_name', 256);
            $table->string('status', 16)->default('AVAILABLE');
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);
            $table->boolean('idea_generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['subdomain_id', 'subject_name']);
        });

        Schema::create('taxonomy_dominant_idea_bank', function ($table) {
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

        Schema::create('taxonomy_generation_memory', function ($table) {
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
