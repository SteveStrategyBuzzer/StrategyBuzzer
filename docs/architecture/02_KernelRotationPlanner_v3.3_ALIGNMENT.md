# 02_KernelRotationPlanner v3.3 — ALIGN-02

**Date d’origine :** 2026-08-17  
**Statut actuel :** **SUPERSEDED**  
**Remplacé le :** 2026-08-20  
**Remplacé par :** `docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md` v3.3  
**Décision :** DEC-114

> Ce document est conservé uniquement comme historique de réconciliation. Il n’a plus aucune autorité architecturale ou contractuelle.

## Règle de reprise

Ne jamais reconstruire KRP depuis ce fichier.

Source canonique unique :

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
Version 3.3
VERROUILLÉ — PARTIE INTELLECTUELLE
```

La spécification canonique intègre notamment :

- DEC-094 — double autorité `DEPTH_EXHAUSTED` / `DepthNeedMatrix` ;
- DEC-107 — garde terminale `DOMAIN_EXHAUSTED` ;
- DEC-108 — `DEPTH_EXHAUSTED` = fin d’un tour ;
- DEC-111 — persistance/idempotence KRP ;
- retour cyclique après Depth 10 vers le prochain Depth encore nécessaire ;
- `PRODUCTION_ON_HOLD` uniquement lorsque tous les besoins globaux sont satisfaits ;
- Factory crée un **nouveau** Blueprint avant KRP ;
- KRP écrit uniquement `depth + domain` ;
- sortie KRP = porte vers Taxonomy.

Toute formulation antérieure incompatible contenue dans l’historique Git de ALIGN-02 est `SUPERSEDED` et ne doit plus être utilisée.
