# Certificat de verrouillage — 02_KernelRotationPlanner

**Module :** `02_KernelRotationPlanner`  
**Version :** 3.7  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Décision :** DEC-118

## Source canonique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
```

## Invariant verrouillé

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

KRP et Taxonomy ne sont jamais actifs simultanément.

## Fermeture de sortie Taxonomy

Le fait `DOMAIN_EXHAUSTED(depth, domain)` ne peut être produit que dans la fermeture de sortie Taxonomy :

```text
IdeaSlot exact sélectionné
↓
triplet exact prêt
↓
écriture Blueprint réussie
↓
consommation immédiate du même IdeaSlot
↓
évaluation finale de l’occurrence du Domain
```

Si le Domain reste exploitable :

```text
AUCUN SIGNAL
```

Si la consommation provoque :

```text
ENCORE EXPLOITABLE → VIDE
```

Taxonomy émet :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification exacte :

```text
CE DOMAIN EST VIDE
```

## Règle delta-only

Taxonomy communique uniquement le changement de besoin :

- pas de signal à chaque noyau ;
- pas de signal à chaque passage ;
- pas de `AVAILABLE` ;
- au maximum un `DOMAIN_EXHAUSTED` normal par occurrence de bassin ;
- une future nouvelle occurrence peut produire son propre signal lorsqu’elle devient vide.

## Séparation temporelle

```text
Taxonomy FIN
↓
DOMAIN_EXHAUSTED si changement réel
↓
fait conservé en attente
↓
KRP INACTIF
↓
... pipeline ...
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle
↓
Factory crée un nouveau Blueprint
↓
KRP devient ACTIF
```

Le signal Taxonomy n’active jamais KRP.

## Application KRP

À sa prochaine activation :

```text
KRP consomme le fait
↓
VISIBLE → ESTOMPÉ
```

`ESTOMPÉ` signifie :

```text
Domain abstrait/exclu des rotations restantes du tour courant
```

KRP décide ensuite seul du prochain Domain.

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

Taxonomy n’émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

Après Depth 10, retour possible vers Depth 2 si un besoin subsiste.

HOLD uniquement lorsque toutes les cibles sont satisfaites.

## Ownership verrouillé

```text
Taxonomy
= Banks + constat de changement vers Domain vide
= aucune autorité de rotation

Frontière de communication
= conservation durable du fait
= aucune autorité de rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclencheur lifecycle

KernelBlueprintFactory
= nouveau Blueprint

DepthNeedMatrix
= besoin quantitatif global

KernelRotationPlanner
= autorité UNIQUE de rotation
```

## Résultat

```text
Architecture intellectuelle : 100 %
Contrat intellectuel      : 100 %
Spécification             : VERROUILLÉE v3.7
Implémentation            : À RÉAUDITER
Validation code terminale : NON
```

## Taxonomy

`03_Taxonomy v1.0` reste historique sur la frontière KRP. Le bridge actif est :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-118.md
```

Taxonomy sera réécrit intégralement dans son propre tour.

## Prochaine étape

```text
RÉAUDIT-02-v3.7
```

Le Build local précédent ne doit pas être repris sans ce réaudit.
