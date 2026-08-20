# 02_KernelRotationPlanner — v3.2 historique

**Version :** 3.2  
**Statut actuel :** **HISTORIQUE / SUPERSEDED COMME VÉRITÉ ACTIVE**  
**Date de remplacement :** 2026-08-20  
**Remplacé par :** `docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md` v3.3  
**Décision :** DEC-114

> Ce document est conservé pour l’historique intellectuel du projet. Il ne doit plus être utilisé pour concevoir, auditer ou implanter KRP.

## Source canonique unique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
Version 3.3
VERROUILLÉ — PARTIE INTELLECTUELLE
```

La v3.3 active remplace notamment les anciennes règles incompatibles concernant :

- l’autorité de `cycle_target/cycle_completed` ;
- la signification de `DEPTH_EXHAUSTED` ;
- la fin après Depth 10 ;
- la persistance/idempotence des épuisements.

Références actives : DEC-094, DEC-107, DEC-108, DEC-111, DEC-114.
