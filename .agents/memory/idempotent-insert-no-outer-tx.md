---
name: Idempotent per-row inserts must not be wrapped in an outer transaction
description: Why concurrency-safe idempotent inserts (firstOrNew + save catching UNIQUE) must run per-row, not inside one DB::transaction, on Postgres.
---

Idempotent inserts that rely on catching a UNIQUE-constraint violation (the "insert, and if a concurrent writer already inserted the same business key, treat it as already-present") must be executed **per row, outside any wrapping `DB::transaction`**.

**Why:** On Postgres, the first failed statement inside a transaction puts the whole transaction into the *aborted* state ("current transaction is aborted, commands ignored until end of transaction block"). So catching the UNIQUE violation and continuing the loop to insert the next row still fails — every subsequent statement in that transaction errors. Wrapping N independent idempotent inserts in one transaction defeats the catch-and-continue strategy.

**How to apply:** When each row is independent (no cross-row invariant requiring all-or-nothing), drop the outer transaction and `save()` each row in its own try/catch. Detect UNIQUE violations connection-agnostically: SQLSTATE `23505` (Postgres) / `23000` (SQLite), plus message fallback ("unique constraint", "unique violation", "duplicate key", or the index name). If you genuinely need atomicity across rows, use a per-row SAVEPOINT instead so one failure only rolls back its own row.
