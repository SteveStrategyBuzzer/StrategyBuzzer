# STRATEGYBUZZER — 06_PHASE1 / CRÉATION COGNITIVE SOURCE

**Version :** 0.1  
**Date :** 28 août 2026  
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission verrouillée

Phase1 reçoit le même KernelBlueprint canonique portant son identité intellectuelle complète et remplit exactement sept CognitiveSlots dans la langue source.

Phase1 ne crée aucun nouveau Blueprint et ne modifie jamais la Section 1.

# 2. Sept CognitiveSlots

```text
QCM_RECOGNITION
QCM_REASONING
QCM_TRAP
TRUE_FALSE_RECOGNITION_TRUE
TRUE_FALSE_RECOGNITION_FALSE
TRUE_FALSE_REASONING_TRUE
TRUE_FALSE_REASONING_FALSE
```

Chaque CognitiveSlot appartient au même Blueprint et contient dans la langue source :

```text
question
bonne réponse
choix de réponses
SV
```

Pour les QCM, les choix couvrent normalement `a/b/c/d`.

Pour les Vrai/Faux, les choix couvrent normalement `a/b`.

Le contrat détaillé de génération cognitive et la signification complète de SV restent à compléter dans ce document avant implantation terminale.

# 3. Unité de création

```text
1 Blueprint
→ 1 identité intellectuelle
→ 7 CognitiveSlots source
```

Les sept slots ne sont ni sept Blueprints ni sept noyaux.

Les traductions futures restent attachées à ces mêmes sept slots.

# 4. Suspicion pendant Phase1

Lorsqu’un contenu source est soupçonné :

- le Blueprint canonique poursuit son parcours normal jusqu’à ReadyBank;
- une copie complète du Blueprint est créée pour Quarantine;
- le ou les champs soupçonnés sont marqués structurellement;
- l’interface Quarantine les affiche en rouge;
- les autres CognitiveSlots valides restent visibles et inchangés;
- les traductions dépendant du CognitiveSlot source non validé ne sont pas créées;
- ces traductions sont indiquées comme non créées ou bloquées, jamais comme erreurs de traduction.

Exemple de cible :

```text
cognitive_slots.QCM_RECOGNITION.source.question
```

# 5. Reprise après correction

Une copie corrigée à la source reprend Phase1 uniquement pour les champs ou le CognitiveSlot ciblés.

Après réussite :

```text
Phase1 ciblée
→ ValidationPhase1 ciblée
→ Phase2 / traductions de ce CognitiveSlot
→ ValidationPhase2
→ ReadyBank
→ réconciliation avec le canonique
```

Les CognitiveSlots déjà valides ne sont pas recréés.

# 6. Invariants verrouillés

- même `blueprint_id`;
- même `kernel_code`;
- exactement sept CognitiveSlots;
- source et traductions dans le même Blueprint;
- Phase1 écrit uniquement les payloads source;
- aucune traduction par Phase1;
- aucun contenu joueur par Phase1;
- aucune modification de l’identité intellectuelle;
- copie Quarantine complète;
- reprise ciblée;
- aucune traduction d’un CognitiveSlot source non validé.

# 7. Statut restant

Restent à spécifier avant Build terminal de Phase1 :

- prompts et contrats de génération;
- schéma exact question/réponse/choix;
- contrat complet SV;
- états détaillés;
- retries;
- validation source;
- règles de variantes éventuelles.

La présente version verrouille la structure et les frontières sans déclarer Phase1 terminée.
