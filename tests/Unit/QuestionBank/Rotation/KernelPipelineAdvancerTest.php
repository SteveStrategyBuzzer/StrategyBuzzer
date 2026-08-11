<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\KernelBlueprintReadyBankReceiver;
use App\Services\QuestionBank\Rotation\KernelBlueprintRunRepository;
use App\Services\QuestionBank\Rotation\KernelPipelineAdvancer;
use App\Services\QuestionBank\Rotation\KernelPipelineOutboxRepository;
use App\Services\QuestionBank\Rotation\QuestionIntentEncoder;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * KernelPipelineAdvancer — tapis roulant du flow canonique (2026-08-11).
 *
 * Couvre : dispatch des étapes par frame_status, réception ReadyBank
 * (y compris DEC-052 : quarantine reçue, rotation jamais bloquée),
 * récupération d'un Blueprint engagé sans intent, garde-fou advance_attempts.
 *
 * Le stageRunner est injecté : AUCUNE commande Artisan réelle (donc aucun
 * appel Gemini) n'est exécutée dans ces tests.
 *
 * DB : SQLite in-memory, tables créées manuellement (PATTERN sans RefreshDatabase).
 */
class KernelPipelineAdvancerTest extends TestCase
{
    private const BP_ID = 'bp-adv-0001';

    /** @var array<int, array{command: string, params: array}> */
    private array $stageCalls = [];

    /** Code de sortie simulé des commandes de phase. */
    private int $stageExit = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->timestamp('engaged_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_pipeline_outbox', function (Blueprint $table) {
            $table->string('event_id', 36)->primary();
            $table->string('event_type', 128);
            $table->integer('schema_version')->default(1);
            $table->text('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('question_intents', function (Blueprint $table) {
            $table->id();
            $table->string('intent_key')->unique();
            $table->string('semantic_key', 255)->nullable();
            $table->string('language_source', 8)->default('en');
            $table->string('domain', 64);
            $table->string('sub_domain', 256);
            $table->unsignedTinyInteger('difficulty_depth');
            $table->string('subject', 256)->nullable();
            $table->string('dominant_idea', 512)->nullable();
            $table->string('angle_large', 512)->nullable();
            $table->string('micro_angle', 512)->nullable();
            $table->text('answer_target')->nullable();
            $table->string('concept_family', 256)->nullable();
            $table->string('source', 32)->default('ai_pipeline');
            $table->string('frame_status', 32)->nullable();
            $table->char('blueprint_id', 36)->nullable()->unique();
            $table->unsignedTinyInteger('advance_attempts')->default(0);
            $table->timestamps();
        });

        $this->stageCalls = [];
        $this->stageExit  = 0;
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('question_intents');
        Schema::dropIfExists('kernel_pipeline_outbox');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdvancer(?array $territory = null): KernelPipelineAdvancer
    {
        $navigator = new class($territory) implements TaxonomyNavigatorInterface {
            /** @var array<int, array{0: int, 1: string}> */
            public array $confirmed = [];

            public function __construct(private readonly ?array $territory) {}

            public function peekNext(int $depth, string $domainCode): ?array
            {
                return $this->territory;
            }

            public function confirmConsumed(int $depth, string $domainCode): void
            {
                $this->confirmed[] = [$depth, $domainCode];
            }
        };

        return new KernelPipelineAdvancer(
            new KernelBlueprintRunRepository(),
            $navigator,
            new QuestionIntentEncoder(),
            new KernelBlueprintReadyBankReceiver(
                new KernelBlueprintRunRepository(),
                new KernelPipelineOutboxRepository(),
            ),
            function (string $command, array $params): int {
                $this->stageCalls[] = ['command' => $command, 'params' => $params];

                return $this->stageExit;
            },
        );
    }

    private function insertRun(string $state = 'ENGAGED_IN_PIPELINE'): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => self::BP_ID,
            'execution_state' => $state,
            'depth'           => 2,
            'domain_code'     => 'geographie',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function insertIntent(?string $frameStatus, int $attempts = 0): int
    {
        return (int) DB::table('question_intents')->insertGetId([
            'intent_key'       => 'BP:' . self::BP_ID,
            'semantic_key'     => 'BP:' . self::BP_ID,
            'language_source'  => 'en',
            'domain'           => 'geographie',
            'sub_domain'       => 'Capitales européennes',
            'difficulty_depth' => 2,
            'subject'          => 'Paris',
            'dominant_idea'    => 'Paris est traversée par la Seine',
            'angle_large'      => 'Paris est traversée par la Seine',
            'micro_angle'      => 'Paris est traversée par la Seine',
            'answer_target'    => 'Paris est traversée par la Seine',
            'concept_family'   => 'Capitales européennes',
            'source'           => 'kernel_rotation',
            'frame_status'     => $frameStatus,
            'blueprint_id'     => self::BP_ID,
            'advance_attempts' => $attempts,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // =========================================================================
    // États sans travail
    // =========================================================================

    public function test_no_active_blueprint_returns_no_active(): void
    {
        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_NO_ACTIVE, $result['outcome']);
        $this->assertSame([], $this->stageCalls, 'Aucune étape dispatchée');
    }

    public function test_created_unengaged_returns_not_engaged(): void
    {
        $this->insertRun('CREATED_UNENGAGED');

        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_NOT_ENGAGED, $result['outcome']);
        $this->assertSame([], $this->stageCalls, 'L\'engagement appartient à KernelPipelineOrchestrator');
    }

    // =========================================================================
    // Récupération — Blueprint engagé sans intent (pré-raccordement A)
    // =========================================================================

    public function test_missing_intent_is_recovered_from_taxonomy_cursor(): void
    {
        $this->insertRun();

        $result = $this->makeAdvancer([
            'sub_domain'    => 'Capitales européennes',
            'subject'       => 'Paris',
            'dominant_idea' => 'Paris est traversée par la Seine',
        ])->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_INTENT_ENCODED, $result['outcome']);

        $intent = DB::table('question_intents')->where('blueprint_id', self::BP_ID)->first();
        $this->assertNotNull($intent, 'L\'intent doit être ré-encodé depuis le curseur Taxonomy');
        $this->assertSame('Paris est traversée par la Seine', $intent->dominant_idea);
    }

    public function test_missing_intent_with_empty_taxonomy_stops(): void
    {
        $this->insertRun();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sans QuestionIntent/');

        $this->makeAdvancer(null)->advance();
    }

    // =========================================================================
    // Dispatch des étapes par frame_status
    // =========================================================================

    public function test_null_status_dispatches_skeleton(): void
    {
        $this->insertRun();
        $intentId = $this->insertIntent(null);

        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_STAGE_ADVANCED, $result['outcome']);
        $this->assertCount(1, $this->stageCalls);
        $this->assertSame('questions:kernel:skeleton', $this->stageCalls[0]['command']);
        $this->assertSame($intentId, (int) $this->stageCalls[0]['params']['intent_id']);
    }

    public function test_draft_dispatches_validate_structure(): void
    {
        $this->insertRun();
        $this->insertIntent('draft');

        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_STAGE_ADVANCED, $result['outcome']);
        $this->assertSame('questions:kernel:validate-structure', $this->stageCalls[0]['command']);
    }

    public function test_awaiting_content_dispatches_fill_content(): void
    {
        $this->insertRun();
        $this->insertIntent('awaiting_content');

        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_STAGE_ADVANCED, $result['outcome']);
        $this->assertSame('questions:kernel:fill-content', $this->stageCalls[0]['command']);
    }

    // =========================================================================
    // Validation Phase 2 → réception ReadyBank au même tick
    // =========================================================================

    public function test_content_ready_validates_then_receives_same_tick(): void
    {
        $this->insertRun();
        $this->insertIntent('content_ready');

        $result = $this->makeAdvancer()->advance();

        // Validation Phase 2 dispatchée avec --apply
        $this->assertSame('questions:kernel:validate-content', $this->stageCalls[0]['command']);
        $this->assertTrue((bool) ($this->stageCalls[0]['params']['--apply'] ?? false));

        // Exit 0 + statut inchangé (policy A/B) → réception au même tick
        $this->assertSame(KernelPipelineAdvancer::OUTCOME_RECEIVED, $result['outcome']);

        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BP_ID)->first();
        $this->assertSame('READY_BANK_RECEIVED', $run->execution_state);

        $outbox = DB::table('kernel_pipeline_outbox')->where('event_type', 'CURRENT_KERNEL_RECEIVED')->get();
        $this->assertCount(1, $outbox, 'CURRENT_KERNEL_RECEIVED émis dans l\'outbox');
        $this->assertStringContainsString(self::BP_ID, $outbox->first()->payload);
    }

    public function test_content_validated_is_received(): void
    {
        $this->insertRun();
        $this->insertIntent('content_validated');

        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_RECEIVED, $result['outcome']);
        $this->assertSame([], $this->stageCalls, 'Aucune étape à rejouer — réception directe');
    }

    /**
     * DEC-052 : un noyau en quarantine est QUAND MÊME reçu — la rotation
     * ne se bloque jamais sur un contenu à corriger.
     */
    public function test_quarantine_is_received_dec052(): void
    {
        $this->insertRun();
        $this->insertIntent('quarantine');

        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_RECEIVED, $result['outcome']);

        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', self::BP_ID)->first();
        $this->assertSame('READY_BANK_RECEIVED', $run->execution_state);
    }

    // =========================================================================
    // Garde-fou advance_attempts — échec explicite, jamais silencieux
    // =========================================================================

    public function test_repeated_stage_failures_quarantine_then_receive(): void
    {
        $this->insertRun();
        $intentId = $this->insertIntent('draft');

        $this->stageExit = 1; // la validation de structure échoue systématiquement
        $advancer        = $this->makeAdvancer();

        // 4 premiers échecs → STAGE_FAILED, attempts s'incrémente
        for ($i = 1; $i <= KernelPipelineAdvancer::MAX_STAGE_ATTEMPTS - 1; $i++) {
            $result = $advancer->advance();
            $this->assertSame(KernelPipelineAdvancer::OUTCOME_STAGE_FAILED, $result['outcome'], "échec #{$i}");
        }

        // 5e échec → quarantaine explicite
        $result = $advancer->advance();
        $this->assertSame(KernelPipelineAdvancer::OUTCOME_QUARANTINED, $result['outcome']);

        $intent = DB::table('question_intents')->find($intentId);
        $this->assertSame('quarantine', $intent->frame_status);
        $this->assertSame(KernelPipelineAdvancer::MAX_STAGE_ATTEMPTS, (int) $intent->advance_attempts);

        // Tick suivant → réception DEC-052, la rotation continue
        $result = $advancer->advance();
        $this->assertSame(KernelPipelineAdvancer::OUTCOME_RECEIVED, $result['outcome']);
    }

    public function test_stage_success_resets_attempts(): void
    {
        $this->insertRun();
        $intentId = $this->insertIntent('draft', 3);

        $this->makeAdvancer()->advance(); // stageExit = 0 → succès

        $intent = DB::table('question_intents')->find($intentId);
        $this->assertSame(0, (int) $intent->advance_attempts, 'Succès ⇒ compteur remis à zéro');
    }

    // =========================================================================
    // frame_status inconnu — blocage explicite
    // =========================================================================

    public function test_unknown_status_is_blocked_explicitly(): void
    {
        $this->insertRun();
        $this->insertIntent('statut_inconnu');

        $result = $this->makeAdvancer()->advance();

        $this->assertSame(KernelPipelineAdvancer::OUTCOME_BLOCKED, $result['outcome']);
        $this->assertSame([], $this->stageCalls, 'Aucune étape dispatchée sur statut inconnu');
    }
}
