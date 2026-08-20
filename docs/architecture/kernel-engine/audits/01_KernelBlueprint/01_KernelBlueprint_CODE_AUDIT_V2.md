# StrategyBuzzer — 01_KernelBlueprint — AUDIT-01-00

**Date :** 2026-08-20  
**Spécification auditée :** `specifications/01_KernelBlueprint.md` v2.0 VERROUILLÉE  
**Décision de référence :** DEC-113  
**Statut du bloc :** **CLOSED — AUDIT COMPLET, AUCUN PATCH APPLICATIF**

---

# 1. Règle de l'audit

Le code n'a aucune autorité architecturale. La classification ci-dessous compare le workspace réel à la spécification v2.0.

```text
KEEP       = conforme, conserver
MODIFY     = existe mais doit être aligné
REMOVE     = legacy/interdit dans le chemin canonique 01
MISSING    = contrat v2.0 absent
UNRESOLVED = information insuffisante pour implanter sans nouvelle décision
```

Aucun fichier `app/**`, `tests/**` ou migration n'a été modifié pendant AUDIT-01-00.

---

# 2. Réconciliation GitHub / Replit

## GitHub officiel

```text
branche : replit/intellectual-engine-current-2026-08-16
HEAD avant AUDIT-01-00 : 690c859e4b13a0cde1056d363f41ff8dbb03aa67
```

## Replit réel au moment de l'audit

```text
branche : replit/intellectual-engine-current-2026-08-16
HEAD local : db26047532cfdf5e030c348dba4455f8eb310971
git status : propre
origin local connu : 0 ahead / 0 behind
```

La référence `origin` locale Replit est périmée. GitHub est 52 commits devant `db260475`.

Comparaison GitHub `db260475...690c859e` : les 52 commits ne modifient que la documentation. Aucun `app/**`, `tests/**` ou fichier de migration n'est différent.

**Conséquence :** l'audit de code reste valide pour le workspace Replit actuel, mais **aucune implantation ne doit commencer avant synchronisation Replit avec le HEAD GitHub officiel**.

---

# 3. Périmètre de code réel audité

## Coeur Blueprint

- `app/Services/QuestionBank/KernelBlueprint.php`
- `app/Services/QuestionBank/Rotation/KernelBlueprintFactory.php`
- `app/Services/QuestionBank/Rotation/KernelBlueprintRunRepository.php`

## Orchestration / lifecycle / frontière

- `app/Services/QuestionBank/Rotation/KernelPipelineOrchestrator.php`
- `app/Services/QuestionBank/Rotation/KernelBlueprintReadyBankReceiver.php`
- `app/Services/QuestionBank/Rotation/ProcessKernelPipelineOutbox.php`
- `app/Services/QuestionBank/Rotation/KernelPipelineOutboxRepository.php`

## Consommateurs / propriétaires Section 1

- `app/Services/QuestionBank/Rotation/KernelRotationPlanner.php`
- `app/Services/QuestionBank/KernelCodeEngine.php`

## Structure legacy parallèle

- `app/Services/QuestionBank/KernelFrameBuilder.php`
- `app/Console/Commands/QuestionsKernelSkeletonCommand.php`

## Persistance

- `database/migrations/2026_07_28_000001_create_kernel_blueprint_runs.php`
- `database/migrations/2026_08_11_200000_add_kernel_code_to_blueprint_runs_and_create_sequences.php`
- `database/migrations/2026_08_12_000001_add_one_active_blueprint_unique_index.php`

## Tests principaux

- `tests/Unit/QuestionBank/KernelBlueprintPart1Test.php`
- `tests/Unit/QuestionBank/Rotation/KernelBlueprintFactoryTest.php`
- `tests/Concurrent/kernel_blueprint_factory_concurrent.php`
- `tests/Unit/QuestionBank/Rotation/KernelPipelineOrchestratorTest.php`
- `tests/Unit/QuestionBank/Rotation/ProcessKernelPipelineOutboxTest.php`
- `tests/Unit/QuestionBank/Rotation/KernelRotationPlannerV3Test.php`
- `tests/Integration/QuestionBank/Rotation/KernelRotationPlannerPostgresTest.php`
- `tests/Integration/QuestionBank/Rotation/KernelRotationPlannerPostgresStrictTest.php`
- `tests/Unit/QuestionBank/Taxonomy/SeededBankRotationBlueprintTest.php`
- `tests/Unit/QuestionBank/Rotation/KernelMigrationSchemaTest.php`

---

# 4. Résultat global

```text
ARCHITECTURE v2.0 : VERROUILLÉE
CONTRAT v2.0      : VERROUILLÉ
CODE EXISTANT     : PARTIELLEMENT CONFORME
TESTS HISTORIQUES : PASS
VALIDATION v2.0   : NON
```

Baseline exécutée dans Replit, sans modification :

```text
KernelBlueprintPart1Test
+
KernelBlueprintFactoryTest
=
75 tests / 130 assertions / PASS
```

Ces tests ne constituent pas la validation v2.0 car ils imposent encore plusieurs hypothèses historiques.

---

# 5. KEEP — éléments conformes à conserver

## K-01 — Encapsulation Section 1

`KernelBlueprint` possède des propriétés privées pour :

```text
blueprint_id
depth
domain
subdomain_active
subject_active
dominant_idea_active
kernel_code
```

Lecture publique contrôlée ; écriture directe externe refusée.

**Classement : KEEP**

## K-02 — Identity write-once

`initializeBlueprintId()` refuse une seconde initialisation.

**Classement : KEEP**

## K-03 — Rotation write-once groupée

`fillRotation(depth, domain)` écrit les deux valeurs ensemble et refuse un deuxième remplissage normal.

**Classement : KEEP**

## K-04 — Taxonomy write-once groupée

`fillTaxonomy(subdomain, subject, dominantIdea)` écrit le triplet ensemble et refuse un deuxième remplissage normal.

**Classement : KEEP**

## K-05 — kernel_code write-once

`fillKernelCode()` protège l'écriture du kernel_code.

**Classement : KEEP**

## K-06 — Absence d'écriture externe directe

Aucune écriture production contournant les méthodes `initializeBlueprintId/fill*` n'a été trouvée.

**Classement : KEEP**

## K-07 — Factory distincte de KRP

`KernelBlueprintFactory` existe, génère l'identité, crée l'enregistrement `CREATED_UNENGAGED` et retourne une enveloppe vide. KRP n'instancie pas directement le Blueprint dans le chemin identifié.

**Classement : KEEP** pour l'existence et la responsabilité Factory.

## K-08 — Unicité d'un Blueprint actif

Deux protections existent :

1. vérification applicative ;
2. index unique partiel PostgreSQL `one_active_blueprint_idx` sur les états actifs.

Le test concurrent 20 workers existe.

**Classement : KEEP**

## K-09 — Moment d'engagement dans le chemin principal

Dans `KernelPipelineOrchestrator`, `fillTaxonomy(...)` réussit avant le passage à `ENGAGED_IN_PIPELINE`.

La rotation seule ne marque pas le Blueprint engagé dans ce chemin.

**Classement : KEEP** sur cet invariant du chemin principal.

## K-10 — KernelCodeEngine

Le moteur écrit le kernel_code après présence des cinq dimensions amont et refuse explicitement `Général` comme domaine de création. `Science` est supporté.

**Classement : KEEP** pour la frontière Section 1 concernée.

## K-11 — Réception ReadyBank atomique

`KernelBlueprintReadyBankReceiver` préserve `blueprint_id` et écrit atomiquement réception + événement Outbox.

**Classement : KEEP** pour l'atomicité de réception et la conservation de l'identité.

---

# 6. MODIFY — éléments existants à aligner

## M-01 — Docblocks et contrats historiques de KernelBlueprint

Le coeur mentionne encore notamment :

- `Général` comme domaine de création ;
- l'absence de `Science` ;
- l'ancien `DominantIdeaValidator` autonome ;
- d'anciens exemples/formats de kernel_code ;
- Sections 2/3 comme non implantées.

**Classement : MODIFY**

## M-02 — Représentation `toArray()` historique

Les tests exigent exactement six clés de Section 1 et excluent les sections aval. La v2.0 exige trois sections permanentes et l'identité canonique doit rester transportable.

**Classement : MODIFY**

La forme technique exacte de sérialisation sera choisie sans inventer le payload Phase1/Phase2.

## M-03 — `NOT_ENGAGED_PRODUCTION_ON_HOLD`

L'ancien état apparaît encore dans :

- commentaire de migration ;
- message Factory ;
- commentaire ReadyBankReceiver ;
- test Factory.

Aucun writer runtime actif de cet état n'a été trouvé.

La v2.0 dit explicitement que `PRODUCTION_ON_HOLD` n'est pas un état Blueprint.

**Classement : MODIFY / LEGACY À RETIRER DU CONTRAT 01**

## M-04 — Repository `markEngaged()` legacy

`KernelBlueprintRunRepository::markEngaged()` mélange :

```text
transition lifecycle
+
persistance depth/domain
```

et sa documentation associe engagement à la rotation.

Cette méthode n'est appelée ni en production ni dans les tests.

**Classement : MODIFY ou REMOVE selon micro-bloc de cleanup ; aucune dépendance active trouvée.**

## M-05 — Persistance Section 1 incomplète

`kernel_blueprint_runs` persiste actuellement :

```text
blueprint_id
execution_state
depth
domain_code
kernel_code
```

mais pas :

```text
subdomain_active
subject_active
dominant_idea_active
```

La v2.0 exige une enveloppe persistante reconstructible sous `blueprint_id`.

**Classement : MODIFY**

## M-06 — Atomicité persistée Rotation / Taxonomy

L'objet métier possède des écritures groupées correctes, mais la persistance canonique ne reflète pas encore tous les groupes Section 1. La v2.0 interdit qu'un état persistant canonique expose un triplet Taxonomy partiel.

**Classement : MODIFY**

## M-07 — Orchestrateur v3.2 historique

Le chemin actuel exécute `KernelRotationPlanner::resolveNextRotation()` avant `KernelBlueprintFactory::create()`.

La v2.0 fixe la frontière :

```text
Factory
↓
nouveau Blueprint
↓
KRP
```

**Classement : MODIFY — frontière 01/02.**

La logique interne de rotation n'est pas corrigée pendant 01 ; seule la frontière canonique 01 devra être préparée sans inventer KRP v3.3.

## M-08 — Cleanup destructif d'un CREATED_UNENGAGED

`KernelPipelineOrchestrator::cleanupBlueprint()` supprime la ligne `CREATED_UNENGAGED` lorsque Taxonomy retourne null.

La v2.0 interdit le recyclage du même Blueprint et exige que la politique d'un Blueprint créé mais non engagé reste contrôlée par la frontière opérationnelle appropriée.

**Classement : MODIFY**

Aucune nouvelle politique métier n'est inventée pendant l'audit.

## M-09 — CURRENT_KERNEL_RECEIVED dirigé directement vers KRP

`ProcessKernelPipelineOutbox` traite actuellement :

```text
CURRENT_KERNEL_RECEIVED
↓
KernelRotationPlanner::receiveKernelReceivedV2()
↓
KernelPipelineOrchestrator::run()
```

La v2.0 verrouille :

```text
ReadyBank reçoit Blueprint courant
↓
CURRENT_KERNEL_RECEIVED
↓
frontière lifecycle/orchestration
↓
Factory crée NOUVEAU Blueprint
↓
KRP travaille sur le nouveau Blueprint
```

**Classement : MODIFY — ancien handoff ReadyBank → KRP à retirer du chemin canonique.**

Le détail interne du futur KRP v3.3 reste hors 01.

## M-10 — Tests historiques

Les tests existants valident entre autres :

- `toArray()` à exactement six clés ;
- ancien état `NOT_ENGAGED_PRODUCTION_ON_HOLD` ;
- ancien chemin Outbox → KRP direct ;
- conservation possible d'un `CREATED_UNENGAGED` orphelin après exception.

**Classement : MODIFY**

---

# 7. MISSING — exigences v2.0 absentes

## X-01 — Sept CognitiveSlots permanents dans KernelBlueprint

Aucun conteneur Section 2 canonique n'existe actuellement dans `KernelBlueprint`.

Les sept conteneurs obligatoires sont absents :

```text
qcm_recognition
qcm_reasoning
qcm_deceptive_trap
tf_recognition_true
tf_recognition_false
tf_reasoning_true
tf_reasoning_false
```

**Classement : MISSING**

## X-02 — Couche TranslationSlots 1:1

Aucune couche Section 3 canonique liée 1:1 aux sept CognitiveSlots n'existe dans KernelBlueprint.

**Classement : MISSING**

## X-03 — Interfaces contrôlées Sections 2/3

Aucun mécanisme canonique KernelBlueprint ne permet aux propriétaires futurs de remplir un conteneur ciblé sans ouvrir Section 1 à l'écriture libre.

**Classement : MISSING**

Le payload métier détaillé n'est pas à inventer avant 06/08.

## X-04 — Réhydratation/reconstruction du Blueprint

Aucun `hydrate`, `fromPersistence`, constructeur de reconstruction ou équivalent n'a été trouvé.

`KernelBlueprintRunRepository::findById()` retourne une ligne DB générique, pas un KernelBlueprint canonique.

**Classement : MISSING**

## X-05 — Persistance des conteneurs Sections 2/3

Aucune persistance liée à `blueprint_id` pour CognitiveSlots ou TranslationSlots n'existe.

**Classement : MISSING**

## X-06 — Reprise d'un CREATED_UNENGAGED orphelin après crash

Après une exception post-commit, aucun mécanisme actif de reprise/fermeture contrôlée n'a été trouvé. Un Blueprint peut rester durablement `CREATED_UNENGAGED` et bloquer Factory.

**Classement : MISSING** pour la reprise technique compatible avec le contrat v2.0.

## X-07 — Tests contractuels v2.0 aval/persistance

Manquent notamment les preuves v2.0 pour :

- présence des sept CognitiveSlots ;
- correspondance Translation 1:1 ;
- impossibilité qu'une écriture Section 2/3 modifie Section 1 ;
- persistance complète du triplet Taxonomy ;
- reconstruction après redémarrage ;
- lifecycle persistant après Taxonomy ;
- nouveau `blueprint_id` après CURRENT_KERNEL_RECEIVED sans réutilisation de l'ancien ;
- absence totale de `PRODUCTION_ON_HOLD` comme état Blueprint.

**Classement : MISSING**

---

# 8. REMOVE — legacy hors chemin canonique 01

## R-01 — KernelFrameBuilder comme faux Blueprint parallèle

`KernelFrameBuilder` construit une structure parallèle comprenant :

- anciens champs rotation/taxonomy ;
- KLD/KS ;
- cognitive_slots ;
- translation_slots ;
- statuses/traces ;
- legacy.

Cette structure est persistée séparément dans `question_intents.frame_en` et n'est pas reliée au KernelBlueprint canonique ni à `kernel_blueprint_runs`.

**Classement : REMOVE DU CHEMIN CANONIQUE 01.**

Cela ne signifie pas suppression immédiate du fichier pendant 01 : le retrait physique du legacy ne doit pas casser les modules aval non encore spécifiés. Il ne peut cependant servir de source de vérité ni remplacer les Sections 2/3 du Blueprint.

## R-02 — Ancien handoff ReadyBank → KRP direct

Le branchement direct de `CURRENT_KERNEL_RECEIVED` vers la logique KRP v3.2 est incompatible avec la frontière v2.0.

**Classement : REMOVE DU CHEMIN CANONIQUE.**

Le remplacement doit préserver la responsabilité future de KRP sans la redéfinir pendant 01.

---

# 9. UNRESOLVED

Aucun blocker métier nouveau n'a été découvert pour implanter les responsabilités propres à 01.

Les points suivants sont volontairement réservés à leurs modules propriétaires et ne bloquent pas 01 :

- payload détaillé des CognitiveSlots → 06_Phase1 ;
- états métier détaillés des validations → 07/09 ;
- schéma linguistique détaillé / langues → 08_Phase2 ;
- mutation contrôlée Quarantine → 10_Quarantine ;
- logique interne complète du prochain KRP → 02_KernelRotationPlanner v3.3.

**UNRESOLVED bloquant pour 01 : AUCUN.**

---

# 10. Tests contractuels v2.0 — état après audit

| Groupe | État actuel |
|---|---|
| Factory / identité | PARTIELLEMENT COUVERT |
| Rotation write-once | COUVERT objet ; persistance à compléter |
| Taxonomy write-once | COUVERT objet ; lifecycle/persistance à compléter |
| kernel_code | largement couvert par mécanisme existant |
| Ownership / encapsulation | COUVERT Section 1 |
| Pipeline / nouveau noyau | NON CONFORME sur frontière actuelle |
| Sections aval | ABSENT |
| Redémarrage / reconstruction | ABSENT |
| Concurrence Factory | TEST EXISTANT |

---

# 11. Découpage d'implantation issu de l'audit

Le découpage suivant devient le plan d'implantation de 01. Chaque bloc reste à ouvrir un par un avec sa fiche exacte avant modification.

## PREP-01-SYNC — précondition environnementale

Synchroniser le workspace Replit sur le HEAD GitHub officiel avant tout patch.

Ce n'est pas un bloc d'architecture ni un bloc d'implantation ; c'est une condition de sécurité Git.

## IMPL-01-01 — Coeur canonique v2.0

Responsabilité unique : aligner `KernelBlueprint` sur son contrat structurel v2.0 sans encore ajouter la persistance complète.

Inclut : nettoyage des contrats historiques et structure canonique permanente minimale.

## IMPL-01-02 — Section 2 : coquilles CognitiveSlots

Responsabilité unique : réserver exactement les sept conteneurs permanents, sans inventer le payload Phase1.

## IMPL-01-03 — Section 3 : coquilles TranslationSlots

Responsabilité unique : réserver la correspondance 1:1, sans inventer le contrat linguistique Phase2.

## IMPL-01-04 — Persistance canonique Section 1

Responsabilité unique : rendre `blueprint_id + rotation + Taxonomy + kernel_code + lifecycle` persistables/reconstructibles sans état partiel normal.

## IMPL-01-05 — Persistance des conteneurs permanents

Responsabilité unique : persister/reconstruire les coquilles Sections 2/3 sous le même `blueprint_id`, sans déplacer les Banks externes dans Blueprint.

## IMPL-01-06 — Réhydratation / reprise technique

Responsabilité unique : reconstruire le Blueprint canonique après redémarrage et traiter proprement un `CREATED_UNENGAGED` récupérable sans recycler l'identité.

## IMPL-01-07 — Lifecycle Taxonomy

Responsabilité unique : garantir et tester `CREATED_UNENGAGED → ENGAGED_IN_PIPELINE` uniquement après `fillTaxonomy(...)` réussi.

## IMPL-01-08 — Frontière CURRENT_KERNEL_RECEIVED → nouveau Blueprint

Responsabilité unique : aligner la frontière 01 afin que le Blueprint reçu ne retourne jamais dans KRP et que le noyau suivant obtienne une nouvelle identité.

La logique métier interne KRP v3.3 reste interdite dans ce bloc.

## IMPL-01-09 — Nettoyage legacy contractuel 01

Responsabilité unique : retirer du chemin canonique les états/formulations/interfaces 01 supersédés sans supprimer prématurément les composants appartenant aux futurs modules.

## IMPL-01-10 — Validation terminale 01

Responsabilité unique : exécuter la matrice complète des 30 tests contractuels v2.0, tests de concurrence applicables et non-régression cumulative.

---

# 12. Ordre obligatoire

```text
AUDIT-01-00 = CLOSED
↓
PREP-01-SYNC
↓
IMPL-01-01
↓
IMPL-01-02
↓
IMPL-01-03
↓
IMPL-01-04
↓
IMPL-01-05
↓
IMPL-01-06
↓
IMPL-01-07
↓
IMPL-01-08
↓
IMPL-01-09
↓
IMPL-01-10
↓
01 KernelBlueprint — validation FINI
↓ seulement ensuite
02 KernelRotationPlanner v3.3
```

Aucun bloc ne peut être combiné avec le suivant simplement pour aller plus vite.

---

# 13. Verdict AUDIT-01-00

```text
AUDIT-01-00 : CLOSED

Architecture v2.0 : 100 %
Contrat v2.0      : 100 %
Implémentation    : PARTIELLE / NON CONFORME v2.0
Validation v2.0   : NON

UNRESOLVED BLOQUANT : AUCUN
BLOCKER AVANT PATCH : REPLIT NON SYNCHRONISÉ AVEC GITHUB
```

Prochaine opération exacte :

```text
PREP-01-SYNC
```

Aucun patch 01 n'est autorisé avant cette synchronisation.