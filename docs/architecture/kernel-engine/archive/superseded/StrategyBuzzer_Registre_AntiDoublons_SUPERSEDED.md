> **ARCHIVE — NE PAS UTILISER COMME SOURCE ACTIVE.** Registre anti-doublons supersédé. Conservé uniquement pour la traçabilité historique.

# StrategyBuzzer — Ancien registre anti-doublons

**Statut : SUPERSEDED — NE PAS UTILISER COMME SOURCE DE VÉRITÉ**  
**Date de remplacement : 2026-08-16**

Les règles anti-doublon du moteur intellectuel appartiennent désormais exclusivement à :

```text
03_Taxonomy_ACTIVE_SPEC.md
```

Raison : maintenir une deuxième copie des règles anti-doublon créait un risque de divergence avec la spécification Taxonomy.

La formulation historique :

```text
un seul Subdomain par (Depth + Domain)
```

est remplacée par la règle active :

```text
un seul Subdomain
par occurrence de bassin
(Depth + occurrence du tour de Depth + Domain)
```

Le même `(Depth + Domain)` peut revenir dans un tour ultérieur et ouvre alors une nouvelle occurrence Taxonomy.

Les règles actives `SUBDOMAIN-LOOKBACK-2`, anti-doublon Subjects, Dominant Ideas PASS/FAIL et la traversée `Depth 10 → nouveau Depth 2` ne sont plus dupliquées ici.