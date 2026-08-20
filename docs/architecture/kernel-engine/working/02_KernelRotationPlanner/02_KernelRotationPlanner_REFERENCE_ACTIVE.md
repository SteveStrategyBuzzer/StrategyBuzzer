# 02_KernelRotationPlanner — Référence de travail historique

**Statut actuel :** **PROMOTED / CLOSED**  
**Date de promotion :** 2026-08-20  
**Promue vers :** `docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md` v3.3  
**Décision :** DEC-114

> Ce fichier n’est plus une source active. Il est conservé uniquement comme trace de la phase de reconstruction v3.3.

## Source canonique unique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
Version 3.3
VERROUILLÉ — PARTIE INTELLECTUELLE
```

La promotion canonique a intégré les décisions actives :

- DEC-094 — double autorité fin de tour / besoin global ;
- DEC-107 — garde terminale DOMAIN_EXHAUSTED ;
- DEC-108 — DEPTH_EXHAUSTED = fin d’un tour ;
- DEC-111 — persistance/idempotence des transitions KRP.

## Interdiction de reprise

Ne jamais utiliser ce document comme contrat d’implantation.

Prochaine source de travail :

```text
AUDIT-02-00
↓
audit du code KRP réel contre specifications/02_KernelRotationPlanner.md v3.3
```
