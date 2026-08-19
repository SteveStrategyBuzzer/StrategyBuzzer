# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.1.0-recovery  
**Date :** 2026-08-19  
**Statut :** ACTIF — VÉRITÉ GLOBALE COURANTE  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Réécriture complète de récupération. Elle harmonise des décisions déjà actives et ne crée aucune nouvelle décision métier. L’historique intellectuel reste dans `00_ArchitectureRegister.md` et `archive/`.

---

# 1. Hiérarchie des sources de vérité

Ordre obligatoire :

```text
00_ConstitutionCognitive.md
↓
00_ArchitectureRegister.md
↓
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
↓
spécifications verrouillées concernées
↓
working/ du SEUL module actif
↓
code réel, seulement après verrouillage
```

`00_CURRENT_HANDOFF.md` indique le point exact de reprise entre deux chats mais ne possède aucune autorité architecturale propre.

`archive/` n’est jamais une source active.

Principe :

```text
UNE RESPONSABILITÉ
=
UN PROPRIÉTAIRE
=
UNE IMPLÉMENTATION ACTIVE
=
UNE SOURCE DE VÉRITÉ
```

---

# 2. Méthode officielle

Toujours :

```text
Idée métier
↓
Architecture
↓
Spécification
↓
Architecture Register
↓
Audit du code
↓
Implantation
↓
Validation
```

Jamais :

```text
Code existant
↓
Architecture supposée
```

Une seule spécification est travaillée à la fois. Avant chaque module, tous les contrats verrouillés précédents sont reconstruits.

---

# 3. Roadmap officielle

```text
01_KernelBlueprint
↓
02_KernelRotationPlanner
↓
03_Taxonomy
↓
04_ValidationDominantIdeas
↓
05_QuestionIntent
↓
06_Phase1
↓
07_ValidationPhase1
↓
08_Phase2
↓
09_ValidationPhase2
↓
10_Quarantine
↓
11_ReadyBank
```

---

# 4. Tableau de bord courant

| Module | Architecture | Contrat | Implémentation | Validation | Statut documentaire actif |
|---|---:|---:|---:|---:|---|
| 01 KernelBlueprint | 100 % historique verrouillé | 100 % historique verrouillé | 100 % selon état historique | complète selon état historique | reconstruction canonique actuelle à certifier avant promotion |
| 02 KernelRotationPlanner | v3.2 historique verrouillée ; v3.3 à reconstruire | v3.3 à réécrire | ancienne implémentation à auditer après v3.3 | à refaire contre v3.3 | **EN RÉVISION v3.3** |
| 03 Taxonomy | **100 %** | **100 %** | **0 % à auditer/adapter** | **0 % code** | **SPÉCIFICATION v1.0 VERROUILLÉE** |
| 04 ValidationDominantIdeas | brides actives | brides actives | non | non | **À SPÉCIFIER** |
| 05 QuestionIntent | **100 %** | **100 %** | **100 %** | **100 %** | **v1.0 VERROUILLÉ selon certificat terminal récupéré** |
| 06 Phase1 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 07 ValidationPhase1 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 08 Phase2 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 09 ValidationPhase2 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 10 Quarantine | frontière connue | non verrouillé | à auditer plus tard | non | À VENIR |
| 11 ReadyBank | frontière connue | non verrouillé | à auditer plus tard | non | À VENIR |

Le statut de `05_QuestionIntent` ne doit plus être rétrogradé à « À SPÉCIFIER ». Son fichier canonique original reste à récupérer ; cela est un problème documentaire, pas un blocker architectural.

---

# 5. Pipeline global actif

```text
KernelBlueprintFactory
↓
KernelBlueprint canonique
↓
KernelRotationPlanner
↓
Taxonomy
  ↳ Gemini utilise les règles ValidationDominantIdeas pendant la création des Dominant Ideas
↓
QuestionIntent
↓
Phase1
↓
ValidationPhase1
├── FAIL → Quarantine
↓ PASS
Phase2
↓
ValidationPhase2
├── FAIL → Quarantine
↓ PASS
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
KernelBlueprintFactory crée le Blueprint suivant
↓
KRP remplit la prochaine rotation
```

Correction majeure de frontière : **KRP ne crée pas le Blueprint.** `KernelBlueprintFactory` crée l’enveloppe canonique avant KRP.

---

# 6. KernelBlueprint — contrat global actif

Le Blueprint est l’enveloppe canonique persistante d’un noyau. Il ne décide rien, ne crée aucun contenu intellectuel et ne possède aucun réservoir Taxonomy.

## 6.1 Création

```text
KernelBlueprintFactory
↓
création unique
↓
blueprint_id immuable
↓
Blueprint remis à KRP
```

Le Blueprint n’est jamais recyclé pour une nouvelle rotation.

## 6.2 Section 1 — Création intellectuelle

Slots structurels actifs :

```text
blueprint_id            → KernelBlueprintFactory

depth                   → KernelRotationPlanner
domain                  → KernelRotationPlanner

subdomain_active        → Taxonomy
subject_active          → Taxonomy
dominant_idea_active    → Taxonomy

kernel_code             → QuestionIntent, propriétaire fonctionnel
                          KernelCodeEngine = mécanisme d’implantation
```

Écritures normales :

```text
fillRotation(depth, domain)
fillTaxonomy(subdomain, subject, dominantIdea)
fillKernelCode(kernelCode)
```

Les slots structurels sont write-once dans le chemin normal. Une correction future par Quarantine doit passer par un contrat contrôlé distinct ; elle ne transforme pas le Blueprint en objet librement modifiable.

## 6.3 Sections aval

Section 2 — création gameplay : 7 CognitiveSlots :

```text
qcm_recognition
qcm_reasoning
qcm_deceptive_trap
tf_recognition_true
tf_recognition_false
tf_reasoning_true
tf_reasoning_false
```

Chaque slot porte question + réponse(s) + Saviez-vous.

Section 3 — traduction : traductions des questions, réponses et Saviez-vous des 7 CognitiveSlots.

Quarantine et ReadyBank sont extérieurs aux trois sections de création du Blueprint.

## 6.4 Lifecycle structurel actif

```text
CREATED_UNENGAGED
↓ première écriture Taxonomy réussie
ENGAGED_IN_PIPELINE
```

`PRODUCTION_ON_HOLD` n’est pas un état du Blueprint.

---

# 7. KernelRotationPlanner — frontière active v3.3

La v3.2 demeure historique. La prochaine spécification canonique doit être une réécriture complète v3.3.

## 7.1 Responsabilité

KRP décide exclusivement le prochain :

```text
Depth + Domain
```

Il écrit uniquement :

```text
Blueprint.depth
Blueprint.domain
```

Il ne choisit jamais Subdomain, Subject ou Dominant Idea et ne lit pas les Banks Taxonomy.

## 7.2 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → retour vers le prochain Depth encore nécessaire
```

## 7.3 Domaines de création

```text
Géographie
Histoire
Faune
Art
Sport
Cinéma
Cuisine
Science
```

`Général` n’est pas un domaine de création intellectuelle.

## 7.4 Besoins globaux DepthNeedMatrix

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100

cycle_remaining = max(0, cycle_target - cycle_completed)
```

Double autorité :

- Taxonomy constate l’épuisement intellectuel réel du Tour courant ;
- DepthNeedMatrix conserve les besoins quantitatifs globaux ;
- KRP combine rotation + besoins + signaux officiels.

## 7.5 Horloge d’un Tour de Depth

Dans un Tour :

```text
VISIBLE
↓ DOMAIN_EXHAUSTED valide et persisté
ESTOMPÉ
```

Aucun retour `ESTOMPÉ → VISIBLE` dans le même Tour.

Un Tour ultérieur du même Depth est une nouvelle occurrence ; ses 8 Domaines peuvent repartir VISIBLE sans constituer une régression historique.

Quand les 8 Domaines du Tour sont terminés :

```text
Taxonomy → DEPTH_EXHAUSTED(depth)
↓
KRP persiste la fermeture
↓
cycle_completed[depth] += 1 exactement une fois
↓
prochain Depth encore nécessaire
```

`DEPTH_EXHAUSTED` = fin d’un Tour, **pas** satisfaction globale du Depth.

`PRODUCTION_ON_HOLD` est permis uniquement lorsque toutes les cibles globales sont satisfaites et qu’aucune transition n’est en attente de persistance.

## 7.6 Persistance / idempotence

- signal identique déjà appliqué → `NO-OP` ;
- 1 tentative initiale + 3 retries de persistance ;
- `KRP-002 DOMAIN_EXHAUSTED_PERSIST_FAILED` ;
- `KRP-003 DEPTH_EXHAUSTED_PERSIST_FAILED` ;
- échec terminal → état opérationnel `BLOCKED` ;
- aucune nouvelle rotation/Blueprint engagé tant que la transition requise n’est pas commitée.

Décisions structurantes : DEC-094, DEC-095 frontière, DEC-108, DEC-111.

---

# 8. Taxonomy — v1.0 VERROUILLÉE

Source canonique : `specifications/03_Taxonomy.md`.

Taxonomy reçoit le `Depth + Domain` fixé. Elle possède les réservoirs et la progression intellectuelle internes.

## 8.1 Occurrence de bassin

Un bassin est contextualisé par :

```text
Depth + occurrence du Tour de Depth + Domain
```

L’occurrence reste interne à Taxonomy ; aucun slot Blueprint supplémentaire.

Chaque occurrence possède **un seul Subdomain officiel**.

## 8.2 Création du territoire

Un même travail intellectuel Gemini doit produire un territoire viable :

```text
1 Subdomain
+
1..50 Subjects PASS
```

50 = maximum, jamais quota à remplir artificiellement.

Subject PASS → persistant.  
Subject FAIL → éphémère dans l’appel courant.

## 8.3 Dominant Ideas

Les Subjects sont préparés en lots équilibrés, capacité actuelle 10 par appel.

Gemini utilise le mécanisme/règles `ValidationDominantIdeas` **pendant** la création intellectuelle des Dominant Ideas.

Préparation réussie d’un Subject accepté :

```text
1..5 Dominant Ideas PASS
```

`0 PASS` = anomalie de préparation, pas état normal complet.

Taxonomy possède les mémoires PASS/FAIL et les exclusions anti-doublon. `ValidationDominantIdeas` ne possède pas ces Banks.

LOOKBACK-2 est cyclique et traverse `Depth 10 → nouveau Depth 2`.

Identité PASS contextualisée :

```text
Depth + Domain + Subdomain + Subject + Dominant Idea
```

## 8.4 Consommation exacte

Invariant :

```text
IdeaSlot sélectionné
=
dominant_idea_active écrit
=
IdeaSlot marqué CONSUMED
```

`CONSUMED` est persisté immédiatement après `fillTaxonomy(...)` réussi. ReadyBank ne confirme pas cette consommation.

## 8.5 Épuisement

Avant `DOMAIN_EXHAUSTED` :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Sinon :

```text
TAX-003 DOMAIN_EXHAUSTION_BLOCKED_REMAINING_CONTENT
```

Après les 8 Domaines du Tour :

```text
DEPTH_EXHAUSTED(depth)
```

Taxonomy ne modifie jamais `cycle_target` ou `cycle_completed`.

## 8.6 Gemini technique

- 1 tentative initiale + 3 retries par opération ;
- erreur technique → aucun effet métier ;
- 3 opérations intellectuelles distinctes ayant chacune épuisé leurs 4 tentatives → `INTELLECTUAL_CREATION_UNAVAILABLE` + `BLOCKED` Admin/Ops ;
- une réussite remet le compteur consécutif à zéro.

`BLOCKED` ≠ `DOMAIN_EXHAUSTED` ≠ `DEPTH_EXHAUSTED` ≠ `PRODUCTION_ON_HOLD`.

---

# 9. ValidationDominantIdeas — brides actives

Statut : **À SPÉCIFIER complètement**.

Frontières déjà actives :

- VDI possède les règles intellectuelles de création/contrôle des Dominant Ideas ;
- ces règles sont utilisées par Gemini dans le travail orchestré par Taxonomy ;
- VDI ne lit ni n’écrit directement le Blueprint ;
- VDI ne possède ni SubjectBank, ni IdeaBank, ni mémoire anti-doublon ;
- Taxonomy fournit le contexte/mémoires/exclusions applicables ;
- la dominance doit être contextuelle au `Subdomain + Subject + DepthContract` ;
- le mécanisme participe au contrat d’exploitabilité `1..5 PASS` d’un Subject accepté.

Il est interdit de réintroduire l’ancienne architecture « génération libre → deuxième moteur autonome VDI ».

---

# 10. QuestionIntent — v1.0 verrouillé

Le certificat terminal récupéré établit :

```text
Architecture    100 %
Contrat         100 %
Implémentation  100 %
Validation      100 %
BLOCKERS        AUCUN
```

Entrée canonique :

```text
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

Seule écriture Blueprint :

```text
kernel_code
```

QuestionIntent ne recrée ni ne valide Taxonomy et ne modifie aucun des cinq champs intellectuels amont.

Le certificat récupéré indique également une identité de forme :

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

avec suffixe base36 sur 4 caractères.

Le fichier canonique original `05_QuestionIntent.md` reste à récupérer. **Ne pas le réécrire de mémoire et ne pas rétrograder son statut.**

---

# 11. Frontières aval déjà connues

## 11.1 Phase1

Responsabilité future : remplir les 7 CognitiveSlots avec questions + réponse(s) + Saviez-vous selon les contrats de création gameplay. Détail non verrouillé ici.

## 11.2 ValidationPhase1

Valide la création Phase1. Les contenus explicitement FAIL peuvent entrer en Quarantine selon le futur contrat détaillé. Ne pas inventer davantage avant 07.

## 11.3 Phase2

Crée les traductions dans les TranslationSlots prévus. Détail non verrouillé ici.

## 11.4 ValidationPhase2

Valide la cohérence linguistique/sémantique/gameplay selon son futur contrat. Détail non verrouillé ici.

## 11.5 Quarantine

Nature déjà fixée : **station de correction**, pas moteur de création normal.

Elle travaille sur une représentation/copie contrôlée du contenu FAIL et doit retourner une correction vers le Blueprint canonique selon un contrat de reprise/versionnement encore à spécifier.

## 11.6 ReadyBank

Destination terminale du noyau canonique prêt.

```text
ReadyBank reçoit/stocke le noyau prêt
↓
CURRENT_KERNEL_RECEIVED
↓
KernelBlueprintFactory crée le prochain Blueprint
↓
KRP remplit depth + domain
```

ReadyBank ne décide jamais quelle Idea Taxonomy consomme et ne confirme pas la consommation d’un IdeaSlot.

---

# 12. Administration opérationnelle

La couche Admin/Ops est extérieure aux moteurs 01→11. Elle observe/persiste les incidents, expose l’état de production et autorise les reprises prévues par les contrats.

Elle ne possède aucun contenu intellectuel normal et ne transforme jamais un incident technique en épuisement métier.

Détails : `cross-module/AdminOperations_BRIDES_ACTIVE.md`.

---

# 13. Frontières explicitement interdites

```text
KRP ne crée pas le Blueprint
KRP ne crée pas de Subdomain
KRP ne choisit pas de Subject
KRP ne choisit pas de Dominant Idea
KRP n’infère pas DOMAIN_EXHAUSTED depuis ses propres compteurs

Taxonomy ne choisit pas le prochain Domain
Taxonomy ne choisit pas le prochain Depth
Taxonomy ne modifie pas cycle_target/cycle_completed
Taxonomy n’écrit pas kernel_code
Taxonomy ne consomme pas une autre Idea que celle écrite
Taxonomy ne crée pas plusieurs Subdomains dans une même occurrence

VDI ne lit/écrit pas directement le Blueprint
VDI ne possède pas les Banks Taxonomy

QuestionIntent ne modifie pas les cinq dimensions intellectuelles amont

ReadyBank ne choisit/valide pas la consommation Taxonomy
```

---

# 14. Points ouverts — NE PAS INVENTER

Ouverts :

1. certification/promotion de la reconstruction canonique actuelle de `01_KernelBlueprint` ;
2. réécriture complète + verrouillage de `02_KernelRotationPlanner` v3.3 ;
3. détail interne complet de `04_ValidationDominantIdeas` ;
4. récupération du **fichier** canonique `05_QuestionIntent.md` — son contrat/statut n’est pas ouvert ;
5. contrats complets 06→11 dans leur ordre officiel.

Fermés :

- ownership Factory → nouveau Blueprint → KRP ;
- Taxonomy v1.0 ;
- occurrence de bassin par Tour ;
- 1 Subdomain par occurrence ;
- SubjectBank max 50 sans remplissage forcé ;
- `1..5 PASS` par Subject préparé ;
- VDI utilisé par Gemini, non deuxième moteur autonome ;
- anti-doublon Taxonomy + LOOKBACK-2 ;
- consommation exacte immédiate ;
- `DOMAIN_EXHAUSTED` avec garde TAX-003 ;
- `DEPTH_EXHAUSTED` = fin d’un Tour ;
- `cycle_target/cycle_completed` = besoins quantitatifs globaux ;
- retries Gemini ;
- QuestionIntent v1.0 verrouillé selon certificat terminal récupéré.

---

# 15. Architecture Register structurant

Décisions actives de cette consolidation :

```text
DEC-094  fin d’un Tour Taxonomy vs besoin global DepthNeedMatrix/KRP
DEC-095  occurrence de bassin par Tour
DEC-096  un Subdomain par occurrence
DEC-097  création atomique Subdomain + SubjectBank
DEC-098  SubjectBank max 50 sans remplissage artificiel
DEC-099  Subject PASS persistant / FAIL éphémère
DEC-100  lots équilibrés
DEC-101  VDI utilisé par Gemini
DEC-102  1..5 Dominant Ideas PASS par Subject préparé
DEC-103  identité anti-doublon contextualisée
DEC-104  LOOKBACK-2 cyclique
DEC-105  FAIL Bank DI persistante
DEC-106  consommation exacte immédiate
DEC-107  DOMAIN_EXHAUSTED + TAX-003
DEC-108  DEPTH_EXHAUSTED = fin d’un Tour
DEC-109  retry Gemini 1+3
DEC-110  BLOCKED après échecs intellectuels consécutifs
DEC-111  persistance/idempotence KRP
DEC-112  Taxonomy v1.0 verrouillée
```

`DEC-088` est `SUPERSEDED` par `DEC-094`.

---

# 16. Références actives

```text
START_HERE.md
00_ConstitutionCognitive.md
00_ArchitectureRegister.md
00_CURRENT_HANDOFF.md
00_DOCUMENTATION_MAP.md

specifications/03_Taxonomy.md

working/01_KernelBlueprint/01_KernelBlueprint_RECONSTRUCTION_ACTIVE.md
working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
working/04_ValidationDominantIdeas/04_ValidationDominantIdeas_BRIDES_ACTIVE.md

certificates/03_Taxonomy/
certificates/05_QuestionIntent/

cross-module/AdminOperations_BRIDES_ACTIVE.md
```

Aucun document d’`archive/` ne doit être utilisé pour réintroduire une architecture supersédée.
