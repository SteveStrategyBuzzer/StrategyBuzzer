# StrategyBuzzer — 01_KernelBlueprint

**Version :** 3.0
**Date :** 2026-09-07
**Statut documentaire :** **VERROUILLÉ**
**Architecture :** **100 %**
**Contrat :** **100 %**
**Décision directrice :** **DEC-123 v1.0 — OFFICIAL**
**Remplace :** v2.1 et la construction progressive de `kernel_code` de DEC-121

> Cette version remplace intégralement v2.1. DEC-121 est conservée comme
> historique `SUPERSEDED`; DEC-123 reprend ses invariants compatibles de
> format, unicité, allocation de `VVVV` et immutabilité, mais abandonne
> définitivement la construction progressive du code.

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

Cette spécification et DEC-123 sont l'autorité. Le code existant ne l'est pas.
La porte logique est le contrat d'accès et d'ownership : elle n'implique ni
table, ni champ `key`, ni token persistant, ni `BlueprintKey`, ni
`current_key_owner`, ni porte distincte par phase.

# 4. Un seul Blueprint canonique

Il existe un seul Blueprint canonique, une seule porte logique et une seule
autorisation d'exécution en circulation. Chaque phase retrouve le même
persistant via `blueprint_id`. Aucun objet `KernelBlueprint` hydraté, payload
de slot ou copie autoritaire n'est transmis entre phases. Une copie Quarantine
est l'exception explicitement non canonique définie par DEC-122.

# 5. Responsabilités exclusives de KBP

`KernelBlueprintFactory` (KBP) crée une seule fois le Blueprint canonique
complet vide, lui attribue son `blueprint_id`, crée ses structures permanentes
et persiste atomiquement l'ensemble. Après une création réussie, KBP remet
l'autorisation initiale au seul destinataire initial.

# 6. Ownership officiel des écritures

| Propriétaire | Zone d'écriture exclusive |
|---|---|
| KBP | `blueprint_id`, enveloppe et sept slots vides |
| Rotation | `depth` + `domain` |
| Taxonomy | `subdomain_active` + `subject_active` + `dominant_idea_active` |
| QuestionIntent | `kernel_code` complet |
| Phase1 | sources des sept CognitiveSlots |
| ValidationPhase1 | états et findings intellectuels |
| Phase2 | traductions dans les mêmes slots |
| ValidationPhase2 | états et findings linguistiques |
| ReadyBank | admissibilité terminale et fusion DEC-122 |

QuestionIntent est l'unique propriétaire du `kernel_code` complet.
`KernelCodeEngine`, s'il existe techniquement, est seulement un mécanisme
interne de QuestionIntent : ni module, ni phase, ni copropriétaire, ni porte,
ni destinataire autonome de l'autorisation, ni autorité d'écriture.

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
courant. Il autorise exclusivement la création du Blueprint suivant, jamais la
modification ou la retransmission de l'ancien Blueprint.

# 9. Référence d'idempotence d'une demande

La demande issue de `CURRENT_KERNEL_RECEIVED` porte une référence de rejeu
stable. Une première réception réussie crée un seul nouveau Blueprint; le
rejeu de la même référence retourne le même `blueprint_id` sans second effet
lifecycle. Si la création est validée mais que la remise de l'autorisation
échoue ou est interrompue, le Blueprint reste `CREATED_UNENGAGED`; le rejeu
retrouve ce Blueprint et remet l'autorisation. Un rollback ne laisse ni
Blueprint ni autorisation.

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
deviennent pas une autorisation de modifier une zone amont.

# 12. Construction canonique complète vide

KBP construit :

```text
kernel_blueprint_runs
+ kernel_blueprint_cognitive_slots × 7
```

Cette construction est structurellement complète et intellectuellement vide.
KBP ne prépare pas artificiellement le Blueprint pour une phase aval et ne
simule ni Rotation, ni Taxonomy, ni QuestionIntent.

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

`fillRotation` persiste `depth + domain` ensemble ou aucune modification.
`fillTaxonomy` persiste son triplet ensemble ou aucune modification. Une
opération groupée ne laisse aucun état partiel canonique.

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

Après succès, KBP ne sort jamais un agrégat Blueprint. Sa sortie est la remise
unique de l'autorisation initiale éphémère au destinataire initial. Après cette
remise, les phases se relaient par références minimales et lookup persistant.

# 17. blueprint_id

`blueprint_id` est l'identité canonique immuable de l'enveloppe, créée par KBP
une seule fois. Il n'est ni `kernel_code`, ni `rotation_identifier`, ni une
clé de droit durable. Toute seconde initialisation ou écriture directe est
refusée.

# 18. Autorisation initiale éphémère

L'autorisation remise après, et seulement après, la transaction réussie est
exactement :

```text
{ blueprint_id, destinataire_initial }
```

Elle n'est jamais persistée, ne fait pas partie du Blueprint et ne contient ni
donnée intellectuelle, ni phase suivante, ni scénario complet, ni mode. Elle
est remise une fois; aucune autorisation ne subsiste après rollback.

# 19. Destination normale Rotation

En production, `destinataire_initial = Rotation`. Rotation retrouve le
Blueprint, écrit seulement `depth + domain`, persiste, puis relaie le résultat
terminal minimal à Taxonomy. Rotation ne construit aucune partie, projection
ou segment du `kernel_code`.

# 20. Destination de test phase demandeuse

En test isolé, `destinataire_initial` est la phase demandeuse. Cette phase
recharge le même Blueprint par `blueprint_id`, applique son contrat et écrit
seulement sa zone. Une phase ne reçoit jamais le pouvoir de créer les
préconditions qui relèvent de phases précédentes; son test ne redéfinit pas le
contrat du Blueprint.

# 21. kernel_code complet

Après lookup persistant et vérification de `depth`, `domain`,
`subdomain_active`, `subject_active` et `dominant_idea_active`, QuestionIntent
seul alloue `VVVV`, construit le `kernel_code` canonique complet, le persiste
et le verrouille. Le code est absent à la création et reste vide après
Rotation et Taxonomy. KRP et Taxonomy ne projettent ni n'assemblent aucune
partie du code; Phase1 et les phases suivantes ne le modifient jamais.

# 22. Invariants VVVV

Le format final conservé de DEC-121 contient les composantes Depth, Domaine,
Sous-domaine, Sujet, Idée dominante et le suffixe `VVVV`, en un unique code
final. `VVVV` est un compteur base36 persistant, transactionnel, unique,
jamais recyclé et indépendant pour chaque bassin `Depth + Domain`. L'allocation
et l'écriture complète sont atomiques pour QuestionIntent : un échec ne
verrouille aucun code partiel. Après verrouillage, toute réécriture est
refusée.

# 23. Droits de lecture

Les lecteurs autorisés consultent les seuls slots nécessaires à leur contrat,
après lookup persistant par `blueprint_id`. Lire ne donne aucun ownership
d'écriture. Les phases aval respectent les résultats déjà inscrits et ne
transportent pas le contenu du Blueprint entre elles.

# 24. Droits d'écriture

Chaque propriétaire écrit uniquement la zone de la section 6. Les groupes
structurels de Section 1 sont write-once dans le chemin normal : identité,
rotation, Taxonomy et `kernel_code`. Une seconde écriture, même identique, est
refusée. Aucun propriétaire aval ne compense une précondition amont absente.

DEC-106 demeure applicable : l'IdeaSlot sélectionné est exactement la
`dominant_idea_active` écrite et devient `CONSUMED` seulement après réussite
de `fillTaxonomy`; aucun `CONSUMED` ne résulte d'un échec.

# 25. Cycle de vie normal

Après la remise à Rotation, chaque phase reçoit seulement :

```text
blueprint_id + phase précédente terminée + statut terminal requis
```

Elle vérifie le statut compatible, charge le persistant, lit ses préconditions,
écrit son ownership, persiste puis émet son propre signal terminal. Un statut
absent, non terminal ou incompatible interdit le démarrage et toute écriture
de la phase suivante.

# 26. Cycle de vie de test

Un test isolé démarre à la phase demandeuse et se limite à ce que son propre
contrat autorise. La phase testée travaille sur le vrai Blueprint persistant
isolé et son résultat peut être observé avant terminaison. Aucune cascade de
production, aucun worker, outbox, queue, compteur, Bank ou ReadyBank de
production ne doit récupérer ce contexte; les relais éventuels relèvent des
contrats des modules testés, pas de 01.

# 27. Absence de Harness

Aucun Harness n'appartient au contrat architectural actif de
`01_KernelBlueprint`. L'infrastructure de test peut techniquement déclarer ses
assertions et simuler des dépendances, mais elle ne possède pas le Blueprint,
ne reçoit ni ne transmet l'autorisation architecturale, ne construit pas de
contenu et n'est jamais un orchestrateur métier.

# 28. Absence de préconditions intellectuelles KBP

KBP ne prépare ni `depth`, `domain`, triplet Taxonomy, `kernel_code`, source
cognitive, traduction ou état intellectuel pour une phase ciblée. Il n'existe
pas de Fixture ni de ManualPreconditions dans le contrat actif. Les tests ne
font pas écrire directement les tables métier pour contourner les propriétaires
de ces données.

# 29. Arrêt et observation d'un test

Le test observe le résultat persistant et les signaux de sa phase selon le
contrat de cette phase. L'arrêt après le travail autorisé est extérieur au
Blueprint et ne crée aucun état de mode, scénario, cible ou clé persistante
dans celui-ci. Les phases ne deviennent pas responsables de l'observation.

# 30. Autorité de terminaison

L'appelant technique qui a demandé la création du contexte de test possède
uniquement l'autorité extérieure de demander la terminaison après ses
observations et assertions. Il présente à KBP `blueprint_id` avec sa référence
externe éphémère. Cette autorité ne donne ni ownership Blueprint, ni droit
d'écriture métier, ni droit de suppression directe.

# 31. Autorité de suppression

KBP vérifie la correspondance entre `blueprint_id` et la référence externe du
contexte isolé et est le seul à exécuter la suppression. Les phases ne
connaissent pas ce mécanisme et n'exécutent aucun SQL de nettoyage. Une demande
non autorisée est refusée; une seconde demande après suppression retourne
`ALREADY_TERMINATED` et ne touche aucune autre donnée.

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

La première écriture Taxonomy réussie engage le Blueprint. `fillKernelCode`,
les phases aval, Quarantine et ReadyBank ne créent jamais une nouvelle identité
Blueprint.

# 35. Erreurs techniques et cas limites

| Cas | Résultat contractuel |
|---|---|
| créations concurrentes | une seule création active autorisée |
| écriture directe externe | refus |
| double écriture normale | refus sans écrasement silencieux |
| groupe incomplet | échec atomique, aucun état partiel |
| QuestionIntent sans triplet Taxonomy | refus avant écriture |
| KRP, Taxonomy ou Phase1 écrit `kernel_code` | refus |
| rejeu `CURRENT_KERNEL_RECEIVED` | même création, aucun double effet |
| remise initiale interrompue | `CREATED_UNENGAGED`, rejeu du même identifiant |
| terminaison non autorisée | refus |
| suppression pendant écriture | refus, Blueprint intact |
| suppression répétée | `ALREADY_TERMINATED` |

`question_intents.frame_en` reste legacy et non autoritaire; il n'est ni
source de vérité Blueprint ni persistance Phase1. Les données joueur, le
mélange des choix et leur affichage sont externes.

# 36. Contrats

DEC-034 (write-once), DEC-035 (création atomique), DEC-058 (Factory avant
KRP), DEC-059 (`blueprint_id` canonique), DEC-068 (KernelCodeEngine hors KRP)
et DEC-106 (consommation exacte) sont compatibles et conservées.

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
intellectuelle; exactitude de `{ blueprint_id, destinataire_initial }`;
idempotence/rejeu de `CURRENT_KERNEL_RECEIVED`; refus d'écriture directe;
autorité de terminaison; refus pendant écriture active; `ALREADY_TERMINATED`;
nettoyage d'un contexte interrompu. L'accès direct Factory est limité à ces
tests internes.

**Tests fonctionnels KBP :** le Blueprint persistant est retrouvé par son
identifiant, ne circule pas comme objet, reste structurellement complet et
intellectuellement vide, et les frontières de création/remise/terminaison ne
créent aucun second Blueprint ou effet lifecycle.

**Tests réservés aux modules propriétaires :** Rotation teste sa rotation;
Taxonomy son triplet, ses Banks et DEC-106; QuestionIntent teste le format,
l'allocation et le verrouillage du `kernel_code`; Phase1 et validations testent
contenus, erreurs techniques et suspicions; Phase2 et validation linguistique
testent les traductions; Quarantine et ReadyBank testent la copie et la fusion
DEC-122. Ces tests ne sont ni définis, ni exécutés, ni remplacés par les tests
de KBP.

```text
Architecture : 100 %
Contrat :      100 %
STATUT DOCUMENTAIRE : VERROUILLÉ v3.0
```