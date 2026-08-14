---
name: KRP-GAP-IMPLEMENTATION-002 — cycle_target/cycle_completed comme autorité (CORRIGÉ 2026-08-14)
description: FERMÉ. applyDepthTransition() utilisait cycle_completed/cycle_target SUPERSEDED. Corrigé par DEPTH_CYCLE_NEXT constant. Reclassifié de IMPASSE-KRP-002 → KRP-GAP-IMPLEMENTATION-002.
---

## KRP-GAP-IMPLEMENTATION-002 — FERMÉ (2026-08-14)

**Classification initiale :** IMPASSE-KRP-002 (incorrecte)
**Reclassification :** KRP-GAP-IMPLEMENTATION-002 — CODE NON CONFORME À SPEC FIGÉE
**Statut :** CORRIGÉ

### Ce qui était non conforme

`applyDepthTransition()` appelait :
- `DepthNeedMatrix::incrementCycleCompleted($exhaustedDepth)`
- `DepthNeedMatrix::nextRequiredDepth($exhaustedDepth)`

Ces deux méthodes utilisent `cycle_target` et `cycle_completed` comme autorité de transition,
ce qui est interdit par la spec v3.2 figée.

### Correction appliquée

Ajout de la constante privée `DEPTH_CYCLE_NEXT` dans `KernelRotationPlanner` :
```
2→4 | 4→6 | 6→7 | 7→8 | 8→9 | 9→10 | 10→null (PRODUCTION_ON_HOLD)
```

`applyDepthTransition()` lit maintenant `self::DEPTH_CYCLE_NEXT[$exhaustedDepth]` directement.
Aucun appel à `DepthNeedMatrix`. `cycle_target` / `cycle_completed` n'influencent plus
aucune transition de Depth.

Depth hors DepthCycle (ex : Depth 1) → `RuntimeException` avec message "hors DepthCycle officiel".

### Tests ajoutés (tous GREEN)

- T-26 à T-31 : transitions 2→4, 4→6, 6→7, 7→8, 8→9, 9→10
- T-32 : Depth 10 → PRODUCTION_ON_HOLD SANS saturation matrix (prouve cycle_completed non modifié)
- T-33 : Depth 1 (hors DepthCycle) → RuntimeException

**Why:** La règle figée interdisait "réintroduire CYCLE_TARGET comme autorité". La décision était
déjà dans le spec : DepthCycle = [2,4,6,7,8,9,10]. Pas une impasse, une non-conformité à corriger.
