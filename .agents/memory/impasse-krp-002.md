---
name: IMPASSE-KRP-002 — cycle_target / cycle_completed encore autorité active (SUPERSEDED)
description: applyDepthTransition() utilise cycle_completed/cycle_target comme autorité pour déterminer PRODUCTION_ON_HOLD, alors que ces autorités sont SUPERSEDED en v3.2.
---

## IMPASSE-KRP-002

**Type :** NON-CONFORMITÉ AUTORITÉ SUPERSEDED

**Modules concernés :**
- 02_KernelRotationPlanner
- DepthNeedMatrix
- kernel_depth_matrix (table)

**Situation :**

`KernelRotationPlanner::applyDepthTransition()` (privée, appelée par `receiveKernelReceivedV2`) :
```php
$depthMatrix->incrementCycleCompleted($exhaustedDepth);  // ligne 498
$nextDepth = $depthMatrix->nextRequiredDepth($exhaustedDepth);  // ligne 499
```

`DepthNeedMatrix::nextRequiredDepth()` lit `cycle_completed` et le compare à `cycle_target`
pour déterminer si un Depth est "saturé". Si tous saturés → retourne null → PRODUCTION_ON_HOLD.

La règle architecturale figée interdit explicitement :
- "réintroduire CYCLE_TARGET comme autorité"
- "réintroduire cycle_completed comme autorité"

Ces autorités sont encore ACTIVES dans le chemin runtime :
`ProcessKernelPipelineOutbox` → `receiveKernelReceivedV2` → `applyDepthTransition`.

**Preuve LOT B FAIL :**

`ProcessKernelPipelineOutboxTest::test_pending_depth_10_causes_production_on_hold()` :
- Pré-sature `cycle_completed = cycle_target` pour TOUS les Depths (lignes 309-313)
- Le chemin DEPTH_EXHAUSTED(10) + CKR → PRODUCTION_ON_HOLD
  DÉPEND de cette saturation pour fonctionner.

**Ce qui manque :**

La spec 02_KernelRotationPlanner v3.2 doit définir :
- quel mécanisme remplace `cycle_target`/`cycle_completed` pour déterminer
  si `nextRequiredDepth` retourne null (PRODUCTION_ON_HOLD) ;
- ou confirmer que `cycle_target`/`cycle_completed` sont encore valides
  dans `applyDepthTransition` (pour lever le flag SUPERSEDED).

**Code inventé pour résoudre :** NON

**Décision architecturale prise :** NON

**Dépend de :** 02_KernelRotationPlanner v3.2 (clarification du mécanisme PRODUCTION_ON_HOLD
dans le chemin receiveKernelReceivedV2 → applyDepthTransition)

**Impact si on continue sans décision :**
Le chemin DEPTH_EXHAUSTED → PRODUCTION_ON_HOLD reste fonctionnel MAIS non conforme
à l'architecture figée. `cycle_target`/`cycle_completed` agissent comme autorité SUPERSEDED.

**Why:** Identifiée le 2026-08-14 lors du scan des symboles SUPERSEDED demandé par l'utilisateur.
La règle figée interdit explicitement ces autorités ; le code les utilise encore activement.
