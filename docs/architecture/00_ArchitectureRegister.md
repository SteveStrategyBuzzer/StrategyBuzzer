# Architecture Register — StrategyBuzzer Kernel Pipeline

## Rôle du registre

Ce fichier est le **registre historique permanent** des décisions architecturales du pipeline Kernel.

Il n'efface aucune ancienne décision. Une décision remplacée reste inscrite et passe à `SUPERSEDED` avec sa décision remplaçante.

### Ordre d'autorité

1. spécification maître actuellement déclarée autoritaire pour le module concerné ;
2. décisions `OFFICIAL` non superseded du présent Architecture Register ;
3. code audité ;
4. tests exécutés au SHA audité ;
5. historique Git et notes techniques comme preuves secondaires.

Pour `02_KernelRotationPlanner`, la source métier actuellement déclarée autoritaire est la **spécification maître KernelRotationPlanner v2.0 du 28 juillet 2026**, revalidée comme « bible » de la partie intellectuelle le 18 août 2026. Les décisions postérieures qui la contredisent sont historisées mais ne restent pas `OFFICIAL`.

Statuts normalisés : `DRAFT`, `UNDER_REVIEW`, `OFFICIAL`, `SUPERSEDED`, `REJECTED`.

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
Cette règle s'applique aux erreurs détectées en Validation Phase 1, Validation Phase 2, dans un contenu cognitif, une traduction ou une dépendance entre plusieurs slots.

---

## DEC-031 — Réintégration de tous les slots modifiés

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `01_KernelBlueprint.md`

La copie corrigée est réintégrée dans le Blueprint canonique portant le même `kernel_code`.
La réintégration peut concerner les slots initialement `FAIL`, les slots initialement `OK` mais modifiés, les slots dépendants régénérés et les traductions corrigées. Les slots canoniques non modifiés restent inchangés.

---

## DEC-032 — Une copie par passe de validation

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `01_KernelBlueprint.md`

Un moteur de validation termine l'analyse de tous les slots remis avant de produire une copie Quarantine. Lorsqu'un ou plusieurs slots sont `FAIL`, il crée une seule copie travaillable contenant tous les échecs, erreurs, points de correction et le contexte complet du noyau.

---

## DEC-033 — Distinction PASS et OK

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `01_KernelBlueprint.md`

`PASS` est le verdict produit par un moteur de validation. `OK` est l'état attribué au slot après `PASS`. `FAIL` constitue le verdict d'échec et l'état de fermeture jusqu'à correction.

---

## DEC-034 — Immutabilité write-once absolue de KernelBlueprint

**Version :** 1.0  
**Date :** 12 août 2026  
**Statut :** SUPERSEDED par DEC-101  
**Module :** `01_KernelBlueprint.md` + `02_KernelRotationPlanner.md`

Ancienne décision : toutes les propriétés étaient privées, l'écriture directe externe interdite et chaque slot ne pouvait être attribué qu'une seule fois via sa méthode propriétaire, incluant `fillRotation()`.

Motif du remplacement : la spécification maître KRP autorise explicitement plusieurs propositions provisoires `depth + domain` sur un Blueprint `CREATED_UNENGAGED` lors des réponses `EMPTY`. L'immutabilité de la rotation commence à l'engagement Taxonomy, pas à la première proposition KRP.

---

## DEC-035 — Atomicité DB de la création de Blueprint

**Version :** 1.0  
**Date :** 12 août 2026  
**Statut :** OFFICIAL  
**Module :** `01_KernelBlueprint.md`

L'unicité du Blueprint actif est garantie par vérification applicative et par l'index unique partiel PostgreSQL protégeant les états `CREATED_UNENGAGED` et `ENGAGED_IN_PIPELINE`. Un conflit DB est converti en refus applicatif uniforme. La protection concurrente reste obligatoire.

---

## DEC-051 — Initialisation par DepthNeedMatrix

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** SUPERSEDED par DEC-060  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée historiquement par DEC-060.

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

Ancienne décision — remplacée par `CURRENT_KERNEL_RECEIVED` comme déclencheur unique de la prochaine rotation.

---

## DEC-054 — États distincts des domaines

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** SUPERSEDED par DEC-061  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée historiquement par le Tour de Depth ON/OFF.

---

## DEC-055 — Complétion sans domaine sélectionnable

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** SUPERSEDED par DEC-062  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée historiquement par la fermeture du Tour à 8/8.

---

## DEC-056 — Persistance obligatoire de RotationState

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** SUPERSEDED par DEC-064  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par `kernel_rotation_state_v2`.

---

## DEC-057 — Inclusion officielle du Depth 2 et ordre du DepthCycle

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** SUPERSEDED par DEC-094, puis DEC-097  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — chaîne de remplacement conservée pour historique.

---

## DEC-058 — Blueprint créé avant KRP

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

`KernelBlueprintFactory` crée le Blueprint avant l'entrée dans KRP. KRP reçoit un Blueprint vide et y inscrit uniquement `depth` et `domain`.

---

## DEC-059 — Identité canonique blueprint_id

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

`blueprint_id` est un UUIDv7 généré par `KernelBlueprintFactory`. `rotation_identifier` est supprimé. `kernel_code` ne sert pas d'identité de Blueprint.

---

## DEC-060 — DepthNeedMatrix réduite à la traçabilité

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** SUPERSEDED par DEC-096  
**Module :** `02_KernelRotationPlanner.md`

Ancienne formulation : `DepthNeedMatrix` ne conservait `kernel_received_total[depth][domain]` que comme traçabilité et `CYCLE_TARGET/cycle_completed` perdaient leur rôle de besoin. Cette restriction est remplacée par la spécification maître.

---

## DEC-061 — Tour de Depth ON/OFF

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** SUPERSEDED par DEC-098  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision correcte dans son principe, mais son statut historique avait ensuite été remplacé par le modèle prospectif. DEC-098 rétablit officiellement le mécanisme maître ON/OFF sans réactiver l'ancien identifiant.

---

## DEC-062 — Fermeture de Tour et bascule de Depth

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** SUPERSEDED par DEC-099  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision correcte dans son principe : fermeture à 8/8 et recherche du prochain besoin. DEC-099 devient l'autorité courante.

---

## DEC-063 — CURRENT_KERNEL_RECEIVED signal unique

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

`CURRENT_KERNEL_RECEIVED` est le seul déclencheur normal de la prochaine rotation. Canal : événement transactionnel avec Outbox. Idempotence : `kernel_current_kernel_receipts` par `blueprint_id`.

---

## DEC-064 — Persistance dans kernel_rotation_state_v2

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

`kernel_rotation_state_v2` porte l'état persistant de rotation et coexiste avec la table legacy pendant la migration. Les retraits physiques exigent un patch séparé après validation.

---

## DEC-065 — DepthCycle complet incluant Depth 2 et Depth 10

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** SUPERSEDED par DEC-094, puis DEC-097  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — chaîne de remplacement conservée.

---

## DEC-066 — Conservation du Blueprint sur EMPTY

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** SUPERSEDED par DEC-101  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : le même Blueprint était conservé sur `EMPTY`. Cette règle est réémise proprement par DEC-101 avec la distinction explicite entre proposition provisoire et engagement immuable.

---

## DEC-067 — Cycle de vie d'exécution du Blueprint

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

Quatre états techniques : `CREATED_UNENGAGED`, `ENGAGED_IN_PIPELINE`, `READY_BANK_RECEIVED`, `NOT_ENGAGED_PRODUCTION_ON_HOLD`. Ils sont distincts des slots du Blueprint.

---

## DEC-068 — KernelCodeEngine hors périmètre KRP

**Version :** 2.0  
**Date :** 28 juillet 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

KRP n'écrit jamais `kernel_code`. Ce champ appartient exclusivement à `KernelCodeEngine`.

---

## DEC-079 — ReadyBank décrémente le besoin

**Version :** 1.0  
**Date :** 14 juillet 2026  
**Statut :** SUPERSEDED par DEC-093  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par la comptabilisation positive `kernel_received_total` lors de `CURRENT_KERNEL_RECEIVED`.

---

## DEC-082 — DOMAIN_EXHAUSTED prospectif

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** SUPERSEDED par DEC-098  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : Taxonomy émettait `DOMAIN_EXHAUSTED(depth, domain)` après consommation. La spécification maître ne prévoit pas ce signal : Taxonomy fournit un territoire ou retourne `EMPTY`; le Tour de Depth traite alors `ON → OFF`.

---

## DEC-083 — DEPTH_EXHAUSTED prospectif

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** SUPERSEDED par DEC-098 + DEC-099  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : Taxonomy émettait `DEPTH_EXHAUSTED(depth)`. La spécification maître ferme le Tour lorsque les huit Domaines sont `OFF`; aucun signal global Taxonomy n'est requis.

---

## DEC-084 — Indépendance rotation KRP ↔ progression Taxonomy

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

Le Tour KRP ne détermine jamais le numéro de Subject ou d'Idea de Taxonomy. Les réservoirs Taxonomy progressent indépendamment.

---

## DEC-085 — Deux flux distincts avec signaux prospectifs Taxonomy

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** SUPERSEDED par DEC-098 + DEC-100  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : Taxonomy envoyait des signaux d'épuisement distincts du flux CKR. La spécification maître ne comporte pas ce canal prospectif global.

---

## DEC-086 — AVAILABLE rejeté

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** REJECTED  
**Module :** `02_KernelRotationPlanner.md`

Taxonomy ne doit pas envoyer un signal `AVAILABLE`. Cette interdiction reste cohérente avec la spécification maître.

---

## DEC-087 — Canal prospectif d'épuisement Taxonomy → KRP

**Version :** 1.1  
**Date :** 13 août 2026  
**Statut :** SUPERSEDED par DEC-098 + DEC-100  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : un canal transportait `DOMAIN_EXHAUSTED/DEPTH_EXHAUSTED` vers KRP. Ce canal n'appartient plus au contrat maître.

---

## DEC-088 — CYCLE_TARGET / cycle_completed retirés de l'autorité de changement de Depth

**Version :** 1.1  
**Date :** 13 août 2026  
**Statut :** SUPERSEDED par DEC-096 + DEC-099  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : `CYCLE_TARGET` et `cycle_completed` étaient rejetés comme mécanisme du besoin Depth. La spécification maître les rétablit explicitement.

---

## DEC-089 — SHORTFALL et états dérivés : REJECTED

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** REJECTED  
**Module :** `02_KernelRotationPlanner.md`

`SHORTFALL`, `DEPTH_TARGET_COMPLETE` et `DEPTH_RESERVOIRS_EXHAUSTED_WITH_SHORTFALL` restent rejetés : ils n'appartiennent pas à la spécification maître actuelle.

---

## DEC-090 — DepthProductionState : REJECTED

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** REJECTED  
**Module :** `02_KernelRotationPlanner.md`

`DepthProductionState` reste rejeté comme structure distincte. Le besoin est porté par `DepthNeedMatrix` et la rotation par KRP.

---

## DEC-091 — Double condition kernel_remaining + AVAILABLE : REJECTED

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** REJECTED  
**Module :** `02_KernelRotationPlanner.md`

La double condition `kernel_remaining > 0 AND reservoir_status = AVAILABLE` reste rejetée. La spécification maître utilise le Tour ON/OFF et ne comporte ni `kernel_remaining` par domaine ni signal `AVAILABLE`.

---

## DEC-092 — DEPTH_EXHAUSTED(10) → PRODUCTION_ON_HOLD terminal

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** SUPERSEDED par DEC-097 + DEC-099  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : après Depth 10, la production s'arrêtait automatiquement. La spécification maître impose de reprendre la recherche à Depth 2 et de ne passer en `PRODUCTION_ON_HOLD` que lorsqu'aucun Depth ne possède encore `cycle_completed < cycle_target`.

---

## DEC-093 — CURRENT_KERNEL_RECEIVED seul incrémenteur de kernel_received_total

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

`CURRENT_KERNEL_RECEIVED` est le seul événement qui incrémente `kernel_received_total[depth][domain]`. La création d'un Blueprint ne modifie jamais ce compteur.

---

## DEC-094 — DepthCycle non bouclant après Depth 10

**Version :** 1.0  
**Date :** 13 août 2026  
**Statut :** SUPERSEDED par DEC-097  
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision : `2 → 4 → 6 → 7 → 8 → 9 → 10`, puis arrêt. La spécification maître conserve l'ordre mais reprend la recherche à Depth 2 après Depth 10 tant qu'un besoin reste ouvert.

---

## DEC-069 — Mission officielle de QuestionIntent / KernelCodeEngine

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

KernelCodeEngine reçoit le KernelBlueprint dont le territoire intellectuel a été déterminé et validé, construit le `kernel_code` canonique, attribue son suffixe séquentiel unique, l'écrit dans le Blueprint et rend cette identité immuable. Il ne détermine aucun traitement cognitif de Phase 1.

---

## DEC-070 — kernel_code : propriétaire exclusif = KernelCodeEngine

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

KernelCodeEngine est le seul moteur autorisé à écrire `kernel_code` dans le Blueprint et dans `kernel_blueprint_runs`. KRP, Taxonomy, VDI et Phase 1 ne l'écrivent jamais.

---

## DEC-071 — Format officiel du kernel_code

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

Format : `DD-DO-SUB-SUJ-IDE-VVVV`, 22 caractères, UPPERCASE ASCII sans espace. Regex canonique : `^[0-9]{2}-[A-Z]{2}-[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}-[0-9A-Z]{4}$`.

---

## DEC-072 — Suffixe VVVV base36

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

Alphabet base36 `0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ`, capacité `36^4 = 1 679 616` par bassin, de `0000` à `ZZZZ`. Aucun UUID, hash ou tirage aléatoire.

---

## DEC-073 — Compteur indépendant par Depth + domain_code

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

`kernel_code_sequences` possède une clé composite `(depth, domain_code)` et un `next_value` atomique. La source de vérité du code attribué reste `kernel_blueprint_runs.kernel_code`.

---

## DEC-074 — Immutabilité du kernel_code

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

Transition autorisée : `NULL → valeur canonique`. Une valeur attribuée n'est jamais remplacée. Une présentation répétée du même Blueprint retourne le même `kernel_code` sans double incrément.

---

## DEC-075 — Non-recyclage des suffixes consommés

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

Un suffixe consommé n'est jamais recyclé. Après `ZZZZ`, l'allocation échoue fermée avec `QUESTION_INTENT_SUFFIX_EXHAUSTED`.

---

## DEC-076 — KernelCodeEngine : zéro responsabilité cognitive

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

KernelCodeEngine ne produit aucun contenu cognitif et n'appelle ni Phase 1, ni Quarantine, ni ReadyBank, ni les moteurs de génération de contenu.

---

## DEC-077 — KLD / KEY_STRUCTURE exclus du kernel_code

**Version :** 1.0  
**Date :** 11 août 2026  
**Statut :** OFFICIAL  
**Module :** `05_QuestionIntent.md`

KLD et KEY_STRUCTURE sont absorbés par ValidationDominantIdeas et ne font pas partie du `kernel_code`. Le stockage canonique du code est `kernel_blueprint_runs.kernel_code`.

---

# Décisions RECOVERY-02 — réalignement avec la bible intellectuelle

## DEC-095 — Autorité documentaire du module 02

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`  
**Remplace :** toute interprétation du registre incompatible avec la spécification maître

La spécification maître KernelRotationPlanner v2.0 du 28 juillet 2026, revalidée par Steve le 18 août 2026 comme **bible actuelle de la partie intellectuelle**, est l'autorité métier du module 02. Le registre doit refléter ce document ; il ne peut pas l'annuler par une décision historique contradictoire.

---

## DEC-096 — Mission complète de DepthNeedMatrix

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`  
**Remplace :** DEC-060, DEC-088

`DepthNeedMatrix` porte :

- le `DepthCycle` ;
- `cycle_target[depth]` ;
- `cycle_completed[depth]` ;
- la progression numérique du Tour actif `0/8 → 8/8` ;
- `kernel_received_total[depth][domain]`.

Elle ne porte pas l'identité des Domaines `ON/OFF` et ne choisit pas elle-même le Domaine. Elle fournit les données de besoin à KRP.

Les cibles maîtres sont :

```text
2  = 250
4  = 300
6  = 350
7  = 350
8  = 350
9  = 250
10 = 100
```

---

## DEC-097 — DepthCycle bouclant par besoin

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`  
**Remplace :** DEC-094 et la règle terminale de DEC-092

Ordre officiel :

```text
2 → 4 → 6 → 7 → 8 → 9 → 10
```

Après Depth 10, la **recherche** reprend à Depth 2. KRP sélectionne le prochain Depth dont :

```text
cycle_completed[depth] < cycle_target[depth]
```

KRP ne recommence jamais immédiatement le même Depth après la fermeture de son Tour. `PRODUCTION_ON_HOLD` n'est atteint que si aucun Depth du cycle ne possède encore de besoin.

---

## DEC-098 — Taxonomy retourne TERRITOIRE ou EMPTY ; Tour de Depth ON/OFF

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md` + `03_Taxonomy.md`  
**Remplace :** DEC-061, DEC-082, DEC-083, DEC-085, DEC-087

Pour KRP, Taxonomy produit uniquement deux résultats fonctionnels :

1. territoire fourni (`subdomain_active`, `subject_active`, `dominant_idea_active`) → le Domaine reste `ON` ;
2. `EMPTY` → transition idempotente du Domaine `ON → OFF` pour le Tour actif.

Il n'existe aucun signal `AVAILABLE`, `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED` dans le contrat maître actuel.

Le Tour de Depth contient exactement huit Domaines de création : Géographie, Histoire, Faune, Art, Sport, Cinéma, Cuisine, Science. `Général` est exclu.

Chaque nouvelle transition valide `ON → OFF` produit exactement `+1/8`. Un Domaine déjà `OFF` recevant de nouveau `EMPTY` est un NO-OP pour la progression.

---

## DEC-099 — Fermeture 8/8 et sélection du prochain Depth

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`  
**Remplace :** DEC-062, DEC-083, DEC-088, DEC-092

Lorsque les huit Domaines du Tour actif sont `OFF` :

```text
Tour = 8/8
↓
cycle_completed[active_depth] += 1
↓
DepthNeedMatrix recherche le prochain Depth où
cycle_completed < cycle_target
↓
KRP applique ce Depth comme nouvel active_depth
↓
nouveau Tour : huit Domaines ON, progression 0/8
```

Si aucun Depth ne satisfait la condition, KRP retourne `PRODUCTION_ON_HOLD`.

---

## DEC-100 — Frontière CURRENT_KERNEL_RECEIVED → nouveau Blueprint → KRP

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`  
**Complète :** DEC-063, DEC-093

Flow récurrent officiel :

```text
ReadyBank reçoit le Blueprint courant
↓
CURRENT_KERNEL_RECEIVED
↓
idempotence par blueprint_id
↓
kernel_received_total[depth][domain] += 1 exactement une fois
↓
autorisation du cycle suivant
↓
KernelBlueprintFactory crée le nouveau Blueprint
↓
KernelRotationPlanner reçoit ce nouveau Blueprint
↓
KRP consulte DepthNeedMatrix + Tour de Depth
↓
KRP écrit depth + domain
```

`CURRENT_KERNEL_RECEIVED` ne sert jamais à réécrire le Blueprint déjà reçu par ReadyBank. Le bookkeeping CKR doit être conservé même si le déclenchement du Blueprint suivant est déplacé vers une frontière d'orchestration dédiée.

---

## DEC-101 — Rotation provisoire avant engagement Taxonomy

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `01_KernelBlueprint.md` + `02_KernelRotationPlanner.md`  
**Remplace :** DEC-034 pour `depth/domain`, DEC-066

Un Blueprint `CREATED_UNENGAGED` peut recevoir plusieurs propositions successives `depth + domain` pendant la recherche d'un territoire exploitable.

Sur `EMPTY` :

```text
même Blueprint conservé
↓
Domaine courant ON → OFF
↓
KRP sélectionne le prochain Domaine ON
↓
KRP remplace le couple provisoire depth + domain
↓
Taxonomy est consultée de nouveau
```

Aucun nouveau Blueprint n'est créé à cause d'un `EMPTY`.

Lorsque Taxonomy fournit ses trois slots et que le Blueprint passe `ENGAGED_IN_PIPELINE`, le couple `depth + domain` devient définitif et immuable.

L'écriture directe externe des slots reste interdite ; cette décision crée uniquement l'exception propriétaire contrôlée nécessaire à KRP avant engagement.

---

## DEC-102 — Sélection de Domaine par previous_domain, sans curseur numérique

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`

KRP parcourt le DomainCycle à partir de `previous_domain`, ignore les Domaines `OFF` et sélectionne le prochain Domaine `ON`.

Le contrat maître n'utilise pas :

```text
domain_position
current_domain_index
depth_position
```

Le `previous_domain` provient du Domaine provisoire précédent lors d'un `EMPTY`, du Domaine porté par `CURRENT_KERNEL_RECEIVED` pour le Blueprint suivant, ou de `null` au premier lancement d'un Tour.

---

## DEC-103 — Frontière d'écriture de KRP

**Version :** 1.0  
**Date :** 18 août 2026  
**Statut :** OFFICIAL  
**Module :** `02_KernelRotationPlanner.md`  
**Complète :** DEC-058, DEC-068

KRP décide exclusivement du prochain couple `depth + domain` et n'écrit que ces deux champs dans le Blueprint.

KRP n'écrit jamais :

- `blueprint_id` ;
- `subdomain_active` ;
- `subject_active` ;
- `dominant_idea_active` ;
- `kernel_code` ;
- `rotation_identifier`.

La Factory possède `blueprint_id`, Taxonomy possède les trois slots de territoire, KernelCodeEngine possède `kernel_code`, et `rotation_identifier` reste supprimé.
