# StrategyBuzzer — IMPLEMENTATION STATE

## RECOVERY-01 — Point de reprise vérifié

**Date de récupération :** 17 août 2026  
**Dépôt :** `SteveStrategyBuzzer/StrategyBuzzer`  
**Branche auditée :** `replit/intellectual-engine-current-2026-08-16`  
**Baseline auditée :** `db26047532cfdf5e030c348dba4455f8eb310971`  
**Portée :** `01_KernelBlueprint`, `02_KernelRotationPlanner`, frontière vers `03_Taxonomy`  
**Nouvelle logique moteur introduite pendant RECOVERY-01 :** AUCUNE

---

# 1. Règle d'autorité utilisée pour la reprise

Pour éviter toute reconstruction à partir d'un code ancien ou d'une mémoire périmée, l'ordre d'autorité est :

1. `docs/architecture/00_ArchitectureRegister.md` — décisions `OFFICIAL` non superseded ;
2. dernière spécification verrouillée du module, lorsqu'elle ne contredit pas le Register ;
3. code réellement présent au SHA audité ;
4. tests réellement présents au SHA audité ;
5. `.agents/memory/*` et `attached_assets/*` uniquement comme preuves historiques / handoff, jamais comme source autonome de contrat.

Règle de travail conservée : **la spécification pilote le code, jamais l'inverse**.

---

# 2. État documentaire constaté

## 2.1 Architecture Register

Le Register est explicitement la source centralisée des décisions officielles.

Décisions actives déterminantes pour la reprise KRP :

- `DEC-034` — KernelBlueprint write-once ;
- `DEC-058` — `KernelBlueprintFactory` crée le Blueprint avant KRP ;
- `DEC-059` — `blueprint_id` canonique, `rotation_identifier` supprimé ;
- `DEC-060` — `kernel_received_total` = traçabilité ; `cycle_target/cycle_completed` ne pilotent plus le changement de Depth ;
- `DEC-063` — `CURRENT_KERNEL_RECEIVED` = déclencheur de la prochaine rotation ;
- `DEC-067` — états techniques du Blueprint ;
- `DEC-068` — KRP n'écrit jamais `kernel_code` ;
- `DEC-082` — `DOMAIN_EXHAUSTED(depth, domain)` prospectif, produit par Taxonomy ;
- `DEC-083` — `DEPTH_EXHAUSTED(depth)` prospectif, produit par Taxonomy ;
- `DEC-092` — `DEPTH_EXHAUSTED(10)` mène à `PRODUCTION_ON_HOLD` ;
- `DEC-093` — `CURRENT_KERNEL_RECEIVED` est le seul incrémenteur de `kernel_received_total` ;
- `DEC-094` — DepthCycle officiel : `2 → 4 → 6 → 7 → 8 → 9 → 10`, sans retour automatique vers 2.

## 2.2 `01_KernelBlueprint.md`

- Version : `1.5`.
- Statut documentaire : `VERROUILLÉ`.
- Le document déclare le module `IMPLÉMENTÉ — CORRECTION TERMINALE`.
- Une section héritée affirme encore que KRP « crée le KernelBlueprint canonique ».
- Cette phrase est **superseded** par `DEC-058` et par `02_KernelRotationPlanner.md v3.2` : la Factory crée le Blueprint avant KRP.

Cette incohérence est documentaire. Elle ne doit pas provoquer un retour à l'ancienne architecture.

## 2.3 `02_KernelRotationPlanner.md`

- Version : `3.2`.
- Date : `13 août 2026`.
- Statut : `VERROUILLÉ`.
- Le contrat impose : `KernelBlueprintFactory → Blueprint vide → KRP → fillRotation(depth, domain)`.
- Le tableau d'en-tête indique encore `Implémentation 0 % / Validation 0 %`.
- Ce tableau est **périmé** par rapport au code, aux tests et aux validations PostgreSQL réalisés ensuite le 14 août 2026.

Aucune architecture ne doit être régressée pour faire correspondre le code à ce tableau historique.

---

# 3. `01_KernelBlueprint` — état d'implantation constaté

Fichier principal :

`app/Services/QuestionBank/KernelBlueprint.php`

Implanté au SHA audité :

- `blueprint_id` privé, initialisé par `KernelBlueprintFactory` ;
- `depth` + `domain` privés, écrits par `fillRotation()` ;
- `subdomain_active` + `subject_active` + `dominant_idea_active`, écrits par `fillTaxonomy()` ;
- `kernel_code`, écrit par `fillKernelCode()` ;
- lecture publique via `__get()` ;
- écriture directe externe bloquée via `__set()` ;
- write-once par groupe de slots ;
- helpers `isRotationFilled()`, `isTaxonomyFilled()`, `isIdentityComplete()`, `isComplete()` ;
- `toArray()` pour les six champs de la Partie 1.

Le fichier précise lui-même :

`Parties 2–6 : non encore implémentées — attendues ultérieurement.`

Donc le constat de reprise est :

**Partie 1 / identité canonique : IMPLÉMENTÉE.**  
**Le Blueprint complet futur au-delà de cette partie : non déclaré terminé par le code actuel.**

Tests identifiés au SHA audité :

- `tests/Unit/QuestionBank/KernelBlueprintPart1Test.php` ;
- tests Factory / Orchestrator / KernelCodeEngine utilisant le Blueprint réel.

RECOVERY-01 n'a pas relancé PHPUnit ; aucun résultat courant n'est inventé.

---

# 4. `02_KernelRotationPlanner` — état d'implantation constaté

Fichiers principaux vérifiés :

- `KernelRotationPlanner.php` ;
- `KernelBlueprintFactory.php` ;
- `KernelPipelineOrchestrator.php` ;
- `ProcessKernelPipelineOutbox.php` ;
- `DepthNeedMatrix.php` ;
- repositories / événements / migrations associés.

## 4.1 Rotation réellement implantée

`KernelRotationPlanner.php` possède une table de transition figée :

```text
2  → 4
4  → 6
6  → 7
7  → 8
8  → 9
9  → 10
10 → null = PRODUCTION_ON_HOLD
```

La transition de Depth ne repose plus sur `nextRequiredDepth()` ni sur `cycle_completed`.

Le KRP :

- sélectionne le prochain domaine actif ;
- ignore les domaines `DOMAIN_EXHAUSTED` ;
- reçoit `DOMAIN_EXHAUSTED` de façon idempotente ;
- reçoit `DEPTH_EXHAUSTED` de façon idempotente ;
- mémorise le Depth épuisé comme transition pending ;
- applique cette transition au `CURRENT_KERNEL_RECEIVED` correspondant ;
- écrit `depth + domain` une seule fois via `fillRotation()` ;
- ne produit pas lui-même les signaux d'épuisement Taxonomy.

## 4.2 CURRENT_KERNEL_RECEIVED réellement implanté

Chemin canonique constaté :

```text
ReadyBank / Outbox CURRENT_KERNEL_RECEIVED
↓
ProcessKernelPipelineOutbox
↓
KernelRotationPlanner::receiveKernelReceivedV2()
↓
receipt idempotent
↓
kernel_received_total +1 exactement une fois
↓
transition Depth pending éventuelle
↓
KernelPipelineOrchestrator::run()
↓
Blueprint suivant
```

`ApplyCurrentKernelReceivedToRotation::applyCount()` est désactivé du chemin canonique.

## 4.3 `DepthNeedMatrix` — dette / surface legacy à ne pas confondre avec l'autorité KRP

Le fichier contient encore :

- `CYCLE_TARGET` ;
- `nextRequiredDepth()` ;
- commentaires historiques de wrap `10 → 2` ;
- `incrementCycleCompleted()`.

Mais le KRP corrigé n'utilise plus ces mécanismes comme autorité de transition de Depth.

Ils doivent être traités comme **surface legacy / traçabilité à auditer ultérieurement**, pas comme le contrat actif de rotation.

RECOVERY-01 ne les supprime pas et ne les réactive pas.

---

# 5. Validation KRP retrouvée

Tests unitaires présents au SHA audité notamment :

- `KernelRotationPlannerV3Test.php` ;
- `KernelPipelineOrchestratorTest.php` ;
- `ProcessKernelPipelineOutboxTest.php` ;
- `KernelBlueprintFactoryTest.php` ;
- `KernelMigrationSchemaTest.php` ;
- `DepthNeedMatrixTest.php` ;
- `DepthTourStateTest.php`.

Tests PostgreSQL réels présents :

- `tests/Integration/QuestionBank/Rotation/KernelRotationPlannerPostgresTest.php` ;
- `tests/Integration/QuestionBank/Rotation/KernelRotationPlannerPostgresStrictTest.php`.

Preuve historique enregistrée le 14 août 2026 :

- série #159 : `9/9 PASS` sur PostgreSQL / Neon ;
- série #159B stricte : `6/6 PASS` ;
- total documenté : `15/15 PASS`.

Ces tests couvrent notamment :

- `FOR UPDATE` réel ;
- concurrence ;
- single-active Blueprint ;
- idempotence CKR ;
- `4 → 6` ;
- `10 → PRODUCTION_ON_HOLD` sans pilotage par matrix ;
- idempotence `DOMAIN_EXHAUSTED` ;
- idempotence `DEPTH_EXHAUSTED` ;
- rollback ;
- preuve de `IMPASSE-KRP-001`.

**Limite RECOVERY-01 :** les tests n'ont pas été réexécutés pendant cette récupération. Aucun workflow GitHub Actions n'est associé au commit baseline. Le dernier résultat exécutable prouvé retrouvé est donc le résultat historique du 14 août.

---

# 6. IMPASSE-KRP-001 — toujours ouverte, volontairement

Type : **FRONTIÈRE INTER-MODULE KRP → Taxonomy**.

Cas :

```text
Factory crée Blueprint CREATED_UNENGAGED
↓
KRP commit sa transaction correctement
↓
Taxonomy est appelée hors transaction
↓
Taxonomy lève une exception
↓
Blueprint CREATED_UNENGAGED reste durable
↓
single-active guard peut bloquer le cycle suivant
```

La preuve existe dans :

`KernelPipelineOrchestratorTest::test_exception_after_transaction_leaves_blueprint_created_unengaged()`.

Décision de récupération :

- ne pas inventer auto-recovery ;
- ne pas inventer cleanup métier ;
- ne pas ajouter état / timeout / retry / signal ;
- ne pas contourner le single-active guard ;
- résoudre seulement après définition du contrat `03_Taxonomy` pour cette frontière.

`peekNext() == null` n'est pas ce cas : le CONTAINMENT temporaire existant nettoie ce Blueprint sans inférer `DOMAIN_EXHAUSTED`. L'impasse concerne une **exception Taxonomy après COMMIT**.

---

# 7. Handoff réel retrouvé dans l'historique

Un artefact ajouté le 14 août 2026 établit le point de passage suivant :

```text
01_KernelBlueprint
✅ implanté pour la frontière courante

02_KernelRotationPlanner
✅ implanté
validation terminale globale / inter-module reportée

↓
03_Taxonomy
PHASE DE SPÉCIFICATION
```

Les commits suivants jusqu'à `db260475` ajoutent principalement des notes et extractions de recherche pour Taxonomy.

Le commit baseline `db260475` demande explicitement d'extraire le code Taxonomy existant **sans le considérer comme source de vérité métier**, afin de pouvoir ensuite classer :

```text
CONSERVER
MODIFIER
SUPPRIMER
AJOUTER
```

Aucune preuve n'indique qu'une nouvelle architecture Taxonomy devait être implantée avant son verrouillage.

---

# 8. État de reprise officiel après RECOVERY-01

| Brique | Contrat | Code | Tests disponibles | Validation retrouvée | Statut de reprise |
|---|---|---|---|---|---|
| Architecture Register | source centrale | n/a | n/a | n/a | AUTORITÉ |
| 01 KernelBlueprint — Partie 1 | verrouillé | implanté | présents | historique | REPRIS |
| 02 KernelRotationPlanner | v3.2 verrouillé | implanté | unit + PostgreSQL | 15/15 PG documentés le 14 août | REPRIS — frontière Taxonomy ouverte |
| IMPASSE-KRP-001 | non résolue par design | aucune solution inventée | preuve présente | prouvée | OUVERTE — dépend de 03 |
| 03 Taxonomy | à spécifier complètement | code existant = matière d'audit seulement | anciens tests présents | non retenus comme contrat | PROCHAINE BRIQUE |

---

# 9. Prochaine séquence autorisée

```text
03_Taxonomy
↓
reconstruction documentaire depuis Constitution + Architecture Register
↓
spécification complète des états et lifecycles
↓
résolution explicite de la frontière IMPASSE-KRP-001
↓
décisions Architecture Register
↓
VERROUILLAGE 03_Taxonomy
↓
seulement ensuite : audit du code Taxonomy existant
↓
CONSERVER / MODIFIER / SUPPRIMER / AJOUTER
↓
implantation
↓
validation terminale
```

Points Taxonomy devant être spécifiés avant implantation :

- bassin `Depth + Domain` ;
- cycle Subdomain ;
- SubjectBank et lifecycle SubjectSlot ;
- IdeaBank et lifecycle IdeaSlot ;
- identité permanente du SubjectSlot / IdeaSlot ;
- contrat avec `04_ValidationDominantIdeas` sans absorber ses règles ;
- readiness d'un sujet ;
- sélection exacte du territoire ;
- moment d'écriture `fillTaxonomy()` ;
- moment exact de consommation ;
- conservation de l'identité exacte entre sélection et consommation ;
- reprise du state persistant ;
- émission `DOMAIN_EXHAUSTED` ;
- émission `DEPTH_EXHAUSTED` ;
- comportement après Blueprint valide si Taxonomy échoue — `IMPASSE-KRP-001`.

---

# 10. Interdictions de reprise

À partir de ce checkpoint, ne pas :

- reconstruire KRP depuis une ancienne mémoire ;
- réintroduire Depth 1 ;
- réintroduire un wrap automatique `10 → 2` ;
- utiliser `cycle_target/cycle_completed` comme autorité KRP ;
- réintroduire `Général` comme domaine de création ;
- faire produire `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED` par KRP ;
- corriger Taxonomy en prenant son code actuel comme source de vérité ;
- résoudre `IMPASSE-KRP-001` par invention avant le contrat 03 ;
- modifier une brique suivante pour masquer une incohérence de la précédente.

---

# 11. Verdict RECOVERY-01

```text
SPEC AUDIT           ✅ TERMINÉ
CODE AUDIT           ✅ TERMINÉ pour la frontière 01 → 02 → 03
TEST INVENTORY       ✅ TERMINÉ
TESTS RERUN          ⚪ NON EXÉCUTÉS pendant Recovery
POINT DE REPRISE     ✅ ÉTABLI
NOUVELLE LOGIQUE     ✅ AUCUNE

PROCHAINE ACTION
→ reprendre 03_Taxonomy en SPÉCIFICATION, pas en implantation.
```
