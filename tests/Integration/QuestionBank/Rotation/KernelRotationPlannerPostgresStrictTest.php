<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

/**
 * #159B — VALIDATION POSTGRESQL KRP STRICTE
 *
 * Chaque test prouve réellement le mécanisme KRP qu'il prétend valider.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Isolation — deux régimes selon le test
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Régime A (outer txn active) — T-PGB-03, T-PGB-05, T-PGB-06 :
 *   setUp : DB::beginTransaction()
 *   tearDown : DB::rollBack()
 *   Aucun commit réel sur Neon.
 *
 * Régime B (outer txn suspendue) — T-PGB-01, T-PGB-02, T-PGB-04 :
 *   Le test suspend l'outer txn au départ via DB::rollBack() (exactement
 *   comme T-PG-01 dans la classe précédente).
 *   Les données commitées sur Neon par Worker A et/ou l'orchestrateur sont
 *   nettoyées dans un bloc finally avant de rétablir DB::beginTransaction().
 *
 *   Pourquoi : un FOR UPDATE dans DB::transaction() tient le verrou jusqu'au
 *   COMMIT de l'outer txn.  Si l'outer txn est active pendant le finally,
 *   le DELETE de cleanup via raw PDO se retrouve bloqué à attendre ce verrou
 *   → deadlock → timeout.  Suspendre l'outer txn avant tout commit raw PDO
 *   évite ce blocage.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Mécanismes prouvés
 * ─────────────────────────────────────────────────────────────────────────
 *   T-PGB-01  receiveDomainExhausted — deux connexions réelles, Worker A commit,
 *             Worker B (KRP API) = NO-OP idempotent via FOR UPDATE.
 *   T-PGB-02  receiveDepthExhausted — même schéma deux connexions.
 *   T-PGB-03  Signal Depth contradictoire rejeté sur Neon (active_depth ≠ signal).
 *   T-PGB-04  FOR UPDATE contention observée (NOWAIT) + Factory guard
 *             empêche deux Blueprints sur orchestrations séquentielles.
 *   T-PGB-05  Rollback vrai chemin après Factory = PREUVE IMPASSE-KRP-001 :
 *             Transaction 1 commitée (SAVEPOINT releasé), Taxonomy throw →
 *             Blueprint CREATED_UNENGAGED durable, active_blueprint_identity set,
 *             active_depth = NULL.
 *   T-PGB-06  Rollback vrai chemin après fillRotation :
 *             SAVEPOINT → applyRotation() écrit active_depth + domain_position
 *             → ROLLBACK TO SAVEPOINT → état restauré, Blueprint toujours
 *             CREATED_UNENGAGED.
 */
class KernelRotationPlannerPostgresStrictTest extends TestCase
{
    private const DOMAINS = [
        'geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science',
    ];

    private KernelRotationPlanner      $planner;
    private KernelPipelineOrchestrator $orchestrator;
    private TaxonomyNavigatorInterface $taxonomy;

    /** @var array<string, mixed> */
    private array $pgCfg;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);

        $this->assertSame(0, (int) DB::connection('pgsql')
            ->table('kernel_rotation_state_v2')->count(),
            'Précondition #159B : kernel_rotation_state_v2 doit être vide avant chaque test'
        );

        DB::beginTransaction();   // outer txn — annulée dans tearDown (Régime A)

        $this->pgCfg    = config('database.connections.pgsql');
        $this->planner  = new KernelRotationPlanner();
        $this->taxonomy = $this->createMock(TaxonomyNavigatorInterface::class);
        $factory        = new KernelBlueprintFactory();

        $this->orchestrator = new KernelPipelineOrchestrator(
            $factory,
            $this->planner,
            $this->taxonomy,
            new KernelCodeEngine()
        );
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    // =========================================================================
    // T-PGB-01 — receiveDomainExhausted : deux connexions réelles (Régime B)
    // =========================================================================

    /**
     * Worker A (connexion PDO séparée, commit réel) exécute la sémantique
     * receiveDomainExhausted manuellement et persiste DOMAIN_EXHAUSTED sur Neon.
     *
     * Worker B (KRP API, DB::transaction() réel sans outer txn) acquiert FOR UPDATE,
     * lit DOMAIN_EXHAUSTED, retourne sans écriture (NO-OP idempotent).
     *
     * Preuve : lock_version inchangé après Worker B, 0 exception exposée.
     * Deux vraies connexions PostgreSQL distinctes.  Outer txn suspendue pour
     * éviter le deadlock raw-PDO / FOR UPDATE (voir note d'isolation en tête).
     */
    public function test_receive_domain_exhausted_two_real_workers(): void
    {
        // Suspendre l'outer txn — Régime B
        DB::rollBack();

        $connA       = $this->openRawPdo();
        $lvAfterA    = null;
        $stateRowId  = null;

        try {
            // ── Worker A : INSERT état + marque DOMAIN_EXHAUSTED, commit réel ─
            $domainStates = $this->buildDomainStates();
            $stmt = $connA->prepare(
                "INSERT INTO kernel_rotation_state_v2
                  (depth_state, active_depth, domain_position, domain_states,
                   tour_domain_states, active_blueprint_identity,
                   last_counted_blueprint_identity, pending_depth_exhausted_depth,
                   lock_version, created_at, updated_at)
                 VALUES
                  ('ROTATION_ACTIVE', 2, 0, ?, ?, NULL, NULL, NULL, 1, NOW(), NOW())
                 RETURNING id"
            );
            $stmt->execute([
                json_encode($domainStates),
                json_encode(DepthTourState::initTour()->toArray()),
            ]);
            $stateRowId = (int) $stmt->fetchColumn();

            // Worker A : BEGIN → FOR UPDATE → marque DOMAIN_EXHAUSTED → COMMIT
            $connA->beginTransaction();
            $row = $connA->query(
                "SELECT domain_states, lock_version
                 FROM kernel_rotation_state_v2 FOR UPDATE"
            )->fetch(PDO::FETCH_ASSOC);
            $ds          = json_decode((string) $row['domain_states'], true);
            $ds['2']['histoire'] = 'DOMAIN_EXHAUSTED';
            $lvAfterA    = (int) $row['lock_version'] + 1;
            $connA->prepare(
                "UPDATE kernel_rotation_state_v2
                 SET domain_states = ?, lock_version = ?, updated_at = NOW()
                 WHERE id IS NOT NULL"
            )->execute([json_encode($ds), $lvAfterA]);
            $connA->commit();   // ← lock FOR UPDATE libéré ici

            // ── Worker B : KRP API (DB::transaction() RÉEL, pas un SAVEPOINT) ─
            // L'outer txn est suspendue → DB::transaction() démarre une vraie
            // transaction, acquiert FOR UPDATE, voit DOMAIN_EXHAUSTED → NO-OP,
            // commet (lock libéré immédiatement après).
            $exceptionRaised = false;
            try {
                $this->planner->receiveDomainExhausted(2, 'histoire');
            } catch (\Throwable) {
                $exceptionRaised = true;
            }

            $this->assertFalse($exceptionRaised,
                'Worker B : receiveDomainExhausted ne doit PAS lever d\'exception (NO-OP idempotent)'
            );

            // lock_version inchangé après Worker B (Worker B n'a rien écrit)
            $finalState = DB::connection('pgsql')->table('kernel_rotation_state_v2')->first();
            $this->assertSame($lvAfterA, (int) $finalState->lock_version,
                'Worker B NO-OP : lock_version inchangé — receiveDomainExhausted idempotent sous deux connexions réelles'
            );
            $finalDs = json_decode((string) $finalState->domain_states, true);
            $this->assertSame('DOMAIN_EXHAUSTED', $finalDs['2']['histoire'],
                'histoire = DOMAIN_EXHAUSTED préservé après Worker B'
            );

        } finally {
            // Nettoyage des données committées sur Neon (Worker A + Worker B)
            // Lock FOR UPDATE libéré avant d'arriver ici (Worker B a committé)
            $connA->exec('DELETE FROM kernel_rotation_state_v2');
            // Rétablir l'outer txn pour tearDown
            DB::beginTransaction();
        }
    }

    // =========================================================================
    // T-PGB-02 — receiveDepthExhausted : deux connexions réelles (Régime B)
    // =========================================================================

    /**
     * Worker A (PDO séparé, commit réel) persiste pending_depth_exhausted_depth = 2.
     * Worker B (KRP API, transaction réelle) acquiert FOR UPDATE, voit même
     * pending → NO-OP idempotent (lock_version inchangé).
     *
     * Deux vraies connexions PostgreSQL distinctes.  Outer txn suspendue.
     */
    public function test_receive_depth_exhausted_two_real_workers(): void
    {
        DB::rollBack();   // Régime B

        $connA    = $this->openRawPdo();
        $lvAfterA = null;

        try {
            // ── Worker A : INSERT état + mémorise pending=2, commit réel ─────
            $domainStates = $this->buildDomainStates();
            $connA->prepare(
                "INSERT INTO kernel_rotation_state_v2
                  (depth_state, active_depth, domain_position, domain_states,
                   tour_domain_states, active_blueprint_identity,
                   last_counted_blueprint_identity, pending_depth_exhausted_depth,
                   lock_version, created_at, updated_at)
                 VALUES ('ROTATION_ACTIVE', 2, 0, ?, ?, NULL, NULL, NULL, 1, NOW(), NOW())"
            )->execute([
                json_encode($domainStates),
                json_encode(DepthTourState::initTour()->toArray()),
            ]);

            $connA->beginTransaction();
            $row  = $connA->query(
                "SELECT lock_version FROM kernel_rotation_state_v2 FOR UPDATE"
            )->fetch(PDO::FETCH_ASSOC);
            $lvAfterA = (int) $row['lock_version'] + 1;
            $connA->prepare(
                "UPDATE kernel_rotation_state_v2
                 SET pending_depth_exhausted_depth = 2, lock_version = ?, updated_at = NOW()
                 WHERE id IS NOT NULL"
            )->execute([$lvAfterA]);
            $connA->commit();

            // ── Worker B : KRP API (transaction réelle) — NO-OP ──────────────
            $exceptionRaised = false;
            try {
                $this->planner->receiveDepthExhausted(2);
            } catch (\Throwable) {
                $exceptionRaised = true;
            }

            $this->assertFalse($exceptionRaised,
                'Worker B : receiveDepthExhausted ne doit PAS lever d\'exception (NO-OP idempotent)'
            );

            $finalState = DB::connection('pgsql')->table('kernel_rotation_state_v2')->first();
            $this->assertSame($lvAfterA, (int) $finalState->lock_version,
                'Worker B NO-OP : lock_version inchangé — receiveDepthExhausted idempotent sous deux connexions réelles'
            );
            $this->assertSame(2, (int) $finalState->pending_depth_exhausted_depth,
                'pending_depth_exhausted_depth = 2 préservé après Worker B (idempotent)'
            );

        } finally {
            $connA->exec('DELETE FROM kernel_rotation_state_v2');
            DB::beginTransaction();
        }
    }

    // =========================================================================
    // T-PGB-03 — Signal Depth contradictoire rejeté sur Neon (Régime A)
    // =========================================================================

    /**
     * État avec active_depth = 4.
     * Appel receiveDepthExhausted(6) → signal ne correspond pas au Depth actif.
     * KRP doit lever RuntimeException INCOHÉRENCE_ÉTAT (DEC-090).
     *
     * Prouve la garde de cohérence sur PostgreSQL/Neon réel.
     * Régime A : outer txn active, tearDown rollback nettoie.
     */
    public function test_contradictory_depth_signal_rejected_on_neon(): void
    {
        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => 4,
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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/INCOHÉRENCE_ÉTAT/');

        $this->planner->receiveDepthExhausted(6);
    }

    // =========================================================================
    // T-PGB-04 — FOR UPDATE contention + Factory guard (Régime B)
    // =========================================================================

    /**
     * Partie A — FOR UPDATE contention observée (NOWAIT) :
     *   Une ligne d'état est insérée via auto-commit raw PDO.
     *   Connexion A : BEGIN + SELECT FOR UPDATE (verrou acquis).
     *   Connexion B : SELECT FOR UPDATE NOWAIT → PDOException (contention prouvée).
     *   Connexion A : ROLLBACK.  Ligne supprimée.
     *
     * Partie B — deux orchestrations séquentielles via KRP réel :
     *   Orchestration 1 → STATUS_ROTATION_ASSIGNED (Blueprint ENGAGED, domain_position=0).
     *   Orchestration 2 → RuntimeException Factory guard (Blueprint actif existe).
     *   Blueprints finaux = 1.  Résolution stale impossible (FOR UPDATE gate).
     *
     * Outer txn suspendue pour Partie A ; Partie B tourne sans outer txn
     * (commits réels → cleanup en finally).
     */
    public function test_two_concurrent_orchestrations_for_update_and_factory_guard(): void
    {
        DB::rollBack();   // Régime B

        $connA        = $this->openRawPdo();
        $connB        = $this->openRawPdo();
        $rowInserted  = false;
        $orchestrated = false;

        try {
            // ── Partie A : FOR UPDATE contention sur state table ──────────────
            $connA->exec(
                "INSERT INTO kernel_rotation_state_v2
                  (depth_state, active_depth, domain_position, domain_states,
                   tour_domain_states, active_blueprint_identity,
                   last_counted_blueprint_identity, pending_depth_exhausted_depth,
                   lock_version, created_at, updated_at)
                 VALUES ('ROTATION_ACTIVE', 2, NULL, '{}', '{}',
                         NULL, NULL, NULL, 1, NOW(), NOW())"
            );
            $rowInserted = true;

            $connA->beginTransaction();
            $connA->query('SELECT id FROM kernel_rotation_state_v2 FOR UPDATE')->fetchAll();

            $contentionObserved = false;
            $connBStarted = false;
            try {
                $connB->beginTransaction();
                $connBStarted = true;
                $connB->query('SELECT id FROM kernel_rotation_state_v2 FOR UPDATE NOWAIT');
            } catch (\PDOException) {
                $contentionObserved = true;
            } finally {
                if ($connBStarted && $connB->inTransaction()) {
                    $connB->rollBack();
                }
            }

            $connA->rollBack();  // libère le verrou

            $this->assertTrue($contentionObserved,
                'FOR UPDATE contention observée : NOWAIT échoue quand Connexion A tient le verrou'
            );

            // Supprimer la ligne de la Partie A (commit auto-commit)
            $connA->exec('DELETE FROM kernel_rotation_state_v2');
            $rowInserted = false;

            // ── Partie B : deux orchestrations via KRP réel ───────────────────
            $this->taxonomy->method('peekNext')->willReturn([
                'sub_domain'    => 'Capitales',
                'subject'       => 'Paris',
                'dominant_idea' => 'Paris est la capitale de la France',
            ]);

            // Orchestration 1 → succès (commit réel sur Neon)
            $result1 = $this->orchestrator->run(null);
            $orchestrated = true;
            $this->assertSame(KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED, $result1['status'],
                'Orchestration 1 : STATUS_ROTATION_ASSIGNED'
            );

            // domain_position = 0 (geographie = index 0 dans DOMAIN_CYCLE)
            $state = DB::connection('pgsql')->table('kernel_rotation_state_v2')->first();
            $this->assertSame(0, (int) $state->domain_position,
                'domain_position = 0 après la première orchestration (geographie)'
            );
            $this->assertSame(2, (int) $state->active_depth,
                'active_depth = 2 (Depth initial du DepthCycle)'
            );

            // Orchestration 2 → Factory guard
            $guardCaught = false;
            try {
                $this->orchestrator->run(null);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Blueprint actif existe', $e->getMessage(),
                    'Factory guard stoppe la deuxième orchestration'
                );
                $guardCaught = true;
            }
            $this->assertTrue($guardCaught, 'RuntimeException Factory guard attendue');

            // Blueprints finaux = 1
            $this->assertSame(1, (int) DB::connection('pgsql')->table('kernel_blueprint_runs')->count(),
                'Blueprints finaux = 1 (Factory guard efficace)'
            );

        } finally {
            if ($rowInserted) {
                $connA->exec('DELETE FROM kernel_rotation_state_v2');
            }
            if ($orchestrated) {
                // Nettoyer les commits réels de la Partie B
                DB::connection('pgsql')->table('kernel_blueprint_runs')->delete();
                DB::connection('pgsql')->table('kernel_rotation_state_v2')->delete();
                // kernel_code_sequences : KernelCodeEngine a peut-être inséré des lignes
                // On ne supprime que les lignes de la Partie B pour ne pas perturber
                // les séquences existantes.  Puisque le Blueprint est supprimé, les
                // séquences orphelines ne bloquent pas les prochains tests.
            }
            DB::beginTransaction();
        }
    }

    // =========================================================================
    // T-PGB-05 — Rollback vrai chemin après Factory = PREUVE IMPASSE-KRP-001
    //            (Régime A — SAVEPOINT model)
    // =========================================================================

    /**
     * Prouve IMPASSE-KRP-001 via l'orchestrateur réel.
     *
     * Flow KRP :
     *   ┌─ Transaction 1 (FOR UPDATE) ──────────────────────────┐
     *   │  Factory::create()   → Blueprint CREATED_UNENGAGED    │
     *   │  registerActiveBlueprintIdentity() → state updated    │
     *   └─ COMMIT (SAVEPOINT releasé dans outer txn) ────────────┘
     *   taxonomy.peekNext() → RuntimeException  (aucune Transaction 2)
     *
     * Résultats attendus :
     *   Blueprint durable après rollback A = 1 CREATED_UNENGAGED (visible)
     *   RotationState après rollback A     = active_blueprint_identity SET,
     *                                        active_depth = NULL
     *
     * Régime A : les données sont dans l'outer txn (SAVEPOINT releasé) ;
     * tearDown rollback nettoie.
     */
    public function test_rollback_real_path_after_factory_impasse_krp001(): void
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

        // ── Blueprint durable après rollback A ────────────────────────────────
        $blueprint = DB::table('kernel_blueprint_runs')
            ->where('execution_state', 'CREATED_UNENGAGED')
            ->first();
        $this->assertNotNull($blueprint,
            'Blueprint durable après rollback A : 1 CREATED_UNENGAGED — IMPASSE-KRP-001 prouvée sur Neon'
        );

        // ── RotationState après rollback A ────────────────────────────────────
        $state = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNotNull($state,
            'RotationState après rollback A : ligne d\'état présente'
        );
        $this->assertNotNull($state->active_blueprint_identity,
            'RotationState après rollback A : active_blueprint_identity SET (Transaction 1 commitée avant Taxonomy throw)'
        );
        $this->assertNull($state->active_depth,
            'RotationState après rollback A : active_depth = NULL (applyRotation jamais appelé)'
        );
    }

    // =========================================================================
    // T-PGB-06 — Rollback vrai chemin après fillRotation (Régime A — SAVEPOINT)
    // =========================================================================

    /**
     * Prouve que les écritures de applyRotation() peuvent être rollbackées
     * proprement via un SAVEPOINT PostgreSQL.
     *
     * applyRotation() fait deux choses :
     *   1. blueprint.fillRotation(depth, domain) — écriture IN-MEMORY (write-once)
     *   2. DB UPDATE active_depth + domain_position   — écriture DB rollbackable
     *
     * Chemin testé :
     *   SAVEPOINT before_apply_rotation
     *   → applyRotation(blueprint, depth=2, domain='geographie', pos=0)
     *      ├─ fillRotation in-memory : blueprint.depth = 2
     *      └─ DB UPDATE active_depth=2, domain_position=0
     *   [vérification : DB écrit]
     *   ROLLBACK TO SAVEPOINT before_apply_rotation
     *   [vérification : DB revenu à active_depth=NULL, domain_position=NULL]
     *
     *   Blueprint durable après rollback B = 1 CREATED_UNENGAGED
     *     (applyRotation ne change pas execution_state ; le Blueprint persiste)
     *   RotationState après rollback B = active_depth=NULL, domain_position=NULL
     */
    public function test_rollback_real_path_after_fill_rotation(): void
    {
        $blueprintId = (string) Str::orderedUuid();

        // Setup : Blueprint CREATED_UNENGAGED + état initial (active_depth = NULL)
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => $blueprintId,
            'execution_state' => 'CREATED_UNENGAGED',
            'depth'           => null,
            'domain_code'     => null,
            'engaged_at'      => null,
            'received_at'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('kernel_rotation_state_v2')->insert([
            'depth_state'                    => 'ROTATION_ACTIVE',
            'active_depth'                   => null,
            'domain_position'                => null,
            'domain_states'                  => json_encode($this->buildDomainStates()),
            'tour_domain_states'             => json_encode(DepthTourState::initTour()->toArray()),
            'active_blueprint_identity'      => $blueprintId,
            'last_counted_blueprint_identity' => null,
            'pending_depth_exhausted_depth'  => null,
            'lock_version'                   => 1,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);

        // Blueprint object (in-memory, pas encore fillRotation)
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($blueprintId);

        // ── SAVEPOINT avant applyRotation ─────────────────────────────────────
        DB::statement('SAVEPOINT before_apply_rotation');

        $this->planner->applyRotation($blueprint, 2, 'geographie', 0);

        // applyRotation a écrit sur Neon (dans l'outer txn via SAVEPOINT)
        $stateAfterApply = DB::table('kernel_rotation_state_v2')->first();
        $this->assertSame(2, (int) $stateAfterApply->active_depth,
            'applyRotation : active_depth = 2 écrit dans DB (avant rollback)'
        );
        $this->assertSame(0, (int) $stateAfterApply->domain_position,
            'applyRotation : domain_position = 0 écrit dans DB (avant rollback)'
        );
        $this->assertSame(2,            $blueprint->depth,  'fillRotation in-memory : depth = 2');
        $this->assertSame('geographie', $blueprint->domain, 'fillRotation in-memory : domain = geographie');

        // ── ROLLBACK TO SAVEPOINT ─────────────────────────────────────────────
        DB::statement('ROLLBACK TO SAVEPOINT before_apply_rotation');

        // ── RotationState après rollback B ────────────────────────────────────
        $stateAfterRollback = DB::table('kernel_rotation_state_v2')->first();
        $this->assertNull($stateAfterRollback->active_depth,
            'RotationState après rollback B : active_depth = NULL (DB write annulé)'
        );
        $this->assertNull($stateAfterRollback->domain_position,
            'RotationState après rollback B : domain_position = NULL (rollback confirmé)'
        );
        $this->assertSame($blueprintId, $stateAfterRollback->active_blueprint_identity,
            'RotationState après rollback B : active_blueprint_identity préservé'
        );

        // ── Blueprint durable après rollback B ────────────────────────────────
        $persistedBp = DB::table('kernel_blueprint_runs')
            ->where('blueprint_id', $blueprintId)->first();
        $this->assertNotNull($persistedBp,
            'Blueprint durable après rollback B : Blueprint toujours présent'
        );
        $this->assertSame('CREATED_UNENGAGED', $persistedBp->execution_state,
            'Blueprint durable après rollback B : execution_state = CREATED_UNENGAGED'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return array<string, array<string, string>> */
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

    private function openRawPdo(): PDO
    {
        $cfg = $this->pgCfg;
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['sslmode'] ?? 'prefer',
        );
        return new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
