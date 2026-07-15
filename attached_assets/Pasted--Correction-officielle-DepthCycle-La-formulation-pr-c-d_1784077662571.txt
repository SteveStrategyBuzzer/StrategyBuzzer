# Correction officielle — DepthCycle

La formulation précédente indiquant que Depth 2 était refusé est annulée.

## Ordre officiel du DepthCycle

```text
Depth 2
↓
Depth 4
↓
Depth 6
↓
Depth 7
↓
Depth 8
↓
Depth 9
```

## Depths autorisés

```text
2
4
6
7
8
9
```

## Depths interdits

```text
1
10
```

Depth 2 constitue le premier niveau intellectuel exploitable par le moteur de création.

Depth 1 reste refusé, car il ne fournit pas une profondeur intellectuelle suffisante pour produire un noyau conforme aux exigences StrategyBuzzer.

Depth 10 reste interdit.

## Séparation maintenue

Le `DepthCycle` définit l’ordre de progression :

```text
2 → 4 → 6 → 7 → 8 → 9
```

La `DepthNeedMatrix` définit le besoin quantitatif :

```text
kernel_target
par
Depth + Domaine
```

La DepthNeedMatrix ne contrôle pas l’ordre du cycle.

---

# Effets sur KernelRotationPlanner

Lors de la première initialisation, KernelRotationPlanner doit commencer par :

```text
active_depth = 2
depth_position = 0
```

Lorsque tous les domaines du Depth 2 sont fermés :

```text
Depth 2
↓
Depth 4
```

Puis :

```text
Depth 4
↓
Depth 6
↓
Depth 7
↓
Depth 8
↓
Depth 9
```

Après la fermeture du Depth 9 :

```text
ROTATION_COMPLETE
```

KernelRotationPlanner ne doit jamais :

* commencer directement au Depth 4 ;
* ignorer le Depth 2 ;
* sélectionner le Depth 1 ;
* sélectionner le Depth 10 ;
* utiliser l’ancienne séquence `6 → 4 → 8 → 7 → 9 → 2 → 10`.

---

# Architecture Register

## DEC-057 — Inclusion officielle du Depth 2

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL

**Décision :**

Depth 2 est inclus dans le DepthCycle officiel du moteur intellectuel StrategyBuzzer.

L’ordre officiel devient :

```text
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

Depth 1 reste refusé.

Depth 10 reste interdit.

**Modules concernés :**

* KernelRotationPlanner ;
* DepthCycle ;
* DepthNeedMatrix ;
* Taxonomy ;
* Phase 1 ;
* Validation Phase 1 ;
* ReadyBank.

**Décision remplacée :**

Toute décision ou formulation indiquant que Depth 2 est refusé.

---

# Correction de l’analyse à transmettre

Ton analyse comprend correctement les trois sources d’information du KernelRotationPlanner, les deux signaux indépendants ReadyBank/Taxonomy, le RotationState persistant, les états des domaines et les opérations atomiques.

Cependant, la correction suivante est obligatoire.

L’ordre officiel du DepthCycle est désormais :

```text
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

Depth 2 est autorisé et constitue le premier Depth du cycle.

Depth 1 reste refusé.

Depth 10 reste interdit.

La séquence :

```text
6 → 4 → 8 → 7 → 9 → 2 → 10
```

reste incorrecte, car elle ne respecte pas l’ordre officiel et contient Depth 10.

DEC-051 ne définit toujours pas l’ordre du DepthCycle.

DEC-051 définit uniquement que la DepthNeedMatrix fournit le `kernel_target` pour chaque couple `Depth + Domaine`.

Séparation officielle :

```text
DepthCycle
→ ordre : 2, 4, 6, 7, 8, 9
```

```text
DepthNeedMatrix
→ nombre de noyaux demandés par Depth + Domaine
```

Aucun audit ou changement du code existant ne doit être entrepris avant le verrouillage de la spécification.
