# STRATEGYBUZZER — 06_PHASE1 / CRÉATION COGNITIVE SOURCE

**Version :** 1.0  
**Date :** 30 août 2026  
**Statut :** CONTRAT DE BUILD VERROUILLÉ — IMPLANTATION À AUDITER/RÉALIGNER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER CONTRE v1.0  
**Validation terminale :** NON

---

# 1. Mission

Phase1 reçoit le même `KernelBlueprint` canonique portant son identité intellectuelle complète et remplit exactement sept `CognitiveSlots` dans la langue source.

Phase1 :

- ne crée aucun nouveau Blueprint;
- ne modifie jamais la Section 1;
- ne choisit ni Depth, ni Domain, ni Taxonomy;
- ne modifie jamais `kernel_code`;
- ne crée aucune traduction;
- ne crée aucun état joueur;
- ne produit aucun `question_code`, `COG` ou `VAR`.

# 2. Unité de création

```text
1 Blueprint
→ 1 identité intellectuelle
→ 7 CognitiveSlots permanents
→ 7 créations source autonomes
```

Les sept slots partagent :

```text
blueprint_id
kernel_code
depth
domain
subdomain_active
subject_active
dominant_idea_active
source_language
```

Ils ne sont ni sept Blueprints ni sept noyaux.

Le modèle « QCM_RECOGNITION master puis six dérivés » est définitivement rejeté. Aucun cognitif ne sert de master textuel, structurel ou sémantique aux six autres.

# 3. Sept CognitiveSlots officiels

```text
QCM_RECOGNITION
QCM_REASONING
QCM_TRAP
TRUE_FALSE_RECOGNITION_TRUE
TRUE_FALSE_RECOGNITION_FALSE
TRUE_FALSE_REASONING_TRUE
TRUE_FALSE_REASONING_FALSE
```

Chaque slot source contient :

```text
cognitive_type
question
choices
correct_answer_key
sv
creation_evidence
creation_status
validation_status
```

# 4. Règles propres aux sept cognitifs

## 4.1 QCM_RECOGNITION

Opération mentale :

```text
Reconnaître ou rappeler directement un fait.
```

Obligatoire :

- une information factuelle principale;
- une réponse accessible sans déduction;
- une formulation directe;
- quatre choix plausibles du même contexte;
- exactement une bonne réponse.

Formes naturelles : `Qui?`, `Quoi?`, `Où?`, `Quand?`, `Lequel?`.

Interdit :

- causalité ou conséquence à déduire;
- comparaison nécessitant une inférence;
- hypothèse;
- piège fondé sur une intuition;
- réponse nécessitant plusieurs étapes mentales.

Discriminateur :

```text
La réponse est obtenue par rappel direct d’un fait
→ QCM_RECOGNITION possible.
```

## 4.2 QCM_REASONING

Opération mentale :

```text
Déduire la réponse par au moins un lien logique.
```

Obligatoire :

- causalité, conséquence, comparaison, relation ou déduction;
- une seule conclusion défendable;
- suffisamment d’information pour raisonner;
- distracteurs représentant des raisonnements plausibles mais incorrects.

Formes naturelles : `Pourquoi?`, `Quelle conséquence?`, `Quelle conclusion?`, `Quelle explication?`.

Interdit :

- simple rappel d’un nom, d’un lieu, d’une date ou d’une définition;
- réponse directement contenue dans la formulation;
- difficulté reposant seulement sur un vocabulaire compliqué;
- raisonnement dépendant d’une donnée absente ou invérifiable.

Discriminateur :

```text
Le joueur doit relier des éléments ou appliquer une relation logique
→ QCM_REASONING possible.
```

## 4.3 QCM_TRAP

Opération mentale :

```text
Détecter et corriger une intuition ou une confusion plausible.
```

Obligatoire :

- une confusion réellement plausible dans le sous-domaine;
- un choix réflexe attirant mais incorrect;
- une bonne réponse factuelle et démontrable;
- suffisamment d’information pour éviter une devinette injuste.

Interdit :

- double négation;
- formulation volontairement ambiguë;
- mot caché ou détail typographique;
- réponse impossible à déterminer;
- distracteur absurde;
- piège reposant uniquement sur `sauf`, `ne… pas` ou une lecture inattentive;
- obscurité utilisée artificiellement comme difficulté.

Discriminateur :

```text
Le piège provient d’une croyance ou intuition plausible liée au contenu
→ QCM_TRAP possible.
Sinon → formulation trompeuse, donc refus.
```

## 4.4 TRUE_FALSE_RECOGNITION_TRUE

Opération mentale :

```text
Reconnaître directement qu’un fait atomique est vrai.
```

Obligatoire :

- une seule affirmation factuelle;
- affirmation entièrement vraie;
- aucune déduction nécessaire;
- aucune ambiguïté.

Interdit :

- relation causale à analyser;
- combinaison de plusieurs affirmations;
- vérité seulement partielle;
- formulation conditionnelle complexe.

## 4.5 TRUE_FALSE_RECOGNITION_FALSE

Opération mentale :

```text
Reconnaître directement qu’un fait atomique est faux.
```

Obligatoire :

- affirmation plausible;
- un élément factuel décisif rend l’affirmation fausse;
- correction claire et démontrable.

Interdit :

- affirmation ridicule ou impossibilité évidente;
- double négation;
- plusieurs erreurs simultanées;
- négation mécanique du slot vrai correspondant.

## 4.6 TRUE_FALSE_REASONING_TRUE

Opération mentale :

```text
Confirmer qu’une relation logique est vraie.
```

Obligatoire :

- causalité, conséquence, comparaison ou déduction;
- affirmation vraie dans son ensemble;
- nécessité de comprendre la relation, pas seulement un fait isolé.

Interdit :

- simple date, nom, lieu ou définition;
- vérité reconnaissable sans raisonnement;
- relation vague ou contestable.

Discriminateur :

```text
La vérité dépend de la validité d’un lien logique
→ TRUE_FALSE_REASONING_TRUE possible.
```

## 4.7 TRUE_FALSE_REASONING_FALSE

Opération mentale :

```text
Détecter qu’une relation logique plausible est incorrecte.
```

Obligatoire :

- relation causale, comparative ou déductive plausible;
- erreur située dans le lien logique;
- explication claire de la raison pour laquelle la relation échoue.

Interdit :

- rendre l’affirmation fausse uniquement en changeant un nom, une date ou un lieu;
- affirmation manifestement absurde;
- négation mécanique du raisonnement vrai;
- erreur sans rapport avec le raisonnement.

Discriminateur :

```text
La fausseté provient d’un lien logique incorrect
→ TRUE_FALSE_REASONING_FALSE possible.
```

# 5. Séparation obligatoire entre les sept slots

- aucun slot ne peut être une reformulation d’un autre;
- aucun QCM ne peut être simplement converti en Vrai/Faux;
- un slot faux ne peut pas être la négation mécanique d’un slot vrai;
- chaque slot traite une proposition, relation ou confusion distincte;
- les sept slots partagent le territoire intellectuel sans partager la même mécanique;
- une réponse identique n’est tolérable que si la question, la proposition visée et l’opération mentale sont réellement distinctes;
- chaque slot explique sa différence cognitive avec les six autres;
- si un type conforme ne peut pas être produit, la sortie est `SLOT_UNCREATABLE`, jamais un contenu mal classé.

# 6. Structure des réponses

## 6.1 QCM

```text
choices = a, b, c, d
exactement quatre textes distincts
correct_answer_key ∈ {a,b,c,d}
exactement une bonne réponse
```

La bonne réponse et chaque distracteur constituent une unité de réponse courte.

Une unité de réponse est :

- un mot;
- un nom propre;
- une valeur courte, notamment un nombre ou une date;
- ou une expression courte représentant une seule idée indivisible, comme un mot composé, un titre ou un nom complet.

Exemples conformes :

```text
Protée anguillard
Empire romain
Océan Atlantique
15 avril 1912
```

Interdictions :

- aucune phrase explicative comme choix;
- aucune justification intégrée à la réponse;
- aucune combinaison de plusieurs idées;
- aucune énumération;
- aucune proposition contenant sa propre conclusion;
- aucun choix artificiellement plus long pour signaler la bonne réponse.

Les distracteurs :

- restent dans le même sous-domaine;
- sont plausibles sans devenir ambigus;
- appartiennent à la même catégorie sémantique et à une forme grammaticale comparable;
- présentent une concision comparable;
- ne révèlent pas la bonne réponse par leur longueur, leur précision ou leur forme;
- ne sont ni absurdes ni hors contexte.

### 6.1.1 Position canonique de la bonne réponse

Dans chacun des trois cognitifs QCM :

```text
QCM_RECOGNITION
QCM_REASONING
QCM_TRAP
```

la bonne réponse est toujours stockée dans le choix canonique `a` :

```text
choices.a = bonne réponse
choices.b = distracteur 1
choices.c = distracteur 2
choices.d = distracteur 3
correct_answer_key = a
```

Phase1 applique cette position lors de la création source. Les traductions,
copies Quarantine, corrections et réconciliations conservent exactement cette
correspondance. Le Blueprint ne mélange jamais les choix et ne modifie jamais
`correct_answer_key`.

Le mélange de l’ordre d’affichage appartient exclusivement au gameplay et ne
réécrit jamais les choix persistés. Le résultat joueur doit conserver
l’identité de la clé canonique du choix présenté.

## 6.2 Vrai/Faux

```text
choices = a, b
a = libellé localisé de VRAI
b = libellé localisé de FAUX
correct_answer_key ∈ {a,b}
```

Polarité obligatoire :

```text
TRUE_FALSE_RECOGNITION_TRUE  → VRAI
TRUE_FALSE_RECOGNITION_FALSE → FAUX
TRUE_FALSE_REASONING_TRUE    → VRAI
TRUE_FALSE_REASONING_FALSE   → FAUX
```

# 7. Temps de lecture et difficulté

## 7.1 Question

Toute question doit pouvoir être lue en huit secondes ou moins par une personne lisant à une vitesse normale à légèrement lente.

Cette règle s’applique à tous les Depths.

Le Depth représente la complexité intellectuelle de la connaissance ou du raisonnement. Il ne donne jamais l’autorisation d’allonger artificiellement la question.

Aucun minimum ou maximum de caractères n’est contractuel.

La limite de huit secondes concerne le texte de la question. Les choix obéissent séparément au contrat d’unité de réponse courte de la section 6.

## 7.2 SV — Saviez-vous

Chaque SV doit pouvoir être lu en trente secondes ou moins.

Le SV :

- explique pourquoi la bonne réponse est correcte;
- reste dans le contexte cognitif de la question;
- apporte une explication utile;
- ne répète pas simplement la réponse;
- n’est jamais allongé artificiellement.

## 7.3 Estimation locale

La validation locale utilise une configuration versionnée par langue :

```text
reading_speed_wpm[source_language]
```

Valeur de repli initiale :

```text
150 mots/minute
```

Formule indicative :

```text
estimated_seconds = nombre_de_mots_normalisés / WPM × 60
```

Cette estimation est une garde reproductible. Elle ne crée aucun minimum de longueur et ne remplace pas le contrôle intellectuel d’une formulation lourde.

# 8. Cohérence contextuelle

```text
question
→ bonne réponse
→ choix
→ SV
→ dominant_idea_active
→ subject_active
→ subdomain_active
```

La bonne réponse répond exactement à la question.

Les choix sont cohérents avec la question et plausibles dans le sous-domaine.

Une proposition factuellement vraie mais appartenant à un autre contexte intellectuel est refusée.

# 9. Appel Gemini de création

Un seul appel de création demande les sept slots ensemble.

## 9.1 Entrée

```text
schema_version
generation_contract_version
blueprint_id
kernel_code
depth
domain
subdomain_active
subject_active
dominant_idea_active
source_language
cognitive_rules
reading_rules
output_schema
```

## 9.2 Sortie JSON v1

```json
{
  "schema_version": "phase1.source.v1",
  "blueprint_id": "...",
  "kernel_code": "...",
  "source_language": "fr",
  "slots": [
    {
      "cognitive_type": "QCM_RECOGNITION",
      "question": "...",
      "choices": [
        {"key": "a", "text": "..."},
        {"key": "b", "text": "..."},
        {"key": "c", "text": "..."},
        {"key": "d", "text": "..."}
      ],
      "correct_answer_key": "a",
      "sv": "...",
      "creation_evidence": {
        "cognitive_operation": "...",
        "cognitive_justification": "...",
        "difference_from_other_slots": "...",
        "truth_basis": "...",
        "trap_basis": null,
        "self_checks": {
          "question_readable_under_8_seconds": true,
          "sv_readable_under_30_seconds": true,
          "correct_answer_explained_by_sv": true,
          "cognitive_type_respected": true,
          "one_correct_answer_only": true,
          "choices_are_plausible": true,
          "distinct_from_other_slots": true,
          "same_subject_and_dominant_idea": true,
          "question_answer_choices_sv_coherent_with_subdomain": true
        }
      }
    }
  ]
}
```

Règles :

- exactement sept entrées;
- chaque `cognitive_type` apparaît une seule fois;
- `trap_basis` est obligatoire pour `QCM_TRAP` et nul pour les autres;
- Gemini répète `blueprint_id` et `kernel_code` seulement pour corrélation; toute divergence est refusée;
- les preuves internes ne sont pas montrées au joueur;
- Gemini ne peut pas déclarer lui-même le PASS officiel.

# 10. États et ownership

Deux axes distincts empêchent un module d’écrire l’état d’un autre.

## 10.1 État de création — propriétaire Phase1

```text
EMPTY
CREATED
CREATION_FAILED
```

## 10.2 État de validation source — propriétaire ValidationPhase1

```text
NOT_VALIDATED
PASS
SUSPICION
```

Phase1 n’écrit jamais `PASS` ou `SUSPICION`.

ValidationPhase1 ne réécrit jamais silencieusement un contenu source.

Les états `READY` et `CONSUMED` n’appartiennent pas à Phase1.

# 11. Atomicité

Les sept conteneurs existent toujours.

L’écriture est atomique par CognitiveSlot :

- un slot valide est écrit entièrement;
- aucune question, réponse, choix ou SV partiel n’est persisté comme `CREATED`;
- un slot invalide reste `EMPTY` ou devient `CREATION_FAILED`;
- les autres slots valides ne sont pas supprimés;
- la Section 1 reste immuable.

# 12. Contrôles techniques locaux

Phase1 vérifie avant écriture :

- JSON décodable et version reconnue;
- identité répétée identique au Blueprint;
- sept types attendus, sans doublon;
- champs obligatoires;
- comptes de choix;
- clés de choix uniques;
- bonne réponse présente;
- polarité Vrai/Faux;
- aucun champ obligatoire vide;
- aucun doublon textuel exact après normalisation;
- temps de lecture estimé de la question et du SV;
- chaque choix respecte le contrat d’unité de réponse courte;
- homogénéité sémantique et grammaticale des choix;
- `trap_basis` conforme;
- aucune mutation de la Section 1.

# 13. Retries techniques

Clé d’idempotence :

```text
blueprint_id + phase1.source.v1 + generation_contract_version
```

Politique :

- maximum trois tentatives techniques au total;
- retry seulement sur timeout, transport, JSON illisible, schéma incomplet ou identité divergente;
- aucun retry automatique destiné à masquer une suspicion intellectuelle;
- aucun nouveau `kernel_code`;
- après épuisement : slots concernés `CREATION_FAILED`, traductions bloquées, incident traçable.

# 14. Frontière ValidationPhase1

La création contrôlée et ses `self_checks` préviennent les erreurs dans le même appel.

La décision officielle demeure indépendante :

```text
Phase1
→ contrôle technique
→ écriture atomique des slots créés
→ ValidationPhase1
→ PASS ou SUSPICION
```

# 15. Quarantine et reprise

Toute source soupçonnée produit une copie complète du Blueprint avec ciblage structuré.

Exemple :

```text
cognitive_slots.QCM_RECOGNITION.source.question
```

Les traductions d’un slot source non validé ne sont pas créées.

Une copie corrigée reprend uniquement les slots et champs ciblés :

```text
Phase1 ciblée
→ ValidationPhase1 ciblée
→ Phase2 ciblée
→ ValidationPhase2
→ ReadyBank
→ réconciliation
```

# 16. Invariants

- même `blueprint_id`;
- même `kernel_code`;
- sept conteneurs permanents;
- sept mécanismes cognitifs autonomes;
- aucun master;
- aucune traduction;
- aucun contenu joueur;
- aucun `question_code`, `COG` ou `VAR`;
- question seule ≤ 8 secondes;
- réponses et distracteurs = unités courtes représentant chacune une seule idée;
- QCM : `choices.a` est la bonne réponse et `correct_answer_key = a`;
- SV ≤ 30 secondes;
- difficulté jamais créée par la longueur;
- cohérence jusqu’au sous-domaine;
- écriture atomique par slot;
- aucune auto-certification Gemini;
- reprise ciblée;
- Section 1 immuable.

# 17. Tests contractuels de Build

1. exactement sept conteneurs;
2. exactement sept types uniques;
3. chaque règle cognitive accepte son mécanisme et refuse les mécanismes voisins;
4. aucune dérivation master;
5. aucun QCM converti mécaniquement en Vrai/Faux;
6. aucun faux obtenu par négation mécanique;
7. QCM : quatre choix courts, une bonne réponse;
8. chaque réponse QCM = un mot, une valeur courte ou une expression représentant une seule idée;
9. phrase, justification, énumération ou plusieurs idées dans un choix → refus;
10. Vrai/Faux : deux choix et polarité exacte;
11. QCM_TRAP exige `trap_basis`;
12. question au-dessus de 8 secondes → refus technique ou suspicion;
13. SV au-dessus de 30 secondes → refus technique ou suspicion;
14. aucune limite minimale de caractères;
15. Depth élevé avec question courte → accepté;
16. incohérence sous-domaine → suspicion;
17. identité divergente → aucun write;
18. un slot invalide n’efface pas les autres;
19. aucun slot partiel déclaré `CREATED`;
20. retries plafonnés à trois;
21. replay idempotent;
22. QCM : bonne réponse canonique en `a`, distracteurs en `b`, `c`, `d`;
23. aucune mutation Section 1.

# 18. Statut

```text
Architecture :          VERROUILLÉE
Contrat :               VERROUILLÉ v1.0
Spécification :         BUILD-READY
Implémentation :        À AUDITER/RÉALIGNER
Validation terminale :  NON
```

Prochaine opération :

```text
ALIGN-AUDIT-06-v1.0
→ comparer le code Phase1 réel à ce contrat
→ KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
→ proposer le patch minimal de Build
```
