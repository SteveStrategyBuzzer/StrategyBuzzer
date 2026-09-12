# StrategyBuzzer — 01_KernelBlueprint

**Version :** 3.1
**Date :** 2026-09-08
**Statut documentaire :** **VERROUILLÉ**
**Architecture :** **100 %**
**Contrat :** **100 %**
**Décisions directrices :** **DEC-123 v1.0 + DEC-124 v1.0 — OFFICIAL**
**Remplace :** v3.0 sur la transmission inter-phase seulement

> Cette version conserve intégralement les responsabilités intellectuelles de
> v3.0. DEC-123 demeure historique et **OFFICIAL**, sauf ses seules clauses sur
> l’autorisation distincte et `destinataire_initial`, marquées
> **SUPERSEDED BY DEC-124** dans le registre. DEC-124 établit `blueprint_id`
> comme unique valeur transmise entre toutes les phases.

# 1. Mission

`KernelBlueprint` est l'unique agrégat canonique, persistant et extérieur aux
phases qui représente un noyau durant son parcours StrategyBuzzer. Créé une
seule fois, conservé sous le même `blueprint_id`, il est enrichi exclusivement
par les propriétaires de ses zones d'écriture. Il ne produit aucune décision
métier.

# 2. Position dans le pipeline

```text
KBP → Rotation → Taxonomy → QuestionIntent → Phase1
    → ValidationPhase1 → Phase2 → ValidationPhase2 → ReadyBank
```

Après la réception terminale :

```text
ReadyBank → CURRENT_KERNEL_RECEIVED → KBP → nouveau blueprint_id → Rotation
```

L'ancien Blueprint demeure l'enveloppe de son propre noyau; il n'est jamais
recyclé, renvoyé à Rotation ou réécrit pour le noyau suivant.

# 3. Autorité architecturale

Cette spécification, DEC-123 dans sa portée non remplacée et DEC-124 sont
l'autorité. Le code existant ne l'est pas. Le contrat d'accès et d'ownership
n'implique ni table ou champ de clé de circulation, ni propriétaire courant,
ni porte distincte par phase.

# 4. Un seul Blueprint canonique

Il existe un seul Blueprint canonique. `blueprint_id` est l'unique valeur
transmise entre les phases; chaque phase retrouve par cet identifiant le même
persistant. Aucun objet `KernelBlueprint` hydraté, payload de slot ou copie
autoritaire n'est transmis entre phases. Une copie Quarantine est l'exception
explicitement non canonique définie par DEC-122.

# 5. Responsabilités exclusives de KBP

`KernelBlueprintFactory` (KBP) crée une seule fois le Blueprint canonique
complet vide, lui attribue son `blueprint_id`, crée ses structures permanentes
et persiste atomiquement l'ensemble. Après une création réussie, KBP remet
uniquement `blueprint_id`.

# 6. Ownership officiel des écritures

| Propriétaire | Zone d'écriture exclusive |
|---|---|
| KBP | `blueprint_id`, enveloppe et sept slots vides |
| Rotation | `depth` + `domain_code` + `kernel_code_dd` + `kernel_code_do` |
| Taxonomy | `subdomain_active` + `subject_active` + `dominant_idea_active` + `kernel_code_sub` + `kernel_code_suj` + `kernel_code_ide` |
| QuestionIntent | `kernel_code_vvvv` uniquement |
| PostgreSQL | `kernel_code` généré, en lecture seule |
| Phase1 | sources des sept CognitiveSlots |
| ValidationPhase1 | états et findings intellectuels |
| Phase2 | traductions dans les mêmes slots |
| ValidationPhase2 | états et findings linguistiques |
| ReadyBank | admissibilité terminale et fusion DEC-122 |

`kernel_code` est une colonne PostgreSQL générée et en lecture seule. Elle reste
`NULL` tant que les six segments `kernel_code_dd`, `kernel_code_do`,
`kernel_code_sub`, `kernel_code_suj`, `kernel_code_ide` et
`kernel_code_vvvv` ne sont pas tous présents. Rotation possède les deux
premiers segments, Taxonomy les trois suivants et QuestionIntent possède
uniquement `kernel_code_vvvv`; aucun module n'assemble ni n'écrit le code
complet. `KernelCodeEngine`, s'il existe techniquement, n'est donc ni un
propriétaire ni une autorité d'écriture.

# 7. Interdictions de KBP

KBP ne choisit ni Depth, Domain, Subdomain, Subject ou Dominant Idea; n'écrit
pas `kernel_code`; ne produit ni question, réponse, SV, traduction,
validation, contenu intellectuel ou résultat attendu. Il ne reçoit ni
précondition intellectuelle, ni scénario de contenu, ni dépendance simulée.
Il ne possède ni Banks, curseurs, occurrence, compteur, cycle, état KRP,
état joueur, mode, cible de phase ou contexte de test.

# 8. Entrée normale de production

L'entrée normale unique est :

```text
ReadyBank → CURRENT_KERNEL_RECEIVED → KBP
```

`CURRENT_KERNEL_RECEIVED` signifie que ReadyBank a reçu terminalement le noyau
courant. Il déclenche exclusivement la création du Blueprint suivant, jamais la
modification ou la retransmission de l'ancien Blueprint.

# 9. Référence d'idempotence d'une demande

La demande issue de `CURRENT_KERNEL_RECEIVED` porte une `request_reference`
stable. L'association `request_reference → blueprint_id` existe uniquement
pour empêcher une création en double et retrouver le même Blueprint après
rejeu. Elle n'est ni une clé inter-phase, ni un registre de destination, ni un
coordinateur de phase, ni un scénario de test, ni une partie du Blueprint.
Si la création est validée mais que la transmission de `blueprint_id` échoue
ou est interrompue, le Blueprint reste `CREATED_UNENGAGED`; le rejeu retrouve
ce Blueprint. Un rollback ne laisse ni Blueprint ni association technique.

# 10. Entrée de test

Un test d'un autre module demande, depuis le point d'entrée de test de la phase
concernée, la mise à disposition d'un Blueprint canonique complet vide dans
son contexte isolé. Cette entrée ne crée pas une seconde architecture, un
Blueprint spécial, une copie en mémoire ou une voie de production parallèle.
Le Blueprint de test emploie les mêmes structures, contraintes et identité
canonique que la production.

# 11. Paramètres propres au test d'une phase

Les paramètres et dépendances simulées nécessaires au test d'une phase
appartiennent au contrat de cette phase et à son infrastructure de test. Ils ne
sont pas reçus, choisis, persistés ou transformés en préconditions Blueprint
par KBP. Ils ne donnent aucune responsabilité intellectuelle à KBP et ne
permettent pas de modifier une zone amont.

# 12. Construction canonique complète vide

KBP construit :

```text
kernel_blueprint_runs
+ kernel_blueprint_cognitive_slots × 7
```

Cette construction est structurellement complète et intellectuellement vide.
KBP ne prépare pas artificiellement le Blueprint pour une phase aval et ne
simule ni Rotation, ni Taxonomy, ni QuestionIntent.

`kernel_blueprint_runs` persiste `depth`, `domain_code`, les valeurs complètes
`subdomain_active`, `subject_active`, `dominant_idea_active` et les six
segments `kernel_code_dd`, `kernel_code_do`, `kernel_code_sub`,
`kernel_code_suj`, `kernel_code_ide`, `kernel_code_vvvv`. La colonne
PostgreSQL générée `kernel_code` est en lecture seule et reste `NULL` avant la
présence de ces six segments.

# 13. Factory interne

KBP est la Factory interne de 01. La création normale passe par sa frontière.
Un accès direct à la Factory est admis exclusivement dans les tests unitaires
internes KBP, pour contrôler sa création atomique, sa structure vide et ses
gardes. Cette exception n'est ni une API des autres modules, ni une deuxième
porte, ni le droit de remplir l'identité intellectuelle.

# 14. Persistance atomique

La persistance garantit atomiquement l'unicité de création active dans le
régime séquentiel, l'immuabilité de `blueprint_id`, les sept slots et leurs
contraintes, ainsi que les gardes write-once. Elle ne permet pas de contourner
le contrat par écriture directe.

`fillRotation` persiste atomiquement `depth + domain_code + kernel_code_dd +
kernel_code_do`, ensemble ou aucune modification. `fillTaxonomy` persiste
atomiquement son triplet complet et `kernel_code_sub + kernel_code_suj +
kernel_code_ide`, ensemble ou aucune modification. QuestionIntent persiste
uniquement `kernel_code_vvvv`. Une opération groupée ne laisse aucun état
partiel canonique; PostgreSQL ne rend `kernel_code` non-NULL qu'après la
présence des six segments.

# 15. Sept CognitiveSlots

Les sept slots permanents sont :

```text
QCM_RECOGNITION
QCM_REASONING
QCM_TRAP
TRUE_FALSE_RECOGNITION_TRUE
TRUE_FALSE_RECOGNITION_FALSE
TRUE_FALSE_REASONING_TRUE
TRUE_FALSE_REASONING_FALSE
```

Ils sont identifiés de façon unique par `(blueprint_id, cognitive_type)`.
Chaque slot est initialisé avec `question`, `sv`, `creation_evidence` et
`creation_failure` à `NULL`, `creation_status = EMPTY`,
`validation_status = NOT_VALIDATED`, `validation_findings = []` et
`translations = {}`. Les QCM ont les choix vides `a/b/c/d` et la clé `a`; les
vrai/faux ont les choix vides `a/b`, la clé `a` pour `*_TRUE` et `b` pour
`*_FALSE`. Phase1 définit le contenu source; le détail de son payload relève
de `06_Phase1`, celui des langues de `08_Phase2`.

# 16. Sortie de KBP

Après succès, KBP ne sort jamais un agrégat Blueprint. Sa seule sortie est
`blueprint_id`. Toutes les phases transmettent ce même identifiant et
rechargent le persistant avant de lire ou d'écrire.

# 17. blueprint_id

`blueprint_id` est l'identité canonique immuable de l'enveloppe, créée par KBP
une seule fois. Il n'est ni `kernel_code`, ni `rotation_identifier`, ni une
clé de droit durable. Toute seconde initialisation ou écriture directe est
refusée.

# 18. Transmission initiale

La valeur produite après, et seulement après, la transaction réussie est :

```text
blueprint_id
```

KBP ne produit aucune donnée intellectuelle, phase suivante, destination,
scénario ou mode. Après rollback, aucun Blueprint ni binding
`request_reference → blueprint_id` ne subsiste.

# 19. Entrée normale Rotation

En production, KBP transmet uniquement `blueprint_id` à Rotation. Rotation
retrouve le Blueprint, écrit atomiquement `depth + domain_code + DD + DO`,
persiste, puis transmet le même `blueprint_id` à Taxonomy. Rotation ne
construit pas le code complet et ne touche à aucun segment Taxonomy.

# 20. Entrée de test de la phase demandeuse

En test isolé, KBP retourne uniquement `blueprint_id` à la phase demandeuse.
Cette phase recharge le même Blueprint, applique son contrat et écrit
seulement sa zone. Une phase ne reçoit jamais le pouvoir de créer les
préconditions qui relèvent de phases précédentes; son test ne redéfinit pas le
contrat du Blueprint.

# 21. Segments et kernel_code généré

Après lookup persistant par `blueprint_id`, Rotation possède atomiquement
`depth`, `domain_code`, `kernel_code_dd` et `kernel_code_do`. Taxonomy possède
atomiquement les trois valeurs complètes et `kernel_code_sub`,
`kernel_code_suj` et `kernel_code_ide`. QuestionIntent alloue et persiste
uniquement `kernel_code_vvvv`.

PostgreSQL génère `kernel_code` en lecture seule à partir des six segments. Il
reste `NULL` à la création, après Rotation et tant que les six segments ne sont
pas présents. Aucun module ne construit, ne persiste ou ne verrouille
directement le code complet; Phase1 et les phases suivantes ne le modifient
jamais.

# 22. Invariants VVVV

Le format final conservé de DEC-121 contient les composantes Depth, Domaine,
Sous-domaine, Sujet, Idée dominante et le suffixe `VVVV`, en un unique code
final généré par PostgreSQL. `VVVV` est un compteur base36 persistant,
transactionnel, unique, jamais recyclé et indépendant pour chaque bassin
`Depth + Domain`. Son allocation et son écriture sont atomiques pour
QuestionIntent; un échec ne persiste aucun suffixe partiel. Le code généré
reste `NULL` jusqu'à la présence des six segments et toute réécriture d'un
segment write-once est refusée.

# 23. Droits de lecture

Les lecteurs autorisés consultent les seuls slots nécessaires à leur contrat,
après lookup persistant par `blueprint_id`. Lire ne donne aucun ownership
d'écriture. Les phases aval respectent les résultats déjà inscrits et ne
transportent pas le contenu du Blueprint entre elles.

# 24. Droits d'écriture

Chaque propriétaire écrit uniquement la zone de la section 6. Les groupes
structurels de Section 1 sont write-once dans le chemin normal : identité,
rotation, Taxonomy et les segments du code. `kernel_code` est généré et en
lecture seule. Une seconde écriture, même identique, est refusée. Aucun
propriétaire aval ne compense une précondition amont absente.

DEC-106 demeure applicable : l'IdeaSlot sélectionné est exactement la
`dominant_idea_active` écrite et devient `CONSUMED` seulement après réussite
de `fillTaxonomy`; aucun `CONSUMED` ne résulte d'un échec.

# 25. Cycle de vie normal

Après la transmission à Rotation, chaque phase reçoit seulement :

```text
blueprint_id
```

Elle charge le persistant, y vérifie le statut compatible, lit ses préconditions,
écrit son ownership, persiste puis émet son propre signal terminal. Un statut
absent, non terminal ou incompatible interdit le démarrage et toute écriture
de la phase suivante.

# 26. Cycle de vie de test

Un test isolé démarre à la phase demandeuse et se limite à ce que son propre
contrat autorise. La phase testée travaille sur le vrai Blueprint persistant
isolé et son résultat peut être observé avant terminaison. Aucune cascade de
production, aucun worker, outbox, queue, compteur, Bank ou ReadyBank de
production ne doit récupérer ce contexte; les relais éventuels relèvent des
contrats des modules testés et transmettent uniquement `blueprint_id`.

# 27. Absence de Harness

Aucun Harness n'appartient au contrat architectural actif de
`01_KernelBlueprint`. L'infrastructure de test peut techniquement déclarer ses
assertions et simuler des dépendances, mais elle ne possède pas le Blueprint,
ne reçoit ni ne transmet de droit d'exécution distinct, ne construit pas de
contenu et n'est jamais un orchestrateur métier. L'infrastructure de test ne
fait que transmettre `blueprint_id` selon le scénario externe.

# 28. Absence de préconditions intellectuelles KBP

KBP ne prépare ni `depth`, `domain_code`, triplet Taxonomy, segments ou
`kernel_code`, source
cognitive, traduction ou état intellectuel pour une phase ciblée. Il n'existe
pas de Fixture ni de ManualPreconditions dans le contrat actif. Les tests ne
font pas écrire directement les tables métier pour contourner les propriétaires
de ces données.

# 29. Arrêt et observation d'un test

Le test observe le résultat persistant et les signaux de sa phase selon le
contrat de cette phase. L'arrêt après le travail autorisé est extérieur au
Blueprint et ne crée aucun état de mode, scénario, cible ou clé persistante
dans celui-ci. Les phases ne deviennent pas responsables de l'observation.

# 30. Demande de terminaison

L'appelant technique qui a demandé la création du contexte de test peut
demander sa terminaison après ses observations et assertions. Il présente à
KBP uniquement `blueprint_id` dans le contexte PHPUnit PostgreSQL isolé. Cela
ne donne ni ownership Blueprint, ni droit d'écriture métier, ni droit de
suppression directe. L'association d'idempotence de création n'est pas une
preuve de terminaison et n'acquiert aucune seconde fonction.

# 31. Suppression par KBP

KBP vérifie le contexte PHPUnit PostgreSQL isolé et l'identité du Blueprint,
puis est le seul à exécuter la suppression. Les phases ne connaissent pas ce
mécanisme et n'exécutent aucun SQL de nettoyage. Une demande hors de ce
contexte est refusée. Un Blueprint déjà absent constitue un succès idempotent
et aucune autre donnée n'est touchée.

# 32. Nettoyage et interruption

KBP refuse une suppression pendant une écriture active : aucune suppression
partielle n'est possible. Après suppression réussie, le Blueprint et ses sept
slots sont supprimés du contexte isolé. En cas d'interruption, le mécanisme de
fermeture du contexte demande le même nettoyage à KBP; aucun worker de
production ne reprend ce contexte.

# 33. États

Le Blueprint possède exactement :

```text
CREATED_UNENGAGED
ENGAGED_IN_PIPELINE
```

`PRODUCTION_ON_HOLD`, `VISIBLE`, `ESTOMPÉ`, `BLOCKED`,
`DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` sont externes. Les états détaillés des
slots appartiennent à leurs modules de création et validation; le Blueprint les
persiste sans devenir leur autorité métier.

# 34. Transitions KBP

```text
ABSENT --KBP.create--> CREATED_UNENGAGED
CREATED_UNENGAGED --fillRotation--> CREATED_UNENGAGED
CREATED_UNENGAGED --fillTaxonomy réussi--> ENGAGED_IN_PIPELINE
```

La première écriture Taxonomy réussie engage le Blueprint. La génération de
`kernel_code`, les phases aval, Quarantine et ReadyBank ne créent jamais une
nouvelle identité Blueprint.

# 35. Erreurs techniques et cas limites

| Cas | Résultat contractuel |
|---|---|
| créations concurrentes | une seule création active autorisée |
| écriture directe externe | refus |
| double écriture normale | refus sans écrasement silencieux |
| groupe incomplet | échec atomique, aucun état partiel |
| QuestionIntent sans triplet Taxonomy | refus avant allocation de `VVVV` |
| un module écrit directement `kernel_code` | refus; colonne générée en lecture seule |
| rejeu `CURRENT_KERNEL_RECEIVED` | même création, aucun double effet |
| remise initiale interrompue | `CREATED_UNENGAGED`, rejeu du même identifiant |
| référence technique ne correspondant pas au Blueprint | refus |
| suppression pendant écriture | refus, Blueprint intact |
| suppression répétée après absence du Blueprint | succès idempotent |

`question_intents.frame_en` reste legacy et non autoritaire; il n'est ni
source de vérité Blueprint ni persistance Phase1. Les données joueur, le
mélange des choix et leur affichage sont externes.

# 36. Contrats

DEC-034 (write-once), DEC-035 (création atomique), DEC-058 (Factory avant
KRP), DEC-059 (`blueprint_id` canonique), DEC-068 (KernelCodeEngine hors KRP)
et DEC-106 (consommation exacte) sont compatibles et conservées. Le
`kernel_code` est généré par PostgreSQL à partir des six segments possédés par
Rotation, Taxonomy et QuestionIntent.

DEC-122 demeure **OFFICIAL** et inchangée. Le canonique contient identité,
sources et traductions, et poursuit le pipeline jusqu'à ReadyBank. Après
`SUSPICION`, Quarantine réalise une copie complète explicitement non canonique,
avec références de réconciliation et chemins suspects structurés. La copie
reprend seulement le travail ciblé; les slots valides ne sont pas recréés et
une source non validée n'est pas traduite.

ReadyBank seul retrouve le canonique et fusionne atomiquement les corrections
ciblées, les valeurs ciblées ou les slots vides, conserve les slots valides et
trace avant/après. Une copie d'un autre `blueprint_id` est refusée; elle ne
devient jamais canonique et ne reçoit jamais un nouveau `kernel_code`.

La clé minimale de ciblage DEC-122 est :

```text
blueprint_id + kernel_code + cognitive_slot + couche source/traduction
+ langue si traduction + chemin(s) de champ suspect(s)
```

Aucun slot suspect, vide ou non validé n'est exposable au gameplay.

# 37. Validation et tests contractuels

**Tests unitaires internes Factory/KBP seulement :** création de
`blueprint_id`; charpente canonique vide; création atomique de l'enveloppe et
des sept slots; unicité active; immutabilité; absence d'écriture
intellectuelle; sortie limitée à `blueprint_id`; idempotence/rejeu de
`CURRENT_KERNEL_RECEIVED`; association
`request_reference → blueprint_id`; refus d'écriture directe; contrôle de la
demande de terminaison; refus pendant écriture active; succès idempotent si le
Blueprint est déjà absent; nettoyage d'un contexte interrompu. L'accès direct
Factory est limité à ces tests internes.

**Tests fonctionnels KBP :** le Blueprint persistant est retrouvé par son
identifiant, ne circule pas comme objet, reste structurellement complet et
intellectuellement vide, et les frontières de création/remise/terminaison ne
créent aucun second Blueprint ou effet lifecycle.

**Tests réservés aux modules propriétaires :** Rotation teste sa rotation et
ses quatre champs (`depth`, `domain_code`, `DD`, `DO`); Taxonomy son triplet,
ses trois segments, ses Banks et DEC-106; QuestionIntent teste l'allocation et
le verrouillage de `VVVV`, ainsi que la génération PostgreSQL de
`kernel_code`; Phase1 et validations testent
contenus, erreurs techniques et suspicions; Phase2 et validation linguistique
testent les traductions; Quarantine et ReadyBank testent la copie et la fusion
DEC-122. Ces tests ne sont ni définis, ni exécutés, ni remplacés par les tests
de KBP.

```text
Architecture : 100 %
Contrat :      100 %
STATUT DOCUMENTAIRE : VERROUILLÉ v3.1
```