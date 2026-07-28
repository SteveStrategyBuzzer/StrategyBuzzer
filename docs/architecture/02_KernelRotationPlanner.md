# STRATEGYBUZZER — MÉCANISME EXACT DU KERNELROTATIONPLANNER

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** VERROUILLÉ
**Implantation autorisée :** OUI — contrat exécutoire

Ce document remplace intégralement la version 1.4.

Il constitue la spécification de référence du module KernelRotationPlanner
et de tous les composants de son périmètre direct.

---

# 1. Principe central

Le `KernelRotationPlanner` décide seul du prochain couple :

```text
depth
+
Domaine réel de création
```

Pour prendre cette décision, il utilise deux sources internes :

```text
DepthNeedMatrix
→ indique quel Depth est actif
→ indique les Tours complétés par Depth
→ indique les noyaux déjà reçus par Depth + Domaine
```

```text
Tour de Depth
→ indique quels Domaines participent encore au Tour actif
```

La règle centrale est :

```text
Blueprint reçu par KRP
+
Depth actif (DepthNeedMatrix)
+
prochain Domaine ON (Tour de Depth)
↓
KRP écrit depth + domain dans le Blueprint
```

ReadyBank ne choisit jamais le prochain domaine.

Taxonomy ne choisit jamais le prochain domaine.

DepthNeedMatrix ne choisit jamais le prochain domaine.

Le choix final appartient exclusivement au KernelRotationPlanner.

---

# 2. Mission et responsabilités

## 2.1 KernelBlueprintFactory

Responsabilités :

* créer une nouvelle instance vide de `KernelBlueprint` ;
* générer `blueprint_id` (UUIDv7 — `Str::orderedUuid()`) ;
* créer l'enregistrement d'exécution dans `kernel_blueprint_runs` ;
* vérifier qu'aucun Blueprint actif (`CREATED_UNENGAGED` ou `ENGAGED_IN_PIPELINE`) n'existe déjà ;
* ne remplir aucun slot métier ;
* ne sélectionner aucun Depth ;
* ne sélectionner aucun Domaine.

## 2.2 KernelPipelineOrchestrator

Responsabilités :

* demander la création du Blueprint à `KernelBlueprintFactory` ;
* transmettre le Blueprint à KRP (`planV2`) ;
* transmettre le résultat KRP à Taxonomy (`TaxonomyProgressManager::peekNext`) ;
* gérer la boucle immédiate lorsque Taxonomy retourne `null` (signal EMPTY) ;
* appeler `KRP::applyEmptyTransitionV2` pour chaque EMPTY avant d'appeler `planV2` de nouveau ;
* engager le Blueprint dans le reste du pipeline lorsque Taxonomy fournit son territoire ;
* ne jamais décider lui-même du Depth ou du Domaine.

## 2.3 KernelRotationPlanner

Responsabilités :

* recevoir un Blueprint déjà créé ;
* consulter `DepthNeedMatrix` pour identifier le Depth actif ;
* consulter le `Tour de Depth` (état ON/OFF des Domaines) ;
* sélectionner le prochain Domaine ON ;
* écrire `depth + domain` dans le Blueprint via `fillRotation()` ;
* gérer les transitions EMPTY (`applyEmptyTransitionV2`) ;
* comptabiliser les réceptions ReadyBank (`receiveKernelReceivedV2`) ;
* persister l'état dans `kernel_rotation_state_v2`.

Interdictions absolues :

* ne jamais créer le Blueprint ;
* ne jamais écrire `blueprint_id` ;
* ne jamais écrire `kernel_code` ;
* ne jamais écrire `subdomain_active`, `subject_active`, `dominant_idea_active` ;
* ne jamais scanner les 8 Domaines en avance (réponse à un signal EMPTY, pas anticipation).

---

# 3. Identité canonique du Blueprint

```text
blueprint_id
```

Format : UUIDv7 (time-ordered UUID via `Str::orderedUuid()`)

Règles :

* généré par `KernelBlueprintFactory` avant l'entrée dans KRP ;
* immuable pendant tout le pipeline ;
* distinct de `kernel_code` ;
* distinct du concept supprimé `rotation_identifier` ;
* transmis jusqu'à ReadyBank ;
* inclus dans `CURRENT_KERNEL_RECEIVED`.

`rotation_identifier` est définitivement supprimé.

`kernel_code` ne sert pas d'identité de Blueprint : il est généré par `KernelCodeEngine` (hors périmètre).

---

# 4. États du cycle de vie du Blueprint

États techniques d'orchestration — distincts des slots du Blueprint et des verdicts de validation.

```text
CREATED_UNENGAGED
ENGAGED_IN_PIPELINE
READY_BANK_RECEIVED
NOT_ENGAGED_PRODUCTION_ON_HOLD
```

## 4.1 CREATED_UNENGAGED

Le Blueprint vient d'être créé par `KernelBlueprintFactory`.

Il peut encore :

* recevoir plusieurs propositions `depth + domain` (sur EMPTY) ;
* être engagé dans le pipeline ;
* être arrêté avant engagement si aucun besoin ne reste.

## 4.2 ENGAGED_IN_PIPELINE

Taxonomy a fourni `subdomain_active`, `subject_active`, `dominant_idea_active`.

À partir de ce moment :

* le couple `depth + domain` est définitivement engagé et immuable ;
* le Blueprint poursuit le pipeline intellectuel.

## 4.3 READY_BANK_RECEIVED

ReadyBank a reçu le Blueprint terminé.

Cet état provoque l'émission de `CURRENT_KERNEL_RECEIVED`.

## 4.4 NOT_ENGAGED_PRODUCTION_ON_HOLD

KRP constate qu'aucun Depth ne possède encore `cycle_completed[depth] < cycle_target[depth]`.

* aucun `depth + domain` n'est inscrit ;
* le Blueprint ne poursuit pas le pipeline ;
* aucun noyau n'est comptabilisé ;
* aucun signal ReadyBank n'est produit ;
* l'enveloppe reste comme trace technique non engagée.

---

# 5. Un seul Blueprint actif

À tout instant, un seul Blueprint peut être dans l'état `CREATED_UNENGAGED` ou `ENGAGED_IN_PIPELINE`.

Cette protection est vérifiée par `KernelBlueprintFactory` avant chaque création.

Un nouveau Blueprint est autorisé après :

* `CURRENT_KERNEL_RECEIVED` (comptabilisé par le listener) ;
* ou classement d'un Blueprint précédent en `NOT_ENGAGED_PRODUCTION_ON_HOLD`.

---

# 6. Tour de Depth

## 6.1 Mission

Répondre à la question :

```text
Quels Domaines participent encore au Tour de Depth actif ?
```

## 6.2 Domaines officiels — DomainCycle

```text
Géographie → Histoire → Faune → Art → Sport → Cinéma → Cuisine → Science
→ retour à Géographie
```

Code interne (snake_case) :

```text
geographie, histoire, faune, art, sport, cinema, cuisine, science
```

`Général` est exclu de la création.

Aucun signal `AVAILABLE` n'existe.

## 6.3 Initialisation

Au début de chaque Tour de Depth :

```text
geographie = ON
histoire   = ON
faune      = ON
art        = ON
sport      = ON
cinema     = ON
cuisine    = ON
science    = ON
```

## 6.4 Territoire fourni par Taxonomy

`TaxonomyProgressManager::peekNext()` retourne un tableau non-null :

```text
Domaine reste ON
Blueprint engagé dans le pipeline
```

## 6.5 EMPTY

`TaxonomyProgressManager::peekNext()` retourne `null` :

```text
Domaine ON → OFF
Domaine ignoré jusqu'à la fin du Tour de Depth
```

La transition `ON → OFF` est idempotente.

Un Domaine déjà `OFF` ne produit aucun second incrément.

## 6.6 Fermeture du Tour

```text
8 Domaines OFF
↓
Tour de Depth terminé
```

---

# 7. DepthNeedMatrix

## 7.1 Mission

Porter les cibles de Tours, les Tours complétés, et les noyaux reçus.

Ne porte pas les états ON/OFF des Domaines.

Ne prend aucune décision.

## 7.2 DepthCycle officiel

```text
2 → 4 → 6 → 7 → 8 → 9 → 10
```

Après Depth 10 : la recherche reprend à Depth 2.

## 7.3 Cibles officielles

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100
```

Constantes de code — non persistées.

## 7.4 Progression par Tour

```text
0/8 → 1/8 → 2/8 → … → 8/8
```

Chaque transition valide `ON → OFF` produit exactement `+1`.

## 7.5 Fermeture d'un Tour

À `8/8` :

```text
cycle_completed[active_depth] += 1
```

DepthNeedMatrix recherche ensuite le prochain Depth du cycle pour lequel :

```text
cycle_completed[depth] < cycle_target[depth]
```

Le résultat est retourné à KRP.

KRP ne recommence jamais immédiatement un Tour du même Depth.

## 7.6 Noyaux reçus

```text
kernel_received_total[depth][domain]
```

À chaque `CURRENT_KERNEL_RECEIVED` valide :

```text
kernel_received_total[depth][domain] += 1
```

Total du Depth = somme des huit Domaines.

Aucun second compteur total distinct.

## 7.7 Initialisation depuis ReadyBank

Au premier démarrage d'un Depth :

* `kernel_received_total[depth][domain]` est initialisé depuis la réalité ReadyBank ;
* interdit d'initialiser à 0 si ReadyBank contient déjà des noyaux.

---

# 8. KernelRotationPlanner — interface V2

## 8.1 Méthodes V2

```text
planV2(KernelBlueprint $blueprint, ?string $previousDomain = null): string
```

Entrée : Blueprint vide (CREATED_UNENGAGED) + Domaine précédent (null = premier appel).

Sortie : `ROTATION_ASSIGNED` ou `NOT_ENGAGED_PRODUCTION_ON_HOLD`.

```text
applyEmptyTransitionV2(string $emptyDomain): void
```

Appelé par l'Orchestrateur après un EMPTY de Taxonomy.

Met à jour le Tour de Depth (ON → OFF).

Si Tour 8/8 : ferme le Tour, sélectionne le prochain Depth.

```text
receiveKernelReceivedV2(string $blueprintId, int $depth, string $domain): void
```

Délégué au listener `ApplyCurrentKernelReceivedToRotation`.

Comptabilise la réception de façon idempotente.

## 8.2 Méthodes legacy (DEPRECATED)

Les méthodes suivantes restent physiquement présentes pour le retour arrière mais ne sont plus utilisées par KRP V2 :

```text
plan(DomainExhaustionChecker $checker): array   — DEPRECATED
initialize(?int $startDepth): void              — DEPRECATED
buildDepthNeedMatrix(array $existingByDepth): array — DEPRECATED
chooseDepth(array $matrix): int                 — DEPRECATED
advanceDomainIndex(?int $currentIndex, array $domains): int — DEPRECATED
```

## 8.3 Sélection du Domaine — règle exacte

KRP utilise `DepthTourState::getNextOnDomain(?string $previousDomain)` :

1. Part du `previousDomain` ;
2. parcourt le DomainCycle circulairement ;
3. ignore les Domaines `OFF` ;
4. retourne le premier Domaine `ON`.

Aucun curseur numérique `domain_position` ou `current_domain_index` n'est persisté.

## 8.4 Ce que KRP n'écrit jamais

```text
blueprint_id           → KernelBlueprintFactory
kernel_code            → KernelCodeEngine (hors périmètre)
subdomain_active       → Taxonomy
subject_active         → Taxonomy
dominant_idea_active   → Taxonomy
rotation_identifier    → supprimé
```

---

# 9. Flow initial

```text
KernelPipelineOrchestrator
↓
KernelBlueprintFactory crée le Blueprint
↓
état CREATED_UNENGAGED
↓
KRP.planV2($blueprint, null)
```

## 9.1 Un besoin existe

```text
KRP sélectionne active_depth (DepthNeedMatrix)
↓
Tour de Depth initialisé (8 Domaines ON, progression 0/8)
↓
KRP sélectionne premier Domaine ON
↓
KRP écrit depth + domain via fillRotation()
↓
Taxonomy.peekNext(depth, domain)
```

### Taxonomy fournit le territoire

```text
Blueprint.fillTaxonomy(subdomain, subject, dominant_idea)
↓
Blueprint = ENGAGED_IN_PIPELINE
↓
pipeline intellectuel
↓
ReadyBank
```

### Taxonomy retourne null (EMPTY)

```text
KRP.applyEmptyTransitionV2(domain)
↓
Domaine ON → OFF
DepthNeedMatrix progression +1/8
même Blueprint conservé
↓
KRP.planV2($blueprint, $emptyDomain)
↓
KRP sélectionne prochain Domaine ON
↓
KRP remplace depth + domain dans le même Blueprint
↓
Taxonomy.peekNext(nouveau depth, nouveau domain)
```

Ce cycle peut se répéter plusieurs fois avec le même Blueprint.

## 9.2 Aucun besoin

```text
PRODUCTION_ON_HOLD
↓
Blueprint = NOT_ENGAGED_PRODUCTION_ON_HOLD
↓
aucun pipeline
```

---

# 10. Flow récurrent

```text
ReadyBank reçoit le Blueprint
↓
Blueprint = READY_BANK_RECEIVED
↓
CURRENT_KERNEL_RECEIVED (Outbox)
↓
ApplyCurrentKernelReceivedToRotation listener
↓
comptabilisation idempotente
↓
KernelPipelineOrchestrator crée le Blueprint suivant
↓
KRP.planV2($blueprint, $previousDomain)
↓
reprise du Tour de Depth actif
```

Tant que la progression est inférieure à `8/8` :

```text
active_depth reste inchangé
prochain Domaine ON sélectionné
```

À `8/8` :

```text
cycle_completed[active_depth] += 1
↓
prochain Depth requis
↓
nouveau Tour de Depth (8 Domaines ON, progression 0/8)
```

---

# 11. Canal ReadyBank → KRP

## 11.1 Événement transactionnel avec Outbox

Nom : `CURRENT_KERNEL_RECEIVED`

Dans la même transaction que la réception ReadyBank :

1. ReadyBank persiste le Blueprint ;
2. exécution du Blueprint passe à `READY_BANK_RECEIVED` ;
3. ReadyBank écrit un événement dans `kernel_pipeline_outbox` ;
4. transaction validée.

## 11.2 Payload obligatoire

```text
event_id          UUID
event_type        = CURRENT_KERNEL_RECEIVED
schema_version    = 1
blueprint_id      UUID
depth             int
domain            string
occurred_at       datetime
```

`kernel_code` peut être présent pour traçabilité — KRP ne l'utilise pas pour l'idempotence.

## 11.3 Listener

```text
ApplyCurrentKernelReceivedToRotation
```

Séquence :

1. vérifier `blueprint_id` dans `kernel_current_kernel_receipts` ;
2. si le reçu existe : NO-OP (idempotent) ;
3. sinon : insérer le reçu + `kernel_received_total[depth][domain] += 1` ;
4. marquer l'événement Outbox comme traité.

## 11.4 Règle de non-jouabilité

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si des slots sont `FAIL` ou en correction.

Quarantine ne bloque jamais la rotation.

---

# 12. Persistance exacte

Migrations additives — aucune colonne legacy supprimée dans la série initiale.

## 12.1 `kernel_blueprint_runs`

```text
blueprint_id      UUID PRIMARY KEY
execution_state   VARCHAR  (CREATED_UNENGAGED | ENGAGED_IN_PIPELINE
                             | READY_BANK_RECEIVED | NOT_ENGAGED_PRODUCTION_ON_HOLD)
depth             SMALLINT NULL
domain_code       VARCHAR  NULL
created_at        TIMESTAMP
engaged_at        TIMESTAMP NULL
received_at       TIMESTAMP NULL
updated_at        TIMESTAMP
```

## 12.2 `kernel_rotation_state_v2`

Une seule ligne active.

```text
id
active_depth                      SMALLINT NULL
active_tour_id                    UUID NULL
rotation_status                   VARCHAR  (TOUR_IN_PROGRESS | NOT_ENGAGED_PRODUCTION_ON_HOLD)
tour_domain_states                JSON
active_blueprint_identity         VARCHAR  NULL
last_counted_blueprint_identity   VARCHAR  NULL
lock_version                      INTEGER
created_at                        TIMESTAMP
updated_at                        TIMESTAMP
```

`tour_domain_states` :

```json
{
  "states":         {"geographie":"ON","histoire":"ON",...},
  "empty_progress": 0
}
```

## 12.3 `kernel_depth_matrix`

Une ligne par Depth. Alimentée par M-07.

```text
depth                         SMALLINT PRIMARY KEY
cycle_target                  INTEGER
cycle_completed               INTEGER  DEFAULT 0
empty_progress_current_tour   SMALLINT DEFAULT 0
current_tour_id               UUID NULL
created_at                    TIMESTAMP
updated_at                    TIMESTAMP
```

Contraintes : `depth IN (2,4,6,7,8,9,10)`, `cycle_completed >= 0`, `empty_progress_current_tour BETWEEN 0 AND 8`.

## 12.4 `kernel_depth_domain_totals`

56 lignes initiales (7 Depths × 8 Domaines). Alimentée par M-08.

```text
depth                  SMALLINT
domain_code            VARCHAR
kernel_received_total  BIGINT   DEFAULT 0
created_at             TIMESTAMP
updated_at             TIMESTAMP
PRIMARY KEY (depth, domain_code)
```

## 12.5 `kernel_current_kernel_receipts`

```text
blueprint_id  UUID PRIMARY KEY
event_id      UUID UNIQUE
depth         SMALLINT
domain_code   VARCHAR
received_at   TIMESTAMP
```

Garantit l'idempotence de `CURRENT_KERNEL_RECEIVED`.

## 12.6 `kernel_pipeline_outbox`

```text
event_id       UUID PRIMARY KEY
event_type     VARCHAR
schema_version INTEGER
payload        JSON (TEXT sur SQLite)
occurred_at    TIMESTAMP
processed_at   TIMESTAMP NULL
attempt_count  INTEGER    DEFAULT 0
last_error     TEXT NULL
created_at     TIMESTAMP
updated_at     TIMESTAMP
```

---

# 13. Atomicité obligatoire

## 13.1 Transition EMPTY

Dans une seule transaction :

1. verrouiller `kernel_rotation_state_v2` ;
2. vérifier que le Domaine est `ON` ;
3. le passer à `OFF` ;
4. si Tour 8/8 : `cycle_completed[active_depth] += 1`, sélectionner prochain Depth, init nouveau Tour ;
5. persister.

Signal `EMPTY` répété pour un Domaine déjà `OFF` : NO-OP.

## 13.2 CURRENT_KERNEL_RECEIVED

Dans une seule transaction :

1. tentative d'insertion du reçu par `blueprint_id` ;
2. si existe déjà : aucun incrément ;
3. sinon : `kernel_received_total[depth][domain] += 1`.

## 13.3 Inscription depth + domain

```text
écrire depth + domain dans le Blueprint
+
enregistrer active_blueprint_identity dans kernel_rotation_state_v2
```

---

# 14. Reprise après interruption

Au redémarrage, KRP recharge l'état depuis `kernel_rotation_state_v2`.

## 14.1 Blueprint actif existant

La présence de `active_blueprint_identity` ne déclenche pas une nouvelle rotation.

Le véritable déclencheur reste `CURRENT_KERNEL_RECEIVED`.

## 14.2 Événement Outbox non traité

L'idempotence de `kernel_current_kernel_receipts` garantit qu'aucun double incrément ne se produit lors du rejeu.

## 14.3 Tour partiellement OFF

L'état `tour_domain_states` est repris tel quel depuis `kernel_rotation_state_v2`.

Aucun Domaine n'est remis à `ON` lors d'une reprise.

---

# 15. États internes du Planner

```text
INITIALIZING_DEPTH
↓
SELECTING_DOMAIN
↓
WRITING_DEPTH_DOMAIN
↓
WAITING_TAXONOMY
├── territoire → BLUEPRINT_ENGAGED
└── EMPTY      → APPLYING_EMPTY_TRANSITION
                 ↓
                 SELECTING_DOMAIN (même Blueprint)
↓
BLUEPRINT_ENGAGED
↓
WAITING_CURRENT_KERNEL_RECEIVED
↓
CALCULATING_NEXT_POSITION
├── Tour < 8/8 → SELECTING_DOMAIN (prochain Domaine ON)
└── Tour = 8/8 → CLOSING_TOUR → INITIALIZING_NEXT_DEPTH
                                  ou PRODUCTION_ON_HOLD
```

---

# 16. Classes du périmètre

## 16.1 Nouvelles classes

```text
KernelPipelineOrchestrator
KernelBlueprintFactory
DepthNeedMatrix
DepthTourState
KernelBlueprintRunRepository
KernelRotationStateRepository
DepthNeedMatrixRepository
CurrentKernelReceived
ApplyCurrentKernelReceivedToRotation
KernelPipelineOutboxRepository
```

## 16.2 Classes modifiées

```text
KernelBlueprint        — ajout du champ blueprint_id
KernelRotationPlanner  — ajout des méthodes V2 (legacy DEPRECATED)
TaxonomyProgressManager — retour null de peekNext() = signal EMPTY
```

## 16.3 Hors périmètre (intacts)

```text
KernelCodeEngine            → spécification ultérieure
Pipeline BankWorker complet → strictement intact
```

---

# 17. Éléments legacy supprimés du contrat V2

Le code V2 ne doit plus utiliser :

```text
ALLOWED_DEPTHS = [4,6,7,8,9]
DEPTH_TARGETS en noyaux
current_domain_index
completed_domains
last_rotation_identifier
rotation_identifier
remaining_kernels
chooseDepth par déficit maximal
advanceDomainIndex
depth_position / domain_position
AVAILABLE
TARGET_COMPLETE
RESERVOIR_EMPTY
EMPTY_BEFORE_TARGET
kernel_target (par Domaine)
kernel_remaining
reservoir_status_by_depth_and_domain
WAITING_TAXONOMY_STATE
```

Colonnes et méthodes legacy conservées physiquement pour le retour arrière.

Marquées `DEPRECATED — NON UTILISÉES PAR KRP V2`.

Suppression physique : patch séparé après validation terminale.

---

# 18. Règles absolues

## KRP-R01

DepthNeedMatrix initialise `cycle_target[depth]` (constantes) et fournit `kernel_received_total[depth][domain]`. Elle ne porte pas les états ON/OFF.

## KRP-R02

ReadyBank confirme les Blueprints réellement reçus.

## KRP-R03

Taxonomy fournit un territoire (`array`) ou `null` (EMPTY). Elle n'émet aucun signal AVAILABLE vers KRP.

## KRP-R04

KernelRotationPlanner combine ces informations et décide seul de la rotation.

## KRP-R05

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si des slots sont `FAIL`.

## KRP-R06

Quarantine ne bloque jamais la rotation.

## KRP-R07

À tout instant, un seul Blueprint peut être `CREATED_UNENGAGED` ou `ENGAGED_IN_PIPELINE`.

## KRP-R08

La transition `ON → OFF` d'un Domaine est idempotente.

## KRP-R09

Le Depth change lorsque le Tour atteint `8/8`. `cycle_completed[active_depth] += 1` est déclenché à ce moment.

## KRP-R10

KRP ne recommence jamais immédiatement un Tour du même Depth après fermeture.

## KRP-R11

`CURRENT_KERNEL_RECEIVED` est le seul signal déclencheur de la prochaine rotation. Taxonomy ne transmet aucun signal vers KRP.

## KRP-R12

L'idempotence de `CURRENT_KERNEL_RECEIVED` est garantie par `kernel_current_kernel_receipts` (PK sur `blueprint_id`).

## KRP-R13

L'état de rotation est persistant dans `kernel_rotation_state_v2`.

## KRP-R14

Toute comptabilisation est exécutée dans une transaction atomique.

## KRP-R15

`Général` ne peut jamais être présent dans le DomainCycle.

## KRP-R16

KRP n'écrit jamais `kernel_code`.

## KRP-R17

`rotation_identifier` est supprimé. Aucun composant ne le produit ni ne le consomme dans V2.

## KRP-R18

Sur EMPTY, le même Blueprint est conservé. Aucun nouveau Blueprint n'est créé après un EMPTY.

## KRP-R19

DepthCycle officiel : `2 → 4 → 6 → 7 → 8 → 9 → 10`. Après Depth 10 : reprend à Depth 2.

---

# 19. Migrations

Ordre obligatoire, additives :

```text
M-01  create_kernel_blueprint_runs
M-02  create_kernel_rotation_state_v2
M-03  create_kernel_depth_matrix
M-04  create_kernel_depth_domain_totals
M-05  create_kernel_current_kernel_receipts
M-06  create_kernel_pipeline_outbox
M-07  seed_depth_matrix         (7 lignes)
M-08  seed_depth_domain_totals  (56 lignes)
```

Aucune migration ne modifie :
`question_groups`, `question_translations`, `taxonomy_progress`, `kernel_rotation_state` (legacy), tables BankWorker, Redis BankWorker.

Aucun `DROP COLUMN` dans cette série.

---

# Architecture Register

## DEC-051 — Initialisation par DepthNeedMatrix

**Statut :** SUPERSEDED par DEC-060

---

## DEC-052 — Réception ReadyBank indépendante de la jouabilité

**Statut :** OFFICIAL

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si des slots sont `FAIL` ou en correction.

---

## DEC-053 — Deux signaux indépendants

**Statut :** SUPERSEDED par DEC-063

---

## DEC-054 — États distincts des domaines

**Statut :** SUPERSEDED par DEC-061

---

## DEC-055 — Complétion sans domaine sélectionnable

**Statut :** SUPERSEDED par DEC-062

---

## DEC-056 — Persistance obligatoire de RotationState

**Statut :** SUPERSEDED par DEC-064

---

## DEC-057 — DepthCycle sans Depth 10

**Statut :** SUPERSEDED par DEC-065

---

## DEC-058 — Blueprint créé avant KRP

**Statut :** OFFICIAL

`KernelBlueprintFactory` crée le Blueprint avant l'entrée dans KRP.
KRP reçoit un Blueprint vide et y inscrit uniquement `depth` et `domain`.

---

## DEC-059 — Identité canonique blueprint_id

**Statut :** OFFICIAL

`blueprint_id` est un UUIDv7 (time-ordered) généré par `KernelBlueprintFactory`.
`rotation_identifier` est supprimé.
`kernel_code` ne sert pas d'identité de Blueprint.

---

## DEC-060 — DepthNeedMatrix V2

**Statut :** OFFICIAL

DepthNeedMatrix porte : `DepthCycle [2,4,6,7,8,9,10]`, `cycle_target[depth]` (constantes), `cycle_completed[depth]`, `kernel_received_total[depth][domain]`. Elle ne porte pas les états ON/OFF des Domaines et ne prend aucune décision.

---

## DEC-061 — Tour de Depth ON/OFF

**Statut :** OFFICIAL

8 Domaines ON au début de chaque Tour. Sur EMPTY : Domaine ON → OFF (idempotent). Tour terminé à 8 Domaines OFF.

---

## DEC-062 — Fermeture de Tour et bascule de Depth

**Statut :** OFFICIAL

Tour fermé à 8/8. `cycle_completed[active_depth] += 1`. Prochain Depth = premier Depth du DepthCycle pour lequel `cycle_completed < cycle_target`. KRP ne recommence jamais immédiatement le même Depth.

---

## DEC-063 — CURRENT_KERNEL_RECEIVED signal unique

**Statut :** OFFICIAL

Seul déclencheur de la prochaine rotation. Canal = événement transactionnel avec Outbox. Listener = `ApplyCurrentKernelReceivedToRotation`. Idempotence = `kernel_current_kernel_receipts` (PK blueprint_id).

---

## DEC-064 — Persistance dans kernel_rotation_state_v2

**Statut :** OFFICIAL

Nouvelle table `kernel_rotation_state_v2` (coexiste avec la table legacy DEPRECATED).

---

## DEC-065 — DepthCycle complet incluant Depth 2 et Depth 10

**Statut :** OFFICIAL

DepthCycle = `2 → 4 → 6 → 7 → 8 → 9 → 10`. Après Depth 10 : reprend à Depth 2. ROTATION_COMPLETE = aucun Depth sous `cycle_target`.

---

## DEC-066 — Conservation du Blueprint sur EMPTY

**Statut :** OFFICIAL

Sur EMPTY, le même Blueprint est conservé et réutilisé. Aucun nouveau Blueprint n'est créé après un EMPTY.

---

## DEC-067 — Cycle de vie d'exécution du Blueprint

**Statut :** OFFICIAL

Quatre états techniques : `CREATED_UNENGAGED`, `ENGAGED_IN_PIPELINE`, `READY_BANK_RECEIVED`, `NOT_ENGAGED_PRODUCTION_ON_HOLD`. Distincts des slots du Blueprint.

---

## DEC-068 — KernelCodeEngine hors périmètre KRP

**Statut :** OFFICIAL

KRP n'écrit jamais `kernel_code`. `kernel_code = null` à la sortie de KRP.
