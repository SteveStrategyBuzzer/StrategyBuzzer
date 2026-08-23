# 03_Taxonomy — Boundary Bridge actif vers KRP

**Date :** 2026-08-23  
**Statut :** ACTIVE BOUNDARY BRIDGE — NON SPÉCIFICATION  
**Décision :** DEC-117

Ce document ne remplace pas la future spécification complète `03_Taxonomy v1.1`. Il fixe uniquement la frontière nécessaire pendant que `02_KernelRotationPlanner` reste le module actif.

## Invariant séquentiel

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

Taxonomy et KRP ne sont jamais actifs simultanément.

## Sortie factuelle Taxonomy

À la fin de son travail sur le territoire attribué, si ce Domain est réellement vide, Taxonomy peut émettre :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification exacte :

```text
CE DOMAIN EST VIDE
```

Ce signal ne veut jamais dire :

- passe au prochain Domain ;
- ferme le tour ;
- passe au prochain Depth ;
- mets HOLD ;
- active KRP maintenant.

Taxonomy n'émet pas `DEPTH_EXHAUSTED` dans la frontière active.

## Moment de communication

```text
Taxonomy ACTIVE
↓
travail du territoire
↓
Taxonomy FIN
↓
DOMAIN_EXHAUSTED si nécessaire
↓
fait conservé à la frontière
↓
KRP INACTIF
```

Le mécanisme technique de conservation du fait n'est pas contractuel.

## Cycle suivant

```text
pipeline continue
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
Factory crée un nouveau Blueprint
↓
KRP ACTIVE
↓
KRP consomme le fait en attente
↓
VISIBLE → ESTOMPÉ
```

`ESTOMPÉ` signifie que le Domain est exclu des rotations restantes du tour courant.

KRP décide alors seul du prochain Domain, de la fermeture du tour, du prochain Depth et de HOLD.

## Ownership

```text
Taxonomy
= réalité intellectuelle de ses Banks
= émet « ce Domain est vide »
= aucune autorité de rotation

Frontière de communication
= conserve le fait entre les phases
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
- transformer `DOMAIN_EXHAUSTED` en commande immédiate de rotation ;
- attribuer `DEPTH_EXHAUSTED` à Taxonomy ;
- laisser Taxonomy choisir prochain Domain ou prochain Depth.

La future réécriture complète de `03_Taxonomy` devra intégrer DEC-117 dans son propre tour de spécification.
