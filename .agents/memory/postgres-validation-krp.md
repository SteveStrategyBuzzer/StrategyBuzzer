---
name: Validation PostgreSQL terminale KRP — #159
description: 9 tests d'intégration PostgreSQL réels (Neon) validant FOR UPDATE, concurrence, CKR, DepthCycle, IMPASSE-KRP-001. Tous PASS 2026-08-14.
---

## #159 — Validation terminale PostgreSQL KRP (2026-08-14)

### Fichier
`tests/Integration/QuestionBank/Rotation/KernelRotationPlannerPostgresTest.php`

### Isolation
- `config(['database.default' => 'pgsql'])` dans setUp pour forcer Neon malgré phpunit.xml SQLite
- `DB::beginTransaction()` + `DB::rollBack()` dans tearDown (inner transactions → SAVEPOINTs)
- Exception T-PG-01 (deux connexions PDO) : gère sa propre isolation via rollback/cleanup

### Tests (9/9 PASS)

| ID | Invariant | Méthode |
|----|-----------|---------|
| T-PG-01 | FOR UPDATE réel | Deux connexions PDO, NOWAIT confirme verrou |
| T-PG-02 | Exactement un Blueprint | Sequential + factory guard one_active_blueprint_idx |
| T-PG-03 | CKR exactly-once | UNIQUE(blueprint_id) + SAVEPOINT pour isolation PSQL |
| T-PG-04 | 4→6 sur Neon | receiveKernelReceivedV2 + DEPTH_CYCLE_NEXT |
| T-PG-05 | 10→PRODUCTION_ON_HOLD | DEPTH_CYCLE_NEXT, cycle_completed[10] = 0 confirmé |
| T-PG-06 | receiveDomainExhausted idempotent | lock_version inchangé 2e appel |
| T-PG-07 | receiveDepthExhausted idempotent | pending inchangé 2e appel |
| T-PG-08 | Rollback avant fillRotation | SAVEPOINT + ROLLBACK TO SAVEPOINT → 0 Blueprints |
| T-PG-09 | IMPASSE-KRP-001 sur Neon | Taxonomy throws → Blueprint CREATED_UNENGAGED durable |

### Points techniques

**T-PG-03** : En PostgreSQL, une erreur dans une transaction avorte toute la transaction. Un SAVEPOINT doit encadrer l'INSERT violant pour permettre ROLLBACK TO SAVEPOINT et continuer.

**T-PG-08** : `DB::savepoint()` n'existe pas sur PostgresConnection Laravel. Utiliser `DB::statement('SAVEPOINT name')` et `DB::statement('ROLLBACK TO SAVEPOINT name')`.

**T-PG-01** : INSERT doit être committed (hors transaction) pour être visible par la deuxième connexion PDO. L'insert auto-commit + BEGIN + SELECT FOR UPDATE dans la même connexion suffit.

**Why:** Aucun test de concurrence réelle ne peut être fait en PHP mono-thread. NOWAIT est la méthode standard pour prouver le verrou sans attente infinie.
