<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyConfig;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\TaxonomyPreparationException;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\CreatesApplication;

/**
 * DEC-102 — épuisement normal d'un Subject selon son total réel de PASS.
 *
 * Ces tests ciblent exclusivement le chemin Taxonomy v1.1. Ils utilisent la
 * méthode privée de génération par réflexion afin d'isoler la transition
 * Subject sans dépendre du pipeline KRP/Blueprint.
 */
final class TaxonomyV11IdeaExhaustionTest extends TestCase
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
        Schema::dropIfExists('taxonomy_v11_blueprint_assignments');
        Schema::dropIfExists('taxonomy_v11_terminal_facts');
        Schema::dropIfExists('taxonomy_v11_generation_memory');
        Schema::dropIfExists('taxonomy_v11_ideas');
        Schema::dropIfExists('taxonomy_v11_subjects');
        Schema::dropIfExists('taxonomy_v11_subdomains');
        Schema::dropIfExists('taxonomy_v11_occurrences');
        parent::tearDown();
    }

    public function test_zero_total_pass_with_no_more_ideas_is_anomaly_and_never_exhausted(): void
    {
        [$occurrence, $subdomain, $subject] = $this->seedSubject();
        $this->mockIdeaResponse('NO_MORE_IDEAS');

        $this->assertPreparationFailure($occurrence, $subdomain, $subject);

        $this->assertSubjectExhausted($subject, false);
        $this->assertMemoryExhausted($occurrence, $subject, false);
    }

    public function test_zero_total_pass_at_attempt_limit_is_anomaly_and_never_exhausted(): void
    {
        [$occurrence, $subdomain, $subject] = $this->seedSubject();
        $this->seedCompletedIdeaAttempts($occurrence, $subject, 2);
        $this->mockIdeaResponse('CANDIDATES');

        $this->assertPreparationFailure($occurrence, $subdomain, $subject);

        $this->assertSubjectExhausted($subject, false);
        $this->assertMemoryExhausted($occurrence, $subject, false);
    }

    public function test_fail_ideas_and_operation_number_survive_preparation_exception(): void
    {
        [$occurrence, $subdomain, $subject] = $this->seedSubject();
        $this->mockIdeaResponse('CANDIDATES', ['Particules élémentaires']);

        $this->assertPreparationFailure($occurrence, $subdomain, $subject);

        $fail = DB::table('taxonomy_v11_ideas')
            ->where('subject_id', $subject->id)
            ->where('validation_status', 'FAIL')
            ->first();

        $this->assertNotNull($fail);
        $this->assertSame('SUBJECT_REPETITION', $fail->fail_reason);
        $this->assertSame(
            1,
            DB::table('taxonomy_v11_generation_memory')
                ->where('occurrence_id', $occurrence->id)
                ->where('context_type', 'IDEAS')
                ->where('context_key', 'subject:' . $subject->id)
                ->value('attempt_number')
        );
        $this->assertSubjectExhausted($subject, false);
        $this->assertSame(0, DB::table('taxonomy_v11_terminal_facts')->count());
    }

    /**
     * @dataProvider partialPassCounts
     */
    public function test_partial_pass_total_with_no_more_ideas_is_normally_exhausted(int $passCount): void
    {
        [$occurrence, $subdomain, $subject] = $this->seedSubject($passCount);
        $this->mockIdeaResponse('NO_MORE_IDEAS');

        $this->invokeIdeaGeneration($occurrence, $subdomain, $subject);

        $this->assertSubjectExhausted($subject, true);
        $this->assertMemoryExhausted($occurrence, $subject, true);
        $this->assertSame($passCount, $this->passCount($subject));
    }

    /**
     * @dataProvider partialPassCounts
     */
    public function test_partial_pass_total_at_attempt_limit_is_normally_exhausted(int $passCount): void
    {
        [$occurrence, $subdomain, $subject] = $this->seedSubject($passCount);
        $this->seedCompletedIdeaAttempts($occurrence, $subject, 2);
        $this->mockIdeaResponse('CANDIDATES');

        $this->invokeIdeaGeneration($occurrence, $subdomain, $subject);

        $this->assertSubjectExhausted($subject, true);
        $this->assertMemoryExhausted($occurrence, $subject, true);
        $this->assertSame($passCount, $this->passCount($subject));
    }

    public function test_four_existing_passes_plus_one_new_pass_reaches_five_and_exhausts(): void
    {
        [$occurrence, $subdomain, $subject] = $this->seedSubject(4);
        $this->mockIdeaResponse('CANDIDATES', ['Moment magnétique']);

        $this->invokeIdeaGeneration($occurrence, $subdomain, $subject);

        $this->assertSame(5, $this->passCount($subject));
        $this->assertSubjectExhausted($subject, true);
        $this->assertMemoryExhausted($occurrence, $subject, true);
    }

    public function test_partial_pass_without_new_pass_remains_open_and_can_resume(): void
    {
        [$occurrence, $subdomain, $subject] = $this->seedSubject(1);
        $this->gemini->expects($this->exactly(2))
            ->method('generateIdeas')
            ->willReturnOnConsecutiveCalls(
                ['status' => 'CANDIDATES', 'candidates' => []],
                ['status' => 'CANDIDATES', 'candidates' => [['value' => 'Moment magnétique']]],
            );

        $this->assertPreparationFailure($occurrence, $subdomain, $subject);
        $this->assertSubjectExhausted($subject, false);

        $this->invokeIdeaGeneration($occurrence, $subdomain, $subject);

        $this->assertSame(2, $this->passCount($subject));
        $this->assertSubjectExhausted($subject, false);
    }

    public function test_zero_pass_subject_prevents_terminal_fact(): void
    {
        [$occurrence] = $this->seedSubject();
        $this->mockIdeaResponse('NO_MORE_IDEAS');

        try {
            $this->orchestrator->assignToBlueprint($this->blueprint('00000000-0000-7000-8000-000000000001'));
            $this->fail('Une TaxonomyPreparationException était attendue.');
        } catch (TaxonomyPreparationException) {
            // Contrat attendu : l'occurrence reste reprenable et non terminale.
        }

        $this->assertSame(0, DB::table('taxonomy_v11_terminal_facts')->count());
        $this->assertSame(1, $this->repo->remainingV11Subjects((int) $occurrence->id));
    }

    public function test_terminal_fact_is_created_only_after_all_subjects_are_normally_exhausted(): void
    {
        [$occurrence, , $subject] = $this->seedSubject(1);
        $this->repo->markV11SubjectIdeaGenerationExhausted((int) $subject->id);
        $this->repo->markV11OccurrenceOpen((int) $occurrence->id);
        $this->gemini->expects($this->never())->method('generateIdeas');

        $this->orchestrator->assignToBlueprint(
            $this->blueprint('00000000-0000-7000-8000-000000000002')
        );

        $this->assertSame(0, $this->repo->remainingV11Ideas((int) $occurrence->id));
        $this->assertSame(0, $this->repo->remainingV11Subjects((int) $occurrence->id));
        $this->assertSame(1, DB::table('taxonomy_v11_terminal_facts')->count());
        $this->assertSame(
            'EXHAUSTED',
            DB::table('taxonomy_v11_occurrences')->where('id', $occurrence->id)->value('status')
        );
    }

    public static function partialPassCounts(): array
    {
        return [
            'une PASS existante' => [1],
            'quatre PASS existantes' => [4],
        ];
    }

    /**
     * @return array{object, object, object}
     */
    private function seedSubject(int $passCount = 0): array
    {
        $occurrence = $this->repo->findOrCreateV11Occurrence(2, 'science');
        $subdomain = $this->repo->createV11Subdomain(
            (int) $occurrence->id,
            'Propriétés de la matière',
        );
        $this->repo->createV11Subjects((int) $subdomain->id, ['Particules élémentaires']);
        $subject = $this->repo->getV11SubjectsForSubdomain((int) $subdomain->id)[0];

        $ideas = [
            'Charge électrique',
            'Masse inertielle',
            'Spin quantique',
            'Nombre baryonique',
        ];

        foreach (array_slice($ideas, 0, $passCount) as $idea) {
            $this->repo->persistV11PassIdea((int) $subject->id, $idea);
        }

        return [$occurrence, $subdomain, $subject];
    }

    private function seedCompletedIdeaAttempts(object $occurrence, object $subject, int $count): void
    {
        for ($attempt = 1; $attempt <= $count; $attempt++) {
            $this->repo->persistV11Memory(
                (int) $occurrence->id,
                'IDEAS',
                'subject:' . $subject->id,
                $attempt,
                [],
                [],
                [],
                [],
                false,
            );
        }
    }

    /**
     * @param string[] $candidates
     */
    private function mockIdeaResponse(string $status, array $candidates = []): void
    {
        $this->gemini->expects($this->once())
            ->method('generateIdeas')
            ->willReturn([
                'status' => $status,
                'candidates' => array_map(
                    static fn(string $value): array => ['value' => $value],
                    $candidates,
                ),
            ]);
    }

    private function assertPreparationFailure(
        object $occurrence,
        object $subdomain,
        object $subject,
    ): void {
        $thrown = null;

        try {
            $this->invokeIdeaGeneration($occurrence, $subdomain, $subject);
        } catch (TaxonomyPreparationException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(TaxonomyPreparationException::class, $thrown);
    }

    private function invokeIdeaGeneration(
        object $occurrence,
        object $subdomain,
        object $subject,
    ): void {
        $method = new ReflectionMethod(TaxonomyOrchestrator::class, 'generateV11IdeasForSubject');
        $method->setAccessible(true);
        $method->invoke(
            $this->orchestrator,
            $occurrence,
            $subdomain,
            $subject,
            'science',
            DepthContractRegistry::get(2),
        );
    }

    private function assertSubjectExhausted(object $subject, bool $expected): void
    {
        $actual = DB::table('taxonomy_v11_subjects')
            ->where('id', $subject->id)
            ->value('idea_generation_exhausted');

        $this->assertSame($expected, (bool) $actual);
    }

    private function assertMemoryExhausted(object $occurrence, object $subject, bool $expected): void
    {
        $actual = DB::table('taxonomy_v11_generation_memory')
            ->where('occurrence_id', $occurrence->id)
            ->where('context_type', 'IDEAS')
            ->where('context_key', 'subject:' . $subject->id)
            ->orderByDesc('attempt_number')
            ->value('generation_exhausted');

        $this->assertSame($expected, (bool) $actual);
    }

    private function passCount(object $subject): int
    {
        return count($this->repo->getV11PassIdeaValues((int) $subject->id));
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
            $table->foreignId('occurrence_id')->unique()
                ->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
            $table->string('subdomain_name', 256);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('taxonomy_v11_subjects', function ($table) {
            $table->id();
            $table->foreignId('subdomain_id')
                ->constrained('taxonomy_v11_subdomains')->cascadeOnDelete();
            $table->string('subject_name', 256);
            $table->string('status', 24)->default('AVAILABLE');
            $table->unsignedTinyInteger('idea_attempt_count')->default(0);
            $table->boolean('idea_generation_exhausted')->default(false);
            $table->timestamps();
            $table->unique(['subdomain_id', 'subject_name']);
        });

        Schema::create('taxonomy_v11_ideas', function ($table) {
            $table->id();
            $table->foreignId('subject_id')
                ->constrained('taxonomy_v11_subjects')->cascadeOnDelete();
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
            $table->foreignId('occurrence_id')
                ->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
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

        Schema::create('taxonomy_v11_blueprint_assignments', function ($table) {
            $table->string('blueprint_id', 36)->primary();
            $table->foreignId('occurrence_id')
                ->constrained('taxonomy_v11_occurrences')->restrictOnDelete();
            $table->foreignId('subdomain_id')
                ->constrained('taxonomy_v11_subdomains')->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained('taxonomy_v11_subjects')->restrictOnDelete();
            $table->foreignId('idea_id')
                ->constrained('taxonomy_v11_ideas')->restrictOnDelete();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('subdomain_active', 256);
            $table->string('subject_active', 256);
            $table->string('dominant_idea_active', 512);
            $table->timestamps();
        });
    }
}