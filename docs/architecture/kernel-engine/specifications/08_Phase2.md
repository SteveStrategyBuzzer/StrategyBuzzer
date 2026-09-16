# STRATEGYBUZZER — 08_PHASE2 / TRADUCTIONS

**Version :** 1.0
**Date :** 2026-09-16
**Statut :** CONTRAT DOCUMENTAIRE TERMINAL VERROUILLÉ
**Décisions directrices :** DEC-125 + DEC-126 + DEC-127 — OFFICIAL (clauses compatibles de DEC-122)
**Implémentation DEC-127 :** NON — TÂCHE TECHNIQUE DISTINCTE
**Validation documentaire :** PASS

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

Chaque langue cible est traduite indépendamment et directement depuis la même
source anglaise du CognitiveSlot. Une traduction cible ne peut jamais servir de
source à une autre langue.

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

## 2.1 Identité persistante d’une traduction

L’identité persistante logique de chaque traduction est le quadruplet :

```text
blueprint_id
+ cognitive_type
+ language_code
+ source_revision
```

`source_revision` désigne uniquement la version du contenu source anglais du
CognitiveSlot, composé de la question, des choix, de la bonne réponse et du SV.
Elle augmente dès qu’au moins une de ces composantes anglaises est modifiée.

Toutes les traductions rattachées à l’ancienne `source_revision` deviennent
alors périmées et ne peuvent ni valider, ni remplacer, ni rendre admissible la
révision courante. Chaque langue cible repart directement de la nouvelle source
anglaise.

La modification d’une traduction cible ne change jamais `source_revision`.
Toute révision propre au contenu cible demeure distincte et ne change pas
l’identité anglaise du CognitiveSlot.

## 2.2 Révision cible et états Phase2

Chaque traduction possède une `translation_revision` distincte de
`source_revision`. Tous les états, claims et résultats s’appliquent au couple
exact :

```text
source_revision + translation_revision
```

La machine de création automatisée Phase2 est :

```text
PENDING
→ IN_PROGRESS
→ CREATED
  ou RETRYABLE_FAILURE
  ou PERMANENT_FAILURE

RETRYABLE_FAILURE → PENDING
```

Phase2 est seule propriétaire de ces états. `CREATED` signifie uniquement que
les quatre composantes cibles sont complètes; il ne signifie jamais `PASS`.
`PERMANENT_FAILURE` est terminal pour le cycle automatisé et la
`translation_revision` concernés.

Une création ou correction manuelle complète en Quarantaine peut réparer cet
échec sous la même `source_revision` :

```text
PERMANENT_FAILURE
→ modification manuelle réelle
→ translation_revision + 1
→ CREATED + NOT_VALIDATED + indice JAUNE
```

Toute modification réelle de la question traduite, des choix traduits, de la
bonne réponse traduite ou du SV traduit augmente atomiquement
`translation_revision` et invalide l’ancien résultat de validation. La
traduction corrigée conserve son indice jaune jusqu’à la réconciliation
ReadyBank.

Chaque claim et chaque résultat fournisseur porte au minimum l’identité
complète, `source_revision`, `translation_revision` et son jeton de claim. Tout
retour ne correspondant plus aux révisions et au claim courants est périmé et
refusé. Phase2 ne peut notamment jamais écraser une correction manuelle jaune
avec un résultat fournisseur antérieur.

L’autorisation d’un nouveau cycle technique après `PERMANENT_FAILURE`, sans
modification artificielle d’une cible correcte, reste réservée au contrat des
retries. L’attribution de la première `translation_revision` et la création
atomique d’une nouvelle révision par un résultat fournisseur restent réservées
au contrat d’interface fournisseur.

## 2.3 Cycles techniques et retries

Les cycles techniques sont indépendants par identité, langue, phase propriétaire
et `retry_cycle`. L’échec d’une langue ne relance jamais les huit autres;
l’échec de ValidationPhase2 ne relance jamais automatiquement Phase2.

Les issues techniques sont :

```text
PRECONDITION_BLOCKED
STALE_RESULT
RETRYABLE_TECHNICAL_FAILURE
NON_RETRYABLE_TECHNICAL_FAILURE
```

`PRECONDITION_BLOCKED` ne démarre aucun cycle et ne consomme aucune tentative.
`STALE_RESULT` refuse un retour périmé sans modifier l’état ni consommer une
tentative du cycle courant. Un timeout, une indisponibilité temporaire, une
limite fournisseur, une erreur réseau ou une réponse structurée invalide sont
retryables. Une requête contractuellement invalide, une configuration ou une
autorisation fournisseur invalide sont non retryables.

Chaque cycle autorise :

```text
tentative initiale
retry 1 après au moins 1 minute
retry 2 après au moins 5 minutes
retry 3 après au moins 15 minutes
```

Si un `Retry-After` fiable du fournisseur est supérieur au plancher applicable,
il est prioritaire :

```text
prochain délai = max(plancher contractuel, Retry-After fiable)
```

Après quatre échecs techniques, ou après un échec non retryable, le cycle
courant atteint `PERMANENT_FAILURE`. Cet état ne signifie jamais que le contenu
est intellectuellement défectueux, ne crée aucun finding rouge et n’envoie pas
seul le Blueprint en Quarantaine. La langue concernée demeure techniquement
bloquée; les autres langues et slots continuent.

Un signal opérationnel persistant contient au minimum :

```text
blueprint_id
cognitive_type
language_code
source_revision
translation_revision si elle existe
owner_phase
retry_cycle
technical_reason_code
blocked_at
```

Un nouveau cycle peut être autorisé sans modification du contenu par :

```text
retry_cycle + 1
resolution_event_id
authorized_by = ADMIN | SYSTEM_RECOVERY
authorization_reason_code
authorized_at
```

`resolution_event_id` est obligatoire et unique. `SYSTEM_RECOVERY` est
idempotent et ne peut ouvrir qu’un cycle pour cet événement. Un nouveau blocage
exige un nouvel événement réel; aucun événement ne peut créer une boucle de
réouvertures. Les révisions doivent être courantes et tout ancien claim ou
résultat demeure périmé.

Phase2 et son fournisseur ne peuvent jamais conclure
`CONTENT_UNTRANSLATABLE`. Ils produisent une cible structurée ou une erreur
technique. Seule ValidationPhase2 indépendante possède cette décision.

## 2.4 Interface fournisseur et idempotence

L’adaptateur Phase2 conserve dans une enveloppe interne :

```text
operation_id
blueprint_id
source_revision
expected_translation_revision
retry_cycle
attempt_number
internal_idempotency_record
claim_token
claimed_at
claim_expires_at
état jaune
références de stockage
source_payload_hash
```

Cette enveloppe n’est jamais transmise au fournisseur. Elle est l’unique
autorité permettant de décider si un résultat reste applicable.

L’échange externe utilise seulement une `provider_request_reference` et une
`external_idempotency_key` opaques. Elles n’encodent aucune identité interne,
révision, tentative ou claim et ne donnent aucun accès au stockage. La même
tentative réseau conserve la même clé; une tentative contractuelle suivante
reçoit une nouvelle clé.

La requête externe minimale contient :

```text
provider_request_reference
external_idempotency_key
source_language = en
target_language
cognitive_type
depth
référents intellectuels anglais strictement nécessaires
payload source conforme au cognitive_type
schéma de réponse exigé
```

Elle ne contient jamais le claim, `blueprint_id`, les révisions, le cycle,
l’état jaune ou les références de stockage.

Le payload des QCM contient la question, les quatre choix `a..d`,
`correct_answer_key = a` et le SV. Le payload Vrai/Faux contient la question,
les deux choix `a = VRAI`, `b = FAUX`, la clé `a` ou `b` correspondant à la
polarité canonique, et le SV. La réponse conserve le nombre et les clés propres
au `cognitive_type`; aucune structure QCM à quatre choix n’est imposée à un
Vrai/Faux.

La réponse externe contient :

```text
provider_request_reference
provider_request_id
target_language
question traduite
choix traduits sous les mêmes clés
correct_answer_key inchangé
SV traduit
```

ou une erreur technique typée. Le fournisseur ne crée aucune révision, ne
décide jamais l’applicabilité, ne reçoit aucun claim et n’écrit aucun état.

Au retour, l’adaptateur recharge l’enveloppe interne et revérifie atomiquement
le claim, les révisions, le cycle, la tentative, l’idempotence, l’état jaune et
le schéma cognitif. Dans une seule transaction :

```text
premier contenu accepté sous l’identité
→ translation_revision = 1

modification réelle autorisée sous la même source_revision
→ translation_revision courante + 1

résultat identique déjà appliqué
→ no-op, même translation_revision
```

Le contenu, `CREATED + NOT_VALIDATED`, la fin du claim et l’enregistrement
d’idempotence sont persistés ensemble. Si une condition échoue, aucune écriture
partielle n’est autorisée. Tout ancien retour est `STALE_RESULT`.

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

# 7. Statut terminal documentaire

Le contrat fonctionnel Phase2 est complet et verrouillé par DEC-127. La sélection
du fournisseur concret, le modèle physique, l’implantation et les tests
appartiennent à un travail technique ultérieur distinct.

La présente version n’autorise aucune modification de code, migration, schéma,
donnée, fournisseur ou conversion.
