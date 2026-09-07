<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use App\Services\QuestionBank\Phase1\KernelPhase1Generator;
use App\Services\QuestionBank\Phase1\KernelPhase1SourceValidator;
use App\Services\QuestionBank\Testing\InMemoryKernelBlueprintTerminalSink;
use App\Services\QuestionBank\Testing\KernelBlueprintFixture;
use App\Services\QuestionBank\Testing\KernelBlueprintManualPreconditions;
use App\Services\QuestionBank\Testing\KernelBlueprintPersistentLoader;
use App\Services\QuestionBank\Testing\KernelBlueprintTestHarness;
use App\Services\QuestionBank\Testing\KernelBlueprintTestPhase;
use App\Services\QuestionBank\Testing\KernelPhase1TestAdapter;
use App\Services\QuestionApi\QuestionApiClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Démonstration intégrée du harness manuel dans un schéma PostgreSQL dédié.
 *
 * Aucune migration n'est appelée. Les tables Blueprint et Taxonomy minimales sont créées
 * directement dans le schéma aléatoire, puis celui-ci est supprimé en tearDown.
 */
final class KernelBlueprintTestHarnessPostgresTest extends TestCase
{
    private const ISOLATED_CONNECTION = 'manual_blueprint_test';

    private string $schemaName = '';
    private string $originalDefaultConnection = '';
    private mixed $originalSearchPath = null;
    private mixed $originalDedicatedConfig = null;
    private bool $schemaCreated = false;

    /** @var array<int, array{relation_name: string, relation_oid: string}> */
    private array $publicRelationOids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');
        $this->originalDedicatedConfig = config(
            'database.connections.' . self::ISOLATED_CONNECTION,
        );

        $this->schemaName = 'test_manual_blueprint_' . bin2hex(random_bytes(6));
        $baseConnection = DB::connection('pgsql');
        $this->publicRelationOids = $this->snapshotPublicRelationOids($baseConnection);
        $baseConnection->statement('CREATE SCHEMA ' . $this->quotedSchemaName());
        $this->schemaCreated = true;

        $configuration = config('database.connections.pgsql');
        $configuration['search_path'] = $this->quotedSchemaName();
        config([
            'database.connections.' . self::ISOLATED_CONNECTION => $configuration,
            'database.default' => self::ISOLATED_CONNECTION,
        ]);
        DB::purge(self::ISOLATED_CONNECTION);
        DB::reconnect(self::ISOLATED_CONNECTION);
        DB::setDefaultConnection(self::ISOLATED_CONNECTION);

        $connection = DB::connection(self::ISOLATED_CONNECTION);
        $this->assertSame('pgsql', $connection->getDriverName());
        $this->assertIsolatedSchemaActive($connection);
        $this->createHarnessSchema($connection);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->schemaCreated) {
                $baseConnection = DB::connection('pgsql');
                $baseConnection->statement(
                    'DROP SCHEMA IF EXISTS ' . $this->quotedSchemaName() . ' CASCADE',
                );
                $this->schemaCreated = false;

                $schemaCount = (int) ($baseConnection->selectOne(
                    'SELECT COUNT(*) AS schema_count '
                    . 'FROM pg_catalog.pg_namespace WHERE nspname = ?',
                    [$this->schemaName],
                )->schema_count ?? -1);
                $this->assertSame(0, $schemaCount);
                $this->assertSame(
                    $this->publicRelationOids,
                    $this->snapshotPublicRelationOids($baseConnection),
                    'Les relations public ne doivent pas changer.',
                );
            }
        } finally {
            DB::setDefaultConnection($this->originalDefaultConnection);
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.pgsql.search_path' => $this->originalSearchPath,
                'database.connections.' . self::ISOLATED_CONNECTION => $this->originalDedicatedConfig,
            ]);
            DB::purge(self::ISOLATED_CONNECTION);
            DB::purge('pgsql');
            parent::tearDown();
        }
    }

    public function test_real_factory_creates_seven_empty_slots_and_invokes_only_the_fake_phase_once(): void
    {
        $phase = new RecordingPhase();
        $harness = new KernelBlueprintTestHarness();
        $preconditions = $this->validPreconditions();

        $blueprintId = $harness->execute(
            $preconditions,
            $phase,
            function (string $blueprintId) use ($phase): string {
                $this->assertSame('ENGAGED_IN_PIPELINE', DB::table('kernel_blueprint_runs')
                    ->where('blueprint_id', $blueprintId)
                    ->value('execution_state'));
                $this->assertSame(1, DB::table('kernel_blueprint_runs')->count());
                $this->assertSame(7, DB::table('kernel_blueprint_cognitive_slots')->count());
                $this->assertSame(1, $phase->calls);
                $this->assertSame($blueprintId, $phase->receivedBlueprintId);

                $parent = DB::table('kernel_blueprint_runs')
                    ->where('blueprint_id', $blueprintId)
                    ->first();
                $this->assertSame(4, (int) $parent->depth);
                $this->assertSame('science', $parent->domain_code);
                $this->assertSame('04-SCI-PHY-LUM-REF-0001', $parent->kernel_code);
                $this->assertNotNull($parent->engaged_at);
                $assignment = DB::table('taxonomy_v11_blueprint_assignments')
                    ->where('blueprint_id', $blueprintId)->first();
                $this->assertSame(4, (int) $assignment->depth);
                $this->assertSame('science', $assignment->domain_code);
                $this->assertSame('Physique', $assignment->subdomain_active);
                $this->assertSame('Lumière', $assignment->subject_active);
                $this->assertSame('Réfraction', $assignment->dominant_idea_active);

                $slots = (new \App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository())
                    ->allForBlueprint($blueprintId);
                $this->assertCount(7, $slots);
                $this->assertEqualsCanonicalizing(KernelBlueprint::COGNITIVE_TYPES, array_keys($slots));
                foreach ($slots as $type => $slot) {
                    $isQcm = str_starts_with($type, 'QCM_');
                    $this->assertSame($type, $slot['cognitive_type']);
                    $this->assertNull($slot['source']['question']);
                    $this->assertSame(
                        $isQcm
                            ? ['a' => null, 'b' => null, 'c' => null, 'd' => null]
                            : ['a' => null, 'b' => null],
                        $slot['source']['choices'],
                    );
                    $this->assertSame(
                        $isQcm || str_ends_with($type, '_TRUE') ? 'a' : 'b',
                        $slot['source']['correct_answer_key'],
                    );
                    $this->assertNull($slot['source']['sv']);
                    $this->assertNull($slot['source']['creation_evidence']);
                    $this->assertSame([], $slot['translations']);
                    $this->assertSame('EMPTY', $slot['creation_status']);
                    $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
                    $this->assertSame([], $slot['validation_findings']);
                    $this->assertNull($slot['creation_failure']);
                }

                $this->assertSame(
                    '{}',
                    DB::table('kernel_blueprint_cognitive_slots')
                        ->where('blueprint_id', $blueprintId)
                        ->value('translations'),
                );
                $this->assertSentinelsUntouched();

                return $blueprintId;
            },
        );

        $this->assertSame($phase->receivedBlueprintId, $blueprintId);
        $this->assertSame(1, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_blueprint_assignments')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_occurrences')->count());
    }

    public function test_phase_failure_still_cleans_parent_and_cascaded_slots(): void
    {
        $phase = new FailingPhase();
        $harness = new KernelBlueprintTestHarness();

        try {
            $harness->execute($this->validPreconditions(), $phase, static fn (): null => null);
            $this->fail('La phase simulée devait échouer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated phase failure', $exception->getMessage());
        }

        $this->assertSame(1, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_blueprint_assignments')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_occurrences')->count());
    }

    public function test_assertion_failure_still_cleans_parent_and_slots(): void
    {
        $harness = new KernelBlueprintTestHarness();
        $phase = new RecordingPhase();

        try {
            $harness->execute(
                $this->validPreconditions(),
                $phase,
                function (): never {
                    throw new AssertionFailedError('intentional inspection failure');
                },
            );
            $this->fail('Le callback devait échouer.');
        } catch (AssertionFailedError $exception) {
            $this->assertSame('intentional inspection failure', $exception->getMessage());
        }

        $this->assertSame(1, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_blueprint_assignments')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_occurrences')->count());
    }

    public function test_invalid_manual_preconditions_are_rejected_before_phase_and_cleaned(): void
    {
        $phase = new RecordingPhase();
        $harness = new KernelBlueprintTestHarness();
        $invalid = new KernelBlueprintManualPreconditions(
            depth: 0,
            domain: 'science',
            subdomainActive: 'Physique',
            subjectActive: 'Lumière',
            dominantIdeaActive: 'Réfraction',
            kernelCode: '04-SCI-PHY-LUM-REF-0001',
        );

        try {
            $harness->execute($invalid, $phase, static fn (): null => null);
            $this->fail('Les préconditions invalides devaient être rejetées.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('depth', $exception->getMessage());
        }

        $this->assertSame(0, $phase->calls);
        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_blueprint_assignments')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_occurrences')->count());
    }

    public function test_phase1_adapter_persists_seven_sources_and_intercepts_terminal_result(): void
    {
        $client = new HarnessPhase1FakeQuestionApiClient([
            fn (array $request): Response => $this->successResponse($this->phase1Payload($request)),
        ]);
        $sink = new InMemoryKernelBlueprintTerminalSink();
        $harness = new KernelBlueprintTestHarness();

        $resultId = $harness->execute($this->validPreconditions(), $this->phase1Adapter($client, $sink),
            function (string $blueprintId) use ($sink, $client): string {
                $slots = (new KernelBlueprintCognitiveSlotRepository())->allForBlueprint($blueprintId);
                $this->assertCount(7, $slots);
                foreach ($slots as $slot) {
                    $this->assertSame('CREATED', $slot['creation_status']);
                    $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
                }
                $terminal = $sink->resultFor($blueprintId);
                $this->assertSame('CREATED', $terminal['status']);
                $this->assertSame(1, $client->calls);
                $this->assertSentinelsUntouched();

                return $blueprintId;
            });

        $this->assertSame(1, $client->calls);
        $this->assertNull($sink->resultFor('other-blueprint'));
        $this->assertIsString($resultId);
    }

    public function test_phase1_transport_failure_is_terminal_and_never_marks_suspicion(): void
    {
        $client = new HarnessPhase1FakeQuestionApiClient([
            new RuntimeException('transport 1'), new RuntimeException('transport 2'),
            new RuntimeException('transport 3'),
        ]);
        $sink = new InMemoryKernelBlueprintTerminalSink();

        (new KernelBlueprintTestHarness())->execute(
            $this->validPreconditions(), $this->phase1Adapter($client, $sink),
            function (string $blueprintId) use ($sink): null {
                $slots = (new KernelBlueprintCognitiveSlotRepository())->allForBlueprint($blueprintId);
                foreach ($slots as $slot) {
                    $this->assertSame('CREATION_FAILED', $slot['creation_status']);
                    $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
                    $this->assertNotSame('SUSPICION', $slot['validation_status']);
                }
                $this->assertSame('CREATION_FAILED', $sink->resultFor($blueprintId)['status']);
                $this->assertSentinelsUntouched();

                return null;
            },
        );
        $this->assertSame(3, $client->calls);
    }

    public function test_technically_valid_but_intellectually_suspect_source_stays_created_not_validated(): void
    {
        $client = new HarnessPhase1FakeQuestionApiClient([
            fn (array $request): Response => $this->successResponse(
                $this->phase1Payload($request, 'La réfraction transforme toujours toute lumière en chaleur.')
            ),
        ]);
        $sink = new InMemoryKernelBlueprintTerminalSink();

        (new KernelBlueprintTestHarness())->execute(
            $this->validPreconditions(), $this->phase1Adapter($client, $sink),
            function (string $blueprintId) use ($sink): null {
                foreach ((new KernelBlueprintCognitiveSlotRepository())->allForBlueprint($blueprintId) as $slot) {
                    $this->assertSame('CREATED', $slot['creation_status']);
                    $this->assertSame('NOT_VALIDATED', $slot['validation_status']);
                }
                $this->assertSame('CREATED', $sink->resultFor($blueprintId)['status']);
                $this->assertSentinelsUntouched();

                return null;
            },
        );
    }

    public function test_fixture_rolls_back_factory_parent_and_slots_when_taxonomy_precondition_insert_fails(): void
    {
        DB::table('taxonomy_v11_occurrences')->insert([
            'depth' => 4, 'domain_code' => 'science', 'ordinal' => 1, 'status' => 'OPEN',
            'consecutive_technical_failures' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            (new KernelBlueprintFixture())->create($this->validPreconditions());
            $this->fail('La contrainte unique de l’occurrence devait interrompre la fixture.');
        } catch (\Throwable) {
            $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
            $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
            $this->assertSame(1, DB::table('taxonomy_v11_occurrences')->count());
            $this->assertSame(0, DB::table('taxonomy_v11_blueprint_assignments')->count());
        }
    }

    public function test_loader_refuses_unengaged_run_before_client_or_terminal_and_fixture_cleans(): void
    {
        $fixture = new KernelBlueprintFixture();
        $blueprintId = $fixture->create($this->validPreconditions());
        DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)
            ->update(['execution_state' => 'CREATED_UNENGAGED']);
        $client = new HarnessPhase1FakeQuestionApiClient([]);
        $sink = new InMemoryKernelBlueprintTerminalSink();

        try {
            $this->phase1Adapter($client, $sink)->run($blueprintId);
            $this->fail('Le loader devait refuser un Blueprint non engagé.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('non engagé', $exception->getMessage());
            $this->assertSame(0, $client->calls);
            $this->assertNull($sink->resultFor($blueprintId));
        } finally {
            $fixture->cleanup($blueprintId);
        }

        $this->assertSame(0, DB::table('kernel_blueprint_runs')->count());
        $this->assertSame(0, DB::table('kernel_blueprint_cognitive_slots')->count());
        $this->assertSame(0, DB::table('taxonomy_v11_blueprint_assignments')->count());
    }

    private function validPreconditions(): KernelBlueprintManualPreconditions
    {
        return new KernelBlueprintManualPreconditions(
            depth: 4,
            domain: 'science',
            subdomainActive: 'Physique',
            subjectActive: 'Lumière',
            dominantIdeaActive: 'Réfraction',
            kernelCode: '04-SCI-PHY-LUM-REF-0001',
        );
    }

    private function phase1Adapter(
        QuestionApiClient $client,
        InMemoryKernelBlueprintTerminalSink $sink,
    ): KernelPhase1TestAdapter {
        return new KernelPhase1TestAdapter(
            new KernelBlueprintPersistentLoader(),
            new KernelPhase1Generator(
                $client,
                new KernelBlueprintCognitiveSlotRepository(),
                new KernelPhase1SourceValidator(),
            ),
            $sink,
        );
    }

    private function successResponse(array $payload): Response
    {
        return new Response(new PsrResponse(200, ['Content-Type' => 'application/json'],
            json_encode(['ok' => true, 'result' => $payload], JSON_THROW_ON_ERROR)));
    }

    /** @param array<string, mixed> $request @return array<string, mixed> */
    private function phase1Payload(array $request, ?string $question = null): array
    {
        $contents = [
            'QCM_RECOGNITION' => ['Quel phénomène décrit le changement de direction de la lumière entre deux milieux ?', ['Réfraction', 'Réflexion', 'Diffusion', 'Absorption'], 'La réfraction dévie la lumière lorsqu’elle change de milieu.'],
            'QCM_REASONING' => ['Pourquoi un rayon lumineux se dévie-t-il en entrant dans l’eau ?', ['Sa vitesse change', 'Sa couleur disparaît', 'Il devient une ombre', 'L’eau le reflète toujours'], 'Le changement de vitesse entre deux milieux provoque la déviation.'],
            'QCM_TRAP' => ['Dans quel cas la réfraction est-elle la plus visible ?', ['Air vers eau obliquement', 'Air vers air', 'Vide vers vide', 'Miroir opaque'], 'Un passage oblique entre air et eau rend la déviation observable.'],
            'TRUE_FALSE_RECOGNITION_TRUE' => ['Vrai ou faux : la réfraction peut faire paraître une paille brisée dans un verre ?', [], 'La lumière déviée à la surface de l’eau modifie la position apparente.'],
            'TRUE_FALSE_RECOGNITION_FALSE' => ['Vrai ou faux : la réfraction signifie que la lumière s’arrête à la surface ?', [], 'La lumière continue son trajet mais change de direction.'],
            'TRUE_FALSE_REASONING_TRUE' => ['Vrai ou faux : un rayon oblique peut être dévié parce que sa vitesse change ?', [], 'La variation de vitesse explique la déviation du rayon.'],
            'TRUE_FALSE_REASONING_FALSE' => ['Vrai ou faux : la réfraction impose la même direction dans tous les milieux ?', [], 'La direction peut changer au passage entre milieux différents.'],
        ];
        $slots = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $index => $type) {
            $qcm = str_starts_with($type, 'QCM_');
            [$defaultQuestion, $choices, $sv] = $contents[$type];
            $slots[] = [
                'cognitive_type' => $type,
                'question' => $question !== null
                    ? "{$question} Variante {$index}?"
                    : $defaultQuestion,
                'choices' => $qcm
                    ? array_map(
                        static fn (string $text, int $key): array => ['key' => ['a', 'b', 'c', 'd'][$key], 'text' => $text],
                        $choices,
                        array_keys($choices),
                    )
                    : [['key' => 'a', 'text' => 'VRAI'], ['key' => 'b', 'text' => 'FAUX']],
                'correct_answer_key' => $qcm || str_ends_with($type, '_TRUE') ? 'a' : 'b',
                'sv' => $sv,
                'creation_evidence' => [
                    'cognitive_operation' => 'Opération distincte',
                    'cognitive_justification' => 'Justification déterministe',
                    'difference_from_other_slots' => 'Proposition intellectuelle indépendante',
                    'truth_basis' => 'Fondement factuel vérifiable',
                    'trap_basis' => $type === 'QCM_TRAP' ? 'Confusion plausible documentée' : null,
                    'self_checks' => [
                        'question_readable_under_8_seconds' => true, 'sv_readable_under_30_seconds' => true,
                        'correct_answer_explained_by_sv' => true, 'cognitive_type_respected' => true,
                        'one_correct_answer_only' => true, 'choices_are_plausible' => true,
                        'distinct_from_other_slots' => true, 'same_subject_and_dominant_idea' => true,
                        'question_answer_choices_sv_coherent_with_subdomain' => true,
                    ],
                ],
            ];
        }
        return ['schema_version' => 'phase1.source.v1', 'blueprint_id' => $request['blueprint_id'],
            'kernel_code' => $request['kernel_code'], 'source_language' => 'fr', 'slots' => $slots];
    }

    private function assertSentinelsUntouched(): void
    {
        foreach (['kernel_pipeline_outbox', 'kernel_depth_matrix', 'kernel_taxonomy_terminal_facts', 'question_groups'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} doit rester vide.");
        }
        $this->assertSame(
            ['legacy' => true],
            json_decode((string) DB::table('question_intents')->value('frame_en'), true),
        );
    }

    private function createHarnessSchema(ConnectionInterface $connection): void
    {
        // Sentinelles explicites : aucune cascade pipeline ou banque aval.
        foreach (['kernel_pipeline_outbox', 'kernel_depth_matrix', 'kernel_taxonomy_terminal_facts', 'question_groups'] as $sentinel) {
            Schema::connection(self::ISOLATED_CONNECTION)->create($sentinel, function (Blueprint $table): void {
                $table->id();
            });
        }
        Schema::connection(self::ISOLATED_CONNECTION)->create('question_intents', function (Blueprint $table): void {
            $table->id();
            $table->jsonb('frame_en')->nullable();
        });
        DB::table('question_intents')->insert(['frame_en' => '{"legacy":true}']);

        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'taxonomy_v11_occurrences',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedTinyInteger('depth');
                $table->string('domain_code', 32);
                $table->unsignedInteger('ordinal');
                $table->string('status', 16)->default('PREPARING');
                $table->unsignedTinyInteger('consecutive_technical_failures')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('exhausted_at')->nullable();
                $table->timestamps();
                $table->unique(['depth', 'domain_code', 'ordinal'], 'tv11_occurrence_ordinal_unique');
            },
        );
        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'taxonomy_v11_subdomains',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('occurrence_id')->unique('tv11_subdomain_occurrence_unique')
                    ->constrained('taxonomy_v11_occurrences')->cascadeOnDelete();
                $table->string('subdomain_name', 256);
                $table->string('status', 16)->default('ACTIVE');
                $table->timestamps();
            },
        );
        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'taxonomy_v11_subjects',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('subdomain_id')->constrained('taxonomy_v11_subdomains')->cascadeOnDelete();
                $table->string('subject_name', 256);
                $table->string('status', 24)->default('AVAILABLE');
                $table->unsignedTinyInteger('idea_attempt_count')->default(0);
                $table->boolean('idea_generation_exhausted')->default(false);
                $table->timestamps();
                $table->unique(['subdomain_id', 'subject_name'], 'tv11_subject_name_unique');
            },
        );
        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'taxonomy_v11_ideas',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('subject_id')->constrained('taxonomy_v11_subjects')->cascadeOnDelete();
                $table->string('idea_value', 512);
                $table->string('validation_status', 8);
                $table->string('fail_reason', 64)->nullable();
                $table->string('fail_conflict_with', 512)->nullable();
                $table->string('status', 16);
                $table->timestamps();
                $table->unique(['subject_id', 'idea_value'], 'tv11_idea_value_unique');
            },
        );
        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'kernel_blueprint_runs',
            function (Blueprint $table): void {
                $table->string('blueprint_id', 36)->primary();
                $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
                $table->smallInteger('depth')->nullable();
                $table->string('domain_code', 64)->nullable();
                $table->string('kernel_code', 23)->nullable();
                $table->timestampTz('engaged_at')->nullable();
                $table->timestampTz('received_at')->nullable();
                $table->timestampsTz();
            },
        );

        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'kernel_blueprint_cognitive_slots',
            function (Blueprint $table): void {
                $table->string('blueprint_id', 36);
                $table->string('cognitive_type', 64);
                $table->jsonb('source');
                $table->jsonb('creation_failure')->nullable();
                $table->jsonb('translations')->default('{}');
                $table->string('creation_status', 32)->default('EMPTY');
                $table->string('validation_status', 32)->default('NOT_VALIDATED');
                $table->jsonb('validation_findings')->default('[]');
                $table->timestampsTz();
                $table->primary(['blueprint_id', 'cognitive_type']);
                $table->foreign('blueprint_id')
                    ->references('blueprint_id')
                    ->on('kernel_blueprint_runs')
                    ->cascadeOnDelete();
            },
        );

        Schema::connection(self::ISOLATED_CONNECTION)->create(
            'taxonomy_v11_blueprint_assignments',
            function (Blueprint $table): void {
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
            },
        );

        $this->assertIsolatedSchemaActive($connection);
    }

    private function assertIsolatedSchemaActive(ConnectionInterface $connection): void
    {
        $activeSchema = $connection->selectOne(
            'SELECT current_schema() AS schema_name',
        )->schema_name ?? null;
        $searchPath = (string) ($connection->selectOne('SHOW search_path')->search_path ?? '');

        $this->assertSame($this->schemaName, $activeSchema);
        $this->assertStringNotContainsString('public', strtolower($searchPath));
    }

    private function relationCount(string $relation): int
    {
        return (int) ($this->isolatedConnection()->selectOne(
            'SELECT COUNT(*) AS relation_count '
            . 'FROM pg_catalog.pg_class c '
            . 'JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace '
            . 'WHERE n.nspname = ? AND c.relname = ?',
            [$this->schemaName, $relation],
        )->relation_count ?? 0);
    }

    private function isolatedConnection(): ConnectionInterface
    {
        return DB::connection(self::ISOLATED_CONNECTION);
    }

    /**
     * @return array<int, array{relation_name: string, relation_oid: string}>
     */
    private function snapshotPublicRelationOids(ConnectionInterface $connection): array
    {
        return array_map(
            static fn (object $row): array => [
                'relation_name' => (string) $row->relation_name,
                'relation_oid' => (string) $row->relation_oid,
            ],
            $connection->select(
                "SELECT c.relname AS relation_name, c.oid::text AS relation_oid
                 FROM pg_catalog.pg_class c
                 JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public'
                   AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
                 ORDER BY c.relname",
            ),
        );
    }

    private function quotedSchemaName(): string
    {
        return '"' . str_replace('"', '""', $this->schemaName) . '"';
    }
}

final class RecordingPhase implements KernelBlueprintTestPhase
{
    public int $calls = 0;
    public ?string $receivedBlueprintId = null;

    public function run(string $blueprintId): void
    {
        $this->calls++;
        $this->receivedBlueprintId = $blueprintId;
    }
}

final class FailingPhase implements KernelBlueprintTestPhase
{
    public int $calls = 0;

    public function run(string $blueprintId): void
    {
        $this->calls++;
        throw new RuntimeException('simulated phase failure');
    }
}

final class HarnessPhase1FakeQuestionApiClient extends QuestionApiClient
{
    public int $calls = 0;

    /** @param array<int, Response|\Throwable|callable(array<string, mixed>): Response> $queue */
    public function __construct(private array $queue) {}

    public function postAdmin(string $endpoint, array $payload, array $opts = []): Response
    {
        $this->calls++;
        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        if (is_callable($next)) {
            return $next($payload);
        }
        if (! $next instanceof Response) {
            throw new RuntimeException('Fake response queue exhausted.');
        }

        return $next;
    }
}