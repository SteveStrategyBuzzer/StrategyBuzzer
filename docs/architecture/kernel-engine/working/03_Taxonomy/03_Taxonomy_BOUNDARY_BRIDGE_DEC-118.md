# 03_Taxonomy — Boundary Bridge actif vers KRP

**Date :** 2026-08-23  
**Statut :** ACTIVE BOUNDARY BRIDGE — NON SPÉCIFICATION  
**Décision :** DEC-118

Ce document ne remplace pas la future spécification complète de `03_Taxonomy`. Il fixe uniquement la frontière nécessaire pendant que `02_KernelRotationPlanner` reste le module actif.

## Invariant séquentiel

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

Taxonomy et KRP ne sont jamais actifs simultanément.

## Moment exact de la sortie Taxonomy

Taxonomy ne communique aucune information de rotation pendant son travail intermédiaire.

Dans sa fermeture de sortie :

```text
IdeaSlot exact sélectionné
↓
triplet exact Subdomain + Subject + Dominant Idea prêt
↓
écriture Blueprint réussie
↓
consommation immédiate du même IdeaSlot
↓
évaluation de l’état final de l’occurrence du Domain
```

Si le Domain reste exploitable :

```text
AUCUN SIGNAL
```

Si cette consommation provoque :

```text
ENCORE EXPLOITABLE → VIDE
```

Taxonomy émet :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

## Signification exacte

```text
CE DOMAIN EST VIDE
```

Le signal ne veut jamais dire :

- passe au prochain Domain ;
- ferme le tour ;
- passe au prochain Depth ;
- mets HOLD ;
- active KRP maintenant.

Taxonomy n’émet pas `DEPTH_EXHAUSTED` dans la frontière active.

## Règle delta-only

Taxonomy informe KRP uniquement lorsqu’un besoin change réellement.

Donc :

- plusieurs noyaux successifs avec contenu restant → silence ;
- pas de signal à chaque passage ;
- pas de `AVAILABLE` ;
- une occurrence de bassin produit au maximum un `DOMAIN_EXHAUSTED` normal ;
- une nouvelle occurrence future du même `(Depth + Domain)` peut émettre son propre signal lorsqu’elle devient vide.

## Séparation temporelle

Après la fermeture Taxonomy :

```text
Taxonomy FIN
↓
DOMAIN_EXHAUSTED si changement réel
↓
fait conservé à la frontière
↓
KRP INACTIF
```

Le mécanisme technique de conservation n’est pas contractuel.

Le pipeline continue normalement.

Puis :

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle
↓
Factory crée un nouveau Blueprint
↓
KRP ACTIVE
```

## Application KRP

À sa prochaine activation seulement :

```text
KRP consomme DOMAIN_EXHAUSTED en attente
↓
VISIBLE → ESTOMPÉ
```

`ESTOMPÉ` signifie :

```text
Domain abstrait/exclu des rotations restantes du tour courant
```

KRP décide ensuite seul :

- conserver le même Domain s’il reste `VISIBLE` ;
- choisir le prochain Domain `VISIBLE` si le courant est `ESTOMPÉ` ;
- fermer le tour si les huit Domaines sont `ESTOMPÉ` ;
- incrémenter `cycle_completed[depth]` exactement une fois ;
- consulter `DepthNeedMatrix` ;
- sélectionner le prochain Depth encore nécessaire ;
- revenir vers Depth 2 après Depth 10 si un besoin subsiste ;
- produire `PRODUCTION_ON_HOLD` uniquement lorsque tous les besoins globaux sont satisfaits.

## Ownership

```text
Taxonomy
= Banks + constat du changement réel vers Domain vide
= aucune autorité de rotation

Frontière de communication
= conservation du fait
= aucune autorité de rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclencheur lifecycle

KernelBlueprintFactory
= création du NOUVEAU Blueprint

KernelRotationPlanner
= autorité UNIQUE de rotation
```

## Interdiction de dérive

Ne jamais :

- faire lire/poller Taxonomy par KRP ;
- activer KRP pendant Taxonomy ;
- émettre `DOMAIN_EXHAUSTED` à chaque noyau ;
- émettre le fait avant une sortie Taxonomy réussie ;
- transformer `DOMAIN_EXHAUSTED` en commande immédiate de rotation ;
- attribuer `DEPTH_EXHAUSTED` à Taxonomy ;
- laisser Taxonomy choisir prochain Domain ou prochain Depth.

La future réécriture complète de `03_Taxonomy` devra intégrer DEC-118 dans son propre tour de spécification.
