# Architecture Register — StrategyBuzzer Kernel Pipeline

**Source de vérité :** ce fichier est le registre centralisé de toutes les décisions architecturales officielles du pipeline Kernel.

Chaque décision inscrite ici est :
- identifiée par un numéro unique (DEC-NNN) ;
- datée ;
- associée à un statut : `OFFICIAL`, `UNDER_REVIEW`, ou `SUPERSEDED` ;
- liée au module concerné.

---

## DEC-027 — Progression individuelle des slots

**Version :** 1.3
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

La validation traite tous les slots concernés avant de produire une copie Quarantine.

Une seule copie travaillable est créée à la fin de la passe lorsqu'un ou plusieurs slots sont `FAIL`.

---

## DEC-028 — Retour ciblé depuis Quarantine

**Statut :** SUPERSEDED
**Remplacé par :** DEC-030
**Module :** `01_KernelBlueprint.md`

Ancienne décision : une copie corrigée retournait au moteur propriétaire du contenu fautif.

---

## DEC-029 — Réintégration limitée au slot initialement FAIL

**Statut :** SUPERSEDED
**Remplacé par :** DEC-031
**Module :** `01_KernelBlueprint.md`

Ancienne décision : la réintégration remplaçait uniquement le slot précédemment identifié `FAIL`.

---

## DEC-030 — Retour systématique à Phase 1

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

Toute copie travaillable corrigée provenant de Quarantine retourne systématiquement à Phase 1.

Cette règle s'applique aux erreurs détectées :
- en Validation Phase 1 ;
- en Validation Phase 2 ;
- dans un contenu cognitif ;
- dans une traduction ;
- dans une dépendance entre plusieurs slots.

---

## DEC-031 — Réintégration de tous les slots modifiés

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

La copie corrigée est réintégrée dans le Blueprint canonique portant le même `kernel_code`.

La réintégration peut concerner les slots initialement `FAIL`, les slots initialement `OK` mais modifiés, les slots dépendants régénérés, les traductions corrigées.

Les slots canoniques non modifiés restent inchangés.

---

## DEC-032 — Une copie par passe de validation

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

Un moteur de validation termine l'analyse de tous les slots qui lui ont été remis avant de produire une copie Quarantine.

Lorsqu'un ou plusieurs slots sont `FAIL`, il crée une seule copie travaillable contenant tous les slots en échec, toutes les erreurs détectées, tous les points de correction, et le contexte complet du noyau.

Il est interdit de créer une copie distincte pour chaque slot `FAIL` appartenant à la même passe.

---

## DEC-033 — Distinction PASS et OK

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

`PASS` est le verdict produit par un moteur de validation.

`OK` est l'état attribué au slot après un verdict `PASS`.

`FAIL` constitue à la fois le verdict d'échec et l'état de fermeture du slot jusqu'à sa correction.

---

## DEC-051 — Initialisation par DepthNeedMatrix

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** UNDER_REVIEW
**Module :** `02_KernelRotationPlanner.md`

Le compte de noyaux demandé (`kernel_target`) pour chaque couple `Depth + Domaine` provient exclusivement de la `DepthNeedMatrix`.

La `DepthNeedMatrix` ne contrôle pas l'ordre du `DepthCycle`.

---

## DEC-052 — Réception ReadyBank indépendante de la jouabilité

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si certains slots sont `FAIL` ou en correction.

---

## DEC-053 — Deux signaux indépendants

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

ReadyBank et Taxonomy transmettent séparément leurs informations au KernelRotationPlanner.

Le calcul de la prochaine position exige les deux signaux :
- `CURRENT_KERNEL_RECEIVED` (ReadyBank) ;
- état actuel des réservoirs (Taxonomy).

---

## DEC-054 — États distincts des domaines

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** UNDER_REVIEW
**Module :** `02_KernelRotationPlanner.md`

Le Planner distingue cinq états de domaine :

```
AVAILABLE
ACTIVE
TARGET_COMPLETE
RESERVOIR_EMPTY
EMPTY_BEFORE_TARGET
```

---

## DEC-055 — Complétion sans domaine sélectionnable

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** UNDER_REVIEW
**Module :** `02_KernelRotationPlanner.md`

Le Depth est fermé lorsqu'aucun de ses domaines ne reste sélectionnable.

La raison de fermeture est conservée : `DEPTH_TARGET_COMPLETE` ou `DEPTH_COMPLETE_WITH_SHORTFALL`.

---

## DEC-056 — Persistance obligatoire de RotationState

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** UNDER_REVIEW
**Module :** `02_KernelRotationPlanner.md`

L'état complet de la rotation (`RotationState`) est persisté afin d'empêcher les doubles comptabilisations, les doubles Blueprints, les sauts de domaine, les pertes de position, et les reprises incohérentes.

---

## DEC-057 — Inclusion officielle du Depth 2 et ordre du DepthCycle

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

**Décision :**

L'ordre officiel du DepthCycle est :

```
2
↓
4
↓
6
↓
7
↓
8
↓
9
```

Depths autorisés : 2, 4, 6, 7, 8, 9.

Depth 1 : refusé (profondeur intellectuelle insuffisante).

Depth 10 : interdit dans le DepthCycle.

Lors de la première initialisation, KernelRotationPlanner commence par :

```
active_depth   = 2
depth_position = 0
```

Après la fermeture du Depth 9 : `ROTATION_COMPLETE`.

**Séparation officielle :**

- `DepthCycle` → ordre de progression : 2, 4, 6, 7, 8, 9
- `DepthNeedMatrix` → `kernel_target` par couple Depth + Domaine (voir DEC-051)

**Modules concernés :** KernelRotationPlanner, DepthCycle, DepthNeedMatrix, Taxonomy, Phase 1, Validation Phase 1, ReadyBank.

**Décision annulée :** toute formulation indiquant que Depth 2 est refusé ou toute séquence différente de `2→4→6→7→8→9`.
