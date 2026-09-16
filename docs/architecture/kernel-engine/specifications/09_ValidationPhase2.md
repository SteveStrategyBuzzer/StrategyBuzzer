# STRATEGYBUZZER — 09_VALIDATIONPHASE2

**Version :** 0.3
**Date :** 2026-09-16
**Statut :** FRONTIÈRE OFFICIELLE VERROUILLÉE — MODULE À COMPLÉTER  
**Décisions directrices :** DEC-125 + DEC-126 — OFFICIAL (clauses compatibles de DEC-122)
**Implémentation :** À AUDITER  
**Validation terminale :** NON

> **Remplace :** v0.1 sur la reprise directe en ValidationPhase2 et les slots
> intouchables. Ces anciennes clauses sont `SUPERSEDED BY DEC-125` avant leur
> remplacement ci-dessous.

## 0. Règles actives DEC-125

ValidationPhase2 valide les traductions autorisées après le redémarrage
Phase1 d’une copie complète. Les sept slots accompagnent toujours la copie;
les slots conformes continuent, les slots échoués restent vides. Tous sont
visibles et éditables : rouge = `SUSPICION`/`EMPTY`, vert = conforme et jamais
encore modifié manuellement, jaune = rempli/modifié manuellement
jusqu’à ReadyBank, marqueur de parcours et non validation. Tous les slots
restent éditables; modifier un vert invalide immédiatement son ancien `PASS`
aval.

Le renvoi est une mise en file FIFO suivant l’ordre exact des clics, un seul
traitement à la fois, et non un démarrage. Admin est externe au pipeline.

**OPEN IMPLEMENTATION REQUIREMENTS — solutions non approuvées :**

1. persistance de la copie complète courante;
2. file d’attente des clics Renvoie;
3. ordre exact des demandes;
4. détection des slots modifiés;
5. conservation du marqueur jaune jusqu’à ReadyBank;
6. invalidation immédiate d’un ancien PASS après modification;
7. protection contre les retours périmés;
8. idempotence de `CURRENT_KERNEL_RECEIVED`;
9. fusion atomique dans ReadyBank.

---

# 1. Mission verrouillée

ValidationPhase2 reçoit uniquement `blueprint_id`, recharge le même
KernelBlueprint persistant, vérifie l’état terminal de Phase2, puis valide les
traductions de la question, de la réponse, des choix et du SV à l’intérieur de
ses sept CognitiveSlots.

La source de référence est exclusivement anglaise (`source_language = en`).
Les traductions validées appartiennent exactement aux neuf codes obligatoires :

```text
fr, es, de, it, pt, ru, zh, ar, el
```

ValidationPhase2 vérifie que chaque cible a été produite indépendamment et
directement depuis la même source anglaise. Toute traduction en chaîne entre
langues est interdite.

Toute ancienne clause évaluant une source française ou une traduction anglaise
depuis le français est `SUPERSEDED BY DEC-126`. Les contenus historiques ne
sont ni réécrits ni convertis par cette révision documentaire.

Elle ne remplace jamais la source et ne crée aucun nouveau CognitiveSlot.

# 2. Sortie sans suspicion

Après validation, ValidationPhase2 transmet uniquement le même `blueprint_id`
à ReadyBank, qui recharge le Blueprint canonique persistant.

# 3. Sortie avec suspicion

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** la copie corrigée ne
> reprend plus directement en Phase2 et l’édition n’est plus limitée aux seuls
> chemins suspects.

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

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** toute copie complète
> corrigée repart en Phase1; ValidationPhase2 ne rejoue ensuite que les
> contrôles de traduction autorisés.

Une copie corrigée reprend ValidationPhase2 uniquement pour :

```text
CognitiveSlot ciblé
+ langue ciblée
+ champ(s) ciblé(s)
```

Après PASS, elle poursuit vers ReadyBank pour réconciliation avec le canonique.

**Remplacement actif DEC-125 :** les slots conformes continuent; les slots
échoués restent vides et ReadyBank reçoit la copie complète.

# 5. Invariants

- copie Quarantine complète;
- source valide inchangée;
- autres langues valides inchangées;
- ciblage structuré;
- reprise complète via Phase1, puis validation de traduction concernée par slot;
- même `blueprint_id`;
- même `kernel_code`;
- aucune traduction suspecte exposée au gameplay.

# 6. Statut restant

Les règles linguistiques détaillées, codes PASS/FAIL, seuils, retries, schémas
de preuve, findings par champ, prédicats terminaux d’admissibilité, interface
fournisseur, idempotence et progression partielle restent à spécifier. La
langue source et les neuf langues cibles ne sont plus ouvertes.
