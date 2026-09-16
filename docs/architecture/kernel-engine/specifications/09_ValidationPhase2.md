# STRATEGYBUZZER — 09_VALIDATIONPHASE2

**Version :** 1.0
**Date :** 2026-09-16
**Statut :** CONTRAT DOCUMENTAIRE TERMINAL VERROUILLÉ
**Décisions directrices :** DEC-125 + DEC-126 + DEC-127 — OFFICIAL (clauses compatibles de DEC-122)
**Implémentation DEC-127 :** NON — TÂCHE TECHNIQUE DISTINCTE
**Validation documentaire :** PASS

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

**CHECKLIST D’ALIGNEMENT TECHNIQUE — aucune décision fonctionnelle ouverte :**

Les comportements ci-dessous sont verrouillés par DEC-125/127. Seuls leur
modèle physique, leur audit d’implantation et leurs tests restent à traiter :

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

Elle valide uniquement une traduction dont l’identité
`blueprint_id + cognitive_type + language_code + source_revision` correspond à
la `source_revision` anglaise courante. Une traduction rattachée à une ancienne
révision est périmée et ne peut recevoir ni conserver un PASS applicable à la
révision courante. Une modification de la cible ne modifie jamais
`source_revision`.

Tous ses états et résultats s’appliquent également à la
`translation_revision` cible courante. Sa machine est :

```text
NOT_VALIDATED
→ IN_PROGRESS
→ PASS
  ou SUSPICION
  ou RETRYABLE_FAILURE
  ou PERMANENT_FAILURE

RETRYABLE_FAILURE → NOT_VALIDATED
```

ValidationPhase2 est seule propriétaire de ces états. `PASS` et `SUSPICION`
sont des résultats intellectuels; les états d’échec concernent l’exécution
technique du cycle de validation. ValidationPhase2 ne modifie aucun contenu.

Une modification réelle d’une composante cible augmente atomiquement
`translation_revision`, remet le résultat courant à `NOT_VALIDATED` et rend
l’ancien résultat périmé. Un passage de `PASS` ou `SUSPICION` à
`NOT_VALIDATED` est interdit sans changement réel de contenu et nouvelle
`translation_revision`.

Chaque claim et chaque résultat de validation doit correspondre à
`source_revision + translation_revision` et au claim courants. Sinon il est
refusé comme périmé. L’indice jaune d’une correction Quarantaine est conservé
pendant cette validation et jusqu’à la réconciliation ReadyBank.

## 1.1 Findings linguistiques déterministes

Chaque finding est immuable et contient :

```text
finding_id
validation_run_id
blueprint_id
cognitive_type
language_code
source_revision
translation_revision
field_path
rule_code
severity
evidence
created_at
```

Les `field_path` autorisés distinguent la clé de réponse de sa valeur :

```text
question
choices.<key>.text
correct_answer_key
correct_answer_text
sv
translation
```

`correct_answer_key` est immuable pendant la traduction.
`correct_answer_text` doit correspondre au choix situé sous cette même clé.
`translation` désigne un constat portant sur la cible complète.

Tous les codes minimaux suivants ont obligatoirement
`severity = BLOCKING` :

```text
TARGET_LANGUAGE_INCORRECT
UNTRANSLATED_SOURCE_FRAGMENT
REQUIRED_COMPONENT_MISSING
TARGET_FORMAT_INVALID
TARGET_CONCISION_INVALID
TARGET_READING_TIME_INVALID

MEANING_DRIFT
FACTUAL_ACCURACY_DRIFT
DEPTH_LEVEL_DRIFT
SUBJECT_ALIGNMENT_DRIFT
DOMINANT_IDEA_DRIFT
COGNITIVE_FUNCTION_DRIFT
AMBIGUITY_INTRODUCED

QUESTION_ANSWER_MISMATCH
ANSWER_KEY_CHANGED
ANSWER_TEXT_KEY_MISMATCH
CHOICE_COUNT_CHANGED
MULTIPLE_CORRECT_ANSWERS_INTRODUCED
DISTRACTOR_BECAME_TRUE
DISTRACTOR_PLAUSIBILITY_LOST
CHOICE_SEMANTIC_CATEGORY_DRIFT
CHOICE_GRAMMATICAL_COHERENCE_DRIFT

REASONING_RELATION_DRIFT
TRAP_CONFUSION_DRIFT
TRUE_FALSE_POLARITY_CHANGED
FALSE_STATEMENT_ERROR_COUNT_DRIFT

SV_CONTRADICTS_SOURCE
SV_CONTRADICTS_ANSWER
SV_PEDAGOGICAL_VALUE_LOST
SV_MERE_REPETITION
```

`REASONING_RELATION_DRIFT` couvre l’affaiblissement ou la perte d’un mécanisme
causal, comparatif, conséquentiel ou déductif. `TRAP_CONFUSION_DRIFT` couvre la
transformation d’un piège intellectuel en piège grammatical.
`FALSE_STATEMENT_ERROR_COUNT_DRIFT` couvre une erreur unique devenue multiple
ou disparue.

Un même `rule_code` ne change jamais de sévérité selon la langue, le
CognitiveSlot, le fournisseur ou la révision. `INFO` est réservé à des codes
distincts, non contractuels et explicitement inscrits comme informatifs dans le
registre. En l’absence de code `INFO` autorisé, aucun finding informatif n’est
accepté.

Chaque finding `BLOCKING` doit fournir une preuve structurée :

```text
expected_rule
observed_result
source_excerpt
target_excerpt
details
```

`expected_rule` identifie la règle attendue; `observed_result` décrit l’écart;
le `field_path` autoritatif situé au niveau racine du finding identifie le
champ. `evidence` ne stocke aucune seconde valeur `field_path`; elle se rapporte
obligatoirement au chemin racine. Au moins un extrait pertinent ou un détail
vérifiable est obligatoire. Lorsque le code exige une comparaison
source/cible, les deux extraits sont obligatoires. Une preuve vide, générique
ou invérifiable rend le résultat complet de validation invalide :

```text
aucun finding courant persisté
aucun PASS ou SUSPICION appliqué
validation_status = RETRYABLE_FAILURE
```

Les conséquences sont déterministes :

```text
au moins un finding BLOCKING valide → SUSPICION
zéro finding BLOCKING + tous les contrôles exécutés → PASS
finding BLOCKING mal formé → résultat technique invalide
```

Seuls les findings correspondant aux `source_revision` et
`translation_revision` courantes sont opérationnels. Les anciens findings
peuvent être conservés pour audit technique et idempotence, mais ne sont jamais
affichés comme courants, recopiés dans une nouvelle copie Quarantaine, reportés
sur une nouvelle révision, utilisés pour colorer un champ ou consultés pour une
décision ValidationPhase2 ou ReadyBank.

## 1.2 Prédicats d’admissibilité

Une traduction cible est admissible si et seulement si :

```text
blueprint_id courant
AND cognitive_type courant
AND language_code attendu
AND source_revision courante
AND translation_revision courante
AND creation_status = CREATED
AND validation_status = PASS
AND question traduite présente
AND tous les choix traduits présents
AND correct_answer_key identique à la source anglaise
AND correct_answer_text = choix situé sous cette clé
AND SV traduit présent
AND aucun finding BLOCKING courant
AND aucun claim courant non terminé
AND aucun retry courant en attente
AND aucun résultat technique invalide non résolu
```

Le `PASS` certifie également que la cible a été traduite directement depuis la
source anglaise courante, que tous les contrôles contractuels ont été exécutés
et qu’aucun invariant intellectuel, cognitif, factuel, structurel ou temporel
n’est violé.

Une cible `SUSPICION`, `RETRYABLE_FAILURE`, `PERMANENT_FAILURE`, périmée,
incomplète ou non validée est inadmissible.

Un CognitiveSlot est admissible si et seulement si :

```text
source anglaise complète
AND source_revision courante
AND validation source Phase1 = PASS
AND aucune reprise source requise
AND exactement neuf language_code distincts attendus
AND chacune des neuf traductions est admissible
```

Les neuf langues sont `fr, es, de, it, pt, ru, zh, ar, el`. Aucune absence,
substitution, duplication, traduction de fallback ou langue facultative n’est
admise. Aucune langue n’emprunte le PASS d’une autre.

L’indice jaune ne rend pas une cible inadmissible par lui-même. Une révision
jaune courante peut devenir admissible après son propre `PASS`; elle reste jaune
jusqu’à la décision ReadyBank portant sur cette révision exacte.

L’admissibilité est calculée dans cet ordre :

```text
traduction par langue
→ CognitiveSlot complet
→ position ReadyBank du CognitiveSlot
```

L’inadmissibilité d’un slot ne contamine pas les autres slots. Aucun finding
historique ni aucune décision fournisseur non persistée ne participe au calcul.
La progression terminale d’un Blueprint partiel reste réservée au contrat
ReadyBank.

## 1.3 Frontière technique et contenu intraduisible

ValidationPhase2, indépendante du fournisseur Phase2, est seule autorisée à
conclure `CONTENT_UNTRANSLATABLE`. Ce code rejoint le registre des codes
`BLOCKING` et produit :

```text
validation_status = SUSPICION
rule_code = CONTENT_UNTRANSLATABLE
severity = BLOCKING
```

Sa preuve contient obligatoirement :

```text
language_code
source_revision
translation_revision si elle existe
components_concerned
contract_rules_in_conflict
source_excerpts
target_excerpts si une cible existe
expected_rule
observed_result
explanation
```

Une preuve incomplète rend le résultat techniquement invalide. Phase2 et son
fournisseur ne peuvent proposer ni persister cette conclusion.

Un échec technique, y compris l’épuisement d’un cycle, ne prouve aucun défaut
du contenu. Il ne produit ni rouge, ni finding intellectuel, ni copie
Quarantaine automatique. Il bloque seulement la langue et la phase concernées,
laisse les autres unités continuer et attend un événement de résolution
autorisé.

La copie Quarantaine complète est créée seulement pour :

```text
SUSPICION
CONTENT_UNTRANSLATABLE
correction manuelle explicitement demandée
```

Une cible `SUSPICION` ne peut jamais être rouverte par une autorisation
technique. Elle exige une modification réelle en Quarantaine, une nouvelle
`translation_revision` jaune et une nouvelle validation.

Après `CONTENT_UNTRANSLATABLE`, deux réparations sont autorisées :

1. créer manuellement une cible conforme sous la même `source_revision`, avec
   une nouvelle `translation_revision` jaune;
2. modifier réellement la source anglaise, augmenter `source_revision` et
   rendre périmées les neuf traductions du CognitiveSlot.

Aucune traduction en chaîne, omission de langue, modification silencieuse de
clé, perte cognitive ou langue de fallback n’est autorisée.

## 1.4 Interface indépendante du validateur

L’adaptateur ValidationPhase2 conserve sa propre enveloppe interne : claim,
révisions, cycle, tentative, état jaune, idempotence et références de stockage.
Cette enveloppe n’est jamais transmise au validateur.

Le validateur reçoit uniquement :

```text
validation_request_reference opaque
external_validation_idempotency_key opaque
source anglaise conforme au cognitive_type
cible traduite conforme au même cognitive_type
contexte cognitif strictement nécessaire
référents intellectuels anglais nécessaires
registre des rule_code
schéma des findings
```

Il ne reçoit jamais :

```text
claim_token
creation_evidence
self_checks
conclusions du fournisseur de traduction
références de stockage
état jaune
```

La réponse externe contient la référence opaque, l’identifiant du validateur,
`PASS` ou `SUSPICION` et les findings structurés, ou une erreur technique
typée. Le validateur ne crée aucune `translation_revision` et ne modifie aucun
contenu.

Après réception, l’adaptateur recharge l’enveloppe interne et vérifie
atomiquement le claim, les révisions, le cycle, la tentative, l’idempotence,
l’absence d’une correction plus récente et le schéma propre au
`cognitive_type`. Un retour périmé ou contradictoire ne produit aucune écriture.

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

# 6. Statut terminal documentaire

Le contrat fonctionnel ValidationPhase2 est complet et verrouillé par DEC-127.
La progression partielle et la frontière terminale ReadyBank sont fixées par le
complément DEC-127 de `11_ReadyBank.md`.

Le modèle physique, l’implantation et les tests appartiennent à un travail
technique ultérieur distinct.

# 7. Annexe officielle de conformité

La projection vérifiable de ce contrat dans la matrice consolidée DEC-127 est :

[`DEC-127_PHASE2_VALIDATION_COMPLIANCE_ANNEX.md`](../DEC-127_PHASE2_VALIDATION_COMPLIANCE_ANNEX.md)

La présente spécification reste propriétaire et autoritative. En cas d’écart,
l’annexe doit être corrigée.
