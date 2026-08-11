---
name: KernelCodeEngine implementation — 05_QuestionIntent
description: Détails d'implémentation livrés le 11 août 2026, invariants, quirks découverts.
---

# KernelCodeEngine — implémentation livrée (11 août 2026)

## Fichiers créés / modifiés
- `app/Exceptions/QuestionBank/KernelCodeEngineException.php` — 7 codes d'erreur
- `app/Services/QuestionBank/KernelCodeEngine.php` — moteur d'attribution
- `database/migrations/2026_08_11_200000_add_kernel_code_to_blueprint_runs_and_create_sequences.php`
- `tests/Unit/QuestionBank/KernelCodeEngineTest.php` — 67 tests
- `KernelBlueprintRunRepository` — +markKernelCodeAssigned +findKernelCode
- `KernelPipelineOrchestrator` — 4e paramètre KernelCodeEngine, appel après fillTaxonomy

## Quirk important : slugs KRP vs noms canoniques

**Problème :** KRP stocke les domaines comme slugs lowercase (`'geographie'`, `'cinema'`)
dans `KernelBlueprint::domain` et `kernel_blueprint_runs.domain_code`.
La spec utilisateur utilise les noms canoniques français (`'Géographie'`, `'Cinéma'`).

**Fix :** `KernelCodeEngine::DOMAIN_CODES` accepte **les deux formes** (slug + canonical).
Ne jamais supprimer les entrées slug — KRP n'est pas prévu pour être changé.

**Entité affectée :** `resolveDomainCode()` dans KernelCodeEngine.

## Quirk : index UNIQUE PARTIEL pgsql

La migration crée l'index UNIQUE WHERE kernel_code IS NOT NULL via `DB::statement` raw.
SQLite tests utilisent un `->unique()` classique dans Blueprint (NULLs pas dupliqués sous SQLite).

## Tests qui nécessitent kernel_code_sequences en setUp

Tout test qui instancie KernelPipelineOrchestrator réel (pas de mock) DOIT ajouter
`kernel_code_sequences` à son schéma SQLite dans setUp() et dropIfExists dans tearDown().

Fichiers actuels : KernelPipelineOrchestratorTest, ProcessKernelPipelineOutboxTest, SeededBankRotationBlueprintTest, KernelCodeEngineTest.

**Why:** KernelCodeEngine::assignKernelCode() appelle insertOrIgnore sur kernel_code_sequences
— la table DOIT exister dans l'env SQLite de chaque test unitaire.

## Architecture Register

DEC-069 à DEC-077 ajoutés dans `docs/architecture/00_ArchitectureRegister.md`.
`docs/architecture/05_QuestionIntent.md` → v1.0 VERROUILLÉ.
