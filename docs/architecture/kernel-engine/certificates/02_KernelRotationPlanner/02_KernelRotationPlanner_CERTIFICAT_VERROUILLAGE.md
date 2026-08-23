# Certificat de verrouillage — 02_KernelRotationPlanner

**Module :** `02_KernelRotationPlanner`  
**Version :** 3.6  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Décision :** DEC-117

## Source canonique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
```

## Invariant verrouillé

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

KRP et Taxonomy ne sont jamais actifs simultanément.

## Frontière Taxonomy

À la fin de son travail, Taxonomy peut émettre :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification exacte :

```text
CE DOMAIN EST VIDE
```

Ce fait n'active pas KRP et ne contient aucune décision de rotation.

Il reste en attente jusqu'au prochain cycle.

Taxonomy n'émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

## Rotation suivante

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle
↓
Factory crée un nouveau Blueprint
↓
KRP devient ACTIF
↓
consomme les faits Domain vides en attente
↓
persiste VISIBLE → ESTOMPÉ
↓
ESTOMPÉ = Domain abstrait/exclu des rotations restantes du tour
↓
KRP décide seul depth + domain
↓
fillRotation(depth,domain)
↓
KRP FIN
↓
Taxonomy peut devenir ACTIF
```

## Fin de tour

```text
8 Domaines ESTOMPÉ
↓
KRP ferme SON tour
↓
cycle_completed[depth] += 1 exactement une fois
↓
DepthNeedMatrix
↓
prochain Depth nécessaire
```

Après Depth 10, retour possible vers Depth 2 si un besoin subsiste.

HOLD uniquement lorsque toutes les cibles sont satisfaites.

## Résultat

```text
Architecture intellectuelle : 100 %
Contrat intellectuel      : 100 %
Spécification             : VERROUILLÉE v3.6
Implémentation            : À RÉAUDITER
Validation code terminale : NON
```

## Taxonomy

`03_Taxonomy v1.0` reste historique sur la frontière KRP. Le bridge actif est :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-117.md
```

Taxonomy sera réécrit intégralement en v1.1 dans son propre tour.

## Prochaine étape

```text
RÉAUDIT-02-v3.6
```

Le Build local précédent ne doit pas être repris sans ce réaudit.
