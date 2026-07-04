---
name: RefreshDatabase incompatible SQLite — pattern de contournement
description: La migration bot_qualification_events contient ADD CONSTRAINT CHECK (PG-only) qui plante RefreshDatabase sur SQLite. Pattern de contournement pour les tests Unit nécessitant une DB.
---

# RefreshDatabase incompatible SQLite

## Cause

`database/migrations/2026_03_15_100004_fix_bot_qualification_events_constraint.php` exécute :

```sql
ALTER TABLE bot_qualification_events
ADD CONSTRAINT bqe_event_type_check
CHECK (event_type IN ('solo_level', 'duo_match', 'league_individual_match'))
```

`ADD CONSTRAINT … CHECK` est une syntaxe PostgreSQL. SQLite ne la supporte pas.

`RefreshDatabase` exécute **toutes** les migrations → la suite crashe dès cette migration
avec `SQLSTATE[HY000]: General error: 1 near "CONSTRAINT": syntax error`.

## Pattern de contournement (à utiliser dans tous les tests Unit nécessitant une DB)

```php
// PAS de use RefreshDatabase;
// Étend Tests\TestCase (pas PHPUnit\Framework\TestCase) pour avoir le container Laravel + DB

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Créer uniquement les tables nécessaires (pas de migrate --all)
        Schema::create('ma_table', function (Blueprint $table) {
            // ... définition exacte tirée de la migration
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ma_table');
        parent::tearDown();
    }
}
```

## Règle d'application

- Tests **sans DB** (pure computation) → étendre `PHPUnit\Framework\TestCase` directement.
- Tests **avec DB** mais **peu de tables** → étendre `Tests\TestCase` + création manuelle dans `setUp()`.
- Ne **jamais** utiliser `use RefreshDatabase` dans un test Unit de ce projet.

## Référence existante

`tests/Unit/QuestionBank/Rotation/TaxonomyProgressManagerTest.php` — implémente ce pattern
pour la table `taxonomy_progress` (12 tests, 93 assertions vertes).

`tests/Unit/QuestionBank/KernelFrameBuilderBlueprintTest.php` — n'a pas de DB du tout,
étend `PHPUnit\Framework\TestCase` directement.

**Why:** Correction du problème global (réécrire la migration pour SQLite) nécessiterait de
modifier une migration déjà appliquée en production — interdit par les règles data-protection.
Le contournement par création manuelle est sûr et non-destructif.
