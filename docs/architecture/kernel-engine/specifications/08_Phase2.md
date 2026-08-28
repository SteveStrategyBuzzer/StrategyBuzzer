# STRATEGYBUZZER — 08_PHASE2 / TRADUCTIONS

**Version :** 0.1  
**Date :** 28 août 2026  
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission verrouillée

Phase2 reçoit le même KernelBlueprint canonique après création et validation des contenus source admissibles.

Elle ajoute, à l’intérieur de chacun des sept CognitiveSlots, les représentations linguistiques supplémentaires.

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

# 6. Invariants verrouillés

- sept CognitiveSlots seulement;
- traductions imbriquées sous leur CognitiveSlot;
- question, réponse, choix et SV traduits;
- aucune traduction d’une source non validée;
- copie Quarantine complète;
- suspicion localisée par chemins structurés;
- reprise ciblée;
- fusion finale dans ReadyBank;
- même `blueprint_id` et même `kernel_code`.

# 7. Statut restant

Restent à spécifier :

- langue source canonique;
- liste des langues obligatoires;
- codes linguistiques officiels;
- moteur de traduction;
- validations linguistiques détaillées;
- retries;
- politiques de contenu intraduisible;
- états détaillés.

La présente version verrouille uniquement la structure et les frontières.
