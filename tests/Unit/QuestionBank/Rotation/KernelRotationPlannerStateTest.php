<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\DomainExhaustionChecker;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests de la machine d'état persistante de KernelRotationPlanner.
 *
 * DB : SQLite in-memory (Tests\TestCase).
 * PAS de RefreshDatabase — tables créées/détruites manuellement.
 *
 * Les tables kernel_rotation_state et question_groups (minimale) sont
 * créées dans setUp() et détruites dans tearDown().
 *
 * DomainExhaustionChecker est fourni sous forme de classe anonyme :
 * les tests contrôlent exactement ce que isExhausted() retourne.
 */
class KernelRotationPlannerStateTest extends TestCase
{
    private KernelRotationPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('kernel_rotation_state', function (Blueprint $table) {
            $table->id();
            $table->integer('current_depth');
            $table->integer('current_domain_index')->default(0);
            $table->integer('completed_domains')->default(0);
            $table->string('last_rotation_identifier')->nullable();
            $table->timestamps();
        });

        // Table minimale pour loadExistingKernelCounts() — utilisée lors du passage de depth
        Schema::create('question_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('difficulty_depth')->nullable();
        });

        $this->planner = new KernelRotationPlanner();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_rotation_state');
        Schema::dropIfExists('question_groups');
        parent::tearDown();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Crée un DomainExhaustionChecker qui retourne toujours $returnValue.
     */
    private function makeChecker(bool $returnValue): DomainExhaustionChecker
    {
        return new class($returnValue) implements DomainExhaustionChecker {
            public function __construct(private readonly bool $value) {}

            public function isExhausted(int $depth, string $domainCode): bool
            {
                return $this->value;
            }
        };
    }

    /**
     * Crée un checker qui retourne true uniquement pour les domaines listés.
     *
     * @param  string[]  $exhaustedDomains
     */
    private function makeCheckerForDomains(array $exhaustedDomains): DomainExhaustionChecker
    {
        return new class($exhaustedDomains) implements DomainExhaustionChecker {
            public function __construct(private readonly array $domains) {}

            public function isExhausted(int $depth, string $domainCode): bool
            {
                return in_array($domainCode, $this->domains, true);
            }
        };
    }

    private function insertState(int $depth, int $domainIndex, int $completedDomains): void
    {
        DB::table('kernel_rotation_state')->insert([
            'current_depth'            => $depth,
            'current_domain_index'     => $domainIndex,
            'completed_domains'        => $completedDomains,
            'last_rotation_identifier' => null,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    private function loadState(): object
    {
        return DB::table('kernel_rotation_state')->first();
    }

    // =========================================================================
    // Test 1 — plan() lève STOP si état non initialisé
    // =========================================================================

    public function test_plan_throws_stop_when_state_not_initialized(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/STOP.*non initialisé/');

        $this->planner->plan($this->makeChecker(false));
    }

    // =========================================================================
    // Test 2 — initialize() crée l'état avec le depth fourni
    // =========================================================================

    public function test_initialize_inserts_state_with_given_depth(): void
    {
        $this->planner->initialize(4);

        $state = $this->loadState();

        $this->assertNotNull($state);
        $this->assertSame(4, (int) $state->current_depth);
        $this->assertSame(0, (int) $state->current_domain_index);
        $this->assertSame(0, (int) $state->completed_domains);
    }

    // =========================================================================
    // Test 3 — initialize() est idempotente
    // =========================================================================

    public function test_initialize_is_idempotent(): void
    {
        $this->planner->initialize(4);
        $this->planner->initialize(6); // second call — doit être ignoré

        $count = DB::table('kernel_rotation_state')->count();
        $state = $this->loadState();

        $this->assertSame(1, $count, 'Une seule ligne doit exister');
        $this->assertSame(4, (int) $state->current_depth, 'Le depth initial est conservé');
    }

    // =========================================================================
    // Test 4 — plan() retourne depth + domain_code + rotation_identifier
    // =========================================================================

    public function test_plan_returns_depth_domain_rotation_identifier(): void
    {
        $this->insertState(depth: 4, domainIndex: 0, completedDomains: 0);

        $result = $this->planner->plan($this->makeChecker(false));

        $this->assertArrayHasKey('rotation_context', $result);
        $ctx = $result['rotation_context'];

        $this->assertArrayHasKey('depth_slot',          $ctx);
        $this->assertArrayHasKey('domain_slot',         $ctx);
        $this->assertArrayHasKey('rotation_identifier', $ctx);

        $this->assertSame(4, $ctx['depth_slot']['depth']);
        $this->assertSame('histoire', $ctx['domain_slot']['domain_code']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $ctx['rotation_identifier'],
            'rotation_identifier doit être un UUID'
        );
    }

    // =========================================================================
    // Test 5 — domaine non épuisé → même domaine conservé
    // =========================================================================

    public function test_plan_keeps_same_domain_when_not_exhausted(): void
    {
        $this->insertState(depth: 4, domainIndex: 2, completedDomains: 1); // sport, idx=2

        $this->planner->plan($this->makeChecker(false));

        $state = $this->loadState();

        $this->assertSame(2, (int) $state->current_domain_index, 'Index domaine inchangé');
        $this->assertSame(1, (int) $state->completed_domains, 'completed_domains inchangé');
    }

    // =========================================================================
    // Test 6 — domaine épuisé → domaine suivant, completed_domains++
    // =========================================================================

    public function test_plan_advances_domain_when_exhausted(): void
    {
        $this->insertState(depth: 4, domainIndex: 0, completedDomains: 0); // histoire, idx=0

        $this->planner->plan($this->makeChecker(true)); // histoire = EXHAUSTED

        $state = $this->loadState();

        $this->assertSame(1, (int) $state->current_domain_index, 'Avancé à index 1 (geographie)');
        $this->assertSame(1, (int) $state->completed_domains,    'completed_domains = 1');
    }

    // =========================================================================
    // Test 7 — plan() retourne le NOUVEAU domaine après avancement
    // =========================================================================

    public function test_plan_returns_new_domain_after_exhaustion(): void
    {
        $this->insertState(depth: 4, domainIndex: 0, completedDomains: 0); // histoire

        $result = $this->planner->plan($this->makeChecker(true)); // histoire exhausted

        $this->assertSame('geographie', $result['rotation_context']['domain_slot']['domain_code']);
    }

    // =========================================================================
    // Test 8 — completed_domains s'incrémente à chaque domaine épuisé
    // =========================================================================

    public function test_completed_domains_increments_across_multiple_exhausted_calls(): void
    {
        $this->insertState(depth: 4, domainIndex: 0, completedDomains: 0);

        $domains    = ['histoire', 'geographie', 'sport', 'art', 'cuisine', 'science'];
        $checker    = $this->makeCheckerForDomains($domains);

        // 6 appels → 6 domaines épuisés un par un
        for ($i = 0; $i < 6; $i++) {
            $this->planner->plan($checker);
        }

        $state = $this->loadState();

        $this->assertSame(6, (int) $state->completed_domains);
        $this->assertSame(6, (int) $state->current_domain_index); // cinema
    }

    // =========================================================================
    // Test 9 — completed_domains == 8 → passage au depth suivant
    // =========================================================================

    public function test_plan_advances_depth_when_all_8_domains_exhausted(): void
    {
        // depth=4 : 0 noyaux → remaining=3000 (le plus élevé → chooseDepth retourne 4)
        // depths 6,7,8,9 : 1 noyau chacun → remaining inférieurs à 3000
        DB::table('question_groups')->insert(['difficulty_depth' => 6]);
        DB::table('question_groups')->insert(['difficulty_depth' => 7]);
        DB::table('question_groups')->insert(['difficulty_depth' => 8]);
        DB::table('question_groups')->insert(['difficulty_depth' => 9]);

        // État : on est au 8e domaine (faune, idx=7), 7 domaines déjà terminés
        $this->insertState(depth: 6, domainIndex: 7, completedDomains: 7);

        $this->planner->plan($this->makeChecker(true)); // faune = EXHAUSTED → 8/8

        $state = $this->loadState();

        $this->assertSame(0, (int) $state->completed_domains,    'Compteur réinitialisé à 0');
        $this->assertSame(0, (int) $state->current_domain_index, 'DomainCycle réinitialisé à 0');
        $this->assertSame(4, (int) $state->current_depth,        'Nouveau depth choisi = 4');
    }

    // =========================================================================
    // Test 10 — rotation_identifier unique à chaque appel
    // =========================================================================

    public function test_rotation_identifier_is_unique_across_calls(): void
    {
        $this->insertState(depth: 4, domainIndex: 0, completedDomains: 0);

        $checker = $this->makeChecker(false);
        $ids     = [];

        for ($i = 0; $i < 10; $i++) {
            $result = $this->planner->plan($checker);
            $ids[]  = $result['rotation_context']['rotation_identifier'];
        }

        $this->assertCount(10, array_unique($ids), 'Chaque rotation_identifier doit être unique');
    }

    // =========================================================================
    // Test 11 — rotation_identifier persiste dans la table après plan()
    // =========================================================================

    public function test_rotation_identifier_is_persisted_in_state_table(): void
    {
        $this->insertState(depth: 4, domainIndex: 0, completedDomains: 0);

        $result = $this->planner->plan($this->makeChecker(false));
        $rid    = $result['rotation_context']['rotation_identifier'];

        $state = $this->loadState();

        $this->assertSame($rid, $state->last_rotation_identifier);
    }

    // =========================================================================
    // Test 12 — plan() ne contient jamais sub_domain / kernel_code / Taxonomy
    // =========================================================================

    public function test_plan_output_never_contains_taxonomy_or_kernel_code(): void
    {
        $this->insertState(depth: 4, domainIndex: 0, completedDomains: 0);

        $ctx = $this->planner->plan($this->makeChecker(false))['rotation_context'];

        $this->assertArrayNotHasKey('sub_domain',     $ctx);
        $this->assertArrayNotHasKey('subjects',       $ctx);
        $this->assertArrayNotHasKey('dominant_idea',  $ctx);
        $this->assertArrayNotHasKey('kernel_code',    $ctx);
        $this->assertArrayNotHasKey('READY_BANK',     $ctx);
        $this->assertArrayNotHasKey('taxonomy',       $ctx);
    }
}
