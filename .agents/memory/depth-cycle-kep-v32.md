---
name: DepthCycle figé KRP v3.2 — DEPTH_CYCLE_NEXT
description: applyDepthTransition() utilise DEPTH_CYCLE_NEXT constant. Plus de nextRequiredDepth/incrementCycleCompleted dans les transitions KRP. Depth 1 → RuntimeException.
---

## DepthCycle KRP v3.2 — Implémentation figée (2026-08-14)

### Constante DEPTH_CYCLE_NEXT (privée dans KernelRotationPlanner)

```php
private const DEPTH_CYCLE_NEXT = [
    2  => 4,
    4  => 6,
    6  => 7,
    7  => 8,
    8  => 9,
    9  => 10,
    10 => null,  // → PRODUCTION_ON_HOLD
];
```

### Règle d'or

`applyDepthTransition($state, $exhaustedDepth)` :
1. `array_key_exists($exhaustedDepth, self::DEPTH_CYCLE_NEXT)` → sinon RuntimeException "hors DepthCycle officiel"
2. `$nextDepth = self::DEPTH_CYCLE_NEXT[$exhaustedDepth]` — null = PRODUCTION_ON_HOLD
3. `domain_position = NULL`, `pending_depth_exhausted_depth = NULL`
4. Si nextDepth non null → `active_depth = nextDepth`, `depth_state = ROTATION_ACTIVE`, domain_states réinitialisés ACTIF
5. Si null → `depth_state = PRODUCTION_ON_HOLD`

**Aucun appel** à `DepthNeedMatrix::nextRequiredDepth()` ni `incrementCycleCompleted()` dans cette méthode.

### Symboles SUPERSEDED dans les transitions KRP

| Symbole | Statut dans KRP | Propriétaire réel |
|---------|----------------|-------------------|
| `nextRequiredDepth()` | ZÉRO caller dans KRP | DepthNeedMatrix (logique interne) |
| `incrementCycleCompleted()` | ZÉRO caller dans KRP | DepthNeedMatrix (logique interne) |
| `cycle_target` | Commentaire seulement dans KRP | DepthNeedMatrix |
| `cycle_completed` | Colonne DB non lue par KRP pour transitions | DepthNeedMatrix |

DepthNeedMatrix reste utilisée dans KRP pour :
- `DEPTH_CYCLE[0]` (constante — valeur initiale Depth)
- `DEPTH_CYCLE` (boucle init domain_states)
- `incrementKernelReceived()` (compteur réception CKR — non lié aux transitions)

### Depth 1 interdit

`DEPTH_CYCLE = [2, 4, 6, 7, 8, 9, 10]` — Depth 1 absent.
`array_key_exists(1, DEPTH_CYCLE_NEXT)` = false → RuntimeException.
Prouvé par T-33.

### Tests GREEN

- T-26 : 2→4
- T-27 : 4→6
- T-28 : 6→7
- T-29 : 7→8
- T-30 : 8→9
- T-31 : 9→10
- T-32 : 10→PRODUCTION_ON_HOLD SANS saturation matrix (cycle_completed[10] reste 0 avant ET après)
- T-33 : Depth 1 → RuntimeException

**Why:** Correction de KRP-GAP-IMPLEMENTATION-002. La spec était déjà figée (DepthCycle = [2,4,6,7,8,9,10]).
L'ancienne implémentation utilisait cycle_target/cycle_completed comme autorité SUPERSEDED.
