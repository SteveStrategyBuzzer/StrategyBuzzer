# 03_Taxonomy — Boundary Bridge DEC-115

**Statut :** SUPERSEDED  
**Décision remplaçante :** DEC-116  
**Source active :** `working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-116.md`

Ce bridge est conservé uniquement pour l’historique intellectuel.

Il ne doit plus être utilisé comme vérité active, car DEC-115 faisait lire à KRP une réalité Taxonomy persistée. La frontière active DEC-116 est différente :

```text
Taxonomy
→ pousse DOMAIN_EXHAUSTED(depth, domain) comme FAIT de Banks vides

KRP
→ persiste ce fait dans RotationState
→ n’applique la rotation qu’au prochain Blueprint
```

Taxonomy n’envoie pas `DEPTH_EXHAUSTED` dans le contrat actif.
