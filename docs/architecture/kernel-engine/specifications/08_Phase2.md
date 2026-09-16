# STRATEGYBUZZER — 08_PHASE2 / TRADUCTIONS

**Version :** 0.3
**Date :** 2026-09-16
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décisions directrices :** DEC-125 + DEC-126 — OFFICIAL (clauses compatibles de DEC-122)
**Implémentation :** À AUDITER  
**Validation terminale :** NON

> **Remplace :** v0.1 sur le cycle Quarantine et la reprise des traductions.
> Les anciennes clauses de reprise directement en Phase2 sont
> `SUPERSEDED BY DEC-125`, sans suppression de leur historique.

## 0. Règles actives DEC-125

Phase2 intervient après le redémarrage Phase1 d’une copie complète et traduit
uniquement les slots autorisés par leur résultat courant. Les sept slots de la
copie accompagnent le contexte; les slots conformes continuent et les slots
échoués restent vides. Rouge = `SUSPICION`/`EMPTY`, vert = conforme et jamais
encore modifié manuellement, jaune = rempli/modifié manuellement
jusqu’à ReadyBank, marqueur de parcours et non validation. Tous les slots
restent éditables; une modification d’un vert invalide immédiatement son ancien
`PASS` aval.

Admin est une UI externe, non une phase. Le renvoi est mis en file dans l’ordre
exact des clics (FIFO), un seul traitement à la fois, sans démarrage immédiat.
ReadyBank reçoit toujours la copie complète; plusieurs copies prêtes et des
cycles successifs avec findings récents sont permis, sans historique permanent
de corrections.

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

Phase2 reçoit uniquement `blueprint_id`, recharge le même KernelBlueprint
canonique persistant, puis vérifie la création et la validation des contenus
source admissibles. Après son travail, elle transmet uniquement le même
`blueprint_id` à ValidationPhase2.

Elle ajoute, à l’intérieur de chacun des sept CognitiveSlots, les représentations linguistiques supplémentaires.

La source canonique est anglaise (`source_language = en`). Phase2 crée
exactement les neuf représentations obligatoires suivantes, sans langue
facultative :

```text
fr, es, de, it, pt, ru, zh, ar, el
```

Toute ancienne clause imposant une source française ou incluant `en` parmi les
langues cibles est `SUPERSEDED BY DEC-126`. Son historique n’est pas effacé et
aucune donnée existante n’est convertie par cette révision documentaire.

Une traduction ne crée jamais :

- un nouveau Blueprint;
- un nouveau noyau;
- un nouveau CognitiveSlot;
- une nouvelle identité intellectuelle.

# 2. Unité de traduction

Pour chaque CognitiveSlot et pour chaque langue supplémentaire, Phase2 traduit exactement :

```text
question
bonne réponse
choix de réponses
SV
```

Structure logique :

```text
CognitiveSlot
├── source
│   ├── question
│   ├── réponse
│   ├── choix
│   └── SV
└── translations
    └── langue
        ├── question
        ├── réponse
        ├── choix
        └── SV
```

La source n’est jamais remplacée par sa traduction.

# 3. Précondition source

Aucune traduction n’est créée pour un CognitiveSlot source soupçonné, invalide ou incomplet.

Dans ce cas :

```text
source soupçonnée
→ traduction de ce CognitiveSlot non créée / bloquée
```

Les autres CognitiveSlots admissibles peuvent conserver leurs traductions normalement.

# 4. Suspicion de traduction

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** la copie conserve
> toujours les sept slots éditables; l’édition n’est donc pas limitée aux
> seuls champs soupçonnés et la reprise ne démarre pas directement en Phase2.

Si la source est valide mais qu’une traduction est soupçonnée :

- la copie Quarantine contient le Blueprint complet;
- la source reste normale;
- les autres langues restent normales;
- seuls la langue et les champs soupçonnés sont marqués;
- l’interface les affiche en rouge.

Exemple :

```text
cognitive_slots.QCM_RECOGNITION.translations.el.answer
```

# 5. Reprise après correction

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** toute copie complète
> corrigée recommence en Phase1. Phase2 ne rejoue ensuite que la traduction
> autorisée du slot concerné.

La copie corrigée reprend Phase2 uniquement pour :

```text
CognitiveSlot ciblé
+ langue ciblée
+ champ(s) ciblé(s)
```

Puis :

```text
Phase2 ciblée
→ ValidationPhase2 ciblée
→ ReadyBank
→ réconciliation avec le canonique
```

Aucune source ou traduction valide non ciblée n’est recréée.

**Remplacement actif DEC-125 :** les slots valides continuent; les slots
échoués restent vides; la copie complète revient à ReadyBank après les
contrôles Phase1/ValidationPhase1 puis Phase2/ValidationPhase2 nécessaires.

# 6. Invariants verrouillés

- sept CognitiveSlots seulement;
- traductions imbriquées sous leur CognitiveSlot;
- question, réponse, choix et SV traduits;
- aucune traduction d’une source non validée;
- copie Quarantine complète;
- suspicion localisée par chemins structurés;
- reprise complète via Phase1, puis contrôles de traduction concernés par slot;
- fusion finale dans ReadyBank;
- même `blueprint_id` et même `kernel_code`.

# 7. Statut restant

Restent à spécifier :

- moteur de traduction;
- validations linguistiques détaillées;
- retries;
- politiques de contenu intraduisible;
- états détaillés;
- identité persistante exacte d’une traduction et rattachement à la révision source;
- structure des findings linguistiques et schémas de preuve;
- prédicats exacts d’admissibilité de la traduction et du slot;
- interface fournisseur et garanties d’idempotence;
- frontière terminale et progression partielle vers ReadyBank.

La présente version verrouille la structure, les frontières, la source anglaise
et les neuf codes cibles. Elle ne verrouille pas encore les décisions
fonctionnelles listées ci-dessus.
