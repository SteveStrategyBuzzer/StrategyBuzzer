# StrategyBuzzer — 01_KernelBlueprint

**Version :** 2.0  
**Date :** 2026-08-19  
**Statut documentaire :** **VERROUILLÉ**  
**Architecture :** **100 %**  
**Contrat :** **100 %**  
**Implémentation :** hors statut de ce document — audit v2.0 obligatoire avant toute correction de code  
**Validation code :** à refaire contre v2.0 après audit/implantation

> Réécriture canonique complète de la spécification KernelBlueprint. Cette version remplace la reconstruction active et harmonise les décisions encore valides avec la frontière actuelle `KernelBlueprintFactory → nouveau KernelBlueprint → KernelRotationPlanner`. Le code historique ne définit pas cette architecture.

---

# 1. Mission

`KernelBlueprint` est l’**enveloppe canonique persistante d’un noyau** pendant son passage dans le pipeline StrategyBuzzer.

Il transporte les contrats permanents du noyau courant et permet à chaque module propriétaire d’écrire uniquement les slots qui lui appartiennent.

Il ne produit aucune décision métier.

```text
KernelBlueprintFactory
↓
KernelBlueprint canonique
↓
KernelRotationPlanner
↓
Taxonomy
↓
QuestionIntent
↓
Phase1
↓
ValidationPhase1
↓
Phase2
↓
ValidationPhase2
↓
ReadyBank
```

Après réception du noyau courant par ReadyBank :

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
autorisation de produire le Blueprint suivant
↓
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint canonique
↓
KernelRotationPlanner
```

Le Blueprint reçu par ReadyBank n’est jamais recyclé vers KRP.

---

# 2. Responsabilités

KernelBlueprint doit :

1. fournir une identité canonique immuable `blueprint_id` ;
2. conserver les slots permanents du noyau ;
3. exposer les valeurs aux lecteurs autorisés sans donner un droit d’écriture libre ;
4. faire respecter l’ownership des écritures ;
5. garantir le write-once des groupes structurels dans le chemin normal ;
6. conserver la même identité pendant tout le pipeline ;
7. transporter la Section 1 — création intellectuelle ;
8. réserver la Section 2 — création gameplay ;
9. réserver la Section 3 — traduction ;
10. rester distinct des réservoirs, curseurs, banques et mécanismes internes des moteurs ;
11. permettre une correction contrôlée future par Quarantine sans transformer l’objet canonique en structure librement réinscriptible ;
12. demeurer lisible par les modules aval selon leurs contrats.

---

# 3. Interdictions

KernelBlueprint ne doit jamais :

- choisir un Depth ;
- choisir un Domain ;
- choisir un Subdomain ;
- choisir un Subject ;
- choisir une Dominant Idea ;
- produire `kernel_code` ;
- générer une question ;
- traduire un contenu ;
- valider un contenu ;
- posséder les banques Taxonomy ;
- posséder les curseurs Taxonomy/KRP ;
- posséder `cycle_target` ou `cycle_completed` ;
- posséder `PRODUCTION_ON_HOLD` comme état du Blueprint ;
- être réutilisé pour une deuxième rotation ;
- accepter une écriture directe extérieure contournant le propriétaire ;
- accepter un second remplissage normal d’un groupe déjà rempli ;
- recevoir directement une ancienne logique `ReadyBank → KRP` réutilisant le même Blueprint.

---

# 4. Entrées

## 4.1 Création

`KernelBlueprintFactory` reçoit une demande de création autorisée par l’orchestration du pipeline et crée une nouvelle enveloppe canonique.

La création attribue :

```text
blueprint_id
```

## 4.2 Écritures des propriétaires

Le Blueprint reçoit ensuite, dans l’ordre normal du pipeline :

```text
KernelRotationPlanner
→ depth + domain

Taxonomy
→ subdomain_active + subject_active + dominant_idea_active

QuestionIntent / KernelCodeEngine
→ kernel_code

Phase1
→ contenu des 7 CognitiveSlots

Phase2
→ contenu des TranslationSlots correspondants
```

Les moteurs de validation et Quarantine peuvent faire évoluer les états/contenus uniquement selon leurs futurs contrats propriétaires ; ces mécanismes ne sont pas inventés par `01_KernelBlueprint`.

---

# 5. Sorties

KernelBlueprint ne produit pas une décision métier en sortie.

Sa sortie est **lui-même**, enrichi progressivement, avec la même identité canonique.

Chaque module aval reçoit le même noyau courant et lit uniquement les slots nécessaires.

Destination terminale :

```text
ReadyBank
```

`CURRENT_KERNEL_RECEIVED` est un signal de réception du noyau courant ; ce signal autorise la création du **Blueprint suivant**. Il ne transforme pas l’ancien Blueprint en entrée KRP.

---

# 6. Slots Blueprint permanents

Le Blueprint possède trois sections fonctionnelles permanentes.

```text
SECTION 1 — CRÉATION INTELLECTUELLE
SECTION 2 — CRÉATION GAMEPLAY
SECTION 3 — TRADUCTION
```

Ces sections appartiennent au **même** Blueprint canonique.

## 6.1 Section 1 — Création intellectuelle

Slots :

```text
blueprint_id

depth
domain

subdomain_active
subject_active
dominant_idea_active

kernel_code
```

Ownership :

| Slot | Propriétaire d’écriture |
|---|---|
| `blueprint_id` | KernelBlueprintFactory |
| `depth` | KernelRotationPlanner |
| `domain` | KernelRotationPlanner |
| `subdomain_active` | Taxonomy |
| `subject_active` | Taxonomy |
| `dominant_idea_active` | Taxonomy |
| `kernel_code` | QuestionIntent, mécanisme d’implantation KernelCodeEngine |

## 6.2 Section 2 — Création gameplay

Le Blueprint réserve exactement sept CognitiveSlots :

```text
qcm_recognition
qcm_reasoning
qcm_deceptive_trap
tf_recognition_true
tf_recognition_false
tf_reasoning_true
tf_reasoning_false
```

Chaque CognitiveSlot est un conteneur permanent destiné au payload gameplay correspondant :

```text
question
+
réponse(s)
+
Saviez-vous (SV)
```

`01_KernelBlueprint` verrouille l’existence, l’identité et l’ownership structurel de ces sept conteneurs.

Le schéma métier détaillé de leur payload appartient à `06_Phase1` et ne doit pas être inventé dans le Blueprint.

## 6.3 Section 3 — Traduction

Pour chacun des sept CognitiveSlots, le Blueprint réserve un contrat de traduction correspondant couvrant :

```text
question
+
réponse(s)
+
Saviez-vous (SV)
```

La traduction ne remplace jamais le contenu source de Section 2.

`01_KernelBlueprint` verrouille la présence de la couche de traduction et la correspondance 1:1 avec les sept CognitiveSlots.

Le schéma linguistique détaillé, les langues obligatoires et les champs techniques exacts appartiennent à `08_Phase2`.

## 6.4 Ce qui n’est pas un slot Blueprint

Sont explicitement externes :

- réservoirs Taxonomy ;
- SubjectBank ;
- IdeaBank ;
- FAIL Banks ;
- LOOKBACK ;
- curseurs ;
- occurrence de bassin ;
- `cycle_target` ;
- `cycle_completed` ;
- états de rotation KRP ;
- état global `PRODUCTION_ON_HOLD` ;
- copies de travail Quarantine.

---

# 7. Données internes

KernelBlueprint ne possède aucune donnée métier cachée concurrente aux slots canoniques.

Ses données internes se limitent à :

- l’identité `blueprint_id` ;
- les valeurs présentes dans ses slots permanents ;
- les indicateurs techniques strictement nécessaires pour faire respecter présence, ownership, write-once et lifecycle structurel ;
- les références/états de contenu que les modules propriétaires ont contractuellement le droit de déposer dans les conteneurs aval.

Aucun réservoir moteur ne peut être déplacé dans le Blueprint pour simplifier une implantation.

---

# 8. Mécanismes

## 8.1 KernelBlueprintFactory

Responsabilité : créer une **nouvelle** enveloppe canonique et lui attribuer son `blueprint_id`.

La création doit être atomique vis-à-vis de l’unicité d’un Blueprint actif.

Décisions récupérées compatibles : DEC-035, DEC-058, DEC-059.

## 8.2 Initialisation de l’identité

L’identité est initialisée une seule fois.

```text
initializeBlueprintId(blueprint_id)
```

Le nom technique peut évoluer lors de l’implantation uniquement si le contrat reste identique : identité créée par Factory, immuable, non réattribuable.

## 8.3 Écriture Rotation

Écriture logique atomique :

```text
fillRotation(depth, domain)
```

Les deux valeurs appartiennent au même groupe d’ownership KRP.

Il est interdit de laisser un état normal où seul `depth` ou seul `domain` aurait été écrit par cette opération.

## 8.4 Écriture Taxonomy

Écriture logique atomique :

```text
fillTaxonomy(subdomain, subject, dominantIdea)
```

Correspondance obligatoire avec DEC-106 :

```text
IdeaSlot sélectionné
=
dominant_idea_active écrit
=
IdeaSlot CONSUMED
```

`CONSUMED` n’est autorisé qu’après réussite de l’écriture `fillTaxonomy(...)`.

ReadyBank ne confirme pas cette consommation.

## 8.5 Écriture kernel_code

```text
fillKernelCode(kernelCode)
```

KRP n’écrit jamais `kernel_code`.

## 8.6 Écritures Sections 2 et 3

Le Blueprint doit fournir un mécanisme contrôlé permettant aux propriétaires futurs de remplir les conteneurs Section 2 et Section 3 sans écriture arbitraire.

La spécification 01 impose :

- owner unique ;
- conteneur ciblé explicitement ;
- absence d’écriture libre sur les autres slots ;
- conservation de l’identité du Blueprint ;
- impossibilité d’utiliser cette interface pour modifier Section 1 hors contrat Quarantine.

Le nom des méthodes techniques est déterminé lors des spécifications propriétaires 06/08 et de l’audit d’implantation ; il n’est pas une décision métier de 01.

---

# 9. Communication inter-modules

## 9.1 Factory → KRP

```text
KernelBlueprintFactory
↓
nouveau Blueprint avec blueprint_id
↓
KernelRotationPlanner
```

KRP ne crée pas l’enveloppe.

## 9.2 KRP → Taxonomy

Taxonomy lit :

```text
depth
domain
```

## 9.3 Taxonomy → QuestionIntent

QuestionIntent lit :

```text
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

## 9.4 Section 1 → Section 2

Phase1 reçoit le territoire intellectuel complet nécessaire à sa création gameplay.

## 9.5 Section 2 → Section 3

Phase2 traduit les contenus admissibles selon son contrat futur sans écraser la source.

## 9.6 ReadyBank → prochain Blueprint

```text
Blueprint courant reçu par ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
orchestration / frontière de création
↓
KernelBlueprintFactory crée un nouveau Blueprint
↓
KRP travaille sur ce nouveau Blueprint
```

Interdit :

```text
ReadyBank
↓
ancien Blueprint
↓
KRP le réécrit
```

---

# 10. Contrats

## 10.1 Contrat d’identité

- `blueprint_id` identifie l’enveloppe canonique ;
- il est généré par Factory ;
- il est immuable ;
- `kernel_code` n’est pas l’identité du Blueprint ;
- `rotation_identifier` n’est pas réintroduit.

## 10.2 Contrat write-once normal

Les groupes structurels Section 1 sont write-once dans le chemin normal :

```text
blueprint_id
rotation
Taxonomy
kernel_code
```

Un second remplissage normal est refusé même si la nouvelle valeur est identique.

Une correction Quarantine future passe par un contrat distinct et contrôlé.

## 10.3 Contrat d’atomicité logique

```text
fillRotation
→ depth + domain ensemble ou aucune modification

fillTaxonomy
→ subdomain_active + subject_active + dominant_idea_active ensemble ou aucune modification
```

## 10.4 Contrat d’ordre normal

Ordre structurel de Section 1 :

```text
blueprint_id
↓
depth + domain
↓
subdomain_active + subject_active + dominant_idea_active
↓
kernel_code
```

Un module aval ne doit pas fabriquer les préconditions manquantes d’un module amont.

## 10.5 Contrat de lecture

Les slots peuvent être lus par les modules autorisés lorsque leur contrat entrant l’exige.

Lecture ne signifie jamais ownership d’écriture.

## 10.6 Contrat de non-réutilisation

Une fois un Blueprint engagé pour une rotation, il ne reçoit jamais une autre rotation.

Le noyau suivant possède un nouveau `blueprint_id`.

---

# 11. États

`01_KernelBlueprint` possède exactement les états structurels suivants :

```text
CREATED_UNENGAGED
ENGAGED_IN_PIPELINE
```

## CREATED_UNENGAGED

Le Blueprint existe et possède son identité canonique, mais Taxonomy n’a pas encore réussi sa première écriture.

Il peut déjà posséder `depth + domain`.

## ENGAGED_IN_PIPELINE

La première écriture Taxonomy réussie engage le Blueprint dans son parcours de noyau.

## États explicitement externes

Ne sont pas des états structurels de KernelBlueprint :

```text
PRODUCTION_ON_HOLD
VISIBLE
ESTOMPÉ
BLOCKED
DOMAIN_EXHAUSTED
DEPTH_EXHAUSTED
```

Les états détaillés des CognitiveSlots et TranslationSlots appartiennent aux modules de création/validation correspondants. Le Blueprint les transporte lorsqu’ils seront spécifiés ; il n’en devient pas l’autorité métier.

---

# 12. Transitions

## 12.1 Création

```text
ABSENT
↓ KernelBlueprintFactory.create()
CREATED_UNENGAGED
```

## 12.2 Rotation

```text
CREATED_UNENGAGED
↓ fillRotation(depth, domain)
CREATED_UNENGAGED
```

La rotation ne suffit pas à déclarer le noyau engagé intellectuellement.

## 12.3 Taxonomy

```text
CREATED_UNENGAGED
↓ fillTaxonomy(...) réussi
ENGAGED_IN_PIPELINE
```

## 12.4 Suite du pipeline

`fillKernelCode`, Phase1, validations, Phase2 et ReadyBank ne créent pas une nouvelle identité Blueprint.

## 12.5 Noyau suivant

```text
ReadyBank reçoit le Blueprint courant
↓
CURRENT_KERNEL_RECEIVED
↓
KernelBlueprintFactory
↓
NOUVEAU blueprint_id
↓
CREATED_UNENGAGED
```

L’ancien Blueprint reste l’enveloppe canonique de son propre noyau.

---

# 13. Cas limites

## 13.1 Création concurrente

Deux créations actives concurrentes ne doivent jamais produire deux Blueprints actifs.

La persistance doit garantir l’unicité atomiquement.

## 13.2 Écriture directe

Toute tentative d’écrire directement un slot structurel hors mécanisme propriétaire doit être refusée.

## 13.3 Double écriture

Deuxième appel normal à un groupe déjà rempli : refus.

Aucune surécriture silencieuse.

## 13.4 Écriture partielle

Une opération groupée échoue entièrement si elle ne peut pas écrire toutes les valeurs obligatoires.

Aucune demi-rotation et aucun triplet Taxonomy partiel.

## 13.5 Ordre invalide

Un module aval ne peut pas compenser l’absence d’un contrat amont.

Exemple : QuestionIntent ne fabrique pas un triplet Taxonomy absent.

## 13.6 Échec fillTaxonomy

Si `fillTaxonomy(...)` échoue :

- le Blueprint ne passe pas à `ENGAGED_IN_PIPELINE` ;
- l’IdeaSlot n’est pas marqué `CONSUMED` à cause de cette tentative ;
- Taxonomy conserve la responsabilité de reprendre conformément à son contrat.

## 13.7 Production globalement en pause

`PRODUCTION_ON_HOLD` ne doit jamais être écrit comme état du Blueprint.

Un Blueprint créé mais non engagé reste structurellement `CREATED_UNENGAGED` tant qu’il n’est pas engagé ou qu’une politique opérationnelle externe ne décide pas autrement selon le module propriétaire.

## 13.8 Réception ReadyBank répétée

L’idempotence du signal `CURRENT_KERNEL_RECEIVED` appartient à la frontière ReadyBank/orchestration et non à une réécriture du Blueprint reçu.

Le même Blueprint ne doit jamais déclencher deux créations effectives du noyau suivant.

## 13.9 Quarantine

Les méthodes write-once normales ne peuvent pas être détournées pour corriger silencieusement un slot déjà rempli.

La future correction passe par le contrat contrôlé de `10_Quarantine`.

## 13.10 Réservoirs

Aucun besoin d’implantation ne justifie l’ajout dans le Blueprint d’une SubjectBank, IdeaBank, FAIL Bank, curseur ou historique complet.

---

# 14. Persistance

## 14.1 Identité

Le Blueprint est persisté sous son `blueprint_id` immuable.

## 14.2 Unicité active

La persistance doit empêcher atomiquement la coexistence illégitime de plusieurs Blueprints actifs dans le régime séquentiel actuel.

Le mécanisme historique DEC-035 — vérification applicative + contrainte PostgreSQL atomique — est compatible avec ce contrat et devra être audité contre le code réel.

## 14.3 Write-once

La persistance ne doit pas permettre de contourner les gardes write-once de l’objet métier.

## 14.4 Atomicité des groupes

Les écritures rotation et Taxonomy doivent être persistables sans exposer d’état partiel comme état canonique normal.

## 14.5 Données externes

Les données internes des autres moteurs restent persistées dans leurs propres espaces.

Le Blueprint ne duplique pas ces sources de vérité.

---

# 15. Validation architecturale

La spécification 01 est valide uniquement si toutes les affirmations suivantes sont vraies :

- Factory crée le Blueprint avant KRP ;
- KRP écrit seulement `depth + domain` ;
- Taxonomy écrit seulement son triplet ;
- QuestionIntent/KernelCodeEngine écrit seulement `kernel_code` dans Section 1 ;
- `blueprint_id` est immuable ;
- Section 1 est write-once dans le chemin normal ;
- les 7 CognitiveSlots permanents existent ;
- la couche Translation correspond 1:1 aux 7 CognitiveSlots ;
- le Blueprint ne possède aucun réservoir moteur ;
- Quarantine et ReadyBank ne deviennent pas des sections de création ;
- ReadyBank ne recycle pas l’ancien Blueprint vers KRP ;
- `CURRENT_KERNEL_RECEIVED` ouvre la création du Blueprint suivant ;
- `PRODUCTION_ON_HOLD` n’est pas un état Blueprint ;
- DEC-106 est respectée pour l’écriture/consommation Taxonomy.

Résultat de reconstruction documentaire : **PASS**.

---

# 16. Tests contractuels obligatoires pour l’audit/implantation

## Factory / identité

1. création → `blueprint_id` présent ;
2. deux créations concurrentes → une seule création active autorisée ;
3. deuxième initialisation de `blueprint_id` → refus ;
4. écriture directe de `blueprint_id` → refus.

## Rotation

5. premier `fillRotation(depth, domain)` valide → succès ;
6. `depth` et `domain` deviennent lisibles ensemble ;
7. deuxième `fillRotation` → refus ;
8. aucun état partiel `depth` seul/domain seul après échec.

## Taxonomy

9. premier `fillTaxonomy(...)` valide → succès ;
10. le triplet devient lisible ensemble ;
11. deuxième `fillTaxonomy` → refus ;
12. aucune écriture partielle après échec ;
13. succès Taxonomy → transition `CREATED_UNENGAGED → ENGAGED_IN_PIPELINE` ;
14. échec Taxonomy → aucune transition d’engagement ;
15. IdeaSlot consommé = IdeaSlot écrit ;
16. aucun `CONSUMED` si l’écriture Blueprint échoue.

## kernel_code

17. premier `fillKernelCode` valide → succès ;
18. deuxième `fillKernelCode` → refus ;
19. KRP ne peut pas écrire `kernel_code`.

## Ownership / encapsulation

20. écriture directe externe d’un slot structurel → refus ;
21. lecture autorisée avant/après remplissage ne donne aucun droit d’écriture ;
22. absence de Banks/cycle data dans le contrat Blueprint.

## Pipeline / nouveau noyau

23. Blueprint reçu par ReadyBank conserve son identité ;
24. `CURRENT_KERNEL_RECEIVED` n’entraîne aucune nouvelle rotation sur ce Blueprint ;
25. noyau suivant → nouveau `blueprint_id` ;
26. KRP travaille sur le nouveau Blueprint ;
27. `PRODUCTION_ON_HOLD` n’est jamais enregistré comme état structurel Blueprint.

## Sections aval

28. les sept CognitiveSlots permanents sont présents ;
29. chaque CognitiveSlot possède une correspondance de traduction ;
30. aucun remplissage Section 2/3 ne peut modifier Section 1 par écriture libre.

Les tests de contenu détaillé Phase1, validations, traductions et Quarantine seront ajoutés par leurs modules propriétaires sans redéfinir la structure permanente de 01.

---

# 17. Architecture Register

Décisions structurantes récupérées et compatibles avec v2.0 :

```text
DEC-034  immutabilité/write-once
DEC-035  atomicité de création
DEC-058  Blueprint créé avant KRP
DEC-059  blueprint_id canonique
DEC-068  KernelCodeEngine hors KRP
DEC-106  consommation exacte après fillTaxonomy réussi
```

Décision de fermeture documentaire :

```text
DEC-113 — Spécification KernelBlueprint v2.0 verrouillée
```

Cette réécriture ne restaure aucune ancienne décision `KRP crée le Blueprint` et ne restaure aucune boucle de réutilisation du même Blueprint.

---

# 18. Statut terminal de spécification

| Rubrique obligatoire | État |
|---|---:|
| Mission | 100 % |
| Responsabilités | 100 % |
| Interdictions | 100 % |
| Entrées | 100 % |
| Sorties | 100 % |
| Slots Blueprint | 100 % |
| Données internes | 100 % |
| Mécanismes | 100 % |
| Communication | 100 % |
| Contrats | 100 % |
| États | 100 % |
| Transitions | 100 % |
| Cas limites | 100 % |
| Persistance | 100 % |
| Validation | 100 % |
| Tests contractuels | 100 % |
| Architecture | 100 % |

```text
Architecture : 100 %
Contrat :      100 %

STATUT DOCUMENTAIRE :
VERROUILLÉ v2.0
```

Prochaine étape autorisée :

```text
AUDIT-01-00
↓
audit du code réel contre cette spécification
↓
découpage des écarts en micro-blocs IMPL-01-XX
```

Aucune réécriture de `02_KernelRotationPlanner` et aucune implantation KRP v3.3 ne doivent commencer avant la fermeture implantation + validation de `01_KernelBlueprint` selon la méthode officielle en vigueur.