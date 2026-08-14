---
name: IMPASSE-KRP-001 — Blueprint orphelin post-COMMIT (frontière KRP/Taxonomy)
description: Blueprint CREATED_UNENGAGED durable si Taxonomy échoue après la transaction KRP. Non résoluble sans contrat 03_Taxonomy.
---

## IMPASSE-KRP-001

**Type :** FRONTIÈRE INTER-MODULE

**Modules concernés :**
- 01_KernelBlueprint
- 02_KernelRotationPlanner
- 03_Taxonomy

**Situation :**

KRP commit correctement sa transaction gate :
- `factory::create()` → Blueprint CREATED_UNENGAGED
- `registerActiveBlueprintIdentity()` → état V2 persisté
- COMMIT

Ensuite, l'orchestrateur appelle `peekNext()` (Taxonomy) HORS de la transaction.
Si Taxonomy échoue à ce point, le Blueprint canonique reste CREATED_UNENGAGED en DB.

Conséquence : au prochain cycle, `resolveNextRotation()` lit `active_blueprint_identity ≠ null`
→ le single-active guard bloque la création du Blueprint suivant.

**Ce qui manque :**

Le contrat officiel de 03_Taxonomy doit définir le comportement lorsque Taxonomy
ne peut pas poursuivre après qu'un Blueprint KRP valide existe.

**Code inventé pour résoudre :** NON

**Décision architecturale prise :** NON

**LOT C commencé :** NON

**Solutions INTERDITES (liste exhaustive) :**
- auto-recovery
- cleanup automatique
- nouvel execution_state
- nouvelle transition Blueprint
- retry Taxonomy
- suppression automatique du Blueprint
- timeout
- nouvelle transaction compensatoire métier
- commande Artisan comme comportement normal
- nouveau signal
- modification du single-active guard

**Preuve en test :** `KernelPipelineOrchestratorTest::test_exception_after_transaction_leaves_blueprint_created_unengaged()`
→ peekNext() throw après COMMIT → 1 Blueprint CREATED_UNENGAGED durable.

**Dépend de :** 03_Taxonomy (contrat de comportement après Blueprint valide existant)

**Impact si on continue sans décision :**
Le single-active guard peut bloquer indéfiniment la rotation si Taxonomy est instable.

**Why:** Identifiée le 2026-08-14 lors de la restauration de `final class KernelRotationPlanner`.
Le test 2 de rollback prouve l'orphelin mais la résolution appartient à 03_Taxonomy.
