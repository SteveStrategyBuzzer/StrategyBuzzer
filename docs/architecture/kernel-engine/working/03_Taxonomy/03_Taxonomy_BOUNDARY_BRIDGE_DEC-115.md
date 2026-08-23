# 03_Taxonomy — Boundary Bridge actif vers KRP

**Date :** 2026-08-23  
**Statut :** ACTIVE BOUNDARY BRIDGE — NON SPÉCIFICATION  
**Décision :** DEC-115

Ce document ne remplace pas la future spécification complète `03_Taxonomy v1.1`. Il fixe uniquement la frontière devenue obligatoire pendant que `02_KernelRotationPlanner` est le module actif.

## Frontière corrigée

Taxonomy reste propriétaire de ses Banks, occurrences, curseurs, Subjects, Dominant Ideas et de la vérité intellectuelle de ce qu'il lui reste réellement à exploiter.

Taxonomy **ne décide aucune rotation** et **ne produit plus de commande de rotation** sous forme de `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED` destinée à faire avancer KRP.

Taxonomy expose/persiste uniquement la réalité courante de ses réservoirs, par exemple :

```text
pour le Depth + Domain actuellement travaillé :
contenu exploitable restant
OU
aucun contenu exploitable restant
```

Le nom technique de l'interface ou du stockage n'est pas défini ici.

## Ownership

```text
Taxonomy
= vérité intellectuelle de ses réservoirs

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenchement lifecycle du noyau suivant

KernelBlueprintFactory
= création du NOUVEAU Blueprint

KernelRotationPlanner
= autorité UNIQUE de rotation
```

KRP interprète la réalité Taxonomy au moment où il reçoit un nouveau Blueprint. Il décide alors seul de :

- conserver le même Domain si du contenu exploitable reste ;
- rendre le Domain courant `ESTOMPÉ` lorsqu'il n'a plus de contenu exploitable ;
- sélectionner le prochain Domain selon le DomainCycle ;
- fermer le tour lorsque les huit Domaines sont `ESTOMPÉ` ;
- incrémenter `cycle_completed[depth]` exactement une fois ;
- consulter `DepthNeedMatrix` ;
- sélectionner le prochain Depth encore nécessaire ;
- revenir vers Depth 2 après Depth 10 si un besoin subsiste ;
- produire `PRODUCTION_ON_HOLD` uniquement lorsque tous les besoins globaux sont satisfaits.

## Interdiction de dérive

Ne jamais reconstruire la frontière active depuis les anciennes formulations de `03_Taxonomy v1.0` qui attribuent à Taxonomy la production de `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED`.

La future réécriture complète de `03_Taxonomy` devra intégrer DEC-115 dans son propre tour de spécification.