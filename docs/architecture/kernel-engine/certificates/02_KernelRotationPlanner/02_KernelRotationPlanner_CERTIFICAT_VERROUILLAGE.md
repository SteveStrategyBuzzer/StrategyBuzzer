# Certificat de verrouillage — 02_KernelRotationPlanner

**Module :** `02_KernelRotationPlanner`  
**Version :** 3.5  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Décision :** DEC-116

## Source canonique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
```

## Ownership verrouillé

```text
Taxonomy
= propriétaire de ses Banks
= pousse DOMAIN_EXHAUSTED(depth, domain) comme fait lorsque le Domain actif est réellement vide
= aucune autorité de rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle
= aucune autorité de rotation

KernelBlueprintFactory
= crée le NOUVEAU Blueprint

DepthNeedMatrix
= indique les Depths encore nécessaires

KernelRotationPlanner
= autorité UNIQUE de rotation
```

## Contrat verrouillé

### Information Taxonomy

```text
Taxonomy
→ DOMAIN_EXHAUSTED(depth, domain)
→ KRP persiste VISIBLE → ESTOMPÉ
→ aucune rotation immédiate
```

Taxonomy n’envoie pas `DEPTH_EXHAUSTED` dans le contrat actif.

### Rotation suivante

```text
ReadyBank reçoit le noyau courant
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle
↓
Factory crée un nouveau Blueprint
↓
KRP lit SON RotationState + DepthNeedMatrix
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

- aucun `DOMAIN_EXHAUSTED` reçu → Domain courant reste `VISIBLE` et est conservé au Blueprint suivant ;
- `DOMAIN_EXHAUSTED` valide → KRP persiste `VISIBLE→ESTOMPÉ`, sans choisir immédiatement un autre Domain ;
- au Blueprint suivant, KRP choisit lui-même le prochain Domain `VISIBLE` ;
- huit Domaines `ESTOMPÉ` → KRP ferme lui-même son tour au cycle suivant ;
- `cycle_completed[depth] += 1` exactement une fois par tour ;
- prochain Depth choisi par `DepthNeedMatrix` ;
- après Depth 10, retour possible vers Depth 2 si un besoin subsiste ;
- HOLD uniquement lorsque toutes les cibles globales sont satisfaites ;
- KRP écrit uniquement `depth + domain`.

## Anciennes formulations superseded

```text
KRP lit/poll la réalité Taxonomy
Taxonomy produit DEPTH_EXHAUSTED
DOMAIN_EXHAUSTED ordonne immédiatement la rotation
```

Elles sont remplacées par DEC-116.

## Résultat

```text
Architecture intellectuelle : 100 %
Contrat intellectuel      : 100 %
Spécification             : VERROUILLÉE v3.5
Implémentation            : À RÉAUDITER
Validation code terminale : NON
```

## Taxonomy

`03_Taxonomy v1.0` devra être réécrite en v1.1 dans son propre tour pour intégrer cette frontière. Jusqu’alors :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-116.md
```

est le bridge actif pour l’ownership KRP/Taxonomy.

## Prochaine étape

```text
RÉAUDIT-02-v3.5
```

Le Build local commencé contre v3.3/v3.4 ne doit pas être repris sans ce réaudit.
