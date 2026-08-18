# StrategyBuzzer — IMPLEMENTATION STATE

## État canonique après RECOVERY-02

**Date :** 18 août 2026  
**Dépôt :** `SteveStrategyBuzzer/StrategyBuzzer`  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**HEAD audité avant ce checkpoint :** `e4b853159f7cd9548cadddcfc2057dab784f4aa5`  
**Baseline moteur/test actuellement restaurée :** `db26047532cfdf5e030c348dba4455f8eb310971`  
**Bloc :** `RECOVERY-02 — reconstruction chirurgicale de l’état d’implantation`

> Ce checkpoint documente un audit. **Aucun fichier moteur ni test n’a été modifié pendant RECOVERY-02.**

---

# 1. REPOSITORY CHECKPOINT

```text
Repository remote        SteveStrategyBuzzer/StrategyBuzzer
Branch                   replit/intellectual-engine-current-2026-08-16
HEAD audit base           e4b853159f7cd9548cadddcfc2057dab784f4aa5
Active module             02_KernelRotationPlanner
Current block             RECOVERY-02
Code modification         NONE
Tests executed            NONE
```

Le connecteur GitHub permet de vérifier l’état **committé** de la branche distante. Il ne permet pas d’affirmer l’état du working tree local Replit ; aucun `working tree clean` n’est donc inventé ici.

Compare Git vérifié :

```text
052eef096ed7ef539f27ca9d84e0c50f4e88799b → e4b853159f7cd9548cadddcfc2057dab784f4aa5
= 2 commits documentaires
= 02_KernelRotationPlanner_v3.3_ALIGNMENT.md ajouté
= IMPLEMENTATION_STATE.md modifié
= aucun fichier moteur/test modifié
```

Compare :

```text
db26047532cfdf5e030c348dba4455f8eb310971 → e4b853159f7cd9548cadddcfc2057dab784f4aa5
```

confirme que les fichiers moteur/test touchés par la série v3.3 ont été restaurés à la baseline `db260475`. Seuls les documents de reprise diffèrent au HEAD audité.

---

# 2. RÈGLE DE RECOVERY-02

RECOVERY-02 ne décide pas qu’une ancienne version est vraie parce qu’elle est ancienne, et ne décide pas qu’une version récente est fausse parce qu’elle est récente.

Chaque mécanisme de la série v3.3 est classé :

```text
KEEP        conforme / élément à préserver
MODIFY      bonne intention mais composition ou détail incorrect
REMOVE      contradiction démontrée
UNRESOLVED  conflit documentaire ou décision manquante
```

Les tests historiques sont des **preuves de comportement d’un SHA**, jamais une source architecturale autonome.

---

# 3. CONFLIT DOCUMENTAIRE / ARCHITECTURAL DÉTECTÉ — DEPTH NEED MATRIX

## SOURCE A — Architecture Register / KRP v3.2

Les décisions actuellement marquées OFFICIAL indiquent notamment :

- `DEC-060` / `DEC-088` : `cycle_target` et `cycle_completed` ne pilotent plus le changement de Depth ;
- `DEC-083` : `DEPTH_EXHAUSTED` Taxonomy pilote le passage au Depth suivant ;
- `DEC-092` : `DEPTH_EXHAUSTED(10)` mène à `PRODUCTION_ON_HOLD` ;
- `DEC-094` : pas de retour automatique `10 → 2`.

Le document `02_KernelRotationPlanner.md v3.2` contient la même position.

## SOURCE B — Code actuel au HEAD restauré

`app/Services/QuestionBank/Rotation/DepthNeedMatrix.php` contient encore, à la baseline `db260475` elle-même :

```text
DEPTH_CYCLE = 2 → 4 → 6 → 7 → 8 → 9 → 10
CYCLE_TARGET[depth]
cycle_completed[depth]
nextRequiredDepth(afterDepth)
```

`nextRequiredDepth()` :

- cherche cycliquement le prochain Depth dont `cycle_completed < cycle_target` ;
- après Depth 10, revient vers Depth 2 si un besoin reste ouvert ;
- retourne `null` uniquement si toutes les cibles sont satisfaites.

Cette logique **n’a pas été introduite par la série v3.3** et **n’a pas été retirée par RESTORE-02**.

## SOURCE C — Série récente v3.3

Le commit `d482821b` reconnectait KRP à cette logique existante :

```text
DEPTH_EXHAUSTED valide
→ incrementCycleCompleted(depth)
→ nextRequiredDepth(depth)
→ 10 → 2 si besoin restant
→ PRODUCTION_ON_HOLD seulement si aucun besoin global ne reste
```

Le commit de tests `0d9cc046` codifiait explicitement :

- saut des Depths dont la cible est satisfaite ;
- `Depth 10 → Depth 2` si besoin global restant ;
- `PRODUCTION_ON_HOLD` seulement lorsque toutes les cibles sont satisfaites.

## SOURCE D — Confirmation de Steve pour RECOVERY-02

Steve confirme comme contrainte de l’audit que `DepthNeedMatrix` mémorise et suit les besoins de production par Depth, que `cycle_target/cycle_completed` font partie du mécanisme de besoin et que la rotation doit pouvoir poursuivre `10 → 2` lorsque des besoins restent ouverts.

## CONSÉQUENCE

```text
CONFLIT ARCHITECTURAL OUVERT — REC02-C01
```

Il est interdit :

- de supprimer `cycle_target/cycle_completed` ;
- de restaurer aveuglément KRP v3.2 comme contrat définitif ;
- de réappliquer aveuglément `d482821b` ;
- d’implanter avant réconciliation complète de la spécification et de l’Architecture Register.

Verdict de récupération :

```text
DepthNeedMatrix besoin global          KEEP comme mécanisme à préserver
cycle_target / cycle_completed         KEEP comme données/mécanisme à préserver
usage exact comme autorité KRP         UNRESOLVED jusqu’à réécriture contractuelle
10 → 2 si besoin restant               KEEP comme contrainte confirmée à réconcilier
```

---

# 4. CONFLIT DOCUMENTAIRE / ARCHITECTURAL DÉTECTÉ — CKR → BLUEPRINT → KRP

## Frontière confirmée pour l’audit

```text
CURRENT_KERNEL_RECEIVED
↓
autorise / déclenche le cycle suivant
↓
KernelBlueprintFactory
↓
nouveau KernelBlueprint canonique
↓
KernelRotationPlanner
↓
consultation des besoins de rotation
↓
fillRotation(depth, domain)
↓
FIN MODULE 02
```

CKR ne doit pas être conceptualisé comme une commande directe appliquant la rotation au Blueprint précédent.

## Code actuel restauré

`ProcessKernelPipelineOutbox.php` fait actuellement :

```text
CURRENT_KERNEL_RECEIVED
↓
planner->receiveKernelReceivedV2(...)
↓
orchestrator->run()
↓
Factory / suite
```

Le call direct KRP sert à :

- idempotence du reçu ;
- `kernel_received_total +1` ;
- transition Depth pending dans la baseline v3.2.

Le code ne réécrit pas le vieux Blueprint, mais la frontière d’appel reste différente de la frontière confirmée.

## Série récente

`2bb88895` a correctement déplacé la destination fonctionnelle de CKR vers la frontière de création du Blueprint suivant et supprimé l’appel direct CKR → KRP.

Mais ce même changement supprimait du chemin actif :

- le receipt CKR ;
- l’incrément `kernel_received_total`.

Il ne peut donc pas être repris tel quel.

`bb87f8a2` retirait correctement Taxonomy / KernelCodeEngine du wiring de la commande Outbox Module 02.

`900ffa95` retirait correctement Taxonomy / KernelCodeEngine du raccord Module 02 et retournait un Blueprint avec uniquement `blueprint_id + depth + domain`.

Cependant son ordre réel était encore :

```text
KRP.resolveNextRotation(state)
↓
Factory.create()
↓
KRP.applyRotation(...)
```

et non strictement :

```text
Factory.create()
↓
KRP consulte / décide / écrit
```

Verdict :

```text
CKR → nouveau Blueprint → KRP                  KEEP
suppression Taxonomy/KCE du Module 02         KEEP
suppression du reçu/compteur CKR              REMOVE
ordre KRP.resolve avant Factory dans 900ffa95 MODIFY
propriétaire exact du bookkeeping CKR         UNRESOLVED
```

---

# 5. MATRICE RECOVERY-02

| MÉCANISME | DOCUMENT ACTUEL | HEAD ACTUEL | SÉRIE v3.3 | VERDICT RECOVERY-02 |
|---|---|---|---|---|
| CKR → nouveau Blueprint | présent de façon partielle/tendue dans v3.2 ; confirmation Steve plus stricte | CKR appelle KRP bookkeeping avant Orchestrator | `2bb88895` route CKR vers création | **KEEP + MODIFY** |
| KernelBlueprintFactory | DEC-058 : Factory avant KRP | crée Blueprint vide, aucun slot métier | conservée | **KEEP** |
| Appel KRP | KRP reçoit Blueprint créé et écrit depth+domain | Orchestrator crée Blueprint mais attend Taxonomy avant `applyRotation()` | `900ffa95` écrit immédiatement mais `resolveNextRotation()` reste avant Factory | **MODIFY** |
| Frontière Module 02 / Taxonomy | KRP s’arrête après `fillRotation()` | Orchestrator actuel appelle Taxonomy + KernelCodeEngine | `900ffa95`, `15529c09`, `bb87f8a2` retirent ces dépendances | **KEEP des retraits / MODIFY HEAD** |
| DepthNeedMatrix | documents réduisent son autorité | possède encore vraie logique de besoin cyclique | `d482821b` la reconnecte à KRP | **KEEP mécanisme / UNRESOLVED autorité exacte** |
| cycle_target | documents : non autorité | présent avec cibles par Depth | utilisé par v3.3 | **KEEP donnée / UNRESOLVED contrat** |
| cycle_completed | documents : non autorité | présent, incrémentable | incrémenté à fin de tour v3.3 | **KEEP donnée / UNRESOLVED moment exact** |
| Depth 10 → Depth 2 | DEC-092/094 : interdit | `DepthNeedMatrix::nextRequiredDepth()` le permet | v3.3 le réactive | **CONFLIT — contrainte Steve à préserver, spec à réécrire** |
| DEPTH_EXHAUSTED | v3.2 = épuisement intellectuel définitif du Depth | KRP pending puis transition terminale statique | v3.3 = fermeture d’un tour + besoin Matrix | **UNRESOLVED** |
| DOMAIN_EXHAUSTED | v3.2 = bassin Depth+Domain épuisé prospectivement | état `DOMAIN_EXHAUSTED` durable | v3.3 introduit VISIBLE/ESTOMPÉ | **UNRESOLVED pour réouverture inter-cycle ; ne pas adopter ESTOMPÉ sans contrat** |
| kernel_received_total | DEC-093 : CKR seul incrémenteur | actif dans `receiveKernelReceivedV2()` | préservé dans `d482821b`, mais chemin retiré par `2bb88895` | **KEEP obligatoire** |
| PRODUCTION_ON_HOLD | v3.2 = après terminal Depth 10 | KRP statique 10→null | v3.3 = seulement quand `nextRequiredDepth()` retourne null | **CONFLIT — sémantique à réécrire avant code** |

---

# 6. CLASSIFICATION CHIRURGICALE DE LA SÉRIE v3.3

## `d482821b` — Align KernelRotationPlanner with active v3.3 rotation contract

```text
KEEP
- reconnexion à DepthNeedMatrix
- nextRequiredDepth()
- cycle_completed comme progression de besoin
- possibilité de poursuivre après Depth 10 si besoin restant
- kernel_received_total conservé

MODIFY / UNRESOLVED
- sens exact de DEPTH_EXHAUSTED
- VISIBLE / ESTOMPÉ
- timing exact des transitions

REMOVE / NE PAS RÉINTRODUIRE SANS DÉCISION
- références DEC-108 / DEC-111 inexistantes
- états BLOCKED / AWAITING_DEPTH_EXHAUSTED comme architecture officielle implicite
- retries techniques présentés comme contrat architectural
```

## `900ffa95` — Stop module 02 orchestrator at depth and domain assignment

```text
KEEP
- suppression Taxonomy du raccord Module 02
- suppression KernelCodeEngine du raccord Module 02
- sortie Blueprint avec taxonomy slots et kernel_code NULL
- FIN Module 02 après depth + domain

MODIFY
- KRP.resolveNextRotation() est encore appelé avant Factory.create()
- statuts dépendants de décisions non verrouillées
```

## `15529c09` — Keep rotate command inside module 02 boundary

```text
KEEP
- retire Taxonomy / ValidationDominantIdeas / KernelCodeEngine de la commande Module 02

MODIFY
- messages / statuts dépendants des états v3.3 non verrouillés
```

## `2bb88895` — Route CURRENT_KERNEL_RECEIVED through Blueprint creation boundary

```text
KEEP
- CKR ne commande plus directement KRP
- CKR ouvre la frontière du Blueprint suivant

MODIFY
- réintroduire la comptabilisation idempotente CKR sans casser la frontière

REMOVE
- perte effective du chemin kernel_received_total / receipt si ce commit est repris seul
```

## `bb87f8a2` — Align outbox command with Blueprint creation boundary

```text
KEEP
- wiring Module 02 sans Taxonomy ni KernelCodeEngine
- formulation CKR → Blueprint → KRP

MODIFY
- dépend du traitement CKR incomplet de 2bb88895
```

## `a2f36539` — Rewrite module 02 orchestrator tests for v3.3 boundary

```text
KEEP COMME INTENTION DE TEST
- Blueprint contient depth+domain seulement
- Taxonomy slots NULL
- kernel_code NULL
- Blueprint reste CREATED_UNENGAGED à la sortie Module 02

NE PAS PRENDRE COMME SOURCE D’ARCHITECTURE
- tests écrits contre la composition v3.3 entière
```

## `203e2151` — Keep CKR replayable while KRP cannot start next Blueprint

```text
KEEP COMME PROPRIÉTÉ TECHNIQUE À ÉVALUER
- un événement non traité ne doit pas être perdu sur échec

UNRESOLVED
- BLOCKED / AWAITING_DEPTH_EXHAUSTED ne sont pas des états architecturaux verrouillés
```

## `0d9cc046` — Rewrite KernelRotationPlanner tests for active v3.3 contract

```text
KEEP COMME PREUVE HISTORIQUE DU MÉCANISME RECHERCHÉ
- skip Depth cible satisfaite
- 10 → 2 si besoin global restant
- PRODUCTION_ON_HOLD seulement après satisfaction de toutes les cibles
- CKR legacy conserve kernel_received_total mais ne change plus active_depth

NE PAS DÉCLARER CES TESTS VALIDES AU HEAD
- ils ont été restaurés/retirés
- aucun test nouveau exécuté pendant RECOVERY-02
```

## `6b82bcf2` — Rewrite CKR boundary tests for module 02 v3.3

```text
KEEP COMME INTENTION
- CKR → nouveau Blueprint → Module 02
- Blueprint sort CREATED_UNENGAGED avec depth + domain

MODIFY
- cette réécriture retire les assertions historiques receipt/kernel_received_total
- la frontière correcte ne doit pas supprimer le bookkeeping CKR
```

---

# 7. ÉTAT ACTUEL DES FICHIERS CLÉS

```text
app/Services/QuestionBank/Rotation/DepthNeedMatrix.php
    KEEP — logique de besoins présente dans la baseline restaurée.

app/Services/QuestionBank/Rotation/KernelBlueprintFactory.php
    KEEP — Factory vide, identité canonique, aucune décision rotation.

app/Services/QuestionBank/Rotation/KernelRotationPlanner.php
    MODIFY APRÈS RÉSOLUTION DOCUMENTAIRE — baseline v3.2 ignore nextRequiredDepth et termine à 10.

app/Services/QuestionBank/Rotation/KernelPipelineOrchestrator.php
    MODIFY APRÈS RÉSOLUTION DOCUMENTAIRE — dépasse actuellement la frontière Module 02 vers Taxonomy/KCE.

app/Services/QuestionBank/Rotation/ProcessKernelPipelineOutbox.php
    MODIFY APRÈS RÉSOLUTION DOCUMENTAIRE — CKR appelle directement KRP avant la création du prochain Blueprint.

app/Console/Commands/QuestionsKernelRotateCommand.php
    MODIFY APRÈS CONTRAT — wiring actuel dépasse Module 02.

app/Console/Commands/QuestionsKernelProcessOutboxCommand.php
    MODIFY APRÈS CONTRAT — wiring actuel dépasse Module 02.
```

Aucune de ces modifications n’est autorisée pendant RECOVERY-02.

---

# 8. VALIDATION / TESTS

```text
Tests exécutés pendant RECOVERY-02 : 0
GitHub status checks sur HEAD audité : aucun check publié
```

Les `15/15 PASS` PostgreSQL retrouvés pendant RECOVERY-01 restent une preuve historique de la baseline v3.2 au SHA où ils ont été exécutés. Ils ne valident pas la future réconciliation.

Les tests de la série v3.3 ont été inspectés comme historique, notamment `0d9cc046` et `6b82bcf2`. Ils ne sont pas déclarés verts au HEAD actuel.

---

# 9. STATUT MODULE 02 APRÈS RECOVERY-02

```text
02_KernelRotationPlanner

SPEC
⚠️ Le fichier v3.2 est encore marqué VERROUILLÉ,
   mais RECOVERY-02 démontre un conflit avec DepthNeedMatrix historique
   et avec les contraintes explicitement confirmées par Steve.
   Une nouvelle version complète est obligatoire.

IMPL
🟡 PARTIAL / RESTORED BASELINE
   Le code actuel est la baseline db260475 pour le périmètre restauré.
   Des morceaux conformes/récupérables existent dans l’historique v3.3.

VALID
🟡 PARTIAL / HISTORICAL ONLY
   Aucun nouveau test exécuté pendant RECOVERY-02.

FINI
❌ PAS FINI
```

---

# 10. RECOVERY-02 — STATUS

```text
BLOCK: RECOVERY-02
STATUS: ✅ COMPLETE — AUDIT ONLY

Contract sources inspected:
- docs/architecture/IMPLEMENTATION_STATE.md
- docs/architecture/00_ArchitectureRegister.md
- docs/architecture/02_KernelRotationPlanner.md v3.2
- docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
- DepthNeedMatrix.php
- KernelBlueprintFactory.php
- KernelRotationPlanner.php
- KernelPipelineOrchestrator.php
- ProcessKernelPipelineOutbox.php
- série v3.3 d482821b → 6b82bcf2

Code modified: NO
Tests modified: NO
Tests executed: NO
Diff verified: audit compare GitHub only
```

---

# 11. NEXT EXACT BLOCK

```text
BLOCK:
RESOLVE-02 — Réconciliation contractuelle complète KRP / DepthNeedMatrix / CKR

GOAL:
Réécrire intégralement la prochaine version de 02_KernelRotationPlanner
à partir des conflits prouvés par RECOVERY-02, puis mettre à jour
l’Architecture Register sans effacer les décisions remplacées.

CONFLICTS À RÉSOUDRE AVANT TOUT CODE:
1. autorité exacte de DepthNeedMatrix ;
2. rôle exact de cycle_target ;
3. moment exact d’incrément de cycle_completed ;
4. sens exact de DEPTH_EXHAUSTED ;
5. comportement DOMAIN_EXHAUSTED lors d’un retour futur sur un Depth ;
6. 10 → 2 lorsque des besoins restent ouverts ;
7. condition exacte de PRODUCTION_ON_HOLD ;
8. emplacement du bookkeeping CKR sans casser CKR → nouveau Blueprint → KRP ;
9. ordre littéral Factory → Blueprint → KRP sans appel KRP pré-Factory ;
10. séparation définitive Module 02 / orchestration globale / Taxonomy.

FILES ALLOWED:
- docs/architecture/02_KernelRotationPlanner.md ou sa prochaine version officielle
- docs/architecture/00_ArchitectureRegister.md
- docs/architecture/IMPLEMENTATION_STATE.md

FILES FORBIDDEN:
- app/**
- tests/**
- Taxonomy implementation

ARCHITECTURAL DECISION REQUIRED:
YES

CODE:
STOP jusqu’au verrouillage de RESOLVE-02.
```

---

# 12. DO NOT REDO

Ne pas :

- refaire RECOVERY-01 / RESTORE-02 ;
- restaurer toute la série v3.3 ;
- rejeter toute la série v3.3 ;
- modifier `DepthNeedMatrix` avant contrat ;
- supprimer `cycle_target/cycle_completed` ;
- remettre `10 → PRODUCTION_ON_HOLD` comme vérité définitive sans résoudre REC02-C01 ;
- envoyer CKR directement à KRP par simple conservation de la baseline ;
- perdre `kernel_received_total` en récupérant `2bb88895` ;
- laisser Module 02 appeler Taxonomy ou KernelCodeEngine ;
- commencer `03_Taxonomy` ;
- commencer une implantation corrective avant `RESOLVE-02` verrouillé.
