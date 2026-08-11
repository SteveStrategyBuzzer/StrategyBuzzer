---
name: TaxonomyOrchestrator architecture (Task 120)
description: Remplacement complet TaxonomyProgressManager → TaxonomyOrchestrator. 4 nouvelles tables, Gemini dynamique, pipeline Subdomain→Subject→Idea.
---

# TaxonomyOrchestrator — architecture officielle

## Règle principale
UNE RESPONSABILITÉ = UN PROPRIÉTAIRE = UNE IMPLÉMENTATION ACTIVE. Zéro legacy fallback.

## Remplacement effectué
- `TaxonomyProgressManager` + `TaxonomyReader` + `taxonomy.json` → SUPPRIMÉS
- `taxonomy_progress` table → DROPPÉE (migration 2026_08_10_000005)
- 4 nouvelles tables : `taxonomy_subdomain_bank`, `taxonomy_subject_bank`, `taxonomy_dominant_idea_bank`, `taxonomy_generation_memory`

## Composants créés
- `TaxonomyConfig` — constantes (MAX_SUBDOMAINS_PER_DOMAIN=20, MAX_SUBJECTS_PER_SUBDOMAIN=50, MAX_DOMINANT_IDEAS_PER_SUBJECT=5, MAX_*_GENERATION_ATTEMPTS=3)
- `DepthContract` + `DepthContractRegistry` — source de vérité des 7 Depths (2,4,6,7,8,9,10), fail-closed
- `FailReason` — codes machine de rejet (15 constantes)
- `ValidationResult` — DTO PASS/FAIL immuable
- `ValidationDominantIdeas` — 18 règles individuelles + diversité collective
- `TaxonomyGeminiClient` — appels Gemini REST avec mémoire cumulative (3 méthodes : subdomains/subjects/ideas)
- `TaxonomyBankRepository` — accès DB pur (4 tables), idempotent via insertOrIgnore
- `TaxonomyOrchestrator` — implémente TaxonomyNavigatorInterface + DomainExhaustionChecker

## Points techniques importants
- `TaxonomyGeminiClient` est NON-final pour permettre le mocking PHPUnit
- `DB::table()->firstOrFail()` n'existe pas sur le query builder → utiliser `->first()` + throw manuel
- `DB::schema()->create()` n'existe pas → utiliser `\Illuminate\Support\Facades\Schema::create()`
- GENERIC_CATEGORIES list a des accents → normaliser les items de la liste dans isGenericCategory() (static cache)
- peekNext() retourne la clé `dominant_idea_active` (attendue par KPO via `$territory['dominant_idea'] ?? $territory['dominant_idea_active']`)

## Tests : 119 PASS
- `DepthContractTest` — 7 Depths + fail-closed + toPromptText + cache
- `ValidationDominantIdeasTest` — 18 règles + diversité collective
- `TaxonomyOrchestratorTest` — idempotence, peekNext→null, confirmConsumed, isExhausted, mémoire cumulative

## Statut pré-existant non-régressif
- BankAIGeneratorRouterTest (2 failures) — pré-existant
- QuestionBankHealthDryTest (7 failures) — pré-existant
- QuestionBankPlannerTest (3 failures) — pré-existant

**Why:** La banque est vide post-déploiement (Gemini n'a pas encore été appelé). Seeder/warm-up = tâche #121.
