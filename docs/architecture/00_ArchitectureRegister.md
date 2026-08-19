# Architecture Register — StrategyBuzzer Kernel Pipeline

**Source de vérité :** ce fichier est le registre centralisé de toutes les décisions architecturales officielles du pipeline Kernel.

Chaque décision inscrite ici est :
- identifiée par un numéro unique (DEC-NNN) ;
- datée ;
- associée à un statut : `OFFICIAL`, `UNDER_REVIEW`, ou `SUPERSEDED` ;
- liée au module concerné.

---

## DEC-027 — Progression individuelle des slots

**Version :** 1.3
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

La validation traite tous les slots concernés avant de produire une copie Quarantine.

Une seule copie travaillable est créée à la fin de la passe lorsqu'un ou plusieurs slots sont `FAIL`.

---

## DEC-028 — Retour ciblé depuis Quarantine

**Statut :** SUPERSEDED
**Remplacé par :** DEC-030
**Module :** `01_KernelBlueprint.md`

Ancienne décision : une copie corrigée retournait au moteur propriétaire du contenu fautif.

---

## DEC-029 — Réintégration limitée au slot initialement FAIL

**Statut :** SUPERSEDED
**Remplacé par :** DEC-031
**Module :** `01_KernelBlueprint.md`

Ancienne décision : la réintégration remplaçait uniquement le slot précédemment identifié `FAIL`.

---

## DEC-030 — Retour systématique à Phase 1

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

Toute copie travaillable corrigée provenant de Quarantine retourne systématiquement à Phase 1.

Cette règle s'applique aux erreurs détectées :
- en Validation Phase 1 ;
- en Validation Phase 2 ;
- dans un contenu cognitif ;
- dans une traduction ;
- dans une dépendance entre plusieurs slots.

---

## DEC-031 — Réintégration de tous les slots modifiés

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

La copie corrigée est réintégrée dans le Blueprint canonique portant le même `kernel_code`.

La réintégration peut concerner les slots initialement `FAIL`, les slots initialement `OK` mais modifiés, les slots dépendants régénérés, les traductions corrigées.

Les slots canoniques non modifiés restent inchangés.

---

## DEC-032 — Une copie par passe de validation

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

Un moteur de validation termine l'analyse de tous les slots qui lui ont été remis avant de produire une copie Quarantine.

Lorsqu'un ou plusieurs slots sont `FAIL`, il crée une seule copie travaillable contenant tous les slots en échec, toutes les erreurs détectées, tous les points de correction, et le contexte complet du noyau.

Il est interdit de créer une copie distincte pour chaque slot `FAIL` appartenant à la même passe.

---

## DEC-033 — Distinction PASS et OK

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

`PASS` est le verdict produit par un moteur de validation.

`OK` est l'état attribué au slot après un verdict `PASS`.

`FAIL` constitue à la fois le verdict d'échec et l'état de fermeture du slot jusqu'à sa correction.

---

## DEC-034 — Immutabilité write-once de KernelBlueprint

**Version :** 1.0
**Date :** 12 août 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

Toutes les propriétés de `KernelBlueprint` sont privées. La lecture publique passe par `__get()` (comportement transparent). L'écriture directe externe est interceptée par `__set()` et lève `LogicException`. Chaque slot ne peut être attribué qu'une seule fois via la méthode `fill*()` de son propriétaire. Un second appel à `fill*()` sur un slot déjà rempli lève `LogicException`. Méthodes d'écriture : `initializeBlueprintId()` (Factory), `fillRotation()` (KRP), `fillTaxonomy()` (Taxonomy), `fillKernelCode()` (KernelCodeEngine).

---

## DEC-035 — Atomicité DB de la création de Blueprint

**Version :** 1.0
**Date :** 12 août 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

L'unicité du Blueprint actif (DEC-067) est garantie par deux niveaux : (1) vérification applicative `SELECT EXISTS` (chemin rapide, séquentiel) ; (2) index unique partiel PostgreSQL `one_active_blueprint_idx` sur l'expression constante `(1)` filtré aux états `CREATED_UNENGAGED` et `ENGAGED_IN_PIPELINE` (atomique, protège contre la concurrence). Sur conflit DB, `UniqueConstraintViolationException` est capturée et convertie en la même `RuntimeException` que le chemin applicatif. Sur SQLite (PHPUnit), la migration est un NO-OP ; seul le chemin applicatif protège. Test concurrent : 20 workers forkés → exactement 1 SUCCESS, 19 refus, 1 Blueprint actif.

---

## DEC-051 — Initialisation par DepthNeedMatrix

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-060
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-060 (DepthNeedMatrix V2).

---

## DEC-052 — Réception ReadyBank indépendante de la jouabilité

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si certains slots sont `FAIL` ou en correction.

---

## DEC-053 — Deux signaux indépendants

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-063
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-063 (CURRENT_KERNEL_RECEIVED signal unique).

---

## DEC-054 — États distincts des domaines

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-061
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-061 (Tour de Depth ON/OFF).

---

## DEC-055 — Complétion sans domaine sélectionnable

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-062
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-062 (fermeture de Tour et bascule de Depth).

---

## DEC-056 — Persistance obligatoire de RotationState

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-064
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-064 (kernel_rotation_state_v2).

---

## DEC-057 — Inclusion officielle du Depth 2 et ordre du DepthCycle

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-094
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-094 (DepthCycle intellectuel officiel v3.2).

---

## DEC-058 — Blueprint créé avant KRP

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`KernelBlueprintFactory` crée le Blueprint avant l'entrée dans KRP.
KRP reçoit un Blueprint vide et y inscrit uniquement `depth` et `domain`.

---

## DEC-059 — Identité canonique blueprint_id

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`blueprint_id` est un UUIDv7 (time-ordered, via `Str::orderedUuid()`) généré par `KernelBlueprintFactory`.
`rotation_identifier` est supprimé.
`kernel_code` ne sert pas d'identité de Blueprint.

---

## DEC-060 — DepthNeedMatrix V2

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL (traçabilité)
**Module :** `02_KernelRotationPlanner.md`

`DepthNeedMatrix` porte `kernel_received_total[depth][domain]` comme données de traçabilité. `CYCLE_TARGET` et `cycle_completed` ne sont plus l'autorité de changement de Depth — remplacés par `DEPTH_EXHAUSTED` (DEC-083).

---

## DEC-061 — Tour de Depth ON/OFF

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** SUPERSEDED par DEC-082 + DEC-083
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — le modèle Tour ON/OFF basé sur la boucle EMPTY est remplacé par `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` prospectifs (DEC-082, DEC-083).

---

## DEC-062 — Fermeture de Tour et bascule de Depth

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** SUPERSEDED par DEC-083
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — le changement de Depth via compteur 8/8 est remplacé par `DEPTH_EXHAUSTED` de Taxonomy (DEC-083).

---

## DEC-063 — CURRENT_KERNEL_RECEIVED signal unique

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Seul déclencheur de la prochaine rotation. Canal = événement transactionnel avec Outbox. Listener = `ApplyCurrentKernelReceivedToRotation`. Idempotence = `kernel_current_kernel_receipts` (PK blueprint_id).

---

## DEC-064 — Persistance dans kernel_rotation_state_v2

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Nouvelle table `kernel_rotation_state_v2` (coexiste avec la table legacy DEPRECATED). Aucune table existante n'est supprimée.

---

## DEC-065 — DepthCycle complet incluant Depth 2 et Depth 10

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** SUPERSEDED par DEC-094
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-094 (DepthCycle intellectuel officiel). La transition terminale après Depth 10 est définie par DEC-092.

---

## DEC-066 — Conservation du Blueprint sur EMPTY

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** SUPERSEDED par DEC-034 + DEC-082
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — l'ancienne logique EMPTY (même Blueprint réutilisé avec autre `depth + domain`) est incompatible avec le contrat write-once de `01_KernelBlueprint` v1.5 (DEC-034) et le modèle `DOMAIN_EXHAUSTED` prospectif (DEC-082).

---

## DEC-067 — Cycle de vie d'exécution du Blueprint

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Quatre états techniques : `CREATED_UNENGAGED`, `ENGAGED_IN_PIPELINE`, `READY_BANK_RECEIVED`, `NOT_ENGAGED_PRODUCTION_ON_HOLD`. Distincts des slots du Blueprint.

---

## DEC-068 — KernelCodeEngine hors périmètre KRP

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

KRP n'écrit jamais `kernel_code`. `kernel_code = null` à la sortie de KRP.

---

## DEC-079 — ReadyBank décrémente le besoin

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-093
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-093 (`CURRENT_KERNEL_RECEIVED` seul incrémenteur de `kernel_received_total`).

---

## DEC-082 — DOMAIN_EXHAUSTED prospectif

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`DOMAIN_EXHAUSTED(depth, domain)` : signal prospectif de Taxonomy vers KRP, émis après consommation exacte du Blueprint courant.

Le Blueprint déclencheur reste valide et continue normalement dans le pipeline.
Le signal modifie uniquement la rotation future.
Portée : `Depth + Domaine` exclusivement.
Idempotent : deuxième réception du même signal = NO-OP.

DomainCycle officiel (réénoncé) : 8 domaines de création — Géographie, Histoire, Faune, Art, Sport, Cinéma, Cuisine, Science. `Général` est exclu de la création intellectuelle.
L'absence de signal d'épuisement signifie disponibilité implicite — aucun signal `AVAILABLE` requis.
Rotation déterministe, circulaire, continue tant qu'aucun signal prospectif d'épuisement ne retire un domaine de la rotation active.

---

## DEC-083 — DEPTH_EXHAUSTED prospectif

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`DEPTH_EXHAUSTED(depth)` : signal prospectif de Taxonomy vers KRP, émis quand tous les bassins Domaines du Depth courant sont épuisés.

Le Blueprint déclencheur reste valide et continue normalement.
Au prochain `CURRENT_KERNEL_RECEIVED` : KRP avance vers le prochain Depth, tous les domaines du nouveau Depth sont réinitialisés `ACTIF`.
Idempotent : signal `DEPTH_EXHAUSTED` déjà mémorisé → NO-OP.

---

## DEC-084 — Indépendance rotation KRP ↔ progression Taxonomy

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

KRP Tour Number ne détermine jamais le Taxonomy Subject Number ni le Taxonomy Idea Number.
Les 8 domaines partagent le cycle KRP mais leurs réservoirs Taxonomy progressent indépendamment.
Aucune synchronisation artificielle entre les progressions de domaines n'est admise.

---

## DEC-085 — Deux flux distincts : informationnel et déclencheur

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Flux informationnel : Taxonomy → signal d'épuisement → mise à jour de l'état KRP (immédiate, sans attendre ReadyBank).
Flux déclencheur : `CURRENT_KERNEL_RECEIVED` → prochain Blueprint → rotation effective.
Ces deux flux sont indépendants.

---

## DEC-086 — AVAILABLE rejeté

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** REJECTED
**Module :** `02_KernelRotationPlanner.md`

Taxonomy ne doit pas envoyer un signal `AVAILABLE`. L'absence de signal d'épuisement signifie disponibilité implicite du domaine.

---

## DEC-087 — Canal d'épuisement : contrat sémantique résolu, transport = détail d'implantation

**Version :** 1.1
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Contrat sémantique complet (D1 résolu) : QUI produit = Taxonomy ; QUI possède la rotation = KRP ; QUI transporte = Orchestration ; QUAND disponible = immédiatement après consommation exacte ; QUAND influence un Blueprint = au prochain `CURRENT_KERNEL_RECEIVED`.

Le transport physique exact (retour enrichi / Outbox / événement / table intermédiaire) est un détail d'implantation soumis aux garanties d'ordre, d'atomicité, d'idempotence et de persistance. Ce choix sera arrêté lors de l'audit d'implantation.

Contraintes inchangées : Taxonomy ne modifie pas directement `kernel_rotation_state_v2`. KRP ne consulte pas les tables Taxonomy pour décider de l'épuisement.

---

## DEC-088 — Remplacement de CYCLE_TARGET / cycle_completed comme autorité de changement de Depth par DEPTH_EXHAUSTED

**Version :** 1.1
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`CYCLE_TARGET` et `cycle_completed` sont rejetés comme autorité de décision de changement de Depth. `DEPTH_EXHAUSTED` de Taxonomy est l'autorité. Si ces compteurs deviennent utiles pour du reporting, une décision future les réintroduira avec un propriétaire clair.

`CYCLE_TARGET[10] = 100` n'est pas justifié par le numéro de niveau Solo Boss 100. Le numéro du niveau de gameplay ne définit aucun volume de production intellectuelle.

---

## DEC-089 — SHORTFALL et états dérivés : REJECTED

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** REJECTED
**Module :** `02_KernelRotationPlanner.md`

`SHORTFALL`, `DEPTH_TARGET_COMPLETE` et `DEPTH_RESERVOIRS_EXHAUSTED_WITH_SHORTFALL` sont rejetés comme états ou signaux KRP. Un seul signal d'épuisement existe : `DEPTH_EXHAUSTED`. L'écart de production éventuel est un concept de reporting/observabilité externe à KRP, sans propriétaire actuel.

---

## DEC-090 — DepthProductionState : REJECTED

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** REJECTED
**Module :** `02_KernelRotationPlanner.md`

`DepthProductionState` est rejeté. La structure est remplacée par `active_depth + domain_states` dans `kernel_rotation_state_v2`. Aucune autre responsabilité indépendante n'a été démontrée.

---

## DEC-091 — Double condition de sélection (kernel_remaining > 0 AND reservoir_status = AVAILABLE) : REJECTED

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** REJECTED
**Module :** `02_KernelRotationPlanner.md`

La sélection du prochain domaine repose uniquement sur : domaine `ACTIF` (non `DOMAIN_EXHAUSTED`) pour ce Depth. La double condition `kernel_remaining > 0 AND reservoir_status = AVAILABLE` est rejetée. `kernel_remaining` est rejeté comme critère de sélection (DEC-078). `AVAILABLE` est rejeté comme signal (DEC-086).

---

## DEC-092 — Transition terminale DEPTH_EXHAUSTED(10) → PRODUCTION_ON_HOLD

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Après `DEPTH_EXHAUSTED(10)` : `depth_state = PRODUCTION_ON_HOLD`. Aucun retour automatique à Depth 2. Aucun état `IDLE` distinct. Aucun Blueprint créé après entrée en `PRODUCTION_ON_HOLD`.

Séquence : Blueprint courant reste VALIDE → `DEPTH_EXHAUSTED(10)` → signal mémorisé prospectivement → au prochain `CURRENT_KERNEL_RECEIVED` (aucun Depth suivant) → `PRODUCTION_ON_HOLD`.

Garantie : si `depth_state = PRODUCTION_ON_HOLD`, l'orchestration n'appelle pas `KernelBlueprintFactory`. La Factory ne connaît pas `PRODUCTION_ON_HOLD`. Le gate appartient à l'orchestration du cycle.

Sortie de `PRODUCTION_ON_HOLD` : non définie dans ce contrat. Une décision architecturale distincte la définira si le projet en a besoin.

Idempotent : `PRODUCTION_ON_HOLD → PRODUCTION_ON_HOLD` = NO-OP.

---

## DEC-093 — CURRENT_KERNEL_RECEIVED seul incrémenteur de kernel_received_total

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`CURRENT_KERNEL_RECEIVED` est le seul événement qui incrémente `kernel_received_total[depth][domain]`.
La création d'un `KernelBlueprint` ne modifie jamais `kernel_received_total`.

---

## DEC-094 — DepthCycle intellectuel officiel

**Version :** 1.0
**Date :** 13 août 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

DepthCycle : `2 → 4 → 6 → 7 → 8 → 9 → 10`.

Après Depth 10 : la transition terminale est définie exclusivement par DEC-092 (`PRODUCTION_ON_HOLD`). Aucun retour automatique à Depth 2.

Rotation des domaines : voir DEC-082.

Remplace : DEC-065.

---

## DEC-069 — Mission officielle de QuestionIntent / KernelCodeEngine

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md` — v1.1 (12 août 2026)

KernelCodeEngine reçoit le KernelBlueprint dont le territoire intellectuel a été entièrement déterminé et validé, construit son kernel_code canonique selon la structure officielle StrategyBuzzer, attribue un suffixe séquentiel unique dans le bassin (Depth + Domaine), écrit ce kernel_code dans le KernelBlueprint et rend cette identité immuable. KernelCodeEngine ne modifie aucune composante intellectuelle du noyau et ne détermine aucun traitement cognitif de Phase 1.

---

## DEC-070 — kernel_code : propriétaire exclusif = KernelCodeEngine

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

KernelCodeEngine est le seul moteur autorisé à écrire `kernel_code` dans le KernelBlueprint et dans `kernel_blueprint_runs`. Aucun autre moteur ne peut créer, modifier ou invalider un kernel_code existant. KRP ne l'écrit jamais (DEC-068). Taxonomy, VDI, Phase 1 ne l'écrivent jamais.

---

## DEC-071 — Format officiel du kernel_code

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Format : `DD-DO-SUB-SUJ-IDE-VVVV` — 22 caractères, UPPERCASE ASCII sans espace.
`DD` = Depth 2 chiffres ; `DO` = code Domaine 2 lettres ; `SUB/SUJ/IDE` = 3 chars normalisés (NFD+strip+A-Z0-9, pad X) ; `VVVV` = suffixe base36 4 chars.
Regex : `^[0-9]{2}-[A-Z]{2}-[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}-[0-9A-Z]{4}$`

---

## DEC-072 — Suffixe VVVV : base36 4 chars, capacité 1 679 616 par bassin

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Alphabet base36 : `0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ`. Capacité : 36^4 = 1 679 616 par bassin Depth × Domaine. Première valeur : `0000`. Dernière valeur : `ZZZZ` (entier 1 679 615). Ordre strict : entier base10 → base36. Aucun algorithme aléatoire, aucun UUID, aucun hash.

---

## DEC-073 — Compteur indépendant par (Depth, domain_code)

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Table `kernel_code_sequences` — clé primaire composite `(depth, domain_code)`. `next_value` = prochain entier base10 à convertir. Chaque bassin `(02, GE)`, `(02, HI)`, `(04, GE)` etc. possède sa propre séquence indépendante. Allocation atomique par transaction avec `LOCK FOR UPDATE` sur la ligne de séquence. Source de vérité de l'identité : `kernel_blueprint_runs.kernel_code`, jamais `kernel_code_sequences`.

---

## DEC-074 — Immutabilité du kernel_code

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Transition autorisée : `NULL → valeur canonique`. Transition interdite : `valeur → autre valeur`. KernelCodeEngine lui-même ne régénère jamais l'identité d'un noyau déjà identifié. Idempotence : même Blueprint présenté deux fois → même kernel_code retourné, compteur avancé une seule fois.

---

## DEC-075 — Non-recyclage des suffixes consommés

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Un suffixe VVVV consommé n'est jamais remis dans le bassin, même si la validation aval échoue, si un print Quarantine est créé, ou si le noyau est corrigé. Le noyau canonique principal reste dans le flow. Son kernel_code ne change jamais. Après `ZZZZ` : `QUESTION_INTENT_SUFFIX_EXHAUSTED`, FAIL CLOSED — aucun overflow silencieux.

---

## DEC-076 — KernelCodeEngine : zéro responsabilité cognitive

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

KernelCodeEngine ne produit aucun contenu cognitif. Il ne choisit pas recognition, reasoning, deceptive_trap, true/false, réponses, formulations, difficulté cognitive. Il n'appelle pas Gemini, OpenAI, Phase 1, Quarantine, ReadyBank, confirmConsumed(). Ces responsabilités appartiennent exclusivement à Phase 1 et aux modules aval.

---

## DEC-077 — KLD / KEY_STRUCTURE / ks_hash / kld_hash exclus du kernel_code

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

KLD et KEY_STRUCTURE sont SUPERSEDED (absorbés par ValidationDominantIdeas). KernelCodeEngine ne les écrit pas et ne les lit pas. `ks_hash`, `kld_hash` et `question_intents.kernel_code` ont été supprimés physiquement le 11 août 2026 (audit #142/#147 : 0 writer, 0 reader, 0 données). Migrations : `2026_08_11_300000` (ks_hash, kld_hash) + `2026_08_11_310000` (kernel_code + index). Chaîne UP→DOWN vérifiée (#146 PASS). Stockage canonique = `kernel_blueprint_runs.kernel_code` (KernelCodeEngine, DEC-070).
