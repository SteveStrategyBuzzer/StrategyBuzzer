# 03_Taxonomy — Boundary Bridge DEC-116

**Statut :** SUPERSEDED — NON ACTIF  
**Remplacé par :** DEC-117  
**Source active :** `working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-117.md`

Ce document est conservé uniquement pour l'historique intellectuel.

Ne jamais reconstruire la frontière KRP/Taxonomy depuis DEC-116.

La correction DEC-117 impose notamment :

```text
Taxonomy FIN
→ DOMAIN_EXHAUSTED(depth,domain) = « ce Domain est vide »
→ fait en attente
→ KRP reste INACTIF
→ ReadyBank / Factory / nouveau Blueprint
→ KRP ACTIVE
→ consomme le fait
→ VISIBLE → ESTOMPÉ
```

KRP et Taxonomy ne sont jamais actifs simultanément.
