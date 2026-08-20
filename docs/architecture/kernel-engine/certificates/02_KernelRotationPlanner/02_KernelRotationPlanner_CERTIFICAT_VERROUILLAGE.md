# Certificat de verrouillage — 02_KernelRotationPlanner

**Module :** `02_KernelRotationPlanner`  
**Version :** 3.3  
**Date :** 2026-08-20  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Décision :** DEC-114

## Source canonique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
```

## Autorités intégrées

- DEC-094 — double autorité `DEPTH_EXHAUSTED` / `DepthNeedMatrix` ;
- DEC-107 — garde terminale avant `DOMAIN_EXHAUSTED` ;
- DEC-108 — `DEPTH_EXHAUSTED` = fin d’un tour ;
- DEC-111 — persistance et idempotence KRP.

## Résultat du verrouillage intellectuel

```text
Architecture intellectuelle : 100 %
Contrat intellectuel      : 100 %
Spécification             : VERROUILLÉE
Implémentation            : À AUDITER
Validation code terminale : NON
```

Le contrat verrouille notamment :

```text
nouveau Blueprint déjà créé
↓
KRP
↓
RotationState + DepthNeedMatrix
↓
sélection depth + domain
↓
fillRotation(depth, domain)
↓
persistance
↓
FIN KRP
↓
porte vers Taxonomy
```

Le contrat interdit le recyclage de l’ancien Blueprint depuis ReadyBank vers KRP.

## Extension future

KRP est complet pour la partie intellectuelle. Les besoins éventuels provenant des futures Phases 1 et 2 restent non spécifiés et ne bloquent pas ce verrouillage.

Toute extension future :

1. doit provenir de la spécification propriétaire de la Phase concernée ;
2. doit produire une nouvelle version KRP et une nouvelle DEC si KRP est affecté ;
3. ne peut pas modifier silencieusement les responsabilités intellectuelles v3.3.

## Documents non autoritatifs

```text
docs/architecture/02_KernelRotationPlanner.md
→ historique v3.2

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED
```

Aucun de ces documents ne peut remplacer la spécification canonique v3.3.

## Prochaine étape

```text
AUDIT-02-00
```

Audit du code réel contre la spécification v3.3 avant toute implantation KRP.
