# STRATEGYBUZZER — 02_KERNELROTATIONPLANNER v3.3 — ALIGN-02

**Date :** 17 août 2026  
**Statut :** UNDER_REVIEW  
**Nature :** réconciliation contractuelle — aucune nouvelle logique moteur  
**Base officielle :** `02_KernelRotationPlanner.md v3.2` + décisions OFFICIAL du `00_ArchitectureRegister.md`  
**Baseline code de référence :** `db26047532cfdf5e030c348dba4455f8eb310971`

---

# 1. But de v3.3 ALIGN-02

Cette version ne redéfinit pas la rotation KRP.

Elle corrige une ambiguïté de périmètre :

> **Le module `02_KernelRotationPlanner` s'arrête dès que le Blueprint canonique a reçu `depth + domain`.**

Tout ce qui suit appartient aux modules suivants ou à une orchestration globale externe au module 02.

Aucune règle de v3.2 concernant le DepthCycle, `CURRENT_KERNEL_RECEIVED`, `DOMAIN_EXHAUSTED`, `DEPTH_EXHAUSTED` ou `PRODUCTION_ON_HOLD` n'est modifiée ici.

---

# 2. Sources d'autorité conservées

ALIGN-02 conserve sans modification les décisions officielles suivantes :

- `DEC-058` — `KernelBlueprintFactory` crée le Blueprint avant KRP ;
- `DEC-060` — `kernel_received_total` est une donnée de traçabilité ; `cycle_target/cycle_completed` ne sont plus l'autorité de changement de Depth ;
- `DEC-063` — `CURRENT_KERNEL_RECEIVED` est le seul déclencheur de la prochaine rotation ;
- `DEC-068` — KRP n'écrit jamais `kernel_code` ;
- `DEC-082` — `DOMAIN_EXHAUSTED(depth, domain)` est produit prospectivement par Taxonomy ;
- `DEC-083` — `DEPTH_EXHAUSTED(depth)` est produit prospectivement par Taxonomy ;
- `DEC-092` — après `DEPTH_EXHAUSTED(10)`, le prochain CKR mène à `PRODUCTION_ON_HOLD` ;
- `DEC-093` — `CURRENT_KERNEL_RECEIVED` est le seul incrémenteur de `kernel_received_total` ;
- `DEC-094` — DepthCycle officiel `2 → 4 → 6 → 7 → 8 → 9 → 10`, sans retour automatique vers 2.

Interdiction : ALIGN-02 ne crée aucune `DEC-108`, `DEC-111` ou autre décision implicite.

---

# 3. Frontière exacte du module 02

## 3.1 Flow canonique interne

```text
ENTRÉE MODULE 02
↓
KernelBlueprintFactory
↓
création d'UNE instance canonique de KernelBlueprint
↓
blueprint_id écrit par Factory
↓
KernelRotationPlanner
↓
lecture de l'état persistant de rotation
↓
sélection du prochain couple depth + domain
↓
fillRotation(depth, domain)
↓
PERSISTENCE de la position KRP
↓
SORTIE MODULE 02
```

**FIN.**

Le module 02 ne poursuit pas lui-même vers Taxonomy.

---

# 4. État exact du Blueprint à la sortie du module 02

Le même Blueprint créé à l'entrée ressort avec :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

Les futurs slots cognitifs restent également non remplis.

Règles :

- aucune réécriture de `depth` ou `domain` ;
- aucun appel à `fillTaxonomy()` ;
- aucun appel à `fillKernelCode()` ;
- aucun contenu cognitif généré ;
- aucune validation cognitive exécutée ;
- aucune traduction exécutée.

Le Blueprint reste `CREATED_UNENGAGED` tant que le module suivant n'a pas rempli le territoire Taxonomy selon son propre contrat.

---

# 5. Propriétés exclusives du module 02

## 5.1 KernelBlueprintFactory

Responsabilités :

- créer le Blueprint canonique ;
- générer `blueprint_id` ;
- garantir l'unicité du Blueprint actif selon le contrat Factory ;
- créer la trace technique `kernel_blueprint_runs` ;
- ne remplir aucun slot métier.

## 5.2 KernelRotationPlanner

Responsabilités :

- lire l'état KRP persistant ;
- recevoir les signaux KRP externes autorisés ;
- décider du prochain `depth + domain` ;
- écrire `depth + domain` une seule fois ;
- persister sa position de rotation ;
- comptabiliser `CURRENT_KERNEL_RECEIVED` de façon idempotente ;
- appliquer la transition Depth pending au moment prévu par le contrat CKR.

Interdictions :

- ne crée pas le Blueprint ;
- ne remplit pas Taxonomy ;
- ne crée pas `kernel_code` ;
- ne produit pas `DOMAIN_EXHAUSTED` ;
- ne produit pas `DEPTH_EXHAUSTED` ;
- ne déduit pas l'épuisement Taxonomy ;
- ne décide pas à partir de `cycle_target/cycle_completed` ;
- ne retourne jamais automatiquement `Depth 10 → Depth 2`.

---

# 6. CURRENT_KERNEL_RECEIVED — interface récurrente

## 6.1 Origine

ReadyBank reçoit le Blueprint canonique courant et émet :

```text
CURRENT_KERNEL_RECEIVED
```

L'événement contient l'identité du Blueprint reçu et son couple `depth + domain`.

## 6.2 Responsabilité KRP

À la réception canonique :

```text
CURRENT_KERNEL_RECEIVED
↓
idempotence par blueprint_id
↓
receipt durable
↓
kernel_received_total[depth][domain] +1 exactement une fois
↓
lecture d'une éventuelle transition DEPTH_EXHAUSTED pending
↓
application de la transition si requise
↓
autorisation de la prochaine rotation
```

Le mécanisme Outbox peut porter cette frontière transactionnelle, mais il ne remplace pas la responsabilité KRP de comptabilisation et de transition.

## 6.3 Blueprint suivant

Après traitement valide du CKR :

```text
entrée module 02
↓
Factory crée le Blueprint suivant
↓
KRP lui écrit le prochain depth + domain
↓
sortie module 02
```

---

# 7. DOMAIN_EXHAUSTED

Source : **Taxonomy uniquement**.

Signal :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Sémantique :

> Après la consommation valide du Blueprint courant, le bassin `Depth + Domaine` ne possède plus de matière intellectuelle exploitable pour une future attribution.

Le Blueprint déclencheur reste valide.

KRP :

- mémorise le domaine comme épuisé pour ce Depth ;
- ignore ce domaine lors des rotations futures de ce Depth ;
- ne réattribue jamais le Blueprint courant ;
- ne réécrit jamais `depth + domain`.

Idempotence : deuxième signal identique = NO-OP.

---

# 8. DEPTH_EXHAUSTED

Source : **Taxonomy uniquement**.

Signal :

```text
DEPTH_EXHAUSTED(depth)
```

Sémantique :

> Après la consommation valide du Blueprint courant, tous les bassins Domaines de ce Depth sont intellectuellement épuisés.

Il ne signifie pas « fin d'un tour KRP ».

KRP :

- mémorise prospectivement la transition ;
- ne change pas le Blueprint courant ;
- attend `CURRENT_KERNEL_RECEIVED` du Blueprint déclencheur ;
- applique ensuite la transition vers le prochain Depth.

---

# 9. DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10
```

Règles :

- Depth 1 n'est pas dans ce cycle ;
- Depth 10 est valide pour la création intellectuelle ;
- après `DEPTH_EXHAUSTED(10)` + `CURRENT_KERNEL_RECEIVED` :

```text
depth_state = PRODUCTION_ON_HOLD
```

Interdiction :

```text
10 → 2 automatique
```

Aucune sortie automatique de `PRODUCTION_ON_HOLD` n'est définie par le module 02.

---

# 10. DepthNeedMatrix — autorité exacte

La Matrix peut porter les données nécessaires au suivi du besoin de production et à la traçabilité.

Dans le contrat KRP officiel actuellement retenu :

```text
kernel_received_total
```

est comptabilisé par CKR.

En revanche :

```text
cycle_target
cycle_completed
nextRequiredDepth() basé sur ces compteurs
```

ne doivent pas remplacer `DEPTH_EXHAUSTED` comme autorité de changement de Depth.

Les surfaces legacy peuvent rester physiquement présentes tant qu'elles ne pilotent pas la décision KRP.

---

# 11. États KRP admis par ALIGN-02

ALIGN-02 ne crée aucun nouvel état.

Les états fonctionnels retenus restent ceux du contrat officiel v3.2 :

```text
ROTATION_ACTIVE
PRODUCTION_ON_HOLD
```

Pour les domaines :

```text
ACTIF
DOMAIN_EXHAUSTED
```

Ne sont pas adoptés par ALIGN-02 :

```text
VISIBLE
ESTOMPÉ
BLOCKED
AWAITING_DEPTH_EXHAUSTED
```

Ils nécessiteraient une décision architecturale explicite distincte.

---

# 12. Orchestration — distinction obligatoire

## 12.1 Orchestration du module 02

Le raccord utilisé pour exécuter le module 02 doit faire uniquement :

```text
Factory
↓
KRP
↓
retour Blueprint avec depth + domain
```

Il ne doit pas :

- instancier Taxonomy ;
- appeler `peekNext()` ;
- appeler `fillTaxonomy()` ;
- instancier `KernelCodeEngine` ;
- appeler `fillKernelCode()` ;
- marquer le Blueprint comme engagé sur la base d'un résultat Taxonomy.

## 12.2 Orchestration globale du pipeline

L'enchaînement :

```text
02 KRP
↓
03 Taxonomy
↓
04 ValidationDominantIdeas / modules suivants
↓
QuestionIntent
↓
Phases
↓
ReadyBank
```

appartient à une orchestration **externe** au module 02.

Son contrat n'est pas défini ici.

ALIGN-02 interdit donc d'élargir `KernelRotationPlanner` pour résoudre une responsabilité de l'orchestrateur global.

---

# 13. Conséquence sur IMPASSE-KRP-001

L'impasse historique était :

```text
KRP commit
↓
Taxonomy appelée par le même orchestrateur
↓
exception Taxonomy
↓
Blueprint CREATED_UNENGAGED durable
```

Avec la frontière stricte ALIGN-02 :

- l'appel Taxonomy n'appartient plus au module 02 ;
- une exception Taxonomy après la sortie du module 02 devient un problème de **handoff / orchestration globale** ;
- KRP ne doit pas inventer de mécanisme de récupération pour ce cas ;
- la résolution exacte doit être définie avec le contrat du module 03 et de l'orchestration globale.

La séparation de responsabilité retire cette impasse du périmètre interne de KRP sans prétendre résoudre le problème inter-module lui-même.

---

# 14. Classification d'implantation après RESTORE-02

Baseline courante = `db260475` pour les huit fichiers restaurés.

## 14.1 `KernelRotationPlanner.php`

```text
CONSERVER pour la logique KRP v3.2 officielle
```

Ne pas réintroduire la réécriture v3.3 annulée.

## 14.2 `KernelPipelineOrchestrator.php`

```text
MODIFIER
```

Raison : l'implémentation baseline coordonne encore Taxonomy et KernelCodeEngine avant de retourner, ce qui dépasse la frontière stricte du module 02.

Cible ALIGN-02 :

```text
Factory → KRP → return Blueprint
```

## 14.3 `QuestionsKernelRotateCommand.php`

```text
MODIFIER
```

La commande de module 02 ne doit pas construire Taxonomy ni KernelCodeEngine.

## 14.4 `ProcessKernelPipelineOutbox.php`

```text
CONSERVER la sémantique CKR baseline
ADAPTER uniquement au nouvel Orchestrator module 02
```

Ordre obligatoire :

```text
planner->receiveKernelReceivedV2(...)
↓
orchestrator module 02 -> run()
↓
processed_at après succès
```

Interdiction : retirer le traitement CKR KRP comme dans la série annulée `2bb88895`.

## 14.5 `QuestionsKernelProcessOutboxCommand.php`

```text
MODIFIER LE WIRING
```

Conserver KRP + Outbox, retirer l'instanciation Taxonomy/KernelCodeEngine de ce point d'entrée module 02.

## 14.6 `KernelRotationPlannerV3Test.php`

```text
CONSERVER la baseline
```

Ne pas réintroduire les tests wrap `10 → 2` / cycle target de `0d9cc046`.

## 14.7 `KernelPipelineOrchestratorTest.php`

```text
RÉÉCRIRE pour la frontière stricte
```

Tester uniquement Factory + KRP + sortie du Blueprint.

## 14.8 `ProcessKernelPipelineOutboxTest.php`

```text
MODIFIER LE HARNESS
CONSERVER les invariants CKR
```

Le test doit continuer à prouver comptabilisation idempotente + transition pending + déclenchement du prochain Blueprint, sans faire de Taxonomy une responsabilité du module 02.

---

# 15. Tests contractuels requis avant fermeture v3.3

Le futur lot d'implantation doit prouver au minimum :

1. premier run : Blueprint créé une fois ;
2. KRP écrit `depth=2`, `domain=geographie` selon l'état initial officiel ;
3. sortie module 02 : Taxonomy slots = `null` ;
4. sortie module 02 : `kernel_code = null` ;
5. aucun appel Taxonomy dans l'orchestrateur module 02 ;
6. aucun appel KernelCodeEngine dans l'orchestrateur module 02 ;
7. `DOMAIN_EXHAUSTED` est prospectif et idempotent ;
8. `DEPTH_EXHAUSTED` ne change pas immédiatement le Blueprint courant ;
9. CKR incrémente `kernel_received_total` exactement une fois ;
10. CKR applique la transition Depth pending ;
11. `Depth 10 + DEPTH_EXHAUSTED + CKR → PRODUCTION_ON_HOLD` ;
12. aucun wrap automatique `10 → 2` ;
13. `cycle_target/cycle_completed` ne pilotent pas la transition ;
14. aucun état `VISIBLE/ESTOMPÉ/BLOCKED/AWAITING_DEPTH_EXHAUSTED` introduit sans contrat ;
15. replay CKR = idempotent ;
16. échec du run module 02 ne marque pas l'événement Outbox comme traité.

---

# 16. Sortie ALIGN-02

Cette spécification produit une cible unique :

```text
CURRENT_KERNEL_RECEIVED / GO initial
↓
MODULE 02
   KernelBlueprintFactory
   ↓
   KernelRotationPlanner
   ↓
   Blueprint {blueprint_id, depth, domain}
↓
FIN MODULE 02
↓
Handoff externe vers 03_Taxonomy
```

Le module 02 est terminé avant :

```text
subdomain_active
subject_active
dominant_idea_active
kernel_code
Phase 1
Validation
Traductions
ReadyBank
```

---

# 17. Statut

```text
ALIGN-02 SPEC DRAFT       ✅ PRODUIT
MODIFICATION CODE         ❌ AUCUNE
DÉCISION NOUVELLE         ❌ AUCUNE inventée
RÈGLES v3.2 ROTATION      ✅ CONSERVÉES
FRONTIÈRE STRICTE 02      ✅ FORMALISÉE
IMPLANTATION ALIGNÉE      ⏳ APRÈS VERROUILLAGE
```
