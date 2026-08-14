---
name: Validation PostgreSQL terminale KRP — #159 + #159B
description: 15 tests d'intégration PostgreSQL réels (Neon). #159B ajoute 6 tests stricts (deux connexions réelles, contention, rollbacks vrais chemins). 15/15 PASS 2026-08-14.
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

---

## #159B — Validation stricte (2026-08-14)

### Fichier
`tests/Integration/QuestionBank/Rotation/KernelRotationPlannerPostgresStrictTest.php`

### Tests (6/6 PASS)

| ID | Mécanisme prouvé | Régime |
|----|-----------------|--------|
| T-PGB-01 | receiveDomainExhausted : 2 connexions réelles, Worker A commit, Worker B NO-OP | B (suspend outer txn) |
| T-PGB-02 | receiveDepthExhausted : même schéma 2 connexions | B |
| T-PGB-03 | Signal Depth contradictoire → RuntimeException INCOHÉRENCE_ÉTAT | A (outer txn) |
| T-PGB-04 | FOR UPDATE contention NOWAIT + Factory guard (2 orchestrations → 1 Blueprint) | B |
| T-PGB-05 | Rollback vrai chemin après Factory = PREUVE IMPASSE-KRP-001 | A (SAVEPOINT model) |
| T-PGB-06 | Rollback vrai chemin après fillRotation (SAVEPOINT → applyRotation → ROLLBACK TO SAVEPOINT) | A (SAVEPOINT) |

### Points techniques

**Deadlock outer txn / raw PDO** : si l'outer test transaction (DB::beginTransaction) est active et qu'un lockForUpdate() a été acquis dans un SAVEPOINT interne, toute tentative de DELETE sur la même ligne via raw PDO BLOQUE (attend la fin de l'outer txn). Solution : suspendre l'outer txn (DB::rollBack()) avant tout commit raw PDO, cleanup en finally, puis rétablir DB::beginTransaction().

**Race CKR dans receiveKernelReceivedV2** : bug corrigé — SAVEPOINT + catch UniqueConstraintViolationException autour de l'INSERT CKR. Si deux workers passent le EXISTS check simultanément, le second INSERT échoue avec UNIQUE violation. Sans SAVEPOINT, la transaction PostgreSQL entre en état "aborted". Avec SAVEPOINT, le ROLLBACK TO SAVEPOINT isole l'erreur → return (NO-OP idempotent). Fix ajouté dans KernelRotationPlanner.php.

**T-PGB-05 vs T-PG-09** : T-PG-09 prouvait IMPASSE-KRP-001 mais était étiqueté "rollback après fillRotation" (erroné). T-PGB-05 reprend la même preuve avec l'étiquette correcte "rollback après Factory". T-PGB-06 teste le vrai chemin après fillRotation (SAVEPOINT autour de applyRotation).

**Why:** Chaque test doit prouver réellement le mécanisme KRP qu'il prétend valider — pas seulement que PostgreSQL a été utilisé. Tests séquentiels à deux connexions prouvent l'idempotence FOR UPDATE ; tests SAVEPOINT prouvent les rollbacks transactionnels réels.
