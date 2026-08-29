# STRATEGYBUZZER — 06_PHASE1 / CRÉATION COGNITIVE SOURCE

**Version :** 0.2  
**Date :** 29 août 2026  
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

Pour chaque QCM :

- exactement quatre choix;
- exactement une bonne réponse;
- la bonne réponse appartient aux quatre choix.

Pour chaque Vrai/Faux :

- exactement deux choix;
- la bonne réponse appartient aux deux choix.

# 3. Unité de création

```text
1 Blueprint
→ 1 identité intellectuelle
→ 7 CognitiveSlots source
```

Les sept slots ne sont ni sept Blueprints ni sept noyaux.

Les traductions futures restent attachées à ces mêmes sept slots.

Phase1 n’ajoute aucun segment `COG`, `VAR` ou `question_code`. L’identité d’un contenu cognitif est déterminée par le même `kernel_code` et l’emplacement permanent `cognitive_type` dans le Blueprint.

# 4. Création cognitive contrôlée dans un même appel

Un appel de création peut produire ensemble les sept CognitiveSlots afin de comparer les sept angles et de prévenir les répétitions dès la génération.

L’appel reçoit au minimum :

```text
blueprint_id
kernel_code
depth
domain
subdomain_active
subject_active
dominant_idea_active
sept cognitive_type attendus
règles de création
règles de lecture
schéma de sortie
```

La réponse structurée de chaque slot contient :

```text
cognitive_type
question
correct_answer
choices
sv
self_checks
```

Les `self_checks` sont des déclarations préventives de création. Ils ne constituent jamais une validation officielle autonome.

Contrôles préventifs demandés à la création :

```text
question_readable_under_8_seconds
sv_readable_under_30_seconds
correct_answer_explained_by_sv
cognitive_type_respected
one_correct_answer_only
choices_are_plausible
distinct_from_other_slots
same_subject_and_dominant_idea
question_answer_choices_sv_coherent_with_subdomain
```

# 5. Temps de lecture et difficulté

## 5.1 Question

Toute question doit pouvoir être lue en moins de huit secondes par une personne lisant à une vitesse normale à légèrement lente.

Cette règle s’applique à tous les Depths.

Le Depth représente la complexité intellectuelle de la connaissance ou du raisonnement. Il ne donne jamais l’autorisation d’allonger artificiellement la question.

Aucun minimum ou maximum contractuel de caractères ne remplace le contrôle du temps de lecture. Un nombre de caractères peut servir d’indicateur technique, jamais de règle intellectuelle principale.

Interdictions :

- aucun remplissage artificiel;
- aucune formulation inutilement longue pour simuler un Depth supérieur;
- aucune difficulté créée principalement par la lecture;
- aucune exigence d’étirer une question courte déjà complète.

## 5.2 SV — Saviez-vous

Chaque SV doit pouvoir être lu en moins de trente secondes.

Le SV :

- explique pourquoi la bonne réponse est correcte;
- reste dans le contexte cognitif de la question;
- apporte une explication utile;
- ne répète pas simplement la réponse;
- ne sort pas du territoire intellectuel du Blueprint;
- n’est jamais allongé artificiellement.

Aucun minimum ou maximum contractuel de caractères ne remplace le contrôle du temps de lecture.

# 6. Cohérence contextuelle obligatoire

Chaque création doit respecter la chaîne suivante :

```text
question
→ bonne réponse
→ choix
→ SV
→ dominant_idea_active
→ subject_active
→ subdomain_active
```

La bonne réponse doit répondre exactement à la question.

Les choix doivent être cohérents avec la question et plausibles dans le sous-domaine. Une proposition factuellement vraie mais appartenant à un autre contexte intellectuel doit être refusée.

Le SV doit expliquer la bonne réponse dans le contexte du cognitif, du sujet, de l’idée dominante et du sous-domaine.

# 7. Contrôles techniques locaux après création

Après la réponse de création, Phase1 vérifie localement au minimum :

- présence exacte des sept CognitiveSlots;
- unicité des sept `cognitive_type`;
- présence des champs obligatoires;
- structure JSON conforme;
- quatre choix et une seule bonne réponse pour les QCM;
- deux choix et une bonne réponse appartenant aux choix pour les Vrai/Faux;
- absence de champ vide obligatoire;
- absence de doublon textuel direct;
- respect estimé des temps de lecture;
- aucune modification de l’identité du Blueprint.

Ces contrôles techniques ne remplacent pas ValidationPhase1.

# 8. Suspicion pendant Phase1

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

Un CognitiveSlot non créé ou techniquement invalide demeure vide ou non admissible. Il n’est jamais rempli avec un contenu partiel présenté comme valide.

# 9. Reprise après correction

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

# 10. Invariants verrouillés

- même `blueprint_id`;
- même `kernel_code`;
- exactement sept CognitiveSlots;
- source et traductions dans le même Blueprint;
- Phase1 écrit uniquement les payloads source;
- aucune traduction par Phase1;
- aucun contenu joueur par Phase1;
- aucune modification de l’identité intellectuelle;
- aucun `question_code`, `COG` ou `VAR`;
- question lisible en moins de huit secondes;
- SV explicatif lisible en moins de trente secondes;
- difficulté portée par la connaissance ou le raisonnement, jamais par la longueur;
- cohérence jusqu’au sous-domaine;
- autocontrôle de création sans auto-certification;
- copie Quarantine complète;
- reprise ciblée;
- aucune traduction d’un CognitiveSlot source non validé.

# 11. Statut restant

Restent à spécifier avant Build terminal de Phase1 :

- règles détaillées propres à chacun des sept cognitifs;
- interdictions sémantiques de répétition entre les sept slots;
- règles précises de fabrication des distracteurs;
- stratégie exacte de mesure du temps de lecture par langue;
- format JSON terminal et versionné;
- retries techniques;
- preuves intellectuelles attendues;
- états détaillés des CognitiveSlots.

Le modèle consistant à créer un `QCM_RECOGNITION` master puis à dériver les six autres cognitifs est définitivement rejeté. Les sept cognitifs sont créés comme sept mécanismes intellectuels autonomes à partir de la même identité du Blueprint. Aucun cognitif ne sert de master textuel, structurel ou sémantique aux six autres.

La présente version verrouille les décisions confirmées sans déclarer Phase1 terminée.
