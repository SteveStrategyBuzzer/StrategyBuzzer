# STRATEGYBUZZER — 09_VALIDATIONPHASE2

**Version :** 0.1  
**Date :** 29 août 2026  
**Statut :** FRONTIÈRE OFFICIELLE VERROUILLÉE — MODULE À COMPLÉTER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission verrouillée

ValidationPhase2 valide les traductions de la question, de la réponse, des choix et du SV à l’intérieur des sept CognitiveSlots du même KernelBlueprint.

Elle ne remplace jamais la source et ne crée aucun nouveau CognitiveSlot.

# 2. Sortie sans suspicion

Une traduction validée peut poursuivre vers ReadyBank avec le Blueprint canonique.

# 3. Sortie avec suspicion

Toute suspicion de traduction :

- identifie le CognitiveSlot;
- identifie la langue;
- identifie exactement les champs concernés;
- conserve la raison SV et les preuves disponibles;
- déclenche une copie complète du Blueprint vers Quarantine;
- permet l’affichage en rouge uniquement de la traduction soupçonnée;
- conserve normalement la source et les autres langues valides;
- n’empêche pas le Blueprint canonique de poursuivre jusqu’à ReadyBank;
- garde la traduction soupçonnée non exploitable.

# 4. Revalidation d’une copie corrigée

Une copie corrigée reprend ValidationPhase2 uniquement pour :

```text
CognitiveSlot ciblé
+ langue ciblée
+ champ(s) ciblé(s)
```

Après PASS, elle poursuit vers ReadyBank pour réconciliation avec le canonique.

# 5. Invariants

- copie Quarantine complète;
- source valide inchangée;
- autres langues valides inchangées;
- ciblage structuré;
- reprise ciblée;
- même `blueprint_id`;
- même `kernel_code`;
- aucune traduction suspecte exposée au gameplay.

# 6. Statut restant

Les règles linguistiques détaillées, codes PASS/FAIL, seuils, retries et schémas de preuve restent à spécifier.
