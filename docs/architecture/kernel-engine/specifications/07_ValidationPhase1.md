# STRATEGYBUZZER — 07_VALIDATIONPHASE1

**Version :** 1.0  
**Date :** 30 août 2026  
**Statut :** CONTRAT DE BUILD VERROUILLÉ — IMPLANTATION À AUDITER/RÉALIGNER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER CONTRE v1.0  
**Validation terminale :** NON

---

# 1. Mission

ValidationPhase1 valide officiellement les contenus source des sept `CognitiveSlots` du même `KernelBlueprint`.

Elle :

- n’ajoute aucun cognitif;
- ne traduit rien;
- ne corrige jamais silencieusement une création;
- ne modifie jamais l’identité intellectuelle;
- ne modifie jamais `kernel_code`;
- ne crée aucun état joueur.

Les `self_checks` de Phase1 sont préventifs. Ils ne constituent jamais le PASS officiel.

# 2. Entrées

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

ValidationPhase1 reçoit ensemble les slots créés du Blueprint afin de vérifier leurs distinctions croisées.

# 3. Contrôles déterministes locaux

- schéma et version reconnus;
- identité conforme au Blueprint;
- type cognitif officiel;
- champs obligatoires présents;
- nombres de choix conformes;
- bonne réponse présente dans les choix;
- une seule bonne réponse;
- polarité Vrai/Faux conforme au type;
- aucune option vide;
- aucune option dupliquée après normalisation;
- aucun doublon exact de question;
- temps de lecture estimé de la question et du SV;
- pour chaque QCM, bonne réponse et distracteurs conformes à une unité de réponse courte;
- aucun choix sous forme de phrase explicative, justification, énumération ou combinaison de plusieurs idées;
- homogénéité sémantique, grammaticale et de concision entre les quatre choix;
- aucune mutation de la Section 1.

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
- question lisible en huit secondes ou moins;
- limite de huit secondes appliquée au texte de la question seulement;
- réponse QCM limitée à un mot, un nom propre, une valeur courte ou une expression courte représentant une seule idée indivisible;
- distracteurs de même catégorie sémantique, de forme grammaticale comparable et de concision comparable;
- SV lisible en trente secondes ou moins;
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

- reçoit les créations sans pouvoir les modifier;
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

au moins un finding intellectuel ou structurel
→ SUSPICION
```

Un slot PASS peut poursuivre vers Phase2.

Un slot SUSPICION :

- n’est pas traduit;
- demeure non exploitable;
- est ciblé dans une copie complète Quarantine;
- n’empêche pas les autres slots PASS de poursuivre.

# 10. États et ownership

ValidationPhase1 écrit uniquement :

```text
NOT_VALIDATED
PASS
SUSPICION
```

Elle ne modifie pas :

```text
EMPTY
CREATED
CREATION_FAILED
```

Elle ne produit ni `READY` ni `CONSUMED`.

# 11. Échec technique de validation

Clé d’idempotence :

```text
blueprint_id + validation-phase1.v1 + validation_contract_version
```

Politique :

- maximum trois tentatives techniques au total;
- retry sur timeout, transport, JSON illisible ou identité divergente;
- aucun PASS par défaut;
- après épuisement : `SOURCE_VALIDATION_TECHNICAL_FAILURE`;
- traductions du slot concerné bloquées;
- contenu non exploitable;
- incident traçable;
- aucun contenu source réécrit.

# 12. Quarantine

Toute SUSPICION :

- identifie exactement le slot et les champs;
- conserve les raisons et preuves;
- crée une copie complète du Blueprint;
- permet l’affichage rouge des chemins ciblés;
- conserve normalement les slots valides;
- ne transforme pas les traductions non créées en erreurs de traduction.

Exemple :

```text
cognitive_slots.QCM_REASONING.source.correct_answer_key
```

# 13. Revalidation ciblée

Une copie corrigée reprend uniquement les slots et champs ciblés.

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
- SV ≤ 30 secondes;
- difficulté indépendante de la longueur;
- sous-domaine comme frontière contextuelle finale;
- validation cognitive propre à chaque type;
- comparaison croisée des sept slots;
- PASS/SUSPICION par slot;
- copie Quarantine complète;
- aucune traduction d’une source non PASS;
- aucun `question_code`, `COG` ou `VAR`.

# 15. Tests contractuels de Build

1. chaque mécanisme cognitif conforme → PASS;
2. mécanisme voisin mal étiqueté → SUSPICION;
3. rappel direct étiqueté reasoning → SUSPICION;
4. raisonnement étiqueté recognition → SUSPICION;
5. piège typographique → SUSPICION;
6. faux absurde → SUSPICION;
7. reasoning false fondé seulement sur une date changée → SUSPICION;
8. réponse absente des choix → SUSPICION;
9. deux bonnes réponses → SUSPICION;
10. choix formulé comme phrase explicative ou contenant plusieurs idées → SUSPICION;
11. choix de catégories ou formes incompatibles → SUSPICION;
12. mot composé, nom complet, date ou expression courte représentant une seule idée → accepté;
13. question > 8 secondes → SUSPICION;
14. SV > 30 secondes → SUSPICION;
15. question courte de Depth élevé → PASS si intellectuellement conforme;
16. contexte hors sous-domaine → SUSPICION;
17. doublon exact → SUSPICION;
18. reformulation sémantique → SUSPICION;
19. conversion QCM/TF mécanique → SUSPICION;
20. négation vrai/faux mécanique → SUSPICION;
21. un slot suspect ne bloque pas les slots PASS;
22. retry plafonné;
23. aucune correction automatique;
24. aucune traduction d’une source non PASS;
25. Section 1 immuable.

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
