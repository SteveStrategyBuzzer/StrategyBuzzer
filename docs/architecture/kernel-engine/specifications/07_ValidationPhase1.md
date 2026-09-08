# STRATEGYBUZZER — 07_VALIDATIONPHASE1

**Version :** 1.0  
**Date :** 30 août 2026  
**Statut :** CONTRAT DE BUILD VERROUILLÉ — IMPLANTATION À AUDITER/RÉALIGNER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER CONTRE v1.0  
**Validation terminale :** NON

---

# 1. Mission

ValidationPhase1 valide officiellement les contenus source techniquement
persistables des sept `CognitiveSlots` du même `KernelBlueprint`.

Elle :

- n’ajoute aucun cognitif;
- ne traduit rien;
- ne corrige jamais silencieusement une création;
- ne modifie jamais l’identité intellectuelle;
- ne modifie jamais `kernel_code`;
- ne crée aucun état joueur.

Les `self_checks` de Phase1 sont préventifs. Ils ne constituent jamais le PASS officiel.

La frontière est stricte : une structure invalide techniquement relève de
Phase1 et termine en `CREATION_FAILED`; elle ne parvient jamais à
ValidationPhase1 comme `SUSPICION`. Un contenu intellectuel techniquement
valide relève de ValidationPhase1 et peut produire `SUSPICION`.

# 2. Entrées et relais

L’entrée de ValidationPhase1 est exclusivement :

```text
blueprint_id
lookup du KernelBlueprint persistant
phase précédente terminée
statut terminal de la phase précédente
```

Aucun objet `Blueprint`, frame, tableau de slots ou copie autoritaire ne
transite entre phases. ValidationPhase1 retrouve le Blueprint persistant par
`blueprint_id`, puis lit les données dont elle a besoin dans cette source
unique.

Pour chaque slot créé :

```text
cognitive_type
question
choices
correct_answer_key
sv
creation_evidence
creation_status
validation_status
depth
domain
subdomain_active
subject_active
dominant_idea_active
source_language
kernel_code
```

ValidationPhase1 lit ensemble les sept slots persistés du Blueprint afin de
vérifier leurs distinctions croisées. Elle ne reçoit pas ces slots en entrée.

# 3. Précondition technique Phase1

Les contrôles suivants sont de la responsabilité de Phase1, avant la fin de sa
création. Leur échec est un échec de création : le slot est
`CREATION_FAILED`, les findings de création sont conservés par Phase1 et le
slot n’est pas soumis à ValidationPhase1.

- schéma et version reconnus;
- identité conforme au Blueprint;
- type cognitif officiel;
- champs obligatoires présents;
- nombres de choix conformes;
- bonne réponse présente dans les choix;
- pour chaque QCM, `choices.a` est la bonne réponse et `correct_answer_key = a`;
- une seule bonne réponse;
- polarité Vrai/Faux conforme au type;
- aucune option vide;
- aucune option dupliquée après normalisation;
- aucun doublon exact de question;
- temps de lecture estimé de la question et du SV conformes;
- aucune mutation de la Section 1.

La forme courte d’une réponse ou d’un distracteur et l’homogénéité
intellectuelle des choix sont des contrôles de contenu de ValidationPhase1 :
si la structure est persistable mais que ces exigences ne sont pas
satisfaites, elles produisent `SUSPICION`.

# 4. Contrôles intellectuels officiels

ValidationPhase1 contrôle :

- factualité de la question;
- factualité de la bonne réponse;
- conformité à l’opération mentale du `cognitive_type`;
- réponse exacte à la question;
- plausibilité des distracteurs;
- absence de seconde bonne réponse;
- absence d’ambiguïté;
- SV expliquant réellement la bonne réponse;
- réponse QCM limitée à un mot, un nom propre, une valeur courte ou une expression courte représentant une seule idée indivisible;
- distracteurs de même catégorie sémantique, de forme grammaticale comparable et de concision comparable;
- absence de remplissage artificiel;
- cohérence contextuelle complète;
- distinction sémantique entre les sept slots;
- absence de conversion mécanique QCM ↔ Vrai/Faux;
- absence de négation mécanique vrai ↔ faux;
- piège cognitif loyal et non typographique.

Chaîne contextuelle obligatoire :

```text
question
→ bonne réponse
→ choix
→ SV
→ dominant_idea_active
→ subject_active
→ subdomain_active
```

Une information vraie mais appartenant à un autre sous-domaine est refusée.

Le Depth détermine la difficulté intellectuelle, jamais une longueur minimale.

# 5. Critères par cognitif

## QCM_RECOGNITION

PASS seulement si la réponse provient d’un rappel factuel direct, sans inférence.

## QCM_REASONING

PASS seulement si la réponse exige au moins un lien causal, comparatif, conséquentiel ou déductif.

## QCM_TRAP

PASS seulement si le piège cible une intuition ou confusion plausible liée au contenu. Une ambiguïté de formulation, une double négation ou un détail typographique produit SUSPICION.

## TRUE_FALSE_RECOGNITION_TRUE

PASS seulement pour un fait atomique entièrement vrai et directement reconnaissable.

## TRUE_FALSE_RECOGNITION_FALSE

PASS seulement pour un fait atomique faux mais plausible, avec une erreur décisive claire. Une absurdité ou plusieurs erreurs produisent SUSPICION.

## TRUE_FALSE_REASONING_TRUE

PASS seulement pour une relation logique vraie nécessitant un raisonnement.

## TRUE_FALSE_REASONING_FALSE

PASS seulement si la fausseté réside dans un lien logique plausible mais incorrect. Un simple changement de nom, date ou lieu ne suffit pas.

# 6. Validation indépendante

Un appel de revue indépendant peut contrôler les sept slots ensemble.

Le reviewer :

- reçoit une projection de revue en lecture seule, produite depuis le
  Blueprint persisté, sans pouvoir la modifier;
- reçoit les règles v1.0;
- retourne uniquement PASS ou des findings structurés;
- ne génère aucun remplacement;
- ne modifie aucune identité;
- ne déclenche aucune traduction.

Sortie de revue :

```json
{
  "schema_version": "validation-phase1.v1",
  "blueprint_id": "...",
  "kernel_code": "...",
  "slots": [
    {
      "cognitive_type": "QCM_RECOGNITION",
      "decision": "PASS",
      "findings": []
    }
  ],
  "cross_slot_findings": []
}
```

Chaque finding contient :

```text
reason_code
field_paths
explanation
evidence
related_cognitive_types si comparaison croisée
```

# 7. Codes de raisons officiels

Les codes structurels ci-dessous sont des findings de création Phase1 : ils
aboutissent à `CREATION_FAILED` et ne sont jamais écrits par
ValidationPhase1. Les autres codes sont des findings de validation et peuvent
accompagner `SUSPICION`.

```text
SOURCE_SCHEMA_INVALID
SOURCE_SLOT_MISSING
SOURCE_FIELD_MISSING
SOURCE_IDENTITY_MISMATCH
SOURCE_COGNITIVE_TYPE_MISMATCH
SOURCE_CHOICE_COUNT_INVALID
SOURCE_ANSWER_NOT_IN_CHOICES
SOURCE_MULTIPLE_CORRECT_ANSWERS
SOURCE_TRUE_FALSE_POLARITY_INVALID
SOURCE_CHOICE_NOT_CONCISE
SOURCE_CHOICE_MULTIPLE_IDEAS
SOURCE_CHOICES_HETEROGENEOUS
SOURCE_QUESTION_READ_TIME_EXCEEDED
SOURCE_SV_READ_TIME_EXCEEDED
SOURCE_FACTUAL_SUSPICION
SOURCE_ANSWER_INCOHERENT
SOURCE_DISTRACTOR_INVALID
SOURCE_AMBIGUOUS
SOURCE_SV_INVALID
SOURCE_CONTEXT_MISMATCH
SOURCE_COGNITIVE_MECHANISM_MISMATCH
SOURCE_CROSS_SLOT_DUPLICATE
SOURCE_MECHANICAL_QCM_TF_CONVERSION
SOURCE_MECHANICAL_TRUE_FALSE_NEGATION
SOURCE_TRAP_UNFAIR
SOURCE_VALIDATION_TECHNICAL_FAILURE
```

# 8. Anti-répétition entre slots

ValidationPhase1 compare les sept questions et leurs propositions intellectuelles.

SUSPICION si :

- même question reformulée;
- même proposition convertie dans un autre format;
- même relation logique répétée;
- vrai et faux obtenus par négation mécanique;
- QCM_TRAP réutilisant seulement un distracteur d’un autre QCM;
- justification de différence absente ou non défendable.

Aucun seuil numérique unique n’est déclaré comme vérité métier. Le contrôle associe :

1. normalisation exacte locale;
2. comparaison structurée des opérations mentales;
3. revue sémantique avec findings explicables.

# 9. Décision par slot

```text
aucun finding
→ PASS

au moins un finding intellectuel
→ SUSPICION
```

Un slot PASS peut poursuivre vers Phase2.

Un slot SUSPICION :

- n’est pas traduit;
- demeure non exploitable;
- est ciblé dans une copie complète Quarantine;
- n’empêche pas les autres slots PASS de poursuivre.

# 10. États et ownership

Après lecture du Blueprint persistant, ValidationPhase1 écrit uniquement ses
statuts de validation et ses findings de validation, sur son ownership :

```text
NOT_VALIDATED
PASS
SUSPICION
```

Elle ne crée, ne remplace ni ne corrige aucun slot. Elle ne modifie pas :

```text
EMPTY
CREATED
CREATION_FAILED
```

Elle ne produit ni `READY` ni `CONSUMED`.

## 10.1 Frontière de persistance et ownership

Le `KernelBlueprint` est une structure persistante extérieure aux phases,
créée une seule fois par `KernelBlueprintFactory` (`KBP`). Il conserve le même
`blueprint_id`, est progressivement rempli, et demeure la source de vérité
unique. Aucune copie autoritaire, ni aucun objet `Blueprint`, n’est autorisé
comme transport entre phases.

Sa Section 1 est persistée dans `kernel_blueprint_runs`; les sept slots sont
persistés séparément dans `kernel_blueprint_cognitive_slots` sous la clé
`(blueprint_id, cognitive_type)`.

La contrainte d’unicité garantit une seule occurrence de chaque type cognitif
par Blueprint. Chaque phase retrouve ce même Blueprint par identifiant, lit et
écrit seulement son ownership, persiste, puis signale sa fin avec son statut
terminal. ValidationPhase1 met à jour exclusivement les statuts et findings
de validation des slots ciblés, sans réécrire un frame global ni modifier la
Section 1.

`question_intents.frame_en` est legacy et non autoritaire. Ni Phase1 ni
ValidationPhase1 n’y trouvent la source canonique des slots. Les traductions,
le masque joueur, le mélange des choix et les données joueur restent hors de
la persistance Phase1.

# 11. Échec technique du fournisseur de validation

Clé d’idempotence :

```text
blueprint_id + validation-phase1.v1 + validation_contract_version
```

Politique :

- maximum trois tentatives techniques au total;
- retry sur timeout, transport ou JSON illisible du fournisseur;
- aucun PASS par défaut;
- après épuisement : finding `SOURCE_VALIDATION_TECHNICAL_FAILURE` et statut
  `SUSPICION` du slot techniquement persistable concerné;
- traductions du slot concerné bloquées;
- contenu non exploitable;
- incident traçable;
- aucun contenu source réécrit.

Une identité divergente, un schéma invalide ou toute autre structure source
invalide n’est pas un échec de ce fournisseur : cette donnée aurait dû être
arrêtée par Phase1 en `CREATION_FAILED` et ne peut pas être reclassée en
`SUSPICION`.

# 12. Quarantine

Le traitement Quarantine, extérieur à ValidationPhase1 et déclenché après son
statut terminal, traite toute `SUSPICION` intellectuelle ou due à l’échec
technique du fournisseur de validation :

- identifie exactement le slot et les champs;
- conserve les raisons et preuves;
- crée une copie complète du Blueprint;
- permet l’affichage rouge des chemins ciblés;
- conserve normalement les slots valides;
- ne transforme pas les traductions non créées en erreurs de traduction.

La copie Quarantine est strictement forensique et non autoritaire : le
`KernelBlueprint` persistant conserve seul la vérité et son `blueprint_id`.
ValidationPhase1 ne crée pas cette copie : elle écrit seulement ses statuts et
findings.

Exemple :

```text
cognitive_slots.QCM_REASONING.source.correct_answer_key
```

# 13. Revalidation ciblée

Une correction reprend uniquement les slots et champs ciblés dans le
`KernelBlueprint` persistant. Elle passe par le chemin de création approprié;
le relais vers ValidationPhase1 reste un `blueprint_id`, non une copie
Blueprint transportée.

```text
correction Phase1
→ ValidationPhase1 ciblée
→ PASS
→ Phase2 ciblée
```

Les slots PASS non ciblés ne sont pas rejoués.

# 14. Invariants

- même `blueprint_id`;
- même `kernel_code`;
- aucune réécriture silencieuse;
- validation indépendante des `self_checks`;
- question seule ≤ 8 secondes;
- chaque réponse et distracteur QCM forme une unité courte représentant une seule idée;
- QCM : bonne réponse canonique en `a`, distracteurs en `b`, `c`, `d`;
- SV ≤ 30 secondes;
- difficulté indépendante de la longueur;
- sous-domaine comme frontière contextuelle finale;
- validation cognitive propre à chaque type;
- comparaison croisée des sept slots;
- PASS/SUSPICION par slot;
- copie Quarantine complète;
- aucune traduction d’une source non PASS;
- aucun `question_code`, `COG` ou `VAR`.
- aucune structure techniquement invalide classée `SUSPICION`;
- aucun champ de mode, target ou test dans le Blueprint.

# 15. Tests contractuels de Build

1. chaque mécanisme cognitif conforme → PASS;
2. mécanisme voisin mal étiqueté → SUSPICION;
3. rappel direct étiqueté reasoning → SUSPICION;
4. raisonnement étiqueté recognition → SUSPICION;
5. piège typographique → SUSPICION;
6. faux absurde → SUSPICION;
7. reasoning false fondé seulement sur une date changée → SUSPICION;
8. réponse absente des choix → Phase1 / `CREATION_FAILED`, jamais ValidationPhase1;
9. deux bonnes réponses → Phase1 / `CREATION_FAILED`, jamais ValidationPhase1;
10. clé QCM différente de `a`, nombre de choix invalide, polarité invalide,
    champ obligatoire absent ou choix vide/dupliqué → Phase1 /
    `CREATION_FAILED`, jamais `SUSPICION`;
11. choix formulé comme phrase explicative ou contenant plusieurs idées → SUSPICION;
12. choix de catégories ou formes incompatibles → SUSPICION;
13. mot composé, nom complet, date ou expression courte représentant une seule idée → accepté;
14. question > 8 secondes → Phase1 / `CREATION_FAILED`, jamais `SUSPICION`;
15. SV > 30 secondes → Phase1 / `CREATION_FAILED`, jamais `SUSPICION`;
16. question courte de Depth élevé → PASS si intellectuellement conforme;
17. contexte hors sous-domaine → SUSPICION;
18. doublon exact → Phase1 / `CREATION_FAILED`, jamais `SUSPICION`;
19. reformulation sémantique → SUSPICION;
20. conversion QCM/TF mécanique → SUSPICION;
21. négation vrai/faux mécanique → SUSPICION;
22. un slot suspect ne bloque pas les slots PASS;
23. retry plafonné;
24. aucune correction automatique;
25. aucune traduction d’une source non PASS;
26. QCM : `correct_answer_key` différent de `a` → Phase1 /
    `CREATION_FAILED`, jamais `SUSPICION`;
27. Section 1 immuable;
28. l’entrée de ValidationPhase1 est uniquement `blueprint_id`; elle recharge
    le Blueprint et y vérifie le statut terminal précédent; aucun objet
    Blueprint ou tableau de slots ne transite;
29. ValidationPhase1 demande, par son point d'entrée de test, un vrai
    `KernelBlueprint` PostgreSQL canonique, complet structurellement et vide
    intellectuellement ; KBP le crée atomiquement avec les sept vrais slots et
    retourne uniquement `blueprint_id` ;
30. les paramètres et dépendances simulées propres au test appartiennent
    exclusivement à ValidationPhase1 et à son point d'entrée ; KBP ne les
    reçoit pas et ne prépare aucune donnée intellectuelle ;
31. l'appelant technique observe le résultat terminal et le persistant, puis
    demande à KBP la terminaison ; il ne prépare, n'écrit, ne relaie et ne
    supprime aucune donnée, et ne constitue pas un Harness architectural ;
32. les modes production et test sont entièrement externes au Blueprint :
    production relaie vers ValidationPhase1, test relaie vers un récepteur
    terminal, sans variante métier Phase1.

# 15.1 Point d'entrée de test et observation terminale

Le mode de production comme le mode test est déterminé hors du
`KernelBlueprint`. Aucun champ `mode`, `target`, `test` ou équivalent ne peut
être persisté dans le Blueprint.

En production, Phase1 persiste sa fin puis transmet uniquement `blueprint_id`
à ValidationPhase1, qui recharge et vérifie cet état. En test,
ValidationPhase1 demande, par son propre point
d'entrée, la mise à disposition d'un Blueprint canonique vide. KBP crée la
même structure qu'en production et retourne uniquement `blueprint_id`.

Les paramètres intellectuels nécessaires au test, les contenus contrôlés et
les dépendances simulées sont fournis directement à ValidationPhase1 par son
environnement de test. Ils ne transitent pas par KBP et KBP ne les inscrit pas
dans le Blueprint. L'appelant technique observe le résultat terminal et le
persistant, puis demande à KBP la terminaison. Il n'est pas propriétaire du
Blueprint, ne transmet pas la clé entre les phases, n'écrit ni précondition,
ni contenu, ni statut ou finding, ne supprime aucune donnée directement et ne
constitue pas un Harness architectural.

# 16. Statut

```text
Architecture :          VERROUILLÉE
Contrat :               VERROUILLÉ v1.0
Spécification :         BUILD-READY
Implémentation :        À AUDITER/RÉALIGNER
Validation terminale :  NON
```

Prochaine opération :

```text
ALIGN-AUDIT-07-v1.0
→ audit du code après ou avec Phase1
→ KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
→ patch minimal séparé de Phase1 si nécessaire
```
