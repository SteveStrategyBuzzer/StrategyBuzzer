<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #159 — VALIDATION TERMINALE POSTGRESQL KRP
 *
 * Tous les tests s'exécutent sur la connexion PostgreSQL réelle (Neon).
 *
 * Isolation : DB::beginTransaction() dans setUp + DB::rollBack() dans tearDown.
 * Les appels internes DB::transaction() créent des SAVEPOINTs (comportement
 * Laravel sur une connexion déjà en transaction) — tout est annulé par le
 * rollback extérieur.
 *
 * Exception : T-PG-01 (FOR UPDATE à deux connexions) gère sa propre isolation.
 *
 * Invariants validés :
 *   - FOR UPDATE réel (lock contention via deux connexions PDO distinctes)
 *   - Deux orchestrations séquentielles → exactement un Blueprint (garde unique partielle)
 *   - CKR exactly-once (UNIQUE PK sur blueprint_id)
 *   - DepthCycle 4→6 sur Neon (receiveKernelReceivedV2)
 *   - Depth 10 → PRODUCTION_ON_HOLD sur Neon (DEPTH_CYCLE_NEXT)
 *   - receiveDomainExhausted idempotent sur Neon
 *   - receiveDepthExhausted idempotent sur Neon
 *   - Rollback PostgreSQL réel avant fillRotation (SAVEPOINT)
 *   - IMPASSE-KRP-001 : Blueprint CREATED_UNENGAGED durable sur Neon si Taxonomy échoue
 */
class KernelRotationPlannerPostgresTest extends TestCase
{
    private const DOMAINS = [
        'geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science',
    ];

    private KernelRotationPlanner      $planner;
    private KernelPipelineOrchestrator $orchestrator;
    private TaxonomyNavigatorInterface $taxonomy;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Force PostgreSQL réel (Neon) ───────────────────────────────────────
        // phpunit.xml impose DB_CONNECTION=sqlite via <env> + <server>.
        // config() prend le dessus une fois l'application bootée.
        config(['database.default' => 'pgsql']);

        // Précondition : tables KRP doivent être vides (auditées dans #158)
        $this->assertSame(0, (int) DB::connection('pgsql')
            ->table('kernel_rotation_state_v2')->count(),
            'Précondition #159 : kernel_rotation_state_v2 doit être vide avant chaque test'
        );

        // ── Transaction extérieure — tout est rollback dans tearDown ──────────
        DB::beginTransaction();

        // ── Services ──────────────────────────────────────────────────────────
        $this->taxonomy     = $this->createMock(TaxonomyNavigatorInterface::class);
        $this->planner      = new KernelRotationPlanner();
        $factory            = new KernelBlueprintFactory();
        $this->orchestrator = new KernelPipelineOrchestrator(
            $factory,
            $this->planner,
            $this->taxonomy,
            new KernelCodeEngine()
        );
    }

    protected function tearDown(): void
    {
        // Annule TOUTES les écritures du test (y compris les SAVEPOINTs internes)
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    // =========================================================================
    // T-PG-01 — FOR UPDATE réel : second connexion bloquée via NOWAIT
    // =========================================================================

    /**
     * Ouvre deux connexions PDO distinctes vers Neon.
     * Connexion A : INSERT + BEGIN + SELECT FOR UPDATE (verrouille la ligne).
     * Connexion B : BEGIN + SELECT FOR UPDATE NOWAIT → doit lever une PDOException.
     * Prouve que FOR UPDATE est réellement émis et respecté par PostgreSQL/Neon.
     *
     * Ce test gère sa propre isolation (rollback extérieur mis en pause).
     */
    public function test_for_update_lock_blocks_second_connection_via_nowait(): void
    {
        // Suspendre la transaction extérieure (T-PG-01 gère sa propre isolation)
        DB::rollBack();

        $cfg  = config('database.connections.pgsql');
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = $cfg['port'] ?? 5432;
        $db   = $cfg['database'] ?? 'postgres';
        $user = $cfg['username'] ?? 'postgres';
        $pass = $cfg['password'] ?? '';
        $ssl  = ($cfg['sslmode'] ?? 'prefer');
        $dsn  = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl}";

        $insertedId = null;
        try {
            // ── Connexion A : INSERT committed + verrou FOR UPDATE ─────────────
            $connA = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            // INSERT auto-commit (hors transaction) → ligne visible pour B
            $stmt = $connA->prepare("
                INSERT INTO kernel_rotation_state_v2
                  (depth_state, domain_states, tour_domain_states,
                   active_depth, domain_position, pending_depth_exhausted_depth,
                   active_blueprint_identity, last_counted_blueprint_identity,
                   lock_version, created_at, updated_at)
                VALUES
                  ('ROTATION_ACTIVE', '{}', '{}',
                   2, NULL, NULL, NULL, NULL, 1, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute();
            $insertedId = (int) $stmt->fetchColumn();

            // Connexion A acquiert le verrou FOR UPDATE dans une transaction
            $connA->beginTransaction();
            $lock = $connA->prepare('SELECT id FROM kernel_rotation_state_v2 FOR UPDATE');
            $lock->execute();
            $lock->fetchAll();

            // ── Connexion B : tente NOWAIT → doit échouer ─────────────────────
            $connB     = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $lockError = false;
            try {
                $connB->beginTransaction();
                $connB->query('SELECT id FROM kernel_rotation_state_v2 FOR UPDATE NOWAIT');
                $connB->rollBack();
            } catch (\PDOException) {
                $lockError = true;
                if ($connB->inTransaction()) {
                    $connB->rollBack();
                }
            }

            $connA->rollBack(); // libère le verrou

            $this->assertTrue(
                $lockError,
                'FOR UPDATE NOWAIT doit lever une PDOException si connexion A tient le verrou (Neon PostgreSQL)'
            );

            // Vérifier que la SQL générée par Laravel contient bien FOR UPDATE
            $sql = DB::connection('pgsql')->table('kernel_rotation_state_v2')
                ->lockForUpdate()->toSql();
            $this->assertStringContainsString('for update', strtolower($sql),
                'Laravel doit générer un clause FOR UPDATE sur PostgreSQL'
            );
        } finally {
            // Nettoyage de la ligne insérée (committed, hors transaction)
            if ($insertedId !== null) {
                DB::connection('pgsql')
                    ->table('kernel_rotation_state_v2')
                    ->where('id', $insertedId)
                    ->delete();
            }
            // Rétablir la transaction extérieure pour tearDown
            DB::beginTransaction();
        }
    }

    // =========================================================================
    // T-PG-02 — Deux orchestrations séquentielles → exactement un Blueprint
    // =========================================================================

    public function test_two_sequential_orchestrations_produce_exactly_one_blueprint(): void
    {
        $this->taxonomy->method('peekNext')->willReturn([
            'sub_domain'    => 'Capitales',
            'subject'       => 'Paris',
            'dominant_idea' => 'Paris est la capitale de la France',
        ]);

        // Première orchestration → Blueprint créé + ENGAGED_IN_PIPELINE
        $result1 = $this->orchestrator->run(null);
        $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result1['status']);

        // Deuxième orchestration → factory guard stoppe
        $caught = false;
        try {
            $this->orchestrator->run(null);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Blueprint actif existe', $e->getMessage(),
                'La deuxième orchestration doit être stoppée par la factory guard'
            );
            $caught = true;
        }
        $this->assertTrue($caught, 'RuntimeException attendue sur la deuxième orchestration');

        // Exactement un Blueprint dans kernel_blueprint_runs
        $count = DB::table('kernel_blueprint_runs')->count();
        $this->assertSame(1, $count,
            'Exactement un Blueprint après deux orchestrations concurrentes séquentielles sur Neon'
        );
    }

    // =========================================================================
    // T-PG-03 — CKR exactly-once : UNIQUE PK sur blueprint_id
    // =========================================================================

    public function test_ckr_exactly_once_unique_constraint_rejects_duplicate(): void
    {
        $blueprintId = (string) Str::orderedUuid();

        DB::table('kernel_current_kernel_receipts')->insert([
            'blueprint_id' => $blueprintId,
            'event_id'     => (string) Str::orderedUuid(),
            'depth'        => 2,
            'domain_code'  => 'geographie',
            'received_at'  => now(),
        ]);

        // Deuxième INSERT avec même blueprint_id (PK) → UNIQUE violation
        // Un SAVEPOINT isole l'erreur : en PostgreSQL, une erreur dans une transaction
        // l'annule entièrement ; le SAVEPOINT permet de ROLLBACK juste cette commande.
        $violated = false;
        DB::statement('SAVEPOINT ckr_unique_test');
        try {
            DB::table('kernel_current_kernel_receipts')->insert([
                'blueprint_id' => $blueprintId,
                'event_id'     => (string) Str::orderedUuid(),
                'depth'        => 2,
                'domain_code'  => 'geographie',
                'received_at'  => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $violated = true;
            DB::statement('ROLLBACK TO SAVEPOINT ckr_unique_test');
        } catch (\Exception) {
            $violated = true;
            DB::statement('ROLLBACK TO SAVEPOINT ckr_unique_test');
        }

        $this->assertTrue($violated,
            'UNIQUE(blueprint_id) doit rejeter le deuxième INSERT dans kernel_current_kernel_receipts'
        );

        // Exactement un reçu (le SAVEPOINT permet de lire normalement après l'erreur)
        $count = DB::table('kernel_current_kernel_receipts')
            ->where('blueprint_id', $blueprintId)->count();
        $this->assertSame(1, $count, 'CKR exactly-once : un seul reçu par blueprint_id sur Neon');
    }

    // =========================================================================
    // T-PG-04 — DepthCycle 4→6 sur Neon (receiveKernelReceivedV2)
    // =========================================================================

    public function test_depth_transition_4_to_6_on_neon(): void
    {
        $this->insertStateWithPending(4);

        $this->planner->receiveKernelReceivedV2('pg-bp-d4', 4, 'geographie');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame('ROTATION_ACTIVE', $state->depth_state,
            '4→6 sur Neon : depth_state = ROTATION_ACTIVE'
        );
        $this->assertSame(6, (int) $state->active_depth,
            'DepthCycle 4→6 sur Neon PostgreSQL réel'
        );
        $this->assertNull($state->pending_depth_exhausted_depth,
            'pending réinitialisé après 4→6 sur Neon'
        );
        $this->assertNull($state->domain_position,
            'domain_position réinitialisé après 4→6 sur Neon'
        );
    }

    // =========================================================================
    // T-PG-05 — Depth 10 → PRODUCTION_ON_HOLD sur Neon
    // =========================================================================

    public function test_depth_10_to_production_on_hold_on_neon(): void
    {
        $this->insertStateWithPending(10);

        $this->planner->receiveKernelReceivedV2('pg-bp-d10', 10, 'geographie');

        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame('PRODUCTION_ON_HOLD', $state->depth_state,
            'Depth 10 → PRODUCTION_ON_HOLD sur Neon PostgreSQL réel (DEPTH_CYCLE_NEXT)'
        );
        $this->assertNull($state->pending_depth_exhausted_depth,
            'pending réinitialisé après 10→POH sur Neon'
        );

        // Preuve : cycle_completed[10] non modifié (incrementCycleCompleted non appelé)
        $matrix = DB::table('kernel_depth_matrix')->where('depth', 10)->first();
        $this->assertSame(0, (int) $matrix->cycle_completed,
            'cycle_completed[10] = 0 — incrementCycleCompleted non appelé (DEPTH_CYCLE_NEXT figé)'
        );
    }

    // =========================================================================
    // T-PG-06 — receiveDomainExhausted idempotent sur Neon
    // =========================================================================

    public function test_receive_domain_exhausted_idempotent_on_neon(): void
    {
        $this->insertInitialState(2);

        // Premier appel : histoire → DOMAIN_EXHAUSTED
        $this->planner->receiveDomainExhausted(2, 'histoire');

        $ds1 = json_decode(DB::table('kernel_rotation_state_v2')->value('domain_states'), true);
        $this->assertSame('DOMAIN_EXHAUSTED', $ds1['2']['histoire'] ?? null,
            'histoire = DOMAIN_EXHAUSTED après premier appel sur Neon'
        );
        $lv1 = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');

        // Deuxième appel identique — idempotent : lock_version inchangé
        $this->planner->receiveDomainExhausted(2, 'histoire');

        $ds2 = json_decode(DB::table('kernel_rotation_state_v2')->value('domain_states'), true);
        $this->assertSame('DOMAIN_EXHAUSTED', $ds2['2']['histoire'] ?? null,
            'État inchangé après appel idempotent sur Neon'
        );
        $lv2 = (int) DB::table('kernel_rotation_state_v2')->value('lock_version');
        $this->assertSame($lv1, $lv2,
            'lock_version identique — receiveDomainExhausted idempotent sur Neon'
        );
    }

    // =========================================================================
    // T-PG-07 — receiveDepthExhausted idempotent sur Neon
    // =========================================================================

    public function test_receive_depth_exhausted_idempotent_on_neon(): void
    {
        $this->insertInitialState(2);

        $this->planner->receiveDepthExhausted(2);

        $pending1 = (int) DB::table('kernel_rotation_state_v2')->value('pending_depth_exhausted_depth');
        $this->assertSame(2, $pending1, 'pending = 2 après premier appel sur Neon');

        // Deuxième appel identique — idempotent
        $this->planner->receiveDepthExhausted(2);

        $pending2 = (int) DB::table('kernel_rotation_state_v2')->value('pending_depth_exhausted_depth');
        $this->assertSame(2, $pending2, 'receiveDepthExhausted idempotent sur Neon — pending inchangé');
    }

    // =========================================================================
    // T-PG-08 — Rollback PostgreSQL avant fillRotation
    // =========================================================================

    /**
     * Prouve qu'un SAVEPOINT + ROLLBACK TO SAVEPOINT annule réellement les
     * écritures sur PostgreSQL/Neon.
     *
     * Simule le cas : exception dans la Transaction 1 de run() (avant fillRotation).
     * Après rollback : 0 Blueprints durables, 0 lignes d'état.
     */
    public function test_rollback_before_fill_rotation_leaves_zero_blueprints_on_neon(): void
    {
        // SAVEPOINT via DB::statement() — DB::savepoint() n'existe pas sur PostgresConnection
        DB::statement('SAVEPOINT pg_test_rollback');

        $blueprintId = (string) Str::orderedUuid();

        // Simule les deux écritures de la Transaction 1 de run()
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => $blueprintId,
            'execution_state' => 'CREATED_UNENGAGED',
            'depth'           => null,
            'domain_code'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                     => 'ROTATION_ACTIVE',
            'domain_states'                   => '{}',
            'tour_domain_states'              => '{}',
            'active_blueprint_identity'       => $blueprintId,
            'last_counted_blueprint_identity' => null,
            'lock_version'                    => 1,
            'created_at'                      => now(),
            'updated_at'                      => now(),
        ]);

        // Dans la transaction, les lignes sont visibles
        $this->assertSame(1, (int) DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)->count(),
            'Blueprint visible dans la transaction avant rollback'
        );

        // Rollback au savepoint (simule l'exception dans Transaction 1)
        DB::statement('ROLLBACK TO SAVEPOINT pg_test_rollback');

        // Après rollback : 0 Blueprints, 0 état
        $this->assertSame(0, (int) DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)->count(),
            'Rollback PostgreSQL réel : Blueprint annulé — 0 Blueprints durables'
        );
        $this->assertSame(0, (int) DB::table('kernel_rotation_state_v2')->count(),
            'Rollback PostgreSQL réel : état KRP annulé'
        );
    }

    // =========================================================================
    // T-PG-09 — IMPASSE-KRP-001 sur Neon : Blueprint CREATED_UNENGAGED durable
    // =========================================================================

    /**
     * Prouve IMPASSE-KRP-001 sur PostgreSQL réel :
     *   Transaction 1 COMMIT → Blueprint CREATED_UNENGAGED durable sur Neon
     *   → Taxonomy lève une RuntimeException
     *   → Blueprint reste CREATED_UNENGAGED (aucun recovery disponible sans 03_Taxonomy)
     *
     * NB : l'orchestrateur ne fait PAS de cleanup quand peekNext() THROWS (vs null).
     * peekNext() == null → cleanupBlueprint (DEC-087). Exception → Blueprint orphelin.
     */
    public function test_blueprint_stays_created_unengaged_when_taxonomy_throws_on_neon(): void
    {
        $this->taxonomy->method('peekNext')
            ->willThrowException(new \RuntimeException('Taxonomy indisponible — IMPASSE-KRP-001'));

        $caught = false;
        try {
            $this->orchestrator->run(null);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Taxonomy indisponible', $e->getMessage());
            $caught = true;
        }
        $this->assertTrue($caught, 'Exception Taxonomy propagée correctement');

        // Transaction 1 a committé avant l'exception Taxonomy
        // (l'outer transaction de test = SAVEPOINT → Blueprint visible dans la transaction)
        $blueprint = DB::table('kernel_blueprint_runs')
            ->where('execution_state', 'CREATED_UNENGAGED')
            ->first();

        $this->assertNotNull($blueprint,
            'IMPASSE-KRP-001 sur Neon : Blueprint CREATED_UNENGAGED persiste après échec Taxonomy'
        );
        $this->assertNotNull(
            DB::table('kernel_rotation_state_v2')->value('active_blueprint_identity'),
            'active_blueprint_identity enregistrée sur Neon (Transaction 1 committée)'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildDomainStates(): array
    {
        $states = [];
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            $key = (string) $depth;
            foreach (self::DOMAINS as $domain) {
                $states[$key][$domain] = 'ACTIF';
            }
        }
        return $states;
    }

    private function insertInitialState(int $depth): void
    {
        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => $depth,
            'domain_position'                => 0,
            'domain_states'                  => json_encode($this->buildDomainStates()),
            'tour_domain_states'             => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'      => null,
            'last_counted_blueprint_identity' => null,
            'pending_depth_exhausted_depth'  => null,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);
    }

    private function insertStateWithPending(int $depth): void
    {
        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => $depth,
            'domain_position'                => 3,
            'domain_states'                  => json_encode($this->buildDomainStates()),
            'tour_domain_states'             => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'      => null,
            'last_counted_blueprint_identity' => null,
            'pending_depth_exhausted_depth'  => $depth,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);
    }
}
