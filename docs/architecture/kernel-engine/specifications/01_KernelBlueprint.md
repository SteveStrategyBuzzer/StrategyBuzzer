# StrategyBuzzer — 01_KernelBlueprint

**Version :** 2.1  
**Date :** 2026-08-19  
**Statut documentaire :** **VERROUILLÉ**  
**Architecture :** **100 %**  
**Contrat :** **100 %**  
**Implémentation :** hors statut de ce document — audit v2.1 obligatoire avant toute correction de code  
**Validation code :** à refaire contre v2.1 après audit/implantation

> Réécriture canonique complète de la spécification KernelBlueprint. Cette version remplace la reconstruction active et harmonise les décisions encore valides avec la frontière actuelle `KernelBlueprintFactory → nouveau KernelBlueprint → KernelRotationPlanner`. Le code historique ne définit pas cette architecture.

---

# 1. Mission

`KernelBlueprint` est la **structure canonique persistante, extérieure aux phases**, d’un noyau pendant son passage dans le pipeline StrategyBuzzer. Créé une seule fois par `KernelBlueprintFactory` (KBP), il est progressivement rempli sous le même `blueprint_id` et demeure l’unique source de vérité.

Il ne circule pas comme objet entre modules et aucune copie n’est autoritaire. Le relais inter-phase est minimal : `blueprint_id`, fin confirmée de la phase précédente et statut terminal de cette phase. Chaque phase retrouve alors le même Blueprint persistant, lit ses préconditions persistées, écrit exclusivement les slots de son ownership, persiste son résultat, puis signale sa propre fin.

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

Le Blueprint identifié par ReadyBank n’est jamais recyclé vers KRP.

---

# 2. Responsabilités

KernelBlueprint doit :

1. fournir une identité canonique immuable `blueprint_id` ;
2. conserver les slots permanents du noyau ;
3. exposer les valeurs aux lecteurs autorisés sans donner un droit d’écriture libre ;
4. faire respecter l’ownership des écritures ;
5. garantir le write-once des groupes structurels dans le chemin normal ;
6. conserver la même identité pendant tout le pipeline ;
7. conserver la Section 1 — création intellectuelle ;
8. réserver la Section 2 — création gameplay ;
9. réserver la Section 3 — traduction ;
10. rester distinct des réservoirs, curseurs, banques et mécanismes internes des moteurs ;
11. permettre la réconciliation contrôlée dans ReadyBank avec une copie complète corrigée par Quarantine, explicitement non canonique, sans rendre la structure canonique librement réinscriptible ;
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
- contenir un champ, flag ou cible de mode `production`, `test`, `target` ou équivalent ;
- être fourni, sérialisé ou reconstruit comme objet Blueprint de relais entre phases.

---

# 4. Entrées

## 4.1 Création

`KernelBlueprintFactory` reçoit une demande de création autorisée par l’orchestration du pipeline et crée, une seule fois, la nouvelle structure canonique persistante. La demande et le relais de création ne contiennent pas un objet Blueprint.

La création persiste atomiquement :

```text
blueprint_id
```

## 4.2 Écritures des propriétaires

Après leur lookup persistant, les propriétaires écrivent ensuite, dans l’ordre normal du pipeline :

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

Les moteurs de validation et Quarantine font évoluer les états/contenus uniquement selon les contrats propriétaires 07, 09 et 10 ainsi que DEC-122 ; `01_KernelBlueprint` conserve leur structure persistante sans devenir l’autorité de validation.

---

# 5. Sorties

KernelBlueprint ne produit pas une décision métier en sortie.

Sa sortie n’est jamais un objet Blueprint. Son état persistant est enrichi progressivement, avec la même identité canonique.

Chaque module aval reçoit seulement le relais minimal, retrouve le même noyau courant par lookup persistant sur `blueprint_id`, puis lit uniquement les slots nécessaires.

Destination terminale :

```text
ReadyBank
```

`CURRENT_KERNEL_RECEIVED` est un signal terminal référencé par `blueprint_id` ; ce signal autorise la création du **Blueprint suivant**. Il ne transforme pas l’ancien Blueprint en entrée KRP.

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

`01_KernelBlueprint` verrouille l’existence, l’identité et l’ownership structurel de ces sept conteneurs. Les sept slots réels existent dès la création persistante, y compris lorsqu’ils sont encore vides.

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

Les modes d’exécution sont entièrement extérieurs : l’orchestrateur sélectionne les relais et récepteurs externes de production ou de test. Aucun état de mode, de test, de cible ou de scénario ne réside dans le Blueprint.

---

# 8. Mécanismes

## 8.1 KernelBlueprintFactory

Responsabilité : créer atomiquement la **nouvelle structure persistante canonique** et lui attribuer son `blueprint_id`.

La création doit être atomique vis-à-vis de l’unicité d’un Blueprint actif.

KBP est aussi l’unique frontière de fixture : pour un scénario demandé, il crée le vrai Blueprint PostgreSQL isolé, déjà préparé pour la phase ciblée. Toutes les préconditions de cette phase et les vrais sept CognitiveSlots existent dès la création atomique. Une fixture KBP n’est ni mock, ni tableau, ni mémoire, ni copie Quarantine. Elle n’est pas récupérable ou modifiable par les workers de production non ciblés, mais reste accessible à la vraie phase autorisée par `blueprint_id`.

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

## 9.0 Règle universelle de relais et lookup

Entre deux phases, le message ne contient que :

```text
blueprint_id
+ phase précédente terminée
+ statut terminal de cette phase
```

Il ne contient aucun objet `KernelBlueprint`, aucun payload de slot et aucune copie autoritaire. La phase destinataire effectue son lookup persistant par `blueprint_id`, vérifie la fin et le statut terminal requis, lit les seules préconditions de son contrat, écrit seulement son ownership, persiste, puis émet son propre signal terminal. Un statut non terminal, absent ou incompatible interdit le démarrage de la phase suivante.

## 9.1 Factory → KRP

```text
KernelBlueprintFactory persiste le Blueprint
↓
relais : blueprint_id + Factory terminée + statut terminal
↓
KernelRotationPlanner retrouve le Blueprint persistant
```

KRP ne crée pas l’enveloppe.

## 9.2 KRP → Taxonomy

Après persistance de `fillRotation`, KRP relaie uniquement son `blueprint_id`, sa fin et son statut terminal. Taxonomy retrouve le Blueprint persistant.

Taxonomy lit :

```text
depth
domain
```

## 9.3 Taxonomy → QuestionIntent

Après persistance de `fillTaxonomy`, Taxonomy relaie uniquement son `blueprint_id`, sa fin et son statut terminal. QuestionIntent retrouve le Blueprint persistant.

QuestionIntent lit :

```text
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

## 9.4 Section 1 → Section 2

Après le relais terminal de QuestionIntent, Phase1 retrouve le Blueprint persistant et lit le territoire intellectuel complet nécessaire à sa création gameplay ; ce territoire n’est pas transporté.

## 9.5 Section 2 → Section 3

Après le relais terminal requis, Phase2 retrouve le Blueprint persistant et traduit les contenus admissibles selon son contrat futur sans écraser la source.

## 9.6 ReadyBank → prochain Blueprint

```text
ReadyBank retrouve le Blueprint courant par blueprint_id
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
↓ relais d’objet ancien Blueprint
KRP le réécrit
```

## 9.7 Phase1, validation et modes externes

Après lookup du `blueprint_id`, Phase1 écrit uniquement les CognitiveSlots de son ownership et persiste avant son signal terminal.

- une erreur technique de Phase1 (échec de fournisseur, persistance impossible, indisponibilité ou erreur d’exécution) termine Phase1 en `CREATION_FAILED` ;
- un contenu intellectuel produit et techniquement persistable termine Phase1 selon son statut de création, puis est relayé vers `ValidationPhase1` ;
- `ValidationPhase1` qualifie ce contenu ; une suspicion intellectuelle est `SUSPICION`, jamais `CREATION_FAILED`.

`CREATION_FAILED` et `SUSPICION` sont donc disjoints : le premier décrit une impossibilité technique de création/persistance ; le second un contenu existant, persisté et soumis à validation.

Les deux parcours sont configurés hors du Blueprint :

```text
production : relais Phase1 terminal → ValidationPhase1
test       : relais Phase1 terminal → récepteur terminal de test
```

Phase1 ne possède aucune variante métier de test : elle exécute le même contrat, les mêmes préconditions, écrit le même ownership persistant et signale le même statut terminal. Seul le routage externe après cette fin diffère.

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

Une correction Quarantine passe par un contrat distinct et contrôlé : copie complète non canonique, reprise ciblée du pipeline, puis application atomique des seules corrections autorisées au persistant canonique par ReadyBank.

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

Les états détaillés des CognitiveSlots et TranslationSlots appartiennent aux modules de création/validation correspondants. Le Blueprint les conserve de façon persistante lorsqu’ils seront spécifiés ; il n’en devient pas l’autorité métier.

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
ReadyBank retrouve le Blueprint courant par son identifiant
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

L’idempotence du signal `CURRENT_KERNEL_RECEIVED` appartient à la frontière ReadyBank/orchestration et non à une réécriture du Blueprint identifié.

Le même Blueprint ne doit jamais déclencher deux créations effectives du noyau suivant.

## 13.9 Quarantine

Les méthodes write-once normales ne peuvent pas être détournées pour corriger silencieusement un slot déjà rempli.

La correction passe par le contrat contrôlé de `10_Quarantine`. La copie Quarantine est une représentation de travail explicitement non canonique, distincte de la source de vérité persistante ; elle ne peut ni remplacer celle-ci ni devenir autoritaire. Seul `11_ReadyBank`, dans son ownership, peut appliquer au canonique les corrections ciblées autorisées.

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

## 14.6 Agrégat canonique et persistance Phase1

Le `KernelBlueprint` est l’unique agrégat canonique. Les tables qui le
persistent ne créent ni un second Blueprint ni une autre identité intellectuelle.

```text
kernel_blueprint_runs
└── Section 1 immuable

kernel_blueprint_cognitive_slots
└── exactement sept CognitiveSlots persistés séparément
```

`kernel_blueprint_runs` conserve la Section 1 sous le `blueprint_id`
immuable. Les sept slots sont persistés dans
`kernel_blueprint_cognitive_slots`, chacun étant identifié par :

```text
(blueprint_id, cognitive_type)
```

Une contrainte unique garantit une seule occurrence de chaque type cognitif
par Blueprint. L’écriture d’un slot est atomique et indépendante des six
autres slots. Les sept lignes appartiennent toujours au même agrégat
`KernelBlueprint`.

`question_intents.frame_en` est une structure legacy non autoritaire. Elle ne
constitue ni la source de vérité du Blueprint ni la persistance Phase1.
Phase1 ne réécrit jamais un frame global.

Phase1 ne persiste aucune traduction ni donnée joueur. Le masque joueur, le
mélange des choix et la position affichée restent externes au Blueprint.

## 14.7 Persistance de fixture KBP

Une fixture est créée exclusivement par KBP dans PostgreSQL isolé. La transaction de création insère le vrai Blueprint, ses sept lignes `kernel_blueprint_cognitive_slots` et toutes les préconditions réelles requises par la phase ciblée ; elle échoue entièrement sinon. Cette fixture est identifiée par son `blueprint_id` et son isolation est imposée par la frontière de test : aucun worker de production ne peut la découvrir, la récupérer ou l’écrire.

Le `Harness` ne construit ni ne persiste aucun Blueprint, précondition, contenu intellectuel ou validation. Il demande le scénario à KBP, reçoit le seul `blueprint_id`, déclenche la vraie phase avec son fournisseur simulé externe, intercepte le signal terminal, bloque la cascade externe, observe le persistant puis nettoie l’isolation. Le Harness n’est ni propriétaire d’écriture ni une voie de contournement de l’ownership.

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
- tout relais inter-phase contient uniquement `blueprint_id`, phase précédente terminée et statut terminal ;
- aucune phase ne reçoit un objet Blueprint, ni n’écrit hors de son ownership après lookup persistant ;
- les modes production et test restent externes au Blueprint ;
- `CREATION_FAILED` Phase1 et `SUSPICION` de validation sont disjoints.

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

23. Blueprint retrouvé par ReadyBank via son identifiant conserve son identité ;
24. `CURRENT_KERNEL_RECEIVED` n’entraîne aucune nouvelle rotation sur ce Blueprint ;
25. noyau suivant → nouveau `blueprint_id` ;
26. KRP travaille sur le nouveau Blueprint ;
27. `PRODUCTION_ON_HOLD` n’est jamais enregistré comme état structurel Blueprint.

## Sections aval

28. les sept CognitiveSlots permanents sont présents ;
29. chaque CognitiveSlot possède une correspondance de traduction ;
30. aucun remplissage Section 2/3 ne peut modifier Section 1 par écriture libre.

## Relais, KBP et Harness

31. chaque relais inter-phase contient exclusivement `blueprint_id`, fin de phase précédente et statut terminal ;
32. une phase sans fin terminale compatible de la phase précédente est refusée avant toute écriture ;
33. chaque phase retrouve le Blueprint PostgreSQL persistant et ne peut écrire que son ownership ;
34. KBP crée atomiquement une fixture PostgreSQL isolée avec les préconditions de la phase ciblée et les sept vrais CognitiveSlots ;
35. une fixture KBP n’est ni mock, ni tableau, ni mémoire, ni Quarantine et elle est inaccessible aux workers ;
36. Harness obtient uniquement l’identifiant de KBP, déclenche la vraie phase avec fournisseur simulé externe, intercepte sa fin, bloque la cascade, observe puis nettoie ;
37. Harness ne peut écrire ni précondition, ni contenu intellectuel, ni validation ;
38. erreur technique Phase1 → `CREATION_FAILED` ;
39. contenu intellectuel techniquement persistable Phase1 → `ValidationPhase1`, puis suspicion éventuelle → `SUSPICION` ;
40. production relaie Phase1 vers ValidationPhase1 et test vers le récepteur terminal externe, sans variante métier Phase1 ni champ de mode dans le Blueprint.

Les tests de contenu détaillé Phase1, validations, traductions et Quarantine seront ajoutés par leurs modules propriétaires sans redéfinir la structure permanente de 01.

---

# 17. Architecture Register

Décisions structurantes récupérées et compatibles avec v2.1 :

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
DEC-122 — Blueprint complet, copie Quarantine et fusion ciblée ReadyBank
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
VERROUILLÉ v2.1
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

---

# 19. Blueprint complet, copie Quarantine et réconciliation ReadyBank — DEC-122

## 19.1 Un seul Blueprint canonique

Le même Blueprint canonique contient progressivement :

```text
1 identité intellectuelle
+
7 CognitiveSlots dans la langue source
+
pour chaque CognitiveSlot :
  question
  réponse correcte
  choix
  SV
+
pour chaque langue supplémentaire :
  traduction de la question
  traduction de la réponse correcte
  traduction des choix
  traduction du SV
```

Une traduction n’est jamais un nouveau CognitiveSlot. Elle est une représentation linguistique du même CognitiveSlot dans le même Blueprint.

## 19.2 Parcours du canonique

Le Blueprint canonique poursuit toutes les phases normales jusqu’à ReadyBank, y compris lorsqu’une suspicion a provoqué la création d’une copie Quarantine.

```text
Blueprint canonique
→ Phase1
→ ValidationPhase1
→ Phase2 / Traductions
→ ValidationPhase2
→ ReadyBank
```

Quarantine ne retire pas le canonique du pipeline et ne suspend pas globalement sa progression.

Les slots suspects ou dépendants non créés ne deviennent cependant jamais exploitables par le gameplay avant leur correction et leur fusion validée.

## 19.3 Copie complète Quarantine

Après lookup persistant par `blueprint_id` et signal de `SUSPICION`, Quarantine crée une copie complète du Blueprint tel qu’il existe au moment de la suspicion. Cette copie est explicitement non canonique, distincte de la source de vérité persistante et ne constitue pas un objet Blueprint relayé entre phases.

La copie conserve :

- l’identité complète;
- les sept CognitiveSlots;
- leurs contenus source;
- toutes les traductions déjà produites;
- les slots vides;
- les résultats SV;
- les références nécessaires à la réconciliation avec le canonique.

Les éléments soupçonnés sont identifiés par des marqueurs structurés permettant à l’interface de les afficher en rouge. La couleur n’est pas l’état métier persistant.

Les éléments valides restent visibles normalement.

Les créations dépendantes qui n’ont pas pu être produites sont marquées comme non créées ou bloquées, jamais comme réussies.

## 19.4 Reprise ciblée de la copie

Après correction, la copie complète reprend le pipeline au propriétaire du premier slot corrigé.

```text
erreur création source
→ correction dans la copie
→ reprise Phase1 pour le CognitiveSlot ciblé
→ validations
→ traductions de ce CognitiveSlot
→ validations
→ ReadyBank

erreur traduction
→ correction dans la copie
→ reprise Phase2 pour la langue et le CognitiveSlot ciblés
→ validation traduction
→ ReadyBank
```

Les slots déjà valides ne sont pas recréés.

## 19.5 Réconciliation dans ReadyBank

ReadyBank retrouve le Blueprint canonique par `blueprint_id` et examine la copie corrigée non canonique selon la référence de réconciliation.

ReadyBank vérifie l’identité canonique puis, de façon contrôlée :

- applique au persistant canonique les corrections des slots explicitement soupçonnés et corrigés ;
- applique les corrections des valeurs ciblées ;
- applique au canonique le remplissage des slots restés vides ;
- conserve sans modification tous les slots valides non ciblés;
- refuse une copie qui ne correspond pas au même `blueprint_id` ;
- conserve la traçabilité avant/après;
- ne rend le contenu concerné exploitable qu’après réussite des validations requises.

La copie ne devient jamais un second noyau canonique, ne remplace jamais le persistant canonique et ne reçoit jamais un nouveau `kernel_code`.

## 19.6 Clé de ciblage

Toute suspicion ou correction cible au minimum :

```text
blueprint_id
+ kernel_code
+ cognitive_slot
+ couche source ou traduction
+ langue si traduction
+ chemin(s) de champ soupçonné(s)
```

Exemples :

```text
cognitive_slots.QCM_RECOGNITION.source.question

cognitive_slots.QCM_RECOGNITION.translations.el.answer
```

## 19.7 Invariants

- un seul Blueprint canonique;
- une copie Quarantine complète, explicitement non canonique et distincte;
- le canonique termine son parcours jusqu’à ReadyBank;
- la copie corrigée reprend uniquement le travail nécessaire;
- aucune traduction d’un contenu source non validé;
- aucune recréation des slots déjà valides;
- fusion uniquement dans ReadyBank;
- remplacement/correction/remplissage uniquement des slots ciblés ou vides;
- même `blueprint_id` et même `kernel_code`;
- aucune exposition gameplay d’un slot suspect, vide ou non validé.

Référence propriétaire :

```text
06_Phase1
08_Phase2
10_Quarantine
11_ReadyBank
DEC-122
```
