# 03_Taxonomy — Boundary Bridge actif vers KRP

**Date :** 2026-08-23  
**Statut :** ACTIVE BOUNDARY BRIDGE — NON SPÉCIFICATION  
**Décision :** DEC-116

Ce document ne remplace pas la future spécification complète `03_Taxonomy v1.1`. Il fixe uniquement la frontière requise pendant que `02_KernelRotationPlanner` reste le module actif.

## Frontière corrigée

Taxonomy reste propriétaire de ses Banks, occurrences, curseurs, Subjects, Dominant Ideas et de la vérité intellectuelle de ce qu’il lui reste réellement à exploiter.

Taxonomy **parle à KRP** lorsqu’un Domain actif est réellement vidé de son contenu exploitable.

Signal factuel actif :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Ce signal signifie uniquement :

```text
pour le territoire KRP actif,
remaining_subjects = 0
AND
remaining_ideas = 0
```

Il ne signifie jamais :

- « passe au Domain suivant » ;
- « ferme le tour » ;
- « passe au Depth suivant » ;
- « mets le moteur en HOLD ».

Taxonomy n’envoie pas `DEPTH_EXHAUSTED` dans le contrat actif.

## Ownership

```text
Taxonomy
= vérité intellectuelle de ses Banks
= émission factuelle DOMAIN_EXHAUSTED lorsque le Domain est réellement vide

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenchement lifecycle du noyau suivant

KernelBlueprintFactory
= création du NOUVEAU Blueprint

KernelRotationPlanner
= autorité UNIQUE de rotation
```

## Moment des effets

À la réception du signal :

```text
KRP
→ valide que le signal concerne le territoire actif
→ persiste VISIBLE → ESTOMPÉ
→ aucune nouvelle rotation immédiate
```

Au cycle suivant seulement :

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ Factory crée un nouveau Blueprint
→ KRP lit SON RotationState
→ applique son DomainCycle / DepthCycle
```

KRP décide alors seul de :

- conserver le même Domain si aucun signal d’épuisement n’a été reçu ;
- choisir le prochain Domain si le courant est `ESTOMPÉ` ;
- fermer le tour si les huit Domaines sont `ESTOMPÉ` ;
- incrémenter `cycle_completed[depth]` exactement une fois ;
- consulter `DepthNeedMatrix` ;
- sélectionner le prochain Depth encore nécessaire ;
- revenir vers Depth 2 après Depth 10 si un besoin subsiste ;
- produire `PRODUCTION_ON_HOLD` uniquement lorsque tous les besoins globaux sont satisfaits.

## Interdiction de dérive

Ne pas reconstruire la frontière depuis `03_Taxonomy v1.0` lorsqu’elle attribue à Taxonomy une décision de fin de tour ou la production de `DEPTH_EXHAUSTED`.

Ne pas utiliser non plus le bridge DEC-115, qui est superseded.

La future réécriture complète de `03_Taxonomy` devra intégrer DEC-116 dans son propre tour de spécification.
