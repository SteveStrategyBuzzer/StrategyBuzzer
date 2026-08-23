# Certificat de verrouillage — 02_KernelRotationPlanner

**Module :** `02_KernelRotationPlanner`  
**Version :** 3.4  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Décision :** DEC-115

## Source canonique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
```

## Ownership verrouillé

```text
Taxonomy
= expose la réalité de ses réservoirs
= aucune autorité de rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle
= aucune autorité de rotation

KernelBlueprintFactory
= crée le NOUVEAU Blueprint

KernelRotationPlanner
= autorité UNIQUE de rotation
```

## Contrat verrouillé

```text
ReadyBank reçoit le noyau courant
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle
↓
Factory crée un nouveau Blueprint
↓
KRP lit RotationState + DepthNeedMatrix + réalité Taxonomy
↓
KRP décide seul depth + domain
↓
fillRotation(depth, domain)
↓
persistance
↓
FIN KRP
↓
porte Taxonomy
```

## Règles principales

- contenu Taxonomy restant → KRP conserve le même Domain ;
- aucun contenu Taxonomy restant → KRP persiste `VISIBLE→ESTOMPÉ` et choisit le Domain suivant ;
- huit Domaines `ESTOMPÉ` → KRP ferme son tour ;
- `cycle_completed[depth] += 1` exactement une fois par tour ;
- prochain Depth choisi par `DepthNeedMatrix` ;
- après Depth 10, retour possible vers Depth 2 si un besoin subsiste ;
- HOLD uniquement lorsque toutes les cibles globales sont satisfaites ;
- KRP écrit uniquement `depth + domain`.

## Ancienne ownership superseded

Les formulations suivantes ne sont plus contractuelles :

```text
Taxonomy produit DOMAIN_EXHAUSTED
Taxonomy produit DEPTH_EXHAUSTED
```

Elles sont remplacées par DEC-115.

## Résultat

```text
Architecture intellectuelle : 100 %
Contrat intellectuel      : 100 %
Spécification             : VERROUILLÉE v3.4
Implémentation            : À RÉAUDITER
Validation code terminale : NON
```

## Taxonomy

`03_Taxonomy v1.0` doit être réécrite en v1.1 dans son propre tour pour intégrer cette frontière. Jusqu’alors, `working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-115.md` est le bridge actif pour l’ownership KRP/Taxonomy.

## Prochaine étape

```text
RÉAUDIT-02-v3.4
```

Le Build local v3.3 arrêté ne doit pas être repris sans ce réaudit.